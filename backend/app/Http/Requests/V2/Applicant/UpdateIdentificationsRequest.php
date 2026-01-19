<?php

namespace App\Http\Requests\V2\Applicant;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation for identification updates (CURP, RFC, INE, Passport).
 */
class UpdateIdentificationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'curp' => 'sometimes|string|size:18|regex:/^[A-Z]{4}[0-9]{6}[HM][A-Z]{5}[0-9A-Z]{2}$/i',
            'rfc' => 'sometimes|string|min:12|max:13|regex:/^[A-Z&Ñ]{3,4}[0-9]{6}[A-Z0-9]{3}$/i',
            'ine_clave' => 'nullable|string|max:20',
            'ine_ocr' => 'nullable|string|max:20',
            'ine_folio' => 'nullable|string|max:20',
            'ine_expiration' => 'nullable|date|after:today',
            'passport_number' => 'nullable|string|max:20',
            'passport_issue_date' => 'nullable|date|before:today',
            'passport_expiry_date' => 'nullable|date|after:today',
        ];
    }

    public function messages(): array
    {
        return [
            'curp.size' => 'El CURP debe tener exactamente 18 caracteres.',
            'curp.regex' => 'El formato del CURP no es válido.',
            'rfc.min' => 'El RFC debe tener al menos 12 caracteres.',
            'rfc.max' => 'El RFC no puede exceder 13 caracteres.',
            'rfc.regex' => 'El formato del RFC no es válido.',
            'ine_expiration.after' => 'La fecha de expiración del INE debe ser futura.',
            'passport_issue_date.before' => 'La fecha de emisión del pasaporte debe ser anterior a hoy.',
            'passport_expiry_date.after' => 'La fecha de expiración del pasaporte debe ser futura.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Normalize CURP to uppercase
        if ($this->has('curp') && $this->curp) {
            $this->merge(['curp' => strtoupper(trim($this->curp))]);
        }

        // Normalize RFC to uppercase
        if ($this->has('rfc') && $this->rfc) {
            $this->merge(['rfc' => strtoupper(trim($this->rfc))]);
        }
    }
}
