<?php

namespace App\Services;

use App\Contracts\KycServiceInterface;
use App\Models\Tenant;
use App\Services\ExternalApi\NubariumService;

/**
 * Factory for creating KYC service instances.
 *
 * This factory resolves the appropriate KYC provider based on tenant
 * configuration and provides a clean way to inject KYC services.
 * The current tenant is injected via constructor to avoid service locator.
 */
class KycServiceFactory
{
    public function __construct(
        protected ?Tenant $currentTenant = null
    ) {
        // Tenant is resolved from container via TenantServiceProvider
    }

    /**
     * Create a KYC service instance for the given tenant.
     */
    public function create(?Tenant $tenant = null): NubariumService
    {
        $tenant = $tenant ?? $this->currentTenant;

        if (!$tenant) {
            throw new \RuntimeException('No tenant available for KYC service creation');
        }

        return new NubariumService($tenant);
    }

    /**
     * Create a KYC service for the current tenant context.
     */
    public function forCurrentTenant(): NubariumService
    {
        return $this->create($this->currentTenant);
    }
}
