<?php

namespace App\Services;

use App\Models\Tenant;
use App\Services\ExternalApi\TwilioService;

/**
 * Factory for creating TwilioService instances.
 *
 * Follows Dependency Injection pattern to avoid direct instantiation
 * with `new TwilioService()` throughout the codebase.
 * The current tenant ID is injected via constructor to avoid service locator.
 */
class TwilioServiceFactory
{
    public function __construct(
        protected ?string $currentTenantId = null
    ) {
        // Tenant ID is resolved from container via TenantServiceProvider
    }

    /**
     * Create a TwilioService for a specific tenant.
     */
    public function create(?string $tenantId = null): TwilioService
    {
        $tenantId = $tenantId ?? $this->currentTenantId;

        if (!$tenantId) {
            throw new \RuntimeException('No tenant ID available for Twilio service creation');
        }

        return new TwilioService($tenantId);
    }

    /**
     * Create a TwilioService for the current tenant.
     */
    public function forCurrentTenant(): TwilioService
    {
        return $this->create($this->currentTenantId);
    }
}
