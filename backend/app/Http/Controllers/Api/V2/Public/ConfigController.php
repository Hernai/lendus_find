<?php

namespace App\Http\Controllers\Api\V2\Public;

use App\Enums\ApplicationStatus;
use App\Enums\BankAccountType;
use App\Enums\DocumentRejectionReason;
use App\Enums\DocumentType;
use App\Enums\EducationLevel;
use App\Enums\EmploymentType;
use App\Enums\Gender;
use App\Enums\HousingType;
use App\Enums\IdType;
use App\Enums\LoanPurpose;
use App\Enums\MaritalStatus;
use App\Enums\MexicanState;
use App\Enums\PaymentFrequency;
use App\Enums\ProductType;
use App\Enums\ReferenceType;
use App\Enums\RejectionReason;
use App\Enums\Relationship;
use App\Enums\UserType;
use App\Http\Controllers\Api\V2\Traits\ApiResponses;
use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/**
 * V2 Config Controller.
 *
 * Returns tenant configuration for the public-facing app.
 * All endpoints are under /api/v2/config
 */
class ConfigController extends Controller
{
    use ApiResponses;

    /**
     * Get tenant configuration.
     *
     * GET /v2/config
     *
     * El resultado se cachea 5 min por tenant (clave `v2:config:{id}`). La
     * config cambia raramente (productos, branding, integraciones) y la
     * invalidacion la hacen los `booted()` de Tenant, TenantBranding,
     * Product y TenantApiConfig al guardar.
     */
    public function index(): JsonResponse
    {
        $tenant = app('tenant');

        $payload = Cache::remember(
            self::cacheKey($tenant->id),
            300,
            fn () => $this->buildPayload($tenant)
        );

        return $this->success($payload);
    }

    /**
     * Clave de cache compartida con los hooks de invalidacion en los
     * modelos. Manten el formato sincronizado con el `forget()` en
     * Tenant::booted() / Product::booted() / TenantApiConfig::booted() /
     * TenantBranding::booted().
     */
    public static function cacheKey(string $tenantId): string
    {
        return "v2:config:{$tenantId}";
    }

