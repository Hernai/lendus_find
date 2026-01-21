<?php

namespace App\Services;

use App\Models\Application;
use App\Models\ApplicationStatusHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Service for tracking application lifecycle events.
 *
 * Records all significant actions during the application process:
 * - Application creation
 * - Profile data updates (personal, address, employment)
 * - Document uploads
 * - KYC verifications (INE, CURP, RFC, Face Match)
 * - References and bank accounts
 * - Status changes
 *
 * Each event includes IP address and user agent for audit purposes.
 */
class ApplicationEventService
{
    // Event types for the application lifecycle
    public const EVENT_APPLICATION_CREATED = 'APPLICATION_CREATED';
    public const EVENT_PROFILE_CREATED = 'PROFILE_CREATED';
    public const EVENT_PROFILE_UPDATED = 'PROFILE_UPDATED';
    public const EVENT_ADDRESS_SAVED = 'ADDRESS_SAVED';
    public const EVENT_EMPLOYMENT_SAVED = 'EMPLOYMENT_SAVED';
    public const EVENT_DOCUMENT_UPLOADED = 'DOCUMENT_UPLOADED';
    public const EVENT_DOCUMENT_DELETED = 'DOCUMENT_DELETED';
    public const EVENT_KYC_INE_VALIDATED = 'KYC_INE_VALIDATED';
    public const EVENT_KYC_CURP_VALIDATED = 'KYC_CURP_VALIDATED';
    public const EVENT_KYC_RFC_VALIDATED = 'KYC_RFC_VALIDATED';
    public const EVENT_KYC_FACE_MATCH = 'KYC_FACE_MATCH';
    public const EVENT_KYC_LIVENESS = 'KYC_LIVENESS';
    public const EVENT_REFERENCE_ADDED = 'REFERENCE_ADDED';
    public const EVENT_REFERENCE_UPDATED = 'REFERENCE_UPDATED';
    public const EVENT_REFERENCE_DELETED = 'REFERENCE_DELETED';
    public const EVENT_BANK_ACCOUNT_ADDED = 'BANK_ACCOUNT_ADDED';
    public const EVENT_BANK_ACCOUNT_UPDATED = 'BANK_ACCOUNT_UPDATED';
    public const EVENT_BANK_ACCOUNT_DELETED = 'BANK_ACCOUNT_DELETED';
    public const EVENT_SIGNATURE_SAVED = 'SIGNATURE_SAVED';
    public const EVENT_APPLICATION_SUBMITTED = 'APPLICATION_SUBMITTED';
    public const EVENT_STEP_COMPLETED = 'STEP_COMPLETED';

    // Event labels in Spanish
    public const EVENT_LABELS = [
        self::EVENT_APPLICATION_CREATED => 'Solicitud creada',
        self::EVENT_PROFILE_CREATED => 'Perfil creado',
        self::EVENT_PROFILE_UPDATED => 'Datos personales actualizados',
        self::EVENT_ADDRESS_SAVED => 'Domicilio guardado',
        self::EVENT_EMPLOYMENT_SAVED => 'Información laboral guardada',
        self::EVENT_DOCUMENT_UPLOADED => 'Documento subido',
        self::EVENT_DOCUMENT_DELETED => 'Documento eliminado',
        self::EVENT_KYC_INE_VALIDATED => 'INE validada con KYC',
        self::EVENT_KYC_CURP_VALIDATED => 'CURP validada',
        self::EVENT_KYC_RFC_VALIDATED => 'RFC validado',
        self::EVENT_KYC_FACE_MATCH => 'Verificación facial completada',
        self::EVENT_KYC_LIVENESS => 'Prueba de vida completada',
        self::EVENT_REFERENCE_ADDED => 'Referencia agregada',
        self::EVENT_REFERENCE_UPDATED => 'Referencia actualizada',
        self::EVENT_REFERENCE_DELETED => 'Referencia eliminada',
        self::EVENT_BANK_ACCOUNT_ADDED => 'Cuenta bancaria agregada',
        self::EVENT_BANK_ACCOUNT_UPDATED => 'Cuenta bancaria actualizada',
        self::EVENT_BANK_ACCOUNT_DELETED => 'Cuenta bancaria eliminada',
        self::EVENT_SIGNATURE_SAVED => 'Firma guardada',
        self::EVENT_APPLICATION_SUBMITTED => 'Solicitud enviada',
        self::EVENT_STEP_COMPLETED => 'Paso completado',
    ];

