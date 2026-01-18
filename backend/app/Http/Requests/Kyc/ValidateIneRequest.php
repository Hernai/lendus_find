<?php

namespace App\Http\Requests\Kyc;

use App\Http\Requests\Traits\ApiFormRequest;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Form Request for INE validation with OCR.
 */
class ValidateIneRequest extends FormRequest
{
    use ApiFormRequest;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'front_image' => 'required|string', // Base64 encoded image
            'back_image' => 'nullable|string',  // Base64 encoded image (recommended)
            'validate_list' => 'nullable|boolean', // Whether to validate against INE list
        ];
    }

    public function messages(): array
    {
        return [
            'front_image.required' => 'La imagen frontal del INE es requerida',
            'front_image.string' => 'La imagen frontal debe estar en formato base64',
            'back_image.string' => 'La imagen trasera debe estar en formato base64',
        ];
    }
}
