<?php

namespace App\Http\Requests\V2\Applicant;

use App\Enums\EducationLevel;
use App\Enums\Gender;
use App\Enums\MaritalStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation for personal data updates.
 *
 * Handles normalization and Spanish error messages.
 */
class UpdatePersonalDataRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => 'sometimes|string|max:100',
            'last_name_1' => 'sometimes|string|max:100',
            'last_name' => 'sometimes|string|max:100', // Alias
            'last_name_2' => 'nullable|string|max:100',
            'second_last_name' => 'nullable|string|max:100', // Alias
            'birth_date' => 'sometimes|date|before:today',
            'birth_state' => 'nullable|string|max:5',
            'birth_country' => 'nullable|string|max:3',
            'gender' => ['sometimes', Rule::in(Gender::values())],
            'nationality' => 'nullable|string|max:3',
            'marital_status' => ['nullable', Rule::in(MaritalStatus::values())],
            'education_level' => ['nullable', Rule::in(EducationLevel::values())],
            'dependents_count' => 'nullable|integer|min:0|max:20',
        ];
    }

    public function messages(): array
    {
        return [
            'first_name.required' => 'El nombre es requerido.',
            'first_name.max' => 'El nombre no puede exceder 100 caracteres.',
            'last_name_1.required' => 'El apellido paterno es requerido.',
            'last_name_1.max' => 'El apellido paterno no puede exceder 100 caracteres.',
            'birth_date.date' => 'La fecha de nacimiento debe ser una fecha válida.',
            'birth_date.before' => 'La fecha de nacimiento debe ser anterior a hoy.',
            'gender.in' => 'El género seleccionado no es válido.',
            'marital_status.in' => 'El estado civil seleccionado no es válido.',
            'education_level.in' => 'El nivel educativo seleccionado no es válido.',
            'dependents_count.integer' => 'El número de dependientes debe ser un número entero.',
            'dependents_count.min' => 'El número de dependientes no puede ser negativo.',
            'dependents_count.max' => 'El número de dependientes no puede exceder 20.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Normalize gender
        if ($this->has('gender') && $this->gender) {
            $this->merge(['gender' => strtoupper($this->gender)]);
        }

        // Normalize marital status
        if ($this->has('marital_status') && $this->marital_status) {
            $normalized = MaritalStatus::normalize($this->marital_status);
            if ($normalized) {
                $this->merge(['marital_status' => $normalized->value]);
            }
        }

        // Normalize education level
        if ($this->has('education_level') && $this->education_level) {
            $normalized = EducationLevel::normalize($this->education_level);
            if ($normalized) {
                $this->merge(['education_level' => $normalized->value]);
            }
        }
    }
}
