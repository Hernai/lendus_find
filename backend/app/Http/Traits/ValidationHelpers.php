<?php

namespace App\Http\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;

/**
 * Shared validation helper methods for API controllers.
 *
 * Provides standardized validation responses to avoid code duplication
 * across controllers (DRY principle).
 */
trait ValidationHelpers
{
    /**
     * Validate request data and return error response if validation fails.
     *
     * @param array $data The data to validate
     * @param array $rules The validation rules
     * @param array $messages Custom error messages (optional)
     * @return ValidatorContract|JsonResponse Returns validator on success, JsonResponse on failure
     */
    protected function validateRequest(array $data, array $rules, array $messages = []): ValidatorContract|JsonResponse
    {
        $validator = Validator::make($data, $rules, $messages);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        return $validator;
    }

    /**
     * Create a standardized validation error response.
     *
     * @param ValidatorContract $validator The failed validator
     * @return JsonResponse
     */
    protected function validationErrorResponse(ValidatorContract $validator): JsonResponse
    {
        return response()->json([
            'message' => 'Validation error',
            'errors' => $validator->errors()
        ], 422);
    }

    /**
     * Create a standardized not found error response.
     *
     * @param string $resource The resource type that was not found
     * @return JsonResponse
     */
    protected function notFoundResponse(string $resource = 'Resource'): JsonResponse
    {
        return response()->json([
            'message' => "{$resource} not found"
        ], 404);
    }

    /**
     * Create a standardized unauthorized error response.
     *
     * @param string $message Custom message (optional)
     * @return JsonResponse
     */
    protected function unauthorizedResponse(string $message = 'Unauthorized'): JsonResponse
    {
        return response()->json([
            'message' => $message
        ], 401);
    }

    /**
     * Create a standardized forbidden error response.
     *
     * @param string $message Custom message (optional)
     * @return JsonResponse
     */
    protected function forbiddenResponse(string $message = 'Forbidden'): JsonResponse
    {
        return response()->json([
            'message' => $message
        ], 403);
    }

    /**
     * Create a standardized success response.
     *
     * @param string $message Success message
     * @param array $data Additional data to include
     * @param int $status HTTP status code
     * @return JsonResponse
     */
    protected function successResponse(string $message, array $data = [], int $status = 200): JsonResponse
    {
        return response()->json(array_merge([
            'message' => $message
        ], $data), $status);
    }

    /**
     * Create a standardized error response.
     *
     * @param string $message Error message
     * @param int $status HTTP status code
     * @param array $errors Additional error details
     * @return JsonResponse
     */
    protected function errorResponse(string $message, int $status = 400, array $errors = []): JsonResponse
    {
        $response = ['message' => $message];

        if (!empty($errors)) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $status);
    }

    /**
     * Check if validation result is an error response.
     *
     * @param mixed $result Result from validateRequest
     * @return bool
     */
    protected function isValidationError(mixed $result): bool
    {
        return $result instanceof JsonResponse;
    }
}
