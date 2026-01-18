<?php

namespace Database\Factories;

use App\Models\ApplicantAccount;
use App\Models\Company;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Company>
 */
class CompanyFactory extends Factory
{
    protected $model = Company::class;

    public function definition(): array
    {
        $legalEntityTypes = ['SA', 'SAPI', 'SA_DE_CV', 'SAPI_DE_CV', 'SC', 'SRL'];
        $companySizes = ['MICRO', 'SMALL', 'MEDIUM', 'LARGE'];

        return [
            'tenant_id' => Tenant::factory(),
            'created_by_account_id' => ApplicantAccount::factory(),
            'legal_name' => fake()->company(),
            'trade_name' => fake()->optional(0.7)->company(),
            'rfc' => $this->generateCompanyRfc(),
            'legal_entity_type' => fake()->randomElement($legalEntityTypes),
            'incorporation_date' => fake()->dateTimeBetween('-20 years', '-1 year'),
            'notary_number' => fake()->optional()->numerify('####'),
            'commercial_folio' => fake()->optional()->numerify('######'),
            'industry_code' => fake()->numerify('####'),
            'industry_description' => fake()->randomElement([
                'Comercio al por menor',
                'Servicios profesionales',
                'Manufactura',
                'Construcción',
                'Transporte',
                'Tecnología',
            ]),
            'main_activity' => fake()->sentence(3),
            'company_size' => fake()->randomElement($companySizes),
            'employees_count' => fake()->numberBetween(1, 500),
            'annual_revenue' => fake()->randomFloat(2, 100000, 50000000),
            'annual_revenue_currency' => 'MXN',
            'website' => fake()->optional()->url(),
            'main_phone' => fake()->numerify('55########'),
            'main_email' => fake()->companyEmail(),
            'status' => Company::STATUS_PENDING,
            'kyb_status' => Company::KYB_PENDING,
        ];
    }

    /**
     * Generate a valid Mexican company RFC (12 characters).
     */
    private function generateCompanyRfc(): string
    {
        $letters = '';
        for ($i = 0; $i < 3; $i++) {
            $letters .= chr(rand(65, 90)); // A-Z
        }

        $year = str_pad(rand(80, 23), 2, '0', STR_PAD_LEFT);
        $month = str_pad(rand(1, 12), 2, '0', STR_PAD_LEFT);
        $day = str_pad(rand(1, 28), 2, '0', STR_PAD_LEFT);

        $homoclave = strtoupper(fake()->lexify('???'));

        return $letters . $year . $month . $day . $homoclave;
    }

    /**
     * Verified company.
     */
    public function verified(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => Company::STATUS_VERIFIED,
            'verified_at' => now(),
            'kyb_status' => Company::KYB_VERIFIED,
            'kyb_verified_at' => now(),
        ]);
    }

    /**
     * Micro company.
     */
    public function micro(): static
    {
        return $this->state(fn(array $attributes) => [
            'company_size' => Company::SIZE_MICRO,
            'employees_count' => fake()->numberBetween(1, 10),
            'annual_revenue' => fake()->randomFloat(2, 100000, 1000000),
        ]);
    }

    /**
     * Small company.
     */
    public function small(): static
    {
        return $this->state(fn(array $attributes) => [
            'company_size' => Company::SIZE_SMALL,
            'employees_count' => fake()->numberBetween(11, 50),
            'annual_revenue' => fake()->randomFloat(2, 1000000, 5000000),
        ]);
    }

    /**
     * Medium company.
     */
    public function medium(): static
    {
        return $this->state(fn(array $attributes) => [
            'company_size' => Company::SIZE_MEDIUM,
            'employees_count' => fake()->numberBetween(51, 250),
            'annual_revenue' => fake()->randomFloat(2, 5000000, 20000000),
        ]);
    }

    /**
     * Large company.
     */
    public function large(): static
    {
        return $this->state(fn(array $attributes) => [
            'company_size' => Company::SIZE_LARGE,
            'employees_count' => fake()->numberBetween(251, 1000),
            'annual_revenue' => fake()->randomFloat(2, 20000000, 100000000),
        ]);
    }

    /**
     * Suspended company.
     */
    public function suspended(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => Company::STATUS_SUSPENDED,
        ]);
    }
}
