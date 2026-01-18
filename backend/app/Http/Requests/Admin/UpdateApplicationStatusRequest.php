<?php

namespace App\Http\Requests\Admin;

use App\Enums\ApplicationStatus;
use App\Http\Requests\Traits\ApiFormRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Form request for updating application status.
 *
 * Validates status transitions and required fields based on the new status.
 */
class UpdateApplicationStatusRequest extends FormRequest
{
    use ApiFormRequest;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        $newStatus = $this->input('status');

        // Check if user can change status at all
        if (!$user->canChangeApplicationStatus()) {
            return false;
        }

        // Check if user can approve/reject (requires special permission)
        $approvalStatuses = [
            ApplicationStatus::APPROVED->value,
            ApplicationStatus::REJECTED->value,
        ];

        if (in_array($newStatus, $approvalStatuses) && !$user->canApproveRejectApplications()) {
            return false;
        }

        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $validStatuses = array_column(ApplicationStatus::cases(), 'value');

        return [
            'status' => ['required', 'string', Rule::in($validStatuses)],
            'reason' => ['nullable', 'string', 'max:500'],
            'internal_note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'status.required' => 'El estado es requerido.',
            'status.in' => 'El estado proporcionado no es válido.',
            'reason.max' => 'La razón no puede exceder 500 caracteres.',
            'internal_note.max' => 'La nota interna no puede exceder 2000 caracteres.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $this->validateStatusTransition($validator);
        });
    }

    /**
     * Validate that the status transition is allowed.
     */
    protected function validateStatusTransition($validator): void
    {
        $application = $this->route('application');
        $newStatus = $this->input('status');
        $currentStatus = $application->status->value;

        // Define allowed transitions
        $allowedTransitions = [
            ApplicationStatus::DRAFT->value => [
                ApplicationStatus::SUBMITTED->value,
                ApplicationStatus::CANCELLED->value,
            ],
            ApplicationStatus::SUBMITTED->value => [
                ApplicationStatus::IN_REVIEW->value,
                ApplicationStatus::DOCS_PENDING->value,
                ApplicationStatus::CANCELLED->value,
            ],
            ApplicationStatus::IN_REVIEW->value => [
                ApplicationStatus::DOCS_PENDING->value,
                ApplicationStatus::CORRECTIONS_PENDING->value,
                ApplicationStatus::APPROVED->value,
                ApplicationStatus::REJECTED->value,
                ApplicationStatus::CANCELLED->value,
            ],
            ApplicationStatus::DOCS_PENDING->value => [
                ApplicationStatus::IN_REVIEW->value,
                ApplicationStatus::CORRECTIONS_PENDING->value,
                ApplicationStatus::CANCELLED->value,
            ],
            ApplicationStatus::CORRECTIONS_PENDING->value => [
                ApplicationStatus::IN_REVIEW->value,
                ApplicationStatus::CANCELLED->value,
            ],
            ApplicationStatus::APPROVED->value => [
                ApplicationStatus::DISBURSED->value,
                ApplicationStatus::CANCELLED->value,
            ],
            ApplicationStatus::REJECTED->value => [],
            ApplicationStatus::DISBURSED->value => [
                ApplicationStatus::ACTIVE->value,
                ApplicationStatus::COMPLETED->value,
                ApplicationStatus::DEFAULT->value,
            ],
            ApplicationStatus::ACTIVE->value => [
                ApplicationStatus::COMPLETED->value,
                ApplicationStatus::DEFAULT->value,
            ],
            ApplicationStatus::COMPLETED->value => [],
            ApplicationStatus::DEFAULT->value => [],
            ApplicationStatus::CANCELLED->value => [],
        ];

        $allowed = $allowedTransitions[$currentStatus] ?? [];

        if (!in_array($newStatus, $allowed)) {
            $validator->errors()->add(
                'status',
                "No se puede cambiar el estado de '{$currentStatus}' a '{$newStatus}'."
            );
        }

        // Require reason for rejection
        if ($newStatus === ApplicationStatus::REJECTED->value && empty($this->input('reason'))) {
            $validator->errors()->add('reason', 'Se requiere una razón para rechazar la solicitud.');
        }
    }
}
