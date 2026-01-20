<?php

namespace App\Transformers;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\VerificationMethod;
use App\Models\Application;
use App\Models\DataVerification;
use App\Models\User;

/**
 * Centralized transformer for Application responses.
 *
 * Single Responsibility: Transform Application models into API responses.
 * Eliminates duplication across ApplicationController and Admin\ApplicationController.
 */
class ApplicationTransformer
{
    /**
     * Transform application for basic list response.
     */
    public function toArray(Application $application): array
    {
        return [
            'id' => $application->id,
            'folio' => $application->folio,
            'status' => $application->status,
            'product' => $application->product ? [
                'id' => $application->product->id,
                'name' => $application->product->name,
                'type' => $application->product->type,
            ] : null,
            'requested_amount' => (float) $application->requested_amount,
            'approved_amount' => $application->approved_amount ? (float) $application->approved_amount : null,
            'term_months' => $application->term_months,
            'payment_frequency' => $application->payment_frequency,
            'interest_rate' => (float) $application->interest_rate,
            'monthly_payment' => (float) $application->monthly_payment,
            'total_to_pay' => (float) $application->total_to_pay,
            'purpose' => $application->purpose,
            'created_at' => $application->created_at->toIso8601String(),
            'updated_at' => $application->updated_at->toIso8601String(),
        ];
    }

    /**
     * Transform application for admin list response.
     */
    public function toArrayForAdmin(Application $application): array
    {
        return [
            'id' => $application->id,
            'folio' => $application->folio,
            'status' => $application->status,
            'applicant' => $application->applicant ? [
                'id' => $application->applicant->id,
                'name' => $application->applicant->full_name,
                'phone' => $application->applicant->phone,
                'email' => $application->applicant->email,
            ] : null,
            'product' => $application->product ? [
                'id' => $application->product->id,
                'name' => $application->product->name,
                'type' => $application->product->type,
            ] : null,
            'requested_amount' => (float) $application->requested_amount,
            'approved_amount' => $application->approved_amount ? (float) $application->approved_amount : null,
            'term_months' => $application->term_months,
            'payment_frequency' => $application->payment_frequency,
            'monthly_payment' => (float) $application->monthly_payment,
            'assigned_to' => $application->assignedAgent?->name,
            'risk_level' => $application->risk_level,
            'created_at' => $application->created_at->toIso8601String(),
            'updated_at' => $application->updated_at->toIso8601String(),
        ];
    }

    /**
     * Transform application with pending documents for applicant view.
     */
    public function toArrayWithPending(Application $application): array
    {
        $data = $this->toArray($application);

        $pendingDocs = $this->getPendingDocuments($application);
        if (!empty($pendingDocs)) {
            $data['pending_documents'] = $pendingDocs;
        }

        return $data;
    }

    /**
     * Transform application with full details for applicant view.
     */
    public function toDetailedArray(Application $application): array
    {
        $data = $this->toArray($application);

        $data['purpose_description'] = $application->purpose_description;
        $data['opening_commission'] = (float) $application->opening_commission;
        $data['rejection_reason'] = $application->rejection_reason;
        $data['assigned_to'] = $application->assignedAgent?->name;

        // Documents
        $data['documents'] = $application->documents->map(fn($doc) => [
            'id' => $doc->id,
            'type' => $doc->type instanceof DocumentType ? $doc->type->value : $doc->type,
            'name' => $doc->name ?? $doc->file_name,
            'status' => $doc->status instanceof DocumentStatus ? $doc->status->value : $doc->status,
            'rejection_reason' => $doc->rejection_reason,
            'uploaded_at' => $doc->created_at->toIso8601String(),
        ]);

        // Pending documents
        $data['pending_documents'] = collect($this->getPendingDocuments($application))->values();

        // References
        $data['references'] = $application->references->map(fn($ref) => [
            'id' => $ref->id,
            'full_name' => $ref->full_name,
            'relationship' => $ref->relationship,
            'phone' => $ref->phone,
            'verified' => $ref->is_verified,
        ]);

        // Status history (simplified for applicant)
        $data['status_history'] = collect($application->status_history ?? [])
            ->filter(fn($h) => isset($h['to']) && isset($h['timestamp']))
            ->map(fn($h) => [
                'status' => $h['to'],
                'timestamp' => $h['timestamp'],
            ])
            ->values();

        return $data;
    }

