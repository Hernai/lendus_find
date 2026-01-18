<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Traits\ApiFormRequest;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Form Request for verifying a reference.
 */
class VerifyReferenceRequest extends FormRequest
{
    use ApiFormRequest;

    public function authorize(): bool
    {
        return $this->user()?->canVerifyReferences() ?? false;
    }

    public function rules(): array
    {
        return [
            'result' => 'required|in:VERIFIED,NOT_VERIFIED,NO_ANSWER',
            'notes' => 'nullable|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'result.required' => 'El resultado de verificación es requerido',
            'result.in' => 'El resultado debe ser VERIFIED, NOT_VERIFIED o NO_ANSWER',
            'notes.max' => 'Las notas no deben exceder 500 caracteres',
        ];
    }
}
