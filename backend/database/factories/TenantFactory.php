<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Tenant>
 */
class TenantFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->company();

        return [
            'id' => Str::uuid(),
            'name' => $name,
            'slug' => Str::slug($name) . '-' . Str::random(4),
            'is_active' => true,
            'branding' => [
                'primary_color' => '#6366f1',
                'secondary_color' => '#10b981',
                'accent_color' => '#f59e0b',
                'logo_url' => '/logo.svg',
                'font_family' => 'Inter, sans-serif',
                'border_radius' => '12px',
            ],
            'settings' => [
                'otp_provider' => 'twilio',
                'max_loan_amount' => 500000,
                'min_loan_amount' => 5000,
                'currency' => 'MXN',
                'timezone' => 'America/Mexico_City',
            ],
            'webhook_config' => null,
        ];
    }

    /**
     * Inactive tenant.
     */
    public function inactive(): static
    {
        return $this->state(fn(array $attributes) => [
            'is_active' => false,
        ]);
    }
}