    /**
     * Transform application with full details for admin view.
     */
    public function toDetailedArrayForAdmin(Application $application): array
    {
        $applicant = $application->applicant;
        $primaryAddress = $applicant?->addresses?->where('is_primary', true)->first()
            ?? $applicant?->addresses?->first();
        $currentEmployment = $applicant?->currentEmployment;
        $primaryBankAccount = $applicant?->bankAccounts?->where('is_primary', true)->first()
            ?? $applicant?->bankAccounts?->first();

        // Preload data verifications indexed by field_name to avoid N+1 queries
        $dataVerificationsIndex = $this->buildVerificationsIndex($applicant);

        return [
            'id' => $application->id,
            'folio' => $application->folio,
            'status' => $application->status,
            'created_at' => $application->created_at->toIso8601String(),
            'updated_at' => $application->updated_at->toIso8601String(),
            'assigned_to' => $application->assignedAgent?->name,

            'applicant' => $this->formatApplicant($applicant),
            'address' => $this->formatAddress($primaryAddress),
            'addresses' => $this->formatAddressList($applicant?->addresses),
            'employment' => $this->formatEmployment($currentEmployment),
            'bank_account' => $this->formatBankAccount($primaryBankAccount),
            'bank_accounts' => $this->formatBankAccountList($applicant?->bankAccounts),
            'loan' => $this->formatLoanDetails($application),
            'risk' => $this->formatRiskData($application),
            'signature' => $this->formatSignature($applicant),
            'field_verifications' => $this->formatFieldVerificationsFromIndex($applicant, $dataVerificationsIndex),
            'verification' => $this->formatVerificationStatus($applicant, $primaryAddress, $currentEmployment),
            'required_documents' => $application->product?->required_documents ?? $application->product?->required_docs ?? [],
            'documents' => $this->formatDocumentsForAdminOptimized($application->documents, $dataVerificationsIndex),
            'references' => $this->formatReferences($application->references),
            'notes' => $this->formatNotes($application->notes),
            'timeline' => $this->formatTimeline($application),
            'rejection_reason' => $application->rejection_reason,
            'internal_notes' => $application->internal_notes,
            'disbursement_reference' => $application->disbursement_reference,
            'approved_at' => $application->approved_at?->toIso8601String(),
            'disbursed_at' => $application->disbursed_at?->toIso8601String(),
        ];
    }

    /**
     * Get pending documents for an application.
     */
    protected function getPendingDocuments(Application $application): array
    {
        $normalizeDoc = fn($t) => $t === 'RFC' ? 'RFC_CONSTANCIA' : $t;
        $requiredDocs = $application->product->required_docs ?? $application->product->required_documents ?? [];
        $uploadedTypes = $application->documents->pluck('type')
            ->map(fn($t) => $t instanceof DocumentType ? $t->value : $t)
            ->map($normalizeDoc)
            ->toArray();

        return collect($requiredDocs)
            ->filter(fn($type) => !in_array($normalizeDoc($type), $uploadedTypes))
            ->map(fn($type) => [
                'type' => $type,
                'label' => $this->getDocumentLabel($type),
                'description' => $this->getDocumentDescription($type),
                'required' => true,
            ])
            ->values()
            ->toArray();
    }

    /**
     * Get human-readable label for a document type.
     * Uses DocumentType enum for consistency.
     */
    public function getDocumentLabel(string $type): string
    {
        $docType = \App\Enums\DocumentType::tryFrom($type);
        if ($docType) {
            return $docType->label();
        }
        return ucwords(str_replace('_', ' ', strtolower($type)));
    }

    /**
     * Get description for a document type.
     * Uses DocumentType enum label as description.
     */
    public function getDocumentDescription(string $type): string
    {
        $docType = \App\Enums\DocumentType::tryFrom($type);
        if ($docType) {
            return $docType->description();
        }
        return 'Documento requerido';
    }

