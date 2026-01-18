<?php

namespace App\Http\Controllers\Api\V2\Applicant;

use App\Http\Controllers\Controller;
use App\Models\ApplicantAccount;
use App\Models\DocumentV2;
use App\Services\DocumentV2Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Applicant Document Controller (v2).
 *
 * Handles document uploads and management for authenticated applicants
 * using the new polymorphic DocumentV2 model.
 */
class DocumentController extends Controller
{
    public function __construct(
        private DocumentV2Service $service
    ) {}

    /**
     * List applicant's documents.
     *
     * GET /v2/applicant/documents
     */
    public function index(Request $request): JsonResponse
    {
        /** @var ApplicantAccount $account */
        $account = $request->user();

        if (!$account->person) {
            return response()->json([
                'error' => 'PROFILE_INCOMPLETE',
                'message' => 'Debes completar tu perfil antes de ver documentos.',
            ], 400);
        }

        $type = $request->query('type');
        $documents = $this->service->getDocumentsFor($account->person, $type);

        return response()->json([
            'documents' => $documents->map(fn($doc) => $this->formatDocument($doc)),
        ]);
    }

    /**
     * Upload a new document.
     *
     * POST /v2/applicant/documents
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|string|in:' . implode(',', DocumentV2::validTypes()),
            'file' => 'required|file|mimes:jpg,jpeg,png,pdf|max:10240', // 10MB
            'identification_id' => 'nullable|uuid|exists:person_identifications,id',
            'metadata' => 'nullable|array',
        ]);

        /** @var ApplicantAccount $account */
        $account = $request->user();
        $person = $account->person;

        if (!$person) {
            return response()->json([
                'error' => 'PROFILE_INCOMPLETE',
                'message' => 'Debes completar tu perfil antes de subir documentos.',
            ], 400);
        }

        // Determine documentable entity
        $documentable = $person;

        // If identification_id is provided, use that identification as documentable
        if (!empty($validated['identification_id'])) {
            $identification = $person->identifications()
                ->where('id', $validated['identification_id'])
                ->first();

            if (!$identification) {
                return response()->json([
                    'error' => 'IDENTIFICATION_NOT_FOUND',
                    'message' => 'Identificación no encontrada.',
                ], 404);
            }

            $documentable = $identification;
        }

