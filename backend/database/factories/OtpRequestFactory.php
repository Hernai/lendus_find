<?php

namespace Database\Factories;

use App\Models\ApplicantIdentity;
use App\Models\OtpRequest;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Factory for OtpRequest model.
 *
 * @extends Factory<OtpRequest>
 */
class OtpRequestFactory extends Factory
{
    protected $model = OtpRequest::class;

    public function definition(): array
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        return [
            'id' => Str::uuid(),
            'identity_id' => null,
            'target_type' => 'PHONE',
            'target_value' => $this->generateMexicanPhone(),
            'code' => $code,
            'channel' => 'SMS',
            'expires_at' => now()->addMinutes(10),
            'verified_at' => null,
            'attempts' => 0,
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /**
     * Generate a Mexican phone number (10 digits).
     */
    protected function generateMexicanPhone(): string
    {
        $prefix = fake()->randomElement(['55', '56', '33', '81', '44']);
        $rest = fake()->numerify('########');
        return $prefix . $rest;
    }

    /**
     * Create for phone target via SMS.
     */
    public function phoneSms(): static
    {
        return $this->state(fn (array $attributes) => [
            'target_type' => 'PHONE',
            'target_value' => $this->generateMexicanPhone(),
            'channel' => 'SMS',
        ]);
    }

    /**
     * Create for phone target via WhatsApp.
     */
    public function phoneWhatsapp(): static
    {
        return $this->state(fn (array $attributes) => [
            'target_type' => 'PHONE',
            'target_value' => $this->generateMexicanPhone(),
            'channel' => 'WHATSAPP',
        ]);
    }

    /**
     * Create for email target.
     */
    public function email(): static
    {
        return $this->state(fn (array $attributes) => [
            'target_type' => 'EMAIL',
            'target_value' => fake()->unique()->safeEmail(),
            'channel' => 'EMAIL',
        ]);
    }

    /**
     * Link to an existing identity.
     */
    public function forIdentity(ApplicantIdentity $identity): static
    {
        return $this->state(fn (array $attributes) => [
            'identity_id' => $identity->id,
            'target_type' => $identity->type,
            'target_value' => $identity->identifier,
            'channel' => $identity->type === 'EMAIL' ? 'EMAIL' : 'SMS',
        ]);
    }

    /**
     * Set specific target.
     */
    public function forTarget(string $type, string $value): static
    {
        return $this->state(fn (array $attributes) => [
            'target_type' => $type,
            'target_value' => $value,
            'channel' => $type === 'EMAIL' ? 'EMAIL' : 'SMS',
        ]);
    }

    /**
     * Set specific code.
     */
    public function withCode(string $code): static
    {
        return $this->state(fn (array $attributes) => [
            'code' => $code,
        ]);
    }

    /**
     * Mark as verified.
     */
    public function verified(): static
    {
        return $this->state(fn (array $attributes) => [
            'verified_at' => now(),
        ]);
    }

    /**
     * Mark as expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->subMinutes(5),
            'verified_at' => null,
        ]);
    }

    /**
     * Set with too many attempts.
     */
    public function tooManyAttempts(): static
    {
        return $this->state(fn (array $attributes) => [
            'attempts' => 5,
            'verified_at' => null,
        ]);
    }

    /**
     * Create a valid OTP (not expired, not verified, not too many attempts).
     */
    public function valid(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->addMinutes(10),
            'verified_at' => null,
            'attempts' => 0,
        ]);
    }

    /**
     * Create recent OTP (within last hour).
     */
    public function recent(): static
    {
        return $this->state(fn (array $attributes) => [
            'created_at' => now()->subMinutes(fake()->numberBetween(1, 30)),
        ]);
    }

    /**
     * Create old OTP (more than an hour ago).
     */
    public function old(): static
    {
        return $this->state(fn (array $attributes) => [
            'created_at' => now()->subHours(2),
            'expires_at' => now()->subHours(2)->addMinutes(10),
        ]);
    }
}
