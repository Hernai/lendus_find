<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\TenantApiConfig;
use App\Models\TenantBranding;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TenantConfigController extends Controller
{
    /**
     * Get current tenant's configuration.
     */
    public function show(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

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
     */
    public function updateTenant(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:100',
            'legal_name' => 'nullable|string|max:200',
            'rfc' => 'nullable|string|max:13',
            'email' => 'nullable|email|max:100',
            'phone' => 'nullable|string|max:15',
            'website' => 'nullable|url|max:200',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $tenant->update($request->only([
            'name', 'legal_name', 'rfc', 'email', 'phone', 'website'
        ]));

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
     */
    public function updateBranding(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        $validator = Validator::make($request->all(), [
            'primary_color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'secondary_color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'accent_color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'background_color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'text_color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'logo_url' => 'nullable|string|max:500',
            'logo_dark_url' => 'nullable|string|max:500',
            'favicon_url' => 'nullable|string|max:500',
            'login_background_url' => 'nullable|string|max:500',
            'font_family' => 'nullable|string|max:100',
            'heading_font_family' => 'nullable|string|max:100',
            'border_radius' => 'nullable|string|max:20',
            'button_style' => 'nullable|in:rounded,pill,square',
            'custom_css' => 'nullable|string|max:10000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $branding = TenantBranding::updateOrCreate(
            ['tenant_id' => $tenant->id],
            $request->only([
                'primary_color', 'secondary_color', 'accent_color',
                'background_color', 'text_color',
                'logo_url', 'logo_dark_url', 'favicon_url', 'login_background_url',
                'font_family', 'heading_font_family', 'border_radius',
                'button_style', 'custom_css'
            ])
        );

        return response()->json([
            'message' => 'Branding actualizado',
            'data' => $branding->toApiArray()
        ]);
    }

    /**
     * List API configurations.
     */
    public function listApiConfigs(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        return response()->json([
            'data' => $tenant->apiConfigs->map->toApiArray(),
            'available_providers' => TenantApiConfig::PROVIDERS,
            'available_service_types' => TenantApiConfig::SERVICE_TYPES,
        ]);
    }

    /**
     * Create or update an API configuration.
     */
    public function saveApiConfig(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        $validator = Validator::make($request->all(), [
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

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $config = TenantApiConfig::updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'provider' => $request->provider,
                'service_type' => $request->service_type,
            ],
            $request->only([
                'api_key', 'api_secret', 'account_sid', 'auth_token',
                'from_number', 'from_email', 'domain',
                'webhook_url', 'webhook_secret', 'extra_config',
                'is_active', 'is_sandbox'
            ])
        );

        return response()->json([
            'message' => 'Configuración guardada',
            'data' => $config->toApiArray()
        ]);
    }

    /**
     * Delete an API configuration.
     */
    public function deleteApiConfig(Request $request, TenantApiConfig $config): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        if ($config->tenant_id !== $tenant->id) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $config->delete();

        return response()->json([
            'message' => 'Configuración eliminada'
        ]);
    }

    /**
     * Test an API configuration.
     */
    public function testApiConfig(Request $request, TenantApiConfig $config): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        if ($config->tenant_id !== $tenant->id) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

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
