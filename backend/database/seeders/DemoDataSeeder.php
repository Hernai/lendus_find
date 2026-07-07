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
 * Seeder del tenant demo (Lendus Demo).
 *
 * Crea:
 *  - Tenant `demo` con branding azul
 *  - TenantBranding con colores y PWA settings
 *  - 3 productos: Crédito Personal, Crédito Nómina, Arrendamiento
 *  - Staff: super admin, admin, 2 supervisores, 2 analistas
 *
 * Idempotente — usa updateOrCreate y verifica existencia antes de crear staff.
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = $this->createTenant();
        $this->createBranding($tenant);
        $this->createProducts($tenant);
        $this->createStaff($tenant);

        $this->command->info('✓ Tenant demo seedeado (slug=demo)');
        $this->command->info('  Admin: admin@lendus.mx');
        $this->command->info('  Supervisor: carlos.ramirez@lendus.mx');
        $this->command->info('  Analista: patricia.moreno@lendus.mx');
        $this->command->info('  (password: password)');
    }

    private function createTenant(): Tenant
    {
        return Tenant::updateOrCreate(
            ['slug' => 'demo'],
            [
                'id' => Tenant::where('slug', 'demo')->value('id') ?? Str::uuid(),
                'name' => 'Lendus Demo',
                // Dominio público en producción. IdentifyTenant matchea el
                // host exacto del request contra este campo antes que el slug.
                'domain' => 'demo.lendus.app',
                'legal_name' => 'Lendus Financiera S.A. de C.V. SOFOM E.N.R.',
                'rfc' => 'LFI180101ABC',
                'branding' => [
                    'primary_color' => '#2563EB',
                    'secondary_color' => '#1E40AF',
                    'accent_color' => '#F59E0B',
                    'logo_url' => null,
                    'favicon_url' => null,
                    'font_family' => 'Inter, sans-serif',
                    'border_radius' => '12px',
                ],
                'settings' => [
                    'otp_provider' => 'twilio',
                    'kyc_provider' => 'mati',
                    'currency' => 'MXN',
                    'timezone' => 'America/Mexico_City',
                    'min_loan_amount' => 5000,
                    'max_loan_amount' => 500000,
                ],
                'features' => null,
                'email' => 'contacto@demo.lendus.app',
                'phone' => '5555555555',
                'website' => 'https://demo.lendus.app',
                'is_active' => true,
                'activated_at' => now(),
            ],
        );
    }

    private function createBranding(Tenant $tenant): void
    {
        TenantBranding::seedFor(
            $tenant->id,
            [
                'id' => TenantBranding::where('tenant_id', $tenant->id)->value('id') ?? Str::uuid(),
                'primary_color' => '#2563EB',
                'secondary_color' => '#1E40AF',
                'accent_color' => '#F59E0B',
                'background_color' => '#FFFFFF',
                'text_color' => '#1F2937',
                'font_family' => 'Inter, sans-serif',
                'border_radius' => '12px',
                'button_style' => 'rounded',
                'pwa_name' => 'Lendus Demo',
                'pwa_short_name' => 'Demo',
                'pwa_theme_color' => '#2563EB',
                'pwa_background_color' => '#FFFFFF',
            ],
        );
    }

    private function createProducts(Tenant $tenant): void
    {
        $products = [
            [
                'code' => 'PERS-001',
                'name' => 'Crédito Personal',
                'type' => 'PERSONAL',
                'description' => 'Crédito personal para cualquier necesidad',
                'icon' => 'user',
                'min_amount' => 5000,
                'max_amount' => 150000,
                'min_term_months' => 3,
                'max_term_months' => 36,
                'interest_rate' => 36.0,
                'opening_commission' => 3.0,
                'late_fee_rate' => 5.0,
                'payment_frequencies' => ['WEEKLY', 'BIWEEKLY', 'MONTHLY'],
                'display_order' => 1,
            ],
            [
                'code' => 'NOMI-001',
                'name' => 'Crédito Nómina',
                'type' => 'NOMINA',
                'description' => 'Crédito con descuento vía nómina',
                'icon' => 'briefcase',
                'min_amount' => 10000,
                'max_amount' => 300000,
                'min_term_months' => 6,
                'max_term_months' => 48,
                'interest_rate' => 24.0,
                'opening_commission' => 2.0,
                'late_fee_rate' => 3.0,
                'payment_frequencies' => ['BIWEEKLY', 'MONTHLY'],
                'display_order' => 2,
            ],
            [
                'code' => 'ARRE-PURO-001',
                'name' => 'Arrendamiento Puro',
                'type' => 'ARRENDAMIENTO',
                'description' => 'Arrendamiento puro de paneles solares, vehículos y maquinaria (sin opción de compra).',
                'icon' => 'key',
                'min_amount' => 50000,
                'max_amount' => 1000000,
                'min_term_months' => 12,
                'max_term_months' => 60,
                'interest_rate' => 18.0,
                'opening_commission' => 2.5,
                'late_fee_rate' => 4.0,
                'payment_frequencies' => ['MONTHLY'],
                'display_order' => 3,
                // Config de arrendamiento (va a rules.lease); el admin la edita.
                'lease' => [
                    'modality' => 'PURO',
                    'asset_types' => ['SOLAR_PANELS', 'VEHICLE', 'MACHINERY'],
                    'purchase_option' => false,
                    'residual_value_pct' => null,
                    // Config financiera del arrendamiento (la lee LeaseCalculationService).
                    'anticipo_pct_default' => 10,
                    'anticipo_pct_min' => 0,
                    'anticipo_pct_max' => 30,
                    'pago_anticipado' => true,
                    'numero_rentas_anticipadas' => 1,
                    'deposito_garantia_meses' => 1,
                    'comision_apertura_pct' => 1.5,
                    'iva_pct' => 16,
                ],
                'onboarding_steps' => $this->leaseOnboardingSteps(),
            ],
            [
                'code' => 'ARRE-FIN-001',
                'name' => 'Arrendamiento Financiero',
                'type' => 'ARRENDAMIENTO',
                'description' => 'Arrendamiento financiero con opción de compra al final del plazo.',
                'icon' => 'key',
                'min_amount' => 50000,
                'max_amount' => 2000000,
                'min_term_months' => 12,
                'max_term_months' => 60,
                'interest_rate' => 16.0,
                'opening_commission' => 2.0,
                'late_fee_rate' => 4.0,
                'payment_frequencies' => ['MONTHLY'],
                'display_order' => 4,
                'lease' => [
                    'modality' => 'FINANCIERO',
                    'asset_types' => ['SOLAR_PANELS', 'VEHICLE', 'MACHINERY'],
                    'purchase_option' => true,
                    'residual_value_pct' => 15,
                    // Config financiera del arrendamiento (la lee LeaseCalculationService).
                    'anticipo_pct_default' => 20,
                    'anticipo_pct_min' => 0,
                    'anticipo_pct_max' => 30,
                    'pago_anticipado' => true,
                    'numero_rentas_anticipadas' => 1,
                    'deposito_garantia_meses' => 1,
                    'comision_apertura_pct' => 1.5,
                    'iva_pct' => 16,
                ],
                'onboarding_steps' => $this->leaseOnboardingSteps(),
            ],
        ];

        foreach ($products as $p) {
            // `lease` y `onboarding_steps` no son columnas planas: se extraen para
            // no pasarlas como atributos sueltos al modelo. `lease` se anida en
            // `rules.lease`; `onboarding_steps` es su propia columna JSONB.
            $lease = $p['lease'] ?? null;
            $steps = $p['onboarding_steps'] ?? null;
            unset($p['lease'], $p['onboarding_steps']);

            $rules = [
                'min_amount' => $p['min_amount'],
                'max_amount' => $p['max_amount'],
                'min_term_months' => $p['min_term_months'],
                'max_term_months' => $p['max_term_months'],
                'default_term_months' => $p['min_term_months'],
                'annual_rate' => $p['interest_rate'],
                'opening_commission' => $p['opening_commission'],
                'amortization_type' => 'FRENCH',
                'payment_frequencies' => $p['payment_frequencies'],
            ];
            if ($lease !== null) {
                $rules['lease'] = $lease;
            }

            Product::updateOrCreate(
                ['tenant_id' => $tenant->id, 'code' => $p['code']],
                array_merge($p, [
                    'id' => Product::where('tenant_id', $tenant->id)->where('code', $p['code'])->value('id') ?? Str::uuid(),
                    'tenant_id' => $tenant->id,
                    'rules' => $rules,
                    'required_documents' => [
                        'nationals' => [
                            ['type' => 'INE_FRONT', 'required' => true, 'description' => 'INE (Frente)'],
                            ['type' => 'INE_BACK', 'required' => true, 'description' => 'INE (Reverso)'],
                            ['type' => 'PROOF_OF_ADDRESS', 'required' => true, 'description' => 'Comprobante de domicilio'],
                            ['type' => 'PROOF_OF_INCOME', 'required' => true, 'description' => 'Comprobante de ingresos'],
                            ['type' => 'SELFIE', 'required' => true, 'description' => 'Selfie de validación facial'],
                        ],
                        'foreigners' => [
                            ['type' => 'PASSPORT', 'required' => true, 'description' => 'Pasaporte'],
                            ['type' => 'RESIDENCE_CARD', 'required' => true, 'description' => 'Tarjeta de Residente'],
                            ['type' => 'PROOF_OF_ADDRESS', 'required' => true, 'description' => 'Comprobante de domicilio'],
                            ['type' => 'PROOF_OF_INCOME', 'required' => true, 'description' => 'Comprobante de ingresos'],
                            ['type' => 'SELFIE', 'required' => true, 'description' => 'Selfie de validación facial'],
                        ],
                    ],
                    'extra_fields' => [],
                    'eligibility_rules' => ['min_age' => 18, 'max_age' => 75, 'requires_mexican_id' => true],
                    'onboarding_steps' => $steps,
                    'is_active' => true,
                ]),
            );
        }

        // El viejo producto genérico 'ARRE-001' se reemplazó por Puro + Financiero.
        // Si quedó sembrado de antes, lo desactivamos (no se borra por FK con solicitudes).
        Product::where('tenant_id', $tenant->id)
            ->where('code', 'ARRE-001')
            ->update(['is_active' => false]);
    }

    /**
     * Pipeline de onboarding para los productos de arrendamiento (demo).
     * Ramifica persona física / empresa vía `condition` (if_individual / if_company).
     * Los tipos custom (applicant_type_select, asset_type, company_data,
     * company_docs) los renderiza el registro del tenant `demo`
     * (frontend/tenants/demo/onboarding.register.ts); el resto son pasos base.
     */
    private function leaseOnboardingSteps(): array
    {
        return [
            ['id' => 'applicant_type', 'type' => 'applicant_type_select', 'label' => 'Tipo de solicitante', 'required' => true],
            // NOTA: el bien a arrendar (tipo + marca/modelo/año + valor) se captura
            // en el SIMULADOR, no aquí; se vuelca a metadata.lease al crear la
            // solicitud (ver application store persistLeaseMetadata). Por eso no hay
            // paso `asset_type` en el onboarding.
            // Rama EMPRESA (persona moral)
            ['id' => 'company', 'type' => 'company_data', 'label' => 'Datos de la empresa', 'required' => true, 'condition' => 'if_company'],
            ['id' => 'company_docs', 'type' => 'company_docs', 'label' => 'Documentos de la empresa', 'required' => true, 'condition' => 'if_company'],
            // Rama PERSONA FÍSICA
            ['id' => 'employment', 'type' => 'select', 'field' => 'employment_type', 'enum' => 'EmploymentType', 'label' => 'Ocupación', 'required' => true, 'condition' => 'if_individual'],
            ['id' => 'salary_range', 'type' => 'select', 'field' => 'salary_range', 'enum' => 'SalaryRange', 'label' => 'Rango de ingresos', 'required' => true, 'condition' => 'if_individual'],
            // Común a ambos
            ['id' => 'location', 'type' => 'state_city', 'fields' => ['state', 'city'], 'label' => 'Estado y ciudad', 'required' => true],
            ['id' => 'address', 'type' => 'address', 'label' => 'Domicilio', 'required' => true],
            ['id' => 'references', 'type' => 'references', 'min' => 2, 'max' => 2, 'label' => 'Referencias', 'required' => true],
            ['id' => 'bank_account', 'type' => 'bank_account', 'label' => 'Cuenta bancaria', 'required' => true],
            ['id' => 'kyc_ine', 'type' => 'kyc_ine', 'label' => 'Validación de identidad', 'required' => true],
            ['id' => 'kyc_face', 'type' => 'kyc_selfie', 'label' => 'Validación facial', 'required' => true],
            ['id' => 'review', 'type' => 'review_full', 'label' => 'Revisión final'],
        ];
    }

    private function createStaff(Tenant $tenant): void
    {
        // Nota: el SUPER_ADMIN global (sin tenant_id) lo crea
        // GlobalSuperAdminSeeder. Aquí solo creamos staff per-tenant.
        $users = [
            [
                'email' => 'admin@lendus.mx',
                'role' => StaffAccount::ROLE_ADMIN,
                'profile' => ['first_name' => 'Admin', 'last_name' => 'Demo', 'phone' => '5500000001', 'title' => 'Administrador'],
            ],
            [
                'email' => 'carlos.ramirez@lendus.mx',
                'role' => StaffAccount::ROLE_SUPERVISOR,
                'profile' => ['first_name' => 'Carlos', 'last_name' => 'Ramírez', 'phone' => '5500000002', 'title' => 'Supervisor de Crédito'],
            ],
            [
                'email' => 'maria.lopez@lendus.mx',
                'role' => StaffAccount::ROLE_SUPERVISOR,
                'profile' => ['first_name' => 'María', 'last_name' => 'López', 'phone' => '5500000003', 'title' => 'Supervisor de Crédito'],
            ],
            [
                'email' => 'patricia.moreno@lendus.mx',
                'role' => StaffAccount::ROLE_ANALYST,
                'profile' => ['first_name' => 'Patricia', 'last_name' => 'Moreno', 'phone' => '5500000101', 'title' => 'Analista de Crédito'],
            ],
            [
                'email' => 'fernando.diaz@lendus.mx',
                'role' => StaffAccount::ROLE_ANALYST,
                'profile' => ['first_name' => 'Fernando', 'last_name' => 'Díaz', 'phone' => '5500000102', 'title' => 'Analista de Crédito'],
            ],
        ];

        foreach ($users as $u) {
            // El email es UNIQUE global en staff_accounts. Buscamos sin
            // global scope para detectar la fila aunque esté asociada a
            // otro tenant (caso típico: seed reproducido tras renombrar
            // o re-crear el tenant). Si ya existe, NO la creamos de nuevo
            // y reasignamos su tenant_id al actual si difiere.
            $existing = StaffAccount::withoutGlobalScope('tenant')
                ->where('email', $u['email'])
                ->first();
            if ($existing) {
                if ($existing->tenant_id !== $tenant->id) {
                    \DB::table('staff_accounts')
                        ->where('id', $existing->id)
                        ->update(['tenant_id' => $tenant->id, 'updated_at' => now()]);
                }
                continue;
            }

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
}
