<?php

namespace Tests\Feature\V2;

use App\Models\ApplicantAccount;
use App\Models\Application;
use App\Models\Person;
use App\Models\Product;
use App\Models\StaffAccount;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Flujo completo de contraoferta (cambio OpenSpec contraoferta-admin-aceptacion):
 * envío staff (días y meses), aceptación → APPROVED, rechazo → CANCELLED,
 * expiración vía comando, validaciones de unidad/rango/permiso.
 */
class CounterOfferTest extends TestCase
{
    use RefreshDatabase;

    // $tenant viene tipado ?Tenant desde Tests\TestCase
    protected StaffAccount $supervisor;
    protected StaffAccount $analyst;
    protected Product $daysProduct;
    protected Product $monthsProduct;
    protected Person $person;
    protected ApplicantAccount $account;
    protected string $supervisorToken;
    protected string $analystToken;
    protected string $applicantToken;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();

        $this->tenant = Tenant::factory()->create(['slug' => 'test-co', 'is_active' => true]);

        $this->supervisor = StaffAccount::factory()->supervisor()->create(['tenant_id' => $this->tenant->id]);
        $this->analyst = StaffAccount::factory()->analyst()->create(['tenant_id' => $this->tenant->id]);
        $this->supervisorToken = $this->supervisor->createToken('test', ['staff'])->plainTextToken;
        $this->analystToken = $this->analyst->createToken('test', ['staff'])->plainTextToken;

        // Producto tipo MoneyCapital: plazo en días, bullet.
        $this->daysProduct = Product::factory()->create([
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

        $this->monthsProduct = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
            'min_amount' => 5000,
            'max_amount' => 100000,
            'min_term_months' => 6,
            'max_term_months' => 36,
            'interest_rate' => 24,
            'opening_commission' => 3,
        ]);

