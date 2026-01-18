<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserType;
use App\Http\Requests\Traits\ApiFormRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Form Request for creating admin/staff users.
 */
class StoreUserRequest extends FormRequest
{
    use ApiFormRequest;

    public function authorize(): bool
    {
        return $this->user()?->canManageUsers() ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:100',
            'email' => 'required|email|unique:users,email',
            'phone' => 'nullable|string|size:10|unique:users,phone',
            'role' => ['required', Rule::in(UserType::staffValues())],
            'password' => 'nullable|string|min:8',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre es requerido',
            'name.max' => 'El nombre no debe exceder 100 caracteres',
            'email.required' => 'El correo electrónico es requerido',
            'email.email' => 'El correo electrónico no es válido',
            'email.unique' => 'Este correo electrónico ya está registrado',
            'phone.size' => 'El teléfono debe tener 10 dígitos',
            'phone.unique' => 'Este número de teléfono ya está registrado',
            'role.required' => 'El rol es requerido',
            'role.in' => 'El rol seleccionado no es válido',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Format phone before validation (remove non-digits)
        if ($this->has('phone') && $this->phone) {
            $this->merge([
                'phone' => preg_replace('/\D/', '', $this->phone),
            ]);
        }
    }
}
