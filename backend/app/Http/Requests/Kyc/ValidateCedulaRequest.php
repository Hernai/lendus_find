<?php

namespace App\Http\Requests\Kyc;

use App\Http\Requests\Traits\ApiFormRequest;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Form Request for professional license (Cédula Profesional) validation.
 */
class ValidateCedulaRequest extends FormRequest
{
    use ApiFormRequest;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'cedula' => 'required|string|max:20',
        ];
    }

    public function messages(): array
    {
        return [
            'cedula.required' => 'El número de cédula es requerido',
            'cedula.max' => 'El número de cédula no debe exceder 20 caracteres',
        ];
    }
}
