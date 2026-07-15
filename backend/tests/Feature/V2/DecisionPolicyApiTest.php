<?php

namespace Tests\Feature\V2;

use App\Models\ApplicantAccount;
use App\Models\Application;
use App\Models\ApplicationDecision;
use App\Models\DecisionPolicy;
use App\Models\Person;
use App\Models\Product;
use App\Models\RiskAssessment;
use App\Models\StaffAccount;
use App\Models\Tenant;
use App\Services\Decision\PhoneRiskGateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Configurador de políticas (permisos por rol, coherencia, activación,
 * dry-run), cooldown post-rechazo y gate telefónico.
 */
class DecisionPolicyApiTest extends TestCase
{
    use RefreshDatabase;

    protected StaffAccount $superAdmin;
    protected StaffAccount $adminStaff;
    protected StaffAccount $analyst;
    protected StaffAccount $supervisor;
    protected Product $product;
    protected Person $person;
    protected ApplicantAccount $account;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();

        $this->tenant = Tenant::factory()->create(['slug' => 'test-policies', 'is_active' => true]);

        $this->superAdmin = StaffAccount::factory()->superAdmin()->create(['tenant_id' => $this->tenant->id]);
        $this->adminStaff = StaffAccount::factory()->admin()->create(['tenant_id' => $this->tenant->id]);
        $this->supervisor = StaffAccount::factory()->supervisor()->create(['tenant_id' => $this->tenant->id]);
        $this->analyst = StaffAccount::factory()->analyst()->create(['tenant_id' => $this->tenant->id]);

