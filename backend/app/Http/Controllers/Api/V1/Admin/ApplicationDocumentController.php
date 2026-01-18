<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\ApplicationStatus;
use App\Enums\AuditAction;
use App\Enums\DocumentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RejectDocumentRequest;
use App\Models\Application;
use App\Models\AuditLog;
use App\Models\Document;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Controller for managing application documents in admin panel.
 *
 * Handles document approval, rejection, unapproval, viewing and history.
 */
class ApplicationDocumentController extends Controller
{
    /**
     * Approve a document.
     */
    public function approve(Request $request, Application $application, Document $document): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        if ($application->tenant_id !== $tenant->id || $document->application_id !== $application->id) {
            return response()->json(['message' => 'Documento no encontrado'], 404);
        }

        if ($document->status !== DocumentStatus::PENDING) {
            return response()->json([
                'message' => 'Solo los documentos pendientes pueden ser aprobados'
            ], 400);
        }

        $previousStatus = $document->status;

        $document->status = DocumentStatus::APPROVED;
        $document->reviewed_by = $request->user()->id;
        $document->reviewed_at = now();
        $document->save();

        // Broadcast document status change
        event(new \App\Events\DocumentStatusChanged(
            $document,
            $previousStatus->value,
            DocumentStatus::APPROVED->value,
            null,
            $request->user()
        ));

        // Add to application timeline
        $this->addDocumentToTimeline($application, $document, 'DOC_APPROVED', $request->user()->id);

        // Log document approval
        $this->logDocumentAction(
            AuditAction::DOCUMENT_APPROVED,
            $request,
            $application,
            $document,
            ['status' => 'APPROVED']
        );

