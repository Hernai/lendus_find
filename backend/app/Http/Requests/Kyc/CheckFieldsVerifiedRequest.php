<?php

namespace App\Http\Requests\Kyc;

use App\Http\Requests\Traits\ApiFormRequest;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Form Request for checking if fields are verified.
 */
class CheckFieldsVerifiedRequest extends FormRequest
{
    use ApiFormRequest;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'applicant_id' => 'required|uuid',
            'fields' => 'required|array',
            'fields.*' => 'string',
        ];
    }

    public function messages(): array
    {
        return [
            'applicant_id.required' => 'El ID del solicitante es requerido',
            'applicant_id.uuid' => 'El ID del solicitante debe ser un UUID válido',
            'fields.required' => 'Los campos son requeridos',
            'fields.array' => 'Los campos deben ser un arreglo',
        ];
    }
}