    /**
     * Format applicant data.
     */
    protected function formatApplicant($applicant): ?array
    {
        if (!$applicant) {
            return null;
        }

        return [
            'id' => $applicant->id,
            'full_name' => $applicant->full_name,
            'first_name' => $applicant->first_name,
            'last_name_1' => $applicant->last_name_1,
            'last_name_2' => $applicant->last_name_2,
            'email' => $applicant->email,
            'phone' => $applicant->phone,
            'phone_secondary' => $applicant->phone_secondary,
            'curp' => $applicant->curp,
            'rfc' => $applicant->rfc,
            'ine_clave' => $applicant->ine_clave,
            'birth_date' => $applicant->birth_date?->format('Y-m-d'),
            'nationality' => $applicant->nationality,
            'gender' => $applicant->gender,
            'marital_status' => $applicant->marital_status,
            'education_level' => $applicant->education_level,
            'dependents_count' => $applicant->dependents_count,
        ];
    }

    /**
     * Format address data.
     */
    protected function formatAddress($address): ?array
    {
        if (!$address) {
            return null;
        }

        return [
            'id' => $address->id,
            'type' => $address->type,
            'street' => $address->street,
            'ext_number' => $address->ext_number,
            'int_number' => $address->int_number,
            'neighborhood' => $address->neighborhood,
            'postal_code' => $address->postal_code,
            'city' => $address->city,
            'municipality' => $address->municipality,
            'state' => $address->state,
            'housing_type' => $address->housing_type,
            'housing_type_label' => $address->housing_type_label,
            'years_at_address' => $address->years_at_address,
            'months_at_address' => $address->months_at_address,
            'monthly_rent' => $address->monthly_rent ? (float) $address->monthly_rent : null,
            'is_verified' => $address->is_verified,
            'full_address' => $address->full_address,
        ];
    }

    /**
     * Format address list.
     */
    protected function formatAddressList($addresses): array
    {
        if (!$addresses) {
            return [];
        }

        return $addresses->map(fn($addr) => [
            'id' => $addr->id,
            'type' => $addr->type,
            'is_primary' => $addr->is_primary,
            'street' => $addr->street,
            'postal_code' => $addr->postal_code,
            'city' => $addr->city,
            'state' => $addr->state,
        ])->toArray();
    }

    /**
     * Format employment data.
     */
    protected function formatEmployment($employment): ?array
    {
        if (!$employment) {
            return null;
        }

        return [
            'id' => $employment->id,
            'employment_type' => $employment->employment_type?->value,
            'company_name' => $employment->company_name,
            'company_industry' => $employment->company_industry,
            'position' => $employment->position,
            'start_date' => $employment->start_date?->format('Y-m-d'),
            'seniority_months' => $employment->seniority_months,
            'contract_type' => $employment->contract_type,
            'monthly_income' => $employment->monthly_income ? (float) $employment->monthly_income : null,
            'monthly_net_income' => $employment->monthly_net_income ? (float) $employment->monthly_net_income : null,
            'payment_frequency' => $employment->payment_frequency,
            'other_income' => $employment->other_income ? (float) $employment->other_income : null,
            'other_income_source' => $employment->other_income_source,
            'is_verified' => $employment->is_verified,
        ];
    }

    /**
     * Format bank account data.
     */
    protected function formatBankAccount($bankAccount): ?array
    {
        if (!$bankAccount) {
            return null;
        }

        return [
            'id' => $bankAccount->id,
            'type' => $bankAccount->type,
            'bank_name' => $bankAccount->bank_name,
            'clabe' => $bankAccount->clabe,
            'account_type' => $bankAccount->account_type,
            'holder_name' => $bankAccount->holder_name,
            'is_own_account' => $bankAccount->is_own_account,
            'is_verified' => $bankAccount->is_verified,
        ];
    }

