<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\Traits\ApiFormRequest;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Form Request for PIN change.
 */
class ChangePinRequest extends FormRequest
{
    use ApiFormRequest;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'current_pin' => 'required|string|digits:4',
            'new_pin' => 'required|string|digits:4',
            'new_pin_confirmation' => 'required|string|same:new_pin',
        ];
    }

    public function messages(): array
    {
        return [
            'current_pin.required' => 'El NIP actual es requerido',
            'current_pin.digits' => 'El NIP actual debe tener 4 dígitos',
            'new_pin.required' => 'El nuevo NIP es requerido',
            'new_pin.digits' => 'El nuevo NIP debe tener 4 dígitos',
            'new_pin_confirmation.required' => 'La confirmación del nuevo NIP es requerida',
            'new_pin_confirmation.same' => 'La confirmación del nuevo NIP no coincide',
        ];
    }
}
