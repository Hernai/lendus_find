<?php

namespace Database\Factories;

use App\Models\ApplicantAccount;
use App\Models\ApplicantIdentity;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Factory for ApplicantIdentity model.
 *
 * @extends Factory<ApplicantIdentity>
 */
class ApplicantIdentityFactory extends Factory
{
    protected $model = ApplicantIdentity::class;

    public function definition(): array
    {
        return [
            'id' => Str::uuid(),
            'account_id' => ApplicantAccount::factory(),
            'type' => 'PHONE',
            'identifier' => $this->generateMexicanPhone(),
            'verified_at' => now(),
            'verification_code' => null,
            'verification_code_expires_at' => null,
            'verification_attempts' => 0,
            'is_primary' => false,
            'last_used_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /**
     * Generate a Mexican phone number (10 digits).
     */
    protected function generateMexicanPhone(): string
    {
        // Mexican phone: 10 digits, starting with valid prefixes
        $prefix = fake()->randomElement(['55', '56', '33', '81', '44']);
        $rest = fake()->numerify('########');
        return $prefix . $rest;
    }

    /**
     * Set type to PHONE with Mexican phone number.
     */
    public function phone(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'PHONE',
            'identifier' => $this->generateMexicanPhone(),
        ]);
    }

    /**
     * Set type to EMAIL.
     */
    public function email(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'EMAIL',
            'identifier' => fake()->unique()->safeEmail(),
        ]);
    }

    /**
     * Set type to WHATSAPP with Mexican phone number.
     */
    public function whatsapp(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'WHATSAPP',
            'identifier' => $this->generateMexicanPhone(),
        ]);
    }

    /**
     * Mark as verified.
     */
    public function verified(): static
    {
        return $this->state(fn (array $attributes) => [
            'verified_at' => now(),
            'verification_code' => null,
            'verification_code_expires_at' => null,
            'verification_attempts' => 0,
            'last_used_at' => now(),
        ]);
    }

    /**
     * Mark as unverified with pending OTP.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'verified_at' => null,
            'verification_code' => str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT),
            'verification_code_expires_at' => now()->addMinutes(10),
            'verification_attempts' => 0,
        ]);
    }

    /**
     * Set as primary identity.
     */
    public function primary(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_primary' => true,
        ]);
    }

    /**
     * Set with expired verification code.
     */
    public function expiredCode(): static
    {
        return $this->state(fn (array $attributes) => [
            'verified_at' => null,
            'verification_code' => str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT),
            'verification_code_expires_at' => now()->subMinutes(5),
            'verification_attempts' => 0,
        ]);
    }

    /**
     * Set with too many verification attempts.
     */
    public function tooManyAttempts(): static
    {
        return $this->state(fn (array $attributes) => [
            'verified_at' => null,
            'verification_code' => str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT),
            'verification_code_expires_at' => now()->addMinutes(10),
            'verification_attempts' => 5,
        ]);
    }

    /**
     * Set specific identifier.
     */
    public function withIdentifier(string $identifier): static
    {
        return $this->state(fn (array $attributes) => [
            'identifier' => $identifier,
        ]);
    }

    /**
     * Set specific verification code.
     */
    public function withVerificationCode(string $code): static
    {
        return $this->state(fn (array $attributes) => [
            'verified_at' => null,
            'verification_code' => $code,
            'verification_code_expires_at' => now()->addMinutes(10),
            'verification_attempts' => 0,
        ]);
    }
}
