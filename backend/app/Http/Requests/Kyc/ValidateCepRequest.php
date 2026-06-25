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
        // Campos que exige BANXICO (/banxico/v2/valida_cep).
        return [
            'tipo_criterio' => 'nullable|in:T,R',
            'clave_rastreo' => 'required|string|max:50',
            'fecha_pago' => 'required|date',
            'institucion_emisora' => 'required|string|max:10',
            'institucion_receptora' => 'required|string|max:10',
            'cuenta_beneficiaria' => 'required|string|max:20',
            'monto' => 'required|numeric|min:0.01',
        ];
    }

    public function messages(): array
    {
        return [
            'tipo_criterio.in' => 'El tipo de criterio debe ser T (clave de rastreo) o R (referencia)',
            'clave_rastreo.required' => 'La clave de rastreo es requerida',
            'clave_rastreo.max' => 'La clave de rastreo no debe exceder 50 caracteres',
            'fecha_pago.required' => 'La fecha de pago es requerida',
            'fecha_pago.date' => 'La fecha de pago no es válida',
            'institucion_emisora.required' => 'La institución emisora es requerida',
            'institucion_receptora.required' => 'La institución receptora es requerida',
            'cuenta_beneficiaria.required' => 'La cuenta beneficiaria es requerida',
            'cuenta_beneficiaria.max' => 'La cuenta beneficiaria no debe exceder 20 caracteres',
            'monto.required' => 'El monto es requerido',
            'monto.numeric' => 'El monto debe ser numérico',
            'monto.min' => 'El monto debe ser mayor a 0',
        ];
    }
}
