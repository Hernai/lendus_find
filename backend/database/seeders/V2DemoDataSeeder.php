<?php

namespace Database\Seeders;

use App\Models\ApplicationStatusHistory;
use App\Models\ApplicationV2;
use App\Models\DocumentV2;
use App\Models\Person;
use App\Models\Address;
use App\Models\BankAccount;
use App\Models\PersonEmployment;
use App\Models\PersonIdentification;
use App\Models\PersonReference;
use App\Models\Product;
use App\Models\StaffAccount;
use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeder for V2 demo data with complete persons, applications, and documents.
 *
 * Creates realistic test data for testing the V2 admin panel including:
 * - Multiple persons with full profile data
 * - Person identifications (CURP, RFC, INE)
 * - Person addresses
 * - Person employments
 * - Person bank accounts
 * - Person references
 * - Applications in various statuses
 * - Documents attached to applications
 */
class V2DemoDataSeeder extends Seeder
{
    private Tenant $tenant;
    private ?StaffAccount $analyst = null;
    private ?StaffAccount $supervisor = null;
    private array $products = [];

    public function run(): void
    {
        $this->tenant = Tenant::where('slug', 'demo')->first();

        if (!$this->tenant) {
            $this->command->error('Demo tenant not found. Run DemoDataSeeder first.');
            return;
        }

        // Get staff accounts for assignment
        $this->analyst = StaffAccount::where('tenant_id', $this->tenant->id)
            ->where('role', 'ANALYST')
            ->first();
        $this->supervisor = StaffAccount::where('tenant_id', $this->tenant->id)
            ->where('role', 'SUPERVISOR')
            ->first();

        // Get products
        $this->products = Product::where('tenant_id', $this->tenant->id)
            ->where('is_active', true)
            ->get()
            ->all();

        if (empty($this->products)) {
            $this->command->warn('No products found. Creating default product.');
            $this->createDefaultProduct();
        }

        // Create demo persons with complete data
        $persons = $this->createPersons();

        // Create applications for some persons
        $this->createApplications($persons);

        $this->command->info('V2 Demo data seeded successfully!');
        $this->command->info('Created:');
        $this->command->info('  - ' . count($persons) . ' persons with complete profiles');
        $this->command->info('  - Applications in various statuses');
        $this->command->info('  - Documents attached to applications and persons');
    }

