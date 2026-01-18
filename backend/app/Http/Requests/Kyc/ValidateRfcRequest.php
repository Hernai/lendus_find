<?php

namespace App\Http\Requests\Kyc;

use App\Http\Requests\Traits\ApiFormRequest;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Form Request for RFC validation.
 */
class ValidateRfcRequest extends FormRequest
{
    use ApiFormRequest;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'rfc' => 'required|string|min:12|max:13|alpha_num',
        ];
    }

    public function messages(): array
    {
        return [
            'rfc.required' => 'El RFC es requerido',
            'rfc.min' => 'El RFC debe tener al menos 12 caracteres',
            'rfc.max' => 'El RFC debe tener máximo 13 caracteres',
            'rfc.alpha_num' => 'El RFC solo debe contener letras y números',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('rfc') && $this->rfc) {
            $this->merge([
                'rfc' => strtoupper(trim($this->rfc)),
            ]);
        }
    }
}
