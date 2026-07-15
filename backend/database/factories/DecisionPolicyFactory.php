<?php

namespace Database\Factories;

use App\Models\DecisionPolicy;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class DecisionPolicyFactory extends Factory
{
    protected $model = DecisionPolicy::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'product_id' => null,
            'version' => 1,
            'mode' => 'SHADOW',
            'is_active' => true,
            'rules' => [
                'phone_risk_gate' => ['enabled' => true, 'flag_from' => 401, 'block_from' => 601, 'fail_mode' => 'open'],
                'cooldown' => ['days' => 30],
            ],
        ];
    }

    /** Política de producto con reglas mínimas del piloto. */
    public function forProduct(string $productId, array $overrides = []): static
    {
        return $this->state(fn () => [
            'product_id' => $productId,
            'rules' => array_replace_recursive([
                'scoring' => [
                    'variables' => [
                        ['key' => 'salary_range', 'points' => ['LT_3000' => 0, 'GT_15000' => 40]],
                    ],
                    'band_cutoffs' => [
                        ['min_score' => 0, 'band' => 'BASE'],
                        ['min_score' => 35, 'band' => 'INTERMEDIA'],
                    ],
                ],
                'bands' => [
                    ['key' => 'BASE', 'min_amount' => 300, 'max_amount' => 400],
                    ['key' => 'INTERMEDIA', 'min_amount' => 500, 'max_amount' => 600],
                ],
                'first_credit' => ['term_days' => 7],
                'offer' => ['validity_hours' => 72, 'reminder_hours_before' => 24],
                'review' => ['input_timeout_minutes' => 30],
                'graduation' => [
                    'levels' => [
                        ['level' => 0, 'max_amount' => 1000, 'max_term_days' => 7],
                        ['level' => 1, 'max_amount' => 2000, 'max_term_days' => 10],
                    ],
                    'advance' => ['on_time' => 1, 'late_or_extension' => 0, 'max_late_days_for_auto' => 5],
                ],
            ], $overrides),
        ]);
    }

    public function active(): static
    {
        return $this->state(fn () => ['is_active' => true, 'mode' => 'ACTIVE']);
    }

    public function shadow(): static
    {
        return $this->state(fn () => ['is_active' => true, 'mode' => 'SHADOW']);
    }
}
