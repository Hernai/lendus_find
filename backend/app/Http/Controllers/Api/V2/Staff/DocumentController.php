<?php

namespace App\Http\Controllers\Api\V2\Staff;

use App\Http\Controllers\Controller;
use App\Models\DocumentV2;
use App\Models\Person;
use App\Models\StaffAccount;
use App\Services\DocumentV2Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Staff Document Controller (v2).
 *
 * Handles document review and management for staff members using the new
 * polymorphic DocumentV2 model.
 */
class DocumentController extends Controller
{
    public function __construct(
        private DocumentV2Service $service
    ) {}

    /**
     * List documents pending review.
     *
     * GET /v2/staff/documents/pending
     */
    public function pending(Request $request): JsonResponse
    {
        /** @var StaffAccount $staff */
        $staff = $request->user();

        $documents = $this->service->getPendingForReview($staff->tenant);

        return response()->json([
            'documents' => $documents->map(fn($doc) => $this->formatDocumentForReview($doc)),
        ]);
    }

    /**
     * List documents expiring soon.
     *
     * GET /v2/staff/documents/expiring
     */
    public function expiring(Request $request): JsonResponse
    {
        $days = (int) $request->query('days', 30);

        /** @var StaffAccount $staff */
        $staff = $request->user();

        $documents = $this->service->getExpiringSoon($staff->tenant, $days);

        return response()->json([
            'documents' => $documents->map(fn($doc) => $this->formatDocumentForReview($doc)),
        ]);
    }

    /**
     * Get documents for a person.
     *
     * GET /v2/staff/persons/{personId}/documents
     */
    public function forPerson(Request $request, string $personId): JsonResponse
    {
        /** @var StaffAccount $staff */
        $staff = $request->user();

        $person = Person::where('id', $personId)
            ->where('tenant_id', $staff->tenant_id)
            ->first();

        if (!$person) {
            return response()->json([
                'error' => 'NOT_FOUND',
                'message' => 'Persona no encontrada.',
            ], 404);
        }

        $type = $request->query('type');
        $documents = $this->service->getDocumentsFor($person, $type);

        return response()->json([
            'documents' => $documents->map(fn($doc) => $this->formatDocument($doc)),
        ]);
    }

    /**
     * Show document details.
     *
     * GET /v2/staff/documents/{id}
     */
    public function show(Request $request, string $id): JsonResponse
    {
        /** @var StaffAccount $staff */
        $staff = $request->user();

        $document = DocumentV2::where('id', $id)
            ->where('tenant_id', $staff->tenant_id)
            ->first();

        if (!$document) {
            return response()->json([
                'error' => 'NOT_FOUND',
                'message' => 'Documento no encontrado.',
            ], 404);
        }

        return response()->json([
            'document' => $this->formatDocumentDetail($document),
        ]);
    }

    /**
     * Get document download URL.
     *
     * GET /v2/staff/documents/{id}/download
     */
    public function download(Request $request, string $id): JsonResponse
    {
        /** @var StaffAccount $staff */
        $staff = $request->user();

        $document = DocumentV2::where('id', $id)
            ->where('tenant_id', $staff->tenant_id)
            ->first();

        if (!$document) {
            return response()->json([
                'error' => 'NOT_FOUND',
                'message' => 'Documento no encontrado.',
            ], 404);
        }

        // Generate signed URL for secure download
        $url = Storage::disk($document->storage_disk ?? 'local')
            ->temporaryUrl($document->file_path, now()->addMinutes(30));

        return response()->json([
            'url' => $url,
            'expires_at' => now()->addMinutes(30)->toIso8601String(),
            'filename' => $document->original_filename,
        ]);
    }

