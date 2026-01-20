<?php

namespace Database\Seeders;

use App\Enums\ApplicationStatus;
use App\Models\Address;
use App\Models\Applicant;
use App\Models\Application;
use App\Models\BankAccount;
use App\Models\Document;
use App\Models\EmploymentRecord;
use App\Models\Product;
use App\Models\Reference;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Seeder para el segundo tenant "lendusdemoii".
 * Genera usuarios, productos, solicitantes y solicitudes para pruebas de multi-tenancy.
 */
class SecondTenantSeeder extends Seeder
{
    public function run(): void
    {
        // Get the second tenant
        $tenant = Tenant::where('slug', 'lendusdemoii')->first();

        if (!$tenant) {
            $this->command->error('Tenant "lendusdemoii" not found. Please create it first.');
            return;
        }

        $this->command->info("Seeding data for tenant: {$tenant->name}");

        // 1. Create Staff Users
        $this->createStaffUsers($tenant);

        // 2. Create Products
        $this->createProducts($tenant);

        // 3. Create Applicants with Applications
        $this->createApplicantsAndApplications($tenant);

        $this->command->info('Second tenant seeding completed!');
    }

    private function createStaffUsers(Tenant $tenant): void
    {
        $this->command->info('Creating staff users...');

        // Super Admin for this tenant
        User::create([
            'name' => 'Super Admin II',
            'first_name' => 'Super',
            'last_name' => 'Admin II',
            'email' => 'superadmin@lendusdemoii.mx',
            'phone' => '5511110000',
            'password' => Hash::make('password'),
            'tenant_id' => $tenant->id,
            'type' => 'SUPER_ADMIN',
            'is_active' => true,
            'email_verified_at' => now(),
            'phone_verified_at' => now(),
        ]);

        // Admin
        User::create([
            'name' => 'Admin LendusII',
            'first_name' => 'Admin',
            'last_name' => 'LendusII',
            'email' => 'admin@lendusdemoii.mx',
            'phone' => '5511110001',
            'password' => Hash::make('password'),
            'tenant_id' => $tenant->id,
            'type' => 'ADMIN',
            'is_active' => true,
            'email_verified_at' => now(),
            'phone_verified_at' => now(),
        ]);

        // Supervisors
        $supervisors = [
            ['Roberto', 'Sánchez', 'García'],
            ['Laura', 'Martínez', 'López'],
        ];

        foreach ($supervisors as $index => $nameParts) {
            User::create([
                'name' => "{$nameParts[0]} {$nameParts[1]}",
                'first_name' => $nameParts[0],
                'last_name' => $nameParts[1],
                'email' => strtolower($nameParts[0]) . '.' . strtolower($nameParts[1]) . '@lendusdemoii.mx',
                'phone' => '551111100' . ($index + 2),
                'password' => Hash::make('password'),
                'tenant_id' => $tenant->id,
                'type' => 'SUPERVISOR',
                'is_active' => true,
                'email_verified_at' => now(),
                'phone_verified_at' => now(),
            ]);
        }

        // Analysts
        $analysts = [
            ['Miguel', 'Torres', 'Ruiz'],
            ['Carmen', 'Flores', 'Vega'],
        ];

        foreach ($analysts as $index => $nameParts) {
            User::create([
                'name' => "{$nameParts[0]} {$nameParts[1]}",
                'first_name' => $nameParts[0],
                'last_name' => $nameParts[1],
                'email' => strtolower($nameParts[0]) . '.' . strtolower($nameParts[1]) . '@lendusdemoii.mx',
                'phone' => '551111200' . ($index + 1),
                'password' => Hash::make('password'),
                'tenant_id' => $tenant->id,
                'type' => 'ANALYST',
                'is_active' => true,
                'email_verified_at' => now(),
                'phone_verified_at' => now(),
            ]);
        }

        $this->command->info('  - Created 6 staff users');
    }