    private function createDefaultProduct(): void
    {
        $product = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Crédito Personal',
            'code' => 'PERSONAL',
            'type' => 'SIMPLE',
            'description' => 'Crédito personal para cualquier necesidad',
            'min_amount' => 5000,
            'max_amount' => 200000,
            'min_term_months' => 3,
            'max_term_months' => 36,
            'interest_rate' => 45.00,
            'opening_commission' => 5.00,
            'is_active' => true,
            'display_order' => 1,
        ]);

        $this->products = [$product];
    }

    private function createPersons(): array
    {
        $personsData = [
            [
                'first_name' => 'Roberto Carlos',
                'last_name_1' => 'García',
                'last_name_2' => 'Hernández',
                'birth_date' => '1985-03-15',
                'birth_state' => 'CDMX',
                'gender' => 'M',
                'marital_status' => 'MARRIED',
                'education_level' => 'BACHELOR',
                'dependents_count' => 2,
                'curp' => 'GAHR850315HDFRRS09',
                'rfc' => 'GAHR850315AB1',
                'employment' => [
                    'type' => 'EMPLOYEE',
                    'employer' => 'Grupo Bimbo S.A. de C.V.',
                    'employer_rfc' => 'GBI881215V44',
                    'job_title' => 'Gerente de Ventas',
                    'department' => 'Comercial',
                    'monthly_income' => 45000.00,
                    'additional_income' => 5000.00,
                    'contract_type' => 'PERMANENT',
                    'years_employed' => 5,
                    'months_employed' => 3,
                ],
                'address' => [
                    'street' => 'Av. Insurgentes Sur',
                    'exterior_number' => '1234',
                    'interior_number' => 'PH-2',
                    'neighborhood' => 'Del Valle',
                    'municipality' => 'Benito Juárez',
                    'state' => 'CDMX',
                    'postal_code' => '03100',
                    'housing_type' => 'OWNED_PAID',
                    'years_at_address' => 8,
                ],
                'bank' => ['name' => 'BBVA México', 'code' => '012'],
                'kyc_status' => 'VERIFIED',
            ],
            [
                'first_name' => 'María Fernanda',
                'last_name_1' => 'López',
                'last_name_2' => 'Martínez',
                'birth_date' => '1990-07-22',
                'birth_state' => 'JAL',
                'gender' => 'F',
                'marital_status' => 'SINGLE',
                'education_level' => 'MASTER',
                'dependents_count' => 0,
                'curp' => 'LOMM900722MJCPRT08',
                'rfc' => 'LOMM900722XY2',
                'employment' => [
                    'type' => 'EMPLOYEE',
                    'employer' => 'FEMSA Comercio S.A. de C.V.',
                    'employer_rfc' => 'FCO090519CY3',
                    'job_title' => 'Analista Financiero Sr.',
                    'department' => 'Finanzas',
                    'monthly_income' => 38000.00,
                    'additional_income' => 0.00,
                    'contract_type' => 'PERMANENT',
                    'years_employed' => 3,
                    'months_employed' => 8,
                ],
                'address' => [
                    'street' => 'Av. Américas',
                    'exterior_number' => '567',
                    'interior_number' => '12-A',
                    'neighborhood' => 'Providencia',
                    'municipality' => 'Guadalajara',
                    'state' => 'JAL',
                    'postal_code' => '44630',
                    'housing_type' => 'RENTED',
                    'years_at_address' => 2,
                    'monthly_rent' => 12000.00,
                ],
                'bank' => ['name' => 'Banorte', 'code' => '072'],
                'kyc_status' => 'VERIFIED',
            ],
            [
                'first_name' => 'Juan Pablo',
                'last_name_1' => 'Rodríguez',
                'last_name_2' => 'Pérez',
                'birth_date' => '1978-11-08',
                'birth_state' => 'NL',
                'gender' => 'M',
                'marital_status' => 'DIVORCED',
                'education_level' => 'HIGH_SCHOOL',
                'dependents_count' => 1,
                'curp' => 'ROPJ781108HNLDDR01',
                'rfc' => 'ROPJ781108QZ3',
                'employment' => [
                    'type' => 'SELF_EMPLOYED',
                    'employer' => 'Taller Mecánico Rodríguez',
                    'employer_rfc' => 'ROPJ781108QZ3',
                    'job_title' => 'Propietario',
                    'department' => 'Dirección General',
                    'monthly_income' => 28000.00,
                    'additional_income' => 7000.00,
                    'contract_type' => 'OTHER',
                    'years_employed' => 12,
                    'months_employed' => 0,
                ],
                'address' => [
                    'street' => 'Calle Padre Mier',
                    'exterior_number' => '890',
                    'interior_number' => null,
                    'neighborhood' => 'San Pedro Garza García',
                    'municipality' => 'San Pedro Garza García',
                    'state' => 'NL',
                    'postal_code' => '66220',
                    'housing_type' => 'OWNED_PAID',
                    'years_at_address' => 15,
                ],
                'bank' => ['name' => 'Santander', 'code' => '014'],
                'kyc_status' => 'VERIFIED',
            ],
            [
                'first_name' => 'Ana Patricia',
                'last_name_1' => 'Sánchez',
                'last_name_2' => 'Torres',
                'birth_date' => '1995-02-14',
                'birth_state' => 'PUE',
                'gender' => 'F',
                'marital_status' => 'SINGLE',
                'education_level' => 'BACHELOR',
                'dependents_count' => 0,
                'curp' => 'SATA950214MPLNRN03',
                'rfc' => 'SATA950214KL4',
                'employment' => [
                    'type' => 'EMPLOYEE',
                    'employer' => 'Liverpool S.A. de C.V.',
                    'employer_rfc' => 'LIV831130U87',
                    'job_title' => 'Ejecutiva de Cuenta',
                    'department' => 'Ventas',
                    'monthly_income' => 22000.00,
                    'additional_income' => 3000.00,
                    'contract_type' => 'PERMANENT',
                    'years_employed' => 1,
                    'months_employed' => 6,
                ],
                'address' => [
                    'street' => 'Blvd. Héroes del 5 de Mayo',
                    'exterior_number' => '2345',
                    'interior_number' => 'Depto. 8',
                    'neighborhood' => 'La Paz',
                    'municipality' => 'Puebla',
                    'state' => 'PUE',
                    'postal_code' => '72160',
                    'housing_type' => 'FAMILY',
                    'years_at_address' => 3,
                ],
                'bank' => ['name' => 'Citibanamex', 'code' => '002'],
                'kyc_status' => 'PENDING',
            ],
            [
                'first_name' => 'Carlos Alberto',
                'last_name_1' => 'Mendoza',
                'last_name_2' => 'Vega',
                'birth_date' => '1982-09-30',
                'birth_state' => 'GTO',
                'gender' => 'M',
                'marital_status' => 'MARRIED',
                'education_level' => 'TECHNICAL',
                'dependents_count' => 3,
                'curp' => 'MEVC820930HGTNDR05',
                'rfc' => 'MEVC820930MN5',
                'employment' => [
                    'type' => 'EMPLOYEE',
                    'employer' => 'General Motors de México',
                    'employer_rfc' => 'GMM840925PB7',
                    'job_title' => 'Técnico de Producción',
                    'department' => 'Manufactura',
                    'monthly_income' => 18000.00,
                    'additional_income' => 2000.00,
                    'contract_type' => 'PERMANENT',
                    'years_employed' => 7,
                    'months_employed' => 2,
                ],
                'address' => [
                    'street' => 'Av. Miguel Hidalgo',
                    'exterior_number' => '678',
                    'interior_number' => null,
                    'neighborhood' => 'León Centro',
                    'municipality' => 'León',
                    'state' => 'GTO',
                    'postal_code' => '37000',
                    'housing_type' => 'OWNED_MORTGAGE',
                    'years_at_address' => 5,
                ],
                'bank' => ['name' => 'HSBC', 'code' => '021'],
                'kyc_status' => 'PENDING',
            ],
            [
                'first_name' => 'Laura Elena',
                'last_name_1' => 'Jiménez',
                'last_name_2' => 'Ortiz',
                'birth_date' => '1988-12-05',
                'birth_state' => 'QRO',
                'gender' => 'F',
                'marital_status' => 'MARRIED',
                'education_level' => 'MASTER',
                'dependents_count' => 1,
                'curp' => 'JIOL881205MQRMRR07',
                'rfc' => 'JIOL881205PQ8',
                'employment' => [
                    'type' => 'EMPLOYEE',
                    'employer' => 'Kellogg Company México',
                    'employer_rfc' => 'KCM851014RY6',
                    'job_title' => 'Gerente de Marketing',
                    'department' => 'Marketing',
                    'monthly_income' => 52000.00,
                    'additional_income' => 8000.00,
                    'contract_type' => 'PERMANENT',
                    'years_employed' => 4,
                    'months_employed' => 9,
                ],
                'address' => [
                    'street' => 'Av. Constituyentes',
                    'exterior_number' => '999',
                    'interior_number' => 'Casa 15',
                    'neighborhood' => 'El Pueblito',
                    'municipality' => 'Corregidora',
                    'state' => 'QRO',
                    'postal_code' => '76904',
                    'housing_type' => 'OWNED_PAID',
                    'years_at_address' => 6,
                ],
                'bank' => ['name' => 'Scotiabank', 'code' => '044'],
                'kyc_status' => 'VERIFIED',
            ],
            [
                'first_name' => 'Miguel Ángel',
                'last_name_1' => 'Flores',
                'last_name_2' => 'Ramírez',
                'birth_date' => '1975-04-18',
                'birth_state' => 'VER',
                'gender' => 'M',
                'marital_status' => 'WIDOWED',
                'education_level' => 'BACHELOR',
                'dependents_count' => 2,
                'curp' => 'FORM750418HVRLMG04',
                'rfc' => 'FORM750418RS9',
                'employment' => [
                    'type' => 'RETIRED',
                    'employer' => 'IMSS',
                    'employer_rfc' => 'IMS421231I45',
                    'job_title' => 'Pensionado',
                    'department' => 'N/A',
                    'monthly_income' => 15000.00,
                    'additional_income' => 0.00,
                    'contract_type' => 'OTHER',
                    'years_employed' => 30,
                    'months_employed' => 0,
                ],
                'address' => [
                    'street' => 'Calle Zaragoza',
                    'exterior_number' => '123',
                    'interior_number' => null,
                    'neighborhood' => 'Centro',
                    'municipality' => 'Xalapa',
                    'state' => 'VER',
                    'postal_code' => '91000',
                    'housing_type' => 'OWNED_PAID',
                    'years_at_address' => 25,
                ],
                'bank' => ['name' => 'Banco Azteca', 'code' => '127'],
                'kyc_status' => 'VERIFIED',
            ],
            [
                'first_name' => 'Gabriela',
                'last_name_1' => 'Moreno',
                'last_name_2' => 'Castro',
                'birth_date' => '1992-08-25',
                'birth_state' => 'YUC',
                'gender' => 'F',
                'marital_status' => 'COMMON_LAW',
                'education_level' => 'BACHELOR',
                'dependents_count' => 1,
                'curp' => 'MOCG920825MYNRSR02',
                'rfc' => 'MOCG920825TU0',
                'employment' => [
                    'type' => 'EMPLOYEE',
                    'employer' => 'Grupo Modelo S.A. de C.V.',
                    'employer_rfc' => 'GMO840703QW2',
                    'job_title' => 'Coordinadora de Logística',
                    'department' => 'Operaciones',
                    'monthly_income' => 32000.00,
                    'additional_income' => 4000.00,
                    'contract_type' => 'PERMANENT',
                    'years_employed' => 2,
                    'months_employed' => 11,
                ],
                'address' => [
                    'street' => 'Calle 60',
                    'exterior_number' => '456',
                    'interior_number' => 'A',
                    'neighborhood' => 'Centro',
                    'municipality' => 'Mérida',
                    'state' => 'YUC',
                    'postal_code' => '97000',
                    'housing_type' => 'RENTED',
                    'years_at_address' => 1,
                    'monthly_rent' => 8500.00,
                ],
                'bank' => ['name' => 'Inbursa', 'code' => '036'],
                'kyc_status' => 'IN_PROGRESS',
            ],
        ];

        $persons = [];

        foreach ($personsData as $index => $data) {
            $person = $this->createPerson($data, $index);
            $persons[] = $person;
        }

        return $persons;
    }

    private function createPerson(array $data, int $index): Person
    {
        // Create Person
        $person = Person::create([
            'tenant_id' => $this->tenant->id,
            'first_name' => $data['first_name'],
            'last_name_1' => $data['last_name_1'],
            'last_name_2' => $data['last_name_2'],
            'birth_date' => $data['birth_date'],
            'birth_state' => $data['birth_state'],
            'birth_country' => 'MX',
            'gender' => $data['gender'],
            'nationality' => 'MX',
            'marital_status' => $data['marital_status'],
            'education_level' => $data['education_level'],
            'dependents_count' => $data['dependents_count'],
            'kyc_status' => $data['kyc_status'],
            'kyc_verified_at' => $data['kyc_status'] === 'VERIFIED' ? now()->subDays(rand(1, 30)) : null,
        ]);

        // Create CURP identification
        $isVerified = $data['kyc_status'] === 'VERIFIED';
        PersonIdentification::create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $person->id,
            'type' => 'CURP',
            'identifier_value' => $data['curp'],
            'is_current' => true,
            'status' => $isVerified ? 'VERIFIED' : 'PENDING',
            'verified_at' => $isVerified ? now()->subDays(rand(1, 30)) : null,
            'verification_method' => $isVerified ? 'RENAPO_API' : null,
            'verification_confidence' => $isVerified ? rand(95, 99) / 100 : null,
        ]);

        // Create RFC identification
        PersonIdentification::create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $person->id,
            'type' => 'RFC',
            'identifier_value' => $data['rfc'],
            'is_current' => true,
            'status' => $isVerified ? 'VERIFIED' : 'PENDING',
            'verified_at' => $isVerified ? now()->subDays(rand(1, 30)) : null,
            'verification_method' => $isVerified ? 'SAT_API' : null,
        ]);

        // Create INE identification with document data
        $ineIssueDate = now()->subYears(rand(1, 8));
        $ineExpireDate = $ineIssueDate->copy()->addYears(10);
        PersonIdentification::create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $person->id,
            'type' => 'INE',
            'identifier_value' => 'IDMEX' . str_pad((string) ($index + 1), 13, '0', STR_PAD_LEFT),
            'document_data' => [
                'cic' => str_pad((string) rand(100000000, 999999999), 9, '0', STR_PAD_LEFT),
                'ocr' => str_pad((string) rand(1000000000000, 9999999999999), 13, '0', STR_PAD_LEFT),
                'section' => str_pad((string) rand(1000, 9999), 4, '0', STR_PAD_LEFT),
                'emision' => rand(2015, 2023),
                'vigencia' => $ineExpireDate->year,
            ],
            'issued_at' => $ineIssueDate,
            'expires_at' => $ineExpireDate,
            'is_current' => true,
            'status' => $isVerified ? 'VERIFIED' : 'PENDING',
            'verified_at' => $isVerified ? now()->subDays(rand(1, 30)) : null,
            'verification_method' => $isVerified ? 'INE_API' : null,
            'verification_confidence' => $isVerified ? rand(90, 99) / 100 : null,
        ]);

        // Create home address
        $addressData = $data['address'];
        $yearsAtAddress = $addressData['years_at_address'];
        Address::create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $person->id,
            'type' => 'HOME',
            'street' => $addressData['street'],
            'exterior_number' => $addressData['exterior_number'],
            'interior_number' => $addressData['interior_number'],
            'neighborhood' => $addressData['neighborhood'],
            'municipality' => $addressData['municipality'],
            'state' => $addressData['state'],
            'postal_code' => $addressData['postal_code'],
            'country' => 'MX',
            'is_current' => true,
            'valid_from' => now()->subYears($yearsAtAddress),
            'years_at_address' => $yearsAtAddress,
            'months_at_address' => rand(0, 11),
            'housing_type' => $addressData['housing_type'],
            'monthly_rent' => $addressData['monthly_rent'] ?? null,
            'status' => $isVerified ? 'VERIFIED' : 'PENDING',
            'verified_at' => $isVerified ? now()->subDays(rand(1, 30)) : null,
            'verification_method' => $isVerified ? 'PROOF_OF_ADDRESS' : null,
        ]);

        // Create employment
        $empData = $data['employment'];
        $startDate = now()->subYears($empData['years_employed'])->subMonths($empData['months_employed']);
        PersonEmployment::create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $person->id,
            'employment_type' => $empData['type'],
            'is_current' => true,
            'employer_name' => $empData['employer'],
            'employer_rfc' => $empData['employer_rfc'],
            'employer_phone' => '55' . str_pad((string) rand(10000000, 99999999), 8, '0', STR_PAD_LEFT),
            'job_title' => $empData['job_title'],
            'department' => $empData['department'],
            'contract_type' => $empData['contract_type'],
            'start_date' => $startDate,
            'years_employed' => $empData['years_employed'],
            'months_employed' => $empData['months_employed'],
            'monthly_income' => $empData['monthly_income'],
            'additional_income' => $empData['additional_income'],
            'payment_frequency' => 'BIWEEKLY',
            'income_currency' => 'MXN',
            'status' => $isVerified ? 'VERIFIED' : 'PENDING',
            'verified_at' => $isVerified ? now()->subDays(rand(1, 30)) : null,
            'verification_method' => $isVerified ? 'EMPLOYER_CALL' : null,
            'income_verified' => $isVerified,
            'income_verified_at' => $isVerified ? now()->subDays(rand(1, 30)) : null,
            'verified_income' => $isVerified ? $empData['monthly_income'] : null,
        ]);

        // Create bank account
        $bank = $data['bank'];
        $clabeBase = $bank['code'] . '180' . str_pad((string) ($index + 1), 11, '0', STR_PAD_LEFT);
        $clabe = $this->calculateClabe($clabeBase);

        BankAccount::create([
            'tenant_id' => $this->tenant->id,
            'owner_type' => 'persons',
            'owner_id' => $person->id,
            'bank_name' => $bank['name'],
            'bank_code' => $bank['code'],
            'clabe' => $clabe,
            'account_number_last4' => str_pad((string) rand(1000, 9999), 4, '0', STR_PAD_LEFT),
            'holder_name' => "{$data['first_name']} {$data['last_name_1']} {$data['last_name_2']}",
            'holder_rfc' => $data['rfc'],
            'account_type' => $index % 3 === 0 ? 'PAYROLL' : 'DEBIT',
            'currency' => 'MXN',
            'is_primary' => true,
            'is_for_disbursement' => true,
            'is_for_collection' => true,
            'status' => 'ACTIVE',
            'is_verified' => $isVerified,
            'verified_at' => $isVerified ? now()->subDays(rand(1, 30)) : null,
            'verification_method' => $isVerified ? 'MICRO_DEPOSIT' : null,
        ]);

        // Create 2 references per person
        $this->createReferences($person, $data, $isVerified);

        // Update profile completeness
        $person->calculateCompleteness();

        return $person;
    }

    private function createReferences(Person $person, array $data, bool $isVerified): void
    {
        $referenceNames = [
            'PERSONAL' => [
                ['first_name' => 'Pedro', 'last_name_1' => 'González', 'last_name_2' => 'López', 'relationship' => 'FRIEND'],
                ['first_name' => 'María', 'last_name_1' => 'Hernández', 'last_name_2' => 'García', 'relationship' => 'SIBLING'],
                ['first_name' => 'José', 'last_name_1' => 'Martínez', 'last_name_2' => 'Ruiz', 'relationship' => 'COUSIN'],
                ['first_name' => 'Ana', 'last_name_1' => 'Rodríguez', 'last_name_2' => 'Pérez', 'relationship' => 'PARENT'],
            ],
            'WORK' => [
                ['first_name' => 'Luis', 'last_name_1' => 'Sánchez', 'last_name_2' => 'Torres', 'relationship' => 'COWORKER'],
                ['first_name' => 'Carmen', 'last_name_1' => 'Flores', 'last_name_2' => 'Mendoza', 'relationship' => 'BOSS'],
                ['first_name' => 'Roberto', 'last_name_1' => 'Díaz', 'last_name_2' => 'Vega', 'relationship' => 'ACQUAINTANCE'],
            ],
        ];

        // Personal reference
        $personalRef = $referenceNames['PERSONAL'][array_rand($referenceNames['PERSONAL'])];
        PersonReference::create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $person->id,
            'type' => 'PERSONAL',
            'first_name' => $personalRef['first_name'],
            'last_name_1' => $personalRef['last_name_1'],
            'last_name_2' => $personalRef['last_name_2'],
            'phone' => '55' . str_pad((string) rand(10000000, 99999999), 8, '0', STR_PAD_LEFT),
            'email' => strtolower($personalRef['first_name']) . '.' . strtolower($personalRef['last_name_1']) . '@gmail.com',
            'relationship' => $personalRef['relationship'],
            'years_known' => rand(3, 15),
            'status' => $isVerified ? 'VERIFIED' : 'PENDING',
            'verified_at' => $isVerified ? now()->subDays(rand(1, 30)) : null,
            'contact_attempts' => $isVerified ? [
                ['date' => now()->subDays(5)->toDateString(), 'time' => '10:30', 'result' => 'CONTACTED', 'notes' => 'Confirmó datos del solicitante'],
            ] : [],
        ]);

        // Work reference
        $workRef = $referenceNames['WORK'][array_rand($referenceNames['WORK'])];
        PersonReference::create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $person->id,
            'type' => 'WORK',
            'first_name' => $workRef['first_name'],
            'last_name_1' => $workRef['last_name_1'],
            'last_name_2' => $workRef['last_name_2'],
            'phone' => '55' . str_pad((string) rand(10000000, 99999999), 8, '0', STR_PAD_LEFT),
            'email' => strtolower($workRef['first_name']) . '.' . strtolower($workRef['last_name_1']) . '@empresa.com',
            'relationship' => $workRef['relationship'],
            'years_known' => rand(1, 8),
            'status' => $isVerified ? 'VERIFIED' : 'PENDING',
            'verified_at' => $isVerified ? now()->subDays(rand(1, 30)) : null,
        ]);
    }

    private function createApplications(array $persons): void
    {
        $applicationStatuses = [
            // Person 0: Approved application
            ['status' => ApplicationV2::STATUS_APPROVED, 'amount' => 75000, 'term' => 24],
            // Person 1: In review (assigned to analyst)
            ['status' => ApplicationV2::STATUS_ANALYST_REVIEW, 'amount' => 50000, 'term' => 18],
            // Person 2: Submitted (pending assignment)
            ['status' => ApplicationV2::STATUS_SUBMITTED, 'amount' => 35000, 'term' => 12],
            // Person 3: Draft (incomplete)
            ['status' => ApplicationV2::STATUS_DRAFT, 'amount' => 20000, 'term' => 6],
            // Person 4: Docs pending
            ['status' => ApplicationV2::STATUS_DOCS_PENDING, 'amount' => 45000, 'term' => 24],
            // Person 5: Supervisor review
            ['status' => ApplicationV2::STATUS_SUPERVISOR_REVIEW, 'amount' => 100000, 'term' => 36],
            // Person 6: Rejected
            ['status' => ApplicationV2::STATUS_REJECTED, 'amount' => 150000, 'term' => 48],
            // Person 7: In review
            ['status' => ApplicationV2::STATUS_IN_REVIEW, 'amount' => 60000, 'term' => 24],
        ];

        foreach ($persons as $index => $person) {
            if (!isset($applicationStatuses[$index])) {
                continue;
            }

            $appData = $applicationStatuses[$index];
            $product = $this->products[array_rand($this->products)];

            // Calculate loan details
            $amount = $appData['amount'];
            $term = $appData['term'];
            $interestRate = $product->interest_rate ?? 45.0;
            $monthlyRate = $interestRate / 100 / 12;
            $monthlyPayment = $amount * ($monthlyRate * pow(1 + $monthlyRate, $term)) / (pow(1 + $monthlyRate, $term) - 1);
            $totalAmount = $monthlyPayment * $term;
            $totalInterest = $totalAmount - $amount;

            // Create application
            $application = ApplicationV2::create([
                'tenant_id' => $this->tenant->id,
                'product_id' => $product->id,
                'applicant_type' => ApplicationV2::TYPE_INDIVIDUAL,
                'person_id' => $person->id,
                'requested_amount' => $amount,
                'requested_term_months' => $term,
                'interest_rate' => $interestRate,
                'monthly_payment' => round($monthlyPayment, 2),
                'total_interest' => round($totalInterest, 2),
                'total_amount' => round($totalAmount, 2),
                'cat' => $interestRate * 1.2, // Approximate CAT
                'purpose' => $this->getRandomPurpose(),
                'purpose_description' => 'Necesito el crédito para ' . strtolower($this->getRandomPurpose()),
                'status' => $appData['status'],
                'status_changed_at' => now()->subDays(rand(1, 10)),
                'risk_level' => $this->calculateRiskLevel($person, $amount),
                'verification_checklist' => $this->getVerificationChecklist($appData['status']),
                'snapshot_data' => [
                    'person' => [
                        'full_name' => $person->full_name,
                        'curp' => $person->curp,
                        'rfc' => $person->rfc,
                    ],
                ],
            ]);

            // Set submission data for non-draft applications
            if ($appData['status'] !== ApplicationV2::STATUS_DRAFT) {
                $application->update([
                    'submitted_at' => now()->subDays(rand(5, 15)),
                    'submission_ip' => '192.168.1.' . rand(1, 255),
                    'submission_device' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X)',
                ]);
            }

            // Assign to staff if in review
            if (in_array($appData['status'], [
                ApplicationV2::STATUS_ANALYST_REVIEW,
                ApplicationV2::STATUS_IN_REVIEW,
                ApplicationV2::STATUS_DOCS_PENDING,
            ]) && $this->analyst) {
                $application->update([
                    'assigned_to' => $this->analyst->id,
                    'assigned_at' => now()->subDays(rand(1, 5)),
                    'assigned_by' => $this->supervisor?->id,
                ]);
            }

            // Supervisor review assignment
            if ($appData['status'] === ApplicationV2::STATUS_SUPERVISOR_REVIEW && $this->supervisor) {
                $application->update([
                    'assigned_to' => $this->supervisor->id,
                    'assigned_at' => now()->subDays(rand(1, 3)),
                ]);
            }

            // Add decision for approved/rejected
            if ($appData['status'] === ApplicationV2::STATUS_APPROVED) {
                $application->update([
                    'decision' => ApplicationV2::DECISION_APPROVED,
                    'decision_at' => now()->subDays(rand(1, 3)),
                    'decision_by' => $this->supervisor?->id,
                    'decision_notes' => 'Solicitud aprobada. Cliente cumple con todos los requisitos.',
                    'approved_amount' => $amount,
                    'approved_term_months' => $term,
                    'approved_interest_rate' => $interestRate,
                    'approved_monthly_payment' => round($monthlyPayment, 2),
                ]);
            }

            if ($appData['status'] === ApplicationV2::STATUS_REJECTED) {
                $application->update([
                    'decision' => ApplicationV2::DECISION_REJECTED,
                    'decision_at' => now()->subDays(rand(1, 5)),
                    'decision_by' => $this->supervisor?->id,
                    'decision_notes' => 'No cumple con los requisitos de ingreso mínimo.',
                    'rejection_reason' => 'INSUFFICIENT_INCOME',
                ]);
            }

            // Create documents for the application
            $this->createApplicationDocuments($application, $appData['status']);

            // Create status history for this application
            $this->createStatusHistory($application, $appData['status']);
        }
    }

    /**
     * Create status history entries for an application based on its current status.
     */
    private function createStatusHistory(ApplicationV2 $application, string $currentStatus): void
    {
        $statusFlow = [
            ApplicationV2::STATUS_DRAFT => [],
            ApplicationV2::STATUS_SUBMITTED => [
                ['from' => ApplicationV2::STATUS_DRAFT, 'to' => ApplicationV2::STATUS_SUBMITTED, 'days_ago' => 10, 'notes' => 'Solicitud enviada por el cliente'],
            ],
            ApplicationV2::STATUS_IN_REVIEW => [
                ['from' => ApplicationV2::STATUS_DRAFT, 'to' => ApplicationV2::STATUS_SUBMITTED, 'days_ago' => 12, 'notes' => 'Solicitud enviada por el cliente'],
                ['from' => ApplicationV2::STATUS_SUBMITTED, 'to' => ApplicationV2::STATUS_IN_REVIEW, 'days_ago' => 10, 'notes' => 'Solicitud en revisión inicial'],
            ],
            ApplicationV2::STATUS_ANALYST_REVIEW => [
                ['from' => ApplicationV2::STATUS_DRAFT, 'to' => ApplicationV2::STATUS_SUBMITTED, 'days_ago' => 15, 'notes' => 'Solicitud enviada por el cliente'],
                ['from' => ApplicationV2::STATUS_SUBMITTED, 'to' => ApplicationV2::STATUS_IN_REVIEW, 'days_ago' => 13, 'notes' => 'Solicitud en revisión inicial'],
                ['from' => ApplicationV2::STATUS_IN_REVIEW, 'to' => ApplicationV2::STATUS_ANALYST_REVIEW, 'days_ago' => 10, 'notes' => 'Asignada a analista para revisión'],
            ],
            ApplicationV2::STATUS_SUPERVISOR_REVIEW => [
                ['from' => ApplicationV2::STATUS_DRAFT, 'to' => ApplicationV2::STATUS_SUBMITTED, 'days_ago' => 18, 'notes' => 'Solicitud enviada por el cliente'],
                ['from' => ApplicationV2::STATUS_SUBMITTED, 'to' => ApplicationV2::STATUS_IN_REVIEW, 'days_ago' => 16, 'notes' => 'Solicitud en revisión inicial'],
                ['from' => ApplicationV2::STATUS_IN_REVIEW, 'to' => ApplicationV2::STATUS_ANALYST_REVIEW, 'days_ago' => 12, 'notes' => 'Asignada a analista para revisión'],
                ['from' => ApplicationV2::STATUS_ANALYST_REVIEW, 'to' => ApplicationV2::STATUS_SUPERVISOR_REVIEW, 'days_ago' => 8, 'notes' => 'Revisión de analista completada, pendiente supervisor'],
            ],
            ApplicationV2::STATUS_DOCS_PENDING => [
                ['from' => ApplicationV2::STATUS_DRAFT, 'to' => ApplicationV2::STATUS_SUBMITTED, 'days_ago' => 14, 'notes' => 'Solicitud enviada por el cliente'],
                ['from' => ApplicationV2::STATUS_SUBMITTED, 'to' => ApplicationV2::STATUS_IN_REVIEW, 'days_ago' => 12, 'notes' => 'Solicitud en revisión inicial'],
                ['from' => ApplicationV2::STATUS_IN_REVIEW, 'to' => ApplicationV2::STATUS_DOCS_PENDING, 'days_ago' => 8, 'notes' => 'Documentación pendiente de completar'],
            ],
            ApplicationV2::STATUS_APPROVED => [
                ['from' => ApplicationV2::STATUS_DRAFT, 'to' => ApplicationV2::STATUS_SUBMITTED, 'days_ago' => 20, 'notes' => 'Solicitud enviada por el cliente'],
                ['from' => ApplicationV2::STATUS_SUBMITTED, 'to' => ApplicationV2::STATUS_IN_REVIEW, 'days_ago' => 18, 'notes' => 'Solicitud en revisión inicial'],
                ['from' => ApplicationV2::STATUS_IN_REVIEW, 'to' => ApplicationV2::STATUS_ANALYST_REVIEW, 'days_ago' => 14, 'notes' => 'Asignada a analista para revisión'],
                ['from' => ApplicationV2::STATUS_ANALYST_REVIEW, 'to' => ApplicationV2::STATUS_SUPERVISOR_REVIEW, 'days_ago' => 8, 'notes' => 'Revisión de analista completada'],
                ['from' => ApplicationV2::STATUS_SUPERVISOR_REVIEW, 'to' => ApplicationV2::STATUS_APPROVED, 'days_ago' => 3, 'notes' => 'Solicitud aprobada'],
            ],
            ApplicationV2::STATUS_REJECTED => [
                ['from' => ApplicationV2::STATUS_DRAFT, 'to' => ApplicationV2::STATUS_SUBMITTED, 'days_ago' => 16, 'notes' => 'Solicitud enviada por el cliente'],
                ['from' => ApplicationV2::STATUS_SUBMITTED, 'to' => ApplicationV2::STATUS_IN_REVIEW, 'days_ago' => 14, 'notes' => 'Solicitud en revisión inicial'],
                ['from' => ApplicationV2::STATUS_IN_REVIEW, 'to' => ApplicationV2::STATUS_ANALYST_REVIEW, 'days_ago' => 10, 'notes' => 'Asignada a analista'],
                ['from' => ApplicationV2::STATUS_ANALYST_REVIEW, 'to' => ApplicationV2::STATUS_REJECTED, 'days_ago' => 5, 'notes' => 'Rechazada: ingresos insuficientes'],
            ],
        ];

        $history = $statusFlow[$currentStatus] ?? [];

        foreach ($history as $entry) {
            ApplicationStatusHistory::create([
                'application_id' => $application->id,
                'from_status' => $entry['from'],
                'to_status' => $entry['to'],
                'changed_by' => $this->supervisor?->id ?? $this->analyst?->id,
                'changed_by_type' => StaffAccount::class,
                'notes' => $entry['notes'],
                'created_at' => now()->subDays($entry['days_ago']),
            ]);
        }
    }

    private function createApplicationDocuments(ApplicationV2 $application, string $status): void
    {
        $documentTypes = [
            ['type' => DocumentV2::TYPE_INE_FRONT, 'category' => DocumentV2::CATEGORY_IDENTITY],
            ['type' => DocumentV2::TYPE_INE_BACK, 'category' => DocumentV2::CATEGORY_IDENTITY],
            ['type' => DocumentV2::TYPE_PROOF_OF_ADDRESS, 'category' => DocumentV2::CATEGORY_ADDRESS],
            ['type' => DocumentV2::TYPE_PAYSLIP, 'category' => DocumentV2::CATEGORY_INCOME],
            ['type' => DocumentV2::TYPE_BANK_STATEMENT, 'category' => DocumentV2::CATEGORY_INCOME],
        ];

        $isApprovedOrReview = in_array($status, [
            ApplicationV2::STATUS_APPROVED,
            ApplicationV2::STATUS_SUPERVISOR_REVIEW,
            ApplicationV2::STATUS_ANALYST_REVIEW,
        ]);

        foreach ($documentTypes as $index => $docType) {
            // Skip some documents for DRAFT and DOCS_PENDING status
            if ($status === ApplicationV2::STATUS_DRAFT && $index > 1) {
                continue;
            }

            if ($status === ApplicationV2::STATUS_DOCS_PENDING && $index > 2) {
                continue;
            }

            $docStatus = DocumentV2::STATUS_PENDING;
            $reviewedAt = null;
            $reviewedBy = null;

            if ($isApprovedOrReview) {
                $docStatus = DocumentV2::STATUS_APPROVED;
                $reviewedAt = now()->subDays(rand(1, 5));
                $reviewedBy = $this->analyst?->id;
            } elseif ($status === ApplicationV2::STATUS_REJECTED && $index === 3) {
                $docStatus = DocumentV2::STATUS_REJECTED;
                $reviewedAt = now()->subDays(rand(1, 5));
                $reviewedBy = $this->analyst?->id;
            }

            DocumentV2::create([
                'tenant_id' => $this->tenant->id,
                'documentable_type' => ApplicationV2::class,
                'documentable_id' => $application->id,
                'type' => $docType['type'],
                'category' => $docType['category'],
                'file_name' => $this->generateFileName($docType['type']),
                'file_path' => 'documents/' . $this->tenant->id . '/' . Str::uuid() . '.pdf',
                'storage_disk' => 'local',
                'mime_type' => 'application/pdf',
                'file_size' => rand(100000, 500000),
                'checksum' => md5(Str::random(32)),
                'status' => $docStatus,
                'reviewed_at' => $reviewedAt,
                'reviewed_by' => $reviewedBy,
                'rejection_reason' => $docStatus === DocumentV2::STATUS_REJECTED ? 'Documento ilegible' : null,
                'ocr_processed' => $isApprovedOrReview,
                'ocr_processed_at' => $isApprovedOrReview ? now()->subDays(rand(1, 5)) : null,
                'ocr_confidence' => $isApprovedOrReview ? rand(85, 99) / 100 : null,
                'is_sensitive' => in_array($docType['type'], [DocumentV2::TYPE_INE_FRONT, DocumentV2::TYPE_INE_BACK]),
                'version_number' => 1,
            ]);
        }
    }

    private function generateFileName(string $type): string
    {
        $names = [
            DocumentV2::TYPE_INE_FRONT => 'INE_frente.pdf',
            DocumentV2::TYPE_INE_BACK => 'INE_reverso.pdf',
            DocumentV2::TYPE_PROOF_OF_ADDRESS => 'comprobante_domicilio.pdf',
            DocumentV2::TYPE_PAYSLIP => 'recibo_nomina.pdf',
            DocumentV2::TYPE_BANK_STATEMENT => 'estado_cuenta.pdf',
        ];

        return $names[$type] ?? 'documento.pdf';
    }

    private function getRandomPurpose(): string
    {
        $purposes = [
            'Consolidación de deudas',
            'Mejoras al hogar',
            'Gastos médicos',
            'Educación',
            'Compra de vehículo',
            'Capital de trabajo',
            'Gastos personales',
            'Viajes',
        ];

        return $purposes[array_rand($purposes)];
    }

    private function calculateRiskLevel(Person $person, float $amount): string
    {
        $employment = $person->currentEmployment;
        if (!$employment) {
            return ApplicationV2::RISK_HIGH;
        }

        $monthlyIncome = $employment->monthly_income ?? 0;
        $debtToIncome = $amount / ($monthlyIncome * 12);

        if ($debtToIncome < 0.2 && $employment->years_employed >= 2) {
            return ApplicationV2::RISK_LOW;
        } elseif ($debtToIncome < 0.4 && $employment->years_employed >= 1) {
            return ApplicationV2::RISK_MEDIUM;
        } elseif ($debtToIncome < 0.6) {
            return ApplicationV2::RISK_HIGH;
        }

        return ApplicationV2::RISK_VERY_HIGH;
    }

    private function getVerificationChecklist(string $status): array
    {
        $checklist = [
            'identity_verified' => false,
            'address_verified' => false,
            'income_verified' => false,
            'references_verified' => false,
            'documents_complete' => false,
        ];

        if (in_array($status, [
            ApplicationV2::STATUS_APPROVED,
            ApplicationV2::STATUS_SUPERVISOR_REVIEW,
        ])) {
            return [
                'identity_verified' => true,
                'address_verified' => true,
                'income_verified' => true,
                'references_verified' => true,
                'documents_complete' => true,
            ];
        }

        if ($status === ApplicationV2::STATUS_ANALYST_REVIEW) {
            return [
                'identity_verified' => true,
                'address_verified' => true,
                'income_verified' => false,
                'references_verified' => false,
                'documents_complete' => true,
            ];
        }

        if ($status === ApplicationV2::STATUS_IN_REVIEW) {
            return [
                'identity_verified' => true,
                'address_verified' => false,
                'income_verified' => false,
                'references_verified' => false,
                'documents_complete' => true,
            ];
        }

        return $checklist;
    }

    private function calculateClabe(string $base17): string
    {
        $weights = [3, 7, 1, 3, 7, 1, 3, 7, 1, 3, 7, 1, 3, 7, 1, 3, 7];
        $sum = 0;

        for ($i = 0; $i < 17; $i++) {
            $sum += ((int) $base17[$i] * $weights[$i]) % 10;
        }

        $checkDigit = (10 - ($sum % 10)) % 10;

        return $base17 . $checkDigit;
    }
}
