<?php

namespace App\Http\Requests\V2\Applicant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation for creating references.
 */
class StoreReferenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['PERSONAL', 'WORK'])],
            'first_name' => 'required|string|max:100',
            'last_name_1' => 'required|string|max:100',
            'last_name' => 'sometimes|string|max:100', // Alias
            'last_name_2' => 'nullable|string|max:100',
            'phone' => 'required|string|max:15|regex:/^[0-9]{10}$/',
            'email' => 'nullable|email|max:100',
            'relationship' => 'required|string|max:50',
            'years_known' => 'nullable|integer|min:0|max:100',
            'employer_name' => 'nullable|string|max:200',
            'job_title' => 'nullable|string|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            'type.required' => 'El tipo de referencia es requerido.',
            'type.in' => 'El tipo de referencia debe ser PERSONAL o WORK.',
            'first_name.required' => 'El nombre de la referencia es requerido.',
            'last_name_1.required' => 'El apellido de la referencia es requerido.',
            'phone.required' => 'El teléfono de la referencia es requerido.',
            'phone.regex' => 'El teléfono debe tener 10 dígitos.',
            'relationship.required' => 'La relación con la referencia es requerida.',
            'email.email' => 'El email debe tener un formato válido.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Map 'last_name' alias to 'last_name_1'
        if ($this->has('last_name') && !$this->has('last_name_1')) {
            $this->merge(['last_name_1' => $this->last_name]);
        }

        // Normalize phone (remove non-numeric characters)
        if ($this->has('phone')) {
            $phone = preg_replace('/[^0-9]/', '', $this->phone);
            $this->merge(['phone' => $phone]);
        }
    }
}
