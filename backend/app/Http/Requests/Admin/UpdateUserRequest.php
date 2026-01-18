<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserType;
use App\Http\Requests\Traits\ApiFormRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Form Request for updating admin/staff users.
 */
class UpdateUserRequest extends FormRequest
{
    use ApiFormRequest;

    public function authorize(): bool
    {
        return $this->user()?->canManageUsers() ?? false;
    }

    public function rules(): array
    {
        $userId = $this->route('user')?->id ?? $this->route('user');

        return [
            'name' => 'sometimes|string|max:100',
            'email' => 'sometimes|email|unique:users,email,' . $userId,
            'phone' => 'nullable|string|size:10|unique:users,phone,' . $userId,
            'role' => ['sometimes', Rule::in(UserType::staffValues())],
            'password' => 'nullable|string|min:8',
            'is_active' => 'sometimes|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'name.max' => 'El nombre no debe exceder 100 caracteres',
            'email.email' => 'El correo electrónico no es válido',
            'email.unique' => 'Este correo electrónico ya está registrado',
            'phone.size' => 'El teléfono debe tener 10 dígitos',
            'phone.unique' => 'Este número de teléfono ya está registrado',
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
