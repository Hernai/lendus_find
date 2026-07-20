<?php

namespace Tests\Feature\V2;

use App\Enums\LoanStatus;
use App\Jobs\DecideApplicationJob;
use App\Jobs\EvaluateRenewalJob;
use App\Models\Address;
use App\Models\ApplicantAccount;
use App\Models\Application;
use App\Models\ApplicationDecision;
use App\Models\DecisionPolicy;
use App\Models\Loan;
use App\Models\LoanExtension;
use App\Models\Person;
use App\Models\Product;
use App\Models\RiskAssessment;
use App\Models\Tenant;
use App\Services\ApplicationService;
use App\Services\Decision\DecisionEngineService;
use App\Services\Decision\DecisionInputCollector;
use App\Services\Decision\PhoneRiskGateService;
use App\Services\LoanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Motor de decisión (cambio OpenSpec motor-decision-configurable): shadow vs
 * active, salidas OFFER/REVIEW/REJECT, contraoferta automática por exceso de
 * cupo, aceptación dentro de rango, y renovaciones con graduación.
 */
class DecisionEngineTest extends TestCase
{
    use RefreshDatabase;

    protected Product $product;
    protected Person $person;
    protected ApplicantAccount $account;
    protected string $applicantToken;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();

        $this->tenant = Tenant::factory()->create(['slug' => 'test-engine', 'is_active' => true]);

