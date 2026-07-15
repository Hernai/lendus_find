<?php

namespace App\Http\Controllers\Api\V2\Staff;

use App\Enums\DecisionTrigger;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\V2\Traits\ApiResponses;
use App\Models\ApplicationDecision;
use App\Models\DecisionPolicy;
use App\Models\Product;
use App\Models\StaffAccount;
use App\Services\Decision\DecisionEngineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Configurador del motor de decisión: políticas versionadas por tenant y por
 * producto, con activación/rollback y probador (dry-run).
 *
 * Todo el módulo requiere canManageProducts (ADMIN del tenant y SUPER_ADMIN):
 * cada admin configura las políticas de SU tenant (scopedTenantId; el super
 * admin cruza tenants vía TenantSwitcher). Ver rutas en routes/api.php.
 */
class DecisionPolicyController extends Controller
{
    use ApiResponses;

    public function __construct(private DecisionEngineService $engine)
    {
    }

    private function scopedTenantId(StaffAccount $staff): string
    {
        if ($staff->isSuperAdmin()) {
            $tenant = app('tenant');
            if ($tenant && isset($tenant->id)) {
                return (string) $tenant->id;
            }
        }

        return (string) $staff->tenant_id;
    }

    /**
     * Catálogo de variables de scoring: llaves conocidas por el motor con sus
     * valores válidos y etiquetas en español (fuente de verdad = los enums del
     * backend). El editor del configurador arma selects con esto — el admin
     * nunca teclea llaves ni valores a mano.
     *
     * GET /v2/staff/decision-policies/catalog
     */
    public function catalog(): JsonResponse
    {
        return $this->success([
            'variables' => [
                [
                    'key' => 'salary_range',
                    'label' => 'Rango salarial mensual',
                    'description' => 'Declarado en el onboarding (paso de rango salarial)',
                    'values' => \App\Enums\SalaryRange::toOptions(),
                ],
                [
                    'key' => 'employment_type',
                    'label' => 'Actividad laboral',
                    'description' => 'Declarada en el onboarding (tipo de actividad o trabajo)',
                    'values' => \App\Enums\EmploymentType::toOptions(),
                ],
                [
                    'key' => 'education_level',
                    'label' => 'Nivel educativo',
                    'description' => 'Declarado en el onboarding',
                    'values' => \App\Enums\EducationLevel::toOptions(),
                ],
                [
                    'key' => 'marital_status',
                    'label' => 'Estado civil',
                    'description' => 'Declarado en el onboarding',
                    'values' => \App\Enums\MaritalStatus::toOptions(),
                ],
                [
                    'key' => 'online_loans_count',
                    'label' => 'Créditos en línea declarados',
                    'description' => '¿Cuántas veces ha solicitado préstamos en línea? (declarado)',
                    'values' => [
                        ['value' => '0', 'label' => 'Ninguno'],
                        ['value' => '1', 'label' => '1 crédito'],
                        ['value' => '2', 'label' => '2 créditos'],
                        ['value' => '3', 'label' => '3 créditos'],
                        ['value' => '4', 'label' => '4 créditos'],
                        ['value' => '5+', 'label' => '5 o más'],
                    ],
                ],
                [
                    'key' => 'phone_risk_level',
                    'label' => 'Riesgo telefónico (Nubarium)',
                    'description' => 'Nivel del Phone Risk Score consultado tras el OTP (verificable)',
                    'values' => [
                        ['value' => 'very-low', 'label' => 'Muy bajo'],
                        ['value' => 'low', 'label' => 'Bajo'],
                        ['value' => 'moderate', 'label' => 'Moderado'],
                        ['value' => 'high', 'label' => 'Alto'],
                    ],
                ],
                [
                    'key' => 'state',
                    'label' => 'Estado (domicilio)',
                    'description' => 'Estado capturado en el domicilio — valor libre, ej. "Sinaloa"',
                    'values' => null,
                ],
                [
                    'key' => 'city',
                    'label' => 'Ciudad (domicilio)',
                    'description' => 'Ciudad capturada en el domicilio — valor libre, ej. "Culiacán"',
                    'values' => null,
                ],
            ],
        ]);
    }

    /**
     * Listado de políticas del tenant (todas las versiones, agrupables por
     * alcance en el frontend).
     *
     * GET /v2/staff/decision-policies
     */
    public function index(Request $request): JsonResponse
    {
        /** @var StaffAccount $staff */
        $staff = $request->user();
        $tenantId = $this->scopedTenantId($staff);

        $policies = DecisionPolicy::withoutGlobalScopes()
            ->with('product:id,name,code')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->orderByRaw('product_id NULLS FIRST')
            ->orderByDesc('version')
            ->get();

        return $this->success([
            'policies' => $policies->map(fn (DecisionPolicy $p) => array_merge($p->toApiArray(), [
                'product' => $p->product ? [
                    'id' => $p->product->id,
                    'name' => $p->product->name,
                    'code' => $p->product->code,
                ] : null,
            ]))->values(),
        ]);
    }