        $this->person = Person::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->account = ApplicantAccount::factory()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);
        $this->applicantToken = $this->account->createToken('test', ['applicant'])->plainTextToken;
    }

    private function staffAuth(string $token): static
    {
        // Sanctum memoiza el usuario del guard entre requests del mismo test;
        // sin esto, un request applicant después de uno staff resuelve el
        // usuario anterior (StaffAccount) en vez del token enviado.
        $this->app['auth']->forgetGuards();

        return $this->withHeader('X-Tenant-ID', $this->tenant->slug)
            ->withHeader('Authorization', "Bearer {$token}");
    }

    private function applicantAuth(): static
    {
        return $this->staffAuth($this->applicantToken);
    }

    private function makeApplication(Product $product, array $overrides = []): Application
    {
        return Application::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'product_id' => $product->id,
            'applicant_type' => Application::TYPE_INDIVIDUAL,
            'person_id' => $this->person->id,
            'submitted_by_account_id' => $this->account->id,
            'requested_amount' => 1000,
            // requested_term_months es NOT NULL; productos en días usan el shim = 1
            'requested_term_months' => $product->term_in_days ? 1 : 12,
            'requested_term_days' => $product->term_in_days ? 10 : null,
            'status' => Application::STATUS_IN_REVIEW,
        ], $overrides));
    }

    private function sendOffer(Application $app, array $payload): \Illuminate\Testing\TestResponse
    {
        return $this->staffAuth($this->supervisorToken)
            ->postJson("/api/v2/staff/applications/{$app->id}/counter-offer", $payload);
    }

    // =====================================================
    // Envío (staff)
    // =====================================================

    public function test_supervisor_sends_days_counter_offer_and_status_transitions(): void
    {
        $app = $this->makeApplication($this->daysProduct);

        $this->sendOffer($app, [
            'amount' => 700,
            'term_days' => 10,
            'reason' => 'Perfil de primer crédito',
        ])->assertStatus(200);

        $app->refresh();
        $this->assertEquals(Application::STATUS_COUNTER_OFFERED, $app->status);
        $this->assertEquals(700, $app->counter_offer['amount']);
        $this->assertEquals(10, $app->counter_offer['term_days']);
        $this->assertNull($app->counter_offer['term_months']);
        // Snapshot de pricing tomado del producto
        $this->assertEquals(36, $app->counter_offer['interest_rate']);
        $this->assertEquals(13, $app->counter_offer['opening_commission']);
        $this->assertNotNull($app->counter_offer['expires_at']);
        $this->assertNull($app->counter_offer_responded_at);
    }

    public function test_supervisor_sends_months_counter_offer(): void
    {
        $app = $this->makeApplication($this->monthsProduct);

        $this->sendOffer($app, [
            'amount' => 40000,
            'term_months' => 18,
            'interest_rate' => 18.5,
        ])->assertStatus(200);

        $app->refresh();
        $this->assertEquals(Application::STATUS_COUNTER_OFFERED, $app->status);
        $this->assertEquals(18, $app->counter_offer['term_months']);
        $this->assertNull($app->counter_offer['term_days']);
        $this->assertEquals(18.5, $app->counter_offer['interest_rate']);
        $this->assertArrayHasKey('monthly_payment', $app->counter_offer);
    }

    public function test_term_unit_must_match_product(): void
    {
        $app = $this->makeApplication($this->daysProduct);

        $this->sendOffer($app, ['amount' => 700, 'term_months' => 12])
            ->assertStatus(400)
            ->assertJsonPath('error', 'TERM_UNIT_MISMATCH');
    }

    public function test_term_days_out_of_product_range_is_rejected(): void
    {
        $app = $this->makeApplication($this->daysProduct);

        $this->sendOffer($app, ['amount' => 700, 'term_days' => 45])
            ->assertStatus(400)
            ->assertJsonPath('error', 'TERM_OUT_OF_RANGE');
    }

    public function test_amount_out_of_product_range_is_rejected(): void
    {
        $app = $this->makeApplication($this->daysProduct);

        $this->sendOffer($app, ['amount' => 20000, 'term_days' => 10])
            ->assertStatus(400)
            ->assertJsonPath('error', 'AMOUNT_OUT_OF_RANGE');
    }

    public function test_counter_offer_from_ineligible_status_fails(): void
    {
        $app = $this->makeApplication($this->daysProduct, ['status' => Application::STATUS_DRAFT]);

        $this->sendOffer($app, ['amount' => 700, 'term_days' => 10])
            ->assertStatus(400);

        $this->assertEquals(Application::STATUS_DRAFT, $app->fresh()->status);
    }

    public function test_analyst_without_permission_gets_403(): void
    {
        $app = $this->makeApplication($this->daysProduct);

        $this->staffAuth($this->analystToken)
            ->postJson("/api/v2/staff/applications/{$app->id}/counter-offer", [
                'amount' => 700,
                'term_days' => 10,
            ])->assertStatus(403);
    }

    public function test_resend_overwrites_offer_without_duplicate_transition(): void
    {
        $app = $this->makeApplication($this->daysProduct);

        $this->sendOffer($app, ['amount' => 700, 'term_days' => 10])->assertStatus(200);
        $this->sendOffer($app, ['amount' => 500, 'term_days' => 7])->assertStatus(200);

        $app->refresh();
        $this->assertEquals(Application::STATUS_COUNTER_OFFERED, $app->status);
        $this->assertEquals(500, $app->counter_offer['amount']);
        $this->assertEquals(7, $app->counter_offer['term_days']);

        $transitions = $app->statusHistory()
            ->where('to_status', Application::STATUS_COUNTER_OFFERED)
            ->count();
        $this->assertEquals(1, $transitions);
    }

    // =====================================================
    // Respuesta (applicant)
    // =====================================================

    public function test_accepting_copies_terms_and_approves(): void
    {
        $app = $this->makeApplication($this->daysProduct);
        $this->sendOffer($app, ['amount' => 700, 'term_days' => 10])->assertStatus(200);

        $this->applicantAuth()
            ->postJson("/api/v2/applicant/applications/{$app->id}/counter-offer/respond", ['accepted' => true])
            ->assertStatus(200);

        $app->refresh();
        $this->assertEquals(Application::STATUS_APPROVED, $app->status);
        $this->assertEquals(700, (float) $app->approved_amount);
        $this->assertEquals(10, $app->approved_term_days);
        $this->assertNull($app->approved_term_months);
        $this->assertTrue($app->counter_offer_accepted);
        $this->assertNotNull($app->counter_offer_responded_at);
    }

    public function test_rejecting_cancels_application(): void
    {
        $app = $this->makeApplication($this->daysProduct);
        $this->sendOffer($app, ['amount' => 700, 'term_days' => 10])->assertStatus(200);

        $this->applicantAuth()
            ->postJson("/api/v2/applicant/applications/{$app->id}/counter-offer/respond", ['accepted' => false])
            ->assertStatus(200);

        $app->refresh();
        $this->assertEquals(Application::STATUS_CANCELLED, $app->status);
        $this->assertFalse($app->counter_offer_accepted);
    }

    public function test_double_response_is_rejected(): void
    {
        $app = $this->makeApplication($this->daysProduct);
        $this->sendOffer($app, ['amount' => 700, 'term_days' => 10])->assertStatus(200);

        $this->applicantAuth()
            ->postJson("/api/v2/applicant/applications/{$app->id}/counter-offer/respond", ['accepted' => true])
            ->assertStatus(200);

        $this->applicantAuth()
            ->postJson("/api/v2/applicant/applications/{$app->id}/counter-offer/respond", ['accepted' => false])
            ->assertStatus(400);

        $this->assertEquals(Application::STATUS_APPROVED, $app->fresh()->status);
    }

    public function test_accepting_expired_offer_returns_422(): void
    {
        $app = $this->makeApplication($this->daysProduct);
        $this->sendOffer($app, ['amount' => 700, 'term_days' => 10])->assertStatus(200);

        $app->refresh();
        $app->update([
            'counter_offer' => array_merge($app->counter_offer, [
                'expires_at' => now()->subMinute()->toIso8601String(),
            ]),
        ]);

        $this->applicantAuth()
            ->postJson("/api/v2/applicant/applications/{$app->id}/counter-offer/respond", ['accepted' => true])
            ->assertStatus(422);

        $this->assertEquals(Application::STATUS_COUNTER_OFFERED, $app->fresh()->status);
    }

    public function test_respond_without_counter_offer_returns_400(): void
    {
        $app = $this->makeApplication($this->daysProduct);

        $this->applicantAuth()
            ->postJson("/api/v2/applicant/applications/{$app->id}/counter-offer/respond", ['accepted' => true])
            ->assertStatus(400);
    }

    // =====================================================
    // Expiración programada
    // =====================================================

    public function test_expire_command_cancels_only_expired_unanswered_offers(): void
    {
        $expired = $this->makeApplication($this->daysProduct);
        $this->sendOffer($expired, ['amount' => 700, 'term_days' => 10])->assertStatus(200);
        $expired->refresh();
        $expired->update([
            'counter_offer' => array_merge($expired->counter_offer, [
                'expires_at' => now()->subMinutes(5)->toIso8601String(),
            ]),
        ]);

        $vigente = $this->makeApplication($this->daysProduct);
        $this->sendOffer($vigente, ['amount' => 500, 'term_days' => 7])->assertStatus(200);

        $this->artisan('counter-offers:expire')->assertExitCode(0);

        $this->assertEquals(Application::STATUS_CANCELLED, $expired->fresh()->status);
        $this->assertEquals(Application::STATUS_COUNTER_OFFERED, $vigente->fresh()->status);
    }
}
