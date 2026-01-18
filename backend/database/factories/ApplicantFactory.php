<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Applicant>
 */
class ApplicantFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $gender = fake()->randomElement(['M', 'F']);
        $firstName = $gender === 'M' ? fake()->firstNameMale() : fake()->firstNameFemale();
        $lastName1 = fake()->lastName();
        $lastName2 = fake()->lastName();
        $birthDate = fake()->dateTimeBetween('-60 years', '-18 years');

        return [
            'id' => Str::uuid(),
            'tenant_id' => Tenant::factory(),
            'user_id' => User::factory(),
            'first_name' => $firstName,
            'last_name_1' => $lastName1,
            'last_name_2' => $lastName2,
            'birth_date' => $birthDate->format('Y-m-d'),
            'gender' => $gender,
            'curp' => $this->generateCurp($firstName, $lastName1, $lastName2, $birthDate, $gender),
            'rfc' => strtoupper(substr($lastName1, 0, 2) . substr($lastName2, 0, 1) . substr($firstName, 0, 1)) .
                $birthDate->format('ymd') . fake()->regexify('[A-Z0-9]{3}'),
            'nationality' => 'MEXICANA',
            'birth_country' => 'MEXICO',
            'birth_state' => fake()->randomElement(['CDMX', 'JAL', 'NL', 'PUE', 'GTO']),
            'type' => fake()->randomElement(['INDIVIDUAL', 'BUSINESS']),
            'marital_status' => fake()->randomElement(['SINGLE', 'MARRIED', 'COMMON_LAW', 'DIVORCED']),
            'education_level' => fake()->randomElement(['HIGH_SCHOOL', 'BACHELOR', 'MASTER']),
            'kyc_status' => 'PENDING',
        ];
    }

    /**
     * Generate a fake CURP.
     */
    private function generateCurp(string $firstName, string $lastName1, string $lastName2, \DateTime $birthDate, string $gender): string
    {
        $curp = strtoupper(
            substr($lastName1, 0, 2) .
            substr($lastName2, 0, 1) .
            substr($firstName, 0, 1)
        );
        $curp .= $birthDate->format('ymd');
        $curp .= $gender === 'M' ? 'H' : 'M';
        $curp .= fake()->randomElement(['DF', 'JC', 'NL', 'PL', 'GT']);
        $curp .= strtoupper(fake()->regexify('[BCDFGHJKLMNPQRSTVWXYZ]{3}'));
        $curp .= fake()->regexify('[0-9A-Z]{2}');

        return substr($curp, 0, 18);
    }

    /**
     * Applicant with verified KYC.
     */
    public function kycVerified(): static
    {
        return $this->state(fn(array $attributes) => [
            'kyc_status' => 'VERIFIED',
            'kyc_verified_at' => now(),
        ]);
    }

    /**
     * Applicant with rejected KYC.
     */
    public function kycRejected(): static
    {
        return $this->state(fn(array $attributes) => [
            'kyc_status' => 'REJECTED',
        ]);
    }
}
