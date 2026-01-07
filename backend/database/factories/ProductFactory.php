<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $types = ['PERSONAL', 'PAYROLL', 'SME'];
        $type = fake()->randomElement($types);

        return [
            'id' => Str::uuid(),
            'tenant_id' => Tenant::factory(),
            'name' => match ($type) {
                'PERSONAL' => 'Crédito Personal',
                'PAYROLL' => 'Crédito de Nómina',
                'SME' => 'Crédito PyME',
            },
            'type' => $type,
            'description' => fake()->sentence(),
            'is_active' => true,
            'display_order' => fake()->numberBetween(1, 10),
            'rules' => [
                'min_amount' => 5000,
                'max_amount' => 100000,
                'min_term' => 6,
                'max_term' => 36,
                'interest_rate' => 24.0,
            ],
            'required_docs' => ['INE', 'COMPROBANTE_DOMICILIO'],
            'extra_fields' => [],
        ];
    }

    /**
     * Personal loan product.
     */
    public function personal(): static
    {
        return $this->state(fn(array $attributes) => [
            'name' => 'Crédito Personal',
            'type' => 'PERSONAL',
        ]);
    }

    /**
     * Payroll loan product.
     */
    public function payroll(): static
    {
        return $this->state(fn(array $attributes) => [
            'name' => 'Crédito de Nómina',
            'type' => 'PAYROLL',
        ]);
    }

    /**
     * Inactive product.
     */
    public function inactive(): static
    {
        return $this->state(fn(array $attributes) => [
            'is_active' => false,
        ]);
    }
}
