<?php

namespace Database\Factories;

use App\Enums\CompanySize;
use App\Enums\ContractType;
use App\Enums\EmploymentType;
use App\Enums\PaymentFrequency;
use App\Models\Person;
use App\Models\PersonEmployment;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory for PersonEmployment model.
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PersonEmployment>
 */
class PersonEmploymentFactory extends Factory
{
    protected $model = PersonEmployment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $yearsEmployed = fake()->numberBetween(0, 15);
        $monthsEmployed = fake()->numberBetween(0, 11);
        $startDate = now()->subYears($yearsEmployed)->subMonths($monthsEmployed);

        return [
            'tenant_id' => Tenant::factory(),
            'person_id' => Person::factory(),
            'employment_type' => EmploymentType::EMPLOYEE->value,
            'is_current' => true,
            'employer_name' => fake()->company(),
            'employer_rfc' => $this->generateCompanyRfc(),
            'employer_phone' => fake()->numerify('55########'),
            'employer_address' => fake('es_MX')->address(),
            'industry_code' => fake()->numerify('#####'),
            'industry_description' => fake()->randomElement([
                'Servicios Financieros',
                'Tecnología',
                'Manufactura',
                'Comercio',
                'Construcción',
                'Salud',
                'Educación',
                'Transporte',
            ]),
            'company_size' => fake()->randomElement(CompanySize::values()),
            'job_title' => fake()->jobTitle(),
            'department' => fake()->randomElement(['Ventas', 'Operaciones', 'Administración', 'TI', 'RH', 'Finanzas']),
            'employee_number' => fake()->numerify('EMP-#####'),
            'contract_type' => fake()->randomElement(ContractType::values()),
            'start_date' => $startDate,
            'end_date' => null,
            'years_employed' => $yearsEmployed,
            'months_employed' => $monthsEmployed,
            'monthly_income' => fake()->randomFloat(2, 8000, 80000),
            'additional_income' => fake()->optional(0.3)->randomFloat(2, 1000, 15000),
            'payment_frequency' => fake()->randomElement(PaymentFrequency::values()),
            'income_currency' => 'MXN',
            'income_verified' => false,
            'income_verified_at' => null,
            'income_verified_by' => null,
            'income_verification_method' => null,
            'verified_income' => null,
            'status' => PersonEmployment::STATUS_PENDING,
            'verified_at' => null,
            'verified_by' => null,
            'verification_method' => null,
            'verification_notes' => null,
            'verification_data' => null,
            'notes' => null,
            'metadata' => null,
        ];
    }

    /**
     * Employee type.
     */
    public function employee(): static
    {
        return $this->state(fn() => [
            'employment_type' => EmploymentType::EMPLOYEE->value,
            'employer_name' => fake()->company(),
            'employer_rfc' => $this->generateCompanyRfc(),
        ]);
    }

    /**
     * Self-employed type.
     */
    public function selfEmployed(): static
    {
        return $this->state(fn() => [
            'employment_type' => EmploymentType::SELF_EMPLOYED->value,
            'employer_name' => null,
            'employer_rfc' => null,
            'contract_type' => null,
            'employee_number' => null,
        ]);
    }

    /**
     * Business owner type.
     */
    public function businessOwner(): static
    {
        return $this->state(fn() => [
            'employment_type' => EmploymentType::BUSINESS_OWNER->value,
            'employer_name' => fake()->company(),
            'job_title' => fake()->randomElement(['Director General', 'Propietario', 'Socio']),
        ]);
    }

    /**
     * Retired/pensioner type.
     */
    public function retired(): static
    {
        return $this->state(fn() => [
            'employment_type' => EmploymentType::RETIRED->value,
            'employer_name' => fake()->randomElement(['IMSS', 'ISSSTE', 'Pensión Privada']),
            'job_title' => 'Pensionado',
            'contract_type' => null,
            'monthly_income' => fake()->randomFloat(2, 5000, 30000),
        ]);
    }

    /**
     * Student type.
     */
    public function student(): static
    {
        return $this->state(fn() => [
            'employment_type' => EmploymentType::STUDENT->value,
            'employer_name' => null,
            'employer_rfc' => null,
            'monthly_income' => null,
            'contract_type' => null,
        ]);
    }

    /**
     * Unemployed type.
     */
    public function unemployed(): static
    {
        return $this->state(fn() => [
            'employment_type' => EmploymentType::UNEMPLOYED->value,
            'is_current' => true,
            'employer_name' => null,
            'employer_rfc' => null,
            'monthly_income' => null,
            'contract_type' => null,
            'start_date' => null,
        ]);
    }

    /**
     * Current employment.
     */
    public function current(): static
    {
        return $this->state(fn() => [
            'is_current' => true,
            'end_date' => null,
        ]);
    }

    /**
     * Past employment.
     */
    public function past(): static
    {
        $endDate = fake()->dateTimeBetween('-2 years', '-1 month');
        $startDate = fake()->dateTimeBetween('-10 years', $endDate);

        return $this->state(fn() => [
            'is_current' => false,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);
    }

    /**
     * Verified employment.
     */
    public function verified(): static
    {
        return $this->state(fn() => [
            'status' => PersonEmployment::STATUS_VERIFIED,
            'verified_at' => fake()->dateTimeBetween('-30 days', 'now'),
            'verification_method' => fake()->randomElement(['PHONE_CALL', 'DOCUMENT', 'IMSS_API']),
        ]);
    }

    /**
     * Income verified.
     */
    public function incomeVerified(): static
    {
        return $this->state(function (array $attributes) {
            $income = $attributes['monthly_income'] ?? fake()->randomFloat(2, 8000, 80000);
            return [
                'income_verified' => true,
                'income_verified_at' => fake()->dateTimeBetween('-30 days', 'now'),
                'income_verification_method' => fake()->randomElement(['PAYSLIP', 'BANK_STATEMENT', 'TAX_RETURN']),
                'verified_income' => $income * fake()->randomFloat(2, 0.95, 1.05), // Small variance
            ];
        });
    }

    /**
     * Pending status.
     */
    public function pending(): static
    {
        return $this->state(fn() => [
            'status' => PersonEmployment::STATUS_PENDING,
            'verified_at' => null,
            'verification_method' => null,
        ]);
    }

    /**
     * Rejected status.
     */
    public function rejected(): static
    {
        return $this->state(fn() => [
            'status' => PersonEmployment::STATUS_REJECTED,
            'verification_notes' => 'Could not verify employment',
        ]);
    }

    /**
     * Unreachable status.
     */
    public function unreachable(): static
    {
        return $this->state(fn() => [
            'status' => PersonEmployment::STATUS_UNREACHABLE,
            'verification_notes' => 'Unable to contact employer',
        ]);
    }

    /**
     * High income (50k+).
     */
    public function highIncome(): static
    {
        return $this->state(fn() => [
            'monthly_income' => fake()->randomFloat(2, 50000, 150000),
            'additional_income' => fake()->randomFloat(2, 5000, 30000),
        ]);
    }

    /**
     * Low income (below 15k).
     */
    public function lowIncome(): static
    {
        return $this->state(fn() => [
            'monthly_income' => fake()->randomFloat(2, 6000, 15000),
            'additional_income' => null,
        ]);
    }

    /**
     * Long tenure (5+ years).
     */
    public function longTenure(): static
    {
        $years = fake()->numberBetween(5, 20);
        return $this->state(fn() => [
            'years_employed' => $years,
            'months_employed' => fake()->numberBetween(0, 11),
            'start_date' => now()->subYears($years)->subMonths(fake()->numberBetween(0, 11)),
        ]);
    }

    /**
     * Short tenure (less than 1 year).
     */
    public function shortTenure(): static
    {
        $months = fake()->numberBetween(1, 11);
        return $this->state(fn() => [
            'years_employed' => 0,
            'months_employed' => $months,
            'start_date' => now()->subMonths($months),
        ]);
    }

    /**
     * Permanent contract.
     */
    public function permanentContract(): static
    {
        return $this->state(fn() => [
            'contract_type' => ContractType::PERMANENT->value,
        ]);
    }

    /**
     * Temporary contract.
     */
    public function temporaryContract(): static
    {
        return $this->state(fn() => [
            'contract_type' => ContractType::TEMPORARY->value,
        ]);
    }

    /**
     * Generate a valid-format RFC for company (12 chars).
     */
    private function generateCompanyRfc(): string
    {
        $letters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $digits = '0123456789';
        $alphanumeric = $letters . $digits;

        // Format for company: AAA######AAA (12 chars)
        $rfc = '';
        // First 3 letters
        for ($i = 0; $i < 3; $i++) {
            $rfc .= $letters[rand(0, 25)];
        }
        // Date (YYMMDD)
        $rfc .= str_pad(rand(80, 23), 2, '0', STR_PAD_LEFT);
        $rfc .= str_pad(rand(1, 12), 2, '0', STR_PAD_LEFT);
        $rfc .= str_pad(rand(1, 28), 2, '0', STR_PAD_LEFT);
        // Homoclave (3 alphanumeric)
        for ($i = 0; $i < 3; $i++) {
            $rfc .= $alphanumeric[rand(0, strlen($alphanumeric) - 1)];
        }

        return $rfc;
    }
}
