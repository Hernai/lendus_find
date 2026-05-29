<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\StaffAccount;
use App\Models\StaffProfile;
use App\Models\Tenant;
use App\Models\TenantBranding;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
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
        $this->createStaff($tenant);

        $this->command->info("✓ Tenant MoneyCapital seedeado (slug={$tenant->slug})");
    }

    /**
     * Crea staff accounts (super admin, admin, supervisor, analyst) para que
     * MoneyCapital tenga panel admin operable. Idempotente.
     */
    private function createStaff(Tenant $tenant): void
    {
        $users = [
            [
                'email' => 'superadmin@moneycapital.mx',
                'role' => StaffAccount::ROLE_SUPER_ADMIN,
                'profile' => ['first_name' => 'Super', 'last_name' => 'Admin', 'phone' => '5500001000', 'title' => 'Super Administrador'],
            ],
            [
                'email' => 'admin@moneycapital.mx',
                'role' => StaffAccount::ROLE_ADMIN,
                'profile' => ['first_name' => 'Admin', 'last_name' => 'MoneyCapital', 'phone' => '5500001001', 'title' => 'Administrador'],
            ],
            [
                'email' => 'supervisor@moneycapital.mx',
                'role' => StaffAccount::ROLE_SUPERVISOR,
                'profile' => ['first_name' => 'Supervisor', 'last_name' => 'MoneyCapital', 'phone' => '5500001002', 'title' => 'Supervisor de Credito'],
            ],
            [
                'email' => 'analista@moneycapital.mx',
                'role' => StaffAccount::ROLE_ANALYST,
                'profile' => ['first_name' => 'Analista', 'last_name' => 'MoneyCapital', 'phone' => '5500001003', 'title' => 'Analista de Credito'],
            ],
        ];

        foreach ($users as $u) {
            $existing = StaffAccount::where('email', $u['email'])
                ->where('tenant_id', $tenant->id)
                ->first();
            if ($existing) continue;

            $account = StaffAccount::create([
                'tenant_id' => $tenant->id,
                'email' => $u['email'],
                'password' => Hash::make('password'),
                'role' => $u['role'],
                'is_active' => true,
                'email_verified_at' => now(),
            ]);

            StaffProfile::create(array_merge(['account_id' => $account->id], $u['profile']));
        }
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
                    'primary_color' => '#371F91',
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
                    'unified_auth_screen' => true,
                    // primary morado extraído del mock oficial: #371F91
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
                'primary_color' => '#371F91',
                'secondary_color' => '#4C1D95',
                'accent_color' => '#A78BFA',
                'background_color' => '#FFFFFF',
                'text_color' => '#1F2937',
                'font_family' => 'Inter, sans-serif',
                'border_radius' => '12px',
                'button_style' => 'rounded',
                'pwa_name' => 'MoneyCapital',
                'pwa_short_name' => 'MoneyCapital',
                'pwa_theme_color' => '#371F91',
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
                // En la columna directa marcamos SINGLE para reflejar "pago único".
                'payment_frequencies' => ['SINGLE'],
                'rules' => [
                    'min_amount' => 300,
                    'max_amount' => 15000,
                    // Monto pre-asignado para la primera solicitud de un cliente nuevo
                    // (se sobrescribe en renovaciones según historial).
                    'default_amount' => 1000,
                    'min_term_days' => 1,
                    'max_term_days' => 30,
                    'default_term_days' => 10,
                    'annual_rate' => 36,
                    'opening_commission' => 13,
                    'amortization_type' => 'BULLET',
                    'payment_frequencies' => ['SINGLE'],
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
            // Si NO hay proveedor KYC (Nubarium) activo, el step `kyc_ine` también
            // pide los datos del INE embebidos: nombre, apellidos, fecha de
            // nacimiento y CURP. Si hay OCR activo, solo pide las imágenes.
            ['id' => 'kyc_ine', 'type' => 'kyc_ine', 'label' => 'Validación de INE', 'required' => true],
            // Datos que NO aparecen en el INE: género, nacionalidad, estado de
            // nacimiento, RFC. Solo si no hay proveedor KYC que los extraiga/derive.
            ['id' => 'personal_data', 'type' => 'personal_data', 'label' => 'Datos personales', 'required' => true, 'condition' => 'unless_kyc_provider'],
            // Domicilio queda en step separado porque tiene CP, estado, municipio, etc.
            ['id' => 'address', 'type' => 'address', 'label' => 'Domicilio', 'required' => true, 'condition' => 'unless_kyc_provider'],
            ['id' => 'kyc_face', 'type' => 'kyc_selfie', 'label' => 'Validación facial', 'required' => true],
            ['id' => 'review_full', 'type' => 'review_full', 'label' => 'Revisión final'],
        ];
    }
}
