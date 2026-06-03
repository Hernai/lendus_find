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
                'email' => 'contacto@lendus.mx',
                'phone' => '5555555555',
                'website' => 'https://lendus.mx',
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
                'code' => 'ARRE-001',
                'name' => 'Arrendamiento',
                'type' => 'ARRENDAMIENTO',
                'description' => 'Arrendamiento de vehículos y maquinaria',
                'icon' => 'truck',
                'min_amount' => 50000,
                'max_amount' => 1000000,
                'min_term_months' => 12,
                'max_term_months' => 60,
                'interest_rate' => 18.0,
                'opening_commission' => 2.5,
                'late_fee_rate' => 4.0,
                'payment_frequencies' => ['MONTHLY'],
                'display_order' => 3,
            ],
        ];

        foreach ($products as $p) {
            Product::updateOrCreate(
                ['tenant_id' => $tenant->id, 'code' => $p['code']],
                array_merge($p, [
                    'id' => Product::where('tenant_id', $tenant->id)->where('code', $p['code'])->value('id') ?? Str::uuid(),
                    'tenant_id' => $tenant->id,
                    'rules' => [
                        'min_amount' => $p['min_amount'],
                        'max_amount' => $p['max_amount'],
                        'min_term_months' => $p['min_term_months'],
                        'max_term_months' => $p['max_term_months'],
                        'default_term_months' => $p['min_term_months'],
                        'annual_rate' => $p['interest_rate'],
                        'opening_commission' => $p['opening_commission'],
                        'amortization_type' => 'FRENCH',
                        'payment_frequencies' => $p['payment_frequencies'],
                    ],
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
                    'onboarding_steps' => null,
                    'is_active' => true,
                ]),
            );
        }
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
}
