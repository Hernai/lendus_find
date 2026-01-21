<?php

namespace App\Http\Controllers\Api\V2\Applicant;

use App\Enums\DocumentStatus;
use App\Http\Controllers\Api\V2\Traits\ApiResponses;
use App\Http\Controllers\Controller;
use App\Models\ApplicantAccount;
use App\Models\Application;
use App\Models\DataVerification;
use App\Models\Document;
use App\Models\Person;
use App\Services\ApplicationEventService;
use App\Services\DocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Applicant Document Controller (v2).
 *
 * Handles document uploads and management for authenticated applicants
 * using the new polymorphic Document model.
 */
class DocumentController extends Controller
{
    use ApiResponses;

    public function __construct(
        private DocumentService $service,
        private ApplicationEventService $eventService
    ) {}

    /**
     * Get the current (active) application for the person.
     */
    private function getCurrentApplication($person): ?Application
    {
        return Application::where('person_id', $person->id)
            ->active()
            ->orderByDesc('created_at')
            ->first();
    }

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
            return $this->badRequest('PROFILE_INCOMPLETE', 'Debes completar tu perfil antes de ver documentos.');
        }

        $type = $request->query('type');
        $documents = $this->service->getDocumentsFor($account->person, $type);

        return $this->success([
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
        // Parse metadata JSON string if sent from FormData
        if ($request->has('metadata') && is_string($request->input('metadata'))) {
            $metadataString = $request->input('metadata');
            $parsedMetadata = json_decode($metadataString, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $request->merge(['metadata' => $parsedMetadata]);
            }
        }

        $validated = $request->validate([
            'type' => 'required|string|in:' . implode(',', Document::validTypes()),
            'file' => 'required|file|mimes:jpg,jpeg,png,pdf|max:10240', // 10MB
            'identification_id' => 'nullable|uuid|exists:person_identifications,id',
            'metadata' => 'nullable|array',
        ]);

        /** @var ApplicantAccount $account */
        $account = $request->user();
        $person = $account->person;

        if (!$person) {
            return $this->badRequest('PROFILE_INCOMPLETE', 'Debes completar tu perfil antes de subir documentos.');
        }

        // Determine documentable entity
        $documentable = $person;

        // If identification_id is provided, use that identification as documentable
        if (!empty($validated['identification_id'])) {
            $identification = $person->identifications()
                ->where('id', $validated['identification_id'])
                ->first();

            if (!$identification) {
                return $this->notFound('Identificación no encontrada.');
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

            // Check if document should be auto-approved based on existing KYC validations
            $this->autoApproveIfKycValidated($document, $person, $validated['type']);

            // Record event if there's an active application
            $application = $this->getCurrentApplication($person);
            if ($application) {
                $this->eventService->recordDocumentUploaded(
                    $application,
                    $validated['type'],
                    $document->id,
                    $account->id,
                    $request
                );
            }

            return $this->created([
                'document' => $this->formatDocument($document->fresh()),
            ], 'Documento subido exitosamente.');
        } catch (\InvalidArgumentException $e) {
            return $this->badRequest('UPLOAD_FAILED', $e->getMessage());
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
            return $this->notFound('Documento no encontrado.');
        }

        return $this->success([
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
            return $this->notFound('Documento no encontrado.');
        }

        // Generate signed URL for secure download
        /** @var \Illuminate\Contracts\Filesystem\Filesystem&\Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk($document->storage_disk ?? 'local');

        // Check if disk supports temporary URLs (S3, MinIO, etc.)
        if (method_exists($disk, 'temporaryUrl')) {
            $url = $disk->temporaryUrl($document->file_path, now()->addMinutes(15));
        } else {
            // Fallback: return regular URL for local disk
            $url = $disk->url($document->file_path);
        }

        return $this->success([
            'url' => $url,
            'expires_at' => now()->addMinutes(15)->toIso8601String(),
            'filename' => $document->original_filename,
        ]);
    }

    /**
     * Stream document content directly (no external URL).
     *
     * GET /v2/applicant/documents/{id}/stream
     */
    public function stream(Request $request, string $id): StreamedResponse|JsonResponse
    {
        /** @var ApplicantAccount $account */
        $account = $request->user();

        $document = $this->getApplicantDocument($account, $id);

        if (!$document) {
            return $this->notFound('Documento no encontrado.');
        }

        $disk = Storage::disk($document->storage_disk ?? 'local');

        if (!$disk->exists($document->file_path)) {
            return $this->notFound('Archivo no encontrado.');
        }

        $mimeType = $document->mime_type ?? 'application/octet-stream';
        $filename = $document->original_filename ?? 'document';

        return response()->stream(
            function () use ($disk, $document) {
                $stream = $disk->readStream($document->file_path);
                if ($stream) {
                    fpassthru($stream);
                    fclose($stream);
                }
            },
            200,
            [
                'Content-Type' => $mimeType,
                'Content-Disposition' => 'inline; filename="' . $filename . '"',
                'Cache-Control' => 'private, max-age=3600',
            ]
        );
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
            return $this->notFound('Documento no encontrado.');
        }

        // Only allow deletion of pending or rejected documents
        if ($document->isApproved()) {
            return $this->badRequest('NOT_DELETABLE', 'No puedes eliminar documentos aprobados.');
        }

        $this->service->delete($document, $account->id);

        return $this->success(null, 'Documento eliminado exitosamente.');
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
            return $this->badRequest('PROFILE_INCOMPLETE', 'Debes completar tu perfil.');
        }

        $rejected = $this->service->getRejectedForReupload($account->person);

        return $this->success([
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
            return $this->badRequest('PROFILE_INCOMPLETE', 'Debes completar tu perfil.');
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
                Document::TYPE_INE_FRONT,
                Document::TYPE_INE_BACK,
                Document::TYPE_PROOF_OF_ADDRESS,
            ];
        }

        $missing = $this->service->getMissingRequired($account->person, $requiredTypes);

        return $this->success([
            'missing_types' => $missing,
            'missing_labels' => collect($missing)->map(fn($type) => [
                'type' => $type,
                'label' => Document::typeLabels()[$type] ?? $type,
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
        return $this->success([
            'types' => Document::typeLabels(),
            'categories' => Document::categories(),
        ]);
    }

    /**
     * Get applicant's document by ID.
     */
    private function getApplicantDocument(ApplicantAccount $account, string $id): ?Document
    {
        $person = $account->person;
        if (!$person) {
            return null;
        }

        // Check documents belonging to person
        $document = Document::where('id', $id)
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
    private function formatDocument(Document $doc): array
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
    private function formatDocumentDetail(Document $doc): array
    {
        $data = $this->formatDocument($doc);

        $data['ocr_processed'] = $doc->ocr_processed;
        $data['ocr_confidence'] = $doc->ocr_confidence;
        $data['notes'] = $doc->notes;
        $data['expires_at'] = $doc->expires_at?->toIso8601String();

        return $data;
    }

    /**
     * Auto-approve a document if the corresponding KYC validation has already passed.
     *
     * This handles the race condition where KYC validation runs before the document
     * is uploaded (e.g., user validates INE, then uploads the INE image).
     */
    private function autoApproveIfKycValidated(Document $document, Person $person, string $type): void
    {
        // Map document types to their corresponding verification fields
        $verificationFieldMap = [
            Document::TYPE_INE_FRONT => 'ine_document_front',
            Document::TYPE_INE_BACK => 'ine_document_front', // INE validation validates both sides
            Document::TYPE_SELFIE => 'face_match',
        ];

        // Only process document types that have KYC validation
        if (!isset($verificationFieldMap[$type])) {
            return;
        }

        $verificationField = $verificationFieldMap[$type];

        // Check if there's a verified record for this field
        $isVerified = DataVerification::where('applicant_id', $person->id)
            ->where('field_name', $verificationField)
            ->where('is_verified', true)
            ->exists();

        if (!$isVerified) {
            Log::debug('[DocumentController] No KYC verification found for document auto-approval', [
                'person_id' => $person->id,
                'document_type' => $type,
                'verification_field' => $verificationField,
            ]);
            return;
        }

        // Get verification data for metadata
        $verification = DataVerification::where('applicant_id', $person->id)
            ->where('field_name', $verificationField)
            ->where('is_verified', true)
            ->first();

        // Build metadata for the auto-approval
        $kycMetadata = [
            'kyc_validated' => true,
            'nubarium_validated' => true,
            'source' => 'kyc',
            'validated_at' => $verification->updated_at?->toIso8601String(),
            'auto_approved' => true,
            'auto_approved_reason' => 'KYC validation completed before document upload',
        ];

        // Add type-specific metadata
        if (in_array($type, [Document::TYPE_INE_FRONT, Document::TYPE_INE_BACK])) {
            $kycMetadata['ine_valid'] = true;
            $kycMetadata['ine_ocr'] = true;
            $kycMetadata['validation_method'] = 'KYC_INE_OCR';

            // Get OCR data from verification if available
            if ($verification->metadata && isset($verification->metadata['ocr_data'])) {
                $kycMetadata['ocr_data'] = $verification->metadata['ocr_data'];
            }
        } elseif ($type === Document::TYPE_SELFIE) {
            $kycMetadata['face_match_passed'] = true;
            $kycMetadata['face_match'] = true;
            $kycMetadata['validation_method'] = 'KYC_FACE_MATCH';

            // Get face match score from verification if available
            if ($verification->metadata && isset($verification->metadata['score'])) {
                $kycMetadata['face_match_score'] = $verification->metadata['score'];
            }
        }

        // Merge existing metadata with KYC metadata
        $currentMetadata = $document->metadata ?? [];
        $newMetadata = array_merge($currentMetadata, $kycMetadata);

        // Update document to APPROVED status
        $document->update([
            'status' => DocumentStatus::APPROVED->value,
            'reviewed_at' => now(),
            'metadata' => $newMetadata,
            'notes' => ($document->notes ? $document->notes . "\n" : '') . 'Auto-aprobado por validación KYC previa.',
        ]);

        Log::info('[DocumentController] Document auto-approved based on existing KYC validation', [
            'person_id' => $person->id,
            'document_id' => $document->id,
            'document_type' => $type,
            'verification_field' => $verificationField,
        ]);
    }
}
