<?php

namespace Database\Seeders;

use App\Models\Address;
use App\Models\Applicant;
use App\Models\Application;
use App\Models\ApplicationNote;
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

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        // Create Demo Tenant
        $tenant = Tenant::create([
            'id' => Str::uuid(),
            'name' => 'Lendus Demo',
            'slug' => 'demo',
            'legal_name' => 'Lendus Financiera S.A. de C.V. SOFOM E.N.R.',
            'rfc' => 'LFI180101ABC',
            'branding' => [
                'primary_color' => '#2563eb',
                'secondary_color' => '#1e40af',
                'logo_url' => '/images/logo.svg',
            ],
            'settings' => [
                'otp_provider' => 'twilio',
                'kyc_provider' => 'mati',
                'max_loan_amount' => 500000,
                'min_loan_amount' => 5000,
            ],
            'email' => 'contacto@lendus.mx',
            'phone' => '5555555555',
            'website' => 'https://lendus.mx',
            'is_active' => true,
            'activated_at' => now(),
        ]);

        // Create Products
        $products = [
            [
                'id' => Str::uuid(),
                'tenant_id' => $tenant->id,
                'name' => 'Crédito Personal',
                'type' => 'PERSONAL',
                'description' => 'Crédito personal para cualquier necesidad',
                'rules' => [
                    'min_amount' => 5000,
                    'max_amount' => 150000,
                    'min_term' => 3,
                    'max_term' => 36,
                    'interest_rate' => 36.0,
                    'opening_commission' => 3.0,
                    'payment_frequencies' => ['WEEKLY', 'BIWEEKLY', 'MONTHLY'],
                ],
                'required_docs' => ['INE_FRONT', 'INE_BACK', 'PROOF_ADDRESS', 'PROOF_INCOME'],
                'is_active' => true,
                'display_order' => 1,
            ],
            [
                'id' => Str::uuid(),
                'tenant_id' => $tenant->id,
                'name' => 'Crédito Nómina',
                'type' => 'PAYROLL',
                'description' => 'Crédito con descuento vía nómina',
                'rules' => [
                    'min_amount' => 10000,
                    'max_amount' => 300000,
                    'min_term' => 6,
                    'max_term' => 48,
                    'interest_rate' => 24.0,
                    'opening_commission' => 2.0,
                    'payment_frequencies' => ['BIWEEKLY', 'MONTHLY'],
                ],
                'required_docs' => ['INE_FRONT', 'INE_BACK', 'PROOF_ADDRESS', 'PAYSLIP_1', 'PAYSLIP_2', 'PAYSLIP_3'],
                'is_active' => true,
                'display_order' => 2,
            ],
            [
                'id' => Str::uuid(),
                'tenant_id' => $tenant->id,
                'name' => 'Arrendamiento',
                'type' => 'LEASING',
                'description' => 'Arrendamiento de vehículos y maquinaria',
                'rules' => [
                    'min_amount' => 50000,
                    'max_amount' => 1000000,
                    'min_term' => 12,
                    'max_term' => 60,
                    'interest_rate' => 18.0,
                    'opening_commission' => 2.5,
                    'payment_frequencies' => ['MONTHLY'],
                ],
                'required_docs' => ['INE_FRONT', 'INE_BACK', 'PROOF_ADDRESS', 'PROOF_INCOME', 'VEHICLE_INVOICE'],
                'is_active' => true,
                'display_order' => 3,
            ],
        ];

        foreach ($products as $productData) {
            Product::create($productData);
        }

        $personalProduct = Product::where('type', 'PERSONAL')->first();
        $payrollProduct = Product::where('type', 'PAYROLL')->first();

        // Create Admin User
        $adminUser = User::create([
            'name' => 'Admin Demo',
            'first_name' => 'Admin',
            'last_name' => 'Demo',
            'email' => 'admin@lendus.mx',
            'phone' => '5500000001',
            'password' => Hash::make('password'),
            'tenant_id' => $tenant->id,
            'type' => User::TYPE_ADMIN,
            'is_active' => true,
            'email_verified_at' => now(),
            'phone_verified_at' => now(),
        ]);

        // Create Analyst Users
        $analysts = [];
        $analystNames = [
            ['Patricia', 'Moreno', 'Ruiz'],
            ['Fernando', 'Díaz', 'Castro'],
        ];

        foreach ($analystNames as $index => $nameParts) {
            $analysts[] = User::create([
                'name' => "{$nameParts[0]} {$nameParts[1]}",
                'first_name' => $nameParts[0],
                'last_name' => $nameParts[1],
                'email' => strtolower($nameParts[0]) . '.' . strtolower($nameParts[1]) . '@lendus.mx',
                'phone' => '550000010' . ($index + 1),
                'password' => Hash::make('password'),
                'tenant_id' => $tenant->id,
                'type' => User::TYPE_ANALYST,
                'is_active' => true,
                'email_verified_at' => now(),
                'phone_verified_at' => now(),
            ]);
        }

        // Create Agent Users (Promotores)
        $agents = [];
        $agentNames = [
            ['Carlos', 'Ramírez', 'López'],
            ['María', 'López', 'García'],
            ['Juan', 'Hernández', 'Martínez'],
            ['Ana', 'García', 'Sánchez'],
        ];

        foreach ($agentNames as $index => $nameParts) {
            $agents[] = User::create([
                'name' => "{$nameParts[0]} {$nameParts[1]}",
                'first_name' => $nameParts[0],
                'last_name' => $nameParts[1],
                'email' => strtolower($nameParts[0]) . '.' . strtolower($nameParts[1]) . '@lendus.mx',
                'phone' => '550000000' . ($index + 2),
                'password' => Hash::make('password'),
                'tenant_id' => $tenant->id,
                'type' => User::TYPE_AGENT,
                'is_active' => true,
                'email_verified_at' => now(),
                'phone_verified_at' => now(),
            ]);
        }

        // Create Applicants with complete data
        $applicantsData = [
            [
                'first_name' => 'Juan Carlos',
                'last_name_1' => 'Pérez',
                'last_name_2' => 'García',
                'curp' => 'PEGJ850315HDFRRC09',
                'rfc' => 'PEGJ8503151A1',
                'phone' => '5512345678',
                'email' => 'juan.perez@email.com',
                'birth_date' => '1985-03-15',
                'gender' => 'M',
                'marital_status' => 'CASADO',
                'education_level' => 'LICENCIATURA',
                'dependents_count' => 2,
            ],
            [
                'first_name' => 'María',
                'last_name_1' => 'González',
                'last_name_2' => 'López',
                'curp' => 'GOLM900720MDFRPR01',
                'rfc' => 'GOLM900720AB2',
                'phone' => '5598765432',
                'email' => 'maria.gonzalez@email.com',
                'birth_date' => '1990-07-20',
                'gender' => 'F',
                'marital_status' => 'SOLTERO',
                'education_level' => 'MAESTRIA',
                'dependents_count' => 0,
            ],
            [
                'first_name' => 'Carlos',
                'last_name_1' => 'Rodríguez',
                'last_name_2' => 'Martínez',
                'curp' => 'ROMC880512HDFDRR05',
                'rfc' => 'ROMC880512CD3',
                'phone' => '5511223344',
                'email' => 'carlos.rodriguez@email.com',
                'birth_date' => '1988-05-12',
                'gender' => 'M',
                'marital_status' => 'UNION_LIBRE',
                'education_level' => 'PREPARATORIA',
                'dependents_count' => 1,
            ],
            [
                'first_name' => 'Ana',
                'last_name_1' => 'Martínez',
                'last_name_2' => 'Sánchez',
                'curp' => 'MASA920830MDFRNN04',
                'rfc' => 'MASA920830EF4',
                'phone' => '5544556677',
                'email' => 'ana.martinez@email.com',
                'birth_date' => '1992-08-30',
                'gender' => 'F',
                'marital_status' => 'CASADO',
                'education_level' => 'LICENCIATURA',
                'dependents_count' => 3,
            ],
            [
                'first_name' => 'Roberto',
                'last_name_1' => 'Hernández',
                'last_name_2' => 'Torres',
                'curp' => 'HETR750115HDFRRS08',
                'rfc' => 'HETR750115GH5',
                'phone' => '5588990011',
                'email' => 'roberto.hernandez@email.com',
                'birth_date' => '1975-01-15',
                'gender' => 'M',
                'marital_status' => 'DIVORCIADO',
                'education_level' => 'DOCTORADO',
                'dependents_count' => 2,
            ],
        ];

        $statuses = ['SUBMITTED', 'SUBMITTED', 'IN_REVIEW', 'IN_REVIEW', 'DOCS_PENDING', 'APPROVED', 'REJECTED', 'DISBURSED'];
        $folioCounter = 42;

        foreach ($applicantsData as $index => $data) {
            // Create User for Applicant
            $user = User::create([
                'name' => "{$data['first_name']} {$data['last_name_1']}",
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name_1'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'password' => Hash::make('password'),
                'tenant_id' => $tenant->id,
                'type' => User::TYPE_APPLICANT,
                'is_active' => true,
                'email_verified_at' => now(),
                'phone_verified_at' => now(),
            ]);

            // Create Applicant
            $applicant = Applicant::create([
                'id' => Str::uuid(),
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
                'type' => 'PERSONA_FISICA',
                'first_name' => $data['first_name'],
                'last_name_1' => $data['last_name_1'],
                'last_name_2' => $data['last_name_2'],
                'curp' => $data['curp'],
                'rfc' => $data['rfc'],
                'phone' => $data['phone'],
                'email' => $data['email'],
                'birth_date' => $data['birth_date'],
                'gender' => $data['gender'],
                'marital_status' => $data['marital_status'],
                'nationality' => 'MEXICANA',
                'birth_state' => 'CIUDAD DE MEXICO',
                'birth_country' => 'MEXICO',
                'education_level' => $data['education_level'],
                'dependents_count' => $data['dependents_count'],
                'kyc_status' => $index < 3 ? 'PENDING' : 'VERIFIED',
                'signature_base64' => $index >= 2 ? 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==' : null,
                'signature_date' => $index >= 2 ? now() : null,
            ]);

            // Create Address
            Address::create([
                'id' => Str::uuid(),
                'tenant_id' => $tenant->id,
                'applicant_id' => $applicant->id,
                'type' => 'HOME',
                'is_primary' => true,
                'street' => 'Av. Insurgentes Sur',
                'ext_number' => (string)(1000 + $index * 100),
                'int_number' => $index % 2 === 0 ? (string)($index + 1) : null,
                'neighborhood' => ['Del Valle', 'Roma Norte', 'Condesa', 'Polanco', 'Coyoacán'][$index],
                'municipality' => 'Benito Juárez',
                'postal_code' => '0' . (3100 + $index * 10),
                'city' => 'Ciudad de México',
                'state' => 'CIUDAD DE MEXICO',
                'country' => 'MEXICO',
                'housing_type' => ['PROPIA_PAGADA', 'RENTADA', 'FAMILIAR', 'PROPIA_HIPOTECA', 'RENTADA'][$index],
                'monthly_rent' => $index % 2 === 1 ? 12000 + ($index * 1000) : null,
                'years_at_address' => 2 + $index,
                'months_at_address' => $index * 2,
                'is_verified' => $index >= 2,
            ]);

            // Create Employment Record
            EmploymentRecord::create([
                'id' => Str::uuid(),
                'tenant_id' => $tenant->id,
                'applicant_id' => $applicant->id,
                'is_current' => true,
                'employment_type' => ['EMPLEADO', 'EMPLEADO', 'INDEPENDIENTE', 'EMPLEADO', 'EMPRESARIO'][$index],
                'company_name' => ['Empresa ABC S.A.', 'Corporativo XYZ', 'Freelancer', 'Banco Nacional', 'Mi Negocio S.A.'][$index],
                'company_rfc' => 'EAB' . str_pad($index + 1, 6, '0', STR_PAD_LEFT) . 'A' . ($index + 1) . 'B', // 12 chars for companies
                'company_industry' => ['Tecnología', 'Finanzas', 'Consultoría', 'Banca', 'Comercio'][$index],
                'company_size' => ['MEDIANA', 'GRANDE', 'MICRO', 'GRANDE', 'PEQUENA'][$index],
                'position' => ['Desarrollador Senior', 'Contador', 'Consultor', 'Ejecutivo de Cuenta', 'Director General'][$index],
                'department' => ['TI', 'Finanzas', 'Consultoría', 'Comercial', 'Dirección'][$index],
                'contract_type' => ['INDEFINIDO', 'INDEFINIDO', 'HONORARIOS', 'INDEFINIDO', 'INDEFINIDO'][$index],
                'start_date' => now()->subYears(2 + $index)->subMonths($index),
                'seniority_months' => (2 + $index) * 12 + $index,
                'monthly_income' => 25000 + ($index * 10000),
                'monthly_net_income' => 22000 + ($index * 8500),
                'payment_frequency' => 'QUINCENAL',
                'income_type' => ['NOMINA', 'NOMINA', 'HONORARIOS', 'NOMINA', 'NEGOCIO_PROPIO'][$index],
                'other_income' => $index >= 3 ? 5000 + ($index * 1000) : null,
                'other_income_source' => $index >= 3 ? 'Rentas' : null,
                'work_phone' => '55' . str_pad($index + 1, 8, '1', STR_PAD_LEFT),
                'is_verified' => $index >= 2,
            ]);

            // Create Bank Account
            BankAccount::create([
                'id' => Str::uuid(),
                'tenant_id' => $tenant->id,
                'applicant_id' => $applicant->id,
                'type' => 'BOTH',
                'is_primary' => true,
                'bank_name' => ['BBVA MEXICO', 'SANTANDER', 'BANORTE', 'HSBC', 'BANAMEX'][$index],
                'bank_code' => ['012', '014', '072', '021', '002'][$index],
                'clabe' => ['012', '014', '072', '021', '002'][$index] . '180' . str_pad($index + 1, 11, '0', STR_PAD_LEFT) . (9 - $index),
                'account_number' => str_pad($index + 1, 10, '0', STR_PAD_LEFT),
                'account_type' => ['NOMINA', 'DEBITO', 'AHORRO', 'NOMINA', 'CHEQUES'][$index],
                'holder_name' => strtoupper("{$data['first_name']} {$data['last_name_1']} {$data['last_name_2']}"),
                'holder_rfc' => $data['rfc'],
                'is_own_account' => true,
                'is_verified' => $index >= 2,
                'is_active' => true,
            ]);

            // Create Applications
            $numApplications = min($index + 1, 2);
            for ($appIndex = 0; $appIndex < $numApplications; $appIndex++) {
                $status = $statuses[($index + $appIndex) % count($statuses)];
                $product = $appIndex === 0 ? $personalProduct : $payrollProduct;
                $amount = 50000 + ($index * 20000) + ($appIndex * 10000);

                $application = Application::create([
                    'id' => Str::uuid(),
                    'tenant_id' => $tenant->id,
                    'applicant_id' => $applicant->id,
                    'product_id' => $product->id,
                    'folio' => 'LEN-2026-' . str_pad($folioCounter--, 5, '0', STR_PAD_LEFT),
                    'requested_amount' => $amount,
                    'approved_amount' => in_array($status, ['APPROVED', 'DISBURSED']) ? $amount : null,
                    'term_months' => 12 + ($index * 6),
                    'payment_frequency' => 'MONTHLY',
                    'interest_rate' => $product->rules['interest_rate'],
                    'opening_commission' => $product->rules['opening_commission'],
                    'monthly_payment' => round($amount * 1.36 / (12 + ($index * 6)), 2),
                    'total_to_pay' => round($amount * 1.36, 2),
                    'cat' => 45.0 + ($index * 2),
                    'purpose' => ['CONSOLIDACION', 'HOGAR', 'EDUCACION', 'NEGOCIO', 'EMERGENCIA'][$index],
                    'status' => $status,
                    'assigned_to' => in_array($status, ['IN_REVIEW', 'DOCS_PENDING', 'APPROVED']) ? $agents[$index % count($agents)]->id : null,
                    'assigned_at' => in_array($status, ['IN_REVIEW', 'DOCS_PENDING', 'APPROVED']) ? now()->subHours($index) : null,
                    'created_at' => now()->subDays($index * 2)->subHours($appIndex * 5),
                    'updated_at' => now()->subHours($index),
                ]);

                // Create Documents
                $docTypes = ['INE_FRONT', 'INE_BACK', 'PROOF_ADDRESS', 'PROOF_INCOME'];
                $docsToCreate = min($index + 1, count($docTypes));

                for ($docIndex = 0; $docIndex < $docsToCreate; $docIndex++) {
                    Document::create([
                        'id' => Str::uuid(),
                        'tenant_id' => $tenant->id,
                        'application_id' => $application->id,
                        'applicant_id' => $applicant->id,
                        'type' => $docTypes[$docIndex],
                        'name' => str_replace('_', ' ', $docTypes[$docIndex]),
                        'file_path' => "documents/{$applicant->id}/{$docTypes[$docIndex]}.pdf",
                        'file_name' => "{$docTypes[$docIndex]}.pdf",
                        'mime_type' => 'application/pdf',
                        'file_size' => rand(100000, 500000),
                        'storage_disk' => 'local',
                        'status' => $docIndex < $index ? 'APPROVED' : ($docIndex === $index ? 'PENDING' : 'PENDING'),
                        'reviewed_by' => $docIndex < $index ? $adminUser->id : null,
                        'reviewed_at' => $docIndex < $index ? now()->subHours($index) : null,
                    ]);
                }

                // Create References
                $refNames = [
                    ['Pedro', 'López', 'Sánchez'],
                    ['Laura', 'García', 'Hernández'],
                ];

                for ($refIndex = 0; $refIndex < min($index + 1, 2); $refIndex++) {
                    Reference::create([
                        'id' => Str::uuid(),
                        'applicant_id' => $applicant->id,
                        'application_id' => $application->id,
                        'first_name' => $refNames[$refIndex][0],
                        'last_name_1' => $refNames[$refIndex][1],
                        'last_name_2' => $refNames[$refIndex][2],
                        'full_name' => implode(' ', $refNames[$refIndex]),
                        'phone' => '55' . str_pad(rand(10000000, 99999999), 8, '0', STR_PAD_LEFT),
                        'relationship' => ['HERMANO', 'AMIGO', 'COMPAÑERO_TRABAJO', 'PADRE_MADRE'][$refIndex],
                        'type' => $refIndex === 0 ? 'PERSONAL' : 'WORK',
                        'is_verified' => $refIndex < $index,
                        'verified_at' => $refIndex < $index ? now()->subHours($index) : null,
                        'verified_by' => $refIndex < $index ? $agents[0]->id : null,
                    ]);
                }

                // Create Notes for some applications
                if ($index >= 2) {
                    ApplicationNote::create([
                        'id' => Str::uuid(),
                        'application_id' => $application->id,
                        'user_id' => $agents[$index % count($agents)]->id,
                        'content' => 'Solicitud revisada. Documentación en orden.',
                        'is_internal' => true,
                        'type' => 'NOTE',
                    ]);
                }
            }
        }

        $this->command->info('Demo data seeded successfully!');
        $this->command->info("Tenant slug: demo");
        $this->command->info("");
        $this->command->info("Staff Credentials (password: 'password'):");
        $this->command->info("  Admin: admin@lendus.mx");
        $this->command->info("  Analista: patricia.moreno@lendus.mx");
        $this->command->info("  Agente: carlos.ramirez@lendus.mx");
    }
}
