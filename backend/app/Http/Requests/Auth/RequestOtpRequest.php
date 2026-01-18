<?php

namespace App\Http\Requests\Auth;

use App\Enums\OtpChannel;
use App\Http\Requests\Traits\ApiFormRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Form Request for OTP request.
 */
class RequestOtpRequest extends FormRequest
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
            'channel' => ['sometimes', Rule::in(OtpChannel::values())],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.required_without' => 'Se requiere teléfono o correo electrónico',
            'phone.regex' => 'El teléfono debe tener 10 dígitos numéricos',
            'email.required_without' => 'Se requiere correo electrónico o teléfono',
            'email.email' => 'El correo electrónico no es válido',
            'channel.in' => 'El canal de envío no es válido',
        ];
    }
}
