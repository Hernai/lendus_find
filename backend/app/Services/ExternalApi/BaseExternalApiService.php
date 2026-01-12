<?php

namespace App\Services\ExternalApi;

use App\Models\Tenant;
use App\Models\TenantApiConfig;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

abstract class BaseExternalApiService
{
    protected Tenant $tenant;
    protected ?TenantApiConfig $config = null;
    protected string $provider;
    protected string $serviceType;

    /**
     * Base URL for production environment.
     */
    protected string $baseUrl = '';

    /**
     * Base URL for sandbox environment.
     */
    protected string $sandboxUrl = '';

    /**
     * Default timeout in seconds.
     */
    protected int $timeout = 30;

    /**
     * Default retry times.
     */
    protected int $retries = 3;

    public function __construct(Tenant $tenant)
    {
        $this->tenant = $tenant;
        $this->loadConfig();
    }

    /**
     * Load API configuration for this tenant and provider.
     */
    protected function loadConfig(): void
    {
        $this->config = TenantApiConfig::where('tenant_id', $this->tenant->id)
            ->where('provider', $this->provider)
            ->where('service_type', $this->serviceType)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Check if the service is configured and active.
     */
    public function isConfigured(): bool
    {
        return $this->config !== null && $this->config->hasCredentials();
    }

    /**
     * Get the appropriate base URL based on sandbox mode.
     */
    protected function getBaseUrl(): string
    {
        if ($this->config?->is_sandbox && $this->sandboxUrl) {
            return $this->sandboxUrl;
        }

        return $this->baseUrl;
    }

    /**
     * Create HTTP client with default configuration.
     */
    protected function http(): PendingRequest
    {
        return Http::timeout($this->timeout)
            ->retry($this->retries, 100)
            ->withHeaders($this->getDefaultHeaders())
            ->baseUrl($this->getBaseUrl());
    }

    /**
     * Get default headers for API requests.
     */
    protected function getDefaultHeaders(): array
    {
        return [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];
    }

    /**
     * Log API request for debugging and auditing.
     */
    protected function logRequest(string $method, string $endpoint, array $data = []): void
    {
        Log::channel('external_api')->info("External API Request", [
            'provider' => $this->provider,
            'tenant_id' => $this->tenant->id,
            'method' => $method,
            'endpoint' => $endpoint,
            'sandbox' => $this->config?->is_sandbox ?? false,
        ]);
    }

    /**
     * Log API response.
     */
    protected function logResponse(Response $response, string $endpoint): void
    {
        $level = $response->successful() ? 'info' : 'error';

        Log::channel('external_api')->{$level}("External API Response", [
            'provider' => $this->provider,
            'tenant_id' => $this->tenant->id,
            'endpoint' => $endpoint,
            'status' => $response->status(),
            'success' => $response->successful(),
        ]);
    }

    /**
     * Handle API errors uniformly.
     */
    protected function handleError(Response $response, string $operation): array
    {
        $error = [
            'success' => false,
            'error' => true,
            'message' => 'Error en la operación: ' . $operation,
            'status_code' => $response->status(),
            'provider_error' => $response->json('message') ?? $response->json('error') ?? 'Unknown error',
        ];

        Log::channel('external_api')->error("External API Error", [
            'provider' => $this->provider,
            'tenant_id' => $this->tenant->id,
            'operation' => $operation,
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        return $error;
    }

    /**
     * Update last test results.
     */
    public function updateTestResult(bool $success, ?string $error = null): void
    {
        if ($this->config) {
            $this->config->update([
                'last_tested_at' => now(),
                'last_test_success' => $success,
                'last_test_error' => $error,
            ]);
        }
    }

    /**
     * Test the API connection.
     */
    abstract public function testConnection(): array;

    /**
     * Get provider name.
     */
    public function getProvider(): string
    {
        return $this->provider;
    }

    /**
     * Get service type.
     */
    public function getServiceType(): string
    {
        return $this->serviceType;
    }
}
