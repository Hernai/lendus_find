<?php

namespace App\Http\Requests\Kyc;

use App\Http\Requests\Traits\ApiFormRequest;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Form Request for SPEI CEP (payment proof) validation.
 */
class ValidateCepRequest extends FormRequest
{
    use ApiFormRequest;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'clave_rastreo' => 'required|string|max:50',
            'fecha_operacion' => 'required|date',
            'monto' => 'required|numeric|min:0.01',
            'cuenta_beneficiario' => 'nullable|string|max:20',
            'cuenta_ordenante' => 'nullable|string|max:20',
        ];
    }

    public function messages(): array
    {
        return [
            'clave_rastreo.required' => 'La clave de rastreo es requerida',
            'clave_rastreo.max' => 'La clave de rastreo no debe exceder 50 caracteres',
            'fecha_operacion.required' => 'La fecha de operación es requerida',
            'fecha_operacion.date' => 'La fecha de operación no es válida',
            'monto.required' => 'El monto es requerido',
            'monto.numeric' => 'El monto debe ser numérico',
            'monto.min' => 'El monto debe ser mayor a 0',
            'cuenta_beneficiario.max' => 'La cuenta del beneficiario no debe exceder 20 caracteres',
            'cuenta_ordenante.max' => 'La cuenta del ordenante no debe exceder 20 caracteres',
        ];
    }
}