    /**
     * Format bank account list for admin.
     */
    protected function formatBankAccountList($bankAccounts): array
    {
        if (!$bankAccounts) {
            return [];
        }

        return $bankAccounts->where('is_active', true)->map(fn($ba) => [
            'id' => $ba->id,
            'type' => $ba->type,
            'bank_name' => $ba->bank_name,
            'bank_code' => $ba->bank_code,
            'clabe' => $ba->clabe,
            'account_type' => $ba->account_type,
            'account_type_label' => $ba->account_type_label,
            'holder_name' => $ba->holder_name,
            'holder_rfc' => $ba->holder_rfc,
            'is_primary' => $ba->is_primary,
            'is_own_account' => $ba->is_own_account,
            'is_verified' => $ba->is_verified,
            'created_at' => $ba->created_at?->toIso8601String(),
        ])->values()->toArray();
    }

    /**
     * Format loan details.
     */
    protected function formatLoanDetails(Application $application): array
    {
        return [
            'product_name' => $application->product?->name,
            'product_id' => $application->product?->id,
            'requested_amount' => (float) $application->requested_amount,
            'approved_amount' => $application->approved_amount ? (float) $application->approved_amount : null,
            'term_months' => $application->term_months,
            'payment_frequency' => $application->payment_frequency,
            'interest_rate' => (float) $application->interest_rate > 0
                ? (float) $application->interest_rate
                : (float) ($application->product?->annual_rate ?? 0),
            'opening_commission' => (float) $application->opening_commission > 0
                ? (float) $application->opening_commission
                : (float) ($application->product?->opening_commission_rate ?? 0),
            'monthly_payment' => (float) $application->monthly_payment,
            'total_to_pay' => (float) $application->total_to_pay,
            'cat' => $application->cat ? (float) $application->cat : null,
            'purpose' => $application->purpose,
            'purpose_description' => $application->purpose_description,
        ];
    }

    /**
     * Format risk data.
     */
    protected function formatRiskData(Application $application): array
    {
        return [
            'score' => $application->risk_score,
            'level' => $application->risk_level,
            'data' => $application->scoring_data,
        ];
    }

    /**
     * Format signature data.
     */
    protected function formatSignature($applicant): ?array
    {
        if (!$applicant) {
            return null;
        }

        return [
            'has_signed' => $applicant->hasSigned(),
            'signature_base64' => $applicant->signature_base64,
            'signature_date' => $applicant->signature_date?->toIso8601String(),
            'signature_ip' => $applicant->signature_ip,
        ];
    }

    /**
     * Format field verifications.
     */
    protected function formatFieldVerifications($applicant): array
    {
        if (!$applicant?->dataVerifications) {
            return [];
        }

        return $applicant->dataVerifications
            ->groupBy('field_name')
            ->map(fn($verifications) => $verifications->sortByDesc('created_at')->first())
            ->mapWithKeys(fn($v) => [
                $v->field_name => [
                    'verified' => $v->is_verified,
                    'method' => $v->method instanceof VerificationMethod ? $v->method->value : $v->method,
                    'method_label' => $v->method?->label() ?? null,
                    'verified_at' => $v->created_at?->toIso8601String(),
                    'verified_by' => $v->verifier?->name,
                    'notes' => $v->notes,
                    'rejection_reason' => $v->rejection_reason,
                    'status' => $v->status instanceof \BackedEnum ? $v->status->value : $v->status,
                    'is_locked' => $v->is_locked ?? false,
                    'metadata' => $v->metadata,
                ]
            ])
            ->toArray();
    }

    /**
     * Format verification status (legacy).
     */
    protected function formatVerificationStatus($applicant, $primaryAddress, $currentEmployment): array
    {
        return [
            'phone_verified' => $applicant?->phone_verified_at !== null,
            'phone_verified_at' => $applicant?->phone_verified_at?->toIso8601String(),
            'email_verified' => $applicant?->email_verified_at !== null,
            'email_verified_at' => $applicant?->email_verified_at?->toIso8601String(),
            'identity_verified' => $applicant?->identity_verified_at !== null,
            'identity_verified_at' => $applicant?->identity_verified_at?->toIso8601String(),
            'address_verified' => $primaryAddress?->is_verified ?? false,
            'employment_verified' => $currentEmployment?->is_verified ?? false,
        ];
    }

