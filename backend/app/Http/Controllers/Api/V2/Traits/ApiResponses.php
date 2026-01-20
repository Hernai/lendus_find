<?php

namespace App\Http\Controllers\Api\V2\Traits;

use Illuminate\Http\JsonResponse;

/**
 * Standardized API responses for V2 endpoints.
 *
 * All V2 API responses should follow this format:
 *
 * Success: { success: true, data: T, message?: string }
 * Error:   { success: false, error: string, message: string, errors?: Record<string, string[]> }
 *
 * This trait provides helper methods to ensure consistency across all V2 endpoints.
 */
trait ApiResponses
{
    /**
     * Return a success response with data.
     *
     * @param mixed $data The response data
     * @param string|null $message Optional success message
     * @param int $status HTTP status code (default 200)
     */
    protected function success(mixed $data = null, ?string $message = null, int $status = 200): JsonResponse
    {
        $response = [
            'success' => true,
        ];

        if ($data !== null) {
            $response['data'] = $data;
        }

        if ($message !== null) {
            $response['message'] = $message;
        }

        return response()->json($response, $status);
    }

    /**
     * Return a created response (201).
     *
     * @param mixed $data The created resource data
     * @param string|null $message Optional success message
     */
    protected function created(mixed $data = null, ?string $message = null): JsonResponse
    {
        return $this->success($data, $message, 201);
    }

    /**
     * Return an error response.
     *
     * @param string $error Error code (e.g., 'NOT_FOUND', 'VALIDATION_ERROR')
     * @param string $message Human-readable error message
     * @param int $status HTTP status code
     * @param array|null $errors Optional validation errors
     */
    protected function error(string $error, string $message, int $status = 400, ?array $errors = null): JsonResponse
    {
        $response = [
            'success' => false,
            'error' => $error,
            'message' => $message,
        ];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $status);
    }

    /**
     * Return a not found error response (404).
     *
     * @param string $message Human-readable error message
     */
    protected function notFound(string $message = 'Recurso no encontrado'): JsonResponse
    {
        return $this->error('NOT_FOUND', $message, 404);
    }

    /**
     * Return an unauthorized error response (401).
     *
     * @param string $message Human-readable error message
     */
    protected function unauthorized(string $message = 'No autorizado'): JsonResponse
    {
        return $this->error('UNAUTHORIZED', $message, 401);
    }

    /**
     * Return a forbidden error response (403).
     *
     * @param string $message Human-readable error message
     */
    protected function forbidden(string $message = 'Acceso denegado'): JsonResponse
    {
        return $this->error('FORBIDDEN', $message, 403);
    }

    /**
     * Return a validation error response (422).
     *
     * @param string $message Human-readable error message
     * @param array $errors Validation errors by field
     */
    protected function validationError(string $message, array $errors): JsonResponse
    {
        return $this->error('VALIDATION_ERROR', $message, 422, $errors);
    }

    /**
     * Return a bad request error response (400).
     *
     * @param string $error Error code
     * @param string $message Human-readable error message
     */
    protected function badRequest(string $error, string $message): JsonResponse
    {
        return $this->error($error, $message, 400);
    }

    /**
     * Return a service unavailable error response (503).
     *
     * @param string $message Human-readable error message
     */
    protected function serviceUnavailable(string $message = 'Servicio no disponible'): JsonResponse
    {
        return $this->error('SERVICE_UNAVAILABLE', $message, 503);
    }

    /**
     * Return a server error response (500).
     *
     * @param string $message Human-readable error message
     */
    protected function serverError(string $message = 'Error interno del servidor'): JsonResponse
    {
        return $this->error('SERVER_ERROR', $message, 500);
    }
}
