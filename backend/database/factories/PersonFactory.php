<?php

namespace Database\Factories;

use App\Enums\EducationLevel;
use App\Enums\Gender;
use App\Enums\MaritalStatus;
use App\Models\ApplicantAccount;
use App\Models\Person;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory for Person model.
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Person>
 */
class PersonFactory extends Factory
{
    protected $model = Person::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $gender = fake()->randomElement(['M', 'F']);
        $firstName = $gender === 'M' ? fake('es_MX')->firstNameMale() : fake('es_MX')->firstNameFemale();

        return [
            'tenant_id' => Tenant::factory(),
            'account_id' => null,
            'first_name' => $firstName,
            'last_name_1' => fake('es_MX')->lastName(),
            'last_name_2' => fake('es_MX')->lastName(),
            'birth_date' => fake()->dateTimeBetween('-65 years', '-18 years'),
            'birth_state' => fake()->randomElement(['CDMX', 'JAL', 'NL', 'MEX', 'GTO', 'PUE', 'VER', 'YUC']),
            'birth_country' => 'MX',
            'gender' => $gender,
            'nationality' => 'MX',
            'marital_status' => fake()->randomElement(MaritalStatus::values()),
            'education_level' => fake()->randomElement(EducationLevel::values()),
            'dependents_count' => fake()->numberBetween(0, 5),
            'profile_completeness' => fake()->numberBetween(0, 100),
            'missing_data' => null,
            'kyc_status' => 'PENDING',
            'kyc_verified_at' => null,
            'kyc_verified_by' => null,
            'kyc_data' => null,
        ];
    }

    /**
     * With linked account.
     */
    public function withAccount(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'account_id' => ApplicantAccount::factory()->create([
                    'tenant_id' => $attributes['tenant_id'],
                ])->id,
            ];
        });
    }

    /**
     * Male person.
     */
    public function male(): static
    {
        return $this->state(fn() => [
            'gender' => 'M',
            'first_name' => fake('es_MX')->firstNameMale(),
        ]);
    }

    /**
     * Female person.
     */
    public function female(): static
    {
        return $this->state(fn() => [
            'gender' => 'F',
            'first_name' => fake('es_MX')->firstNameFemale(),
        ]);
    }

    /**
     * KYC verified.
     */
    public function kycVerified(): static
    {
        return $this->state(fn() => [
            'kyc_status' => 'VERIFIED',
            'kyc_verified_at' => now(),
            'kyc_data' => [
                'curp_verified' => true,
                'rfc_verified' => true,
                'ine_verified' => true,
            ],
        ]);
    }

    /**
     * KYC pending.
     */
    public function kycPending(): static
    {
        return $this->state(fn() => [
            'kyc_status' => 'PENDING',
            'kyc_verified_at' => null,
            'kyc_data' => null,
        ]);
    }

    /**
     * KYC rejected.
     */
    public function kycRejected(): static
    {
        return $this->state(fn() => [
            'kyc_status' => 'REJECTED',
            'kyc_verified_at' => null,
            'kyc_data' => [
                'rejection_reason' => 'Documents do not match',
            ],
        ]);
    }

    /**
     * Complete profile.
     */
    public function profileComplete(): static
    {
        return $this->state(fn() => [
            'profile_completeness' => 100,
            'missing_data' => [],
        ]);
    }

    /**
     * Incomplete profile.
     */
    public function profileIncomplete(): static
    {
        return $this->state(fn() => [
            'profile_completeness' => fake()->numberBetween(20, 80),
            'missing_data' => fake()->randomElements(
                ['birth_date', 'marital_status', 'education_level', 'current_address', 'curp', 'rfc'],
                fake()->numberBetween(1, 4)
            ),
        ]);
    }

    /**
     * Single marital status.
     */
    public function single(): static
    {
        return $this->state(fn() => [
            'marital_status' => MaritalStatus::SINGLE->value,
        ]);
    }

    /**
     * Married marital status.
     */
    public function married(): static
    {
        return $this->state(fn() => [
            'marital_status' => MaritalStatus::MARRIED->value,
        ]);
    }

    /**
     * Young adult (18-30).
     */
    public function youngAdult(): static
    {
        return $this->state(fn() => [
            'birth_date' => fake()->dateTimeBetween('-30 years', '-18 years'),
        ]);
    }

    /**
     * Middle aged (31-50).
     */
    public function middleAged(): static
    {
        return $this->state(fn() => [
            'birth_date' => fake()->dateTimeBetween('-50 years', '-31 years'),
        ]);
    }

    /**
     * Senior (51+).
     */
    public function senior(): static
    {
        return $this->state(fn() => [
            'birth_date' => fake()->dateTimeBetween('-70 years', '-51 years'),
        ]);
    }

    /**
     * With dependents.
     */
    public function withDependents(int $count = 2): static
    {
        return $this->state(fn() => [
            'dependents_count' => $count,
        ]);
    }

    /**
     * Without dependents.
     */
    public function withoutDependents(): static
    {
        return $this->state(fn() => [
            'dependents_count' => 0,
        ]);
    }
}