    /**
     * Format documents for admin view with KYC lock detection.
     */
    protected function formatDocumentsForAdmin($documents, $applicant): array
    {
        return $documents->map(function ($doc) use ($applicant) {
            $docType = $doc->type instanceof DocumentType ? $doc->type->value : $doc->type;
            $metadata = $doc->metadata ?? [];

            $isKycLocked = $this->isDocumentKycLocked($doc, $docType, $metadata, $applicant);

            // Enhance metadata with face match info if available
            if ($isKycLocked && $docType === 'SELFIE' && $applicant) {
                $metadata = $this->enrichSelfieMetadata($applicant, $metadata);
            }

            return [
                'id' => $doc->id,
                'type' => $docType,
                'name' => $doc->name ?? $doc->file_name,
                'status' => $doc->status instanceof DocumentStatus ? $doc->status->value : $doc->status,
                'rejection_reason' => $doc->rejection_reason,
                                'uploaded_at' => $doc->created_at->toIso8601String(),
                'reviewed_at' => $doc->reviewed_at?->toIso8601String(),
                'mime_type' => $doc->mime_type,
                'metadata' => $metadata,
                'is_kyc_locked' => $isKycLocked,
            ];
        })->toArray();
    }

    /**
     * Check if a document is KYC locked.
     */
    protected function isDocumentKycLocked($doc, string $docType, array $metadata, $applicant): bool
    {
        // Check metadata flags
        if (
            ($metadata['kyc_validated'] ?? false) === true ||
            ($metadata['nubarium_validated'] ?? false) === true ||
            ($metadata['ine_valid'] ?? false) === true ||
            ($metadata['face_match_passed'] ?? false) === true ||
            ($metadata['face_match'] ?? false) === true ||
            ($metadata['validated_by_kyc'] ?? false) === true ||
            ($metadata['source'] ?? '') === 'kyc' ||
            ($metadata['source'] ?? '') === 'nubarium' ||
            in_array($metadata['validation_method'] ?? '', ['KYC_INE_OCR', 'KYC_FACE_MATCH']) ||
            ($docType === 'SELFIE' && isset($metadata['face_match_score']) && $metadata['face_match_score'] > 0)
        ) {
            return true;
        }

        if (!$applicant) {
            return false;
        }

        // Check data_verifications for selfie/face_match
        if ($docType === 'SELFIE') {
            return DataVerification::where('applicant_id', $applicant->id)
                ->whereIn('field_name', ['selfie_document', 'face_match'])
                ->where('is_verified', true)
                ->where('is_locked', true)
                ->exists();
        }

        // Check data_verifications for INE documents
        if (in_array($docType, ['INE_FRONT', 'INE_BACK'])) {
            $fieldName = $docType === 'INE_FRONT' ? 'ine_document_front' : 'ine_document_back';
            return DataVerification::where('applicant_id', $applicant->id)
                ->where('field_name', $fieldName)
                ->where('is_verified', true)
                ->where('is_locked', true)
                ->exists();
        }

        return false;
    }

    /**
     * Enrich selfie metadata with face match info.
     */
    protected function enrichSelfieMetadata($applicant, array $metadata): array
    {
        $selfieVerification = DataVerification::where('applicant_id', $applicant->id)
            ->whereIn('field_name', ['selfie_document', 'face_match'])
            ->where('is_verified', true)
            ->where('is_locked', true)
            ->first();

        if ($selfieVerification) {
            $verificationMeta = $selfieVerification->metadata ?? [];
            if (isset($verificationMeta['face_match_score']) || isset($verificationMeta['score'])) {
                $metadata['face_match_score'] = $verificationMeta['face_match_score'] ?? $verificationMeta['score'] ?? null;
                $metadata['face_match_passed'] = true;
            }
        }

        return $metadata;
    }

    /**
     * Format references.
     */
    protected function formatReferences($references): array
    {
        return $references->map(fn($ref) => [
            'id' => $ref->id,
            'full_name' => $ref->full_name,
            'relationship' => $ref->relationship,
            'phone' => $ref->phone,
            'verified' => $ref->is_verified,
            'verification_result' => $ref->verification_result,
            'verification_notes' => $ref->verification_notes,
            'verified_at' => $ref->verified_at?->toIso8601String(),
        ])->toArray();
    }

