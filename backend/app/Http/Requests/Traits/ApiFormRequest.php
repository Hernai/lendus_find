<?php

namespace App\Http\Requests\Traits;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Trait for API Form Requests.
 *
 * Provides JSON error responses instead of redirects for API validation failures.
 * Use this trait in any Form Request that serves an API endpoint.
 */
trait ApiFormRequest
{
    /**
     * Handle a failed validation attempt.
     * Returns JSON response instead of redirect for API.
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json([
                'message' => 'Error de validación',
                'errors' => $validator->errors(),
            ], 422)
        );
    }
}
