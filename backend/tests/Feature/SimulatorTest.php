<?php

namespace Tests\Feature;

use Tests\TestCase;

class SimulatorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
    }

    public function test_can_get_simulator_products(): void
    {
        $response = $this->withTenant()
            ->getJson('/api/simulator/products');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'type',
                        'rules',
                    ],
                ],
            ]);
    }

    public function test_can_calculate_loan(): void
    {
        $response = $this->withTenant()
            ->postJson('/api/simulator/calculate', [
                'amount' => 50000,
                'term' => 12,
                'product_type' => 'PERSONAL',
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'amount',
                    'term',
                    'interest_rate',
                    'monthly_payment',
                    'total_interest',
                    'total_payment',
                    'cat',
                ],
            ]);

        $data = $response->json('data');
        $this->assertEquals(50000, $data['amount']);
        $this->assertEquals(12, $data['term']);
    }

    public function test_calculate_validates_amount(): void
    {
        $response = $this->withTenant()
            ->postJson('/api/simulator/calculate', [
                'amount' => 1000, // Below minimum
                'term' => 12,
                'product_type' => 'PERSONAL',
            ]);

        $response->assertStatus(422);
    }

    public function test_can_get_amortization_table(): void
    {
        $response = $this->withTenant()
            ->getJson('/api/simulator/amortization?amount=50000&term=6&product_type=PERSONAL');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'summary' => [
                        'amount',
                        'term',
                        'monthly_payment',
                    ],
                    'schedule' => [
                        '*' => [
                            'payment_number',
                            'payment_date',
                            'principal',
                            'interest',
                            'payment',
                            'balance',
                        ],
                    ],
                ],
            ]);

        $schedule = $response->json('data.schedule');
        $this->assertCount(6, $schedule); // 6 months term
    }
}