    private function createProducts(Tenant $tenant): void
    {
        $this->command->info('Creating products...');

        $products = [
            [
                'name' => 'Crédito Express',
                'code' => 'EXPR-001',
                'type' => 'PERSONAL',
                'description' => 'Crédito rápido para emergencias',
                'min_amount' => 3000,
                'max_amount' => 50000,
                'min_term_months' => 3,
                'max_term_months' => 12,
                'interest_rate' => 42.0,
                'opening_commission' => 4.0,
                'late_fee_rate' => 6.0,
                'payment_frequencies' => ['SEMANAL', 'QUINCENAL'],
                'required_documents' => ['INE_FRONT', 'INE_BACK', 'PROOF_OF_ADDRESS'],
                'rules' => [
                    'min_amount' => 3000,
                    'max_amount' => 50000,
                    'min_term_months' => 3,
                    'max_term_months' => 12,
                    'interest_rate' => 42.0,
                ],
                'display_order' => 1,
            ],
            [
                'name' => 'Crédito PyME Plus',
                'code' => 'PYME-001',
                'type' => 'PYME',
                'description' => 'Financiamiento para pequeñas y medianas empresas',
                'min_amount' => 50000,
                'max_amount' => 500000,
                'min_term_months' => 12,
                'max_term_months' => 48,
                'interest_rate' => 28.0,
                'opening_commission' => 2.5,
                'late_fee_rate' => 4.0,
                'payment_frequencies' => ['MENSUAL'],
                'required_documents' => ['INE_FRONT', 'INE_BACK', 'PROOF_OF_ADDRESS', 'RFC_CONSTANCIA', 'BANK_STATEMENT'],
                'rules' => [
                    'min_amount' => 50000,
                    'max_amount' => 500000,
                    'min_term_months' => 12,
                    'max_term_months' => 48,
                    'interest_rate' => 28.0,
                ],
                'display_order' => 2,
            ],
            [
                'name' => 'Crédito Automotriz',
                'code' => 'AUTO-001',
                'type' => 'AUTO',
                'description' => 'Financiamiento para vehículos nuevos y seminuevos',
                'min_amount' => 100000,
                'max_amount' => 800000,
                'min_term_months' => 12,
                'max_term_months' => 60,
                'interest_rate' => 18.0,
                'opening_commission' => 2.0,
                'late_fee_rate' => 3.0,
                'payment_frequencies' => ['MENSUAL'],
                'required_documents' => ['INE_FRONT', 'INE_BACK', 'PROOF_OF_ADDRESS', 'PAYSLIP', 'VEHICLE_INVOICE'],
                'rules' => [
                    'min_amount' => 100000,
                    'max_amount' => 800000,
                    'min_term_months' => 12,
                    'max_term_months' => 60,
                    'interest_rate' => 18.0,
                ],
                'display_order' => 3,
            ],
        ];

        foreach ($products as $productData) {
            Product::create(array_merge($productData, [
                'id' => Str::uuid(),
                'tenant_id' => $tenant->id,
                'is_active' => true,
            ]));
        }

        $this->command->info('  - Created 3 products');
    }

