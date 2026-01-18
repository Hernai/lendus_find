<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\ApplicationStatus;
use App\Enums\AuditAction;
use App\Enums\VerificationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\VerifyDataRequest;
use App\Http\Requests\Admin\VerifyReferenceRequest;
use App\Models\Application;
use App\Models\AuditLog;
use App\Models\BankAccount;
use App\Models\DataVerification;
use App\Models\Reference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller for verification actions on applications.
 *
 * Handles reference verification, bank account verification, and data field verification.
 */
class ApplicationVerificationController extends Controller
{
    /**
     * Verify a reference.
     */
    public function verifyReference(VerifyReferenceRequest $request, Application $application, Reference $reference): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        if ($application->tenant_id !== $tenant->id || $reference->application_id !== $application->id) {
            return response()->json(['message' => 'Referencia no encontrada'], 404);
        }

        $reference->is_verified = $request->result === 'VERIFIED';
        $reference->verification_result = $request->result;
        $reference->verification_notes = $request->notes;
        $reference->verified_by = $request->user()->id;
        $reference->verified_at = now();
        $reference->save();

        // Broadcast reference verification
        event(new \App\Events\ReferenceVerified(
            $reference,
            $request->result,
            $request->notes,
            $request->user()
        ));

        // Add to application timeline
        $this->addToTimeline($application, [
            'action' => 'REF_VERIFIED',
            'reference' => $reference->full_name,
            'result' => $request->result,
            'user_id' => $request->user()->id,
            'timestamp' => now()->toIso8601String(),
        ]);

        // Log reference verification
        $metadata = $request->attributes->get('metadata', []);
        AuditLog::log(
            AuditAction::REFERENCE_VERIFIED->value,
            null,
            array_merge($metadata, [
                'user_id' => $request->user()->id,
                'applicant_id' => $application->applicant_id,
                'application_id' => $application->id,
            ])
        );

