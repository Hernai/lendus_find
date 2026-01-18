<?php

namespace App\Http\Requests\Kyc;

use App\Http\Requests\Traits\ApiFormRequest;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Form Request for OFAC & UN sanctions check.
 */
class CheckOfacRequest extends FormRequest
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
            'similarity' => 'nullable|integer|min:0|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre es requerido',
            'name.max' => 'El nombre no debe exceder 200 caracteres',
            'similarity.integer' => 'La similitud debe ser un número entero',
            'similarity.min' => 'La similitud mínima es 0',
            'similarity.max' => 'La similitud máxima es 100',
        ];
    }
}