    private function createApplicantsAndApplications(Tenant $tenant): void
    {
        $this->command->info('Creating applicants and applications...');

        $products = Product::where('tenant_id', $tenant->id)->get();
        $staffUsers = User::where('tenant_id', $tenant->id)
            ->whereIn('type', ['SUPERVISOR', 'ANALYST'])
            ->get();

        $applicantsData = [
            [
                'first_name' => 'Pedro',
                'last_name_1' => 'Gómez',
                'last_name_2' => 'Hernández',
                'email' => 'pedro.gomez@email.com',
                'phone' => '5522221111',
                'curp' => 'GOHP850315HDFRRD01',
                'rfc' => 'GOHP850315AB1',
                'birth_date' => '1985-03-15',
                'gender' => 'M',
            ],
            [
                'first_name' => 'Sofía',
                'last_name_1' => 'Ramírez',
                'last_name_2' => 'Castro',
                'email' => 'sofia.ramirez@email.com',
                'phone' => '5522222222',
                'curp' => 'RACS900720MDFRFS02',
                'rfc' => 'RACS900720CD2',
                'birth_date' => '1990-07-20',
                'gender' => 'F',
            ],
            [
                'first_name' => 'Jorge',
                'last_name_1' => 'López',
                'last_name_2' => 'Mendoza',
                'email' => 'jorge.lopez@email.com',
                'phone' => '5522223333',
                'curp' => 'LOMJ880512HDFPNR03',
                'rfc' => 'LOMJ880512EF3',
                'birth_date' => '1988-05-12',
                'gender' => 'M',
            ],
            [
                'first_name' => 'Diana',
                'last_name_1' => 'Fernández',
                'last_name_2' => 'Ortiz',
                'email' => 'diana.fernandez@email.com',
                'phone' => '5522224444',
                'curp' => 'FEOD920830MDFRNT04',
                'rfc' => 'FEOD920830GH4',
                'birth_date' => '1992-08-30',
                'gender' => 'F',
            ],
            [
                'first_name' => 'Ricardo',
                'last_name_1' => 'Morales',
                'last_name_2' => 'Vega',
                'email' => 'ricardo.morales@email.com',
                'phone' => '5522225555',
                'curp' => 'MOVR780225HDFRGL05',
                'rfc' => 'MOVR780225IJ5',
                'birth_date' => '1978-02-25',
                'gender' => 'M',
            ],
            [
                'first_name' => 'Alejandra',
                'last_name_1' => 'Torres',
                'last_name_2' => 'Silva',
                'email' => 'alejandra.torres@email.com',
                'phone' => '5522226666',
                'curp' => 'TOSA950615MDFRLL06',
                'rfc' => 'TOSA950615KL6',
                'birth_date' => '1995-06-15',
                'gender' => 'F',
            ],
        ];

        $statuses = [
            ApplicationStatus::DRAFT,
            ApplicationStatus::SUBMITTED,
            ApplicationStatus::IN_REVIEW,
            ApplicationStatus::DOCS_PENDING,
            ApplicationStatus::APPROVED,
            ApplicationStatus::REJECTED,
        ];

        $folioCounter = 1;

        foreach ($applicantsData as $index => $data) {
            // Create User for applicant
            $user = User::create([
                'tenant_id' => $tenant->id,
                'name' => "{$data['first_name']} {$data['last_name_1']}",
                'email' => $data['email'],
                'phone' => $data['phone'],
                'password' => Hash::make('password'),
                'type' => 'APPLICANT',
                'is_active' => true,
                'email_verified_at' => now(),
                'phone_verified_at' => now(),
            ]);

            // Create Applicant
            $applicant = Applicant::create([
                'id' => Str::uuid(),
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
                'first_name' => $data['first_name'],
                'last_name_1' => $data['last_name_1'],
                'last_name_2' => $data['last_name_2'],
                'full_name' => "{$data['first_name']} {$data['last_name_1']} {$data['last_name_2']}",
                'email' => $data['email'],
                'phone' => $data['phone'],
                'curp' => $data['curp'],
                'rfc' => $data['rfc'],
                'birth_date' => $data['birth_date'],
                'gender' => $data['gender'],
                'nationality' => 'MX',
                'marital_status' => ['SOLTERO', 'CASADO', 'DIVORCIADO'][rand(0, 2)],
            ]);

            // Create Address
            Address::create([
                'id' => Str::uuid(),
                'tenant_id' => $tenant->id,
                'applicant_id' => $applicant->id,
                'type' => 'HOME',
                'street' => 'Calle ' . ($index + 1) . ' de Septiembre',
                'ext_number' => (string) rand(100, 999),
                'neighborhood' => ['Centro', 'Roma Norte', 'Condesa', 'Polanco', 'Del Valle', 'Narvarte'][$index],
                'postal_code' => '0' . rand(6000, 6999),
                'city' => 'Ciudad de México',
                'municipality' => ['Cuauhtémoc', 'Benito Juárez', 'Miguel Hidalgo'][$index % 3],
                'state' => 'CDMX',
                'country' => 'MX',
                'is_primary' => true,
                'housing_type' => ['PROPIA_PAGADA', 'RENTADA', 'FAMILIAR'][rand(0, 2)],
                'years_at_address' => rand(1, 10),
            ]);

            // Create Employment
            EmploymentRecord::create([
                'id' => Str::uuid(),
                'tenant_id' => $tenant->id,
                'applicant_id' => $applicant->id,
                'employment_type' => ['EMPLEADO', 'INDEPENDIENTE', 'EMPRESARIO'][rand(0, 2)],
                'company_name' => ['Empresa ABC', 'Tech Solutions', 'Comercializadora XYZ', 'Servicios Pro', 'Innovación Digital', 'Grupo Empresarial'][$index],
                'position' => ['Gerente', 'Analista', 'Director', 'Coordinador', 'Jefe de Área', 'Especialista'][$index],
                'start_date' => now()->subMonths(rand(12, 60)),
                'monthly_income' => rand(15000, 80000),
                'monthly_net_income' => rand(12000, 65000),
                'payment_frequency' => ['MENSUAL', 'QUINCENAL'][rand(0, 1)],
                'is_current' => true,
            ]);

            // Create Bank Account
            BankAccount::create([
                'id' => Str::uuid(),
                'tenant_id' => $tenant->id,
                'applicant_id' => $applicant->id,
                'type' => 'DISBURSEMENT',
                'bank_name' => ['BBVA', 'Santander', 'Banorte', 'HSBC', 'Scotiabank', 'Citibanamex'][$index],
                'bank_code' => ['012', '014', '072', '021', '044', '002'][$index],
                'clabe' => str_pad((string) rand(1, 999999999999999999), 18, '0', STR_PAD_LEFT),
                'account_type' => 'DEBITO',
                'holder_name' => "{$data['first_name']} {$data['last_name_1']} {$data['last_name_2']}",
                'is_primary' => true,
                'is_own_account' => true,
                'is_active' => true,
            ]);

            // Create Application
            $product = $products->random();
            $status = $statuses[$index % count($statuses)];
            $requestedAmount = rand((int) $product->min_amount, (int) $product->max_amount);
            $termMonths = rand($product->min_term_months, $product->max_term_months);
            $interestRate = $product->interest_rate;
            $monthlyPayment = $this->calculateMonthlyPayment($requestedAmount, $interestRate, $termMonths);

            $assignedTo = null;
            if (in_array($status, [ApplicationStatus::IN_REVIEW, ApplicationStatus::DOCS_PENDING, ApplicationStatus::APPROVED])) {
                $assignedTo = $staffUsers->random()->id;
            }

            Application::create([
                'id' => Str::uuid(),
                'tenant_id' => $tenant->id,
                'applicant_id' => $applicant->id,
                'product_id' => $product->id,
                'folio' => 'LII-2026-' . str_pad((string) $folioCounter++, 5, '0', STR_PAD_LEFT),
                'status' => $status,
                'requested_amount' => $requestedAmount,
                'approved_amount' => $status === ApplicationStatus::APPROVED ? $requestedAmount : null,
                'term_months' => $termMonths,
                // Applications table uses English values
                'payment_frequency' => match($product->payment_frequencies[0] ?? 'MENSUAL') {
                    'SEMANAL' => 'WEEKLY',
                    'QUINCENAL' => 'BIWEEKLY',
                    'MENSUAL' => 'MONTHLY',
                    default => 'MONTHLY',
                },
                'interest_rate' => $interestRate,
                'opening_commission' => $product->opening_commission,
                'monthly_payment' => $monthlyPayment,
                'total_to_pay' => $monthlyPayment * $termMonths,
                'assigned_to' => $assignedTo,
                'assigned_at' => $assignedTo ? now() : null,
                'rejection_reason' => $status === ApplicationStatus::REJECTED ? 'Historial crediticio insuficiente' : null,
                'approved_at' => $status === ApplicationStatus::APPROVED ? now() : null,
            ]);

            // Create References
            $application = Application::where('applicant_id', $applicant->id)->first();
            $referenceNames = [
                ['Juan', 'Pérez', 'García'],
                ['María', 'López', 'Sánchez'],
            ];
            for ($r = 0; $r < 2; $r++) {
                Reference::create([
                    'id' => Str::uuid(),
                    'applicant_id' => $applicant->id,
                    'application_id' => $application->id,
                    'first_name' => $referenceNames[$r][0],
                    'last_name_1' => $referenceNames[$r][1],
                    'last_name_2' => $referenceNames[$r][2],
                    'full_name' => implode(' ', $referenceNames[$r]),
                    'phone' => '55' . rand(10000000, 99999999),
                    'relationship' => ['FAMILIAR', 'AMIGO'][$r],
                    'type' => ['PERSONAL', 'WORK'][$r],
                ]);
            }
        }

        $this->command->info('  - Created 6 applicants with applications');
    }

    private function calculateMonthlyPayment(float $amount, float $annualRate, int $months): float
    {
        $monthlyRate = ($annualRate / 100) / 12;
        if ($monthlyRate == 0) {
            return $amount / $months;
        }
        return $amount * ($monthlyRate * pow(1 + $monthlyRate, $months)) / (pow(1 + $monthlyRate, $months) - 1);
    }
}
