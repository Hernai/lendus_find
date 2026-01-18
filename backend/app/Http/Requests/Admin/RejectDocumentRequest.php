<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Traits\ApiFormRequest;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Form Request for rejecting a document.
 */
class RejectDocumentRequest extends FormRequest
{
    use ApiFormRequest;

    public function authorize(): bool
    {
        return $this->user()?->canReviewDocuments() ?? false;
    }

    public function rules(): array
    {
        return [
            'reason' => 'required|string|max:50',
            'comment' => 'nullable|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'La razón de rechazo es requerida',
            'reason.max' => 'La razón no debe exceder 50 caracteres',
            'comment.max' => 'El comentario no debe exceder 500 caracteres',
        ];
    }
}
