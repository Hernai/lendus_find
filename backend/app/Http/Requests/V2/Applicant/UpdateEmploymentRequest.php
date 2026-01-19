<?php

namespace App\Http\Requests\V2\Applicant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation for employment updates.
 */
class UpdateEmploymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $employmentTypes = [
            'EMPLOYEE', 'SELF_EMPLOYED', 'FREELANCE', 'BUSINESS_OWNER',
            'RETIRED', 'UNEMPLOYED', 'STUDENT', 'OTHER'
        ];

        return [
            'employment_type' => ['required', Rule::in($employmentTypes)],
            'type' => ['sometimes', Rule::in($employmentTypes)], // Alias
            'company_name' => 'required_unless:employment_type,UNEMPLOYED,RETIRED,STUDENT|string|max:200',
            'employer_name' => 'sometimes|string|max:200', // Alias
            'position' => 'nullable|string|max:100',
            'job_title' => 'nullable|string|max:100', // Alias
            'department' => 'nullable|string|max:100',
            'work_phone' => 'nullable|string|max:15',
            'company_phone' => 'nullable|string|max:15', // Alias
            'employer_phone' => 'nullable|string|max:15', // Alias
            'company_address' => 'nullable|string|max:500',
            'employer_address' => 'nullable|string|max:500', // Alias
            'employer_rfc' => 'nullable|string|min:12|max:13',
            'monthly_income' => 'required|numeric|min:0',
            'payment_frequency' => ['nullable', Rule::in(['WEEKLY', 'BIWEEKLY', 'MONTHLY'])],
            'contract_type' => ['nullable', Rule::in(['INDEFINITE', 'FIXED_TERM', 'TEMPORARY', 'FREELANCE', 'INTERNSHIP', 'OTHER'])],
            'start_date' => 'nullable|date|before_or_equal:today',
            'seniority_years' => 'nullable|integer|min:0|max:60',
            'seniority_months' => 'nullable|integer|min:0|max:11',
            'years_employed' => 'nullable|integer|min:0|max:60', // Alias
            'months_employed' => 'nullable|integer|min:0|max:11', // Alias
        ];
    }

    public function messages(): array
    {
        return [
            'employment_type.required' => 'El tipo de empleo es requerido.',
            'employment_type.in' => 'El tipo de empleo seleccionado no es válido.',
            'company_name.required_unless' => 'El nombre de la empresa es requerido.',
            'company_name.max' => 'El nombre de la empresa no puede exceder 200 caracteres.',
            'monthly_income.required' => 'El ingreso mensual es requerido.',
            'monthly_income.numeric' => 'El ingreso mensual debe ser un número.',
            'monthly_income.min' => 'El ingreso mensual no puede ser negativo.',
            'start_date.before_or_equal' => 'La fecha de inicio no puede ser futura.',
            'payment_frequency.in' => 'La frecuencia de pago seleccionada no es válida.',
            'contract_type.in' => 'El tipo de contrato seleccionado no es válido.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Map 'type' alias to 'employment_type'
        if ($this->has('type') && !$this->has('employment_type')) {
            $this->merge(['employment_type' => $this->type]);
        }
    }
}
