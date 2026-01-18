<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\Traits\ApiFormRequest;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Form Request for checking if user exists.
 */
class CheckUserRequest extends FormRequest
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
        ];
    }

    public function messages(): array
    {
        return [
            'phone.required' => 'El teléfono es requerido',
            'phone.regex' => 'El teléfono debe tener 10 dígitos numéricos',
        ];
    }
}