    /**
     * Record an application event.
     *
     * @param Application $application The application
     * @param string $eventType One of the EVENT_* constants
     * @param string|null $changedById ID of who made the change (user/account ID)
     * @param string|null $changedByType Type: 'applicant_accounts', 'staff_accounts', 'system'
     * @param string|null $details Additional description
     * @param array|null $metadata Additional data (document type, field changes, etc.)
     * @param Request|null $request Optional request for IP/user agent
     */
    public function record(
        Application $application,
        string $eventType,
        ?string $changedById = null,
        ?string $changedByType = 'applicant_accounts',
        ?string $details = null,
        ?array $metadata = null,
        ?Request $request = null
    ): ApplicationStatusHistory {
        // Build metadata with IP and user agent
        $eventMetadata = array_merge(
            $metadata ?? [],
            [
                'event_type' => $eventType,
                'ip_address' => $request?->ip() ?? request()->ip(),
                'user_agent' => $request?->userAgent() ?? request()->userAgent(),
            ]
        );

        // Use the event type as both from_status and to_status for non-status-change events
        $history = ApplicationStatusHistory::create([
            'application_id' => $application->id,
            'from_status' => $eventType,
            'to_status' => $eventType,
            'changed_by' => $changedById,
            'changed_by_type' => $changedByType,
            'notes' => $details ?? self::EVENT_LABELS[$eventType] ?? $eventType,
            'metadata' => $eventMetadata,
            'created_at' => now(),
        ]);

        Log::debug('[ApplicationEventService] Event recorded', [
            'application_id' => $application->id,
            'event_type' => $eventType,
            'changed_by' => $changedById,
        ]);

        return $history;
    }

    /**
     * Record application created event.
     */
    public function recordApplicationCreated(
        Application $application,
        ?string $userId = null,
        ?Request $request = null
    ): ApplicationStatusHistory {
        return $this->record(
            $application,
            self::EVENT_APPLICATION_CREATED,
            $userId,
            'applicant_accounts',
            'Solicitud iniciada',
            [
                'product_id' => $application->product_id,
                'product_name' => $application->product?->name,
            ],
            $request
        );
    }

    /**
     * Record profile created event.
     */
    public function recordProfileCreated(
        Application $application,
        ?string $userId = null,
        array $fields = [],
        ?Request $request = null
    ): ApplicationStatusHistory {
        return $this->record(
            $application,
            self::EVENT_PROFILE_CREATED,
            $userId,
            'applicant_accounts',
            'Perfil creado',
            ['fields' => $fields],
            $request
        );
    }

    /**
     * Record profile updated event.
     */
    public function recordProfileUpdated(
        Application $application,
        ?string $userId = null,
        array $changedFields = [],
        ?Request $request = null
    ): ApplicationStatusHistory {
        $fieldCount = count($changedFields);
        $details = $fieldCount > 0
            ? "Datos personales actualizados ({$fieldCount} campos)"
            : 'Datos personales actualizados';

        return $this->record(
            $application,
            self::EVENT_PROFILE_UPDATED,
            $userId,
            'applicant_accounts',
            $details,
            ['changed_fields' => $changedFields],
            $request
        );
    }

