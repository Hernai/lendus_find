<?php

namespace Database\Factories;

use App\Models\StaffAccount;
use App\Models\StaffProfile;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Factory for StaffAccount model.
 *
 * @extends Factory<StaffAccount>
 */
class StaffAccountFactory extends Factory
{
    protected $model = StaffAccount::class;

    public function definition(): array
    {
        return [
            'id' => Str::uuid(),
            'tenant_id' => Tenant::factory(),
            'email' => fake()->unique()->safeEmail(),
            'password' => Hash::make('password'),
            'role' => 'ANALYST',
            'is_active' => true,
            'email_verified_at' => now(),
            'last_login_at' => now(),
            'last_login_ip' => fake()->ipv4(),
            'remember_token' => Str::random(10),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /**
     * Configure the factory to create a profile after creating the account.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (StaffAccount $account) {
            if (!$account->profile) {
                StaffProfile::factory()->create([
                    'account_id' => $account->id,
                ]);
            }
        });
    }

    /**
     * Set the role to ANALYST.
     */
    public function analyst(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'ANALYST',
        ]);
    }

    /**
     * Set the role to SUPERVISOR.
     */
    public function supervisor(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'SUPERVISOR',
        ]);
    }

    /**
     * Set the role to ADMIN.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'ADMIN',
        ]);
    }

    /**
     * Set the role to SUPER_ADMIN.
     */
    public function superAdmin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'SUPER_ADMIN',
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
     * Mark email as unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Create without auto-creating profile.
     */
    public function withoutProfile(): static
    {
        return $this->afterCreating(function (StaffAccount $account) {
            // Don't create profile - override the configure() behavior
        });
    }
}
