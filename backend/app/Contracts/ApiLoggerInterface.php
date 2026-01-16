<?php

namespace App\Contracts;

use App\Models\ApiLog;

/**
 * Interface for API call logging.
 *
 * Provides consistent logging of external API calls
 * for auditing, debugging, and analytics purposes.
 */
interface ApiLoggerInterface
{
    /**
     * Set the context for subsequent log entries.
     *
     * @param int|null $tenantId
     * @param int|null $applicantId
     * @param int|null $applicationId
     * @param int|null $userId
     * @return static
     */
    public function setContext(
        ?int $tenantId = null,
        ?int $applicantId = null,
        ?int $applicationId = null,
        ?int $userId = null
    ): static;

    /**
     * Log an API call.
     *
     * @param string $provider Provider name (e.g., 'NUBARIUM', 'TWILIO')
     * @param string $service Service/endpoint name
     * @param string $method HTTP method
     * @param string $endpoint Full endpoint URL
     * @param array $requestHeaders Request headers (sensitive data will be masked)
     * @param array $requestPayload Request body (sensitive data will be masked)
     * @param int $responseStatus HTTP response status code
     * @param array $responseHeaders Response headers
     * @param mixed $responseBody Response body
     * @param bool $success Whether the call was successful
     * @param int $durationMs Duration in milliseconds
     * @param string|null $errorCode Error code if failed
     * @param string|null $errorMessage Error message if failed
     * @return ApiLog The created log entry
     */
    public function log(
        string $provider,
        string $service,
        string $method,
        string $endpoint,
        array $requestHeaders,
        array $requestPayload,
        int $responseStatus,
        array $responseHeaders,
        mixed $responseBody,
        bool $success,
        int $durationMs,
        ?string $errorCode = null,
        ?string $errorMessage = null
    ): ApiLog;

    /**
     * Reset the context after completing a series of calls.
     *
     * @return static
     */
    public function reset(): static;
}