        try {
            $document = $this->service->upload(
                $account->tenant,
                $documentable,
                $validated['type'],
                $request->file('file'),
                $validated['metadata'] ?? []
            );

            return response()->json([
                'message' => 'Documento subido exitosamente.',
                'document' => $this->formatDocument($document),
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'error' => 'UPLOAD_FAILED',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Show document details.
     *
     * GET /v2/applicant/documents/{id}
     */
    public function show(Request $request, string $id): JsonResponse
    {
        /** @var ApplicantAccount $account */
        $account = $request->user();

        $document = $this->getApplicantDocument($account, $id);

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
     * GET /v2/applicant/documents/{id}/download
     */
    public function download(Request $request, string $id): JsonResponse
    {
        /** @var ApplicantAccount $account */
        $account = $request->user();

        $document = $this->getApplicantDocument($account, $id);

        if (!$document) {
            return response()->json([
                'error' => 'NOT_FOUND',
                'message' => 'Documento no encontrado.',
            ], 404);
        }

        // Generate signed URL for secure download
        $url = Storage::disk($document->storage_disk ?? 'local')
            ->temporaryUrl($document->file_path, now()->addMinutes(15));

        return response()->json([
            'url' => $url,
            'expires_at' => now()->addMinutes(15)->toIso8601String(),
            'filename' => $document->original_filename,
        ]);
    }

    /**
     * Delete a document.
     *
     * DELETE /v2/applicant/documents/{id}
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        /** @var ApplicantAccount $account */
        $account = $request->user();

        $document = $this->getApplicantDocument($account, $id);

        if (!$document) {
            return response()->json([
                'error' => 'NOT_FOUND',
                'message' => 'Documento no encontrado.',
            ], 404);
        }

        // Only allow deletion of pending or rejected documents
        if ($document->isApproved()) {
            return response()->json([
                'error' => 'NOT_DELETABLE',
                'message' => 'No puedes eliminar documentos aprobados.',
            ], 400);
        }

        $this->service->delete($document, $account->id);

        return response()->json([
            'message' => 'Documento eliminado exitosamente.',
        ]);
    }

    /**
     * Get rejected documents that need re-upload.
     *
     * GET /v2/applicant/documents/rejected
     */
    public function rejected(Request $request): JsonResponse
    {
        /** @var ApplicantAccount $account */
        $account = $request->user();

        if (!$account->person) {
            return response()->json([
                'error' => 'PROFILE_INCOMPLETE',
                'message' => 'Debes completar tu perfil.',
            ], 400);
        }

        $rejected = $this->service->getRejectedForReupload($account->person);

        return response()->json([
            'documents' => $rejected->map(fn($doc) => [
                'id' => $doc->id,
                'type' => $doc->type,
                'type_label' => $doc->type_label,
                'rejection_reason' => $doc->rejection_reason,
                'rejected_at' => $doc->reviewed_at?->toIso8601String(),
            ]),
        ]);
    }

    /**
     * Check missing required documents for a product.
     *
     * GET /v2/applicant/documents/missing
     */
    public function missing(Request $request): JsonResponse
    {
        $productId = $request->query('product_id');

        /** @var ApplicantAccount $account */
        $account = $request->user();

        if (!$account->person) {
            return response()->json([
                'error' => 'PROFILE_INCOMPLETE',
                'message' => 'Debes completar tu perfil.',
            ], 400);
        }

        // Get required docs from product or use default identity docs
        $requiredTypes = [];
        if ($productId) {
            $product = \App\Models\Product::find($productId);
            if ($product && $product->tenant_id === $account->tenant_id) {
                $requiredTypes = $product->required_documents ?? [];
            }
        }

        if (empty($requiredTypes)) {
            // Default required documents
            $requiredTypes = [
                DocumentV2::TYPE_INE_FRONT,
                DocumentV2::TYPE_INE_BACK,
                DocumentV2::TYPE_PROOF_OF_ADDRESS,
            ];
        }

        $missing = $this->service->getMissingRequired($account->person, $requiredTypes);

        return response()->json([
            'missing_types' => $missing,
            'missing_labels' => collect($missing)->map(fn($type) => [
                'type' => $type,
                'label' => DocumentV2::typeLabels()[$type] ?? $type,
            ]),
            'is_complete' => empty($missing),
        ]);
    }

    /**
     * Get document types and categories.
     *
     * GET /v2/applicant/documents/types
     */
    public function types(): JsonResponse
    {
        return response()->json([
            'types' => DocumentV2::typeLabels(),
            'categories' => DocumentV2::categories(),
        ]);
    }

    /**
     * Get applicant's document by ID.
     */
    private function getApplicantDocument(ApplicantAccount $account, string $id): ?DocumentV2
    {
        $person = $account->person;
        if (!$person) {
            return null;
        }

        // Check documents belonging to person
        $document = DocumentV2::where('id', $id)
            ->where('tenant_id', $account->tenant_id)
            ->where(function ($query) use ($person) {
                $query->where(function ($q) use ($person) {
                    $q->where('documentable_type', get_class($person))
                        ->where('documentable_id', $person->id);
                })->orWhere(function ($q) use ($person) {
                    // Also check documents for person's identifications
                    $q->where('documentable_type', \App\Models\PersonIdentification::class)
                        ->whereIn('documentable_id', $person->identifications()->pluck('id'));
                });
            })
            ->first();

        return $document;
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
            'rejection_reason' => $doc->rejection_reason,
            'created_at' => $doc->created_at?->toIso8601String(),
            'reviewed_at' => $doc->reviewed_at?->toIso8601String(),
        ];
    }

    /**
     * Format document for detail view.
     */
    private function formatDocumentDetail(DocumentV2 $doc): array
    {
        $data = $this->formatDocument($doc);

        $data['ocr_processed'] = $doc->ocr_processed;
        $data['ocr_confidence'] = $doc->ocr_confidence;
        $data['notes'] = $doc->notes;
        $data['expires_at'] = $doc->expires_at?->toIso8601String();

        return $data;
    }
}