    private function buildPayload($tenant): array
    {
        // Use tenant_branding table if available, fallback to legacy branding column
        $branding = $tenant->brandingConfig
            ? $tenant->brandingConfig->toApiArray()
            : $this->formatBranding($tenant->branding);

        // Detectar integraciones activas para que el frontend decida flows
        // condicionales (ej. saltar paso "personal_data" si hay proveedor KYC
        // que extrae los datos automáticamente del INE).
        $activeIntegrations = \App\Models\TenantApiConfig::where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->get(['provider', 'service_type'])
            ->map(fn ($c) => strtolower($c->service_type . ':' . $c->provider))
            ->values()
            ->all();

        $integrations = [
            'has_kyc_provider' => collect($activeIntegrations)
                ->contains(fn ($i) => str_starts_with($i, 'kyc:')),
            'has_phone_score' => collect($activeIntegrations)
                ->contains(fn ($i) => str_starts_with($i, 'phone_score:')),
            'has_disbursement' => collect($activeIntegrations)
                ->contains(fn ($i) => str_starts_with($i, 'disbursement:')),
            'active' => $activeIntegrations,
        ];

        return [
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'branding' => $branding,
                'webhook_config' => $tenant->webhook_config,
                'settings' => $this->formatSettings($tenant->settings),
                'features' => $tenant->features ?? [],
                'integrations' => $integrations,
                'contact' => [
                    'email' => $tenant->email,
                    'phone' => $tenant->phone,
                    'website' => $tenant->website,
                    'whatsapp' => $tenant->settings['whatsapp'] ?? $tenant->phone,
                ],
                'is_active' => $tenant->is_active,
                'created_at' => $tenant->created_at?->toIso8601String(),
                'updated_at' => $tenant->updated_at?->toIso8601String(),
            ],
            // Site key publica de reCAPTCHA v3 para que el frontend cargue
            // el script de Google y ejecute grecaptcha.execute(siteKey, ...)
            // antes de POST /staff/auth/login. Null = el backend no requiere
            // captcha (local/testing); el frontend salta la validacion.
            'recaptcha' => [
                'site_key' => config('services.recaptcha.site_key'),
            ],
            'products' => Product::active()->orderBy('display_order')->get()->map(fn($p) => [
                'id' => $p->id,
                'tenant_id' => $p->tenant_id,
                'name' => $p->name,
                'code' => $p->code,
                'type' => $p->type?->value ?? $p->type,
                'description' => $p->description,
                'icon' => $p->icon ?? $this->getDefaultIcon($p->type?->value ?? $p->type),
                'rules' => $this->formatRules($p),
                'required_docs' => $this->formatRequiredDocs($p->required_documents ?? $p->required_docs),
                'extra_fields' => $p->extra_fields ?? [],
                'eligibility_rules' => $p->eligibility_rules ?? [],
                'onboarding_steps' => $p->onboarding_steps,
                'late_fee_rate' => $p->late_fee_rate,
                'display_order' => $p->display_order,
                'is_active' => $p->is_active,
                'is_default' => $p->is_default,
            ])->all(),
            'options' => $this->getEnumOptions(),
        ];
    }

    /**
     * Get enum options for frontend selects.
     * Keys use camelCase to match JavaScript/TypeScript conventions.
     */
    private function getEnumOptions(): array
    {
        return [
            // Profile enums
            'gender' => Gender::toOptions(),
            'maritalStatus' => MaritalStatus::toOptions(),
            'educationLevel' => EducationLevel::toOptions(),
            'housingType' => HousingType::toOptions(),
            'employmentType' => EmploymentType::toOptions(),
            'salaryRange' => \App\Enums\SalaryRange::toOptions(),
            'bankAccountType' => BankAccountType::toOptions(),
            'mexicanState' => MexicanState::toOptions(),
            // Reference enums
            'referenceType' => ReferenceType::toOptions(),
            'relationship' => Relationship::toOptions(),
            'relationshipFamily' => Relationship::familyOptions(),
            'relationshipNonFamily' => Relationship::nonFamilyOptions(),
            // Document and ID enums
            'documentType' => DocumentType::toOptions(),
            'idType' => IdType::toOptions(),
            // Application enums
            'loanPurpose' => LoanPurpose::toOptions(),
            'paymentFrequency' => PaymentFrequency::toOptions(),
            'applicationStatus' => ApplicationStatus::toOptions(),
            // Product enums
            'productType' => ProductType::toOptions(),
            'leaseModality' => \App\Enums\LeaseModality::toOptions(),
            'assetType' => \App\Enums\AssetType::toOptions(),
            // Admin enums
            'userType' => UserType::toOptions(),
            'rejectionReason' => RejectionReason::toOptions(),
            'documentRejectionReason' => DocumentRejectionReason::toOptions(),
        ];
    }

    /**
     * Format branding with defaults (legacy support).
     */
    private function formatBranding(?array $branding): array
    {
        return [
            'primary_color' => $branding['primary_color'] ?? '#6366f1',
            'secondary_color' => $branding['secondary_color'] ?? '#10b981',
            'accent_color' => $branding['accent_color'] ?? '#f59e0b',
            'background_color' => $branding['background_color'] ?? '#ffffff',
            'text_color' => $branding['text_color'] ?? '#1f2937',
            'logo_url' => $branding['logo_url'] ?? null,
            'logo_dark_url' => $branding['logo_dark_url'] ?? null,
            'favicon_url' => $branding['favicon_url'] ?? null,
            'login_background_url' => $branding['login_background_url'] ?? null,
            'font_family' => $branding['font_family'] ?? 'Inter, sans-serif',
            'heading_font_family' => $branding['heading_font_family'] ?? null,
            'border_radius' => $branding['border_radius'] ?? '12px',
            'button_style' => $branding['button_style'] ?? 'rounded',
            'custom_css' => $branding['custom_css'] ?? null,
        ];
    }

    /**
     * Format settings with defaults.
     */
    private function formatSettings(?array $settings): array
    {
        return [
            'otp_provider' => $settings['otp_provider'] ?? 'twilio',
            'kyc_provider' => $settings['kyc_provider'] ?? null,
            'max_loan_amount' => $settings['max_loan_amount'] ?? 500000,
            'min_loan_amount' => $settings['min_loan_amount'] ?? 5000,
            'currency' => $settings['currency'] ?? 'MXN',
            'timezone' => $settings['timezone'] ?? 'America/Mexico_City',
        ];
    }

    /**
     * Get default icon for product type.
     */
    private function getDefaultIcon(string $type): string
    {
        return match ($type) {
            'PERSONAL' => 'user',
            'PAYROLL' => 'briefcase',
            'SME' => 'building',
            'LEASING' => 'truck',
            'FACTORING' => 'document',
            default => 'credit-card',
        };
    }

    /**
     * Format product rules for frontend (normalize field names).
     */
    private function formatRules(Product $product): array
    {
        $rules = $product->rules ?? [];

        return [
            'min_amount' => $product->min_amount,
            'max_amount' => $product->max_amount,
            'min_term_months' => $product->min_term_months,
            'max_term_months' => $product->max_term_months,
            'annual_rate' => $product->annual_rate,
            'opening_commission' => $product->opening_commission_rate,
            'amortization_type' => $rules['amortization_type'] ?? 'FRENCH',
            'payment_frequencies' => $product->payment_frequencies ?? $rules['payment_frequencies'] ?? ['MONTHLY'],
            'term_config' => $rules['term_config'] ?? null,
            'min_age' => $rules['min_age'] ?? 18,
            'max_age' => $rules['max_age'] ?? 75,
            'min_income' => $rules['min_income'] ?? 8000,
            // Config de arrendamiento (asset_types, modalidad, anticipos, IVA…). Solo
            // existe en productos ARRENDAMIENTO; el frontend (AssetTypeStep, simulador)
            // la lee de `rules.lease`. Sin este passthrough el selector de bien sale vacío.
            'lease' => $rules['lease'] ?? null,
        ];
    }

    /**
     * Format required docs for frontend.
     * Uses DocumentType enum for descriptions to stay in sync with backend.
     * Supports both legacy array format and new {nationals: [], foreigners: []} structure.
     */
    private function formatRequiredDocs(?array $docs): array|object
    {
        if (!$docs) {
            return [];
        }

        // Check if using new structure {nationals: [], foreigners: []}
        if (isset($docs['nationals']) || isset($docs['foreigners'])) {
            // New structure: return as object with formatted arrays for each type
            $result = [];

            if (isset($docs['nationals'])) {
                $result['nationals'] = $this->formatDocArray($docs['nationals']);
            }

            if (isset($docs['foreigners'])) {
                $result['foreigners'] = $this->formatDocArray($docs['foreigners']);
            }

            return $result;
        }

        // Legacy format: flat array
        return $this->formatDocArray($docs);
    }

    /**
     * Format an array of document types with descriptions.
     *
     * Acepta dos formatos en BD:
     *   - Legacy: array de strings, ej. ['INE_FRONT', 'INE_BACK', ...]
     *   - Actual: array de objetos {type, required, description}, como lo
     *     guarda DemoDataSeeder y los seeders de tenants reales.
     *
     * Normaliza ambos al shape unificado que el frontend espera:
     *   [{type: 'INE_FRONT', required: true, description: 'INE (Frente)'}]
     */
    private function formatDocArray(array $docs): array
    {
        return array_map(function ($doc) {
            // Si ya viene como objeto/array, extraemos el type y lo
            // re-formateamos para garantizar el shape esperado.
            if (is_array($doc)) {
                $type = $doc['type'] ?? null;
                $required = $doc['required'] ?? true;
                $description = $doc['description'] ?? $type;
            } else {
                $type = $doc;
                $required = true;
                $description = $doc;
            }

            // Enriquecemos description desde el enum si esta disponible.
            try {
                $docType = \App\Enums\DocumentType::tryFrom($type);
                if ($docType) {
                    $description = $docType->description();
                }
            } catch (\Throwable) {
                // Fallback: usamos la description ya calculada arriba.
            }

            return [
                'type' => $type,
                'required' => $required,
                'description' => $description,
            ];
        }, $docs);
    }
}
