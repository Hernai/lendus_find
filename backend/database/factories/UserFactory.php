<?php

namespace Database\Factories;

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
            'role' => 'applicant',
            'is_active' => true,
            'phone_verified_at' => now(),
            'last_login_at' => now(),
        ];
    }

    /**
     * Set user as admin.
     */
    public function admin(): static
    {
        return $this->state(fn(array $attributes) => [
            'role' => 'admin',
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
}