    /**
     * Format notes.
     */
    protected function formatNotes($notes): array
    {
        return $notes->map(fn($note) => [
            'id' => $note->id,
            'text' => $note->content,
            'author' => $note->user?->name ?? 'System',
            'is_internal' => $note->is_internal,
            'created_at' => $note->created_at->toIso8601String(),
        ])->toArray();
    }

    /**
     * Format timeline from status history.
     */
    protected function formatTimeline(Application $application): array
    {
        $history = collect($application->status_history ?? []);

        // Load all users at once to avoid N+1 queries
        $userIds = $history->pluck('user_id')->filter()->unique()->values()->toArray();
        $users = !empty($userIds)
            ? User::whereIn('id', $userIds)->get()->keyBy('id')
            : collect();

        return $history
            ->reverse()
            ->values()
            ->map(function ($h, $i) use ($users) {
                $author = 'System';
                if (isset($h['user_id']) && $users->has($h['user_id'])) {
                    $author = $users[$h['user_id']]->name;
                }

                return [
                    'id' => (string) ($i + 1),
                    'action' => $h['action'] ?? 'STATUS_CHANGE',
                    'description' => $this->getTimelineDescription($h),
                    'author' => $author,
                    'created_at' => $h['timestamp'] ?? now()->toIso8601String(),
                    'metadata' => [
                        'ip_address' => $h['ip_address'] ?? null,
                        'user_agent' => $h['user_agent'] ?? null,
                        'location' => $h['location'] ?? null,
                        'old_value' => $h['old_value'] ?? null,
                        'new_value' => $h['new_value'] ?? null,
                        'changes' => $h['changes'] ?? null,
                        'reason' => $h['reason'] ?? null,
                        'is_replacement' => $h['is_replacement'] ?? false,
                        'old_file' => $h['old_file'] ?? null,
                        'new_file' => $h['new_file'] ?? null,
                        'document_label' => $h['document_label'] ?? null,
                    ],
                ];
            })
            ->toArray();
    }

    /**
     * Get timeline description from history entry.
     */
    protected function getTimelineDescription(array $h): string
    {
        $action = $h['action'] ?? 'STATUS_CHANGE';

        return match ($action) {
            'STATUS_CHANGE' => isset($h['from'], $h['to'])
                ? "Estado cambiado de {$h['from']} a {$h['to']}"
                : "Estado actualizado",
            'DOCUMENT_UPLOAD' => "Documento subido: " . ($h['document_label'] ?? $h['document_type'] ?? 'documento'),
            'DOCUMENT_REPLACE' => "Documento reemplazado: " . ($h['document_label'] ?? $h['document_type'] ?? 'documento'),
            'DOCUMENT_REVIEW' => "Documento revisado: " . ($h['document_label'] ?? 'documento'),
            'REFERENCE_VERIFY' => "Referencia verificada",
            'ASSIGNED' => "Asignado a " . ($h['assigned_to'] ?? 'analista'),
            'NOTE_ADDED' => "Nota agregada",
            'DATA_VERIFIED' => "Datos verificados: " . ($h['field_name'] ?? 'campo'),
            default => $h['description'] ?? "Actividad registrada",
        };
    }

    /**
     * Build an indexed array of data verifications for O(1) lookups.
     * Avoids N+1 queries when checking KYC status per document.
     */
    protected function buildVerificationsIndex($applicant): array
    {
        if (!$applicant) {
            return [];
        }

        // Load all verifications once, indexed by field_name
        $verifications = $applicant->dataVerifications ?? collect();

        return $verifications
            ->filter(fn($v) => $v->is_verified && $v->is_locked)
            ->keyBy('field_name')
            ->toArray();
    }

    /**
     * Format field verifications using pre-built index.
     */
    protected function formatFieldVerificationsFromIndex($applicant, array $verificationsIndex): array
    {
        if (!$applicant?->dataVerifications) {
            return [];
        }

        return $applicant->dataVerifications
            ->groupBy('field_name')
            ->map(fn($verifications) => $verifications->sortByDesc('created_at')->first())
            ->mapWithKeys(fn($v) => [
                $v->field_name => [
                    'verified' => $v->is_verified,
                    'method' => $v->method instanceof VerificationMethod ? $v->method->value : $v->method,
                    'method_label' => $v->method?->label() ?? null,
                    'verified_at' => $v->created_at?->toIso8601String(),
                    'verified_by' => $v->verifier?->name,
                    'notes' => $v->notes,
                    'rejection_reason' => $v->rejection_reason,
                    'status' => $v->status instanceof \BackedEnum ? $v->status->value : $v->status,
                    'is_locked' => $v->is_locked ?? false,
                    'metadata' => $v->metadata,
                ]
            ])
            ->toArray();
    }

