<?php

namespace App\Services;

use App\Enums\VerifiableField;
use App\Enums\VerificationMethod;
use App\Enums\VerificationStatus;
use App\Models\ApplicantAccount;
use App\Models\DataVerification;
use App\Models\Person;

class VerificationService
{
    /**
     * Verify and lock a field for an entity (ApplicantAccount or Person).
     *
     * @param ApplicantAccount|Person $entity The entity to verify
     * @param VerifiableField|string $field The field being verified
     * @param mixed $value The verified value
     * @param VerificationMethod|string $method How it was verified (OTP, RENAPO, SAT, INE, etc.)
     * @param array|null $metadata Additional data about the verification
     * @param string|null $notes Optional notes
     * @return DataVerification|null Returns the verification record, or null if no person
     */
    public function verify(
        ApplicantAccount|Person $entity,
        VerifiableField|string $field,
        mixed $value,
        VerificationMethod|string $method,
        ?array $metadata = null,
        ?string $notes = null
    ): ?DataVerification {
        $fieldName = $field instanceof VerifiableField ? $field->value : $field;
        $methodEnum = $method instanceof VerificationMethod ? $method : VerificationMethod::tryFrom($method);

        // Get person from entity
        $person = $this->resolvePerson($entity);

        // Update entity-level verification timestamps
        $this->updateEntityTimestamps($entity, $fieldName);

        // If no person yet, can't create DataVerification record
        if (!$person) {
            return null;
        }

        // Check if already verified and locked
        $existing = DataVerification::where('applicant_id', $person->id)
            ->where('field_name', $fieldName)
            ->where('is_verified', true)
            ->first();

        // Allow RENAPO/SAT (official government sources) to override OCR-locked fields
        $isOfficialSource = in_array($methodEnum, [
            VerificationMethod::RENAPO,
            VerificationMethod::KYC_CURP_RENAPO,
            VerificationMethod::SAT,
            VerificationMethod::KYC_RFC_SAT,
        ]);

        if ($existing && $existing->is_locked && !$isOfficialSource) {
            return $existing;
        }

        // Determine if this method should lock the field
        $shouldLock = $methodEnum && $methodEnum->isAutomated();

        // Build metadata
        $fullMetadata = array_merge(
            ['verified_at' => now()->toIso8601String()],
            $metadata ?? []
        );

        // Build notes if not provided
        if (!$notes) {
            $notes = $this->generateNotes($fieldName, $methodEnum);
        }

        // Create or update verification record
        $verification = DataVerification::updateOrCreate(
            [
                'applicant_id' => $person->id,
                'field_name' => $fieldName,
            ],
            [
                'tenant_id' => $person->tenant_id,
                'field_value' => is_array($value) ? json_encode($value) : (string) $value,
                'method' => $methodEnum?->value ?? $method,
                'is_verified' => true,
                'is_locked' => $shouldLock,
                'status' => VerificationStatus::VERIFIED,
                'metadata' => $fullMetadata,
                'notes' => $notes,
            ]
        );

        // Sync verification status to related model (identification, etc.)
        $this->syncToRelatedModel($person, $fieldName, $methodEnum?->value ?? $method);

        return $verification;
    }

    /**
     * Verify multiple fields at once.
     *
     * @param ApplicantAccount|Person $entity
     * @param array $verifications Array of [field => [value, method, metadata, notes]] or [field => value]
     * @param VerificationMethod|string|null $defaultMethod Default method if not specified per field
     * @return array<string, DataVerification|null>
     */
    public function verifyBatch(
        ApplicantAccount|Person $entity,
        array $verifications,
        VerificationMethod|string|null $defaultMethod = null
    ): array {
        $results = [];

        foreach ($verifications as $field => $data) {
            if (is_array($data)) {
                $value = $data['value'] ?? $data[0] ?? null;
                $method = $data['method'] ?? $data[1] ?? $defaultMethod ?? VerificationMethod::API;
                $metadata = $data['metadata'] ?? $data[2] ?? null;
                $notes = $data['notes'] ?? $data[3] ?? null;
            } else {
                $value = $data;
                $method = $defaultMethod ?? VerificationMethod::API;
                $metadata = null;
                $notes = null;
            }

            $results[$field] = $this->verify($entity, $field, $value, $method, $metadata, $notes);
        }

        return $results;
    }

