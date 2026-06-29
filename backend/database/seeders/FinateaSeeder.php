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
 * Seeder de tenant Finatea.
 *
 * Crea:
 *  - Tenant `finatea` con branding rojo (#B91C1C)
 *  - TenantBranding con colores y PWA settings
 *  - Producto base de crédito personal con onboarding estándar
 *  - Staff accounts (super admin, admin, supervisor, analyst)
 *
 * Idempotente — se puede correr varias veces sin duplicar.
 */
class FinateaSeeder extends Seeder
{
    /**
     * Catálogo oficial de Finatea. Productos del tenant con code fuera de
     * esta lista (huérfanos de seeds viejos) se desactivan — no se borran
     * por si tienen applications históricas.
     */
    private const OFFICIAL_PRODUCT_CODES = ['FIN-PERSONAL'];

    public function run(): void
    {
        $tenant = $this->createTenant();
        $this->createBranding($tenant);
        $this->createProduct($tenant);
        $this->deactivateForeignProducts($tenant);
        $this->createStaff($tenant);

        $this->command->info("✓ Tenant Finatea seedeado (slug={$tenant->slug})");
    }

    /** Idempotente: la segunda corrida no encuentra nada que desactivar. */
    private function deactivateForeignProducts(Tenant $tenant): void
    {
        $deactivated = Product::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->whereNotIn('code', self::OFFICIAL_PRODUCT_CODES)
            ->where('is_active', true)
            ->update(['is_active' => false]);

        if ($deactivated > 0) {
            $this->command->warn("  ⚠ {$deactivated} producto(s) ajeno(s) al catálogo Finatea desactivado(s)");
        }
    }

    private function createTenant(): Tenant
    {
        return Tenant::updateOrCreate(
            ['slug' => 'finatea'],
            [
                'id' => Tenant::where('slug', 'finatea')->value('id') ?? Str::uuid(),
                'name' => 'Finatea',
                // Dominio público en producción. IdentifyTenant matchea el
                // host exacto del request contra este campo antes que el slug.
                'domain' => 'finatea.lendus.app',
                'legal_name' => 'Finatea S.A. de C.V. SOFOM E.N.R.',
                'rfc' => 'FIN260101AAA',
                'branding' => [
                    'primary_color' => '#B91C1C',
                    'secondary_color' => '#991B1B',
                ],
                'settings' => [
                    'otp_provider' => 'twilio',
                    'kyc_provider' => 'nubarium',
                    'currency' => 'MXN',
                    'timezone' => 'America/Mexico_City',
                    'min_loan_amount' => 5000,
                    'max_loan_amount' => 300000,
                ],
                'features' => [
                    'loan_portfolio' => false,
                    'unified_consent_screen' => false,
                    'unified_auth_screen' => false,
                    'phone_score_enabled' => false,
                    'auto_disbursement' => false,
                ],
                'email' => 'contacto@finatea.lendus.app',
                'phone' => '5555550100',
                'website' => 'https://finatea.lendus.app',
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
                'primary_color' => '#B91C1C',
                'secondary_color' => '#991B1B',
                'accent_color' => '#F59E0B',
                'background_color' => '#FFFFFF',
                'text_color' => '#1F2937',
                'font_family' => 'Inter, sans-serif',
                'border_radius' => '12px',
                'button_style' => 'rounded',
                'pwa_name' => 'Finatea',
                'pwa_short_name' => 'Finatea',
                'pwa_theme_color' => '#B91C1C',
                'pwa_background_color' => '#FFFFFF',
            ],
        );
    }

    private function createProduct(Tenant $tenant): void
    {
        Product::updateOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'FIN-PERSONAL'],
            [
                'id' => Product::where('tenant_id', $tenant->id)
                    ->where('code', 'FIN-PERSONAL')
                    ->value('id') ?? Str::uuid(),
                'name' => 'Crédito Personal Finatea',
                'type' => 'PERSONAL',
                'description' => 'Crédito personal hasta $300,000 con aprobación digital en minutos. Solicita por WhatsApp y firma 100% online.',
                'icon' => 'user',
                'min_amount' => 5000,
                'max_amount' => 300000,
                'min_term_months' => 3,
                'max_term_months' => 36,
                'interest_rate' => 28,
                'opening_commission' => 3,
                'late_fee_rate' => 5,
                'payment_frequencies' => ['MONTHLY', 'BIWEEKLY'],
                'rules' => [
                    'min_amount' => 5000,
                    'max_amount' => 300000,
                    'default_amount' => 30000,
                    'min_term_months' => 3,
                    'max_term_months' => 36,
                    'default_term_months' => 12,
                    'annual_rate' => 28,
                    'opening_commission' => 3,
                    'amortization_type' => 'FRENCH',
                    'payment_frequencies' => ['MONTHLY', 'BIWEEKLY'],
                ],
                'required_documents' => [
                    'nationals' => [
                        ['type' => 'INE_FRONT', 'required' => true, 'description' => 'INE (Frente)'],
                        ['type' => 'INE_BACK', 'required' => true, 'description' => 'INE (Reverso)'],
                        ['type' => 'SELFIE', 'required' => true, 'description' => 'Selfie de validación facial'],
                        ['type' => 'PROOF_OF_ADDRESS', 'required' => true, 'description' => 'Comprobante de domicilio'],
                        ['type' => 'PROOF_OF_INCOME', 'required' => true, 'description' => 'Comprobante de ingresos'],
                    ],
                    'foreigners' => [
                        ['type' => 'PASSPORT', 'required' => true, 'description' => 'Pasaporte'],
                        ['type' => 'RESIDENCE_CARD', 'required' => true, 'description' => 'Tarjeta de Residente'],
                        ['type' => 'SELFIE', 'required' => true, 'description' => 'Selfie de validación facial'],
                        ['type' => 'PROOF_OF_ADDRESS', 'required' => true, 'description' => 'Comprobante de domicilio'],
                        ['type' => 'PROOF_OF_INCOME', 'required' => true, 'description' => 'Comprobante de ingresos'],
                    ],
                ],
                'extra_fields' => [],
                'eligibility_rules' => [
                    'min_age' => 18,
                    'max_age' => 70,
                    'requires_mexican_id' => true,
                ],
                'onboarding_steps' => null,
                'is_active' => true,
                'display_order' => 1,
            ],
        );
    }

    private function createStaff(Tenant $tenant): void
    {
        // Nota: el SUPER_ADMIN es global (sin tenant) y se crea con
        // GlobalSuperAdminSeeder. Aquí solo staff per-tenant.
        $users = [
            [
                'email' => 'admin@finatea.mx',
                'role' => StaffAccount::ROLE_ADMIN,
                'profile' => ['first_name' => 'Admin', 'last_name' => 'Finatea', 'phone' => '5500002001', 'title' => 'Administrador'],
            ],
            [
                'email' => 'supervisor@finatea.mx',
                'role' => StaffAccount::ROLE_SUPERVISOR,
                'profile' => ['first_name' => 'Supervisor', 'last_name' => 'Finatea', 'phone' => '5500002002', 'title' => 'Supervisor de Crédito'],
            ],
            [
                'email' => 'analista@finatea.mx',
                'role' => StaffAccount::ROLE_ANALYST,
                'profile' => ['first_name' => 'Analista', 'last_name' => 'Finatea', 'phone' => '5500002003', 'title' => 'Analista de Crédito'],
            ],
        ];

        foreach ($users as $u) {
            // El email es UNIQUE global. Buscamos sin global scope para
            // detectar la fila aunque esté en otro tenant. Si existe, la
            // reasignamos a este tenant en vez de crear duplicado.
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
