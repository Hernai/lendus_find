<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\Traits\ApiFormRequest;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Form Request for PIN login.
 */
class LoginWithPinRequest extends FormRequest
{
    use ApiFormRequest;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'phone' => 'required|string|regex:/^[0-9]{10}$/',
            'pin' => 'required|string|digits:4',
        ];
    }

    public function messages(): array
    {
        return [
            'phone.required' => 'El teléfono es requerido',
            'phone.regex' => 'El teléfono debe tener 10 dígitos numéricos',
            'pin.required' => 'El NIP es requerido',
            'pin.digits' => 'El NIP debe tener 4 dígitos',
        ];
    }
}
