<?php

namespace Tests\Feature\V2;

use App\Enums\LoanStatus;
use App\Models\ApplicantAccount;
use App\Models\Application;
use App\Models\ApplicationDecision;
use App\Models\BankAccount;
use App\Models\DecisionPolicy;
use App\Models\Loan;
use App\Models\Person;
use App\Models\PersonEmployment;
use App\Models\Product;
use App\Models\RiskAssessment;
use App\Models\Tenant;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Services\Decision\PhoneRiskGateService;
use App\Services\Webhook\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * E2E del onboarding a través del motor de decisión, la oferta con rango, la
 * aceptación, el Loan y los webhooks. Cola sync: al hacer submit el motor
 * decide inline. Escenarios happy y bad path. Plan documentado en
 * docs/testing/onboarding-e2e.md.
 */
class OnboardingE2ETest extends TestCase
{
    use RefreshDatabase;

    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake(['*' => Http::response('ok', 200)]); // webhooks salientes no salen a red

        $this->tenant = Tenant::factory()->create([
            'slug' => 'mc-e2e', 'is_active' => true,
            'features' => ['loan_portfolio' => true, 'external_disbursement' => true],
        ]);

        $this->product = Product::factory()->create([
            'tenant_id' => $this->tenant->id, 'is_active' => true, 'code' => 'MC-E2E',
            'min_amount' => 300, 'max_amount' => 15000, 'min_term_months' => 1, 'max_term_months' => 1,
            'interest_rate' => 36, 'opening_commission' => 13,
            'onboarding_steps' => [['id' => 'salary_range', 'type' => 'select']], // dynamic → submit no exige docs
            'rules' => ['term_in_days' => true, 'min_term_days' => 1, 'max_term_days' => 30, 'annual_rate' => 36, 'opening_commission' => 13],
        ]);