    /**
     * Crea una versión nueva (borrador) para el alcance indicado.
     *
     * POST /v2/staff/decision-policies
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'nullable|uuid',
            'rules' => 'required|array',
            'notes' => 'nullable|string|max:500',
            'mode' => 'nullable|string|in:OFF,SHADOW,ACTIVE',
        ]);

        /** @var StaffAccount $staff */
        $staff = $request->user();
        $tenantId = $this->scopedTenantId($staff);
        $productId = $validated['product_id'] ?? null;

        if ($productId) {
            $product = Product::withoutGlobalScope('tenant')
                ->where('tenant_id', $tenantId)
                ->find($productId);
            if (!$product) {
                return $this->notFound('El producto no pertenece a este tenant.');
            }
        }

        $errors = $this->validateRulesCoherence($validated['rules'], $productId ? ($product ?? null) : null, $productId === null);
        if (!empty($errors)) {
            return $this->validationError('La política tiene incoherencias.', ['rules' => $errors]);
        }

        $policy = DecisionPolicy::create([
            'tenant_id' => $tenantId,
            'product_id' => $productId,
            'version' => DecisionPolicy::nextVersionFor($tenantId, $productId),
            'mode' => $validated['mode'] ?? 'SHADOW',
            'is_active' => false,
            'rules' => $validated['rules'],
            'notes' => $validated['notes'] ?? null,
        ]);

