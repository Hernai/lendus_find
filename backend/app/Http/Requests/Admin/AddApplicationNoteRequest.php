<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Traits\ApiFormRequest;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Form Request for adding a note to an application.
 */
class AddApplicationNoteRequest extends FormRequest
{
    use ApiFormRequest;

    public function authorize(): bool
    {
        return $this->user()?->isStaff() ?? false;
    }

    public function rules(): array
    {
        return [
            'content' => 'required|string|max:2000',
            'is_internal' => 'sometimes|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'content.required' => 'El contenido de la nota es requerido',
            'content.max' => 'La nota no debe exceder 2000 caracteres',
            'is_internal.boolean' => 'El campo interno debe ser verdadero o falso',
        ];
    }
}
