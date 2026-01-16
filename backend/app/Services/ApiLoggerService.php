<?php

namespace App\Services;

use App\Models\ApiLog;
use Illuminate\Support\Facades\Log;

class ApiLoggerService
{
    protected ?string $tenantId = null;
    protected ?string $applicantId = null;
    protected ?string $applicationId = null;
    protected ?string $userId = null;
    protected array $additionalMetadata = [];

    /**
     * Set the tenant context for logging.
     */
    public function forTenant(?string $tenantId): self
    {
        $this->tenantId = $tenantId;
        return $this;
    }

    /**
     * Set the applicant context for logging.
     */
    public function forApplicant(?string $applicantId): self
    {
        $this->applicantId = $applicantId;
        return $this;
    }

    /**
     * Set the application context for logging.
     */
    public function forApplication(?string $applicationId): self
    {
        $this->applicationId = $applicationId;
        return $this;
    }

    /**
     * Set the user context for logging.
     */
    public function forUser(?string $userId): self
    {
        $this->userId = $userId;
        return $this;
    }

    /**
     * Add additional metadata.
     */
    public function withMetadata(array $metadata): self
    {
        $this->additionalMetadata = array_merge($this->additionalMetadata, $metadata);
        return $this;
    }

    /**
     * Log an API call.
     *
     * @param string $provider The API provider (NUBARIUM, TWILIO, etc.)
     * @param string $service The specific service called (ine_ocr, curp_validate, etc.)
     * @param string $endpoint The full URL called
     * @param string $method HTTP method (GET, POST, etc.)
     * @param array $requestHeaders Request headers (will be masked)
     * @param array $requestPayload Request body (will be masked)
     * @param int|null $responseStatus HTTP response status code
     * @param array $responseHeaders Response headers
     * @param array|string|null $responseBody Response body (will be masked)
     * @param bool $success Whether the call was successful
     * @param string|null $errorCode Error code if failed
     * @param string|null $errorMessage Error message if failed
     * @param int|null $durationMs Duration in milliseconds
     * @param float|null $cost Cost of the API call
     * @return ApiLog
     */
    public function log(
        string $provider,
        string $service,
        string $endpoint,
        string $method = 'POST',
        array $requestHeaders = [],
        array $requestPayload = [],
        ?int $responseStatus = null,
        array $responseHeaders = [],
        array|string|null $responseBody = null,
        bool $success = false,
        ?string $errorCode = null,
        ?string $errorMessage = null,
        ?int $durationMs = null,
        ?float $cost = null
    ): ApiLog {
        try {
            // Mask sensitive data
            $maskedRequestHeaders = ApiLog::maskSensitiveData($requestHeaders);
            $maskedRequestPayload = ApiLog::maskSensitiveData($requestPayload);

            // Handle response body (could be string or array)
            $maskedResponseBody = null;
            if ($responseBody !== null) {
                if (is_string($responseBody)) {
                    $decoded = json_decode($responseBody, true);
                    $maskedResponseBody = $decoded ? ApiLog::maskSensitiveData($decoded) : ['raw' => substr($responseBody, 0, 10000)];
                } else {
                    $maskedResponseBody = ApiLog::maskSensitiveData($responseBody);
                }
            }

            // Get request context
            $ipAddress = request()->ip();
            $userAgent = request()->userAgent();

            // Merge additional metadata
            $metadata = $this->additionalMetadata;

            // Create the log entry (bypass tenant scope for logging)
            return ApiLog::withoutGlobalScopes()->create([
                'tenant_id' => $this->tenantId,
                'applicant_id' => $this->applicantId,
                'application_id' => $this->applicationId,
                'user_id' => $this->userId,
                'provider' => $provider,
                'service' => $service,
                'endpoint' => $endpoint,
                'method' => strtoupper($method),
                'request_headers' => $maskedRequestHeaders,
                'request_payload' => $maskedRequestPayload,
                'response_status' => $responseStatus,
                'response_headers' => $responseHeaders,
                'response_body' => $maskedResponseBody,
                'success' => $success,
                'error_code' => $errorCode,
                'error_message' => $errorMessage,
                'duration_ms' => $durationMs,
                'cost' => $cost,
                'metadata' => $metadata ?: null,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
            ]);
        } catch (\Exception $e) {
            // Don't let logging failures break the application
            Log::error('Failed to log API call', [
                'provider' => $provider,
                'service' => $service,
                'error' => $e->getMessage(),
            ]);

            // Return a non-persisted model to maintain fluent interface
            return new ApiLog([
                'provider' => $provider,
                'service' => $service,
                'success' => $success,
            ]);
        }
    }

    /**
     * Helper method to wrap an API call and automatically log it.
     *
     * @param string $provider
     * @param string $service
     * @param string $endpoint
     * @param callable $apiCall Function that makes the API call, should return ['status' => int, 'headers' => array, 'body' => mixed]
     * @param string $method
     * @param array $requestHeaders
     * @param array $requestPayload
     * @return array ['log' => ApiLog, 'response' => mixed]
     */
    public function wrapCall(
        string $provider,
        string $service,
        string $endpoint,
        callable $apiCall,
        string $method = 'POST',
        array $requestHeaders = [],
        array $requestPayload = []
    ): array {
        $startTime = microtime(true);
        $success = false;
        $errorCode = null;
        $errorMessage = null;
        $responseStatus = null;
        $responseHeaders = [];
        $responseBody = null;

        try {
            $result = $apiCall();

            $responseStatus = $result['status'] ?? null;
            $responseHeaders = $result['headers'] ?? [];
            $responseBody = $result['body'] ?? null;
            $success = ($responseStatus >= 200 && $responseStatus < 300);

            if (!$success && isset($result['error'])) {
                $errorMessage = $result['error'];
            }
        } catch (\Exception $e) {
            $errorCode = 'EXCEPTION';
            $errorMessage = $e->getMessage();
            $responseBody = ['exception' => $e->getMessage()];
        }

        $durationMs = (int) ((microtime(true) - $startTime) * 1000);

        $log = $this->log(
            $provider,
            $service,
            $endpoint,
            $method,
            $requestHeaders,
            $requestPayload,
            $responseStatus,
            $responseHeaders,
            $responseBody,
            $success,
            $errorCode,
            $errorMessage,
            $durationMs
        );

        // Reset context after logging
        $this->reset();

        return [
            'log' => $log,
            'response' => $responseBody,
            'status' => $responseStatus,
            'success' => $success,
        ];
    }

    /**
     * Reset the context (useful after logging).
     */
    public function reset(): self
    {
        $this->tenantId = null;
        $this->applicantId = null;
        $this->applicationId = null;
        $this->userId = null;
        $this->additionalMetadata = [];
        return $this;
    }

    /**
     * Create a new instance (for fresh context).
     */
    public static function make(): self
    {
        return new self();
    }
}
