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
        // Use ProductType enum values
        $types = ['PERSONAL', 'NOMINA', 'PYME'];
        $type = fake()->randomElement($types);

        $code = strtoupper(match ($type) {
            'PERSONAL' => 'PER',
            'NOMINA' => 'NOM',
            'PYME' => 'PYM',
            default => 'GEN',
        } . '-' . fake()->unique()->numerify('###'));

        return [
            'id' => Str::uuid(),
            'tenant_id' => Tenant::factory(),
            'code' => $code,
            'name' => match ($type) {
                'PERSONAL' => 'Crédito Personal',
                'NOMINA' => 'Crédito de Nómina',
                'PYME' => 'Crédito PyME',
                default => 'Crédito General',
            },
            'type' => $type,
            'description' => fake()->sentence(),
            'is_active' => true,
            'display_order' => fake()->numberBetween(1, 10),
            // Database columns (with defaults from migration)
            'min_amount' => 5000,
            'max_amount' => 100000,
            'min_term_months' => 6,
            'max_term_months' => 36,
            'interest_rate' => 24.0,
            'opening_commission' => 3.0,
            'payment_frequencies' => ['MONTHLY'],
            // Legacy JSON fields
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
            'code' => 'PER-' . fake()->unique()->numerify('###'),
        ]);
    }

    /**
     * Payroll/Nomina loan product.
     */
    public function nomina(): static
    {
        return $this->state(fn(array $attributes) => [
            'name' => 'Crédito de Nómina',
            'type' => 'NOMINA',
            'code' => 'NOM-' . fake()->unique()->numerify('###'),
        ]);
    }

    /**
     * PyME loan product.
     */
    public function pyme(): static
    {
        return $this->state(fn(array $attributes) => [
            'name' => 'Crédito PyME',
            'type' => 'PYME',
            'code' => 'PYM-' . fake()->unique()->numerify('###'),
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
