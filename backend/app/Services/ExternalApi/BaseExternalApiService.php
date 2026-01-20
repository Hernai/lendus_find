<?php

namespace App\Services\ExternalApi;

use App\Models\ApiLog;
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
     * Context for API logging.
     */
    protected ?string $applicantId = null;
    protected ?string $entityType = null;
    protected ?string $entityId = null;
    protected ?string $applicationId = null;
    protected ?string $userId = null;

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
     * Set the applicant context for API logging (legacy).
     * @deprecated Use forEntity() instead
     */
    public function forApplicant(?string $applicantId): static
    {
        $this->applicantId = $applicantId;
        return $this;
    }

    /**
     * Set the entity context for API logging (Person or Company).
     */
    public function forEntity($entity): static
    {
        if ($entity) {
            $this->entityType = get_class($entity);
            $this->entityId = $entity->id;
        }
        return $this;
    }

    /**
     * Set the application context for API logging.
     */
    public function forApplication(?string $applicationId): static
    {
        $this->applicationId = $applicationId;
        return $this;
    }

    /**
     * Set the user context for API logging.
     */
    public function forUser(?string $userId): static
    {
        $this->userId = $userId;
        return $this;
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
     * Current request context for logging (populated by logRequest, used by logResponse).
     */
    protected array $currentRequestContext = [];

    /**
     * Log API request for debugging and auditing.
     */
    protected function logRequest(string $method, string $endpoint, array $data = []): void
    {
        // Store context for when response is logged
        $this->currentRequestContext = [
            'method' => $method,
            'endpoint' => $endpoint,
            'payload' => $data,
            'start_time' => microtime(true),
        ];

        Log::channel('external_api')->info("External API Request", [
            'provider' => $this->provider,
            'tenant_id' => $this->tenant->id,
            'method' => $method,
            'endpoint' => $endpoint,
            'sandbox' => $this->config?->is_sandbox ?? false,
        ]);
    }

    /**
     * Log API response and persist to api_logs table.
     */
    protected function logResponse(Response $response, string $endpoint, ?string $service = null): void
    {
        $level = $response->successful() ? 'info' : 'error';

        Log::channel('external_api')->{$level}("External API Response", [
            'provider' => $this->provider,
            'tenant_id' => $this->tenant->id,
            'endpoint' => $endpoint,
            'status' => $response->status(),
            'success' => $response->successful(),
        ]);

        // Persist to api_logs table
        $this->persistApiLog($response, $endpoint, $service);
    }

    /**
     * Persist API call to the api_logs table.
     */
    protected function persistApiLog(Response $response, string $endpoint, ?string $service = null): void
    {
        try {
            $context = $this->currentRequestContext;
            $durationMs = isset($context['start_time'])
                ? (int) ((microtime(true) - $context['start_time']) * 1000)
                : null;

            // Determine service name from endpoint if not provided
            $serviceName = $service ?? $this->extractServiceFromEndpoint($endpoint);

            // Get full URL
            $fullUrl = $this->getBaseUrl() . '/' . ltrim($endpoint, '/');

            // Parse response body
            $responseBody = $response->json() ?? ['raw' => substr($response->body(), 0, 5000)];

            // Determine error info
            $success = $response->successful();
            $errorCode = null;
            $errorMessage = null;

            if (!$success) {
                $errorCode = (string) $response->status();
                $errorMessage = $responseBody['mensaje'] ?? $responseBody['message'] ?? $responseBody['error'] ?? 'HTTP ' . $response->status();
            }

            // Create the log entry
            ApiLog::withoutGlobalScopes()->create([
                'tenant_id' => $this->tenant->id,
                'applicant_id' => $this->applicantId,
                'entity_type' => $this->entityType,
                'entity_id' => $this->entityId,
                'application_id' => $this->applicationId,
                'user_id' => $this->userId,
                'provider' => strtoupper($this->provider),
                'service' => $serviceName,
                'endpoint' => $fullUrl,
                'method' => strtoupper($context['method'] ?? 'POST'),
                'request_headers' => ApiLog::maskSensitiveData($this->getDefaultHeaders()),
                'request_payload' => ApiLog::maskSensitiveData($context['payload'] ?? []),
                'response_status' => $response->status(),
                'response_headers' => $response->headers(),
                'response_body' => ApiLog::maskSensitiveData($responseBody),
                'success' => $success,
                'error_code' => $errorCode,
                'error_message' => $errorMessage,
                'duration_ms' => $durationMs,
                'metadata' => [
                    'sandbox' => $this->config?->is_sandbox ?? false,
                ],
                'ip_address' => $this->getSafeRequestIp(),
                'user_agent' => $this->getSafeUserAgent(),
            ]);

            // Clear context
            $this->currentRequestContext = [];
        } catch (\Exception $e) {
            // Don't let logging failures break the application
            Log::error('Failed to persist API log', [
                'provider' => $this->provider,
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Extract service name from endpoint.
     */
    protected function extractServiceFromEndpoint(string $endpoint): string
    {
        // Remove leading slashes and version prefixes
        $clean = preg_replace('/^\/?([a-z]+\/)?v\d+\//', '', $endpoint);

        // Take first two path segments
        $parts = explode('/', $clean);
        $service = implode('_', array_slice($parts, 0, 2));

        return $service ?: $endpoint;
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

    // =====================================================
    // Request Context Helpers
    // =====================================================

    /**
     * Get request IP safely (works in Jobs/CLI context).
     */
    protected function getSafeRequestIp(): ?string
    {
        try {
            return request()?->ip();
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get user agent safely (works in Jobs/CLI context).
     */
    protected function getSafeUserAgent(): ?string
    {
        try {
            return request()?->userAgent();
        } catch (\Exception $e) {
            return null;
        }
    }
}
