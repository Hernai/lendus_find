<?php

namespace Database\Seeders;

use App\Models\DecisionPolicy;
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
    /**
     * Catálogo oficial de productos de MoneyCapital. Cualquier producto del
     * tenant con un code fuera de esta lista se considera huérfano (típico:
     * productos del seed demo viejo que quedaron asignados a MC en
     * producción) y se DESACTIVA — no se borra, porque puede tener
     * applications históricas asociadas.
     */
    private const OFFICIAL_PRODUCT_CODES = ['MC-SIN-BURO'];

    public function run(): void
    {
        $tenant = $this->createTenant();
        $this->createBranding($tenant);
        $this->createProduct($tenant);
        $this->deactivateForeignProducts($tenant);
        $this->createStaff($tenant);
        $this->seedDecisionPolicies($tenant);

        $this->command->info("✓ Tenant MoneyCapital seedeado (slug={$tenant->slug})");
    }

    /**
     * Desactiva productos del tenant que no pertenecen a su catálogo oficial.
     * Idempotente: la segunda corrida no encuentra nada que desactivar.
     */
    private function deactivateForeignProducts(Tenant $tenant): void
    {
        $deactivated = Product::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->whereNotIn('code', self::OFFICIAL_PRODUCT_CODES)
            ->where('is_active', true)
            ->update(['is_active' => false]);

        if ($deactivated > 0) {
            $this->command->warn("  ⚠ {$deactivated} producto(s) ajeno(s) al catálogo MC desactivado(s)");
        }
    }

    /**
     * Crea staff accounts (super admin, admin, supervisor, analyst) para que
     * MoneyCapital tenga panel admin operable. Idempotente.
     */
    private function createStaff(Tenant $tenant): void
    {
        // Nota: el SUPER_ADMIN es global (sin tenant) y se crea con
        // GlobalSuperAdminSeeder. Aquí solo staff per-tenant.
        $users = [
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

    private function createTenant(): Tenant
    {
        return Tenant::updateOrCreate(
            ['slug' => 'moneycapital'],
            [
                'id' => Tenant::where('slug', 'moneycapital')->value('id') ?? Str::uuid(),
                'name' => 'MoneyCapital',
                // Dominio público en producción. IdentifyTenant matchea el
                // host exacto del request contra este campo antes que el slug.
                'domain' => 'moneycapital.lendus.app',
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
                'email' => 'contacto@moneycapital.lendus.app',
                'phone' => '5555550000',
                'website' => 'https://moneycapital.lendus.app',
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
                // Producto predeterminado: se usa cuando el cliente entra por
                // "Iniciar sesión" sin simular (el admin ajusta monto/días).
                'is_default' => true,
                'display_order' => 1,
            ],
        );
    }

    /**
     * Siembra las políticas v1 del motor de decisión (Matriz Maestra) en modo
     * SHADOW, SOLO si no existe ninguna política para ese alcance. Prod
     * re-siembra tenants en cada deploy: nunca sobrescribir ediciones del
     * admin (las políticas se versionan desde el configurador).
     */
    private function seedDecisionPolicies(Tenant $tenant): void
    {
        $hasTenantPolicy = DecisionPolicy::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->whereNull('product_id')
            ->exists();

        if (! $hasTenantPolicy) {
            DecisionPolicy::create([
                'tenant_id' => $tenant->id,
                'product_id' => null,
                'version' => 1,
                'mode' => 'SHADOW',
                'is_active' => true,
                'activated_at' => now(),
                'notes' => 'Política inicial de la Matriz Maestra (filtros de entrada)',
                'rules' => [
                    // Escala de referencia Nubarium: 0-400 permitir, 401-600
                    // alerta, 601+ bloquear. Fail-open: una falla técnica del
                    // proveedor nunca detiene al cliente (Regla 21).
                    'phone_risk_gate' => [
                        'enabled' => true,
                        'flag_from' => 401,
                        'block_from' => 601,
                        'fail_mode' => 'open',
                    ],
                    'cooldown' => ['days' => 30],
                ],
            ]);
        }

        $product = Product::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->where('code', 'MC-SIN-BURO')
            ->first();

        if (! $product) {
            return;
        }

        $hasProductPolicy = DecisionPolicy::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->where('product_id', $product->id)
            ->exists();

        if ($hasProductPolicy) {
            return;
        }

        DecisionPolicy::create([
            'tenant_id' => $tenant->id,
            'product_id' => $product->id,
            'version' => 1,
            'mode' => 'SHADOW',
            'is_active' => true,
            'activated_at' => now(),
            'notes' => 'Política inicial de la Matriz Maestra (piloto $300-$1,000 a 7 días)',
            'rules' => [
                'scoring' => [
                    'variables' => [
                        [
                            'key' => 'salary_range',
                            'label' => 'Rango salarial',
                            'points' => ['LT_3000' => 0, 'R_3001_6000' => 5, 'R_6001_9000' => 10, 'R_9001_12000' => 15, 'R_12001_15000' => 20, 'GT_15000' => 25],
                        ],
                        [
                            'key' => 'employment_type',
                            'label' => 'Actividad laboral',
                            'points' => ['EMPLOYEE' => 15, 'BUSINESS_OWNER' => 15, 'SELF_EMPLOYED' => 10, 'RETIRED' => 5, 'HOMEMAKER' => 5, 'STUDENT' => 0, 'UNEMPLOYED' => 0, 'OTHER' => 0],
                        ],
                        [
                            'key' => 'education_level',
                            'label' => 'Nivel educativo',
                            'points' => ['PRIMARY' => 0, 'SECONDARY' => 2, 'HIGH_SCHOOL' => 5, 'TECHNICAL' => 8, 'BACHELOR' => 10, 'MASTER' => 10, 'DOCTORATE' => 10],
                        ],
                        [
                            'key' => 'phone_risk_level',
                            'label' => 'Riesgo telefónico',
                            'points' => ['very-low' => 15, 'low' => 10, 'moderate' => 0, 'high' => 0],
                        ],
                        [
                            'key' => 'online_loans_count',
                            'label' => 'Créditos en línea declarados',
                            'points' => ['0' => 5, '1' => 10, '2' => 10, '3' => 5, '4' => 0, '5+' => 0],
                        ],
                    ],
                    // Piso de puntaje: un perfil por debajo del corte más bajo
                    // (declarativas muy flacas) NO recibe oferta automática —
                    // cae a revisión manual para que un analista decida. Los
                    // filtros de identidad (KYC/INE) y el gate telefónico ya
                    // gatean aprobación/rechazo; esto solo evita auto-ofertar el
                    // piso a perfiles en blanco durante la calibración del piloto.
                    'band_cutoffs' => [
                        ['min_score' => 15, 'band' => 'BASE'],
                        ['min_score' => 35, 'band' => 'INTERMEDIA'],
                        ['min_score' => 50, 'band' => 'CONTROLADA'],
                        ['min_score' => 65, 'band' => 'EXCEPCIONAL'],
                    ],
                ],
                // Bandas de la matriz: los target_share_pct son metas de
                // monitoreo, NUNCA mecanismo de asignación.
                'bands' => [
                    ['key' => 'BASE', 'min_amount' => 300, 'max_amount' => 400, 'target_share_pct' => 85],
                    ['key' => 'INTERMEDIA', 'min_amount' => 500, 'max_amount' => 600, 'target_share_pct' => 13],
                    ['key' => 'CONTROLADA', 'min_amount' => 700, 'max_amount' => 800, 'target_share_pct' => 5],
                    ['key' => 'EXCEPCIONAL', 'min_amount' => 900, 'max_amount' => 1000, 'target_share_pct' => 2],
                ],
                'first_credit' => ['term_days' => 7],
                'offer' => ['validity_hours' => 72, 'reminder_hours_before' => 24],
                'review' => ['input_timeout_minutes' => 30],
                'reject' => [
                    ['rule' => 'kyc_failed'],
                    ['rule' => 'identity_mismatch'],
                ],
                'graduation' => [
                    'levels' => [
                        ['level' => 0, 'max_amount' => 1000, 'max_term_days' => 7],
                        ['level' => 1, 'max_amount' => 2000, 'max_term_days' => 10],
                        ['level' => 2, 'max_amount' => 4000, 'max_term_days' => 15],
                        ['level' => 3, 'max_amount' => 8000, 'max_term_days' => 20],
                        ['level' => 4, 'max_amount' => 15000, 'max_term_days' => 30],
                    ],
                    'advance' => [
                        'on_time' => 1,
                        'late_or_extension' => 0,
                        'max_late_days_for_auto' => 5,
                    ],
                ],
            ],
        ]);

        $this->command->info('  ✓ Políticas de decisión v1 (SHADOW) sembradas');
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
            // SIEMPRE se captura: el INE/Nubarium NO aporta domicilio, así que no debe
            // filtrarse por `unless_kyc_provider` (con proveedor KYC quedaba fuera del
            // flujo y el domicilio llegaba vacío al admin).
            ['id' => 'address', 'type' => 'address', 'label' => 'Domicilio', 'required' => true],
            ['id' => 'kyc_face', 'type' => 'kyc_selfie', 'label' => 'Validación facial', 'required' => true],
            ['id' => 'review_full', 'type' => 'review_full', 'label' => 'Revisión final'],
        ];
    }
}
