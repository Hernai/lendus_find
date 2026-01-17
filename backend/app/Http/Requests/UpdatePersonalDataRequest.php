<?php

namespace App\Http\Requests;

use App\Enums\EducationLevel;
use App\Enums\Gender;
use App\Enums\MaritalStatus;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

/**
 * Form Request for updating applicant personal data.
 *
 * Used in Steps 1 & 2 of onboarding (personal info + identification).
 * Supports partial updates - all fields are optional.
 */
class UpdatePersonalDataRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Step 1: Personal data
            'first_name' => 'sometimes|string|max:100',
            'last_name_1' => 'nullable|string|max:100',
            'last_name_2' => 'nullable|string|max:100',
            // Legacy field mapping support
            'last_name' => 'nullable|string|max:100',
            'second_last_name' => 'nullable|string|max:100',
            'birth_date' => 'sometimes|date|before:-18 years',
            'gender' => ['sometimes', Rule::in(Gender::values())],
            'marital_status' => ['nullable', 'string', 'max:20'],
            'nationality' => 'sometimes|string|max:50',
            'birth_state' => 'nullable|string|max:50',
            'education_level' => ['nullable', 'string', 'max:50'],
            'dependents_count' => 'nullable|integer|min:0',

            // Step 2: Identification
            'curp' => ['nullable', 'string', 'size:18', 'regex:/^[A-Z]{4}\d{6}[HM][A-Z]{5}[A-Z\d]\d$/'],
            'rfc' => ['nullable', 'string', 'min:12', 'max:13', 'regex:/^[A-ZÑ&]{3,4}\d{6}[A-Z\d]{3}$/'],

            // INE fields
            'ine_clave' => ['nullable', 'string', 'max:20', 'regex:/^[A-Z0-9]{18}$/'],
            'ine_ocr' => ['nullable', 'string', 'max:15', 'regex:/^\d{13}$/'],
            'ine_folio' => ['nullable', 'string', 'max:25', 'regex:/^\d{9,20}$/'],

            // Passport fields
            'passport_number' => ['nullable', 'string', 'max:15'],
            'passport_issue_date' => 'nullable|date|before_or_equal:today',
            'passport_expiry_date' => 'nullable|date|after:today',

            // Contact
            'email' => 'nullable|email|max:255',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'birth_date.before' => 'Debes ser mayor de 18 años',
            'curp.size' => 'La CURP debe tener exactamente 18 caracteres',
            'curp.regex' => 'El formato de la CURP es inválido',
            'rfc.min' => 'El RFC debe tener al menos 12 caracteres',
            'rfc.max' => 'El RFC debe tener máximo 13 caracteres',
            'rfc.regex' => 'El formato del RFC es inválido',
            'ine_clave.regex' => 'La clave de elector debe tener 18 caracteres alfanuméricos',
            'ine_ocr.regex' => 'El número OCR debe tener 13 dígitos',
            'ine_folio.regex' => 'El folio debe tener entre 9 y 20 dígitos',
            'passport_issue_date.before_or_equal' => 'La fecha de emisión debe ser anterior o igual a hoy',
            'passport_expiry_date.after' => 'El pasaporte debe estar vigente',
            'email.email' => 'El correo electrónico no tiene un formato válido',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'first_name' => 'nombre',
            'last_name_1' => 'apellido paterno',
            'last_name_2' => 'apellido materno',
            'birth_date' => 'fecha de nacimiento',
            'gender' => 'género',
            'marital_status' => 'estado civil',
            'nationality' => 'nacionalidad',
            'birth_state' => 'estado de nacimiento',
            'education_level' => 'nivel educativo',
            'dependents_count' => 'número de dependientes',
            'curp' => 'CURP',
            'rfc' => 'RFC',
            'ine_clave' => 'clave de elector',
            'ine_ocr' => 'número OCR',
            'ine_folio' => 'folio INE',
            'passport_number' => 'número de pasaporte',
            'passport_issue_date' => 'fecha de emisión',
            'passport_expiry_date' => 'fecha de vencimiento',
            'email' => 'correo electrónico',
        ];
    }

    /**
     * Prepare the data for validation.
     * Normalize fields before validation.
     */
    protected function prepareForValidation(): void
    {
        $data = [];

        // Normalize CURP and RFC to uppercase
        if ($this->has('curp') && $this->curp) {
            $data['curp'] = strtoupper(trim($this->curp));
        }

        if ($this->has('rfc') && $this->rfc) {
            $data['rfc'] = strtoupper(trim($this->rfc));
        }

        // Normalize INE fields to uppercase
        if ($this->has('ine_clave') && $this->ine_clave) {
            $data['ine_clave'] = strtoupper(trim($this->ine_clave));
        }

        // Map legacy field names to canonical names
        if ($this->has('last_name') && !$this->has('last_name_1')) {
            $data['last_name_1'] = $this->last_name;
        }

        if ($this->has('second_last_name') && !$this->has('last_name_2')) {
            $data['last_name_2'] = $this->second_last_name;
        }

        // Normalize marital_status using enum
        if ($this->has('marital_status') && $this->marital_status) {
            $normalized = MaritalStatus::normalize($this->marital_status);
            if ($normalized) {
                $data['marital_status'] = $normalized->value;
            }
        }

        // Normalize education_level using enum
        if ($this->has('education_level') && $this->education_level) {
            $normalized = EducationLevel::normalize($this->education_level);
            if ($normalized) {
                $data['education_level'] = $normalized->value;
            }
        }

        if (!empty($data)) {
            $this->merge($data);
        }
    }

    /**
     * Handle a failed validation attempt.
     * Returns JSON response instead of redirect for API.
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json([
                'message' => 'Error de validación',
                'errors' => $validator->errors(),
            ], 422)
        );
    }
}