    /**
     * Record address saved event.
     */
    public function recordAddressSaved(
        Application $application,
        ?string $userId = null,
        ?string $postalCode = null,
        ?Request $request = null
    ): ApplicationStatusHistory {
        return $this->record(
            $application,
            self::EVENT_ADDRESS_SAVED,
            $userId,
            'applicant_accounts',
            'Domicilio guardado',
            ['postal_code' => $postalCode],
            $request
        );
    }

    /**
     * Record employment saved event.
     */
    public function recordEmploymentSaved(
        Application $application,
        ?string $userId = null,
        ?string $employmentType = null,
        ?Request $request = null
    ): ApplicationStatusHistory {
        return $this->record(
            $application,
            self::EVENT_EMPLOYMENT_SAVED,
            $userId,
            'applicant_accounts',
            'Información laboral guardada',
            ['employment_type' => $employmentType],
            $request
        );
    }

    /**
     * Record document uploaded event.
     */
    public function recordDocumentUploaded(
        Application $application,
        string $documentType,
        ?string $documentId = null,
        ?string $userId = null,
        ?Request $request = null
    ): ApplicationStatusHistory {
        $typeLabel = \App\Models\Document::typeLabels()[$documentType] ?? $documentType;

        return $this->record(
            $application,
            self::EVENT_DOCUMENT_UPLOADED,
            $userId,
            'applicant_accounts',
            "Documento subido: {$typeLabel}",
            [
                'document_type' => $documentType,
                'document_type_label' => $typeLabel,
                'document_id' => $documentId,
            ],
            $request
        );
    }

    /**
     * Record KYC INE validation event.
     */
    public function recordKycIneValidated(
        Application $application,
        bool $isValid,
        ?string $userId = null,
        ?array $ocrData = null,
        ?Request $request = null
    ): ApplicationStatusHistory {
        $details = $isValid ? 'INE validada exitosamente' : 'Validación de INE fallida';

        return $this->record(
            $application,
            self::EVENT_KYC_INE_VALIDATED,
            $userId,
            'applicant_accounts',
            $details,
            [
                'is_valid' => $isValid,
                'curp_extracted' => $ocrData['curp'] ?? null,
                'name_extracted' => isset($ocrData['nombres']) ? true : false,
            ],
            $request
        );
    }

    /**
     * Record KYC CURP validation event.
     */
    public function recordKycCurpValidated(
        Application $application,
        bool $isValid,
        string $curp,
        ?string $userId = null,
        ?Request $request = null
    ): ApplicationStatusHistory {
        $maskedCurp = substr($curp, 0, 4) . '****' . substr($curp, -4);
        $details = $isValid ? "CURP validada: {$maskedCurp}" : 'Validación de CURP fallida';

        return $this->record(
            $application,
            self::EVENT_KYC_CURP_VALIDATED,
            $userId,
            'applicant_accounts',
            $details,
            ['is_valid' => $isValid, 'curp_masked' => $maskedCurp],
            $request
        );
    }

    /**
     * Record KYC RFC validation event.
     */
    public function recordKycRfcValidated(
        Application $application,
        bool $isValid,
        string $rfc,
        ?string $userId = null,
        ?Request $request = null
    ): ApplicationStatusHistory {
        $maskedRfc = substr($rfc, 0, 4) . '****' . substr($rfc, -3);
        $details = $isValid ? "RFC validado: {$maskedRfc}" : 'Validación de RFC fallida';

        return $this->record(
            $application,
            self::EVENT_KYC_RFC_VALIDATED,
            $userId,
            'applicant_accounts',
            $details,
            ['is_valid' => $isValid, 'rfc_masked' => $maskedRfc],
            $request
        );
    }

    /**
     * Record KYC Face Match event.
     */
    public function recordKycFaceMatch(
        Application $application,
        bool $matched,
        ?float $score = null,
        ?string $userId = null,
        ?Request $request = null
    ): ApplicationStatusHistory {
        $details = $matched
            ? 'Verificación facial exitosa' . ($score ? " (score: {$score}%)" : '')
            : 'Verificación facial fallida';

        return $this->record(
            $application,
            self::EVENT_KYC_FACE_MATCH,
            $userId,
            'applicant_accounts',
            $details,
            ['matched' => $matched, 'score' => $score],
            $request
        );
    }

