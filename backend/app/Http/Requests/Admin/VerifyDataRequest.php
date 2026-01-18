<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Traits\ApiFormRequest;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Form Request for verifying applicant data fields.
 */
class VerifyDataRequest extends FormRequest
{
    use ApiFormRequest;

    /**
     * Valid fields that can be verified.
     */
    public const VALID_FIELDS = [
        'first_name',
        'last_name_1',
        'last_name_2',
        'curp',
        'rfc',
        'ine_clave',
        'birth_date',
        'phone',
        'email',
        'address',
        'employment',
    ];

    public function authorize(): bool
    {
        return $this->user()?->isStaff() ?? false;
    }

    public function rules(): array
    {
        return [
            'field' => 'required|in:' . implode(',', self::VALID_FIELDS),
            'action' => 'sometimes|in:verify,reject,unverify',
            'verified' => 'sometimes|boolean', // Legacy support
            'method' => 'sometimes|in:MANUAL,OTP,API,DOCUMENT,BUREAU',
            'notes' => 'nullable|string|max:500',
            'rejection_reason' => 'required_if:action,reject|nullable|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'field.required' => 'El campo es requerido',
            'field.in' => 'El campo seleccionado no es válido para verificación',
            'action.in' => 'La acción debe ser verify, reject o unverify',
            'method.in' => 'El método de verificación no es válido',
            'notes.max' => 'Las notas no deben exceder 500 caracteres',
            'rejection_reason.required_if' => 'La razón de rechazo es requerida',
            'rejection_reason.max' => 'La razón de rechazo no debe exceder 500 caracteres',
        ];
    }

    /**
     * Determine the action to perform based on request data.
     */
    public function determineAction(): string
    {
        if ($this->has('action')) {
            return $this->action;
        }

        // Legacy support: use 'verified' boolean
        $verified = $this->verified;
        if ($verified) {
            return 'verify';
        } elseif ($this->rejection_reason) {
            return 'reject';
        }

        return 'unverify';
    }
}
