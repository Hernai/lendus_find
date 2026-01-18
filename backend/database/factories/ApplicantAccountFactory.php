<?php

namespace Database\Factories;

use App\Models\ApplicantAccount;
use App\Models\ApplicantIdentity;
use App\Models\Person;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Factory for ApplicantAccount model.
 *
 * @extends Factory<ApplicantAccount>
 */
class ApplicantAccountFactory extends Factory
{
    protected $model = ApplicantAccount::class;

    public function definition(): array
    {
        return [
            'id' => Str::uuid(),
            'tenant_id' => Tenant::factory(),
            'person_id' => null,
            'pin_hash' => null,
            'pin_set_at' => null,
            'pin_attempts' => 0,
            'pin_locked_until' => null,
            'is_active' => true,
            'onboarding_step' => 0,
            'onboarding_completed' => false,
            'onboarding_completed_at' => null,
            'last_login_at' => null,
            'last_login_ip' => null,
            'last_login_method' => null,
            'known_devices' => null,
            'preferences' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /**
     * By default, do NOT create identity. Use withIdentity() to add one.
     */
    public function configure(): static
    {
        return $this;
    }

    /**
     * Create with a primary phone identity.
     */
    public function withIdentity(): static
    {
        return $this->afterCreating(function (ApplicantAccount $account) {
            if ($account->identities()->count() === 0) {
                ApplicantIdentity::factory()->phone()->verified()->create([
                    'account_id' => $account->id,
                    'is_primary' => true,
                ]);
            }
        });
    }

    /**
     * Create account with PIN set.
     */
    public function withPin(string $pin = '123456'): static
    {
        return $this->state(fn (array $attributes) => [
            'pin_hash' => Hash::make($pin),
            'pin_set_at' => now(),
        ]);
    }

    /**
     * Create account with locked PIN (too many attempts).
     */
    public function pinLocked(): static
    {
        return $this->state(fn (array $attributes) => [
            'pin_hash' => Hash::make('123456'),
            'pin_set_at' => now()->subDays(1),
            'pin_attempts' => 5,
            'pin_locked_until' => now()->addMinutes(30),
        ]);
    }

    /**
     * Mark the account as inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Set onboarding as completed.
     */
    public function onboardingCompleted(): static
    {
        return $this->state(fn (array $attributes) => [
            'onboarding_step' => 8,
            'onboarding_completed' => true,
            'onboarding_completed_at' => now(),
        ]);
    }

    /**
     * Set onboarding to specific step.
     */
    public function atOnboardingStep(int $step): static
    {
        return $this->state(fn (array $attributes) => [
            'onboarding_step' => $step,
            'onboarding_completed' => false,
        ]);
    }

    /**
     * Set last login info.
     */
    public function withLastLogin(string $method = 'PHONE_OTP'): static
    {
        return $this->state(fn (array $attributes) => [
            'last_login_at' => now(),
            'last_login_ip' => fake()->ipv4(),
            'last_login_method' => $method,
        ]);
    }

    /**
     * Set known devices.
     */
    public function withKnownDevices(): static
    {
        return $this->state(fn (array $attributes) => [
            'known_devices' => [
                [
                    'device_id' => Str::uuid()->toString(),
                    'last_seen' => now()->toIso8601String(),
                    'user_agent' => fake()->userAgent(),
                ],
            ],
        ]);
    }

    /**
     * Set preferences.
     */
    public function withPreferences(array $preferences = []): static
    {
        return $this->state(fn (array $attributes) => [
            'preferences' => array_merge([
                'preferred_contact' => 'whatsapp',
                'language' => 'es',
            ], $preferences),
        ]);
    }

    /**
     * Create with both phone and email identities.
     */
    public function withMultipleIdentities(): static
    {
        return $this->afterCreating(function (ApplicantAccount $account) {
            // Delete any auto-created identity first
            $account->identities()->delete();

            // Create phone as primary
            ApplicantIdentity::factory()->phone()->verified()->create([
                'account_id' => $account->id,
                'is_primary' => true,
            ]);

            // Create email as secondary
            ApplicantIdentity::factory()->email()->verified()->create([
                'account_id' => $account->id,
                'is_primary' => false,
            ]);
        });
    }

    /**
     * Create with unverified identity.
     */
    public function withUnverifiedIdentity(): static
    {
        return $this->afterCreating(function (ApplicantAccount $account) {
            // Delete any auto-created identity first
            $account->identities()->delete();

            // Create unverified phone identity
            ApplicantIdentity::factory()->phone()->unverified()->create([
                'account_id' => $account->id,
                'is_primary' => true,
            ]);
        });
    }

    /**
     * Create account with an associated Person.
     */
    public function withPerson(): static
    {
        return $this->afterCreating(function (ApplicantAccount $account) {
            if (!$account->person_id) {
                $person = Person::factory()->create([
                    'tenant_id' => $account->tenant_id,
                    'account_id' => $account->id,
                ]);
                $account->update(['person_id' => $person->id]);
            }
        });
    }
}
