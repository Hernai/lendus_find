<?php

namespace App\Http\Controllers\Api\V2\Staff;

use App\Http\Controllers\Api\V2\Traits\ApiResponses;
use App\Http\Controllers\Controller;
use App\Models\StaffAccount;
use App\Models\TenantApiConfig;
use App\Services\KycServiceFactory;
use App\Services\TwilioServiceFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;

/**
 * Staff Integration Controller (v2).
 *
 * Handles API integration management for staff users.
 */
class IntegrationController extends Controller
{
    use ApiResponses;
    public function __construct(
        protected KycServiceFactory $kycFactory,
        protected TwilioServiceFactory $twilioFactory
    ) {}

    /**
     * List all integrations for current tenant.
     *
     * GET /v2/staff/integrations
     */
    public function index(Request $request): JsonResponse
    {
        $tenant = app('tenant');

        // Cache 5 min — las integraciones del tenant cambian solo cuando
        // el super_admin las edita. Invalida via V2ConfigCacheObserver
        // (TenantApiConfig::saved) que ya esta registrado.
        $integrations = Cache::remember(
            "integrations:list:{$tenant->id}",
            300,
            fn () => TenantApiConfig::where('tenant_id', $tenant->id)
                ->orderBy('provider')
                ->orderBy('service_type')
                ->get()
                ->map(fn ($config) => $config->toApiArray())
                ->all()
        );

        return $this->success([
            'integrations' => $integrations,
        ]);
    }

    /**
     * Clave de cache compartida con observers. Manten sincronizada con
     * V2ConfigCacheObserver y futuros invalidadores.
     */
    public static function listCacheKey(string $tenantId): string
    {
        return "integrations:list:{$tenantId}";
    }

    /**
     * Get available providers and service types.
     *
     * GET /v2/staff/integrations/options
     */
    public function options(): JsonResponse
    {
        return $this->success([
            // Catálogo con estado (available | beta | coming_soon) para que el
            // admin muestre badges y bloquee los "próximamente" en el alta.
            'providers' => TenantApiConfig::providerCatalog(),
            // Compat: mapa key=>label por si algún consumidor viejo lo usa.
            'providers_map' => TenantApiConfig::PROVIDERS,
            'service_types' => TenantApiConfig::SERVICE_TYPES,
            // Descripción de cada servicio: para que el admin sepa qué habilita.
            'service_type_descriptions' => TenantApiConfig::SERVICE_TYPE_DESCRIPTIONS,
        ]);
    }

    /**
     * Create or update an integration.
     *
     * POST /v2/staff/integrations
     */
    public function store(Request $request): JsonResponse
    {
        /** @var StaffAccount $staff */
        $staff = $request->user();
        // El super admin global tiene tenant_id NULL; usamos el tenant
        // resuelto del header X-Tenant-ID. RequireStaff valida cross-tenant.
        $tenant = app('tenant');

        $validator = Validator::make($request->all(), [
            'provider' => 'required|string|in:' . implode(',', array_keys(TenantApiConfig::PROVIDERS)),
            'service_type' => 'required|string|in:' . implode(',', array_keys(TenantApiConfig::SERVICE_TYPES)),
            'account_sid' => 'nullable|string',
            'auth_token' => 'nullable|string',
            'api_key' => 'nullable|string',
            'api_secret' => 'nullable|string',
            'from_number' => 'nullable|string|max:20',
            'from_email' => 'nullable|email|max:100',
            'domain' => 'nullable|string|max:200',
            'webhook_url' => 'nullable|url|max:500',
            'webhook_secret' => 'nullable|string|max:100',
            'extra_config' => 'nullable|array',
            'is_active' => 'boolean',
            'is_sandbox' => 'boolean',
        ]);

        if ($validator->fails()) {
            return $this->validationError('Error de validación', $validator->errors()->toArray());
        }

        // Bloquear proveedores "próximamente" (sin implementación real) — el
        // frontend ya los deshabilita en el dropdown, esto es defensa server-side.
        if (TenantApiConfig::statusFor($request->provider) === 'coming_soon') {
            return $this->error(
                'PROVIDER_NOT_AVAILABLE',
                'Este proveedor aún no está disponible (próximamente).',
                422
            );
        }

        // Check if config already exists
        $config = TenantApiConfig::where('tenant_id', $tenant->id)
            ->where('provider', $request->provider)
            ->where('service_type', $request->service_type)
            ->first();

        $data = [
            'tenant_id' => $tenant->id,
            'provider' => $request->provider,
            'service_type' => $request->service_type,
            'is_active' => $request->is_active ?? true,
            'is_sandbox' => $request->is_sandbox ?? (app()->environment() !== 'production'),
        ];

        // Only update credentials if provided (don't overwrite with null)
        if ($request->filled('account_sid')) {
            $data['account_sid'] = $request->account_sid;
        }
        if ($request->filled('auth_token')) {
            $data['auth_token'] = $request->auth_token;
        }
        if ($request->filled('api_key')) {
            $data['api_key'] = $request->api_key;
        }
        if ($request->filled('api_secret')) {
            $data['api_secret'] = $request->api_secret;
        }
        if ($request->filled('from_number')) {
            $data['from_number'] = $request->from_number;
        }
        if ($request->filled('from_email')) {
            $data['from_email'] = $request->from_email;
        }
        if ($request->filled('domain')) {
            $data['domain'] = $request->domain;
        }
        if ($request->filled('webhook_url')) {
            $data['webhook_url'] = $request->webhook_url;
        }
        if ($request->filled('webhook_secret')) {
            $data['webhook_secret'] = $request->webhook_secret;
        }
        if ($request->filled('extra_config')) {
            $data['extra_config'] = $request->extra_config;
        }

        if ($config) {
            $config->update($data);
        } else {
            $config = TenantApiConfig::create($data);
        }

        // Invalidate cache for this provider/service
        $this->clearIntegrationCache($tenant->id, $request->provider, $request->service_type);

        return $this->success([
            'integration' => $config->toApiArray(),
        ], 'Integración guardada correctamente');
    }

