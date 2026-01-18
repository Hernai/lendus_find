<?php

namespace App\Http\Requests\Kyc;

use App\Http\Requests\Traits\ApiFormRequest;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Form Request for recording KYC verifications.
 */
class RecordVerificationsRequest extends FormRequest
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
            'verifications' => 'required|array',
            'verifications.*.field' => 'required|string',
            'verifications.*.value' => 'nullable',
            'verifications.*.method' => 'required|string',
            'verifications.*.verified' => 'boolean',
            'verifications.*.metadata' => 'nullable|array',
            'verifications.*.notes' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'applicant_id.required' => 'El ID del solicitante es requerido',
            'applicant_id.uuid' => 'El ID del solicitante debe ser un UUID válido',
            'verifications.required' => 'Las verificaciones son requeridas',
            'verifications.array' => 'Las verificaciones deben ser un arreglo',
            'verifications.*.field.required' => 'El campo de verificación es requerido',
            'verifications.*.method.required' => 'El método de verificación es requerido',
        ];
    }
}
