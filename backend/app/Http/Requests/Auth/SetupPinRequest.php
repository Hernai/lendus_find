<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\Traits\ApiFormRequest;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Form Request for PIN setup.
 */
class SetupPinRequest extends FormRequest
{
    use ApiFormRequest;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'pin' => 'required|string|digits:4',
            'pin_confirmation' => 'required|string|same:pin',
        ];
    }

    public function messages(): array
    {
        return [
            'pin.required' => 'El NIP es requerido',
            'pin.digits' => 'El NIP debe tener 4 dígitos',
            'pin_confirmation.required' => 'La confirmación del NIP es requerida',
            'pin_confirmation.same' => 'La confirmación del NIP no coincide',
        ];
    }
}