        $this->tenantPolicy();
        $this->productPolicy();
    }

    // ===================== Fixtures =====================

    private function tenantPolicy(string $mode = 'ACTIVE'): DecisionPolicy
    {
        return DecisionPolicy::updateOrCreate(
            ['tenant_id' => $this->tenant->id, 'product_id' => null, 'version' => 1],
            [
                'mode' => $mode, 'is_active' => true,
                'rules' => [
                    'phone_risk_gate' => ['enabled' => true, 'flag_from' => 401, 'block_from' => 601, 'fail_mode' => 'open'],
                    'cooldown' => ['days' => 30],
                ],
            ]
        );
    }

    private function productPolicy(string $mode = 'ACTIVE'): DecisionPolicy
    {
        return DecisionPolicy::updateOrCreate(
            ['tenant_id' => $this->tenant->id, 'product_id' => $this->product->id, 'version' => 1],
            [
                'mode' => $mode, 'is_active' => true,
                'rules' => [
                    'scoring' => [
                        'variables' => [
                            ['key' => 'salary_range', 'points' => ['LT_3000' => 0, 'R_6001_9000' => 10, 'R_12001_15000' => 20, 'GT_15000' => 25]],
                            ['key' => 'employment_type', 'points' => ['EMPLOYEE' => 15, 'UNEMPLOYED' => 0]],
                        ],
                        'band_cutoffs' => [
                            ['min_score' => 15, 'band' => 'BASE'],
                            ['min_score' => 35, 'band' => 'INTERMEDIA'],
                            ['min_score' => 50, 'band' => 'CONTROLADA'],
                        ],
                    ],
                    'bands' => [
                        ['key' => 'BASE', 'min_amount' => 300, 'max_amount' => 400],
                        ['key' => 'INTERMEDIA', 'min_amount' => 500, 'max_amount' => 600],
                        ['key' => 'CONTROLADA', 'min_amount' => 700, 'max_amount' => 800],
                    ],
                    'first_credit' => ['term_days' => 7],
                    'offer' => ['validity_hours' => 72, 'reminder_hours_before' => 24],
                    'review' => ['input_timeout_minutes' => 30],
                    'reject' => [['rule' => 'kyc_failed'], ['rule' => 'identity_mismatch']],
                    'graduation' => [
                        'levels' => [['level' => 0, 'max_amount' => 1000, 'max_term_days' => 7], ['level' => 1, 'max_amount' => 2000, 'max_term_days' => 10]],
                        'advance' => ['on_time' => 1, 'late_or_extension' => 0, 'max_late_days_for_auto' => 5],
                    ],
                ],
            ]
        );
    }

    /** Persona que completó el onboarding. $income y $employment definen su banda. */
    private function onboardedPerson(float $income = 16000, string $employment = 'EMPLOYEE', string $kyc = 'VERIFIED', ?int $phoneScore = 180): array
    {
        $person = Person::factory()->create(['tenant_id' => $this->tenant->id, 'kyc_status' => $kyc]);
        PersonEmployment::create([
            'tenant_id' => $this->tenant->id, 'person_id' => $person->id,
            'employment_type' => $employment, 'monthly_income' => $income, 'is_current' => true,
        ]);
        BankAccount::create([
            'tenant_id' => $this->tenant->id, 'entity_type' => 'persons', 'entity_id' => $person->id,
            'bank_name' => 'STP', 'clabe' => '646180157099999993', 'holder_name' => $person->full_name,
            'is_for_disbursement' => true, 'is_primary' => true,
        ]);
        $account = ApplicantAccount::factory()->create([
            'tenant_id' => $this->tenant->id, 'person_id' => $person->id,
        ]);
        if ($phoneScore !== null) {
            RiskAssessment::create([
                'tenant_id' => $this->tenant->id, 'account_id' => $account->id, 'person_id' => $person->id,
                'type' => RiskAssessment::TYPE_PHONE_RISK, 'status' => RiskAssessment::STATUS_COMPLETED,
                'score' => $phoneScore, 'level' => $phoneScore < 300 ? 'low' : 'moderate',
            ]);
        }
        $token = $account->createToken('e2e', ['applicant'])->plainTextToken;

        return [$person, $account, $token];
    }

    private function auth(string $token): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withHeader('X-Tenant-ID', $this->tenant->slug)->withHeader('Authorization', "Bearer {$token}");
    }

    private function makeApp(string $token, float $amount, int $termDays = 7): string
    {
        $res = $this->auth($token)->postJson('/api/v2/applicant/applications', [
            'product_id' => $this->product->id, 'amount' => $amount,
            'term_months' => 1, 'requested_term_days' => $termDays,
        ]);
        $res->assertCreated();

        return $res->json('data.id');
    }

    private function submitApp(string $token, string $id): \Illuminate\Testing\TestResponse
    {
        return $this->auth($token)->postJson("/api/v2/applicant/applications/{$id}/submit");
    }

    private function respondOffer(string $token, string $id, array $payload): \Illuminate\Testing\TestResponse
    {
        return $this->auth($token)->postJson("/api/v2/applicant/applications/{$id}/counter-offer/respond", $payload);
    }

    private function webhookEndpoint(): WebhookEndpoint
    {
        return WebhookEndpoint::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Cartera', 'url' => 'https://cartera.example/wh',
            'secret' => 'whsec_e2e', 'events' => ['*'], 'is_active' => true,
        ]);
    }

    // =====================================================
    // HAPPY PATHS
    // =====================================================

    public function test_h1_onboarding_a_credito_autorizado_con_oferta_de_rango(): void
    {
        $this->webhookEndpoint();
        [$person, $account, $token] = $this->onboardedPerson(income: 16000, employment: 'EMPLOYEE'); // 25+15=40 → INTERMEDIA

        $id = $this->makeApp($token, amount: 550);
        $this->submitApp($token, $id)->assertOk();

        // El motor decidió inline: oferta de rango automática.
        $app = Application::withoutGlobalScopes()->find($id);
        $this->assertSame(Application::STATUS_COUNTER_OFFERED, $app->status);
        $this->assertSame('ENGINE', $app->counter_offer['source']);
        // Rango = [mínimo del producto -> cupo de la banda INTERMEDIA]
        $this->assertEquals(300, $app->counter_offer['min_amount']);
        $this->assertEquals(600, $app->counter_offer['max_amount']);

        // El cliente acepta un monto dentro del rango.
        $this->respondOffer($token, $id, ['accepted' => true, 'amount' => 550, 'term_days' => 7])->assertOk();
        $app->refresh();
        $this->assertSame(Application::STATUS_APPROVED, $app->status);
        $this->assertEquals(550, $app->approved_amount);

        // Se creó el Loan pendiente de dispersión (cartera externa) y se emitió el webhook.
        $loan = Loan::withoutGlobalScopes()->where('application_id', $id)->first();
        $this->assertNotNull($loan);
        $this->assertSame(LoanStatus::PENDING_DISBURSEMENT, $loan->status);
        $this->assertTrue(
            WebhookDelivery::withoutGlobalScopes()->where('event', WebhookEvent::APPLICATION_APPROVED)->exists(),
            'Debió emitirse el webhook application.approved'
        );
    }

    public function test_h2_monto_solicitado_excede_cupo_genera_contraoferta(): void
    {
        [$person, $account, $token] = $this->onboardedPerson(income: 7000, employment: 'EMPLOYEE'); // 10+15=25 → BASE ($300-400)

        $id = $this->makeApp($token, amount: 15000); // pide mucho más que su cupo
        $this->submitApp($token, $id)->assertOk();

        $app = Application::withoutGlobalScopes()->find($id);
        $this->assertSame(Application::STATUS_COUNTER_OFFERED, $app->status);
        $this->assertEquals(400, $app->counter_offer['max_amount']); // cupo BASE
        $this->assertEquals(400, $app->counter_offer['amount']);      // pre-selección = tope
    }

    public function test_h3_renovacion_automatica_al_liquidar(): void
    {
        [$person, $account, $token] = $this->onboardedPerson();
        // Crédito activo liquidado puntual → oferta de renovación (nivel 1).
        $origin = Application::create([
            'tenant_id' => $this->tenant->id, 'product_id' => $this->product->id,
            'applicant_type' => Application::TYPE_INDIVIDUAL, 'person_id' => $person->id,
            'requested_amount' => 400, 'requested_term_months' => 1, 'status' => Application::STATUS_SYNCED,
        ]);
        $loan = Loan::create([
            'tenant_id' => $this->tenant->id, 'application_id' => $origin->id, 'person_id' => $person->id,
            'applicant_account_id' => $account->id, 'principal_amount' => 400, 'interest_rate' => 36, 'term_days' => 7,
            'opening_commission_amount' => 52, 'total_to_pay' => 460, 'outstanding_balance' => 460,
            'paid_amount' => 0, 'late_fee_accrued' => 0, 'status' => LoanStatus::ACTIVE->value,
            'due_date' => now()->addDay(),
        ]);

        app(\App\Services\LoanService::class)->recordPayment($loan, ['amount' => 460, 'paid_at' => now()]);

        $renewal = Application::withoutGlobalScopes()->where('renewal_of_loan_id', $loan->id)->first();
        $this->assertNotNull($renewal, 'Debió crearse la solicitud de renovación');
        $this->assertSame(Application::STATUS_COUNTER_OFFERED, $renewal->status);
        $this->assertEquals(2000, $renewal->counter_offer['max_amount']); // nivel 1
    }

    // =====================================================
    // BAD PATHS
    // =====================================================

    public function test_b1_kyc_rechazado_es_rechazo_automatico(): void
    {
        $this->webhookEndpoint();
        [$person, $account, $token] = $this->onboardedPerson(kyc: 'REJECTED');

        $id = $this->makeApp($token, amount: 400);
        $this->submitApp($token, $id)->assertOk();

        $app = Application::withoutGlobalScopes()->find($id);
        $this->assertSame(Application::STATUS_REJECTED, $app->status);
        $this->assertTrue(WebhookDelivery::withoutGlobalScopes()->where('event', WebhookEvent::APPLICATION_REJECTED)->exists());
    }

    public function test_b2_perfil_muy_bajo_va_a_revision_manual(): void
    {
        [$person, $account, $token] = $this->onboardedPerson(income: 2000, employment: 'UNEMPLOYED'); // 0 pts < piso 15

        $id = $this->makeApp($token, amount: 400);
        $this->submitApp($token, $id)->assertOk();

        $app = Application::withoutGlobalScopes()->find($id);
        $this->assertSame(Application::STATUS_IN_REVIEW, $app->status);
        $this->assertNull($app->counter_offer); // sin oferta automática
    }

    public function test_b3_filtro_telefonico_bloquea_antes_de_ine(): void
    {
        [$person, $account, $token] = $this->onboardedPerson(phoneScore: 720); // zona de bloqueo (>=601)

        $outcome = app(PhoneRiskGateService::class)->check($account->fresh());

        $this->assertSame('BLOCK', $outcome);
        $this->assertTrue(
            ApplicationDecision::withoutGlobalScopes()->where('trigger', 'PHONE_GATE')->where('outcome', 'BLOCK')->exists()
        );
    }

    public function test_b4_cooldown_bloquea_nuevo_intento_tras_rechazo(): void
    {
        [$person, $account, $token] = $this->onboardedPerson();
        // Rechazo reciente.
        Application::create([
            'tenant_id' => $this->tenant->id, 'product_id' => $this->product->id,
            'applicant_type' => Application::TYPE_INDIVIDUAL, 'person_id' => $person->id,
            'requested_amount' => 400, 'requested_term_months' => 1,
            'status' => Application::STATUS_REJECTED, 'decision_at' => now()->subDays(5),
        ]);

        $res = $this->auth($token)->postJson('/api/v2/applicant/applications', [
            'product_id' => $this->product->id, 'amount' => 400, 'term_months' => 1, 'requested_term_days' => 7,
        ]);

        $res->assertStatus(409);
        $this->assertSame('APPLICATION_COOLDOWN', $res->json('error'));
    }

    public function test_b5_solicitud_duplicada_activa_es_409(): void
    {
        [$person, $account, $token] = $this->onboardedPerson();
        $this->makeApp($token, amount: 400); // ya tiene una activa (DRAFT)

        $res = $this->auth($token)->postJson('/api/v2/applicant/applications', [
            'product_id' => $this->product->id, 'amount' => 400, 'term_months' => 1, 'requested_term_days' => 7,
        ]);

        $res->assertStatus(409);
        $this->assertSame('APPLICATION_EXISTS', $res->json('error'));
    }

    public function test_b6_aceptar_monto_fuera_del_rango_es_422(): void
    {
        [$person, $account, $token] = $this->onboardedPerson(income: 7000, employment: 'EMPLOYEE'); // BASE $300-400
        $id = $this->makeApp($token, amount: 400);
        $this->submitApp($token, $id)->assertOk();

        // Intenta aceptar $900, fuera del rango BASE.
        $this->respondOffer($token, $id, ['accepted' => true, 'amount' => 900, 'term_days' => 7])->assertStatus(422);
        $this->assertSame(Application::STATUS_COUNTER_OFFERED, Application::withoutGlobalScopes()->find($id)->status);
    }

    public function test_b7_aceptar_oferta_expirada_es_422(): void
    {
        [$person, $account, $token] = $this->onboardedPerson(income: 7000, employment: 'EMPLOYEE');
        $id = $this->makeApp($token, amount: 400);
        $this->submitApp($token, $id)->assertOk();

        // Forzar expiración del snapshot.
        $app = Application::withoutGlobalScopes()->find($id);
        $co = $app->counter_offer;
        $co['expires_at'] = now()->subMinute()->toIso8601String();
        $app->update(['counter_offer' => $co]);

        $this->respondOffer($token, $id, ['accepted' => true, 'amount' => 350, 'term_days' => 7])->assertStatus(422);
    }

    public function test_b8_modo_sombra_no_actua(): void
    {
        $this->productPolicy('SHADOW');
        [$person, $account, $token] = $this->onboardedPerson(income: 16000, employment: 'EMPLOYEE');

        $id = $this->makeApp($token, amount: 550);
        $this->submitApp($token, $id)->assertOk();

        $app = Application::withoutGlobalScopes()->find($id);
        $this->assertSame(Application::STATUS_SUBMITTED, $app->status); // el flujo sigue manual
        $decision = ApplicationDecision::withoutGlobalScopes()->where('application_id', $id)->first();
        $this->assertNotNull($decision);
        $this->assertFalse($decision->executed);
        $this->assertSame('OFFER', $decision->outcome); // lo que HABRÍA hecho
    }

    public function test_b9_sin_politica_activa_flujo_queda_manual(): void
    {
        DecisionPolicy::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->update(['is_active' => false]);
        [$person, $account, $token] = $this->onboardedPerson();

        $id = $this->makeApp($token, amount: 400);
        $this->submitApp($token, $id)->assertOk();

        $this->assertSame(Application::STATUS_SUBMITTED, Application::withoutGlobalScopes()->find($id)->status);
        $this->assertSame(0, ApplicationDecision::withoutGlobalScopes()->where('application_id', $id)->count());
    }
}