    /**
     * Check if a field is locked for an entity.
     */
    public function isLocked(ApplicantAccount|Person $entity, VerifiableField|string $field): bool
    {
        $person = $this->resolvePerson($entity);
        if (!$person) {
            return false;
        }

        $fieldName = $field instanceof VerifiableField ? $field->value : $field;

        return DataVerification::where('applicant_id', $person->id)
            ->where('field_name', $fieldName)
            ->where('is_locked', true)
            ->exists();
    }

    /**
     * Check if a field is verified for an entity.
     */
    public function isVerified(ApplicantAccount|Person $entity, VerifiableField|string $field): bool
    {
        $person = $this->resolvePerson($entity);
        if (!$person) {
            return false;
        }

        $fieldName = $field instanceof VerifiableField ? $field->value : $field;

        return DataVerification::where('applicant_id', $person->id)
            ->where('field_name', $fieldName)
            ->where('is_verified', true)
            ->exists();
    }

    /**
     * Get all locked fields for an entity.
     */
    public function getLockedFields(ApplicantAccount|Person $entity): array
    {
        $person = $this->resolvePerson($entity);
        if (!$person) {
            return [];
        }

        return DataVerification::where('applicant_id', $person->id)
            ->where('is_locked', true)
            ->pluck('field_name')
            ->toArray();
    }

    /**
     * Get verification summary for an entity.
     */
    public function getSummary(ApplicantAccount|Person $entity): array
    {
        $person = $this->resolvePerson($entity);
        if (!$person) {
            return ['total' => 0, 'verified' => 0, 'locked' => 0, 'fields' => []];
        }

        $verifications = DataVerification::where('applicant_id', $person->id)->get();

        $summary = [
            'total' => $verifications->count(),
            'verified' => $verifications->where('is_verified', true)->count(),
            'locked' => $verifications->where('is_locked', true)->count(),
            'pending' => $verifications->where('status', VerificationStatus::PENDING)->count(),
            'fields' => [],
        ];

        foreach ($verifications as $v) {
            $summary['fields'][$v->field_name] = [
                'verified' => $v->is_verified,
                'locked' => $v->is_locked,
                'method' => $v->method?->value,
                'status' => $v->status?->value,
            ];
        }

        return $summary;
    }

    /**
     * Check if entity has completed KYC (critical fields verified).
     */
    public function hasCompletedKyc(ApplicantAccount|Person $entity): bool
    {
        $person = $this->resolvePerson($entity);
        if (!$person) {
            return false;
        }

        $criticalFields = [
            VerifiableField::CURP->value,
            VerifiableField::FIRST_NAME->value,
            VerifiableField::LAST_NAME_1->value,
            VerifiableField::BIRTH_DATE->value,
        ];

        $verifiedCount = DataVerification::where('applicant_id', $person->id)
            ->whereIn('field_name', $criticalFields)
            ->where('is_verified', true)
            ->count();

        return $verifiedCount >= count($criticalFields);
    }