    /**
     * Record reference added event.
     */
    public function recordReferenceAdded(
        Application $application,
        string $referenceType,
        ?string $referenceName = null,
        ?string $userId = null,
        ?Request $request = null
    ): ApplicationStatusHistory {
        $typeLabels = [
            'PERSONAL' => 'personal',
            'WORK' => 'laboral',
            'FAMILY' => 'familiar',
        ];
        $typeLabel = $typeLabels[$referenceType] ?? $referenceType;
        $details = "Referencia {$typeLabel} agregada" . ($referenceName ? ": {$referenceName}" : '');

        return $this->record(
            $application,
            self::EVENT_REFERENCE_ADDED,
            $userId,
            'applicant_accounts',
            $details,
            ['reference_type' => $referenceType, 'reference_name' => $referenceName],
            $request
        );
    }

    /**
     * Record bank account added event.
     */
    public function recordBankAccountAdded(
        Application $application,
        ?string $bankName = null,
        ?string $userId = null,
        ?Request $request = null
    ): ApplicationStatusHistory {
        $details = 'Cuenta bancaria agregada' . ($bankName ? ": {$bankName}" : '');

        return $this->record(
            $application,
            self::EVENT_BANK_ACCOUNT_ADDED,
            $userId,
            'applicant_accounts',
            $details,
            ['bank_name' => $bankName],
            $request
        );
    }

    /**
     * Record signature saved event.
     */
    public function recordSignatureSaved(
        Application $application,
        ?string $userId = null,
        ?Request $request = null
    ): ApplicationStatusHistory {
        return $this->record(
            $application,
            self::EVENT_SIGNATURE_SAVED,
            $userId,
            'applicant_accounts',
            'Firma digital guardada',
            [],
            $request
        );
    }

    /**
     * Record application submitted event.
     */
    public function recordApplicationSubmitted(
        Application $application,
        ?string $userId = null,
        ?Request $request = null
    ): ApplicationStatusHistory {
        return $this->record(
            $application,
            self::EVENT_APPLICATION_SUBMITTED,
            $userId,
            'applicant_accounts',
            'Solicitud enviada para revisión',
            [
                'submitted_at' => now()->toIso8601String(),
            ],
            $request
        );
    }

    /**
     * Record step completed event.
     */
    public function recordStepCompleted(
        Application $application,
        int $stepNumber,
        string $stepName,
        ?string $userId = null,
        ?Request $request = null
    ): ApplicationStatusHistory {
        $stepLabels = [
            1 => 'Selección de producto',
            2 => 'Datos personales',
            3 => 'Domicilio',
            4 => 'Información laboral',
            5 => 'Verificación de identidad',
            6 => 'Documentos',
            7 => 'Referencias',
            8 => 'Revisión y envío',
        ];

        $details = $stepLabels[$stepNumber] ?? "Paso {$stepNumber}";

        return $this->record(
            $application,
            self::EVENT_STEP_COMPLETED,
            $userId,
            'applicant_accounts',
            "Paso {$stepNumber} completado: {$details}",
            [
                'step_number' => $stepNumber,
                'step_name' => $stepName,
                'step_label' => $stepLabels[$stepNumber] ?? null,
            ],
            $request
        );
    }

    /**
     * Get event label in Spanish.
     */
    public static function getEventLabel(string $eventType): string
    {
        return self::EVENT_LABELS[$eventType] ?? $eventType;
    }

    /**
     * Check if an event type is a lifecycle event (not a status change).
     */
    public static function isLifecycleEvent(string $eventType): bool
    {
        return array_key_exists($eventType, self::EVENT_LABELS);
    }
}
