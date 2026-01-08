<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;

class ConfigController extends Controller
{
    /**
     * Get tenant configuration.
     */
    public function index(): JsonResponse
    {
        $tenant = app('tenant');

        return response()->json([
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'branding' => $this->formatBranding($tenant->branding),
                'webhook_config' => $tenant->webhook_config,
                'settings' => $this->formatSettings($tenant->settings),
                'is_active' => $tenant->is_active,
                'created_at' => $tenant->created_at?->toIso8601String(),
                'updated_at' => $tenant->updated_at?->toIso8601String(),
            ],
            'products' => Product::active()->orderBy('display_order')->get()->map(fn ($p) => [
                'id' => $p->id,
                'tenant_id' => $p->tenant_id,
                'name' => $p->name,
                'type' => $p->type?->value ?? $p->type,
                'description' => $p->description,
                'icon' => $p->icon ?? $this->getDefaultIcon($p->type?->value ?? $p->type),
                'rules' => $this->formatRules($p),
                'required_docs' => $this->formatRequiredDocs($p->required_docs),
                'extra_fields' => $p->extra_fields ?? [],
                'is_active' => $p->is_active,
            ]),
        ]);
    }

    /**
     * Format branding with defaults.
     */
    private function formatBranding(?array $branding): array
    {
        return [
            'primary_color' => $branding['primary_color'] ?? '#6366f1',
            'secondary_color' => $branding['secondary_color'] ?? '#10b981',
            'accent_color' => $branding['accent_color'] ?? '#f59e0b',
            'logo_url' => $branding['logo_url'] ?? '/logo.svg',
            'favicon_url' => $branding['favicon_url'] ?? '/favicon.ico',
            'font_family' => $branding['font_family'] ?? 'Inter, sans-serif',
            'border_radius' => $branding['border_radius'] ?? '12px',
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
            'payment_frequencies' => $rules['payment_frequencies'] ?? ['MONTHLY'],
            'min_age' => $rules['min_age'] ?? 18,
            'max_age' => $rules['max_age'] ?? 75,
            'min_income' => $rules['min_income'] ?? 8000,
        ];
    }

    /**
     * Format required docs for frontend.
     */
    private function formatRequiredDocs(?array $docs): array
    {
        if (!$docs) {
            return [];
        }

        $docDescriptions = [
            'INE_FRONT' => 'Identificación oficial (frente)',
            'INE_BACK' => 'Identificación oficial (reverso)',
            'PROOF_ADDRESS' => 'Comprobante de domicilio',
            'PROOF_INCOME' => 'Comprobante de ingresos',
            'PAYSLIP_1' => 'Recibo de nómina 1',
            'PAYSLIP_2' => 'Recibo de nómina 2',
            'PAYSLIP_3' => 'Recibo de nómina 3',
            'BANK_STATEMENTS' => 'Estados de cuenta bancarios',
            'VEHICLE_INVOICE' => 'Factura del vehículo',
            'RFC_CSF' => 'Constancia de Situación Fiscal',
        ];

        return array_map(fn ($doc) => [
            'type' => $doc,
            'required' => true,
            'description' => $docDescriptions[$doc] ?? $doc,
        ], $docs);
    }
}