    /**
     * Update entity's KYC status if all critical fields are verified.
     */
    public function updateKycStatus(ApplicantAccount|Person $entity): bool
    {
        $person = $this->resolvePerson($entity);
        if (!$person || $person->kyc_verified_at) {
            return false;
        }

        if ($this->hasCompletedKyc($entity)) {
            $person->kyc_verified_at = now();
            $person->kyc_status = \App\Enums\KycStatus::VERIFIED;
            $person->save();
            return true;
        }

        return false;
    }

    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    /**
     * Sync verification status to related models (identifications, etc.).
     *
     * When KYC verifies a field, also update the corresponding model's status
     * so all data remains consistent across tables.
     */
    private function syncToRelatedModel(Person $person, string $fieldName, ?string $method): void
    {
        // Map field names to identification types
        $identificationMap = [
            'curp' => 'CURP',
            'rfc' => 'RFC',
            'ine_document_front' => 'INE',
            'ine_document_back' => 'INE',
            'ine_cic' => 'INE',
            'ine_clave' => 'INE',
        ];

        // Check if this field maps to an identification
        if (isset($identificationMap[$fieldName])) {
            $identType = $identificationMap[$fieldName];
            $identification = $person->identifications()
                ->where('type', $identType)
                ->where('is_current', true)
                ->first();

            if ($identification && $identification->status !== 'VERIFIED') {
                $identification->update([
                    'status' => 'VERIFIED',
                    'verified_at' => now(),
                    'verification_method' => $method,
                ]);
            }
        }

        // Person data fields - check if KYC should be marked complete
        $personFields = ['first_name', 'last_name_1', 'last_name_2', 'birth_date', 'gender', 'nationality', 'birth_state', 'birth_country'];
        $kycRelatedFields = array_merge(array_keys($identificationMap), $personFields);

        if (in_array($fieldName, $kycRelatedFields)) {
            // Check and update KYC status if all critical fields are verified
            $this->updateKycStatus($person);
        }

        // Handle face_match -> update selfie verification status if needed
        // (Documents are handled separately via DocumentController auto-approve)
    }

    /**
     * Resolve person from entity.
     */
    private function resolvePerson(ApplicantAccount|Person $entity): ?Person
    {
        if ($entity instanceof Person) {
            return $entity;
        }

        // Entity is ApplicantAccount, get their person
        return $entity->person;
    }

    /**
     * Update entity-level verification timestamps.
     */
    private function updateEntityTimestamps(ApplicantAccount|Person $entity, string $fieldName): void
    {
        if ($entity instanceof ApplicantAccount) {
            if ($fieldName === 'phone' && !$entity->phone_verified_at) {
                $entity->phone_verified_at = now();
                $entity->save();
            } elseif ($fieldName === 'email' && !$entity->email_verified_at) {
                $entity->email_verified_at = now();
                $entity->save();
            }

            // Also update person timestamps if exists
            if ($entity->person) {
                $this->updateEntityTimestamps($entity->person, $fieldName);
            }
        } elseif ($entity instanceof Person) {
            // Person doesn't have phone/email verified timestamps typically
            // but we can extend this if needed
        }
    }

    /**
     * Generate default notes based on field and method.
     */
    private function generateNotes(string $fieldName, ?VerificationMethod $method): string
    {
        $methodLabel = $method?->label() ?? 'verificación';

        $fieldLabels = [
            'phone' => 'Teléfono',
            'email' => 'Email',
            'curp' => 'CURP',
            'rfc' => 'RFC',
            'ine_cic' => 'INE',
            'ine_document_front' => 'INE Frente',
            'ine_document_back' => 'INE Reverso',
            'proof_of_address' => 'Comprobante de domicilio',
            'first_name' => 'Nombre',
            'last_name_1' => 'Apellido paterno',
            'last_name_2' => 'Apellido materno',
            'birth_date' => 'Fecha de nacimiento',
            'address' => 'Dirección',
            'street' => 'Calle',
            'exterior_number' => 'Número exterior',
            'colony' => 'Colonia',
            'city' => 'Ciudad',
            'state' => 'Estado',
            'postal_code' => 'Código postal',
        ];

        $fieldLabel = $fieldLabels[$fieldName] ?? $fieldName;

        return "{$fieldLabel} verificado vía {$methodLabel}";
    }

    // =========================================================================
    // DOCUMENT VERIFICATION
    // =========================================================================

