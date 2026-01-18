<?php

namespace App\Http\Requests\Kyc;

use App\Http\Requests\Traits\ApiFormRequest;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Form Request for CURP validation.
 */
class ValidateCurpRequest extends FormRequest
{
    use ApiFormRequest;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'curp' => 'required|string|size:18|alpha_num',
        ];
    }

    public function messages(): array
    {
        return [
            'curp.required' => 'La CURP es requerida',
            'curp.size' => 'La CURP debe tener exactamente 18 caracteres',
            'curp.alpha_num' => 'La CURP solo debe contener letras y números',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('curp') && $this->curp) {
            $this->merge([
                'curp' => strtoupper(trim($this->curp)),
            ]);
        }
    }
}