        return $this->created($policy->toApiArray(), 'Versión creada como borrador.');
    }

    /**
     * Edita un borrador (las versiones ya activadas son inmutables: para
     * cambiarlas se crea una versión nueva).
     *
     * PUT /v2/staff/decision-policies/{id}
     */
    public function update(Request $request, string $id): JsonResponse
    {
        /** @var StaffAccount $staff */
        $staff = $request->user();

        $policy = DecisionPolicy::withoutGlobalScopes()
            ->where('tenant_id', $this->scopedTenantId($staff))
            ->whereNull('deleted_at')
            ->find($id);
        if (!$policy) {
            return $this->notFound('Política no encontrada.');
        }

        if ($policy->is_active || $policy->activated_at !== null) {
            return $this->validationError(
                'Las versiones activadas son inmutables. Crea una versión nueva para editar.',
                ['policy' => ['Versión inmutable']]
            );
        }

        $validated = $request->validate([
            'rules' => 'required|array',
            'notes' => 'nullable|string|max:500',
            'mode' => 'nullable|string|in:OFF,SHADOW,ACTIVE',
        ]);

        $product = $policy->product_id
            ? Product::withoutGlobalScope('tenant')->find($policy->product_id)
            : null;
        $errors = $this->validateRulesCoherence($validated['rules'], $product, $policy->product_id === null);
        if (!empty($errors)) {
            return $this->validationError('La política tiene incoherencias.', ['rules' => $errors]);
        }

        $policy->update([
            'rules' => $validated['rules'],
            'notes' => $validated['notes'] ?? $policy->notes,
            'mode' => $validated['mode'] ?? $policy->mode,
        ]);

        return $this->success($policy->fresh()->toApiArray(), 'Borrador actualizado.');
    }

    /**
     * Activa una versión (rollback incluido: activar una versión anterior) y
     * opcionalmente cambia su modo. Activar en ACTIVE requiere confirmación
     * explícita del frontend.
     *
     * POST /v2/staff/decision-policies/{id}/activate
     */
    public function activate(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'mode' => 'nullable|string|in:OFF,SHADOW,ACTIVE',
        ]);

        /** @var StaffAccount $staff */
        $staff = $request->user();

        $policy = DecisionPolicy::withoutGlobalScopes()
            ->where('tenant_id', $this->scopedTenantId($staff))
            ->whereNull('deleted_at')
            ->find($id);
        if (!$policy) {
            return $this->notFound('Política no encontrada.');
        }

        if (!empty($validated['mode'])) {
            $policy->update(['mode' => $validated['mode']]);
        }

        $policy->activate($staff->id);

        return $this->success($policy->fresh()->toApiArray(), "Versión {$policy->version} activada en modo {$policy->mode}.");
    }

    /**
     * Probador (dry-run): evalúa un perfil hipotético sin tocar solicitudes
     * reales, contra una versión guardada (`policy_id`) O contra las reglas en
     * edición (`rules` + `product_id`, sin persistir) — "probar sin guardar".
     *
     * POST /v2/staff/decision-policies/dry-run
     */
    public function dryRun(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'policy_id' => 'nullable|uuid|required_without:rules',
            'rules' => 'nullable|array|required_without:policy_id',
            'product_id' => 'nullable|uuid|required_with:rules',
            'profile' => 'required|array',
            'profile.requested_amount' => 'nullable|numeric|min:1',
            'profile.requested_term_days' => 'nullable|integer|min:1',
            'profile.variables' => 'nullable|array',
            'profile.kyc_status' => 'nullable|string',
            'profile.identity_mismatch' => 'nullable|boolean',
            'profile.phone_risk_score' => 'nullable|integer|min:0|max:1000',
            'profile.phone_risk_level' => 'nullable|string',
            'profile.renewal_history' => 'nullable|array',
        ]);

        /** @var StaffAccount $staff */
        $staff = $request->user();
        $tenantId = $this->scopedTenantId($staff);

        if (!empty($validated['rules'])) {
            // Reglas en edición: validar coherencia y evaluar con una política
            // efímera (no se persiste ninguna versión).
            $product = Product::withoutGlobalScope('tenant')
                ->where('tenant_id', $tenantId)
                ->find($validated['product_id']);
            if (!$product) {
                return $this->notFound('El producto no pertenece a este tenant.');
            }

            $errors = $this->validateRulesCoherence($validated['rules'], $product, false);
            if (!empty($errors)) {
                return $this->validationError('La política tiene incoherencias.', ['rules' => $errors]);
            }

            $policy = new DecisionPolicy([
                'product_id' => $product->id,
                'version' => 0, // 0 = configuración en edición, sin versión
                'mode' => 'SHADOW',
                'rules' => $validated['rules'],
            ]);
            $policy->tenant_id = $tenantId;

            return $this->runDryEvaluation($policy, $validated['profile'], $tenantId, persist: false);
        }

        $policy = DecisionPolicy::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->find($validated['policy_id']);
        if (!$policy) {
            return $this->notFound('Política no encontrada.');
        }
        if ($policy->product_id === null) {
            return $this->validationError(
                'El probador evalúa políticas de producto. La política de tenant (filtros) se prueba con el score telefónico del perfil.',
                ['policy_id' => ['Selecciona una política de producto']]
            );
        }

        return $this->runDryEvaluation($policy, $validated['profile'], $tenantId, persist: true);
    }

    /**
     * Evalúa el perfil contra la política (guardada o efímera) y devuelve la
     * decisión. Solo persiste la corrida en application_decisions cuando la
     * política existe en BD ($persist) — las reglas en edición no dejan rastro.
     */
    private function runDryEvaluation(DecisionPolicy $policy, array $profile, string $tenantId, bool $persist): JsonResponse
    {
        $product = Product::withoutGlobalScope('tenant')->find($policy->product_id);

        // Renovación hipotética: historial de pagos → graduación.
        if (!empty($profile['renewal_history'])) {
            $result = $this->engine->evaluateRenewal($policy, $profile['renewal_history'], [
                'product_min_amount' => (float) ($product?->min_amount ?? 300),
            ]);
        } else {
            $result = $this->engine->evaluate($policy, [
                'kyc_status' => $profile['kyc_status'] ?? 'VERIFIED',
                'identity_mismatch' => (bool) ($profile['identity_mismatch'] ?? false),
                'phone_risk' => [
                    'score' => $profile['phone_risk_score'] ?? null,
                    'level' => $profile['phone_risk_level'] ?? null,
                ],
                'bank' => ['has_account' => true, 'is_verified' => true, 'clabe_result' => null],
                'variables' => (array) ($profile['variables'] ?? []),
            ], [
                'requested_amount' => (float) ($profile['requested_amount'] ?? 0),
                'requested_term_days' => $profile['requested_term_days'] ?? null,
                'product_min_amount' => (float) ($product?->min_amount ?? 300),
                'phone_gate' => $this->gateOutcomeForScore($tenantId, $profile['phone_risk_score'] ?? null),
            ]);
        }

        if ($persist) {
            ApplicationDecision::create([
                'tenant_id' => $tenantId,
                'decision_policy_id' => $policy->id,
                'policy_version' => $policy->version,
                'trigger' => DecisionTrigger::DRY_RUN->value,
                'mode' => $policy->mode,
                'inputs' => $profile,
                'rule_hits' => $result['rule_hits'],
                'score' => $result['score'],
                'band' => $result['band'],
                'outcome' => $result['outcome'],
                'outcome_detail' => ['range' => $result['range'], 'reasons' => $result['reasons']],
                'executed' => false,
            ]);
        }

        return $this->success([
            'policy_version' => $policy->version,
            'result' => $result,
        ], 'Evaluación de prueba completada (sin efectos).');
    }

    /**
     * Outcome del gate telefónico para un score hipotético, usando la política
     * de tenant activa (para que el probador refleje el flujo completo).
     */
    private function gateOutcomeForScore(string $tenantId, ?int $score): ?string
    {
        if ($score === null) {
            return null;
        }

        $tenantPolicy = DecisionPolicy::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereNull('product_id')
            ->where('is_active', true)
            ->first();

        $gate = (array) ($tenantPolicy?->rule('phone_risk_gate', []) ?? []);
        if (!($gate['enabled'] ?? false)) {
            return null;
        }

        return match (true) {
            $score >= (int) ($gate['block_from'] ?? 601) => 'BLOCK',
            $score >= (int) ($gate['flag_from'] ?? 401) => 'FLAG',
            default => 'ALLOW',
        };
    }

    /**
     * Validación de coherencia de la política antes de guardar.
     *
     * @return string[] Errores legibles (vacío = coherente).
     */
    private function validateRulesCoherence(array $rules, ?Product $product, bool $isTenantPolicy): array
    {
        $errors = [];

        if ($isTenantPolicy) {
            $gate = (array) ($rules['phone_risk_gate'] ?? []);
            if (!empty($gate)) {
                $flagFrom = (int) ($gate['flag_from'] ?? 401);
                $blockFrom = (int) ($gate['block_from'] ?? 601);
                if ($flagFrom >= $blockFrom) {
                    $errors[] = 'El umbral de alerta (flag_from) debe ser menor al de bloqueo (block_from).';
                }
            }
            $cooldownDays = $rules['cooldown']['days'] ?? null;
            if ($cooldownDays !== null && (int) $cooldownDays < 0) {
                $errors[] = 'Los días de cooldown no pueden ser negativos.';
            }

            return $errors;
        }

        // --- Política de producto ---
        $cutoffs = (array) ($rules['scoring']['band_cutoffs'] ?? []);
        $lastScore = -1;
        foreach ($cutoffs as $cutoff) {
            $min = (int) ($cutoff['min_score'] ?? 0);
            if ($min <= $lastScore) {
                $errors[] = 'Los cortes de puntaje deben ser estrictamente crecientes.';
                break;
            }
            $lastScore = $min;
        }

        $bands = collect((array) ($rules['bands'] ?? []))->sortBy('min_amount')->values();
        $prevMax = null;
        foreach ($bands as $band) {
            $min = (float) ($band['min_amount'] ?? 0);
            $max = (float) ($band['max_amount'] ?? 0);
            if ($min > $max) {
                $errors[] = "La banda {$band['key']} tiene monto mínimo mayor al máximo.";
            }
            if ($prevMax !== null && $min <= $prevMax) {
                $errors[] = 'Las bandas no deben traslaparse.';
            }
            $prevMax = $max;
            if ($product && ($min < (float) $product->min_amount || $max > (float) $product->max_amount)) {
                $errors[] = "La banda {$band['key']} sale de los límites del producto ({$product->min_amount}-{$product->max_amount}).";
            }
        }

        foreach ($cutoffs as $cutoff) {
            $bandKey = $cutoff['band'] ?? null;
            if ($bandKey && !$bands->firstWhere('key', $bandKey)) {
                $errors[] = "El corte apunta a la banda {$bandKey} que no existe.";
            }
        }

        $termDays = $rules['first_credit']['term_days'] ?? null;
        if ($termDays !== null && (int) $termDays < 1) {
            $errors[] = 'El plazo del primer crédito debe ser al menos 1 día.';
        }

        $validity = $rules['offer']['validity_hours'] ?? null;
        if ($validity !== null && (int) $validity < 1) {
            $errors[] = 'La vigencia de la oferta debe ser al menos 1 hora.';
        }

        $levels = (array) ($rules['graduation']['levels'] ?? []);
        $lastLevel = -1;
        $lastAmount = 0.0;
        foreach ($levels as $level) {
            $n = (int) ($level['level'] ?? 0);
            $amount = (float) ($level['max_amount'] ?? 0);
            if ($n <= $lastLevel) {
                $errors[] = 'Los niveles de graduación deben ser crecientes.';
                break;
            }
            if ($amount < $lastAmount) {
                $errors[] = 'El cupo máximo por nivel no puede decrecer.';
                break;
            }
            $lastLevel = $n;
            $lastAmount = $amount;
        }

        return array_values(array_unique($errors));
    }
}
