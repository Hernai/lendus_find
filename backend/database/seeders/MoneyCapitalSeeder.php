<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Tenant;
use App\Models\TenantBranding;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeder de tenant MoneyCapital México.
 *
 * Crea:
 *  - Tenant `moneycapital` con branding morado y features habilitadas
 *    (loan_portfolio, unified_consent_screen, phone_score_enabled)
 *  - TenantBranding con colores y placeholders PWA
 *  - Producto "Préstamo Sin Buró" con onboarding_steps minimalista y
 *    plazo en días ($300 - $15,000)
 *
 * Idempotente — se puede correr varias veces sin duplicar.
 */
class MoneyCapitalSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = $this->createTenant();
        $this->createBranding($tenant);
        $this->createProduct($tenant);

        $this->command->info("✓ Tenant MoneyCapital seedeado (slug={$tenant->slug})");
    }

    private function createTenant(): Tenant
    {
        return Tenant::updateOrCreate(
            ['slug' => 'moneycapital'],
            [
                'id' => Tenant::where('slug', 'moneycapital')->value('id') ?? Str::uuid(),
                'name' => 'MoneyCapital',
                'legal_name' => 'MoneyCapital México S.A. de C.V. SOFOM E.N.R.',
                'rfc' => 'MMX260101AAA',
                'branding' => [
                    'primary_color' => '#5B21B6',
                    'secondary_color' => '#4C1D95',
                ],
                'settings' => [
                    'otp_provider' => 'nubarium',
                    'kyc_provider' => 'nubarium',
                    'currency' => 'MXN',
                    'timezone' => 'America/Mexico_City',
                    'min_loan_amount' => 300,
                    'max_loan_amount' => 15000,
                    'support_hours' => [
                        'monday_friday' => '8:30 a.m. a 6:00 p.m.',
                        'saturday' => '8:30 a.m. a 2:00 p.m.',
                        'sunday' => 'cerrado',
                    ],
                ],
                'features' => [
                    'loan_portfolio' => true,
                    'unified_consent_screen' => true,
                    'phone_score_enabled' => true,
                    'auto_disbursement' => true,
                ],
                'email' => 'contacto@moneycapital.mx',
                'phone' => '5555550000',
                'website' => 'https://moneycapital.mx',
                'is_active' => true,
                'activated_at' => now(),
            ],
        );
    }

    private function createBranding(Tenant $tenant): void
    {
        TenantBranding::updateOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'id' => TenantBranding::where('tenant_id', $tenant->id)->value('id') ?? Str::uuid(),
                'primary_color' => '#5B21B6',
                'secondary_color' => '#4C1D95',
                'accent_color' => '#A78BFA',
                'background_color' => '#FFFFFF',
                'text_color' => '#1F2937',
                'font_family' => 'Inter, sans-serif',
                'border_radius' => '12px',
                'button_style' => 'rounded',
                'pwa_name' => 'MoneyCapital',
                'pwa_short_name' => 'MoneyCapital',
                'pwa_theme_color' => '#5B21B6',
                'pwa_background_color' => '#FFFFFF',
            ],
        );
    }

    private function createProduct(Tenant $tenant): void
    {
        Product::updateOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'MC-SIN-BURO'],
            [
                'id' => Product::where('tenant_id', $tenant->id)
                    ->where('code', 'MC-SIN-BURO')
                    ->value('id') ?? Str::uuid(),
                'name' => 'Préstamo Sin Buró',
                'type' => 'PERSONAL',
                'description' => 'Préstamo personal de hasta $15,000 sin consultar buró tradicional. Evaluamos tu perfil con tecnología y validación digital.',
                'icon' => 'user',
                'min_amount' => 300,
                'max_amount' => 15000,
                // Plazo en días para MoneyCapital — usamos min_term_months/max_term_months
                // como meses pero con valores muy bajos para representar días/meses fraccionados.
                // El frontend específico de MoneyCapital interpreta el plazo en días vía product.rules.
                'min_term_months' => 1,
                'max_term_months' => 1,
                'interest_rate' => 36,
                'opening_commission' => 13,
                'late_fee_rate' => 5,
                'rules' => [
                    'min_amount' => 300,
                    'max_amount' => 15000,
                    'min_term_days' => 7,
                    'max_term_days' => 30,
                    'default_term_days' => 10,
                    'annual_rate' => 36,
                    'opening_commission' => 13,
                    'amortization_type' => 'BULLET',
                    'payment_frequencies' => ['MONTHLY'],
                    'term_in_days' => true,
                ],
                'required_documents' => [
                    'nationals' => [
                        ['type' => 'INE_FRONT', 'required' => true, 'description' => 'INE (Frente)'],
                        ['type' => 'INE_BACK', 'required' => true, 'description' => 'INE (Reverso)'],
                        ['type' => 'SELFIE', 'required' => true, 'description' => 'Selfie de validación facial'],
                    ],
                    'foreigners' => [
                        ['type' => 'PASSPORT', 'required' => true, 'description' => 'Pasaporte'],
                        ['type' => 'RESIDENCE_CARD', 'required' => true, 'description' => 'Tarjeta de Residente'],
                        ['type' => 'SELFIE', 'required' => true, 'description' => 'Selfie de validación facial'],
                    ],
                ],
                'extra_fields' => [],
                'eligibility_rules' => [
                    'min_age' => 18,
                    'max_age' => 75,
                    'requires_mexican_id' => true,
                ],
                'onboarding_steps' => $this->onboardingSteps(),
                'is_active' => true,
                'display_order' => 1,
            ],
        );
    }

    /**
     * Pipeline de onboarding MoneyCapital — 12 pasos según el PDF.
     * Cada paso es renderizado por OnboardingStepRenderer en el frontend.
     */
    private function onboardingSteps(): array
    {
        return [
            ['id' => 'education', 'type' => 'select', 'field' => 'education_level', 'enum' => 'EducationLevel', 'label' => 'Nivel educativo', 'required' => true],
            ['id' => 'marital', 'type' => 'select', 'field' => 'marital_status', 'enum' => 'MaritalStatus', 'label' => 'Estado civil', 'required' => true],
            ['id' => 'location', 'type' => 'state_city', 'fields' => ['state', 'city'], 'label' => 'Estado y ciudad actual', 'required' => true],
            ['id' => 'employment', 'type' => 'select', 'field' => 'employment_type', 'enum' => 'EmploymentType', 'label' => 'Tipo de actividad o trabajo', 'required' => true],
            ['id' => 'salary_range', 'type' => 'select', 'field' => 'salary_range', 'enum' => 'SalaryRange', 'label' => 'Rango salarial mensual', 'required' => true],
            ['id' => 'review_personal', 'type' => 'review', 'sections' => ['personal'], 'label' => 'Revisión de información personal'],
            ['id' => 'references', 'type' => 'references', 'min' => 2, 'max' => 2, 'label' => 'Referencias'],
            ['id' => 'credit_history', 'type' => 'number_select', 'field' => 'online_loans_count', 'options' => [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10], 'label' => '¿Cuántas veces has solicitado préstamos en línea?', 'required' => true],
            ['id' => 'bank_account', 'type' => 'bank_account', 'label' => 'Cuenta bancaria', 'required' => true],
            ['id' => 'kyc_ine', 'type' => 'kyc_ine', 'label' => 'Validación de INE', 'required' => true],
            ['id' => 'kyc_face', 'type' => 'kyc_selfie', 'label' => 'Validación facial', 'required' => true],
            ['id' => 'review_full', 'type' => 'review_full', 'label' => 'Revisión final'],
        ];
    }
}
