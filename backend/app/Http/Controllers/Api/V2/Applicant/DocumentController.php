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
use Illuminate\Support\Facades\DB;
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
        $person = $account->person;
        $application = $this->getCurrentApplication($person);

        if (!$application) {
            // No active application - return empty list
            return $this->success([
                'documents' => [],
            ]);
        }

        // Get documents for current application via documentable_relations
        // Find all documents that have USAGE relation with this application
        $query = DB::table('documentable_relations')
            ->join('documents', 'documentable_relations.document_id', '=', 'documents.id')
            ->where('documentable_relations.relatable_type', 'App\\Models\\Application')
            ->where('documentable_relations.relatable_id', $application->id)
            ->where('documentable_relations.relation_context', 'USAGE')
            ->whereNull('documentable_relations.deleted_at') // Exclude soft-deleted relations
            ->select('documents.*');

        if ($type) {
            $query->where('documents.type', $type);
        }

        $documentIds = $query->pluck('documents.id');

        // Load full Document models with relationships
        $documents = Document::whereIn('id', $documentIds)->get();

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

        // Get current application (needed for snapshot relationship)
        $application = $this->getCurrentApplication($person);

        // Determine documentable entity
        if (!empty($validated['identification_id'])) {
            // If identification_id is provided, use that identification as documentable
            $identification = $person->identifications()
                ->where('id', $validated['identification_id'])
                ->first();

            if (!$identification) {
                return $this->notFound('Identificación no encontrada.');
            }

            $documentable = $identification;
        } else {
            // ALL documents are associated with Person
            // The application_documents pivot table handles per-application relationships
            // Some documents (INE, Selfie) are reused across applications
            // Others (PAYSLIP, PROOF_OF_ADDRESS) must be renewed per application
            $documentable = $person;
        }

        try {
            $options = $validated['metadata'] ?? [];

            // Upload the document
            $document = $this->service->upload(
                $account->tenant,
                $documentable,
                $validated['type'],
                $request->file('file'),
                $options
            );

            // Activate the document (Active Document Pattern)
            // This will deactivate any previous active document of the same type
            $document->activate();

            // Check if document should be auto-approved based on existing KYC validations
            $this->autoApproveIfKycValidated($document, $person, $validated['type']);

            // Record event if there's an active application (already fetched above)
            if ($application) {
                $this->eventService->recordDocumentUploaded(
                    $application,
                    $validated['type'],
                    $document->id,
                    $account->id,
                    $request
                );

                // Create snapshot relationship - link document to application immediately
                // This preserves which documents were used even if person uploads new ones later
                $this->attachDocumentToApplication($application, $document, $account->id);
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
        $disk = Storage::disk($document->storage_disk ?? 'local');

        // Check if disk supports temporary URLs (S3, MinIO, etc.)
        try {
            $url = $disk->temporaryUrl($document->file_path, now()->addMinutes(15));
        } catch (\RuntimeException $e) {
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

        // Check documents belonging to person, person's identifications, or person's applications
        $document = Document::where('id', $id)
            ->where('tenant_id', $account->tenant_id)
            ->where(function ($query) use ($person) {
                $query->where(function ($q) use ($person) {
                    // Documents for Person
                    $q->where('documentable_type', get_class($person))
                        ->where('documentable_id', $person->id);
                })->orWhere(function ($q) use ($person) {
                    // Documents for Person's Identifications
                    $q->where('documentable_type', \App\Models\PersonIdentification::class)
                        ->whereIn('documentable_id', $person->identifications()->pluck('id'));
                })->orWhere(function ($q) use ($person) {
                    // Documents for Person's Applications
                    $q->where('documentable_type', \App\Models\Application::class)
                        ->whereIn('documentable_id', $person->applications()->pluck('id'));
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
            'metadata' => $doc->metadata, // Include metadata to check kyc_validated flag
            'created_at' => $doc->created_at?->toIso8601String(),
            'reviewed_at' => $doc->reviewed_at?->toIso8601String(),
            // Active Document Pattern fields
            'is_active' => $doc->is_active,
            'is_currently_valid' => $doc->isCurrentlyValid(),
            'valid_from' => $doc->valid_from?->toIso8601String(),
            'valid_to' => $doc->valid_to?->toIso8601String(),
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
        $data['superseded_by_id'] = $doc->superseded_by_id;
        $data['is_superseded'] = $doc->isSuperseded();

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

    /**
     * Attach document to application (create snapshot relationship).
     * This preserves which documents were used even if person uploads new ones later.
     *
     * When replacing a rejected document, marks the old one as replaced.
     *
     * IMPORTANT: All operations wrapped in transaction for data integrity.
     */
    private function attachDocumentToApplication(
        Application $application,
        Document $document,
        string $attachedBy
    ): void {
        DB::transaction(function () use ($application, $document, $attachedBy) {
            // Create OWNERSHIP relation: Document belongs to Person
            $person = $application->person;
        $ownershipExists = DB::table('documentable_relations')
            ->where('document_id', $document->id)
            ->where('relatable_type', 'App\\Models\\Person')
            ->where('relatable_id', $person->id)
            ->where('relation_context', 'OWNERSHIP')
            ->whereNull('deleted_at')
            ->exists();

        if (!$ownershipExists) {
            DB::table('documentable_relations')->insert([
                'id' => \Illuminate\Support\Str::uuid(),
                'tenant_id' => $application->tenant_id,
                'document_id' => $document->id,
                'relatable_type' => 'App\\Models\\Person',
                'relatable_id' => $person->id,
                'relation_context' => 'OWNERSHIP',
                'created_by' => $attachedBy,
                'created_by_type' => 'App\\Models\\ApplicantAccount',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Check if USAGE relation already exists for this document type in this application
        $existingUsage = DB::table('documentable_relations')
            ->where('document_id', '!=', $document->id) // Different document
            ->where('relatable_type', 'App\\Models\\Application')
            ->where('relatable_id', $application->id)
            ->where('relation_context', 'USAGE')
            ->whereNull('documentable_relations.deleted_at')
            ->join('documents', 'documentable_relations.document_id', '=', 'documents.id')
            ->where('documents.type', $document->type) // Same document type
            ->whereNull('documents.deleted_at')
            ->select('documentable_relations.*', 'documents.id as old_document_id')
            ->first();

        if ($existingUsage) {
            // Get the old document and supersede it with the new one
            $oldDocument = Document::find($existingUsage->old_document_id);
            if ($oldDocument && !$oldDocument->isSuperseded()) {
                // Determine replacement reason based on old document status
                $reason = match ($oldDocument->status) {
                    Document::STATUS_REJECTED => Document::REASON_REJECTED,
                    Document::STATUS_EXPIRED => Document::REASON_EXPIRED,
                    default => Document::REASON_UPDATED,
                };

                // Use supersedeWith method (Active Document Pattern)
                $oldDocument->supersedeWith($document, $reason);
            }

            // Soft delete old relation
            DB::table('documentable_relations')
                ->where('id', $existingUsage->id)
                ->update(['deleted_at' => now()]);

            Log::info('Document superseded in application', [
                'application_id' => $application->id,
                'old_document_id' => $existingUsage->old_document_id,
                'new_document_id' => $document->id,
                'document_type' => $document->type,
                'reason' => $reason ?? 'UPDATED',
            ]);
        }

        // Create or restore USAGE relation: Document used in Application
        $usageRelation = DB::table('documentable_relations')
            ->where('document_id', $document->id)
            ->where('relatable_type', 'App\\Models\\Application')
            ->where('relatable_id', $application->id)
            ->where('relation_context', 'USAGE')
            ->first();

        if ($usageRelation) {
            // Restore if soft-deleted
            if ($usageRelation->deleted_at) {
                DB::table('documentable_relations')
                    ->where('id', $usageRelation->id)
                    ->update([
                        'deleted_at' => null,
                        'updated_at' => now(),
                    ]);
            }
        } else {
            // Create new USAGE relation
            DB::table('documentable_relations')->insert([
                'id' => \Illuminate\Support\Str::uuid(),
                'tenant_id' => $application->tenant_id,
                'document_id' => $document->id,
                'relatable_type' => 'App\\Models\\Application',
                'relatable_id' => $application->id,
                'relation_context' => 'USAGE',
                'created_by' => $attachedBy,
                'created_by_type' => 'App\\Models\\ApplicantAccount',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

            Log::debug('Document attached to application', [
                'application_id' => $application->id,
                'document_id' => $document->id,
                'document_type' => $document->type,
            ]);
        }); // End transaction
    }
}