        return response()->json([
            'message' => 'Verificación de referencia registrada',
            'data' => [
                'id' => $reference->id,
                'full_name' => $reference->full_name,
                'is_verified' => $reference->is_verified,
                'result' => $reference->verification_result,
            ]
        ]);
    }

    /**
     * Verify a bank account.
     */
    public function verifyBankAccount(Request $request, Application $application, BankAccount $bankAccount): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        if ($application->tenant_id !== $tenant->id) {
            return response()->json(['message' => 'Solicitud no encontrada'], 404);
        }

        if ($bankAccount->applicant_id !== $application->applicant_id) {
            return response()->json(['message' => 'Cuenta bancaria no encontrada'], 404);
        }

        if ($bankAccount->is_verified) {
            return response()->json([
                'message' => 'La cuenta bancaria ya está verificada'
            ], 400);
        }

        $bankAccount->is_verified = true;
        $bankAccount->verified_at = now();
        $bankAccount->verification_method = 'MANUAL';
        $bankAccount->save();

        // Broadcast bank account verification
        event(new \App\Events\BankAccountVerified(
            $bankAccount,
            $application->id,
            true,
            $request->user()
        ));

        // Add to application timeline
        $this->addToTimeline($application, [
            'action' => 'BANK_ACCOUNT_VERIFIED',
            'bank_name' => $bankAccount->bank_name,
            'clabe_last4' => substr($bankAccount->clabe, -4),
            'user_id' => $request->user()->id,
            'timestamp' => now()->toIso8601String(),
        ]);

        // Log verification
        $metadata = $request->attributes->get('metadata', []);
        AuditLog::log(
            AuditAction::DATA_VERIFIED->value,
            null,
            array_merge($metadata, [
                'user_id' => $request->user()->id,
                'applicant_id' => $application->applicant_id,
                'application_id' => $application->id,
                'bank_account_id' => $bankAccount->id,
                'action_type' => 'bank_account_verify',
            ])
        );

        return response()->json([
            'message' => 'Cuenta bancaria verificada',
            'data' => [
                'id' => $bankAccount->id,
                'bank_name' => $bankAccount->bank_name,
                'is_verified' => $bankAccount->is_verified,
                'verified_at' => $bankAccount->verified_at?->toIso8601String(),
            ]
        ]);
    }

    /**
     * Unverify a bank account.
     */
    public function unverifyBankAccount(Request $request, Application $application, BankAccount $bankAccount): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        if ($application->tenant_id !== $tenant->id) {
            return response()->json(['message' => 'Solicitud no encontrada'], 404);
        }

        if ($bankAccount->applicant_id !== $application->applicant_id) {
            return response()->json(['message' => 'Cuenta bancaria no encontrada'], 404);
        }

        if (!$bankAccount->is_verified) {
            return response()->json([
                'message' => 'La cuenta bancaria no está verificada'
            ], 400);
        }

        $bankAccount->is_verified = false;
        $bankAccount->verified_at = null;
        $bankAccount->verification_method = null;
        $bankAccount->save();

        // Broadcast bank account unverification
        event(new \App\Events\BankAccountVerified(
            $bankAccount,
            $application->id,
            false,
            $request->user()
        ));

        // Add to application timeline
        $this->addToTimeline($application, [
            'action' => 'BANK_ACCOUNT_UNVERIFIED',
            'bank_name' => $bankAccount->bank_name,
            'clabe_last4' => substr($bankAccount->clabe, -4),
            'user_id' => $request->user()->id,
            'timestamp' => now()->toIso8601String(),
        ]);

        // Log unverification
        $metadata = $request->attributes->get('metadata', []);
        AuditLog::log(
            AuditAction::DATA_VERIFIED->value,
            null,
            array_merge($metadata, [
                'user_id' => $request->user()->id,
                'applicant_id' => $application->applicant_id,
                'application_id' => $application->id,
                'bank_account_id' => $bankAccount->id,
                'action_type' => 'bank_account_unverify',
            ])
        );

        return response()->json([
            'message' => 'Verificación de cuenta bancaria removida',
            'data' => [
                'id' => $bankAccount->id,
                'bank_name' => $bankAccount->bank_name,
                'is_verified' => $bankAccount->is_verified,
            ]
        ]);
    }

    /**
     * Verify applicant data field.
     */
    public function verifyData(VerifyDataRequest $request, Application $application): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        if ($application->tenant_id !== $tenant->id) {
            return response()->json(['message' => 'Solicitud no encontrada'], 404);
        }

        $field = $request->field;
        $method = $request->input('method', 'MANUAL');
        $notes = $request->notes;
        $applicant = $application->applicant;

        if (!$applicant) {
            return response()->json(['message' => 'Solicitante no encontrado'], 404);
        }

        // Get the field value being verified
        $fieldValue = $this->getFieldValue($applicant, $field);

        // Determine action
        $action = $request->determineAction();

        // Process action
        $result = $this->processVerificationAction(
            $action,
            $tenant,
            $applicant,
            $application,
            $field,
            $fieldValue,
            $method,
            $notes,
            $request
        );

        // Update legacy fields for backwards compatibility
        $this->updateLegacyVerificationFields($applicant, $field, $result['verified']);

        // Add to status history
        $this->addToTimeline($application, [
            'action' => 'DATA_VERIFIED',
            'field' => $field,
            'field_label' => DataVerification::getFieldLabel($field),
            'method' => $method,
            'verified' => $result['verified'],
            'timestamp' => now()->toIso8601String(),
            'user_id' => $request->user()->id,
        ]);

        // Log action
        $this->logVerificationAction($action, $request, $application);

        return response()->json([
            'message' => $result['message'],
            'data' => [
                'field' => $field,
                'field_label' => DataVerification::getFieldLabel($field),
                'action' => $action,
                'verified' => $result['verified'],
                'status' => $result['status'],
                'method' => $method,
                'verified_at' => $result['verified_at'],
                'rejected_at' => $result['rejected_at'],
                'rejection_reason' => $request->rejection_reason,
            ]
        ]);
    }

    /**
     * Get field value from applicant.
     */
    private function getFieldValue($applicant, string $field): ?string
    {
        return match ($field) {
            'address' => $applicant->primaryAddress?->full_address,
            'employment' => $applicant->currentEmployment?->company_name,
            default => $applicant->{$field} ?? null
        };
    }

    /**
     * Process the verification action.
     */
    private function processVerificationAction(
        string $action,
        $tenant,
        $applicant,
        Application $application,
        string $field,
        ?string $fieldValue,
        string $method,
        ?string $notes,
        VerifyDataRequest $request
    ): array {
        switch ($action) {
            case 'verify':
                DataVerification::create([
                    'tenant_id' => $tenant->id,
                    'applicant_id' => $applicant->id,
                    'field_name' => $field,
                    'field_value' => $fieldValue,
                    'method' => $method,
                    'is_verified' => true,
                    'status' => VerificationStatus::VERIFIED->value,
                    'notes' => $notes,
                    'verified_by' => $request->user()->id,
                    'rejection_reason' => null,
                    'rejected_at' => null,
                ]);

                return [
                    'verified' => true,
                    'message' => DataVerification::getFieldLabel($field) . ' verificado',
                    'status' => VerificationStatus::VERIFIED->value,
                    'verified_at' => now()->toIso8601String(),
                    'rejected_at' => null,
                ];

            case 'reject':
                DataVerification::create([
                    'tenant_id' => $tenant->id,
                    'applicant_id' => $applicant->id,
                    'field_name' => $field,
                    'field_value' => $fieldValue,
                    'is_verified' => false,
                    'status' => VerificationStatus::REJECTED->value,
                    'rejection_reason' => $request->rejection_reason,
                    'rejected_at' => now(),
                    'verified_by' => $request->user()->id,
                    'notes' => $notes,
                ]);

                // Change application status to CORRECTIONS_PENDING
                if ($application->status !== ApplicationStatus::CORRECTIONS_PENDING) {
                    $application->changeStatus(
                        ApplicationStatus::CORRECTIONS_PENDING->value,
                        "Dato rechazado: " . DataVerification::getFieldLabel($field),
                        $request->user()->id
                    );
                }

                return [
                    'verified' => false,
                    'message' => DataVerification::getFieldLabel($field) . ' rechazado - se solicitó corrección al usuario',
                    'status' => VerificationStatus::REJECTED->value,
                    'verified_at' => null,
                    'rejected_at' => now()->toIso8601String(),
                ];

            case 'unverify':
                DataVerification::create([
                    'tenant_id' => $tenant->id,
                    'applicant_id' => $applicant->id,
                    'field_name' => $field,
                    'field_value' => $fieldValue,
                    'is_verified' => false,
                    'status' => VerificationStatus::PENDING->value,
                    'rejection_reason' => null,
                    'rejected_at' => null,
                    'verified_by' => $request->user()->id,
                    'notes' => $notes ?? 'Verificación removida',
                ]);

                return [
                    'verified' => false,
                    'message' => 'Verificación de ' . DataVerification::getFieldLabel($field) . ' removida',
                    'status' => null,
                    'verified_at' => null,
                    'rejected_at' => null,
                ];

            default:
                throw new \InvalidArgumentException('Acción inválida');
        }
    }

    /**
     * Update legacy verification fields for backwards compatibility.
     */
    private function updateLegacyVerificationFields($applicant, string $field, bool $verified): void
    {
        $verifiedAt = $verified ? now() : null;

        switch ($field) {
            case 'phone':
                $applicant->update(['phone_verified_at' => $verifiedAt]);
                break;
            case 'email':
                $applicant->update(['email_verified_at' => $verifiedAt]);
                break;
            case 'address':
                $address = $applicant->primaryAddress;
                if ($address) {
                    $address->update(['is_verified' => $verified]);
                }
                break;
        }
    }

    /**
     * Log verification action.
     */
    private function logVerificationAction(string $action, Request $request, Application $application): void
    {
        $auditAction = match ($action) {
            'verify', 'unverify' => AuditAction::DATA_VERIFIED->value,
            'reject' => AuditAction::DATA_REJECTED->value,
            default => AuditAction::DATA_VERIFIED->value,
        };

        $metadata = $request->attributes->get('metadata', []);
        AuditLog::log(
            $auditAction,
            null,
            array_merge($metadata, [
                'user_id' => $request->user()->id,
                'applicant_id' => $application->applicant_id,
                'application_id' => $application->id,
            ])
        );
    }

    /**
     * Add entry to application timeline.
     */
    private function addToTimeline(Application $application, array $entry): void
    {
        $history = $application->status_history ?? [];
        $history[] = $entry;
        $application->update(['status_history' => $history]);
    }
}
