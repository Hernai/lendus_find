<?php

namespace App\Http\Controllers\Api\V2\Staff;

use App\Http\Controllers\Controller;
use App\Models\StaffAccount;
use App\Models\TenantApiConfig;
use App\Models\TenantBranding;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Staff Config Controller (v2).
 *
 * Handles tenant configuration management for staff users.
 */
class ConfigController extends Controller
{
    /**
     * Get current tenant's configuration.
     *
     * GET /v2/staff/config
     */
    public function show(Request $request): JsonResponse
    {
        /** @var StaffAccount $staff */
        $staff = $request->user();
        $tenant = $staff->tenant;

        return response()->json([
            'data' => [
                'tenant' => [
                    'id' => $tenant->id,
                    'name' => $tenant->name,
                    'slug' => $tenant->slug,
                    'legal_name' => $tenant->legal_name,
                    'rfc' => $tenant->rfc,
                    'email' => $tenant->email,
                    'phone' => $tenant->phone,
                    'website' => $tenant->website,
                ],
                'branding' => $tenant->brandingConfig?->toApiArray() ?? $this->getDefaultBranding(),
                'api_configs' => $tenant->apiConfigs->map->toApiArray(),
                'available_providers' => TenantApiConfig::PROVIDERS,
                'available_service_types' => TenantApiConfig::SERVICE_TYPES,
            ]
        ]);
    }

    /**
     * Update tenant basic info.
     *
     * PUT /v2/staff/config/tenant
     */
    public function updateTenant(Request $request): JsonResponse
    {
        /** @var StaffAccount $staff */
        $staff = $request->user();
        $tenant = $staff->tenant;

        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'legal_name' => 'nullable|string|max:200',
            'rfc' => 'nullable|string|max:13',
            'email' => 'nullable|email|max:100',
            'phone' => 'nullable|string|max:15',
            'website' => 'nullable|url|max:200',
        ]);

        $tenant->update($validated);

        return response()->json([
            'message' => 'Información actualizada',
            'data' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'legal_name' => $tenant->legal_name,
                'rfc' => $tenant->rfc,
                'email' => $tenant->email,
                'phone' => $tenant->phone,
                'website' => $tenant->website,
            ]
        ]);
    }

    /**
     * Update tenant branding.
     *
     * PUT /v2/staff/config/branding
     */
    public function updateBranding(Request $request): JsonResponse
    {
        /** @var StaffAccount $staff */
        $staff = $request->user();
        $tenant = $staff->tenant;

        $validated = $request->validate([
            'primary_color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'secondary_color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'accent_color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'background_color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'text_color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'logo_url' => 'nullable|string|max:500000',
            'logo_dark_url' => 'nullable|string|max:500000',
            'favicon_url' => 'nullable|string|max:500000',
            'login_background_url' => 'nullable|string|max:500000',
            'font_family' => 'nullable|string|max:100',
            'heading_font_family' => 'nullable|string|max:100',
            'border_radius' => 'nullable|string|max:20',
            'button_style' => 'nullable|in:rounded,pill,square',
            'custom_css' => 'nullable|string|max:10000',
        ]);

        $branding = TenantBranding::updateOrCreate(
            ['tenant_id' => $tenant->id],
            $validated
        );

        return response()->json([
            'message' => 'Branding actualizado',
            'data' => $branding->toApiArray()
        ]);
    }

    /**
     * List API configurations.
     *
     * GET /v2/staff/config/api-configs
     */
    public function listApiConfigs(Request $request): JsonResponse
    {
        /** @var StaffAccount $staff */
        $staff = $request->user();
        $tenant = $staff->tenant;

        return response()->json([
            'data' => $tenant->apiConfigs->map->toApiArray(),
            'available_providers' => TenantApiConfig::PROVIDERS,
            'available_service_types' => TenantApiConfig::SERVICE_TYPES,
        ]);
    }

    /**
     * Create or update an API configuration.
     *
     * POST /v2/staff/config/api-configs
     */
    public function saveApiConfig(Request $request): JsonResponse
    {
        /** @var StaffAccount $staff */
        $staff = $request->user();
        $tenant = $staff->tenant;

        $validated = $request->validate([
            'provider' => 'required|string|max:50',
            'service_type' => 'required|string|max:50',
            'api_key' => 'nullable|string|max:500',
            'api_secret' => 'nullable|string|max:500',
            'account_sid' => 'nullable|string|max:100',
            'auth_token' => 'nullable|string|max:500',
            'from_number' => 'nullable|string|max:20',
            'from_email' => 'nullable|email|max:100',
            'domain' => 'nullable|string|max:200',
            'webhook_url' => 'nullable|url|max:500',
            'webhook_secret' => 'nullable|string|max:100',
            'extra_config' => 'nullable|array',
            'is_active' => 'boolean',
            'is_sandbox' => 'boolean',
        ]);

        $config = TenantApiConfig::updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'provider' => $validated['provider'],
                'service_type' => $validated['service_type'],
            ],
            collect($validated)->except(['provider', 'service_type'])->toArray()
        );

        return response()->json([
            'message' => 'Configuración guardada',
            'data' => $config->toApiArray()
        ]);
    }

    /**
     * Delete an API configuration.
     *
     * DELETE /v2/staff/config/api-configs/{id}
     */
    public function deleteApiConfig(Request $request, string $id): JsonResponse
    {
        /** @var StaffAccount $staff */
        $staff = $request->user();
        $tenant = $staff->tenant;

        $config = TenantApiConfig::where('tenant_id', $tenant->id)
            ->where('id', $id)
            ->firstOrFail();

        $config->delete();

        return response()->json([
            'message' => 'Configuración eliminada'
        ]);
    }

    /**
     * Test an API configuration.
     *
     * POST /v2/staff/config/api-configs/{id}/test
     */
    public function testApiConfig(Request $request, string $id): JsonResponse
    {
        /** @var StaffAccount $staff */
        $staff = $request->user();
        $tenant = $staff->tenant;

        $config = TenantApiConfig::where('tenant_id', $tenant->id)
            ->where('id', $id)
            ->firstOrFail();

        // TODO: Implement actual API testing per provider
        $success = $config->hasCredentials();
        $error = $success ? null : 'Credenciales incompletas';

        $config->update([
            'last_tested_at' => now(),
            'last_test_success' => $success,
            'last_test_error' => $error,
        ]);

        return response()->json([
            'success' => $success,
            'message' => $success ? 'Conexión exitosa' : $error,
            'data' => $config->toApiArray()
        ]);
    }

    /**
     * Get default branding values.
     */
    private function getDefaultBranding(): array
    {
        return [
            'primary_color' => '#6366f1',
            'secondary_color' => '#10b981',
            'accent_color' => '#f59e0b',
            'background_color' => '#ffffff',
            'text_color' => '#1f2937',
            'logo_url' => null,
            'logo_dark_url' => null,
            'favicon_url' => null,
            'login_background_url' => null,
            'font_family' => 'Inter, sans-serif',
            'heading_font_family' => null,
            'border_radius' => '12px',
            'button_style' => 'rounded',
            'custom_css' => null,
        ];
    }
}
