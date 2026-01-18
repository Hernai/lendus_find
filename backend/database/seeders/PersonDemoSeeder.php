<?php

namespace Database\Seeders;

use App\Models\Person;
use App\Models\PersonAddress;
use App\Models\PersonBankAccount;
use App\Models\PersonEmployment;
use App\Models\PersonIdentification;
use App\Models\PersonReference;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class PersonDemoSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::where('slug', 'demo')->first();

        if (!$tenant) {
            $this->command->error('Demo tenant not found. Run DemoDataSeeder first.');
            return;
        }

        // Create 5 demo persons with complete data
        $personsData = [
            [
                'first_name' => 'Roberto',
                'last_name_1' => 'García',
                'last_name_2' => 'Hernández',
                'birth_date' => '1985-03-15',
                'birth_state' => 'CDMX',
                'gender' => 'M',
                'marital_status' => 'MARRIED',
                'education_level' => 'BACHELOR',
                'curp' => 'GAHR850315HDFRRS09',
                'rfc' => 'GAHR850315AB1',
                'employment_type' => 'EMPLOYEE',
                'employer' => 'Grupo Bimbo S.A. de C.V.',
                'job_title' => 'Gerente de Ventas',
                'monthly_income' => 45000.00,
            ],
            [
                'first_name' => 'María',
                'last_name_1' => 'López',
                'last_name_2' => 'Martínez',
                'birth_date' => '1990-07-22',
                'birth_state' => 'JAL',
                'gender' => 'F',
                'marital_status' => 'SINGLE',
                'education_level' => 'MASTER',
                'curp' => 'LOMM900722MJCPRT08',
                'rfc' => 'LOMM900722XY2',
                'employment_type' => 'EMPLOYEE',
                'employer' => 'FEMSA Comercio',
                'job_title' => 'Analista Financiero',
                'monthly_income' => 38000.00,
            ],
            [
                'first_name' => 'Juan',
                'last_name_1' => 'Rodríguez',
                'last_name_2' => 'Pérez',
                'birth_date' => '1978-11-08',
                'birth_state' => 'NL',
                'gender' => 'M',
                'marital_status' => 'DIVORCED',
                'education_level' => 'HIGH_SCHOOL',
                'curp' => 'ROPJ781108HNLDDR01',
                'rfc' => 'ROPJ781108QZ3',
                'employment_type' => 'SELF_EMPLOYED',
                'employer' => 'Taller Mecánico Rodríguez',
                'job_title' => 'Propietario',
                'monthly_income' => 28000.00,
            ],
            [
                'first_name' => 'Ana',
                'last_name_1' => 'Sánchez',
                'last_name_2' => 'Torres',
                'birth_date' => '1995-02-14',
                'birth_state' => 'PUE',
                'gender' => 'F',
                'marital_status' => 'SINGLE',
                'education_level' => 'BACHELOR',
                'curp' => 'SATA950214MPLNRN03',
                'rfc' => 'SATA950214KL4',
                'employment_type' => 'EMPLOYEE',
                'employer' => 'Liverpool S.A. de C.V.',
                'job_title' => 'Ejecutiva de Cuenta',
                'monthly_income' => 22000.00,
            ],
            [
                'first_name' => 'Carlos',
                'last_name_1' => 'Mendoza',
                'last_name_2' => 'Vega',
                'birth_date' => '1982-09-30',
                'birth_state' => 'GTO',
                'gender' => 'M',
                'marital_status' => 'MARRIED',
                'education_level' => 'TECHNICAL',
                'curp' => 'MEVC820930HGTNDR05',
                'rfc' => 'MEVC820930MN5',
                'employment_type' => 'EMPLOYEE',
                'employer' => 'General Motors de México',
                'job_title' => 'Técnico de Producción',
                'monthly_income' => 18000.00,
            ],
        ];

        $neighborhoods = [
            ['name' => 'Polanco', 'postal_code' => '11550', 'municipality' => 'Miguel Hidalgo', 'state' => 'CDMX'],
            ['name' => 'Providencia', 'postal_code' => '44630', 'municipality' => 'Guadalajara', 'state' => 'JAL'],
            ['name' => 'San Pedro Garza García', 'postal_code' => '66220', 'municipality' => 'San Pedro Garza García', 'state' => 'NL'],
            ['name' => 'La Paz', 'postal_code' => '72160', 'municipality' => 'Puebla', 'state' => 'PUE'],
            ['name' => 'León Centro', 'postal_code' => '37000', 'municipality' => 'León', 'state' => 'GTO'],
        ];

        $banks = [
            ['name' => 'BBVA México', 'code' => '012'],
            ['name' => 'Banorte', 'code' => '072'],
            ['name' => 'Santander', 'code' => '014'],
            ['name' => 'Citibanamex', 'code' => '002'],
            ['name' => 'HSBC', 'code' => '021'],
        ];

        foreach ($personsData as $index => $data) {
            // Create Person (without account for now - will be linked during onboarding)
            $person = Person::create([
                'tenant_id' => $tenant->id,
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
                'dependents_count' => rand(0, 3),
                'kyc_status' => $index < 3 ? 'VERIFIED' : 'PENDING',
                'kyc_verified_at' => $index < 3 ? now() : null,
            ]);

            // Create CURP identification
            PersonIdentification::create([
                'tenant_id' => $tenant->id,
                'person_id' => $person->id,
                'type' => 'CURP',
                'identifier_value' => $data['curp'],
                'is_current' => true,
                'status' => $index < 3 ? 'VERIFIED' : 'PENDING',
                'verified_at' => $index < 3 ? now() : null,
                'verification_method' => $index < 3 ? 'RENAPO_API' : null,
            ]);

            // Create RFC identification
            PersonIdentification::create([
                'tenant_id' => $tenant->id,
                'person_id' => $person->id,
                'type' => 'RFC',
                'identifier_value' => $data['rfc'],
                'is_current' => true,
                'status' => $index < 3 ? 'VERIFIED' : 'PENDING',
                'verified_at' => $index < 3 ? now() : null,
                'verification_method' => $index < 3 ? 'SAT_API' : null,
            ]);

            // Create home address
            $neighborhood = $neighborhoods[$index];
            PersonAddress::create([
                'tenant_id' => $tenant->id,
                'person_id' => $person->id,
                'type' => 'HOME',
                'street' => 'Calle ' . ($index + 1) . ' de Septiembre',
                'exterior_number' => (string) (($index + 1) * 100),
                'interior_number' => $index % 2 === 0 ? 'A' : null,
                'neighborhood' => $neighborhood['name'],
                'municipality' => $neighborhood['municipality'],
                'state' => $neighborhood['state'],
                'postal_code' => $neighborhood['postal_code'],
                'country' => 'MX',
                'is_current' => true,
                'status' => $index < 3 ? 'VERIFIED' : 'PENDING',
                'verified_at' => $index < 3 ? now() : null,
                'housing_type' => $index % 2 === 0 ? 'OWNED' : 'RENTED',
                'years_at_address' => rand(1, 10),
            ]);

            // Create employment
            PersonEmployment::create([
                'tenant_id' => $tenant->id,
                'person_id' => $person->id,
                'employment_type' => $data['employment_type'],
                'employer_name' => $data['employer'],
                'employer_rfc' => 'EMP' . str_pad((string) ($index + 1), 9, '0', STR_PAD_LEFT),
                'employer_phone' => '55' . str_pad((string) (9000 + $index), 8, '0', STR_PAD_LEFT),
                'job_title' => $data['job_title'],
                'department' => 'Operaciones',
                'start_date' => now()->subYears(rand(1, 5))->subMonths(rand(0, 11)),
                'is_current' => true,
                'contract_type' => 'INDEFINITE',
                'monthly_income' => $data['monthly_income'],
                'payment_frequency' => 'BIWEEKLY',
                'years_employed' => rand(1, 5),
                'months_employed' => rand(0, 11),
                'status' => $index < 3 ? 'VERIFIED' : 'PENDING',
                'verified_at' => $index < 3 ? now() : null,
                'income_verified' => $index < 3,
                'income_verified_at' => $index < 3 ? now() : null,
                'verified_income' => $index < 3 ? $data['monthly_income'] : null,
            ]);

            // Create bank account
            $bank = $banks[$index];
            $clabeBase = $bank['code'] . '180' . str_pad((string) ($index + 1), 11, '0', STR_PAD_LEFT);
            $clabe = $this->calculateClabe($clabeBase);

            PersonBankAccount::create([
                'tenant_id' => $tenant->id,
                'owner_type' => 'persons',
                'owner_id' => $person->id,
                'bank_name' => $bank['name'],
                'bank_code' => $bank['code'],
                'clabe' => $clabe,
                'holder_name' => $data['first_name'] . ' ' . $data['last_name_1'] . ' ' . $data['last_name_2'],
                'account_type' => $index % 2 === 0 ? 'DEBIT' : 'PAYROLL',
                'is_primary' => true,
                'is_for_disbursement' => true,
                'is_for_collection' => true,
                'status' => 'ACTIVE',
                'is_verified' => $index < 3,
                'verified_at' => $index < 3 ? now() : null,
                'verification_method' => $index < 3 ? 'MICRO_DEPOSIT' : null,
            ]);

            // Create 2 references per person
            $referenceTypes = ['PERSONAL', 'WORK'];
            $relationships = [
                'PERSONAL' => ['FRIEND', 'SIBLING', 'PARENT', 'COUSIN'],
                'WORK' => ['COWORKER', 'SUPERVISOR', 'CLIENT'],
            ];

            foreach ($referenceTypes as $refIndex => $refType) {
                $possibleRelationships = $relationships[$refType];
                PersonReference::create([
                    'tenant_id' => $tenant->id,
                    'person_id' => $person->id,
                    'type' => $refType,
                    'first_name' => 'Referencia' . ($refIndex + 1),
                    'last_name_1' => 'Apellido' . ($index + 1),
                    'last_name_2' => 'Demo',
                    'phone' => '55' . str_pad((string) (5000 + ($index * 10) + $refIndex), 8, '0', STR_PAD_LEFT),
                    'relationship' => $possibleRelationships[array_rand($possibleRelationships)],
                    'years_known' => rand(2, 15),
                    'status' => $index < 2 ? 'VERIFIED' : 'PENDING',
                    'verified_at' => $index < 2 ? now() : null,
                ]);
            }

            // Update profile completeness
            $person->profile_completeness = $person->calculateCompleteness();
            $person->save();
        }

        $this->command->info('Person demo data seeded successfully!');
        $this->command->info('Created 5 persons with:');
        $this->command->info('  - 2 identifications each (CURP + RFC)');
        $this->command->info('  - 1 home address each');
        $this->command->info('  - 1 employment record each');
        $this->command->info('  - 1 bank account each');
        $this->command->info('  - 2 references each (1 personal + 1 work)');
    }

    /**
     * Calculate valid CLABE with check digit.
     */
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
