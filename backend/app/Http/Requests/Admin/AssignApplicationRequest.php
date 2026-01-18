<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Traits\ApiFormRequest;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request for assigning an application to a staff member.
 *
 * Validates that the assigned user belongs to the same tenant.
 */
class AssignApplicationRequest extends FormRequest
{
    use ApiFormRequest;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->canAssignApplications();
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $tenant = $this->attributes->get('tenant');
        $tenantId = $tenant ? $tenant->id : null;

        return [
            // Validate user exists AND belongs to the same tenant
            'user_id' => [
                'required',
                'uuid',
                "exists:users,id,tenant_id,{$tenantId}",
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'user_id.required' => 'Se requiere especificar un usuario.',
            'user_id.uuid' => 'El ID de usuario no tiene un formato válido.',
            'user_id.exists' => 'El usuario especificado no existe o no pertenece a este tenant.',
        ];
    }
}
