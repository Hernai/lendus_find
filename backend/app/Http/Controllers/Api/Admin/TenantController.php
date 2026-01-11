<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Applicant;
use App\Models\Application;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\TenantApiConfig;
use App\Models\TenantBranding;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class TenantController extends Controller
{
    /**
     * List all tenants.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Tenant::with('brandingConfig');

        // Search filter
        if ($search = $request->input('search')) {
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
                'last_page' => $tenants->lastPage(),
                'per_page' => $tenants->perPage(),
                'total' => $tenants->total(),
            ]
        ]);
    }

    /**
     * Get a specific tenant.
     */
    public function show(Tenant $tenant): JsonResponse
    {
        return response()->json([
            'data' => $this->formatTenantDetailed($tenant)
        ]);
    }

    /**
     * Create a new tenant.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
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
            'branding.logo_url' => 'nullable|string|max:500',
            'branding.favicon_url' => 'nullable|string|max:500',
            'branding.font_family' => 'nullable|string|max:100',
            'branding.border_radius' => 'nullable|string|max:20',
            'settings' => 'nullable|array',
            'settings.otp_provider' => 'nullable|string|in:twilio,messagebird,vonage',
            'settings.kyc_provider' => 'nullable|string|in:mati,onfido,jumio',
            'settings.max_loan_amount' => 'nullable|numeric|min:1000',
            'settings.min_loan_amount' => 'nullable|numeric|min:100',
            'settings.currency' => 'nullable|string|size:3',
            'settings.timezone' => 'nullable|string|max:50',
            'webhook_config' => 'nullable|array',
            'webhook_config.url' => 'nullable|url',
            'webhook_config.secret_key' => 'nullable|string|max:100',
            'webhook_config.events' => 'nullable|array',
            'is_active' => 'boolean',
        ], [
            'slug.regex' => 'El slug solo puede contener letras minúsculas, números y guiones',
            'slug.unique' => 'Este slug ya está en uso',
            'branding.primary_color.regex' => 'El color debe ser un código hexadecimal válido (ej: #2563eb)',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $tenant = Tenant::create([
            'id' => Str::uuid(),
            'name' => $request->name,
            'slug' => $request->slug,
            'legal_name' => $request->legal_name,
            'rfc' => $request->rfc,
            'email' => $request->email,
            'phone' => $request->phone,
            'website' => $request->website,
            'branding' => $request->branding,
            'settings' => $request->settings,
            'webhook_config' => $request->webhook_config,
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
     */
    public function update(Request $request, Tenant $tenant): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:100',
            'slug' => 'sometimes|string|max:50|unique:tenants,slug,' . $tenant->id . '|regex:/^[a-z0-9-]+$/',
            'legal_name' => 'nullable|string|max:200',
            'rfc' => 'nullable|string|max:13',
            'email' => 'nullable|email|max:100',
            'phone' => 'nullable|string|max:15',
            'website' => 'nullable|url|max:200',
            'branding' => 'nullable|array',
            'branding.primary_color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'branding.secondary_color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'branding.accent_color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'branding.logo_url' => 'nullable|string|max:500',
            'branding.favicon_url' => 'nullable|string|max:500',
            'branding.font_family' => 'nullable|string|max:100',
            'branding.border_radius' => 'nullable|string|max:20',
            'settings' => 'nullable|array',
            'settings.otp_provider' => 'nullable|string|in:twilio,messagebird,vonage',
            'settings.kyc_provider' => 'nullable|string|in:mati,onfido,jumio',
            'settings.max_loan_amount' => 'nullable|numeric|min:1000',
            'settings.min_loan_amount' => 'nullable|numeric|min:100',
            'settings.currency' => 'nullable|string|size:3',
            'settings.timezone' => 'nullable|string|max:50',
            'webhook_config' => 'nullable|array',
            'webhook_config.url' => 'nullable|url',
            'webhook_config.secret_key' => 'nullable|string|max:100',
            'webhook_config.events' => 'nullable|array',
            'is_active' => 'sometimes|boolean',
        ], [
            'slug.regex' => 'El slug solo puede contener letras minúsculas, números y guiones',
            'slug.unique' => 'Este slug ya está en uso',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Update basic fields
        $tenant->fill($request->only([
            'name', 'slug', 'legal_name', 'rfc', 'email', 'phone', 'website'
        ]));

        // Update branding (merge with existing)
        if ($request->has('branding')) {
            $tenant->branding = array_merge(
                $tenant->getRawOriginal('branding') ?? [],
                $request->branding
            );
        }

        // Update settings (merge with existing)
        if ($request->has('settings')) {
            $tenant->settings = array_merge(
                $tenant->getRawOriginal('settings') ?? [],
                $request->settings
            );
        }

        // Update webhook config
        if ($request->has('webhook_config')) {
            $tenant->webhook_config = $request->webhook_config;
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
     */
    public function destroy(Tenant $tenant): JsonResponse
    {
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
     */
    public function stats(Tenant $tenant): JsonResponse
    {
        // Bypass global tenant scope to get accurate counts for the specified tenant
        $usersCount = User::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->count();

        $staffCount = User::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->whereIn('type', ['SUPERVISOR', 'ANALYST', 'ADMIN', 'SUPER_ADMIN'])
            ->count();

        $applicantsCount = Applicant::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->count();

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
                'applicants_count' => $applicantsCount,
                'applications_count' => $applicationsCount,
                'applications_by_status' => $applicationsByStatus,
                'products_count' => $productsCount,
                'active_products_count' => $activeProductsCount,
            ]
        ]);
    }

    // =============================================
    // TENANT-SPECIFIC CONFIGURATION METHODS
    // =============================================

    /**
     * Get full configuration for a specific tenant (including API configs).
     */
    public function getConfig(Tenant $tenant): JsonResponse
    {
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
     */
    public function updateBranding(Request $request, Tenant $tenant): JsonResponse
    {
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
     * List API configurations for a specific tenant.
     */
    public function listApiConfigs(Tenant $tenant): JsonResponse
    {
        return response()->json([
            'data' => $tenant->apiConfigs->map->toApiArray(),
            'available_providers' => TenantApiConfig::PROVIDERS,
            'available_service_types' => TenantApiConfig::SERVICE_TYPES,
        ]);
    }

    /**
     * Create or update an API configuration for a specific tenant.
     */
    public function saveApiConfig(Request $request, Tenant $tenant): JsonResponse
    {
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
     * Delete an API configuration for a specific tenant.
     */
    public function deleteApiConfig(Tenant $tenant, TenantApiConfig $config): JsonResponse
    {
        if ($config->tenant_id !== $tenant->id) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $config->delete();

        return response()->json([
            'message' => 'Configuración eliminada'
        ]);
    }

    /**
     * Test an API configuration for a specific tenant.
     */
    public function testApiConfig(Tenant $tenant, TenantApiConfig $config): JsonResponse
    {
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
     * Upload a logo for a specific tenant.
     */
    public function uploadLogo(Request $request, Tenant $tenant): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:png,jpg,jpeg,svg,webp,ico|max:2048',
            'field' => 'required|string|in:logo_url,logo_dark_url,favicon_url,login_background_url',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $file = $request->file('file');
        $field = $request->input('field');

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
        // Use withoutGlobalScope to bypass tenant filtering when counting across tenants
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
