<?php

namespace Tests\Feature;

use Tests\TestCase;

class ConfigTest extends TestCase
{
    public function test_can_get_tenant_config(): void
    {
        $this->setUpTenant();

        $response = $this->withTenant()
            ->getJson('/api/config');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'tenant' => [
                    'id',
                    'name',
                    'slug',
                    'branding' => [
                        'primary_color',
                        'secondary_color',
                    ],
                    'settings',
                    'is_active',
                ],
                'products',
            ]);

        $this->assertEquals('test-tenant', $response->json('tenant.slug'));
    }

    public function test_config_requires_tenant(): void
    {
        $response = $this->getJson('/api/config');

        // Returns 404 when tenant not found
        $response->assertStatus(404);
    }

    public function test_config_returns_products(): void
    {
        $this->setUpTenant();

        $response = $this->withTenant()
            ->getJson('/api/config');

        $response->assertStatus(200);

        $products = $response->json('products');
        $this->assertIsArray($products);
        $this->assertNotEmpty($products);
        $this->assertEquals('Crédito Personal', $products[0]['name']);
    }
}
