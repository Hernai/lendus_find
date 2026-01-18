<?php

namespace Tests\Feature;

use App\Models\Product;
use Tests\TestCase;

class SimulatorTest extends TestCase
{
    protected ?Product $product = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();

        // Create a test product for simulations
        // Values go in 'rules' JSON field since columns don't exist in base migration
        $this->product = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Crédito Personal',
            'type' => 'PERSONAL',
            'is_active' => true,
            'rules' => [
                'min_amount' => 5000,
                'max_amount' => 100000,
                'min_term' => 3,
                'max_term' => 36,
                'interest_rate' => 24.0,
                'opening_commission' => 3.0,
            ],
        ]);
    }

    public function test_can_get_simulator_products(): void
    {
        $response = $this->withTenant()
            ->getJson('/api/simulator/products');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'products' => [
                    '*' => [
                        'id',
                        'name',
                        'type',
                    ],
                ],
            ]);
    }

    public function test_can_calculate_loan(): void
    {
        $response = $this->withTenant()
            ->postJson('/api/simulator/calculate', [
                'product_id' => $this->product->id,
                'amount' => 50000,
                'term_months' => 12,
                'payment_frequency' => 'MONTHLY',
            ]);

        if ($response->status() !== 200) {
            dump('Response: ' . $response->getContent());
            dump('Product ID: ' . $this->product->id);
            dump('Product rules: ' . json_encode($this->product->rules));
            dump('Product min_amount: ' . $this->product->min_amount);
            dump('Product max_amount: ' . $this->product->max_amount);
        }

        $response->assertStatus(200)
            ->assertJsonStructure([
                'simulation' => [
                    'product_id',
                    'product_name',
                    'requested_amount',
                    'term_months',
                    'payment_amount',
                ],
            ]);

        $simulation = $response->json('simulation');
        $this->assertEquals(50000, $simulation['requested_amount']);
        $this->assertEquals(12, $simulation['term_months']);
    }

    public function test_calculate_validates_required_fields(): void
    {
        $response = $this->withTenant()
            ->postJson('/api/simulator/calculate', [
                'amount' => 50000,
                // Missing product_id, term_months, payment_frequency
            ]);

        $response->assertStatus(422);
    }

    public function test_can_get_amortization_table(): void
    {
        $response = $this->withTenant()
            ->getJson('/api/simulator/amortization?amount=50000&annual_rate=24&term_months=6&payment_frequency=MONTHLY');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'amortization',
            ]);

        // amortization should be an array of payment periods
        $this->assertIsArray($response->json('amortization'));
    }
}
