<?php

namespace Tests\Feature\V2;

use App\Enums\LoanStatus;
use App\Jobs\DeliverWebhookJob;
use App\Models\ApplicantAccount;
use App\Models\Application;
use App\Models\BankAccount;
use App\Models\InboundEvent;
use App\Models\Loan;
use App\Models\Person;
use App\Models\Product;
use App\Models\StaffAccount;
use App\Models\Tenant;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Services\Webhook\WebhookEvent;
use App\Services\Webhook\WebhookService;
use App\Services\Webhook\WebhookSigner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Integración saliente y entrante (cambio integracion-webhooks-cartera):
 * emisión + firma, entrega, API entrante (dispersión/pago) idempotente,
 * re-consulta y permisos del panel.
 */
class WebhookIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected StaffAccount $adminStaff;
    protected StaffAccount $analyst;
    protected Product $product;
    protected Person $person;
    protected ApplicantAccount $applicant;
    protected string $secret = 'whsec_test_secret_abcdef';

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['slug' => 'test-wh', 'is_active' => true]);
        $this->adminStaff = StaffAccount::factory()->admin()->create(['tenant_id' => $this->tenant->id]);
        $this->analyst = StaffAccount::factory()->analyst()->create(['tenant_id' => $this->tenant->id]);

        $this->product = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
            'min_amount' => 300, 'max_amount' => 15000,
            'min_term_months' => 1, 'max_term_months' => 1,
            'interest_rate' => 36, 'opening_commission' => 13,
            'rules' => ['term_in_days' => true, 'min_term_days' => 1, 'max_term_days' => 30, 'annual_rate' => 36, 'opening_commission' => 13],
        ]);

        $this->person = Person::factory()->create(['tenant_id' => $this->tenant->id, 'kyc_status' => 'VERIFIED']);
        $this->applicant = ApplicantAccount::factory()->create([
            'tenant_id' => $this->tenant->id, 'person_id' => $this->person->id,
        ]);
        BankAccount::create([
            'tenant_id' => $this->tenant->id,
            'entity_type' => 'persons', 'entity_id' => $this->person->id,
            'bank_name' => 'STP', 'clabe' => '646180157099999993', 'holder_name' => 'Juan Pérez',
            'is_for_disbursement' => true, 'is_primary' => true,
        ]);
    }

    private function endpoint(array $events = ['*'], array $overrides = []): WebhookEndpoint
    {
        return WebhookEndpoint::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'name' => 'Cartera', 'url' => 'https://cartera.example/webhooks',
            'secret' => $this->secret, 'events' => $events, 'is_active' => true,
        ], $overrides));
    }

    private function makeApp(string $status = Application::STATUS_APPROVED): Application
    {
        return Application::create([
            'tenant_id' => $this->tenant->id, 'product_id' => $this->product->id,
            'applicant_type' => Application::TYPE_INDIVIDUAL, 'person_id' => $this->person->id,
            'requested_amount' => 350, 'requested_term_months' => 1, 'requested_term_days' => 7,
            'approved_amount' => 350, 'approved_term_days' => 7, 'approved_interest_rate' => 36,
            'status' => $status, 'decision_at' => now(),
        ]);
    }

    private function makeLoan(string $status, Application $app): Loan
    {
        return Loan::create([
            'tenant_id' => $this->tenant->id, 'application_id' => $app->id,
            'person_id' => $this->person->id, 'applicant_account_id' => $this->applicant->id,
            'principal_amount' => 350, 'interest_rate' => 36, 'term_days' => 7,
            'opening_commission_amount' => 45.5, 'total_to_pay' => 405.59,
            'outstanding_balance' => 405.59, 'paid_amount' => 0, 'late_fee_accrued' => 0,
            'status' => $status,
        ]);
    }

    private function staffAuth(StaffAccount $staff): static
    {
        $this->app['auth']->forgetGuards();
        $token = $staff->createToken('test', ['staff'])->plainTextToken;

        return $this->withHeader('X-Tenant-ID', $this->tenant->slug)
            ->withHeader('Authorization', "Bearer {$token}");
    }

    private function signedHeaders(string $body): array
    {
        $ts = time();
        $sig = app(WebhookSigner::class)->sign($body, $this->secret, $ts);

        return ['X-LendusFind-Signature' => $sig, 'X-LendusFind-Timestamp' => (string) $ts];
    }

    /** POST firmado: json() aplica los headers de withHeaders y envía json_encode($data). */
    private function postSigned(string $url, array $data): \Illuminate\Testing\TestResponse
    {
        $body = json_encode($data);

        return $this->withHeaders($this->signedHeaders($body))->json('POST', $url, $data);
    }

    // ===================== Emisión =====================

    public function test_emite_a_endpoints_suscritos_con_sobre_completo(): void
    {
        Queue::fake();
        $endpoint = $this->endpoint([WebhookEvent::APPLICATION_APPROVED]);
        $app = $this->makeApp();

        app(WebhookService::class)->emit(WebhookEvent::APPLICATION_APPROVED, $app);

        $delivery = WebhookDelivery::withoutGlobalScopes()->where('event', 'application.approved')->first();
        $this->assertNotNull($delivery);
        $this->assertSame($endpoint->id, $delivery->webhook_endpoint_id);
        $this->assertSame('1', $delivery->payload['version']);
        // CLABE completa en el payload (la cartera dispersa)
        $this->assertSame('646180157099999993', $delivery->payload['data']['application']['disbursement_account']['clabe']);
        $this->assertEquals(350, $delivery->payload['data']['application']['approved']['amount']);
        Queue::assertPushed(DeliverWebhookJob::class);
    }

    public function test_sin_endpoints_no_emite_nada(): void
    {
        Queue::fake();
        $app = $this->makeApp();

        app(WebhookService::class)->emit(WebhookEvent::APPLICATION_APPROVED, $app);

        $this->assertSame(0, WebhookDelivery::withoutGlobalScopes()->count());
    }

    public function test_evento_no_suscrito_no_genera_entrega(): void
    {
        Queue::fake();
        $this->endpoint([WebhookEvent::PAYMENT_RECEIVED]); // no suscribe approved
        app(WebhookService::class)->emit(WebhookEvent::APPLICATION_APPROVED, $this->makeApp());

        $this->assertSame(0, WebhookDelivery::withoutGlobalScopes()->count());
    }

    public function test_entrega_firma_y_marca_sent(): void
    {
        Queue::fake();
        Http::fake(['*' => Http::response('ok', 200)]);
        $this->endpoint([WebhookEvent::APPLICATION_APPROVED]);
        app(WebhookService::class)->emit(WebhookEvent::APPLICATION_APPROVED, $this->makeApp());
        $delivery = WebhookDelivery::withoutGlobalScopes()->first();

        (new DeliverWebhookJob($delivery->id))->handle(app(WebhookSigner::class));

        $delivery->refresh();
        $this->assertSame(WebhookDelivery::STATUS_SENT, $delivery->status);
        $this->assertStringStartsWith('sha256=', $delivery->signature);
        Http::assertSent(fn ($req) => $req->hasHeader('X-LendusFind-Signature') && $req->hasHeader('X-LendusFind-Timestamp'));
    }

    public function test_entrega_fallida_reintenta_con_backoff(): void
    {
        Queue::fake();
        Http::fake(['*' => Http::response('boom', 500)]);
        $this->endpoint([WebhookEvent::APPLICATION_APPROVED]);
        app(WebhookService::class)->emit(WebhookEvent::APPLICATION_APPROVED, $this->makeApp());
        $delivery = WebhookDelivery::withoutGlobalScopes()->first();

        (new DeliverWebhookJob($delivery->id))->handle(app(WebhookSigner::class));

        $delivery->refresh();
        $this->assertSame(WebhookDelivery::STATUS_RETRYING, $delivery->status);
        $this->assertNotNull($delivery->next_retry_at);
        $this->assertSame(1, $delivery->attempts);
    }

    // ===================== Entrante =====================

    public function test_confirmacion_de_dispersion_activa_y_sincroniza(): void
    {
        Http::fake(['*' => Http::response('ok', 200)]);
        $endpoint = $this->endpoint([WebhookEvent::LOAN_DISBURSED]);
        $app = $this->makeApp(Application::STATUS_APPROVED);
        $loan = $this->makeLoan(LoanStatus::PENDING_DISBURSEMENT->value, $app);

        $res = $this->postSigned("/api/webhooks/inbound/{$endpoint->id}/disbursement", [
            'external_event_id' => 'cartera-disb-1', 'loan_id' => $loan->id,
            'external_id' => 'CARTERA-556677', 'external_system' => 'CORE',
            'disbursement_reference' => 'STP-123', 'disbursed_at' => now()->toIso8601String(),
        ]);

        $res->assertOk();
        $this->assertSame('processed', $res->json('data.status'));
        $loan->refresh();
        $this->assertSame(LoanStatus::ACTIVE, $loan->status);
        $this->assertSame(Application::STATUS_SYNCED, $app->fresh()->status);
        $this->assertSame('CARTERA-556677', $app->fresh()->external_id);
    }

    public function test_pago_entrante_idempotente(): void
    {
        Http::fake(['*' => Http::response('ok', 200)]);
        $endpoint = $this->endpoint([WebhookEvent::PAYMENT_RECEIVED]);
        $app = $this->makeApp();
        $loan = $this->makeLoan(LoanStatus::ACTIVE->value, $app);

        $post = fn () => $this->postSigned("/api/webhooks/inbound/{$endpoint->id}/payments", [
            'external_event_id' => 'pay-1', 'loan_id' => $loan->id, 'amount' => 200, 'channel' => 'STP',
        ]);

        $first = $post();
        $first->assertOk();
        $this->assertSame('processed', $first->json('data.status'));

        $second = $post();
        $second->assertOk();
        $this->assertSame('duplicate', $second->json('data.status'));
        // un solo pago aplicado
        $this->assertEquals(1, $loan->fresh()->payments()->count());
    }

    public function test_firma_invalida_es_401(): void
    {
        $endpoint = $this->endpoint([WebhookEvent::PAYMENT_RECEIVED]);
        $app = $this->makeApp();
        $loan = $this->makeLoan(LoanStatus::ACTIVE->value, $app);
        $this->withHeaders(['X-LendusFind-Signature' => 'sha256=bad', 'X-LendusFind-Timestamp' => (string) time()])
            ->json('POST', "/api/webhooks/inbound/{$endpoint->id}/payments", ['external_event_id' => 'x', 'loan_id' => $loan->id, 'amount' => 100])
            ->assertStatus(401);

        $this->assertSame(0, InboundEvent::withoutGlobalScopes()->count());
    }

    // ===================== Re-consulta =====================

    public function test_reconsulta_devuelve_mismo_esquema_con_ability(): void
    {
        $app = $this->makeApp();
        $loan = $this->makeLoan(LoanStatus::ACTIVE->value, $app);

        $this->app['auth']->forgetGuards();
        $token = $this->adminStaff->createToken('int', ['integration'])->plainTextToken;
        $res = $this->withHeader('X-Tenant-ID', $this->tenant->slug)
            ->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v2/integration/loans/{$loan->id}");

        $res->assertOk();
        $this->assertEquals(350, $res->json('data.loan.principal_amount'));
        $this->assertSame('MXN', $res->json('data.loan.currency'));
    }

    public function test_reconsulta_sin_ability_es_403(): void
    {
        $app = $this->makeApp();
        $loan = $this->makeLoan(LoanStatus::ACTIVE->value, $app);

        $this->app['auth']->forgetGuards();
        $token = $this->adminStaff->createToken('staff', ['staff'])->plainTextToken; // sin ability integration
        $this->withHeader('X-Tenant-ID', $this->tenant->slug)
            ->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v2/integration/loans/{$loan->id}")
            ->assertForbidden();
    }

    // ===================== Panel (permisos) =====================

    public function test_admin_crea_endpoint_y_ve_secreto_una_vez(): void
    {
        $res = $this->staffAuth($this->adminStaff)->postJson('/api/v2/staff/webhooks/endpoints', [
            'name' => 'Cartera', 'url' => 'https://cartera.example/wh', 'events' => ['application.approved'],
        ]);
        $res->assertCreated();
        $this->assertNotEmpty($res->json('data.secret')); // se muestra al crear

        // en el listado ya no viaja el secreto
        $list = $this->staffAuth($this->adminStaff)->getJson('/api/v2/staff/webhooks/endpoints');
        $this->assertArrayNotHasKey('secret', $list->json('data.endpoints.0'));
        $this->assertTrue($list->json('data.endpoints.0.has_secret'));
    }

    public function test_url_no_https_es_rechazada(): void
    {
        $this->staffAuth($this->adminStaff)->postJson('/api/v2/staff/webhooks/endpoints', [
            'name' => 'X', 'url' => 'http://inseguro.example', 'events' => ['application.approved'],
        ])->assertStatus(422);
    }

    public function test_analista_no_gestiona_webhooks(): void
    {
        $this->staffAuth($this->analyst)->getJson('/api/v2/staff/webhooks/endpoints')->assertForbidden();
    }

    public function test_reenviar_entrega_fallida(): void
    {
        Http::fake(['*' => Http::response('ok', 200)]);
        $endpoint = $this->endpoint([WebhookEvent::APPLICATION_APPROVED]);
        app(WebhookService::class)->emit(WebhookEvent::APPLICATION_APPROVED, $this->makeApp());
        $delivery = WebhookDelivery::withoutGlobalScopes()->first();
        $delivery->update(['status' => WebhookDelivery::STATUS_FAILED]);

        Queue::fake();
        $this->staffAuth($this->adminStaff)->postJson("/api/v2/staff/webhooks/deliveries/{$delivery->id}/retry")->assertOk();

        $this->assertSame(WebhookDelivery::STATUS_PENDING, $delivery->fresh()->status);
        Queue::assertPushed(DeliverWebhookJob::class);
    }
}
