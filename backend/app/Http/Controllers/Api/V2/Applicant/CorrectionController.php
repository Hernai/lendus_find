<?php

namespace App\Http\Controllers\Api\V2\Applicant;

use App\Enums\DocumentStatus;
use App\Enums\EmploymentType;
use App\Http\Controllers\Api\V2\Traits\ApiResponses;
use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Document;
use App\Models\Person;
use App\Models\Address;
use App\Models\PersonEmployment;
use App\Services\ApplicantProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * V2 Applicant Correction Controller.
 *
 * Handles data corrections for rejected fields and documents.
 * Uses Person model and Application for V2 architecture.
 *
 * All endpoints are under /api/v2/applicant/corrections
 */
class CorrectionController extends Controller
{
    use ApiResponses;

    public function __construct(
        protected ApplicantProfileService $profileService
    ) {}

    /**
     * Get list of pending corrections (rejected fields and documents).
     *
     * GET /api/v2/applicant/corrections
     */
    public function index(Request $request): JsonResponse
    {
        $account = $request->user();
        $person = $this->profileService->getOrCreatePerson($account);

        // Get applications with CORRECTIONS_PENDING status
        $pendingApplications = Application::where('person_id', $person->id)
            ->where('status', Application::STATUS_CORRECTIONS_PENDING)
            ->get(['id', 'status', 'updated_at']);

        // Get rejected fields from verification_checklist of all pending applications
        $rejectedFields = $this->getRejectedFields($person, $pendingApplications);

        // Get rejected documents
        $rejectedDocuments = $this->getRejectedDocuments($person, $pendingApplications);

        // Get correction history
        $correctionHistory = $this->getCorrectionHistory($person, $pendingApplications);

        // Get current person data for form population
        $personData = $this->getPersonData($person);

        return $this->success([
            'rejected_fields' => $rejectedFields,
            'rejected_documents' => $rejectedDocuments,
            'correction_history' => $correctionHistory,
            'applicant_data' => $personData,
            'pending_applications' => $pendingApplications->map(fn($app) => [
                'id' => $app->id,
                'status' => $app->status,
                'updated_at' => $app->updated_at?->toIso8601String(),
            ]),
            'has_corrections_pending' => $rejectedFields->count() > 0 || $rejectedDocuments->count() > 0,
        ]);
    }