    /**
     * Format documents for admin view using pre-built verifications index.
     * Optimized to avoid N+1 queries - O(n) instead of O(n²).
     */
    protected function formatDocumentsForAdminOptimized($documents, array $verificationsIndex): array
    {
        return $documents->map(function ($doc) use ($verificationsIndex) {
            $docType = $doc->type instanceof DocumentType ? $doc->type->value : $doc->type;
            $metadata = $doc->metadata ?? [];

            // Check KYC lock using pre-loaded index (O(1) lookup)
            $isKycLocked = $this->isDocumentKycLockedOptimized($docType, $metadata, $verificationsIndex);

            // Enhance metadata with face match info if available
            if ($isKycLocked && $docType === 'SELFIE') {
                $metadata = $this->enrichSelfieMetadataFromIndex($verificationsIndex, $metadata);
            }

            return [
                'id' => $doc->id,
                'type' => $docType,
                'name' => $doc->name ?? $doc->file_name,
                'status' => $doc->status instanceof DocumentStatus ? $doc->status->value : $doc->status,
                'rejection_reason' => $doc->rejection_reason,
                                'uploaded_at' => $doc->created_at->toIso8601String(),
                'reviewed_at' => $doc->reviewed_at?->toIso8601String(),
                'mime_type' => $doc->mime_type,
                'metadata' => $metadata,
                'is_kyc_locked' => $isKycLocked,
            ];
        })->toArray();
    }

    /**
     * Check if a document is KYC locked using pre-built index (O(1)).
     */
    protected function isDocumentKycLockedOptimized(string $docType, array $metadata, array $verificationsIndex): bool
    {
        // First check metadata flags (fast path)
        if (
            ($metadata['kyc_validated'] ?? false) === true ||
            ($metadata['nubarium_validated'] ?? false) === true ||
            ($metadata['ine_valid'] ?? false) === true ||
            ($metadata['face_match_passed'] ?? false) === true ||
            ($metadata['face_match'] ?? false) === true ||
            ($metadata['validated_by_kyc'] ?? false) === true ||
            ($metadata['source'] ?? '') === 'kyc' ||
            ($metadata['source'] ?? '') === 'nubarium' ||
            in_array($metadata['validation_method'] ?? '', ['KYC_INE_OCR', 'KYC_FACE_MATCH']) ||
            ($docType === 'SELFIE' && isset($metadata['face_match_score']) && $metadata['face_match_score'] > 0)
        ) {
            return true;
        }

        // Check using pre-loaded index - O(1) lookups instead of DB queries
        if ($docType === 'SELFIE') {
            return isset($verificationsIndex['selfie_document']) || isset($verificationsIndex['face_match']);
        }

        if ($docType === 'INE_FRONT') {
            return isset($verificationsIndex['ine_document_front']);
        }

        if ($docType === 'INE_BACK') {
            return isset($verificationsIndex['ine_document_back']);
        }

        return false;
    }

    /**
     * Enrich selfie metadata using pre-built index (no DB query).
     */
    protected function enrichSelfieMetadataFromIndex(array $verificationsIndex, array $metadata): array
    {
        $selfieVerification = $verificationsIndex['selfie_document'] ?? $verificationsIndex['face_match'] ?? null;

        if ($selfieVerification) {
            $verificationMeta = $selfieVerification['metadata'] ?? [];
            if (isset($verificationMeta['face_match_score']) || isset($verificationMeta['score'])) {
                $metadata['face_match_score'] = $verificationMeta['face_match_score'] ?? $verificationMeta['score'] ?? null;
                $metadata['face_match_passed'] = true;
            }
        }

        return $metadata;
    }
}
