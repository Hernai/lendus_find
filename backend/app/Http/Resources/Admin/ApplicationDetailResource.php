<?php

namespace App\Http\Resources\Admin;

use App\Models\Application;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API Resource for detailed Application view (Admin panel).
 *
 * Formats application with all related data for admin review.
 */
class ApplicationDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Application $application */
        $application = $this->resource;
        $applicant = $application->applicant;

        $primaryAddress = $applicant?->addresses?->where('is_primary', true)->first()
            ?? $applicant?->addresses?->first();
        $currentEmployment = $applicant?->currentEmployment;
        $primaryBankAccount = $applicant?->bankAccounts?->where('is_primary', true)->first()
            ?? $applicant?->bankAccounts?->first();

        return [
            'id' => $application->id,
            'folio' => $application->folio,
            'status' => $application->status,
            'created_at' => $application->created_at->toIso8601String(),
            'updated_at' => $application->updated_at->toIso8601String(),
            'assigned_to' => $application->assignedAgent?->name,

            // Applicant data
            'applicant' => $this->formatApplicant($applicant),

            // Primary Address
            'address' => $this->formatAddress($primaryAddress),

            // All addresses
            'addresses' => $this->formatAddresses($applicant?->addresses),

            // Current Employment
            'employment' => $this->formatEmployment($currentEmployment),

            // Primary Bank Account
            'bank_account' => $this->formatBankAccount($primaryBankAccount),

            // All Bank Accounts
            'bank_accounts' => $this->formatBankAccounts($applicant?->bankAccounts),

            // Loan details
            'loan' => $this->formatLoanDetails($application),

            // Risk scoring
            'risk' => [
                'score' => $application->risk_score,
                'level' => $application->risk_level,
                'data' => $application->scoring_data,
            ],

            // Signature data
            'signature' => $this->formatSignature($applicant),

            // Field-level verifications
            'field_verifications' => $this->formatFieldVerifications($applicant),

            // Legacy verification status
            'verification' => $this->formatVerificationStatus($applicant, $primaryAddress, $currentEmployment),

            // Required documents from product
            'required_documents' => $application->product?->required_documents
                ?? $application->product?->required_docs
                ?? [],

            // Documents
            'documents' => $this->formatDocuments($application->documents, $applicant),

            // References
            'references' => $this->formatReferences($application->references),

            // Notes
            'notes' => $this->formatNotes($application->notes),

            // Timeline
            'timeline' => $this->formatTimeline($application),

            // Additional data
            'rejection_reason' => $application->rejection_reason,
            'internal_notes' => $application->internal_notes,
            'disbursement_reference' => $application->disbursement_reference,
            'approved_at' => $application->approved_at?->toIso8601String(),
            'disbursed_at' => $application->disbursed_at?->toIso8601String(),
        ];
    }

    private function formatApplicant($applicant): ?array
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

    private function formatAddress($address): ?array
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

    private function formatAddresses($addresses): array
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

    private function formatEmployment($employment): ?array
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

    private function formatBankAccount($bankAccount): ?array
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

    private function formatBankAccounts($bankAccounts): array
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

    private function formatLoanDetails($application): array
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

    private function formatSignature($applicant): ?array
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

    private function formatFieldVerifications($applicant): array
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
                    'method' => $v->method instanceof \App\Enums\VerificationMethod ? $v->method->value : $v->method,
                    'method_label' => $v->method?->label() ?? null,
                    'verified_at' => $v->created_at?->toIso8601String(),
                    'verified_by' => $v->verifier?->name,
                    'notes' => $v->notes,
                    'rejection_reason' => $v->rejection_reason,
                    'status' => $v->status instanceof \BackedEnum ? $v->status->value : $v->status,
                    'is_locked' => $v->is_locked ?? false,
                    'metadata' => $v->metadata,
                ]
            ])->toArray();
    }

    private function formatVerificationStatus($applicant, $primaryAddress, $currentEmployment): array
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

    private function formatDocuments($documents, $applicant): array
    {
        return $documents->map(function ($doc) use ($applicant) {
            $docType = $doc->type instanceof \App\Enums\DocumentType ? $doc->type->value : $doc->type;
            $metadata = $doc->metadata ?? [];

            $isKycLocked = $this->checkDocumentKycLock($doc, $docType, $metadata, $applicant);

            return [
                'id' => $doc->id,
                'type' => $docType,
                'name' => $doc->name ?? $doc->file_name,
                'status' => $doc->status instanceof \App\Enums\DocumentStatus ? $doc->status->value : $doc->status,
                'rejection_reason' => $doc->rejection_reason,
                'uploaded_at' => $doc->created_at->toIso8601String(),
                'reviewed_at' => $doc->reviewed_at?->toIso8601String(),
                'mime_type' => $doc->mime_type,
                'metadata' => $metadata,
                'is_kyc_locked' => $isKycLocked,
            ];
        })->toArray();
    }

    private function checkDocumentKycLock($doc, string $docType, array &$metadata, $applicant): bool
    {
        // Check metadata for KYC validation flags
        $isKycLocked = (
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
        );

        // Usar la relación ya cargada en lugar de queries adicionales (evita N+1)
        $verifications = $applicant?->dataVerifications;

        // Check data_verifications for selfie/face_match
        if (!$isKycLocked && $docType === 'SELFIE' && $verifications) {
            $selfieVerification = $verifications
                ->whereIn('field_name', ['selfie_document', 'face_match'])
                ->where('is_verified', true)
                ->where('is_locked', true)
                ->first();

            if ($selfieVerification) {
                $isKycLocked = true;
                $verificationMeta = $selfieVerification->metadata ?? [];
                if (isset($verificationMeta['face_match_score']) || isset($verificationMeta['score'])) {
                    $metadata['face_match_score'] = $verificationMeta['face_match_score'] ?? $verificationMeta['score'] ?? null;
                    $metadata['face_match_passed'] = true;
                }
            }
        }

        // Check data_verifications for INE documents
        if (!$isKycLocked && in_array($docType, ['INE_FRONT', 'INE_BACK']) && $verifications) {
            $fieldName = $docType === 'INE_FRONT' ? 'ine_document_front' : 'ine_document_back';
            $ineVerification = $verifications
                ->where('field_name', $fieldName)
                ->where('is_verified', true)
                ->where('is_locked', true)
                ->first();
            $isKycLocked = $ineVerification !== null;
        }

        return $isKycLocked;
    }

    private function formatReferences($references): array
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

    private function formatNotes($notes): array
    {
        return $notes->map(fn($note) => [
            'id' => $note->id,
            'text' => $note->content,
            'author' => $note->user?->name ?? 'System',
            'is_internal' => $note->is_internal,
            'created_at' => $note->created_at->toIso8601String(),
        ])->toArray();
    }

    private function formatTimeline($application): array
    {
        $history = collect($application->status_history ?? []);

        $userIds = $history->pluck('user_id')->filter()->unique()->values()->toArray();
        // Filtrar por tenant para seguridad
        $users = !empty($userIds)
            ? User::where('tenant_id', $application->tenant_id)
                ->whereIn('id', $userIds)
                ->get()
                ->keyBy('id')
            : collect();

        return $history
            ->reverse()
            ->values()
            ->map(function($h, $i) use ($users) {
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
            })->toArray();
    }

    private function getTimelineDescription(array $historyEntry): string
    {
        $action = $historyEntry['action'] ?? 'STATUS_CHANGE';

        return match ($action) {
            'STATUS_CHANGE' => sprintf(
                'Estado cambiado de %s a %s',
                $historyEntry['old_value'] ?? 'N/A',
                $historyEntry['new_value'] ?? 'N/A'
            ),
            'DOCUMENT_UPLOAD' => 'Documento subido: ' . ($historyEntry['document_label'] ?? 'Documento'),
            'DOCUMENT_REPLACE' => 'Documento reemplazado: ' . ($historyEntry['document_label'] ?? 'Documento'),
            'DOCUMENT_APPROVED' => 'Documento aprobado: ' . ($historyEntry['document_label'] ?? 'Documento'),
            'DOCUMENT_REJECTED' => 'Documento rechazado: ' . ($historyEntry['document_label'] ?? 'Documento'),
            'NOTE_ADDED' => 'Nota agregada',
            'ASSIGNED' => 'Asignado a ' . ($historyEntry['new_value'] ?? 'agente'),
            'COUNTER_OFFER' => 'Contraoferta enviada',
            default => $historyEntry['description'] ?? 'Acción: ' . $action,
        };
    }
}
