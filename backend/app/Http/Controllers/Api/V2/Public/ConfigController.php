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
     */
    public function index(): JsonResponse
    {
        $tenant = app('tenant');

        // Use tenant_branding table if available, fallback to legacy branding column
        $branding = $tenant->brandingConfig
            ? $tenant->brandingConfig->toApiArray()
            : $this->formatBranding($tenant->branding);

        return $this->success([
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'branding' => $branding,
                'webhook_config' => $tenant->webhook_config,
                'settings' => $this->formatSettings($tenant->settings),
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
                'late_fee_rate' => $p->late_fee_rate,
                'display_order' => $p->display_order,
                'is_active' => $p->is_active,
            ]),
            'options' => $this->getEnumOptions(),
        ]);
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
            'bankAccountType' => BankAccountType::toOptions(),
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
        ];
    }

    /**
     * Format required docs for frontend.
     * Uses DocumentType enum for descriptions to stay in sync with backend.
     */
    private function formatRequiredDocs(?array $docs): array
    {
        if (!$docs) {
            return [];
        }

        return array_map(function ($doc) {
            // Try to get description from DocumentType enum
            $description = $doc;
            try {
                $docType = \App\Enums\DocumentType::tryFrom($doc);
                if ($docType) {
                    $description = $docType->description();
                }
            } catch (\Throwable) {
                // Fallback to raw type if enum doesn't have this value
            }

            return [
                'type' => $doc,
                'required' => true,
                'description' => $description,
            ];
        }, $docs);
    }
}
