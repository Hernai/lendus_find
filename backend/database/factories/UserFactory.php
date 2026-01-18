<?php

namespace Database\Factories;

use App\Enums\UserType;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => Str::uuid(),
            'tenant_id' => Tenant::factory(),
            'name' => fake()->name(),
            'phone' => '+52' . fake()->numerify('55########'),
            'email' => fake()->unique()->safeEmail(),
            'type' => UserType::APPLICANT,
            'is_active' => true,
            'phone_verified_at' => now(),
            'last_login_at' => now(),
        ];
    }

    /**
     * Set user as applicant.
     */
    public function applicant(): static
    {
        return $this->state(fn(array $attributes) => [
            'type' => UserType::APPLICANT,
        ]);
    }

    /**
     * Set user as analyst.
     */
    public function analyst(): static
    {
        return $this->state(fn(array $attributes) => [
            'type' => UserType::ANALYST,
        ]);
    }

    /**
     * Set user as supervisor.
     */
    public function supervisor(): static
    {
        return $this->state(fn(array $attributes) => [
            'type' => UserType::SUPERVISOR,
        ]);
    }

    /**
     * Set user as admin.
     */
    public function admin(): static
    {
        return $this->state(fn(array $attributes) => [
            'type' => UserType::ADMIN,
        ]);
    }

    /**
     * Set user as super admin.
     */
    public function superAdmin(): static
    {
        return $this->state(fn(array $attributes) => [
            'type' => UserType::SUPER_ADMIN,
        ]);
    }

    /**
     * Set user as unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn(array $attributes) => [
            'phone_verified_at' => null,
        ]);
    }

    /**
     * Set user as inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn(array $attributes) => [
            'is_active' => false,
        ]);
    }
}
