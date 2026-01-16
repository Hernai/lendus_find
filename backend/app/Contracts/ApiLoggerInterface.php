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
     * Set the tenant context for logging.
     */
    public function forTenant(?string $tenantId): static;

    /**
     * Set the applicant context for logging.
     */
    public function forApplicant(?string $applicantId): static;

    /**
     * Set the application context for logging.
     */
    public function forApplication(?string $applicationId): static;

    /**
     * Set the user context for logging.
     */
    public function forUser(?string $userId): static;

    /**
     * Log an API call.
     *
     * @param string $provider Provider name (e.g., 'NUBARIUM', 'TWILIO')
     * @param string $service Service/endpoint name
     * @param string $endpoint Full endpoint URL
     * @param string $method HTTP method (default: POST)
     * @param array $requestHeaders Request headers
     * @param array $requestPayload Request body
     * @param int|null $responseStatus HTTP response status code
     * @param array $responseHeaders Response headers
     * @param array|string|null $responseBody Response body
     * @param bool $success Whether the call was successful
     * @param string|null $errorCode Error code if failed
     * @param string|null $errorMessage Error message if failed
     * @param int|null $durationMs Duration in milliseconds
     * @param float|null $cost Cost of the API call
     * @return ApiLog The created log entry
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
    ): ApiLog;

    /**
     * Reset the context after completing a series of calls.
     */
    public function reset(): static;
}
