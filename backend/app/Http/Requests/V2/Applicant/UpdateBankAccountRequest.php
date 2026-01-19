<?php

namespace App\Http\Requests\V2\Applicant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation for bank account updates.
 */
class UpdateBankAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'bank_name' => 'required|string|max:100',
            'bank_code' => 'nullable|string|max:10',
            'clabe' => 'required|string|size:18|regex:/^[0-9]{18}$/',
            'account_number' => 'nullable|string|max:20',
            'card_number' => 'nullable|string|max:20',
            'holder_name' => 'required|string|max:200',
            'account_type' => ['nullable', Rule::in(['DEBIT', 'PAYROLL', 'SAVINGS', 'CHECKING'])],
        ];
    }

    public function messages(): array
    {
        return [
            'bank_name.required' => 'El nombre del banco es requerido.',
            'bank_name.max' => 'El nombre del banco no puede exceder 100 caracteres.',
            'clabe.required' => 'La CLABE es requerida.',
            'clabe.size' => 'La CLABE debe tener exactamente 18 dígitos.',
            'clabe.regex' => 'La CLABE debe contener solo números.',
            'holder_name.required' => 'El nombre del titular es requerido.',
            'holder_name.max' => 'El nombre del titular no puede exceder 200 caracteres.',
            'account_type.in' => 'El tipo de cuenta seleccionado no es válido.',
        ];
    }
}
