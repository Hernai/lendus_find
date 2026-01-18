<?php

namespace App\Http\Controllers\Api\V1\Applicant;

use App\Enums\ApplicationStatus;
use App\Enums\AuditAction;
use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\EmploymentType;
use App\Enums\VerifiableField;
use App\Enums\VerificationStatus;
use App\Events\DataCorrectionSubmitted;
use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\AuditLog;
use App\Models\DataVerification;
use App\Models\Document;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CorrectionController extends Controller
{
    /**
     * Get list of rejected fields that need correction.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $applicant = $user->applicant;

        if (!$applicant) {
            return response()->json([
                'data' => [],
                'message' => 'No applicant profile found',
            ]);
        }

        // Get all rejected verifications
        $rejectedFields = DataVerification::where('applicant_id', $applicant->id)
            ->rejected()
            ->get()
            ->map(fn ($verification) => [
                'id' => $verification->id,
                'field_name' => $verification->field_name,
                'field_label' => DataVerification::getFieldLabel($verification->field_name),
                'current_value' => $verification->field_value,
                'rejection_reason' => $verification->rejection_reason,
                'rejected_at' => $verification->rejected_at?->toIso8601String(),
            ]);

        // Get correction history (all fields that have been corrected)
        $nameFields = [
            VerifiableField::FIRST_NAME->value,
            VerifiableField::LAST_NAME_1->value,
            VerifiableField::LAST_NAME_2->value,
        ];

        $correctionHistory = DataVerification::where('applicant_id', $applicant->id)
            ->whereNotNull('correction_history')
            ->get()
            ->flatMap(function ($verification) use ($nameFields) {
                $history = $verification->correction_history ?? [];
                // Para campos de nombre, mostrar "Nombre Completo" como label de sección
                $fieldLabel = in_array($verification->field_name, $nameFields)
                    ? 'Nombre Completo'
                    : DataVerification::getFieldLabel($verification->field_name);

                return collect($history)->map(fn ($entry) => [
                    'field_name' => $verification->field_name,
                    'field_label' => $fieldLabel,
                    'old_value' => $entry['old_value'] ?? null,
                    'new_value' => $entry['new_value'] ?? null,
                    'rejection_reason' => $entry['rejection_reason'] ?? null,
                    'corrected_by' => $entry['corrected_by'] ?? null,
                    'corrected_at' => $entry['corrected_at'] ?? null,
                ]);
            })
            ->sortByDesc('corrected_at')
            ->values();

        // Get applications with CORRECTIONS_PENDING status
        $pendingApplications = Application::where('applicant_id', $applicant->id)
            ->where('status', ApplicationStatus::CORRECTIONS_PENDING)
            ->get(['id', 'folio', 'status', 'updated_at']);

        // Get rejected documents from all applications
        $applicationIds = Application::where('applicant_id', $applicant->id)->pluck('id');
        $rejectedDocuments = Document::whereIn('application_id', $applicationIds)
            ->where('status', DocumentStatus::REJECTED)
            ->get()
            ->map(fn ($doc) => [
                'id' => $doc->id,
                'application_id' => $doc->application_id,
                'type' => $doc->type->value,
                'type_label' => $doc->type->description(),
                'name' => $doc->name ?? $doc->file_name,
                'rejection_reason' => $doc->rejection_reason,
                'rejected_at' => $doc->reviewed_at?->toIso8601String(),
            ]);

        // Get applicant data for form population
        $applicant->load(['primaryAddress', 'currentEmployment']);
        $personalData = $applicant->personal_data ?? [];
        $address = $applicant->primaryAddress;
        $employment = $applicant->currentEmployment;

        $applicantData = [
            'first_name' => $applicant->first_name,
            'last_name_1' => $applicant->last_name_1,
            'last_name_2' => $applicant->last_name_2,
            // These are stored directly on the applicant model, not in personal_data
            'curp' => $applicant->curp ?? '',
            'rfc' => $applicant->rfc ?? '',
            'ine_clave' => $applicant->ine_clave ?? '',
            'birth_date' => $applicant->birth_date?->format('Y-m-d') ?? '',
            'phone' => $applicant->phone,
            'email' => $applicant->email,
            'address' => $address ? [
                'street' => $address->street,
                'ext_number' => $address->ext_number,
                'int_number' => $address->int_number,
                'neighborhood' => $address->neighborhood,
                'postal_code' => $address->postal_code,
                'municipality' => $address->municipality,
                'state' => $address->state,
            ] : null,
            'employment' => $employment ? [
                'type' => $employment->employment_type?->value ?? 'EMPLOYEE',
                'company_name' => $employment->company_name ?? '',
                'position' => $employment->position ?? '',
                'monthly_income' => (float) ($employment->monthly_income ?? 0),
                'seniority_months' => (int) ($employment->seniority_months ?? 0),
            ] : [
                'type' => 'EMPLOYEE',
                'company_name' => '',
                'position' => '',
                'monthly_income' => 0,
                'seniority_months' => 0,
            ],
        ];

        return response()->json([
            'data' => [
                'rejected_fields' => $rejectedFields,
                'rejected_documents' => $rejectedDocuments,
                'correction_history' => $correctionHistory,
                'applicant_data' => $applicantData,
                'pending_applications' => $pendingApplications,
                'has_corrections_pending' => $rejectedFields->count() > 0 || $rejectedDocuments->count() > 0,
            ],
        ]);
    }

    /**
     * Submit a correction for a rejected field.
     */
    public function submitCorrection(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'field_name' => 'required|string|max:100',
            'new_value' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();
        $applicant = $user->applicant;

        if (!$applicant) {
            return response()->json([
                'message' => 'No applicant profile found',
            ], 404);
        }

        // Find the rejected verification
        $verification = DataVerification::where('applicant_id', $applicant->id)
            ->where('field_name', $request->field_name)
            ->rejected()
            ->first();

        if (!$verification) {
            return response()->json([
                'message' => 'No hay corrección pendiente para este campo',
            ], 404);
        }

        $newValue = $request->new_value;

        // Para campos complejos, obtener el valor actual desde los datos reales (no del verification record)
        $nameFields = [
            VerifiableField::FIRST_NAME->value,
            VerifiableField::LAST_NAME_1->value,
            VerifiableField::LAST_NAME_2->value,
        ];

        if (in_array($request->field_name, $nameFields)) {
            // Guardar nombre completo actual como old_value
            $oldValue = [
                'first_name' => $applicant->first_name,
                'last_name_1' => $applicant->last_name_1,
                'last_name_2' => $applicant->last_name_2,
            ];
        } elseif ($request->field_name === VerifiableField::ADDRESS->value) {
            // Guardar dirección actual como old_value (desde el registro real)
            $address = $applicant->primaryAddress;
            $oldValue = $address ? [
                'street' => $address->street,
                'ext_number' => $address->ext_number,
                'int_number' => $address->int_number,
                'neighborhood' => $address->neighborhood,
                'postal_code' => $address->postal_code,
                'municipality' => $address->municipality,
                'state' => $address->state,
            ] : [];
        } elseif ($request->field_name === VerifiableField::EMPLOYMENT->value) {
            // Guardar empleo actual como old_value (desde el registro real)
            $employment = $applicant->currentEmployment;
            $oldValue = $employment ? [
                'type' => $employment->employment_type?->value,
                'company_name' => $employment->company_name,
                'position' => $employment->position,
                'monthly_income' => $employment->monthly_income,
                'seniority_months' => $employment->seniority_months,
            ] : [];
        } else {
            // Para campos simples, usar el valor del verification record
            $oldValue = $verification->field_value;
            // Si es JSON string, decodificar
            if (is_string($oldValue) && str_starts_with($oldValue, '{')) {
                $decoded = json_decode($oldValue, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $oldValue = $decoded;
                }
            }
        }

        // Update the field value based on field_name
        $this->updateApplicantField($applicant, $request->field_name, $newValue);

        // Update verification record with history
        $verification->field_value = is_array($newValue) ? json_encode($newValue) : $newValue;
        $verification->markCorrected($oldValue, $newValue, [
            'id' => $user->id,
            'name' => $user->name,
        ]);

        // Log the correction
        $metadata = $request->attributes->get('metadata', []);
        $tenant = $request->attributes->get('tenant');
        $nameFields = [
            VerifiableField::FIRST_NAME->value,
            VerifiableField::LAST_NAME_1->value,
            VerifiableField::LAST_NAME_2->value,
        ];
        $fieldLabel = in_array($request->field_name, $nameFields)
            ? 'Nombre Completo'
            : DataVerification::getFieldLabel($request->field_name);

        AuditLog::log(
            AuditAction::DATA_CORRECTED->value,
            $tenant->id,
            array_merge($metadata, [
                'user_id' => $user->id,
                'applicant_id' => $applicant->id,
                'entity_type' => 'DataVerification',
                'entity_id' => $verification->id,
                'old_values' => ['value' => $oldValue],
                'new_values' => ['value' => $newValue],
                'metadata' => [
                    'field_name' => $request->field_name,
                    'field_label' => $fieldLabel,
                ],
            ])
        );

        // Add timeline entry to all applications of this applicant
        $applications = Application::where('applicant_id', $applicant->id)->get();
        $ipAddress = $metadata['ip_address'] ?? $request->ip();
        $userAgent = $metadata['user_agent'] ?? $request->userAgent();
        $approximateLocation = $this->getApproximateLocation($ipAddress);

        // Determine field type and format changes appropriately
        $fieldType = $this->getFieldType($request->field_name);

        // For complex objects, get specific changed fields as JSON; for simple values, use null (will use old/new)
        $changesJson = null;
        if ($fieldType && is_array($oldValue) && is_array($newValue)) {
            $changesJson = $this->getChangedFields($oldValue, $newValue, $fieldType);
        }

        foreach ($applications as $application) {
            $application->addTimelineEntry('DATA_CORRECTED', [
                'field_label' => $fieldLabel,
                'changes' => $changesJson, // Array of specific field changes (stored as JSON)
                'old_value' => $this->formatValueForSummary($oldValue),
                'new_value' => $this->formatValueForSummary($newValue),
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'location' => $approximateLocation,
            ], $user->id);
        }

        // Check if all corrections are done to update application status
        $this->checkAndUpdateApplicationStatus($applicant, $user, $fieldLabel, $oldValue, $newValue);

        // Broadcast the correction event
        event(new DataCorrectionSubmitted(
            $verification,
            $applicant,
            $oldValue,
            $newValue
        ));

        return response()->json([
            'message' => 'Corrección enviada correctamente',
            'data' => [
                'field_name' => $request->field_name,
                'status' => 'corrected',
            ],
        ]);
    }

    /**
     * Update the applicant field based on field_name.
     */
    protected function updateApplicantField($applicant, string $fieldName, $value): void
    {
        switch ($fieldName) {
            case VerifiableField::FIRST_NAME->value:
            case VerifiableField::LAST_NAME_1->value:
            case VerifiableField::LAST_NAME_2->value:
                // Si el valor es un objeto con los 3 campos, actualizar todos
                // (el admin rechaza "Nombre Completo" como sección, no campo individual)
                if (is_array($value)) {
                    if (isset($value['first_name'])) {
                        $applicant->first_name = $value['first_name'];
                    }
                    if (isset($value['last_name_1'])) {
                        $applicant->last_name_1 = $value['last_name_1'];
                    }
                    if (isset($value['last_name_2'])) {
                        $applicant->last_name_2 = $value['last_name_2'];
                    }
                } else {
                    // Si es un valor simple, actualizar solo el campo específico
                    match ($fieldName) {
                        VerifiableField::FIRST_NAME->value => $applicant->first_name = $value,
                        VerifiableField::LAST_NAME_1->value => $applicant->last_name_1 = $value,
                        VerifiableField::LAST_NAME_2->value => $applicant->last_name_2 = $value,
                    };
                }
                break;
            case VerifiableField::CURP->value:
                // CURP is stored directly on the applicant model
                $applicant->curp = $value;
                break;
            case VerifiableField::RFC->value:
                // RFC is stored directly on the applicant model
                $applicant->rfc = $value;
                break;
            case VerifiableField::INE->value:
                // INE clave is stored directly on the applicant model
                $applicant->ine_clave = $value;
                break;
            case VerifiableField::BIRTH_DATE->value:
                // birth_date is stored directly on the applicant model
                $applicant->birth_date = $value;
                break;
            case VerifiableField::PHONE->value:
                $applicant->phone = $value;
                break;
            case VerifiableField::EMAIL->value:
                $applicant->email = $value;
                break;
            case VerifiableField::ADDRESS->value:
                // For address, expect full address object
                if (is_array($value) && $applicant->primaryAddress) {
                    $address = $applicant->primaryAddress;
                    $address->fill($value);
                    $address->save();
                }
                break;
            case VerifiableField::EMPLOYMENT->value:
                // For employment, update the current employment record
                if (is_array($value)) {
                    $employment = $applicant->currentEmployment;
                    if ($employment) {
                        // Map the correction values to employment record fields
                        if (isset($value['type'])) {
                            // Convert string to enum
                            $employment->employment_type = EmploymentType::tryFrom($value['type']) ?? EmploymentType::EMPLOYEE;
                        }
                        if (isset($value['company_name'])) {
                            $employment->company_name = $value['company_name'];
                        }
                        if (isset($value['position'])) {
                            $employment->position = $value['position'];
                        }
                        if (isset($value['monthly_income'])) {
                            $employment->monthly_income = $value['monthly_income'];
                        }
                        if (isset($value['seniority_months'])) {
                            $employment->seniority_months = $value['seniority_months'];
                        }
                        $employment->save();
                    }
                }
                break;
        }

        $applicant->save();
    }

    /**
     * Check if all corrections are done and update application status.
     */
    protected function checkAndUpdateApplicationStatus($applicant, $user, string $fieldLabel, $oldValue, $newValue): void
    {
        // Check if there are still rejected fields
        $stillRejectedFields = DataVerification::where('applicant_id', $applicant->id)
            ->rejected()
            ->exists();

        if ($stillRejectedFields) {
            return; // Still has field corrections pending
        }

        // Check if there are still rejected documents
        $applicationIds = Application::where('applicant_id', $applicant->id)->pluck('id');
        $stillRejectedDocs = Document::whereIn('application_id', $applicationIds)
            ->where('status', DocumentStatus::REJECTED)
            ->exists();

        if ($stillRejectedDocs) {
            return; // Still has document corrections pending
        }

        // Get the first pending application to determine correction cycle start date
        $firstApplication = Application::where('applicant_id', $applicant->id)
            ->where('status', ApplicationStatus::CORRECTIONS_PENDING)
            ->first();

        if (!$firstApplication) {
            return; // No pending applications
        }

        // Find when the application entered CORRECTIONS_PENDING status
        $correctionStartDate = $this->getCorrectionCycleStartDate($firstApplication);

        // Get fields corrected AFTER entering CORRECTIONS_PENDING
        $correctedFieldLabels = [];
        if ($correctionStartDate) {
            $nameFields = [
                VerifiableField::FIRST_NAME->value,
                VerifiableField::LAST_NAME_1->value,
                VerifiableField::LAST_NAME_2->value,
            ];

            $correctedFields = DataVerification::where('applicant_id', $applicant->id)
                ->where('status', VerificationStatus::CORRECTED)
                ->where('corrected_at', '>=', $correctionStartDate)
                ->pluck('field_name')
                ->unique();

            $hasNameCorrection = false;
            foreach ($correctedFields as $fieldName) {
                if (in_array($fieldName, $nameFields)) {
                    if (!$hasNameCorrection) {
                        $correctedFieldLabels[] = 'Nombre Completo';
                        $hasNameCorrection = true;
                    }
                } else {
                    $correctedFieldLabels[] = DataVerification::getFieldLabel($fieldName);
                }
            }
        }

        // Get documents uploaded AFTER entering CORRECTIONS_PENDING (from timeline)
        $uploadedDocLabels = $this->getUploadedDocumentsDuringCorrection($firstApplication, $correctionStartDate);

        // Build reason message based on what was actually corrected
        $reason = $this->buildCorrectionReasonMessage($correctedFieldLabels, $uploadedDocLabels);

        // Update any applications in CORRECTIONS_PENDING status
        $applications = Application::where('applicant_id', $applicant->id)
            ->where('status', ApplicationStatus::CORRECTIONS_PENDING)
            ->get();

        foreach ($applications as $application) {
            // Move back to IN_REVIEW status with user and correction details
            $application->changeStatus(
                ApplicationStatus::IN_REVIEW->value,
                $reason,
                $user->id
            );
        }
    }

    /**
     * Get the date when the application entered CORRECTIONS_PENDING status.
     */
    protected function getCorrectionCycleStartDate(Application $application): ?Carbon
    {
        $history = $application->status_history ?? [];

        // Find the most recent entry where status changed TO CORRECTIONS_PENDING
        $correctionEntries = collect($history)
            ->filter(fn ($entry) => ($entry['to'] ?? null) === ApplicationStatus::CORRECTIONS_PENDING->value)
            ->sortByDesc('timestamp');

        $lastEntry = $correctionEntries->first();

        if ($lastEntry && isset($lastEntry['timestamp'])) {
            return Carbon::parse($lastEntry['timestamp']);
        }

        return null;
    }

    /**
     * Get list of documents uploaded during the correction cycle.
     */
    protected function getUploadedDocumentsDuringCorrection(Application $application, ?Carbon $startDate): array
    {
        if (!$startDate) {
            return [];
        }

        $history = $application->status_history ?? [];

        $uploadedDocs = collect($history)
            ->filter(function ($entry) use ($startDate) {
                if (($entry['action'] ?? null) !== 'DOC_UPLOADED') {
                    return false;
                }
                if (!isset($entry['timestamp'])) {
                    return false;
                }
                return Carbon::parse($entry['timestamp'])->gte($startDate);
            })
            ->pluck('document')
            ->unique()
            ->map(function ($docType) {
                // Convert document type to human-readable label
                $enumType = DocumentType::tryFrom($docType);
                return $enumType ? $enumType->description() : $docType;
            })
            ->values()
            ->toArray();

        return $uploadedDocs;
    }

    /**
     * Build a reason message for the status change based on what was corrected.
     */
    protected function buildCorrectionReasonMessage(array $correctedFields, array $uploadedDocs): string
    {
        $parts = [];

        if (!empty($correctedFields)) {
            $parts[] = 'Datos corregidos: ' . implode(', ', $correctedFields);
        }

        if (!empty($uploadedDocs)) {
            $parts[] = 'Documentos actualizados: ' . implode(', ', $uploadedDocs);
        }

        if (empty($parts)) {
            return 'Correcciones completadas';
        }

        return implode('. ', $parts);
    }

    /**
     * Get the specific fields that changed between old and new values.
     * Returns an array of ['field_label' => 'Old → New'] for each changed field.
     */
    protected function getChangedFields($oldValue, $newValue, string $fieldType): array
    {
        $changes = [];

        if (!is_array($oldValue) || !is_array($newValue)) {
            return $changes;
        }

        // Field labels for each type
        $fieldLabels = match ($fieldType) {
            'name' => [
                'first_name' => 'Nombre',
                'last_name_1' => 'Apellido Paterno',
                'last_name_2' => 'Apellido Materno',
            ],
            'employment' => [
                'type' => 'Tipo de Empleo',
                'company_name' => 'Empresa',
                'position' => 'Puesto',
                'monthly_income' => 'Ingreso Mensual',
                'seniority_months' => 'Antigüedad (meses)',
            ],
            'address' => [
                'street' => 'Calle',
                'ext_number' => 'Número Exterior',
                'int_number' => 'Número Interior',
                'neighborhood' => 'Colonia',
                'postal_code' => 'Código Postal',
                'municipality' => 'Municipio',
                'state' => 'Estado',
            ],
            default => [],
        };

        foreach ($fieldLabels as $key => $label) {
            $oldVal = $oldValue[$key] ?? null;
            $newVal = $newValue[$key] ?? null;

            // Normalize values for comparison
            $oldNormalized = $this->normalizeValueForComparison($oldVal, $key);
            $newNormalized = $this->normalizeValueForComparison($newVal, $key);

            if ($oldNormalized !== $newNormalized) {
                $oldDisplay = $this->formatSingleValue($oldVal, $key);
                $newDisplay = $this->formatSingleValue($newVal, $key);
                $changes[$label] = "{$oldDisplay} → {$newDisplay}";
            }
        }

        return $changes;
    }

    /**
     * Normalize a value for comparison (handle nulls, types, etc).
     */
    protected function normalizeValueForComparison($value, string $key): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        // For employment type, compare enum values
        if ($key === 'type' && is_string($value)) {
            return strtoupper($value);
        }

        // For numeric values
        if ($key === 'monthly_income' || $key === 'seniority_months') {
            return (string) ((float) $value);
        }

        return (string) $value;
    }

    /**
     * Format a single value for display.
     */
    protected function formatSingleValue($value, string $key): string
    {
        if ($value === null || $value === '') {
            return '(vacío)';
        }

        // Format employment type
        if ($key === 'type') {
            return EmploymentType::tryFrom($value)?->label() ?? $value;
        }

        // Format money
        if ($key === 'monthly_income') {
            return '$' . number_format((float) $value, 0, '.', ',');
        }

        // Format seniority
        if ($key === 'seniority_months') {
            $months = (int) $value;
            if ($months >= 12) {
                $years = floor($months / 12);
                $remainingMonths = $months % 12;
                if ($remainingMonths > 0) {
                    return "{$years} año(s) y {$remainingMonths} mes(es)";
                }
                return "{$years} año(s)";
            }
            return "{$months} mes(es)";
        }

        return (string) $value;
    }

    /**
     * Format changes for timeline display.
     */
    protected function formatChangesForTimeline($oldValue, $newValue, string $fieldType): string
    {
        $changes = $this->getChangedFields($oldValue, $newValue, $fieldType);

        if (empty($changes)) {
            // Fallback to old format if no specific changes detected
            return $this->formatValueForSummary($oldValue) . ' → ' . $this->formatValueForSummary($newValue);
        }

        // Format as "Campo: viejo → nuevo, Campo2: viejo2 → nuevo2"
        $parts = [];
        foreach ($changes as $label => $change) {
            $parts[] = "{$label}: {$change}";
        }

        return implode(', ', $parts);
    }

    /**
     * Determine the field type based on field name.
     */
    protected function getFieldType(string $fieldName): ?string
    {
        $nameFields = [
            VerifiableField::FIRST_NAME->value,
            VerifiableField::LAST_NAME_1->value,
            VerifiableField::LAST_NAME_2->value,
        ];

        if (in_array($fieldName, $nameFields)) {
            return 'name';
        }

        if ($fieldName === VerifiableField::EMPLOYMENT->value) {
            return 'employment';
        }

        if ($fieldName === VerifiableField::ADDRESS->value) {
            return 'address';
        }

        return null;
    }

    /**
     * Get approximate location from IP address using a free geolocation service.
     */
    protected function getApproximateLocation(string $ip): ?string
    {
        // Skip for localhost/private IPs
        if (in_array($ip, ['127.0.0.1', '::1']) ||
            str_starts_with($ip, '192.168.') ||
            str_starts_with($ip, '10.') ||
            str_starts_with($ip, '172.')) {
            return 'Local/Privada';
        }

        try {
            // Use ip-api.com (free, no API key required, 45 req/min limit)
            $response = @file_get_contents("http://ip-api.com/json/{$ip}?fields=status,city,regionName,country&lang=es");

            if ($response) {
                $data = json_decode($response, true);

                if ($data && ($data['status'] ?? '') === 'success') {
                    $parts = array_filter([
                        $data['city'] ?? null,
                        $data['regionName'] ?? null,
                        $data['country'] ?? null,
                    ]);

                    return implode(', ', $parts) ?: null;
                }
            }
        } catch (\Exception $e) {
            // Silently fail, location is optional
        }

        return null;
    }

    /**
     * Format a value for the correction summary (concise version for timeline).
     */
    protected function formatValueForSummary($value): string
    {
        if ($value === null) {
            return '(vacío)';
        }

        if (is_array($value)) {
            // For name objects - show full name
            if (isset($value['first_name']) || isset($value['last_name_1']) || isset($value['last_name_2'])) {
                $parts = array_filter([
                    $value['first_name'] ?? null,
                    $value['last_name_1'] ?? null,
                    $value['last_name_2'] ?? null,
                ]);
                return implode(' ', $parts) ?: '(vacío)';
            }

            // For employment - show type, company, position, income
            if (isset($value['company_name']) || isset($value['type'])) {
                $parts = [];
                if (!empty($value['type'])) {
                    $typeLabel = EmploymentType::tryFrom($value['type'])?->label() ?? $value['type'];
                    $parts[] = $typeLabel;
                }
                if (!empty($value['company_name'])) {
                    $parts[] = $value['company_name'];
                }
                if (!empty($value['position'])) {
                    $parts[] = $value['position'];
                }
                if (!empty($value['monthly_income'])) {
                    $parts[] = '$' . number_format((float) $value['monthly_income'], 0, '.', ',');
                }
                return implode(' - ', $parts) ?: '(vacío)';
            }

            // For address - show full address with all components
            if (isset($value['street']) || isset($value['ext_number']) || isset($value['neighborhood'])) {
                $parts = [];

                // Street and numbers
                $streetLine = trim(($value['street'] ?? '') . ' ' . ($value['ext_number'] ?? ''));
                if (!empty($value['int_number'])) {
                    $streetLine .= ' Int. ' . $value['int_number'];
                }
                if ($streetLine) {
                    $parts[] = $streetLine;
                }

                // Neighborhood
                if (!empty($value['neighborhood'])) {
                    $parts[] = 'Col. ' . $value['neighborhood'];
                }

                // Postal code
                if (!empty($value['postal_code'])) {
                    $parts[] = 'C.P. ' . $value['postal_code'];
                }

                // Municipality and State
                $location = trim(($value['municipality'] ?? '') . ', ' . ($value['state'] ?? ''), ', ');
                if ($location) {
                    $parts[] = $location;
                }

                return implode(', ', $parts) ?: '(vacío)';
            }

            // For other arrays, return first non-empty value
            $firstValue = array_filter(array_values($value), fn($v) => $v !== null && $v !== '');
            return reset($firstValue) ?: '(vacío)';
        }

        // For date strings, try to format nicely
        $strValue = (string) $value;
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $strValue)) {
            // It's a date, format as d/m/Y
            try {
                return \Carbon\Carbon::parse($strValue)->format('d/m/Y');
            } catch (\Exception $e) {
                return $strValue;
            }
        }

        return $strValue ?: '(vacío)';
    }

    /**
     * Get correction details for a specific field.
     */
    public function show(Request $request, string $fieldName): JsonResponse
    {
        $user = $request->user();
        $applicant = $user->applicant;

        if (!$applicant) {
            return response()->json([
                'message' => 'No applicant profile found',
            ], 404);
        }

        $verification = DataVerification::where('applicant_id', $applicant->id)
            ->where('field_name', $fieldName)
            ->first();

        if (!$verification) {
            return response()->json([
                'message' => 'Verificación de campo no encontrada',
            ], 404);
        }

        return response()->json([
            'data' => [
                'id' => $verification->id,
                'field_name' => $verification->field_name,
                'field_label' => DataVerification::getFieldLabel($verification->field_name),
                'current_value' => $verification->field_value,
                'status' => $verification->status,
                'status_label' => DataVerification::getStatusLabel($verification->status),
                'rejection_reason' => $verification->rejection_reason,
                'rejected_at' => $verification->rejected_at?->toIso8601String(),
                'corrected_at' => $verification->corrected_at?->toIso8601String(),
                'is_rejected' => $verification->isRejected(),
            ],
        ]);
    }
}