        $this->product = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
            'min_amount' => 300,
            'max_amount' => 15000,
            'min_term_months' => 1,
            'max_term_months' => 1,
            'rules' => ['min_amount' => 300, 'max_amount' => 15000, 'term_in_days' => true, 'min_term_days' => 1, 'max_term_days' => 30, 'annual_rate' => 36, 'opening_commission' => 13],
        ]);

        $this->person = Person::factory()->create([
            'tenant_id' => $this->tenant->id,
            'kyc_status' => 'VERIFIED',
        ]);
        $this->account = ApplicantAccount::factory()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);
    }

    private function authAs(StaffAccount $staff): static
    {
        $this->app['auth']->forgetGuards();
        $token = $staff->createToken('test', ['staff'])->plainTextToken;

        return $this->withHeader('X-Tenant-ID', $this->tenant->slug)
            ->withHeader('Authorization', "Bearer {$token}");
    }

    private function applicantAuth(): static
    {
        $this->app['auth']->forgetGuards();
        $token = $this->account->createToken('test', ['applicant'])->plainTextToken;

        return $this->withHeader('X-Tenant-ID', $this->tenant->slug)
            ->withHeader('Authorization', "Bearer {$token}");
    }

    private function validProductRules(): array
    {
        return [
            'scoring' => [
                'variables' => [['key' => 'salary_range', 'points' => ['GT_15000' => 40]]],
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
            'offer' => ['validity_hours' => 72],
            'review' => ['input_timeout_minutes' => 30],
        ];
    }

    private function tenantPolicy(string $mode = 'ACTIVE', int $cooldownDays = 30): DecisionPolicy
    {
        return DecisionPolicy::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => null,
            'version' => 1,
            'mode' => $mode,
            'is_active' => true,
            'rules' => [
                'phone_risk_gate' => ['enabled' => true, 'flag_from' => 401, 'block_from' => 601, 'fail_mode' => 'open'],
                'cooldown' => ['days' => $cooldownDays],
            ],
        ]);
    }

    // =====================================================
    // Permisos del configurador
    // =====================================================

    public function test_analista_no_puede_ver_politicas(): void
    {
        $this->authAs($this->analyst)
            ->getJson('/api/v2/staff/decision-policies')
            ->assertForbidden();
    }

    public function test_admin_consulta_pero_no_edita(): void
    {
        $this->authAs($this->adminStaff)
            ->getJson('/api/v2/staff/decision-policies')
            ->assertOk();

        $this->authAs($this->adminStaff)
            ->postJson('/api/v2/staff/decision-policies', [
                'product_id' => $this->product->id,
                'rules' => $this->validProductRules(),
            ])
            ->assertForbidden();
    }

    public function test_super_admin_crea_borrador_y_activa(): void
    {
        $create = $this->authAs($this->superAdmin)
            ->postJson('/api/v2/staff/decision-policies', [
                'product_id' => $this->product->id,
                'rules' => $this->validProductRules(),
                'notes' => 'v1 de la matriz',
            ]);
        $create->assertCreated();
        $policyId = $create->json('data.id');
        $this->assertFalse($create->json('data.is_active'));

        $this->authAs($this->superAdmin)
            ->postJson("/api/v2/staff/decision-policies/{$policyId}/activate", ['mode' => 'SHADOW'])
            ->assertOk();

        $policy = DecisionPolicy::withoutGlobalScopes()->find($policyId);
        $this->assertTrue($policy->is_active);
        $this->assertSame('SHADOW', $policy->mode);
        $this->assertSame((string) $this->superAdmin->id, (string) $policy->activated_by);
    }

    public function test_activar_version_desactiva_la_anterior_rollback(): void
    {
        $auth = fn () => $this->authAs($this->superAdmin);

        $v1 = $auth()->postJson('/api/v2/staff/decision-policies', [
            'product_id' => $this->product->id,
            'rules' => $this->validProductRules(),
        ])->json('data.id');
        $auth()->postJson("/api/v2/staff/decision-policies/{$v1}/activate")->assertOk();

        $v2 = $auth()->postJson('/api/v2/staff/decision-policies', [
            'product_id' => $this->product->id,
            'rules' => $this->validProductRules(),
        ])->json('data.id');
        $this->assertSame(2, DecisionPolicy::withoutGlobalScopes()->find($v2)->version);

        $auth()->postJson("/api/v2/staff/decision-policies/{$v2}/activate")->assertOk();
        $this->assertFalse(DecisionPolicy::withoutGlobalScopes()->find($v1)->is_active);

        // Rollback: reactivar v1
        $auth()->postJson("/api/v2/staff/decision-policies/{$v1}/activate")->assertOk();
        $this->assertTrue(DecisionPolicy::withoutGlobalScopes()->find($v1)->is_active);
        $this->assertFalse(DecisionPolicy::withoutGlobalScopes()->find($v2)->is_active);
    }

    public function test_version_activada_es_inmutable(): void
    {
        $auth = fn () => $this->authAs($this->superAdmin);
        $id = $auth()->postJson('/api/v2/staff/decision-policies', [
            'product_id' => $this->product->id,
            'rules' => $this->validProductRules(),
        ])->json('data.id');
        $auth()->postJson("/api/v2/staff/decision-policies/{$id}/activate")->assertOk();

        $auth()->putJson("/api/v2/staff/decision-policies/{$id}", [
            'rules' => $this->validProductRules(),
        ])->assertStatus(422);
    }

    public function test_validacion_rechaza_bandas_traslapadas_y_umbrales_invertidos(): void
    {
        $rules = $this->validProductRules();
        $rules['bands'] = [
            ['key' => 'BASE', 'min_amount' => 300, 'max_amount' => 500],
            ['key' => 'INTERMEDIA', 'min_amount' => 450, 'max_amount' => 600], // traslape
        ];
        $this->authAs($this->superAdmin)
            ->postJson('/api/v2/staff/decision-policies', [
                'product_id' => $this->product->id,
                'rules' => $rules,
            ])
            ->assertStatus(422);

        $this->authAs($this->superAdmin)
            ->postJson('/api/v2/staff/decision-policies', [
                'rules' => [
                    'phone_risk_gate' => ['enabled' => true, 'flag_from' => 700, 'block_from' => 600],
                ],
            ])
            ->assertStatus(422);
    }

    public function test_dry_run_evalua_sin_tocar_solicitudes(): void
    {
        $auth = fn () => $this->authAs($this->superAdmin);
        $id = $auth()->postJson('/api/v2/staff/decision-policies', [
            'product_id' => $this->product->id,
            'rules' => $this->validProductRules(),
        ])->json('data.id');

        $before = Application::withoutGlobalScopes()->count();

        $response = $auth()->postJson('/api/v2/staff/decision-policies/dry-run', [
            'policy_id' => $id,
            'profile' => [
                'requested_amount' => 900,
                'variables' => ['salary_range' => 'GT_15000'],
            ],
        ]);

        $response->assertOk();
        $this->assertSame('OFFER', $response->json('data.result.outcome'));
        $this->assertSame('INTERMEDIA', $response->json('data.result.band')); // 40 pts ≥ 35
        $this->assertEquals(600, $response->json('data.result.range.max_amount'));

        $this->assertSame($before, Application::withoutGlobalScopes()->count());
        $dryRun = ApplicationDecision::withoutGlobalScopes()->where('trigger', 'DRY_RUN')->first();
        $this->assertNotNull($dryRun);
        $this->assertFalse($dryRun->executed);
    }

    // =====================================================
    // Cooldown post-rechazo
    // =====================================================

    private function makeRejected(\Carbon\Carbon $decisionAt): Application
    {
        return Application::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'applicant_type' => Application::TYPE_INDIVIDUAL,
            'person_id' => $this->person->id,
            'requested_amount' => 500,
            'requested_term_months' => 1,
            'status' => Application::STATUS_REJECTED,
            'decision' => 'REJECTED',
            'decision_at' => $decisionAt,
        ]);
    }

    private function attemptCreateApplication(): \Illuminate\Testing\TestResponse
    {
        return $this->applicantAuth()->postJson('/api/v2/applicant/applications', [
            'product_id' => $this->product->id,
            'amount' => 500,
            'term_months' => 1,
            'requested_term_days' => 7,
        ]);
    }

    public function test_rechazo_reciente_bloquea_con_fecha_de_reintento(): void
    {
        $this->tenantPolicy('ACTIVE', 30);
        $this->makeRejected(now()->subDays(10));

        $response = $this->attemptCreateApplication();

        $response->assertStatus(409);
        $this->assertSame('APPLICATION_COOLDOWN', $response->json('error'));
    }

    public function test_rechazo_viejo_no_bloquea(): void
    {
        $this->tenantPolicy('ACTIVE', 30);
        $this->makeRejected(now()->subDays(31));

        $this->attemptCreateApplication()->assertCreated();
    }

    public function test_cancelada_no_genera_cooldown(): void
    {
        $this->tenantPolicy('ACTIVE', 30);
        Application::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'applicant_type' => Application::TYPE_INDIVIDUAL,
            'person_id' => $this->person->id,
            'requested_amount' => 500,
            'requested_term_months' => 1,
            'status' => Application::STATUS_CANCELLED,
            'decision_at' => now()->subDay(),
        ]);

        $this->attemptCreateApplication()->assertCreated();
    }

    public function test_en_sombra_el_cooldown_no_bloquea(): void
    {
        $this->tenantPolicy('SHADOW', 30);
        $this->makeRejected(now()->subDays(10));

        $this->attemptCreateApplication()->assertCreated();
    }

    public function test_endpoint_de_estado_de_cooldown(): void
    {
        $this->tenantPolicy('ACTIVE', 30);
        $this->makeRejected(now()->subDays(10));

        $response = $this->applicantAuth()->getJson('/api/v2/applicant/applications/cooldown');

        $response->assertOk();
        $this->assertTrue($response->json('data.blocked'));
        $this->assertNotNull($response->json('data.cooldown.blocked_until'));
    }

    public function test_supervisor_levanta_cooldown_con_motivo(): void
    {
        $this->tenantPolicy('ACTIVE', 30);
        $rejected = $this->makeRejected(now()->subDays(10));

        $this->authAs($this->supervisor)
            ->postJson("/api/v2/staff/applications/{$rejected->id}/lift-cooldown", [
                'reason' => 'Falla técnica comprobada del proveedor',
            ])
            ->assertOk();

        $rejected->refresh();
        $this->assertNotNull($rejected->cooldown_waived_at);
        $this->assertSame((string) $this->supervisor->id, (string) $rejected->cooldown_waived_by);

        $this->attemptCreateApplication()->assertCreated();
    }

    public function test_waiver_sin_motivo_es_422_y_analista_403(): void
    {
        $this->tenantPolicy('ACTIVE', 30);
        $rejected = $this->makeRejected(now()->subDays(10));

        $this->authAs($this->supervisor)
            ->postJson("/api/v2/staff/applications/{$rejected->id}/lift-cooldown", [])
            ->assertStatus(422);

        $this->authAs($this->analyst)
            ->postJson("/api/v2/staff/applications/{$rejected->id}/lift-cooldown", [
                'reason' => 'Intento sin permiso',
            ])
            ->assertForbidden();
    }

    // =====================================================
    // Gate telefónico
    // =====================================================

    private function makeAssessment(?int $score, string $status = RiskAssessment::STATUS_COMPLETED): void
    {
        RiskAssessment::create([
            'tenant_id' => $this->tenant->id,
            'account_id' => $this->account->id,
            'person_id' => $this->person->id,
            'type' => RiskAssessment::TYPE_PHONE_RISK,
            'status' => $status,
            'score' => $score,
            'level' => 'moderate',
            'error' => $status === RiskAssessment::STATUS_FAILED ? '403 Forbidden' : null,
        ]);
    }

    public function test_gate_bloquea_score_alto_en_active(): void
    {
        $this->tenantPolicy('ACTIVE');
        $this->makeAssessment(720);

        $outcome = app(PhoneRiskGateService::class)->check($this->account->fresh());

        $this->assertSame('BLOCK', $outcome);
        $decision = ApplicationDecision::withoutGlobalScopes()->where('trigger', 'PHONE_GATE')->first();
        $this->assertSame('BLOCK', $decision->outcome);
        $this->assertTrue($decision->executed);
    }

    public function test_gate_en_sombra_registra_pero_no_bloquea(): void
    {
        $this->tenantPolicy('SHADOW');
        $this->makeAssessment(720);

        $outcome = app(PhoneRiskGateService::class)->check($this->account->fresh());

        $this->assertSame('ALLOW', $outcome);
        $decision = ApplicationDecision::withoutGlobalScopes()->where('trigger', 'PHONE_GATE')->first();
        $this->assertSame('BLOCK', $decision->outcome); // lo que habría hecho
        $this->assertFalse($decision->executed);
    }

    public function test_gate_fail_open_ante_falla_del_proveedor(): void
    {
        $this->tenantPolicy('ACTIVE');
        $this->makeAssessment(null, RiskAssessment::STATUS_FAILED);

        $outcome = app(PhoneRiskGateService::class)->check($this->account->fresh());

        $this->assertSame('FLAG', $outcome); // continúa (solo BLOCK detiene) marcado a revisión
        $decision = ApplicationDecision::withoutGlobalScopes()->where('trigger', 'PHONE_GATE')->first();
        $rules = array_column($decision->rule_hits, 'rule');
        $this->assertContains('score_unavailable', $rules);
    }

    public function test_gate_score_bajo_permite(): void
    {
        $this->tenantPolicy('ACTIVE');
        $this->makeAssessment(180);

        $this->assertSame('ALLOW', app(PhoneRiskGateService::class)->check($this->account->fresh()));
    }
}
