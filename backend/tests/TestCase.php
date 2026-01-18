<?php

namespace Tests;

use App\Models\Tenant;
use App\Models\User;
use App\Models\Product;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected ?Tenant $tenant = null;
    protected ?User $user = null;
    protected ?User $admin = null;

    /**
     * Set up a test tenant with products.
     */
    protected function setUpTenant(): Tenant
    {
        $this->tenant = Tenant::factory()->create([
            'slug' => 'test-tenant',
            'is_active' => true,
            'branding' => [
                'primary_color' => '#6366f1',
                'secondary_color' => '#10b981',
            ],
            'settings' => [
                'otp_provider' => 'twilio',
                'max_loan_amount' => 500000,
                'min_loan_amount' => 5000,
            ],
        ]);

        // Create a test product
        Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Crédito Personal',
            'type' => 'PERSONAL',
            'is_active' => true,
            'rules' => [
                'min_amount' => 5000,
                'max_amount' => 100000,
                'min_term' => 6,
                'max_term' => 36,
                'interest_rate' => 24.0,
            ],
        ]);

        return $this->tenant;
    }

    /**
     * Set up a test user (applicant).
     */
    protected function setUpUser(): User
    {
        if (!$this->tenant) {
            $this->setUpTenant();
        }

        $this->user = User::factory()->applicant()->create([
            'tenant_id' => $this->tenant->id,
            'phone' => '+525512345678',
        ]);

        return $this->user;
    }

    /**
     * Set up an admin user.
     */
    protected function setUpAdmin(): User
    {
        if (!$this->tenant) {
            $this->setUpTenant();
        }

        $this->admin = User::factory()->admin()->create([
            'tenant_id' => $this->tenant->id,
            'phone' => '+525500000001',
        ]);

        return $this->admin;
    }

    /**
     * Make authenticated request with tenant header.
     */
    protected function withTenant(?Tenant $tenant = null): self
    {
        $tenant = $tenant ?? $this->tenant;

        return $this->withHeader('X-Tenant-ID', $tenant->slug);
    }

    /**
     * Make authenticated request as user.
     */
    protected function actingAsUser(?User $user = null): self
    {
        $user = $user ?? $this->user ?? $this->setUpUser();

        return $this->actingAs($user, 'sanctum')
            ->withTenant();
    }

    /**
     * Make authenticated request as admin.
     */
    protected function actingAsAdmin(?User $admin = null): self
    {
        $admin = $admin ?? $this->admin ?? $this->setUpAdmin();

        return $this->actingAs($admin, 'sanctum')
            ->withTenant();
    }
}
