<?php

namespace App\Http\Requests\Kyc;

use App\Http\Requests\Traits\ApiFormRequest;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Form Request for PLD (Anti-Money Laundering) blacklists check.
 */
class CheckPldBlacklistsRequest extends FormRequest
{
    use ApiFormRequest;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:200',
            'curp' => 'nullable|string|size:18',
            'similarity' => 'nullable|integer|min:0|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre es requerido',
            'name.max' => 'El nombre no debe exceder 200 caracteres',
            'curp.size' => 'La CURP debe tener exactamente 18 caracteres',
            'similarity.integer' => 'La similitud debe ser un número entero',
            'similarity.min' => 'La similitud mínima es 0',
            'similarity.max' => 'La similitud máxima es 100',
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