    /**
     * Submit a correction for a rejected field.
     *
     * POST /api/v2/applicant/corrections
     */
    public function submitCorrection(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'field_name' => 'required|string|max:100',
            'new_value' => 'required',
        ]);

        $account = $request->user();
        $person = $this->profileService->getOrCreatePerson($account);

        // Get all applications with CORRECTIONS_PENDING
        $pendingApplications = Application::where('person_id', $person->id)
            ->where('status', Application::STATUS_CORRECTIONS_PENDING)
            ->get();

        if ($pendingApplications->isEmpty()) {
            return $this->notFound('No hay correcciones pendientes');
        }

        $fieldName = $validated['field_name'];
        $newValue = $validated['new_value'];

        // Get old value and update the field
        $oldValue = $this->getFieldValue($person, $fieldName);

        try {
            DB::beginTransaction();

            // Update the person data
            $this->updatePersonField($person, $fieldName, $newValue);

            // Add to correction history in each pending application
            foreach ($pendingApplications as $application) {
                $this->addCorrectionToHistory($application, $fieldName, $oldValue, $newValue, $account);

                // Add timeline entry
                $application->addTimelineEntry('DATA_CORRECTED', [
                    'field_label' => $this->getFieldLabel($fieldName),
                    'old_value' => $this->formatValueForDisplay($oldValue),
                    'new_value' => $this->formatValueForDisplay($newValue),
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ], $account->id);
            }

            // Check if all corrections are done
            $this->checkAndUpdateApplicationStatus($person, $account);

            DB::commit();

            return $this->success([
                'field_name' => $fieldName,
                'status' => 'corrected',
            ], 'Corrección enviada correctamente');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to submit correction', [
                'error' => $e->getMessage(),
                'field_name' => $fieldName,
                'person_id' => $person->id,
            ]);

            return $this->serverError('Error al enviar la corrección');
        }
    }

    /**
     * Get correction details for a specific field.
     *
     * GET /api/v2/applicant/corrections/{fieldName}
     */
    public function show(Request $request, string $fieldName): JsonResponse
    {
        $account = $request->user();
        $person = $this->profileService->getOrCreatePerson($account);

        // Get pending applications
        $pendingApplications = Application::where('person_id', $person->id)
            ->where('status', Application::STATUS_CORRECTIONS_PENDING)
            ->get();

        // Find the rejection info for this field
        $rejectionInfo = null;
        foreach ($pendingApplications as $application) {
            $checklist = $application->verification_checklist ?? [];
            if (isset($checklist[$fieldName]) && ($checklist[$fieldName]['status'] ?? null) === 'REJECTED') {
                $rejectionInfo = $checklist[$fieldName];
                break;
            }
        }

        if (!$rejectionInfo) {
            return $this->notFound('Campo no encontrado o no rechazado');
        }

        return $this->success([
            'field_name' => $fieldName,
            'field_label' => $this->getFieldLabel($fieldName),
            'current_value' => $this->getFieldValue($person, $fieldName),
            'status' => 'REJECTED',
            'rejection_reason' => $rejectionInfo['reason'] ?? null,
            'rejected_at' => $rejectionInfo['rejected_at'] ?? null,
            'rejected_by' => $rejectionInfo['rejected_by'] ?? null,
        ]);
    }

    // =========================================================================
    // Helper Methods
    // =========================================================================

    /**
     * Get rejected fields from verification_checklist of pending applications.
     */
    private function getRejectedFields(Person $person, $pendingApplications): \Illuminate\Support\Collection
    {
        $rejectedFields = collect();

        foreach ($pendingApplications as $application) {
            $checklist = $application->verification_checklist ?? [];

            foreach ($checklist as $fieldName => $fieldData) {
                if (($fieldData['status'] ?? null) === 'REJECTED') {
                    // Avoid duplicates
                    if (!$rejectedFields->contains('field_name', $fieldName)) {
                        $rejectedFields->push([
                            'id' => $application->id . '_' . $fieldName,
                            'field_name' => $fieldName,
                            'field_label' => $this->getFieldLabel($fieldName),
                            'current_value' => $this->formatValueForDisplay($this->getFieldValue($person, $fieldName)),
                            'rejection_reason' => $fieldData['reason'] ?? null,
                            'rejected_at' => $fieldData['rejected_at'] ?? null,
                        ]);
                    }
                }
            }
        }

        return $rejectedFields;
    }

    /**
     * Get rejected documents.
     */
    private function getRejectedDocuments(Person $person, $pendingApplications): \Illuminate\Support\Collection
    {
        // Get rejected documents from Person
        $personDocuments = Document::where('documentable_type', Person::class)
            ->where('documentable_id', $person->id)
            ->where('status', DocumentStatus::REJECTED)
            ->whereNull('replaced_at')
            ->get();

        // Also get from applications
        $applicationIds = $pendingApplications->pluck('id');
        $appDocuments = Document::where('documentable_type', Application::class)
            ->whereIn('documentable_id', $applicationIds)
            ->where('status', DocumentStatus::REJECTED)
            ->whereNull('replaced_at')
            ->get();

        return $personDocuments->merge($appDocuments)->map(fn($doc) => [
            'id' => $doc->id,
            'application_id' => $doc->documentable_type === Application::class ? $doc->documentable_id : null,
            'type' => $doc->type,
            'type_label' => $this->getDocumentTypeLabel($doc->type),
            'name' => $doc->file_name,
            'rejection_reason' => $doc->rejection_reason,
            'rejected_at' => $doc->reviewed_at?->toIso8601String(),
        ]);
    }

    /**
     * Get correction history from applications.
     */
    private function getCorrectionHistory(Person $person, $pendingApplications): \Illuminate\Support\Collection
    {
        $history = collect();

        foreach ($pendingApplications as $application) {
            $metadata = $application->metadata ?? [];
            $corrections = $metadata['correction_history'] ?? [];

            foreach ($corrections as $entry) {
                $history->push([
                    'field_name' => $entry['field_name'] ?? null,
                    'field_label' => $this->getFieldLabel($entry['field_name'] ?? ''),
                    'old_value' => $entry['old_value'] ?? null,
                    'new_value' => $entry['new_value'] ?? null,
                    'rejection_reason' => $entry['rejection_reason'] ?? null,
                    'corrected_by' => $entry['corrected_by'] ?? null,
                    'corrected_at' => $entry['corrected_at'] ?? null,
                ]);
            }
        }

        return $history->sortByDesc('corrected_at')->values();
    }

    /**
     * Get current person data for form population.
     */
    private function getPersonData(Person $person): array
    {
        $person->load(['currentHomeAddress', 'currentEmployment', 'currentCurp', 'currentRfc']);

        $address = $person->currentHomeAddress;
        $employment = $person->currentEmployment;

        return [
            'first_name' => $person->first_name,
            'last_name_1' => $person->last_name_1,
            'last_name_2' => $person->last_name_2,
            'curp' => $person->curp,
            'rfc' => $person->rfc,
            'ine_clave' => $person->currentIne?->identifier_value,
            'birth_date' => $person->birth_date?->format('Y-m-d'),
            'phone' => $person->account?->phone,
            'email' => $person->account?->email,
            'address' => $address ? [
                'street' => $address->street,
                'ext_number' => $address->exterior_number,
                'int_number' => $address->interior_number,
                'neighborhood' => $address->neighborhood,
                'postal_code' => $address->postal_code,
                'municipality' => $address->municipality,
                'state' => $address->state,
            ] : null,
            'employment' => $employment ? [
                'type' => $employment->employment_type,
                'company_name' => $employment->employer_name,
                'position' => $employment->job_title,
                'monthly_income' => (float) ($employment->monthly_income ?? 0),
                'seniority_months' => ($employment->years_employed ?? 0) * 12 + ($employment->months_employed ?? 0),
            ] : [
                'type' => 'EMPLOYEE',
                'company_name' => '',
                'position' => '',
                'monthly_income' => 0,
                'seniority_months' => 0,
            ],
        ];
    }

    /**
     * Get field value from Person.
     */
    private function getFieldValue(Person $person, string $fieldName): mixed
    {
        $nameFields = ['first_name', 'last_name_1', 'last_name_2'];

        if (in_array($fieldName, $nameFields)) {
            return [
                'first_name' => $person->first_name,
                'last_name_1' => $person->last_name_1,
                'last_name_2' => $person->last_name_2,
            ];
        }

        return match ($fieldName) {
            'curp' => $person->curp,
            'rfc' => $person->rfc,
            'ine_clave', 'ine' => $person->currentIne?->identifier_value,
            'birth_date' => $person->birth_date?->format('Y-m-d'),
            'phone' => $person->account?->phone,
            'email' => $person->account?->email,
            'address' => $this->getAddressValue($person),
            'employment' => $this->getEmploymentValue($person),
            default => null,
        };
    }

    /**
     * Get address as array.
     */
    private function getAddressValue(Person $person): ?array
    {
        $address = $person->currentHomeAddress;
        if (!$address) {
            return null;
        }

        return [
            'street' => $address->street,
            'ext_number' => $address->exterior_number,
            'int_number' => $address->interior_number,
            'neighborhood' => $address->neighborhood,
            'postal_code' => $address->postal_code,
            'municipality' => $address->municipality,
            'state' => $address->state,
        ];
    }

    /**
     * Get employment as array.
     */
    private function getEmploymentValue(Person $person): ?array
    {
        $employment = $person->currentEmployment;
        if (!$employment) {
            return null;
        }

        return [
            'type' => $employment->employment_type,
            'company_name' => $employment->employer_name,
            'position' => $employment->job_title,
            'monthly_income' => $employment->monthly_income,
            'seniority_months' => ($employment->years_employed ?? 0) * 12 + ($employment->months_employed ?? 0),
        ];
    }

    /**
     * Update person field with new value.
     */
    private function updatePersonField(Person $person, string $fieldName, mixed $value): void
    {
        $nameFields = ['first_name', 'last_name_1', 'last_name_2'];

        if (in_array($fieldName, $nameFields)) {
            // Handle name update (could be object with all fields or single value)
            if (is_array($value)) {
                if (isset($value['first_name'])) {
                    $person->first_name = $value['first_name'];
                }
                if (isset($value['last_name_1'])) {
                    $person->last_name_1 = $value['last_name_1'];
                }
                if (isset($value['last_name_2'])) {
                    $person->last_name_2 = $value['last_name_2'];
                }
            } else {
                $person->$fieldName = $value;
            }
            $person->save();
            return;
        }

        switch ($fieldName) {
            case 'curp':
                $this->profileService->updateIdentifications($person, ['curp' => $value]);
                break;

            case 'rfc':
                $this->profileService->updateIdentifications($person, ['rfc' => $value]);
                break;

            case 'ine_clave':
            case 'ine':
                $this->profileService->updateIdentifications($person, ['ine_clave' => $value]);
                break;

            case 'birth_date':
                $person->birth_date = $value;
                $person->save();
                break;

            case 'phone':
                if ($person->account) {
                    $person->account->phone = $value;
                    $person->account->save();
                }
                break;

            case 'email':
                if ($person->account) {
                    $person->account->email = $value;
                    $person->account->save();
                }
                break;

            case 'address':
                if (is_array($value)) {
                    $this->profileService->updateAddress($person, $value);
                }
                break;

            case 'employment':
                if (is_array($value)) {
                    $this->updateEmployment($person, $value);
                }
                break;
        }
    }

    /**
     * Update employment with correction values.
     */
    private function updateEmployment(Person $person, array $value): void
    {
        $employment = $person->currentEmployment;

        if (!$employment) {
            // Create new employment if doesn't exist
            $employment = $person->employments()->create([
                'tenant_id' => $person->tenant_id,
                'is_current' => true,
            ]);
        }

        if (isset($value['type'])) {
            $employment->employment_type = $value['type'];
        }
        if (isset($value['company_name'])) {
            $employment->employer_name = $value['company_name'];
        }
        if (isset($value['position'])) {
            $employment->job_title = $value['position'];
        }
        if (isset($value['monthly_income'])) {
            $employment->monthly_income = $value['monthly_income'];
        }
        if (isset($value['seniority_months'])) {
            $months = (int) $value['seniority_months'];
            $employment->years_employed = intdiv($months, 12);
            $employment->months_employed = $months % 12;
        }

        $employment->save();
    }

    /**
     * Add correction to application history.
     */
    private function addCorrectionToHistory(Application $application, string $fieldName, mixed $oldValue, mixed $newValue, $account): void
    {
        // Update verification_checklist to mark as corrected
        $checklist = $application->verification_checklist ?? [];
        if (isset($checklist[$fieldName])) {
            $rejectionReason = $checklist[$fieldName]['reason'] ?? null;
            $checklist[$fieldName]['status'] = 'CORRECTED';
            $checklist[$fieldName]['corrected_at'] = now()->toIso8601String();
            $checklist[$fieldName]['corrected_by'] = $account->id;
        } else {
            $rejectionReason = null;
        }

        // Add to correction_history (stored in metadata)
        $metadata = $application->metadata ?? [];
        $history = $metadata['correction_history'] ?? [];
        $history[] = [
            'field_name' => $fieldName,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'rejection_reason' => $rejectionReason,
            'corrected_by' => [
                'id' => $account->id,
                'name' => $account->person?->full_name ?? 'Solicitante',
            ],
            'corrected_at' => now()->toIso8601String(),
        ];
        $metadata['correction_history'] = $history;

        $application->update([
            'verification_checklist' => $checklist,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Check if all corrections are done and update application status.
     */
    private function checkAndUpdateApplicationStatus(Person $person, $account): void
    {
        $pendingApplications = Application::where('person_id', $person->id)
            ->where('status', Application::STATUS_CORRECTIONS_PENDING)
            ->get();

        foreach ($pendingApplications as $application) {
            // Check if there are still rejected fields
            $checklist = $application->verification_checklist ?? [];
            $hasRejectedFields = collect($checklist)
                ->contains(fn($field) => ($field['status'] ?? null) === 'REJECTED');

            // Check if there are still rejected documents
            $hasRejectedDocs = Document::where(function ($query) use ($application, $person) {
                $query->where(function ($q) use ($person) {
                    $q->where('documentable_type', Person::class)
                        ->where('documentable_id', $person->id);
                })->orWhere(function ($q) use ($application) {
                    $q->where('documentable_type', Application::class)
                        ->where('documentable_id', $application->id);
                });
            })
                ->where('status', DocumentStatus::REJECTED)
                ->whereNull('replaced_at')
                ->exists();

            // If no more rejections, move to IN_REVIEW
            if (!$hasRejectedFields && !$hasRejectedDocs) {
                $application->changeStatus(
                    Application::STATUS_IN_REVIEW,
                    'Correcciones completadas',
                    $account->id
                );
            }
        }
    }

    /**
     * Get human-readable label for a field.
     */
    private function getFieldLabel(string $fieldName): string
    {
        $labels = [
            'first_name' => 'Nombre Completo',
            'last_name_1' => 'Nombre Completo',
            'last_name_2' => 'Nombre Completo',
            'curp' => 'CURP',
            'rfc' => 'RFC',
            'ine_clave' => 'Clave de Elector (INE)',
            'ine' => 'Clave de Elector (INE)',
            'birth_date' => 'Fecha de Nacimiento',
            'phone' => 'Teléfono',
            'email' => 'Correo Electrónico',
            'address' => 'Domicilio',
            'employment' => 'Información Laboral',
        ];

        return $labels[$fieldName] ?? $fieldName;
    }

    /**
     * Get human-readable label for a document type.
     */
    private function getDocumentTypeLabel(string $type): string
    {
        $labels = [
            'INE_FRONT' => 'INE (Frente)',
            'INE_BACK' => 'INE (Reverso)',
            'PROOF_OF_ADDRESS' => 'Comprobante de Domicilio',
            'INCOME_PROOF' => 'Comprobante de Ingresos',
            'BANK_STATEMENT' => 'Estado de Cuenta Bancario',
            'SELFIE' => 'Selfie',
            'SIGNATURE' => 'Firma',
        ];

        return $labels[$type] ?? $type;
    }

    /**
     * Format a value for display.
     */
    private function formatValueForDisplay(mixed $value): string
    {
        if ($value === null) {
            return '(vacío)';
        }

        if (is_array($value)) {
            // Name object
            if (isset($value['first_name']) || isset($value['last_name_1'])) {
                $parts = array_filter([
                    $value['first_name'] ?? null,
                    $value['last_name_1'] ?? null,
                    $value['last_name_2'] ?? null,
                ]);
                return implode(' ', $parts) ?: '(vacío)';
            }

            // Employment
            if (isset($value['company_name']) || isset($value['type'])) {
                $parts = [];
                if (!empty($value['type'])) {
                    $parts[] = EmploymentType::tryFrom($value['type'])?->label() ?? $value['type'];
                }
                if (!empty($value['company_name'])) {
                    $parts[] = $value['company_name'];
                }
                if (!empty($value['monthly_income'])) {
                    $parts[] = '$' . number_format((float) $value['monthly_income'], 0, '.', ',');
                }
                return implode(' - ', $parts) ?: '(vacío)';
            }

            // Address
            if (isset($value['street']) || isset($value['neighborhood'])) {
                $parts = [];
                if (!empty($value['street'])) {
                    $streetLine = $value['street'];
                    if (!empty($value['ext_number'])) {
                        $streetLine .= ' ' . $value['ext_number'];
                    }
                    $parts[] = $streetLine;
                }
                if (!empty($value['neighborhood'])) {
                    $parts[] = 'Col. ' . $value['neighborhood'];
                }
                if (!empty($value['postal_code'])) {
                    $parts[] = 'C.P. ' . $value['postal_code'];
                }
                return implode(', ', $parts) ?: '(vacío)';
            }

            return json_encode($value);
        }

        // Date formatting
        if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}/', $value)) {
            try {
                return \Carbon\Carbon::parse($value)->format('d/m/Y');
            } catch (\Exception $e) {
                return $value;
            }
        }

        return (string) $value ?: '(vacío)';
    }
}
