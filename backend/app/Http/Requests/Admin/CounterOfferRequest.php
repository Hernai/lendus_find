<?php

namespace App\Http\Requests\Admin;

use App\Enums\PaymentFrequency;
use App\Http\Requests\Traits\ApiFormRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Form Request for creating a counter-offer.
 */
class CounterOfferRequest extends FormRequest
{
    use ApiFormRequest;

    public function authorize(): bool
    {
        return $this->user()?->isStaff() ?? false;
    }

    public function rules(): array
    {
        return [
            'amount' => 'required|numeric|min:1000',
            'term_months' => 'required|integer|min:1|max:120',
            'interest_rate' => 'required|numeric|min:0|max:100',
            'payment_frequency' => ['required', Rule::in(PaymentFrequency::values())],
            'reason' => 'nullable|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'amount.required' => 'El monto es requerido',
            'amount.numeric' => 'El monto debe ser un número',
            'amount.min' => 'El monto mínimo es $1,000',
            'term_months.required' => 'El plazo es requerido',
            'term_months.integer' => 'El plazo debe ser un número entero',
            'term_months.min' => 'El plazo mínimo es 1 mes',
            'term_months.max' => 'El plazo máximo es 120 meses',
            'interest_rate.required' => 'La tasa de interés es requerida',
            'interest_rate.numeric' => 'La tasa debe ser un número',
            'interest_rate.min' => 'La tasa mínima es 0%',
            'interest_rate.max' => 'La tasa máxima es 100%',
            'payment_frequency.required' => 'La frecuencia de pago es requerida',
            'payment_frequency.in' => 'La frecuencia de pago no es válida',
            'reason.max' => 'La razón no debe exceder 500 caracteres',
        ];
    }
}
