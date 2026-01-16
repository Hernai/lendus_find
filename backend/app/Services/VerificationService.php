<?php

namespace App\Services;

use App\Enums\VerifiableField;
use App\Enums\VerificationMethod;
use App\Enums\VerificationStatus;
use App\Models\Applicant;
use App\Models\DataVerification;
use App\Models\User;

class VerificationService
{
    /**
     * Verify and lock a field for an entity (User or Applicant).
     *
     * @param User|Applicant $entity The entity to verify
     * @param VerifiableField|string $field The field being verified
     * @param mixed $value The verified value
     * @param VerificationMethod|string $method How it was verified (OTP, RENAPO, SAT, INE, etc.)
     * @param array|null $metadata Additional data about the verification
     * @param string|null $notes Optional notes
     * @return DataVerification|null Returns the verification record, or null if no applicant
     */
    public function verify(
        User|Applicant $entity,
        VerifiableField|string $field,
        mixed $value,
        VerificationMethod|string $method,
        ?array $metadata = null,
        ?string $notes = null
    ): ?DataVerification {
        $fieldName = $field instanceof VerifiableField ? $field->value : $field;
        $methodEnum = $method instanceof VerificationMethod ? $method : VerificationMethod::tryFrom($method);

        // Get applicant from entity
        $applicant = $this->resolveApplicant($entity);

        // Update entity-level verification timestamps
        $this->updateEntityTimestamps($entity, $fieldName);

        // If no applicant yet, can't create DataVerification record
        if (!$applicant) {
            return null;
        }

        // Check if already verified and locked
        $existing = DataVerification::where('applicant_id', $applicant->id)
            ->where('field_name', $fieldName)
            ->where('is_verified', true)
            ->first();

        // Allow RENAPO/SAT (official government sources) to override OCR-locked fields
        // RENAPO data is the authoritative source and should take precedence over OCR which can have errors
        $isOfficialSource = in_array($methodEnum, [
            VerificationMethod::RENAPO,
            VerificationMethod::KYC_CURP_RENAPO,
            VerificationMethod::SAT,
            VerificationMethod::KYC_RFC_SAT,
        ]);

        if ($existing && $existing->is_locked && !$isOfficialSource) {
            return $existing; // Already verified and locked, and new method is not official source
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
        return DataVerification::updateOrCreate(
            [
                'applicant_id' => $applicant->id,
                'field_name' => $fieldName,
            ],
            [
                'tenant_id' => $applicant->tenant_id,
                'field_value' => is_array($value) ? json_encode($value) : (string) $value,
                'method' => $methodEnum?->value ?? $method,
                'is_verified' => true,
                'is_locked' => $shouldLock,
                'status' => VerificationStatus::VERIFIED,
                'metadata' => $fullMetadata,
                'notes' => $notes,
            ]
        );
    }

    /**
     * Verify multiple fields at once.
     *
     * @param User|Applicant $entity
     * @param array $verifications Array of [field => [value, method, metadata, notes]] or [field => value]
     * @param VerificationMethod|string|null $defaultMethod Default method if not specified per field
     * @return array<string, DataVerification|null>
     */
    public function verifyBatch(
        User|Applicant $entity,
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
     *
     * @param User|Applicant $entity
     * @param VerifiableField|string $field
     * @return bool
     */
    public function isLocked(User|Applicant $entity, VerifiableField|string $field): bool
    {
        $applicant = $this->resolveApplicant($entity);
        if (!$applicant) {
            return false;
        }

        $fieldName = $field instanceof VerifiableField ? $field->value : $field;

        return DataVerification::where('applicant_id', $applicant->id)
            ->where('field_name', $fieldName)
            ->where('is_locked', true)
            ->exists();
    }

    /**
     * Check if a field is verified for an entity.
     *
     * @param User|Applicant $entity
     * @param VerifiableField|string $field
     * @return bool
     */
    public function isVerified(User|Applicant $entity, VerifiableField|string $field): bool
    {
        $applicant = $this->resolveApplicant($entity);
        if (!$applicant) {
            return false;
        }

        $fieldName = $field instanceof VerifiableField ? $field->value : $field;

        return DataVerification::where('applicant_id', $applicant->id)
            ->where('field_name', $fieldName)
            ->where('is_verified', true)
            ->exists();
    }

    /**
     * Get all locked fields for an entity.
     *
     * @param User|Applicant $entity
     * @return array
     */
    public function getLockedFields(User|Applicant $entity): array
    {
        $applicant = $this->resolveApplicant($entity);
        if (!$applicant) {
            return [];
        }

        return DataVerification::where('applicant_id', $applicant->id)
            ->where('is_locked', true)
            ->pluck('field_name')
            ->toArray();
    }

    /**
     * Get verification summary for an entity.
     *
     * @param User|Applicant $entity
     * @return array
     */
    public function getSummary(User|Applicant $entity): array
    {
        $applicant = $this->resolveApplicant($entity);
        if (!$applicant) {
            return ['total' => 0, 'verified' => 0, 'locked' => 0, 'fields' => []];
        }

        $verifications = DataVerification::where('applicant_id', $applicant->id)->get();

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
     *
     * @param User|Applicant $entity
     * @return bool
     */
    public function hasCompletedKyc(User|Applicant $entity): bool
    {
        $criticalFields = [
            VerifiableField::CURP->value,
            VerifiableField::FIRST_NAME->value,
            VerifiableField::LAST_NAME_1->value,
            VerifiableField::BIRTH_DATE->value,
        ];

        foreach ($criticalFields as $field) {
            if (!$this->isVerified($entity, $field)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Update entity's KYC status if all critical fields are verified.
     *
     * @param User|Applicant $entity
     * @return bool Whether status was updated
     */
    public function updateKycStatus(User|Applicant $entity): bool
    {
        $applicant = $this->resolveApplicant($entity);
        if (!$applicant || $applicant->kyc_verified_at) {
            return false;
        }

        if ($this->hasCompletedKyc($entity)) {
            $applicant->kyc_verified_at = now();
            $applicant->kyc_status = \App\Enums\KycStatus::VERIFIED;
            $applicant->save();
            return true;
        }

        return false;
    }

    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    /**
     * Resolve applicant from entity.
     */
    private function resolveApplicant(User|Applicant $entity): ?Applicant
    {
        if ($entity instanceof Applicant) {
            return $entity;
        }

        // Entity is User, get their applicant
        return $entity->applicant;
    }

    /**
     * Update entity-level verification timestamps.
     */
    private function updateEntityTimestamps(User|Applicant $entity, string $fieldName): void
    {
        if ($entity instanceof User) {
            if ($fieldName === 'phone' && !$entity->phone_verified_at) {
                $entity->phone_verified_at = now();
                $entity->save();
            } elseif ($fieldName === 'email' && !$entity->email_verified_at) {
                $entity->email_verified_at = now();
                $entity->save();
            }

            // Also update applicant timestamps if exists
            if ($entity->applicant) {
                $this->updateEntityTimestamps($entity->applicant, $fieldName);
            }
        } elseif ($entity instanceof Applicant) {
            if ($fieldName === 'phone' && !$entity->phone_verified_at) {
                $entity->phone_verified_at = now();
                $entity->save();
            } elseif ($fieldName === 'email' && !$entity->email_verified_at) {
                $entity->email_verified_at = now();
                $entity->save();
            }
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
     *
     * @param User|Applicant $entity
     * @param string $documentType Type of document (INE_FRONT, INE_BACK, PROOF_OF_ADDRESS, etc.)
     * @param string $documentId The document ID
     * @param VerificationMethod|string $method Verification method
     * @param array|null $metadata Additional metadata (OCR data, etc.)
     * @param array|null $fieldsToLock Additional fields to lock based on this document
     * @return DataVerification|null
     */
    public function verifyDocument(
        User|Applicant $entity,
        string $documentType,
        string $documentId,
        VerificationMethod|string $method,
        ?array $metadata = null,
        ?array $fieldsToLock = null
    ): ?DataVerification {
        // Normalize document type to field name
        $fieldName = $this->documentTypeToFieldName($documentType);

        // Verify the document itself
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

        // Lock additional fields if specified
        if ($fieldsToLock && $verification) {
            $applicant = $this->resolveApplicant($entity);
            if ($applicant) {
                foreach ($fieldsToLock as $field => $value) {
                    $this->verify($applicant, $field, $value, $method, [
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
     *
     * @param User|Applicant $entity
     * @param string $side 'front' or 'back'
     * @param string $documentId
     * @param array $ocrData OCR extracted data
     * @return DataVerification|null
     */
    public function verifyIneDocument(
        User|Applicant $entity,
        string $side,
        string $documentId,
        array $ocrData = []
    ): ?DataVerification {
        $documentType = $side === 'front' ? 'INE_FRONT' : 'INE_BACK';

        // Fields to lock from INE front
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
     *
     * @param User|Applicant $entity
     * @param string $documentId
     * @param array $faceMatchData Face match validation data (score, threshold, etc.)
     * @return DataVerification|null
     */
    public function verifySelfieDocument(
        User|Applicant $entity,
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
            [] // No fields to lock from selfie
        );
    }

    /**
     * Verify proof of address document and lock address fields.
     *
     * @param User|Applicant $entity
     * @param string $documentId
     * @param array $addressData Extracted address data
     * @return DataVerification|null
     */
    public function verifyProofOfAddress(
        User|Applicant $entity,
        string $documentId,
        array $addressData = []
    ): ?DataVerification {
        // Fields to lock from proof of address
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