        return response()->json([
            'message' => 'Documento aprobado',
            'data' => $this->formatDocumentResponse($document)
        ]);
    }

    /**
     * Reject a document.
     */
    public function reject(RejectDocumentRequest $request, Application $application, Document $document): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        if ($application->tenant_id !== $tenant->id || $document->application_id !== $application->id) {
            return response()->json(['message' => 'Documento no encontrado'], 404);
        }

        if ($document->status !== DocumentStatus::PENDING) {
            return response()->json([
                'message' => 'Solo los documentos pendientes pueden ser rechazados'
            ], 400);
        }

        $previousStatus = $document->status;

        $document->status = DocumentStatus::REJECTED;
        $document->rejection_reason = $request->reason ?? $request->comment;
        $document->reviewed_by = $request->user()->id;
        $document->reviewed_at = now();
        $document->save();

        // Broadcast document status change
        event(new \App\Events\DocumentStatusChanged(
            $document,
            $previousStatus->value,
            DocumentStatus::REJECTED->value,
            $request->reason,
            $request->user()
        ));

        // Add to application timeline
        $this->addDocumentToTimeline(
            $application,
            $document,
            'DOC_REJECTED',
            $request->user()->id,
            ['reason' => $request->reason]
        );

        // Optionally change application status to DOCS_PENDING
        if ($application->status === ApplicationStatus::IN_REVIEW) {
            $docTypeLabel = $this->getDocumentTypeLabel($document);
            $application->changeStatus(
                ApplicationStatus::DOCS_PENDING->value,
                "Documento rechazado: {$docTypeLabel}",
                $request->user()->id
            );
        }

        // Log document rejection
        $this->logDocumentAction(
            AuditAction::DOCUMENT_REJECTED,
            $request,
            $application,
            $document,
            [
                'status' => 'REJECTED',
                'rejection_reason' => $request->reason,
                'rejection_comment' => $request->comment,
            ]
        );

        return response()->json([
            'message' => 'Documento rechazado',
            'data' => array_merge(
                $this->formatDocumentResponse($document),
                ['rejection_reason' => $document->rejection_reason]
            )
        ]);
    }

    /**
     * Unapprove a document (set back to pending).
     */
    public function unapprove(Request $request, Application $application, Document $document): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        if ($application->tenant_id !== $tenant->id || $document->application_id !== $application->id) {
            return response()->json(['message' => 'Documento no encontrado'], 404);
        }

        if ($document->status === DocumentStatus::PENDING) {
            return response()->json([
                'message' => 'El documento ya está pendiente'
            ], 400);
        }

        // Check if document was validated by KYC (cannot be unapproved)
        if ($this->isKycValidated($document)) {
            return response()->json([
                'message' => 'No se puede desaprobar un documento validado por KYC automático'
            ], 403);
        }

        $previousStatus = $document->status;

        $document->status = DocumentStatus::PENDING;
        $document->reviewed_by = null;
        $document->reviewed_at = null;
        $document->rejection_reason = null;
        $document->save();

        // Broadcast document status change
        event(new \App\Events\DocumentStatusChanged(
            $document,
            $previousStatus->value,
            DocumentStatus::PENDING->value,
            null,
            $request->user()
        ));

        // Add to application timeline
        $this->addDocumentToTimeline(
            $application,
            $document,
            'DOC_UNAPPROVED',
            $request->user()->id,
            ['previous_status' => $previousStatus->value]
        );

        // Log document unapproval
        $this->logDocumentAction(
            AuditAction::DOCUMENT_UNAPPROVED,
            $request,
            $application,
            $document,
            ['status' => 'PENDING'],
            ['status' => $previousStatus->value]
        );

        return response()->json([
            'message' => 'Documento devuelto a pendiente',
            'data' => $this->formatDocumentResponse($document)
        ]);
    }

    /**
     * Get a temporary URL for viewing a document.
     */
    public function getUrl(Request $request, Application $application, Document $document): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        if ($application->tenant_id !== $tenant->id || $document->application_id !== $application->id) {
            return response()->json(['message' => 'Documento no encontrado'], 404);
        }

        if ($document->storage_disk === 's3') {
            $url = Storage::disk('s3')->temporaryUrl(
                $document->file_path,
                now()->addMinutes(15)
            );
        } else {
            // For local development, return a route URL
            $url = route('api.admin.documents.download', [
                'application' => $application->id,
                'document' => $document->id
            ]);
        }

        return response()->json([
            'url' => $url,
            'mime_type' => $document->mime_type,
            'original_name' => $document->name ?? $document->file_name,
            'expires_in' => 900 // 15 minutes
        ]);
    }

    /**
     * Download a document (for local development).
     */
    public function download(Request $request, Application $application, Document $document)
    {
        $tenant = $request->attributes->get('tenant');

        if ($application->tenant_id !== $tenant->id || $document->application_id !== $application->id) {
            abort(404);
        }

        if ($document->storage_disk !== 'local') {
            abort(400, 'Direct download not available for this storage type');
        }

        $path = Storage::disk('local')->path($document->file_path);

        if (!file_exists($path)) {
            abort(404, 'File not found');
        }

        $fileName = $document->name ?? $document->file_name ?? 'document';

        return response()->file($path, [
            'Content-Type' => $document->mime_type,
            'Content-Disposition' => 'inline; filename="' . $fileName . '"',
        ]);
    }

    /**
     * Get the review history for a document.
     */
    public function history(Request $request, Application $application, Document $document): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        if ($application->tenant_id !== $tenant->id || $document->application_id !== $application->id) {
            return response()->json(['message' => 'Documento no encontrado'], 404);
        }

        $auditLogs = AuditLog::where('entity_type', 'Document')
            ->where('entity_id', $document->id)
            ->whereIn('action', [
                AuditAction::DOCUMENT_APPROVED->value,
                AuditAction::DOCUMENT_REJECTED->value,
                AuditAction::DOCUMENT_UNAPPROVED->value,
            ])
            ->orderBy('created_at', 'desc')
            ->get();

        $history = $auditLogs->map(function ($log) {
            $newValues = $log->new_values ?? [];
            $oldValues = $log->old_values ?? [];

            return [
                'id' => $log->id,
                'action' => $log->action->value ?? $log->action,
                'action_label' => $log->action->label() ?? $log->action,
                'status' => $newValues['status'] ?? null,
                'previous_status' => $oldValues['status'] ?? null,
                'rejection_reason' => $newValues['rejection_reason'] ?? null,
                'rejection_comment' => $newValues['rejection_comment'] ?? null,
                'reviewer_name' => $newValues['reviewer_name'] ?? null,
                'reviewer_id' => $newValues['reviewed_by'] ?? $log->user_id,
                'created_at' => $log->created_at?->toIso8601String(),
            ];
        });

        return response()->json([
            'data' => [
                'document_id' => $document->id,
                'document_type' => $document->type instanceof \App\Enums\DocumentType ? $document->type->value : $document->type,
                'current_status' => $document->status instanceof \App\Enums\DocumentStatus ? $document->status->value : $document->status,
                'history' => $history,
            ]
        ]);
    }

    /**
     * Check if document was validated by KYC.
     */
    private function isKycValidated(Document $document): bool
    {
        $metadata = $document->metadata ?? [];

        return (
            ($metadata['kyc_validated'] ?? false) === true ||
            ($metadata['nubarium_validated'] ?? false) === true ||
            ($metadata['ine_valid'] ?? false) === true ||
            ($metadata['face_match_passed'] ?? false) === true ||
            ($metadata['face_match'] ?? false) === true ||
            ($metadata['validated_by_kyc'] ?? false) === true ||
            ($metadata['source'] ?? '') === 'kyc' ||
            ($metadata['source'] ?? '') === 'nubarium' ||
            in_array($metadata['validation_method'] ?? '', ['KYC_INE_OCR', 'KYC_FACE_MATCH'])
        );
    }

    /**
     * Get document type label.
     */
    private function getDocumentTypeLabel(Document $document): string
    {
        return $document->type instanceof \App\Enums\DocumentType
            ? $document->type->description()
            : (string) $document->type;
    }

    /**
     * Format document response.
     */
    private function formatDocumentResponse(Document $document): array
    {
        return [
            'id' => $document->id,
            'type' => $document->type instanceof \App\Enums\DocumentType ? $document->type->value : $document->type,
            'status' => $document->status instanceof \App\Enums\DocumentStatus ? $document->status->value : $document->status,
        ];
    }

    /**
     * Add document action to application timeline.
     */
    private function addDocumentToTimeline(
        Application $application,
        Document $document,
        string $action,
        string $userId,
        array $extra = []
    ): void {
        $docType = $document->type instanceof \App\Enums\DocumentType ? $document->type->value : $document->type;
        $docTypeLabel = $this->getDocumentTypeLabel($document);

        $history = $application->status_history ?? [];
        $history[] = array_merge([
            'action' => $action,
            'document' => $docType,
            'document_label' => $docTypeLabel,
            'user_id' => $userId,
            'timestamp' => now()->toIso8601String(),
        ], $extra);

        $application->status_history = $history;
        $application->save();
    }

    /**
     * Log document action to audit log.
     */
    private function logDocumentAction(
        AuditAction $action,
        Request $request,
        Application $application,
        Document $document,
        array $newValues = [],
        array $oldValues = []
    ): void {
        $docType = $document->type instanceof \App\Enums\DocumentType ? $document->type->value : $document->type;
        $docTypeLabel = $this->getDocumentTypeLabel($document);

        $requestMetadata = $request->attributes->get('metadata', []);

        AuditLog::log(
            $action->value,
            null,
            array_merge($requestMetadata, [
                'user_id' => $request->user()->id,
                'applicant_id' => $application->applicant_id,
                'application_id' => $application->id,
                'entity_type' => 'Document',
                'entity_id' => $document->id,
                'old_values' => $oldValues,
                'new_values' => array_merge([
                    'document_type' => $docType,
                    'document_label' => $docTypeLabel,
                    'reviewed_by' => $request->user()->id,
                    'reviewer_name' => $request->user()->name,
                ], $newValues),
            ])
        );
    }
}
