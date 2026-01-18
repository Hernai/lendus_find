<?php

namespace Database\Factories;

use App\Enums\ApplicationStatus;
use App\Models\Tenant;
use App\Models\Applicant;
use App\Models\Product;
use App\Models\Application;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Application>
 */
class ApplicationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $requestedAmount = fake()->randomElement([10000, 25000, 50000, 75000, 100000]);
        $termMonths = fake()->randomElement([6, 12, 18, 24, 36]);
        $interestRate = 24.0;

        // Calculate monthly payment (simple formula)
        $monthlyInterest = $interestRate / 100 / 12;
        $monthlyPayment = $requestedAmount * ($monthlyInterest * pow(1 + $monthlyInterest, $termMonths))
            / (pow(1 + $monthlyInterest, $termMonths) - 1);

        return [
            'id' => Str::uuid(),
            'tenant_id' => Tenant::factory(),
            'applicant_id' => Applicant::factory(),
            'product_id' => Product::factory(),
            'folio' => 'APP-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6)),
            'status' => ApplicationStatus::DRAFT->value,
            'requested_amount' => $requestedAmount,
            'approved_amount' => null,
            'term_months' => $termMonths,
            'interest_rate' => $interestRate,
            'monthly_payment' => round($monthlyPayment, 2),
            'purpose' => fake()->randomElement([
                'Gastos personales',
                'Consolidación de deudas',
                'Remodelación del hogar',
                'Compra de vehículo',
                'Emergencia médica',
            ]),
        ];
    }

    /**
     * Set application as submitted.
     */
    public function submitted(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => ApplicationStatus::SUBMITTED->value,
        ]);
    }

    /**
     * Set application as approved.
     */
    public function approved(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => ApplicationStatus::APPROVED->value,
            'approved_at' => now(),
            'approved_amount' => $attributes['requested_amount'],
        ]);
    }

    /**
     * Set application as disbursed.
     */
    public function disbursed(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => ApplicationStatus::DISBURSED->value,
            'approved_at' => now()->subDays(5),
            'disbursed_at' => now(),
            'approved_amount' => $attributes['requested_amount'],
            'disbursement_reference' => 'DIS-' . strtoupper(Str::random(10)),
        ]);
    }

    /**
     * Set application as rejected.
     */
    public function rejected(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => ApplicationStatus::REJECTED->value,
            'rejection_reason' => fake()->randomElement([
                'Historial crediticio insuficiente',
                'Ingresos no comprobables',
                'Documentación incompleta',
            ]),
        ]);
    }
}
