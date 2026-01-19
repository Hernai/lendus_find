<?php

namespace App\Http\Controllers\Api\V2\Staff;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\ApplicationV2;
use App\Models\Product;
use App\Models\StaffAccount;
use App\Models\Tenant;
use App\Models\TenantApiConfig;
use App\Models\TenantBranding;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Staff Tenant Controller (v2).
 *
 * Handles tenant management for super admin users.
 * Requires canConfigureTenant permission.
 */
class TenantController extends Controller
{
    /**
     * List all tenants.
     *
     * GET /v2/staff/tenants
     */
    public function index(Request $request): JsonResponse
    {
        $query = Tenant::with('brandingConfig');

        // Search filter
        if ($search = $request->input('search')) {
            $search = str_replace(['%', '_'], ['\\%', '\\_'], $search);
            $search = mb_substr($search, 0, 100);
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('slug', 'LIKE', "%{$search}%")
                    ->orWhere('legal_name', 'LIKE', "%{$search}%")
                    ->orWhere('rfc', 'LIKE', "%{$search}%")
                    ->orWhere('email', 'LIKE', "%{$search}%");
            });
        }

        // Active filter
        if ($request->has('active')) {
            $query->where('is_active', $request->boolean('active'));
        }

        // Sorting
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDir = $request->input('sort_dir', 'desc');
        $query->orderBy($sortBy, $sortDir);

        // Pagination
        $perPage = min($request->input('per_page', 20), 100);
        $tenants = $query->paginate($perPage);

        return response()->json([
            'data' => $tenants->map(fn($tenant) => $this->formatTenant($tenant)),
            'meta' => [
                'current_page' => $tenants->currentPage(),
                'from' => $tenants->firstItem(),
                'last_page' => $tenants->lastPage(),
                'per_page' => $tenants->perPage(),
                'to' => $tenants->lastItem(),
                'total' => $tenants->total(),
            ]
        ]);
    }

    /**
     * Get a specific tenant.
     *
     * GET /v2/staff/tenants/{id}
     */
    public function show(string $id): JsonResponse
    {
        $tenant = Tenant::findOrFail($id);

        return response()->json([
            'data' => $this->formatTenantDetailed($tenant)
        ]);
    }

    /**
     * Create a new tenant.
     *
     * POST /v2/staff/tenants
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'slug' => 'required|string|max:50|unique:tenants,slug|regex:/^[a-z0-9-]+$/',
            'legal_name' => 'nullable|string|max:200',
            'rfc' => 'nullable|string|max:13',
            'email' => 'nullable|email|max:100',
            'phone' => 'nullable|string|max:15',
            'website' => 'nullable|url|max:200',
            'branding' => 'nullable|array',
            'branding.primary_color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'branding.secondary_color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'branding.accent_color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'branding.logo_url' => 'nullable|string|max:500000',
            'branding.favicon_url' => 'nullable|string|max:500000',
            'branding.font_family' => 'nullable|string|max:100',
            'branding.border_radius' => 'nullable|string|max:20',
            'settings' => 'nullable|array',
            'webhook_config' => 'nullable|array',
            'is_active' => 'boolean',
        ], [
            'slug.regex' => 'El slug solo puede contener letras minúsculas, números y guiones',
            'slug.unique' => 'Este slug ya está en uso',
        ]);

        $tenant = Tenant::create([
            'id' => Str::uuid(),
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'legal_name' => $validated['legal_name'] ?? null,
            'rfc' => $validated['rfc'] ?? null,
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'website' => $validated['website'] ?? null,
            'branding' => $validated['branding'] ?? null,
            'settings' => $validated['settings'] ?? null,
            'webhook_config' => $validated['webhook_config'] ?? null,
            'is_active' => $request->boolean('is_active', true),
            'activated_at' => $request->boolean('is_active', true) ? now() : null,
        ]);

        return response()->json([
            'message' => 'Tenant creado exitosamente',
            'data' => $this->formatTenantDetailed($tenant)
        ], 201);
    }

    /**
     * Update a tenant.
     *
     * PUT /v2/staff/tenants/{id}
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $tenant = Tenant::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'slug' => 'sometimes|string|max:50|unique:tenants,slug,' . $tenant->id . '|regex:/^[a-z0-9-]+$/',
            'legal_name' => 'nullable|string|max:200',
            'rfc' => 'nullable|string|max:13',
            'email' => 'nullable|email|max:100',
            'phone' => 'nullable|string|max:15',
            'website' => 'nullable|url|max:200',
            'branding' => 'nullable|array',
            'settings' => 'nullable|array',
            'webhook_config' => 'nullable|array',
            'is_active' => 'sometimes|boolean',
        ], [
            'slug.regex' => 'El slug solo puede contener letras minúsculas, números y guiones',
            'slug.unique' => 'Este slug ya está en uso',
        ]);

        // Update basic fields
        $tenant->fill(collect($validated)->only([
            'name', 'slug', 'legal_name', 'rfc', 'email', 'phone', 'website'
        ])->toArray());

        // Update branding (merge with existing)
        if ($request->has('branding')) {
            $existingBranding = $tenant->branding ?? [];
            $tenant->branding = array_merge(
                is_array($existingBranding) ? $existingBranding : [],
                $validated['branding']
            );
        }

        // Update settings (merge with existing)
        if ($request->has('settings')) {
            $existingSettings = $tenant->settings ?? [];
            $tenant->settings = array_merge(
                is_array($existingSettings) ? $existingSettings : [],
                $validated['settings']
            );
        }

        // Update webhook config
        if ($request->has('webhook_config')) {
            $tenant->webhook_config = $validated['webhook_config'];
        }

        // Handle activation/deactivation
        if ($request->has('is_active')) {
            $wasActive = $tenant->is_active;
            $tenant->is_active = $request->boolean('is_active');

            if (!$wasActive && $tenant->is_active) {
                $tenant->activated_at = now();
                $tenant->suspended_at = null;
            } elseif ($wasActive && !$tenant->is_active) {
                $tenant->suspended_at = now();
            }
        }

        $tenant->save();

        return response()->json([
            'message' => 'Tenant actualizado exitosamente',
            'data' => $this->formatTenantDetailed($tenant)
        ]);
    }

    /**
     * Delete a tenant (soft delete).
     *
     * DELETE /v2/staff/tenants/{id}
     */
    public function destroy(string $id): JsonResponse
    {
        $tenant = Tenant::findOrFail($id);

        // Check if tenant has any data
        $hasUsers = $tenant->users()->count() > 0;
        $hasApplications = $tenant->applications()->count() > 0;

        if ($hasUsers || $hasApplications) {
            return response()->json([
                'message' => 'No se puede eliminar el tenant porque tiene usuarios o solicitudes asociadas',
                'error' => 'HAS_RELATED_DATA'
            ], 422);
        }

        $tenant->delete();

        return response()->json([
            'message' => 'Tenant eliminado exitosamente'
        ]);
    }

    /**
     * Get tenant statistics.
     *
     * GET /v2/staff/tenants/{id}/stats
     */
    public function stats(string $id): JsonResponse
    {
        $tenant = Tenant::findOrFail($id);

        $usersCount = User::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->count();

        $staffCount = StaffAccount::where('tenant_id', $tenant->id)->count();

        $applicationsCount = Application::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->count();

        $applicationsByStatus = Application::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $productsCount = Product::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->count();

        $activeProductsCount = Product::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->count();

        return response()->json([
            'data' => [
                'users_count' => $usersCount,
                'staff_count' => $staffCount,
                'applications_count' => $applicationsCount,
                'applications_by_status' => $applicationsByStatus,
                'products_count' => $productsCount,
                'active_products_count' => $activeProductsCount,
            ]
        ]);
    }

    /**
     * Get full configuration for a specific tenant.
     *
     * GET /v2/staff/tenants/{id}/config
     */
    public function getConfig(string $id): JsonResponse
    {
        $tenant = Tenant::findOrFail($id);

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
     * Update branding for a specific tenant.
     *
     * PUT /v2/staff/tenants/{id}/branding
     */
    public function updateBranding(Request $request, string $id): JsonResponse
    {
        $tenant = Tenant::findOrFail($id);

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
     * Upload a logo for a specific tenant.
     *
     * POST /v2/staff/tenants/{id}/upload-logo
     */
    public function uploadLogo(Request $request, string $id): JsonResponse
    {
        $tenant = Tenant::findOrFail($id);

        $validated = $request->validate([
            'file' => 'required|file|mimes:png,jpg,jpeg,svg,webp,ico|max:2048',
            'field' => 'required|string|in:logo_url,logo_dark_url,favicon_url,login_background_url',
        ]);

        $file = $request->file('file');
        $field = $validated['field'];

        // Generate unique filename
        $extension = $file->getClientOriginalExtension();
        $filename = $tenant->slug . '_' . $field . '_' . time() . '.' . $extension;

        // Store the file
        $path = $file->storeAs(
            'tenants/' . $tenant->slug . '/branding',
            $filename,
            'public'
        );

        // Generate the public URL
        $url = Storage::disk('public')->url($path);

        // Update the branding record
        TenantBranding::updateOrCreate(
            ['tenant_id' => $tenant->id],
            [$field => $url]
        );

        return response()->json([
            'message' => 'Logo subido correctamente',
            'url' => $url,
            'field' => $field
        ]);
    }

    /**
     * List API configurations for a specific tenant.
     *
     * GET /v2/staff/tenants/{id}/api-configs
     */
    public function listApiConfigs(string $id): JsonResponse
    {
        $tenant = Tenant::findOrFail($id);

        return response()->json([
            'data' => $tenant->apiConfigs->map->toApiArray(),
            'available_providers' => TenantApiConfig::PROVIDERS,
            'available_service_types' => TenantApiConfig::SERVICE_TYPES,
        ]);
    }

    /**
     * Create or update an API configuration for a specific tenant.
     *
     * POST /v2/staff/tenants/{id}/api-configs
     */
    public function saveApiConfig(Request $request, string $id): JsonResponse
    {
        $tenant = Tenant::findOrFail($id);

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
     * Delete an API configuration for a specific tenant.
     *
     * DELETE /v2/staff/tenants/{tenantId}/api-configs/{configId}
     */
    public function deleteApiConfig(string $tenantId, string $configId): JsonResponse
    {
        $tenant = Tenant::findOrFail($tenantId);

        $config = TenantApiConfig::where('tenant_id', $tenant->id)
            ->where('id', $configId)
            ->firstOrFail();

        $config->delete();

        return response()->json([
            'message' => 'Configuración eliminada'
        ]);
    }

    /**
     * Test an API configuration for a specific tenant.
     *
     * POST /v2/staff/tenants/{tenantId}/api-configs/{configId}/test
     */
    public function testApiConfig(Request $request, string $tenantId, string $configId): JsonResponse
    {
        $tenant = Tenant::findOrFail($tenantId);

        $config = TenantApiConfig::where('tenant_id', $tenant->id)
            ->where('id', $configId)
            ->firstOrFail();

        // Simple test - just check credentials
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
            'data' => $config->fresh()->toApiArray()
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

    /**
     * Format tenant for list view.
     */
    private function formatTenant(Tenant $tenant): array
    {
        $usersCount = User::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->count();

        $applicationsCount = Application::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->count();

        return [
            'id' => $tenant->id,
            'name' => $tenant->name,
            'slug' => $tenant->slug,
            'legal_name' => $tenant->legal_name,
            'rfc' => $tenant->rfc,
            'email' => $tenant->email,
            'phone' => $tenant->phone,
            'website' => $tenant->website,
            'is_active' => $tenant->is_active,
            'branding' => [
                'primary_color' => $tenant->brandingConfig?->primary_color ?? $tenant->branding['primary_color'] ?? '#6366f1',
                'logo_url' => $tenant->brandingConfig?->logo_url ?? $tenant->branding['logo_url'] ?? null,
            ],
            'users_count' => $usersCount,
            'applications_count' => $applicationsCount,
            'created_at' => $tenant->created_at?->toISOString(),
            'activated_at' => $tenant->activated_at?->toISOString(),
            'suspended_at' => $tenant->suspended_at?->toISOString(),
        ];
    }

    /**
     * Format tenant for detailed view.
     */
    private function formatTenantDetailed(Tenant $tenant): array
    {
        return [
            'id' => $tenant->id,
            'name' => $tenant->name,
            'slug' => $tenant->slug,
            'legal_name' => $tenant->legal_name,
            'rfc' => $tenant->rfc,
            'email' => $tenant->email,
            'phone' => $tenant->phone,
            'website' => $tenant->website,
            'is_active' => $tenant->is_active,
            'branding' => $tenant->branding,
            'settings' => $tenant->settings,
            'webhook_config' => $tenant->webhook_config,
            'created_at' => $tenant->created_at?->toISOString(),
            'updated_at' => $tenant->updated_at?->toISOString(),
            'activated_at' => $tenant->activated_at?->toISOString(),
            'suspended_at' => $tenant->suspended_at?->toISOString(),
        ];
    }
}
