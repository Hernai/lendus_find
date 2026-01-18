<?php

namespace App\Http\Requests\Kyc;

use App\Http\Requests\Traits\ApiFormRequest;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Form Request for face match validation.
 */
class ValidateFaceMatchRequest extends FormRequest
{
    use ApiFormRequest;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'selfie_image' => 'required|string', // Base64 encoded selfie
            'ine_image' => 'required|string',    // Base64 encoded INE front (with face)
            'threshold' => 'nullable|integer|min:50|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            'selfie_image.required' => 'La imagen de selfie es requerida',
            'selfie_image.string' => 'La imagen de selfie debe estar en formato base64',
            'ine_image.required' => 'La imagen del INE es requerida',
            'ine_image.string' => 'La imagen del INE debe estar en formato base64',
            'threshold.integer' => 'El umbral debe ser un número entero',
            'threshold.min' => 'El umbral mínimo es 50',
            'threshold.max' => 'El umbral máximo es 100',
        ];
    }
}
