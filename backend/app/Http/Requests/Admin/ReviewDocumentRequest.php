<?php

namespace App\Http\Requests\Admin;

use App\Enums\DocumentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Form request for reviewing (approving/rejecting) a document.
 */
class ReviewDocumentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->canReviewDocuments();
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $validStatuses = [
            DocumentStatus::APPROVED->value,
            DocumentStatus::REJECTED->value,
        ];

        return [
            'status' => ['required', 'string', Rule::in($validStatuses)],
            'rejection_reason' => [
                'nullable',
                'string',
                Rule::in($this->getValidRejectionReasons()),
            ],
            'rejection_comment' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'status.required' => 'El estado es requerido.',
            'status.in' => 'El estado debe ser APPROVED o REJECTED.',
            'rejection_reason.in' => 'La razón de rechazo no es válida.',
            'rejection_comment.max' => 'El comentario no puede exceder 500 caracteres.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Require rejection_reason when status is REJECTED
            if (
                $this->input('status') === DocumentStatus::REJECTED->value
                && empty($this->input('rejection_reason'))
            ) {
                $validator->errors()->add(
                    'rejection_reason',
                    'Se requiere una razón de rechazo.'
                );
            }
        });
    }

    /**
     * Get valid rejection reasons.
     */
    protected function getValidRejectionReasons(): array
    {
        return [
            'ILLEGIBLE',
            'EXPIRED',
            'INCOMPLETE',
            'WRONG_DOCUMENT',
            'LOW_QUALITY',
            'DATA_MISMATCH',
            'OTHER',
        ];
    }
}