    /**
     * Verify a document and optionally lock related fields.
     */
    public function verifyDocument(
        ApplicantAccount|Person $entity,
        string $documentType,
        string $documentId,
        VerificationMethod|string $method,
        ?array $metadata = null,
        ?array $fieldsToLock = null
    ): ?DataVerification {
        $fieldName = $this->documentTypeToFieldName($documentType);

        $verification = $this->verify(
            $entity,
            $fieldName,
            $documentId,
            $method,
            array_merge($metadata ?? [], [
                'document_id' => $documentId,
                'document_type' => $documentType,
            ])
        );

        if ($fieldsToLock && $verification) {
            $person = $this->resolvePerson($entity);
            if ($person) {
                foreach ($fieldsToLock as $field => $value) {
                    $this->verify($person, $field, $value, $method, [
                        'locked_by_document' => $documentType,
                        'document_id' => $documentId,
                    ]);
                }
            }
        }

        return $verification;
    }

    /**
     * Verify INE document (front or back) and lock identity fields.
     */
    public function verifyIneDocument(
        ApplicantAccount|Person $entity,
        string $side,
        string $documentId,
        array $ocrData = []
    ): ?DataVerification {
        $documentType = $side === 'front' ? 'INE_FRONT' : 'INE_BACK';

        $fieldsToLock = [];
        if ($side === 'front' && !empty($ocrData)) {
            if (!empty($ocrData['curp'])) {
                $fieldsToLock['curp'] = $ocrData['curp'];
            }
            if (!empty($ocrData['first_name'])) {
                $fieldsToLock['first_name'] = $ocrData['first_name'];
            }
            if (!empty($ocrData['last_name_1'])) {
                $fieldsToLock['last_name_1'] = $ocrData['last_name_1'];
            }
            if (!empty($ocrData['last_name_2'])) {
                $fieldsToLock['last_name_2'] = $ocrData['last_name_2'];
            }
            if (!empty($ocrData['birth_date'])) {
                $fieldsToLock['birth_date'] = $ocrData['birth_date'];
            }
        }

        return $this->verifyDocument(
            $entity,
            $documentType,
            $documentId,
            VerificationMethod::KYC_INE_OCR,
            [
                'ocr_data' => $ocrData,
                'ine_valid' => true,
                'auto_approved' => true,
            ],
            $fieldsToLock
        );
    }

    /**
     * Verify selfie document with face match validation.
     */
    public function verifySelfieDocument(
        ApplicantAccount|Person $entity,
        string $documentId,
        array $faceMatchData = []
    ): ?DataVerification {
        return $this->verifyDocument(
            $entity,
            'SELFIE',
            $documentId,
            VerificationMethod::KYC_FACE_MATCH,
            [
                'face_match_score' => $faceMatchData['face_match_score'] ?? null,
                'face_match_passed' => $faceMatchData['face_match_passed'] ?? false,
                'liveness_passed' => $faceMatchData['liveness_passed'] ?? null,
                'liveness_score' => $faceMatchData['liveness_score'] ?? null,
                'auto_approved' => true,
            ],
            []
        );
    }

    /**
     * Verify proof of address document and lock address fields.
     */
    public function verifyProofOfAddress(
        ApplicantAccount|Person $entity,
        string $documentId,
        array $addressData = []
    ): ?DataVerification {
        $fieldsToLock = [];
        if (!empty($addressData)) {
            $addressFields = ['street', 'exterior_number', 'interior_number', 'colony', 'city', 'state', 'postal_code'];
            foreach ($addressFields as $field) {
                if (!empty($addressData[$field])) {
                    $fieldsToLock[$field] = $addressData[$field];
                }
            }
        }

        return $this->verifyDocument(
            $entity,
            'PROOF_OF_ADDRESS',
            $documentId,
            VerificationMethod::DOCUMENT,
            ['address_data' => $addressData],
            $fieldsToLock
        );
    }

    /**
     * Convert document type to verification field name.
     */
    private function documentTypeToFieldName(string $documentType): string
    {
        $mapping = [
            'INE_FRONT' => 'ine_document_front',
            'INE_BACK' => 'ine_document_back',
            'PROOF_OF_ADDRESS' => 'proof_of_address',
            'SELFIE' => 'selfie_document',
            'INCOME_PROOF' => 'income_proof_document',
            'BANK_STATEMENT' => 'bank_statement_document',
        ];

        return $mapping[$documentType] ?? strtolower($documentType) . '_document';
    }
}