        $this->product = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
            'min_amount' => 300,
            'max_amount' => 15000,
            'min_term_months' => 1,
            'max_term_months' => 1,
            'interest_rate' => 36,
            'opening_commission' => 13,
            'rules' => [
                'min_amount' => 300,
                'max_amount' => 15000,
                'min_term_days' => 1,
                'max_term_days' => 30,
                'default_term_days' => 10,
                'annual_rate' => 36,
                'opening_commission' => 13,
                'amortization_type' => 'BULLET',
                'term_in_days' => true,
            ],
        ]);

        $this->person = Person::factory()->create([
            'tenant_id' => $this->tenant->id,
            'kyc_status' => 'VERIFIED',
        ]);
        $this->account = ApplicantAccount::factory()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);
        $this->applicantToken = $this->account->createToken('test', ['applicant'])->plainTextToken;
    }

    private function applicantAuth(): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withHeader('X-Tenant-ID', $this->tenant->slug)
            ->withHeader('Authorization', "Bearer {$this->applicantToken}");
    }

    private function productPolicy(string $mode = 'ACTIVE', array $overrides = []): DecisionPolicy
    {
        return DecisionPolicy::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'version' => 1,
            'mode' => $mode,
            'is_active' => true,
            'rules' => array_replace_recursive([
                'scoring' => [
                    'variables' => [
                        ['key' => 'salary_range', 'points' => ['GT_15000' => 40, 'R_6001_9000' => 10]],
                    ],
                    'band_cutoffs' => [
                        ['min_score' => 0, 'band' => 'BASE'],
                        ['min_score' => 35, 'band' => 'INTERMEDIA'],
                    ],
                ],
                'bands' => [
                    ['key' => 'BASE', 'min_amount' => 300, 'max_amount' => 400],
                    ['key' => 'INTERMEDIA', 'min_amount' => 500, 'max_amount' => 600],
                ],
                'first_credit' => ['term_days' => 7],
                'offer' => ['validity_hours' => 72, 'reminder_hours_before' => 24],
                'review' => ['input_timeout_minutes' => 30],
                'reject' => [['rule' => 'kyc_failed']],
                'graduation' => [
                    'levels' => [
                        ['level' => 0, 'max_amount' => 1000, 'max_term_days' => 7],
                        ['level' => 1, 'max_amount' => 2000, 'max_term_days' => 10],
                    ],
                    'advance' => ['on_time' => 1, 'late_or_extension' => 0, 'max_late_days_for_auto' => 5],
                ],
            ], $overrides),
        ]);
    }

    private function makeSubmitted(array $overrides = []): Application
    {
        return Application::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'applicant_type' => Application::TYPE_INDIVIDUAL,
            'person_id' => $this->person->id,
            'submitted_by_account_id' => $this->account->id,
            'requested_amount' => 350,
            'requested_term_months' => 1,
            'requested_term_days' => 7,
            'status' => Application::STATUS_SUBMITTED,
            'submitted_at' => now(),
        ], $overrides));
    }

    private function runDecisionJob(Application $app): void
    {
        (new DecideApplicationJob($app->id))->handle(
            app(ApplicationService::class),
            app(DecisionEngineService::class),
            app(DecisionInputCollector::class),
            app(PhoneRiskGateService::class),
        );
    }

    // =====================================================
    // Salidas del motor
    // =====================================================

    public function test_shadow_evalua_sin_tocar_el_estado(): void
    {
        $this->productPolicy('SHADOW');
        $app = $this->makeSubmitted();

        $this->runDecisionJob($app);

        $app->refresh();
        $this->assertSame(Application::STATUS_SUBMITTED, $app->status);
        $this->assertNull($app->counter_offer);

        $decision = ApplicationDecision::withoutGlobalScopes()->where('application_id', $app->id)->first();
        $this->assertNotNull($decision);
        $this->assertFalse($decision->executed);
        $this->assertSame('OFFER', $decision->outcome);
        $this->assertSame('SHADOW', $decision->mode);
    }

    public function test_active_crea_oferta_de_rango_con_lo_solicitado_preseleccionado(): void
    {
        $this->productPolicy('ACTIVE');
        $app = $this->makeSubmitted(['requested_amount' => 350]);

        $this->runDecisionJob($app);

        $app->refresh();
        $this->assertSame(Application::STATUS_COUNTER_OFFERED, $app->status);
        $offer = $app->counter_offer;
        $this->assertSame('ENGINE', $offer['source']);
        $this->assertSame('system', $offer['offered_by']);
        $this->assertEquals(300, $offer['min_amount']);
        $this->assertEquals(400, $offer['max_amount']);
        $this->assertEquals(350, $offer['amount']); // lo solicitado cabe → pre-seleccionado
        $this->assertEquals(7, $offer['term_days']);
        $this->assertNotNull($offer['expires_at']);
        $this->assertNotNull($offer['reminder_at']);
    }

    public function test_contraoferta_automatica_cuando_lo_solicitado_excede_el_cupo(): void
    {
        $this->productPolicy('ACTIVE');
        $app = $this->makeSubmitted(['requested_amount' => 900]);

        $this->runDecisionJob($app);

        $app->refresh();
        $this->assertSame(Application::STATUS_COUNTER_OFFERED, $app->status);
        $this->assertEquals(400, $app->counter_offer['amount']); // pre-selección = cupo
        $this->assertEquals(400, $app->counter_offer['max_amount']);

        $decision = ApplicationDecision::withoutGlobalScopes()->where('application_id', $app->id)->first();
        $rules = array_column($decision->rule_hits, 'rule');
        $this->assertContains('counter_offer', $rules);
    }

    public function test_kyc_rechazado_produce_rechazo_automatico(): void
    {
        $this->productPolicy('ACTIVE');
        $this->person->update(['kyc_status' => 'REJECTED']);
        $app = $this->makeSubmitted();

        $this->runDecisionJob($app);

        $app->refresh();
        $this->assertSame(Application::STATUS_REJECTED, $app->status);
        $this->assertSame('MOTOR_DECISION', $app->rejection_reason);
        $this->assertNull($app->decision_by); // actor system, no staff
    }

    public function test_flag_del_gate_telefonico_fuerza_revision_manual(): void
    {
        $tenantPolicy = DecisionPolicy::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => null,
            'version' => 1,
            'mode' => 'ACTIVE',
            'is_active' => true,
            'rules' => [
                'phone_risk_gate' => ['enabled' => true, 'flag_from' => 401, 'block_from' => 601, 'fail_mode' => 'open'],
                'cooldown' => ['days' => 30],
            ],
        ]);
        // Score en zona de alerta → el gate registra FLAG
        RiskAssessment::create([
            'tenant_id' => $this->tenant->id,
            'account_id' => $this->account->id,
            'person_id' => $this->person->id,
            'type' => RiskAssessment::TYPE_PHONE_RISK,
            'status' => RiskAssessment::STATUS_COMPLETED,
            'score' => 450,
            'level' => 'moderate',
        ]);
        app(PhoneRiskGateService::class)->check($this->account->fresh());

        $this->productPolicy('ACTIVE');
        $app = $this->makeSubmitted();

        $this->runDecisionJob($app);

        $app->refresh();
        $this->assertSame(Application::STATUS_IN_REVIEW, $app->status);
        $this->assertNull($app->counter_offer);
    }

    public function test_timeout_de_insumos_manda_a_revision(): void
    {
        $this->productPolicy('ACTIVE');
        $this->person->update(['kyc_status' => 'PENDING']); // insumo faltante
        $app = $this->makeSubmitted(['submitted_at' => now()->subMinutes(45)]); // timeout (30) agotado

        $this->runDecisionJob($app);

        $app->refresh();
        $this->assertSame(Application::STATUS_IN_REVIEW, $app->status);

        $decision = ApplicationDecision::withoutGlobalScopes()->where('application_id', $app->id)->first();
        $this->assertSame('REVIEW', $decision->outcome);
        $this->assertContains('kyc', $decision->outcome_detail['missing']);
    }

    public function test_sin_politica_el_flujo_sigue_manual(): void
    {
        $app = $this->makeSubmitted();

        $this->runDecisionJob($app);

        $app->refresh();
        $this->assertSame(Application::STATUS_SUBMITTED, $app->status);
        $this->assertSame(0, ApplicationDecision::withoutGlobalScopes()->where('application_id', $app->id)->count());
    }

    // =====================================================
    // Aceptación de oferta con rango
    // =====================================================

    private function makeRangeOffered(): Application
    {
        $this->productPolicy('ACTIVE');
        $app = $this->makeSubmitted(['requested_amount' => 900]);
        $this->runDecisionJob($app);

        return $app->fresh();
    }

    public function test_acepta_dentro_del_rango_con_monto_elegido_y_evidencia(): void
    {
        $app = $this->makeRangeOffered();

        $response = $this->applicantAuth()->postJson(
            "/api/v2/applicant/applications/{$app->id}/counter-offer/respond",
            ['accepted' => true, 'amount' => 350, 'term_days' => 7]
        );

        $response->assertOk();
        $app->refresh();
        $this->assertSame(Application::STATUS_APPROVED, $app->status);
        $this->assertEquals(350, $app->approved_amount);
        $this->assertEquals(7, $app->approved_term_days);
        $acceptance = $app->counter_offer['acceptance'];
        $this->assertEquals(350, $acceptance['chosen_amount']);
        $this->assertNotEmpty($acceptance['ip']);
        $this->assertNotEmpty($acceptance['accepted_at']);
    }

    public function test_monto_fuera_del_rango_es_422(): void
    {
        $app = $this->makeRangeOffered();

        $response = $this->applicantAuth()->postJson(
            "/api/v2/applicant/applications/{$app->id}/counter-offer/respond",
            ['accepted' => true, 'amount' => 450]
        );

        $response->assertStatus(422);
        $this->assertSame(Application::STATUS_COUNTER_OFFERED, $app->fresh()->status);
    }

    public function test_cliente_viejo_sin_monto_acepta_la_preseleccion(): void
    {
        $app = $this->makeRangeOffered();

        $response = $this->applicantAuth()->postJson(
            "/api/v2/applicant/applications/{$app->id}/counter-offer/respond",
            ['accepted' => true]
        );

        $response->assertOk();
        $this->assertEquals(400, $app->fresh()->approved_amount); // pre-selección del snapshot
    }

    public function test_aceptar_crea_loan_en_tenant_con_portafolio(): void
    {
        $this->tenant->update(['features' => ['loan_portfolio' => true]]);
        $app = $this->makeRangeOffered();

        $this->applicantAuth()->postJson(
            "/api/v2/applicant/applications/{$app->id}/counter-offer/respond",
            ['accepted' => true, 'amount' => 350, 'term_days' => 7]
        )->assertOk();

        $loan = Loan::withoutGlobalScopes()->where('application_id', $app->id)->first();
        $this->assertNotNull($loan);
        $this->assertEquals(350, $loan->principal_amount);
        $this->assertEquals(7, $loan->term_days);
    }

    // =====================================================
    // Recordatorio y expiración
    // =====================================================

    public function test_recordatorio_de_vencimiento_se_envia_una_sola_vez(): void
    {
        $app = $this->makeRangeOffered();

        // Simular que ya cruzó el umbral del recordatorio.
        $snapshot = $app->counter_offer;
        $snapshot['reminder_at'] = now()->subMinutes(5)->toIso8601String();
        $app->update(['counter_offer' => $snapshot]);

        $this->artisan('counter-offers:expire')->assertSuccessful();
        $first = $app->fresh()->counter_offer['reminder_sent_at'];
        $this->assertNotNull($first);

        $this->artisan('counter-offers:expire')->assertSuccessful();
        $this->assertSame($first, $app->fresh()->counter_offer['reminder_sent_at']);
    }

    public function test_oferta_del_motor_expira_a_cancelled(): void
    {
        $app = $this->makeRangeOffered();

        $snapshot = $app->counter_offer;
        $snapshot['expires_at'] = now()->subMinute()->toIso8601String();
        $app->update(['counter_offer' => $snapshot]);

        $this->artisan('counter-offers:expire')->assertSuccessful();

        $this->assertSame(Application::STATUS_CANCELLED, $app->fresh()->status);
    }

    // =====================================================
    // Renovaciones
    // =====================================================

    private function makeCompletedLoan(array $loanOverrides = [], ?\Carbon\Carbon $paidAt = null): Loan
    {
        $origin = $this->makeSubmitted([
            'status' => Application::STATUS_APPROVED,
            'approved_amount' => 350,
            'approved_term_days' => 7,
        ]);

        $loan = Loan::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'application_id' => $origin->id,
            'applicant_account_id' => $this->account->id,
            'person_id' => $this->person->id,
            'principal_amount' => 350,
            'interest_rate' => 36,
            'term_days' => 7,
            'opening_commission_amount' => 45.5,
            'total_to_pay' => 400,
            'outstanding_balance' => 400,
            'paid_amount' => 0,
            'late_fee_accrued' => 0,
            'status' => LoanStatus::ACTIVE->value,
            'disbursed_at' => now()->subDays(7),
            'due_date' => now()->toDateString(),
        ], $loanOverrides));

        app(LoanService::class)->recordPayment($loan, [
            'amount' => 400,
            'paid_at' => $paidAt ?? now(),
        ]);

        return $loan->fresh();
    }

    private function runRenewalJob(Loan $loan): void
    {
        (new EvaluateRenewalJob($loan->id))->handle(
            app(ApplicationService::class),
            app(DecisionEngineService::class),
        );
    }

    public function test_liquidacion_puntual_sube_de_nivel_y_crea_renovacion(): void
    {
        $this->productPolicy('ACTIVE');
        $loan = $this->makeCompletedLoan();
        $this->assertSame(LoanStatus::COMPLETED, $loan->status);
        Queue::assertPushed(EvaluateRenewalJob::class);

        // Cerrar la solicitud origen para que no bloquee la renovación.
        Application::withoutGlobalScopes()->whereKey($loan->application_id)
            ->update(['status' => Application::STATUS_SYNCED]);

        $this->runRenewalJob($loan);

        $renewal = Application::withoutGlobalScopes()
            ->where('renewal_of_loan_id', $loan->id)
            ->first();
        $this->assertNotNull($renewal);
        $this->assertSame(Application::STATUS_COUNTER_OFFERED, $renewal->status);
        $this->assertEquals(2000, $renewal->counter_offer['max_amount']); // nivel 1
        $this->assertEquals(10, $renewal->counter_offer['max_term_days']);
        $this->assertEquals(7, $renewal->counter_offer['min_term_days']);
    }

    public function test_prorroga_mantiene_el_nivel(): void
    {
        $this->productPolicy('ACTIVE');
        $loan = $this->makeCompletedLoan();
        LoanExtension::create([
            'tenant_id' => $this->tenant->id,
            'loan_id' => $loan->id,
            'days_added' => 7,
            'fee_amount' => 50,
            'previous_due_date' => now()->toDateString(),
            'new_due_date' => now()->addDays(7)->toDateString(),
            'status' => 'APPROVED',
            'requested_at' => now(),
            'approved_at' => now(),
        ]);
        Application::withoutGlobalScopes()->whereKey($loan->application_id)
            ->update(['status' => Application::STATUS_SYNCED]);

        $this->runRenewalJob($loan);

        $renewal = Application::withoutGlobalScopes()
            ->where('renewal_of_loan_id', $loan->id)
            ->first();
        $this->assertNotNull($renewal);
        $this->assertEquals(1000, $renewal->counter_offer['max_amount']); // se mantiene nivel 0
    }

    public function test_mora_relevante_no_genera_oferta_automatica(): void
    {
        $this->productPolicy('ACTIVE');
        $loan = $this->makeCompletedLoan([
            'due_date' => now()->subDays(12)->toDateString(), // pagó 12 días tarde
        ]);
        Application::withoutGlobalScopes()->whereKey($loan->application_id)
            ->update(['status' => Application::STATUS_SYNCED]);

        $this->runRenewalJob($loan);

        $this->assertNull(
            Application::withoutGlobalScopes()->where('renewal_of_loan_id', $loan->id)->first()
        );
        $decision = ApplicationDecision::withoutGlobalScopes()->where('loan_id', $loan->id)->first();
        $this->assertSame('NO_OFFER', $decision->outcome);
    }

    public function test_solicitud_activa_bloquea_renovacion(): void
    {
        $this->productPolicy('ACTIVE');
        $loan = $this->makeCompletedLoan();
        // La solicitud origen queda APPROVED (activa) → bloquea.

        $this->runRenewalJob($loan);

        $this->assertNull(
            Application::withoutGlobalScopes()->where('renewal_of_loan_id', $loan->id)->first()
        );
        $decision = ApplicationDecision::withoutGlobalScopes()->where('loan_id', $loan->id)->first();
        $this->assertSame('NO_OFFER', $decision->outcome);
    }

    public function test_renovacion_en_sombra_solo_registra(): void
    {
        $this->productPolicy('SHADOW');
        $loan = $this->makeCompletedLoan();
        Application::withoutGlobalScopes()->whereKey($loan->application_id)
            ->update(['status' => Application::STATUS_SYNCED]);

        $this->runRenewalJob($loan);

        $this->assertNull(
            Application::withoutGlobalScopes()->where('renewal_of_loan_id', $loan->id)->first()
        );
        $decision = ApplicationDecision::withoutGlobalScopes()->where('loan_id', $loan->id)->first();
        $this->assertSame('OFFER', $decision->outcome);
        $this->assertFalse($decision->executed);
    }

    // =====================================================
    // Regla de facematch (cambio facematch-motor-decision)
    // =====================================================

    public function test_facematch_no_coincide_va_a_revision(): void
    {
        $this->productPolicy('ACTIVE', ['face_match' => ['min_score' => 80]]);
        $this->person->update(['kyc_data' => ['face_match' => ['passed' => false, 'score' => 50]]]);
        $app = $this->makeSubmitted();

        $this->runDecisionJob($app);

        $app->refresh();
        $this->assertSame(Application::STATUS_IN_REVIEW, $app->status);
        $this->assertNull($app->counter_offer);

        $decision = ApplicationDecision::withoutGlobalScopes()->where('application_id', $app->id)->first();
        $this->assertSame('REVIEW', $decision->outcome);
        $this->assertContains('face_match_failed', array_column($decision->rule_hits, 'rule'));
    }

    public function test_facematch_ausente_va_a_revision(): void
    {
        $this->productPolicy('ACTIVE', ['face_match' => ['min_score' => 80]]);
        // person sin kyc_data.face_match: el facematch no concluyó / no se ejecutó
        $app = $this->makeSubmitted();

        $this->runDecisionJob($app);

        $app->refresh();
        $this->assertSame(Application::STATUS_IN_REVIEW, $app->status);
        $decision = ApplicationDecision::withoutGlobalScopes()->where('application_id', $app->id)->first();
        $this->assertSame('REVIEW', $decision->outcome);
        $this->assertContains('face_match_failed', array_column($decision->rule_hits, 'rule'));
    }

    public function test_facematch_coincide_no_penaliza(): void
    {
        $this->productPolicy('ACTIVE', ['face_match' => ['min_score' => 80]]);
        $this->person->update(['kyc_data' => ['face_match' => ['passed' => true, 'score' => 95]]]);
        $app = $this->makeSubmitted(['requested_amount' => 350]);

        $this->runDecisionJob($app);

        $app->refresh();
        $this->assertSame(Application::STATUS_COUNTER_OFFERED, $app->status);
        $decision = ApplicationDecision::withoutGlobalScopes()->where('application_id', $app->id)->first();
        $this->assertNotContains('face_match_failed', array_column($decision->rule_hits, 'rule'));
    }

    public function test_facematch_sin_regla_en_politica_no_altera(): void
    {
        $this->productPolicy('ACTIVE'); // sin face_match en las reglas
        $this->person->update(['kyc_data' => ['face_match' => ['passed' => false, 'score' => 10]]]);
        $app = $this->makeSubmitted(['requested_amount' => 350]);

        $this->runDecisionJob($app);

        $app->refresh();
        $this->assertSame(Application::STATUS_COUNTER_OFFERED, $app->status);
    }

    // =====================================================
    // Vivienda / ubicación (cambio motor-housing-ubicacion)
    // =====================================================

    private function makeHomeAddress(string $housingType): Address
    {
        return Address::create([
            'tenant_id' => $this->tenant->id,
            'entity_type' => 'persons',
            'entity_id' => $this->person->id,
            'type' => 'HOME',
            'is_current' => true,
            'street' => 'Calle Falsa',
            'exterior_number' => '123',
            'neighborhood' => 'Centro',
            'municipality' => 'Cuauhtémoc',
            'city' => 'Ciudad de México',
            'state' => 'CDMX',
            'postal_code' => '06000',
            'housing_type' => $housingType,
            'status' => 'PENDING',
        ]);
    }

    public function test_recolecta_housing_type_del_domicilio_normalizado_al_canonico(): void
    {
        $collector = app(DecisionInputCollector::class);
        $app = $this->makeSubmitted();

        // Sin domicilio → null (el motor no suma puntos por esa variable).
        $this->assertNull(
            $collector->collect($app->fresh())['inputs']['variables']['housing_type']
        );

        // Valor legacy del store anterior se normaliza al canónico.
        $this->makeHomeAddress('MORTGAGED');
        $this->assertSame(
            'OWNED_MORTGAGE',
            $collector->collect($app->fresh())['inputs']['variables']['housing_type']
        );

        // Un valor ya canónico pasa sin cambios.
        Address::withoutGlobalScopes()
            ->where('entity_id', $this->person->id)
            ->update(['housing_type' => 'OWNED_PAID']);
        $this->assertSame(
            'OWNED_PAID',
            $collector->collect($app->fresh())['inputs']['variables']['housing_type']
        );
    }

    public function test_housing_type_con_puntos_cero_no_altera_score_ni_decision(): void
    {
        $engine = app(DecisionEngineService::class);
        $inputs = ['variables' => ['salary_range' => 'GT_15000', 'housing_type' => 'OWNED_PAID']];
        $context = ['requested_amount' => 350, 'product_min_amount' => 300];

        $bands = [
            ['key' => 'BASE', 'min_amount' => 300, 'max_amount' => 400],
            ['key' => 'INTERMEDIA', 'min_amount' => 500, 'max_amount' => 600],
        ];
        $cutoffs = [
            ['min_score' => 0, 'band' => 'BASE'],
            ['min_score' => 35, 'band' => 'INTERMEDIA'],
        ];

        $baseline = DecisionPolicy::make(['rules' => [
            'scoring' => [
                'variables' => [['key' => 'salary_range', 'points' => ['GT_15000' => 40]]],
                'band_cutoffs' => $cutoffs,
            ],
            'bands' => $bands,
            'first_credit' => ['term_days' => 7],
        ]]);

        // Igual que la sembrada por MoneyCapitalSeeder: housing_type con todos los
        // valores en 0 (matriz sin calibrar).
        $withHousing = DecisionPolicy::make(['rules' => [
            'scoring' => [
                'variables' => [
                    ['key' => 'salary_range', 'points' => ['GT_15000' => 40]],
                    ['key' => 'housing_type', 'points' => [
                        'OWNED_PAID' => 0, 'OWNED_MORTGAGE' => 0, 'RENTED' => 0,
                        'FAMILY' => 0, 'BORROWED' => 0, 'OTHER' => 0,
                    ]],
                ],
                'band_cutoffs' => $cutoffs,
            ],
            'bands' => $bands,
            'first_credit' => ['term_days' => 7],
        ]]);

        $base = $engine->evaluate($baseline, $inputs, $context);
        $withH = $engine->evaluate($withHousing, $inputs, $context);

        // Con points en 0 no cambia score, banda ni resultado.
        $this->assertSame($base['score'], $withH['score']);
        $this->assertSame($base['band'], $withH['band']);
        $this->assertSame($base['outcome'], $withH['outcome']);

        // Pero sí queda trazada en el desglose con points 0.
        $scoring = collect($withH['rule_hits'])->firstWhere('rule', 'scoring');
        $this->assertSame(
            ['key' => 'housing_type', 'value' => 'OWNED_PAID', 'points' => 0],
            collect($scoring['detail']['points'])->firstWhere('key', 'housing_type'),
        );
    }
}
