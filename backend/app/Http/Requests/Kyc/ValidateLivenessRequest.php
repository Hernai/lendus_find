<?php

namespace App\Http\Requests\Kyc;

use App\Http\Requests\Traits\ApiFormRequest;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Form Request for liveness validation.
 */
class ValidateLivenessRequest extends FormRequest
{
    use ApiFormRequest;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'face_image' => 'required|string', // Base64 encoded face/selfie image
        ];
    }

    public function messages(): array
    {
        return [
            'face_image.required' => 'La imagen del rostro es requerida',
            'face_image.string' => 'La imagen del rostro debe estar en formato base64',
        ];
    }
}