    /**
     * Approve document.
     *
     * POST /v2/staff/documents/{id}/approve
     */
    public function approve(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'notes' => 'nullable|string|max:1000',
        ]);

        /** @var StaffAccount $staff */
        $staff = $request->user();

        $document = DocumentV2::where('id', $id)
            ->where('tenant_id', $staff->tenant_id)
            ->first();

        if (!$document) {
            return response()->json([
                'error' => 'NOT_FOUND',
                'message' => 'Documento no encontrado.',
            ], 404);
        }

        if (!$document->isPending()) {
            return response()->json([
                'error' => 'NOT_PENDING',
                'message' => 'Este documento ya ha sido revisado.',
            ], 400);
        }

        $document = $this->service->approve($document, $staff, $validated['notes'] ?? null);

        return response()->json([
            'message' => 'Documento aprobado.',
            'document' => $this->formatDocument($document),
        ]);
    }

    /**
     * Reject document.
     *
     * POST /v2/staff/documents/{id}/reject
     */
    public function reject(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        /** @var StaffAccount $staff */
        $staff = $request->user();

        $document = DocumentV2::where('id', $id)
            ->where('tenant_id', $staff->tenant_id)
            ->first();

        if (!$document) {
            return response()->json([
                'error' => 'NOT_FOUND',
                'message' => 'Documento no encontrado.',
            ], 404);
        }

        if (!$document->isPending()) {
            return response()->json([
                'error' => 'NOT_PENDING',
                'message' => 'Este documento ya ha sido revisado.',
            ], 400);
        }

        $document = $this->service->reject($document, $staff, $validated['reason']);

        return response()->json([
            'message' => 'Documento rechazado.',
            'document' => $this->formatDocument($document),
        ]);
    }

    /**
     * Set OCR data for document.
     *
     * POST /v2/staff/documents/{id}/ocr
     */
    public function setOcrData(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'ocr_data' => 'required|array',
            'confidence' => 'nullable|numeric|min:0|max:100',
        ]);

        /** @var StaffAccount $staff */
        $staff = $request->user();

        $document = DocumentV2::where('id', $id)
            ->where('tenant_id', $staff->tenant_id)
            ->first();

        if (!$document) {
            return response()->json([
                'error' => 'NOT_FOUND',
                'message' => 'Documento no encontrado.',
            ], 404);
        }

        $document = $this->service->setOcrData(
            $document,
            $validated['ocr_data'],
            $validated['confidence'] ?? null
        );

        return response()->json([
            'message' => 'Datos OCR guardados.',
            'document' => [
                'id' => $document->id,
                'ocr_processed' => $document->ocr_processed,
                'ocr_data' => $document->ocr_data,
                'ocr_confidence' => $document->ocr_confidence,
            ],
        ]);
    }

    /**
     * Check required documents for a person.
     *
     * GET /v2/staff/persons/{personId}/documents/check
     */
    public function checkRequired(Request $request, string $personId): JsonResponse
    {
        $validated = $request->validate([
            'required_types' => 'required|array',
            'required_types.*' => 'string',
        ]);

        /** @var StaffAccount $staff */
        $staff = $request->user();

        $person = Person::where('id', $personId)
            ->where('tenant_id', $staff->tenant_id)
            ->first();

        if (!$person) {
            return response()->json([
                'error' => 'NOT_FOUND',
                'message' => 'Persona no encontrada.',
            ], 404);
        }

        $requiredTypes = $validated['required_types'];
        $allApproved = $this->service->areAllRequiredApproved($person, $requiredTypes);
        $missing = $this->service->getMissingRequired($person, $requiredTypes);
        $rejected = $this->service->getRejectedForReupload($person);

        return response()->json([
            'is_complete' => $allApproved,
            'missing_types' => $missing,
            'rejected_count' => $rejected->count(),
            'rejected_documents' => $rejected->map(fn($doc) => [
                'id' => $doc->id,
                'type' => $doc->type,
                'type_label' => $doc->type_label,
                'rejection_reason' => $doc->rejection_reason,
            ]),
        ]);
    }

    /**
     * Get document types and categories.
     *
     * GET /v2/staff/documents/types
     */
    public function types(): JsonResponse
    {
        return response()->json([
            'types' => DocumentV2::typeLabels(),
            'categories' => DocumentV2::categories(),
        ]);
    }

    /**
     * Format document for list view.
     */
    private function formatDocument(DocumentV2 $doc): array
    {
        return [
            'id' => $doc->id,
            'type' => $doc->type,
            'type_label' => $doc->type_label,
            'category' => $doc->category,
            'status' => $doc->status,
            'status_label' => $doc->status_label,
            'original_filename' => $doc->original_filename,
            'file_size' => $doc->file_size,
            'mime_type' => $doc->mime_type,
            'version_number' => $doc->version_number,
            'is_sensitive' => $doc->is_sensitive,
            'rejection_reason' => $doc->rejection_reason,
            'reviewed_by' => $doc->reviewed_by,
            'created_at' => $doc->created_at?->toIso8601String(),
            'reviewed_at' => $doc->reviewed_at?->toIso8601String(),
        ];
    }

    /**
     * Format document for review list (includes owner info).
     */
    private function formatDocumentForReview(DocumentV2 $doc): array
    {
        $data = $this->formatDocument($doc);

        // Add owner information
        $data['owner'] = $this->getOwnerInfo($doc);

        return $data;
    }

    /**
     * Format document for detail view.
     */
    private function formatDocumentDetail(DocumentV2 $doc): array
    {
        $data = $this->formatDocumentForReview($doc);

        $data['ocr_processed'] = $doc->ocr_processed;
        $data['ocr_data'] = $doc->ocr_data;
        $data['ocr_confidence'] = $doc->ocr_confidence;
        $data['notes'] = $doc->notes;
        $data['metadata'] = $doc->metadata;
        $data['expires_at'] = $doc->expires_at?->toIso8601String();
        $data['checksum'] = $doc->checksum;
        $data['previous_version_id'] = $doc->previous_version_id;

        return $data;
    }

    /**
     * Get owner information for document.
     */
    private function getOwnerInfo(DocumentV2 $doc): array
    {
        $documentable = $doc->documentable;

        if (!$documentable) {
            return ['type' => 'unknown', 'name' => 'Desconocido'];
        }

        $type = class_basename($documentable);

        return match ($type) {
            'Person' => [
                'type' => 'person',
                'id' => $documentable->id,
                'name' => $documentable->full_name,
            ],
            'PersonIdentification' => [
                'type' => 'identification',
                'id' => $documentable->id,
                'person_id' => $documentable->person_id,
                'person_name' => $documentable->person?->full_name,
                'identification_type' => $documentable->type,
            ],
            'Company' => [
                'type' => 'company',
                'id' => $documentable->id,
                'name' => $documentable->legal_name,
            ],
            default => ['type' => 'unknown', 'name' => 'Desconocido'],
        };
    }
}