    /**
     * Clear cached integration config.
     */
    protected function clearIntegrationCache(string $tenantId, string $provider, string $serviceType): void
    {
        $cacheKey = "tenant:{$tenantId}:api_config:{$provider}:{$serviceType}";
        cache()->forget($cacheKey);
    }

    /**
     * Test an integration.
     *
     * POST /v2/staff/integrations/{id}/test
     */
    public function test(Request $request, string $id): JsonResponse
    {
        /** @var StaffAccount $staff */
        $staff = $request->user();
        // El super admin global tiene tenant_id NULL; usamos el tenant
        // resuelto del header X-Tenant-ID. RequireStaff valida cross-tenant.
        $tenant = app('tenant');

        $config = TenantApiConfig::where('tenant_id', $tenant->id)
            ->where('id', $id)
            ->firstOrFail();

        // Validation depends on provider/service type
        $rules = [];
        if (in_array($config->provider, ['twilio', 'nubarium'], true) && in_array($config->service_type, ['sms', 'whatsapp'])) {
            $rules['test_phone'] = 'required|string';
        } elseif ($config->service_type === 'email') {
            $rules['test_email'] = 'required|email';
        }

        if (!empty($rules)) {
            $validator = Validator::make($request->all(), $rules);
            if ($validator->fails()) {
                return $this->validationError('Se requiere número de teléfono para la prueba', $validator->errors()->toArray());
            }
        }

        // Clear cache before testing to ensure fresh credentials
        $this->clearIntegrationCache($tenant->id, $config->provider, $config->service_type);

        // Prueba real vía servicio compartido (misma lógica que ConfigController
        // y TenantController). Envía SMS/OTP/email según el proveedor.
        $result = app(\App\Services\IntegrationTester::class)->test(
            $config,
            $request->input('test_phone'),
            $request->input('test_email'),
        );

        if ($result['success']) {
            return $this->success(['details' => $result['details'] ?? []], $result['message']);
        }

        return $this->badRequest($result['error'] ?? 'TEST_FAILED', $result['message']);
    }

    /**
     * Toggle integration active status (enable/disable).
     *
     * PATCH /v2/staff/integrations/{id}/toggle
     */
    public function toggle(Request $request, string $id): JsonResponse
    {
        /** @var StaffAccount $staff */
        $staff = $request->user();
        // El super admin global tiene tenant_id NULL; usamos el tenant
        // resuelto del header X-Tenant-ID. RequireStaff valida cross-tenant.
        $tenant = app('tenant');

        $config = TenantApiConfig::where('tenant_id', $tenant->id)
            ->where('id', $id)
            ->firstOrFail();

        $config->update([
            'is_active' => !$config->is_active,
        ]);

        return $this->success([
            'integration' => $config->toApiArray(),
        ], $config->is_active ? 'Integración habilitada' : 'Integración deshabilitada');
    }

    /**
     * Delete an integration.
     *
     * DELETE /v2/staff/integrations/{id}
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        /** @var StaffAccount $staff */
        $staff = $request->user();
        // El super admin global tiene tenant_id NULL; usamos el tenant
        // resuelto del header X-Tenant-ID. RequireStaff valida cross-tenant.
        $tenant = app('tenant');

        $config = TenantApiConfig::where('tenant_id', $tenant->id)
            ->where('id', $id)
            ->firstOrFail();

        $config->delete();

        return $this->success(null, 'Integración eliminada correctamente');
    }
}
