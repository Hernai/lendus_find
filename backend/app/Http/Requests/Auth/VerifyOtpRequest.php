<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\Traits\ApiFormRequest;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Form Request for OTP verification.
 */
class VerifyOtpRequest extends FormRequest
{
    use ApiFormRequest;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'phone' => 'required_without:email|string|regex:/^[0-9]{10}$/',
            'email' => 'required_without:phone|email',
            'code' => 'required|string|size:6',
        ];
    }

    public function messages(): array
    {
        return [
            'phone.required_without' => 'Se requiere teléfono o correo electrónico',
            'phone.regex' => 'El teléfono debe tener 10 dígitos numéricos',
            'email.required_without' => 'Se requiere correo electrónico o teléfono',
            'email.email' => 'El correo electrónico no es válido',
            'code.required' => 'El código es requerido',
            'code.size' => 'El código debe tener 6 dígitos',
        ];
    }
}
