<?php

namespace App\Http\Requests\Kyc;

use App\Http\Requests\Traits\ApiFormRequest;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Form Request for CURP lookup by personal data.
 */
class GetCurpRequest extends FormRequest
{
    use ApiFormRequest;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'nombres' => 'required|string|max:100',
            'apellido_paterno' => 'required|string|max:50',
            'apellido_materno' => 'nullable|string|max:50',
            'fecha_nacimiento' => 'required|date',
            'sexo' => 'required|string|in:H,M,HOMBRE,MUJER,MASCULINO,FEMENINO',
            'entidad_nacimiento' => 'required|string|size:2',
        ];
    }

    public function messages(): array
    {
        return [
            'nombres.required' => 'El nombre es requerido',
            'nombres.max' => 'El nombre no debe exceder 100 caracteres',
            'apellido_paterno.required' => 'El apellido paterno es requerido',
            'apellido_paterno.max' => 'El apellido paterno no debe exceder 50 caracteres',
            'apellido_materno.max' => 'El apellido materno no debe exceder 50 caracteres',
            'fecha_nacimiento.required' => 'La fecha de nacimiento es requerida',
            'fecha_nacimiento.date' => 'La fecha de nacimiento no es válida',
            'sexo.required' => 'El sexo es requerido',
            'sexo.in' => 'El sexo debe ser H (Hombre) o M (Mujer)',
            'entidad_nacimiento.required' => 'La entidad de nacimiento es requerida',
            'entidad_nacimiento.size' => 'La entidad de nacimiento debe tener 2 caracteres',
        ];
    }
}
