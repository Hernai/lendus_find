<?php

namespace App\Services;

use App\Models\DocumentV2;
use App\Models\StaffAccount;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentV2Service
{
    // =====================================================
    // Constants
    // =====================================================

    /**
     * Allowed file extensions for security.
     */
    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx'];

    /**
     * Allowed MIME types mapped to extensions for double validation.
     */
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png' => ['png'],
        'image/gif' => ['gif'],
        'application/pdf' => ['pdf'],
        'application/msword' => ['doc'],
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => ['docx'],
    ];

    // =====================================================
    // Document Upload & Storage
    // =====================================================

    /**
     * Upload a document and attach to any documentable entity.
     *
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    public function upload(
        Tenant $tenant,
        Model $documentable,
        string $type,
        UploadedFile $file,
        array $options = []
    ): DocumentV2 {
        // Validate documentable entity type (security: prevent arbitrary class associations)
        $documentableClass = get_class($documentable);
        if (!DocumentV2::isValidDocumentableType($documentableClass)) {
            throw new \InvalidArgumentException(
                "Tipo de entidad no permitido para documentos: " . class_basename($documentable)
            );
        }

        // Validate file extension for security
        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, self::ALLOWED_EXTENSIONS)) {
            throw new \InvalidArgumentException("Tipo de archivo no permitido: {$extension}");
        }

        // Validate MIME type matches extension (prevent extension spoofing)
        $mimeType = $file->getMimeType();
        if (!isset(self::ALLOWED_MIME_TYPES[$mimeType])) {
            throw new \InvalidArgumentException("Tipo MIME no permitido: {$mimeType}");
        }
        if (!in_array($extension, self::ALLOWED_MIME_TYPES[$mimeType])) {
            throw new \InvalidArgumentException("La extensión no coincide con el tipo de archivo");
        }

        $category = DocumentV2::getCategoryForType($type);
        $isSensitive = $this->isSensitiveType($type);

        // Generate storage path
        $filename = Str::lower($type) . '_' . now()->format('YmdHis') . '.' . $extension;
        $entityType = class_basename($documentable);
        $path = "tenants/{$tenant->id}/{$entityType}/{$documentable->id}/documents/{$filename}";

        $disk = config('app.env') === 'production' ? 's3' : 'local';

        // Check for existing document of same type BEFORE storing file
        $existingDoc = DocumentV2::where('documentable_type', get_class($documentable))
            ->where('documentable_id', $documentable->id)
            ->where('type', $type)
            ->whereNull('replaced_at')
            ->first();

        if ($existingDoc && $existingDoc->isApproved() && !($options['allow_replace_approved'] ?? false)) {
            throw new \InvalidArgumentException('Cannot replace an approved document');
        }

        // Calculate checksum before storing (SHA-256 for cryptographic strength)
        $checksum = hash_file('sha256', $file->getRealPath());

        // Use transaction for DB operations and handle file storage atomically
        return DB::transaction(function () use (
            $tenant, $documentable, $type, $category, $file, $path, $disk,
            $checksum, $isSensitive, $options, $existingDoc, $extension
        ) {
            // Store file using stream for better memory management
            try {
                Storage::disk($disk)->put($path, fopen($file->getRealPath(), 'r'), 'private');
            } catch (\Exception $e) {
                Log::error('Document upload failed', [
                    'tenant_id' => $tenant->id,
                    'type' => $type,
                    'error' => $e->getMessage(),
                ]);
                throw new \RuntimeException('Error al subir el archivo: ' . $e->getMessage());
            }

            try {
                // If replacing, mark old as superseded
                $versionNumber = 1;
                $previousVersionId = null;
                if ($existingDoc) {
                    $previousVersionId = $existingDoc->id;
                    $versionNumber = $existingDoc->version_number + 1;

                    $reason = $existingDoc->isRejected()
                        ? DocumentV2::REASON_REJECTED
                        : DocumentV2::REASON_UPDATED;

                    $existingDoc->supersede($existingDoc->id, $reason);
                }

                // Create document record
                return DocumentV2::create([
                    'tenant_id' => $tenant->id,
                    'documentable_type' => get_class($documentable),
                    'documentable_id' => $documentable->id,
                    'type' => $type,
                    'category' => $category,
                    'file_name' => $file->getClientOriginalName(),
                    'file_path' => $path,
                    'storage_disk' => $disk,
                    'mime_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                    'checksum' => $checksum,
                    'status' => $options['status'] ?? DocumentV2::STATUS_PENDING,
                    'is_sensitive' => $isSensitive,
                    'is_encrypted' => $options['encrypt'] ?? false,
                    'previous_version_id' => $previousVersionId,
                    'version_number' => $versionNumber,
                    'valid_until' => $options['valid_until'] ?? null,
                    'metadata' => $options['metadata'] ?? null,
                    'created_by' => $options['created_by'] ?? null,
                ]);
            } catch (\Exception $e) {
                // Clean up uploaded file if DB operation fails
                Storage::disk($disk)->delete($path);
                throw $e;
            }
        });
    }

    // =====================================================
    // Document Review & Approval
    // =====================================================

    /**
     * Auto-approve a document (e.g., from KYC validation).
     */
    public function autoApprove(
        DocumentV2 $document,
        ?string $approvedBy = null,
        ?array $metadata = null
    ): DocumentV2 {
        $document->update([
            'status' => DocumentV2::STATUS_APPROVED,
            'reviewed_at' => now(),
            'reviewed_by' => $approvedBy,
            'metadata' => array_merge($document->metadata ?? [], [
                'auto_approved' => true,
                'auto_approved_at' => now()->toIso8601String(),
            ], $metadata ?? []),
        ]);

        return $document->fresh();
    }

    /**
     * Approve a document by staff.
     */
    public function approve(DocumentV2 $document, StaffAccount $staff, ?string $notes = null): DocumentV2
    {
        $document->approve($staff->id);

        if ($notes) {
            $document->update(['notes' => $notes]);
        }

        return $document->fresh();
    }

    /**
     * Reject a document by staff.
     */
    public function reject(DocumentV2 $document, StaffAccount $staff, string $reason): DocumentV2
    {
        $document->reject($staff->id, $reason);

        return $document->fresh();
    }

    // =====================================================
    // OCR Processing
    // =====================================================

    /**
     * Set OCR data for a document.
     */
    public function setOcrData(DocumentV2 $document, array $ocrData, float $confidence): DocumentV2
    {
        $document->setOcrData($ocrData, $confidence);

        return $document->fresh();
    }

    // =====================================================
    // URL Generation
    // =====================================================

    /**
     * Get a signed URL for accessing the document.
     */
    public function getSignedUrl(DocumentV2 $document, int $expirationMinutes = 15): ?string
    {
        return $document->getSignedUrl($expirationMinutes);
    }

    // =====================================================
    // Document Deletion
    // =====================================================

    /**
     * Delete a document (soft delete).
     */
    public function delete(DocumentV2 $document, ?string $deletedBy = null): bool
    {
        if ($deletedBy) {
            $document->update(['deleted_by' => $deletedBy]);
        }

        return $document->delete();
    }

    /**
     * Permanently delete a document and its file.
     */
    public function forceDelete(DocumentV2 $document): bool
    {
        return DB::transaction(function () use ($document) {
            $filePath = $document->file_path;
            $disk = $document->storage_disk;

            // Delete DB record first (transaction will rollback if fails)
            $deleted = $document->forceDelete();

            // Then delete file (if DB delete succeeded)
            if ($deleted && Storage::disk($disk)->exists($filePath)) {
                try {
                    Storage::disk($disk)->delete($filePath);
                } catch (\Exception $e) {
                    // Log but don't fail - file can be cleaned up later
                    Log::warning('Failed to delete document file', [
                        'document_id' => $document->id,
                        'file_path' => $filePath,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return $deleted;
        });
    }

    // =====================================================
    // Query Methods
    // =====================================================

    /**
     * Get documents for a documentable entity.
     */
    public function getDocumentsFor(
        Model $documentable,
        ?string $type = null,
        ?string $category = null,
        bool $currentOnly = true
    ): \Illuminate\Database\Eloquent\Collection {
        $query = DocumentV2::where('documentable_type', get_class($documentable))
            ->where('documentable_id', $documentable->id);

        if ($currentOnly) {
            $query->currentVersion();
        }

        if ($type) {
            $query->ofType($type);
        }

        if ($category) {
            $query->ofCategory($category);
        }

        return $query->orderByDesc('created_at')->get();
    }

    /**
     * Get pending documents for review.
     */
    public function getPendingForReview(
        Tenant $tenant,
        ?string $category = null,
        int $limit = 50
    ): \Illuminate\Database\Eloquent\Collection {
        $query = DocumentV2::where('tenant_id', $tenant->id)
            ->pending()
            ->currentVersion()
            ->with('documentable'); // Eager load to prevent N+1

        if ($category) {
            $query->ofCategory($category);
        }

        return $query->orderBy('created_at')->limit($limit)->get();
    }

    /**
     * Get documents expiring soon.
     */
    public function getExpiringSoon(Tenant $tenant, int $days = 30): \Illuminate\Database\Eloquent\Collection
    {
        return DocumentV2::where('tenant_id', $tenant->id)
            ->expiringSoon($days)
            ->with('documentable')
            ->get();
    }

    /**
     * Mark documents as expiration notified.
     */
    public function markExpirationNotified(array $documentIds): int
    {
        return DocumentV2::whereIn('id', $documentIds)
            ->update(['expiration_notified' => true]);
    }

    /**
     * Check if all required documents are approved for an entity.
     */
    public function areAllRequiredApproved(Model $documentable, array $requiredTypes): bool
    {
        $approvedTypes = DocumentV2::where('documentable_type', get_class($documentable))
            ->where('documentable_id', $documentable->id)
            ->approved()
            ->currentVersion()
            ->pluck('type')
            ->toArray();

        return empty(array_diff($requiredTypes, $approvedTypes));
    }

    /**
     * Get missing required document types for an entity.
     */
    public function getMissingRequired(Model $documentable, array $requiredTypes): array
    {
        $existingTypes = DocumentV2::where('documentable_type', get_class($documentable))
            ->where('documentable_id', $documentable->id)
            ->currentVersion()
            ->whereIn('status', [DocumentV2::STATUS_PENDING, DocumentV2::STATUS_APPROVED])
            ->pluck('type')
            ->toArray();

        return array_values(array_diff($requiredTypes, $existingTypes));
    }

    /**
     * Get rejected documents that need re-upload.
     */
    public function getRejectedForReupload(Model $documentable): \Illuminate\Database\Eloquent\Collection
    {
        return DocumentV2::where('documentable_type', get_class($documentable))
            ->where('documentable_id', $documentable->id)
            ->rejected()
            ->currentVersion()
            ->get();
    }

    // =====================================================
    // Document Copy & Transfer
    // =====================================================

    /**
     * Copy documents from one entity to another (e.g., for application snapshots).
     *
     * @throws \RuntimeException
     */
    public function copyDocuments(
        Model $sourceEntity,
        Model $targetEntity,
        array $types = [],
        bool $onlyApproved = true
    ): array {
        $query = DocumentV2::where('documentable_type', get_class($sourceEntity))
            ->where('documentable_id', $sourceEntity->id)
            ->currentVersion();

        if ($onlyApproved) {
            $query->approved();
        }

        if (!empty($types)) {
            $query->whereIn('type', $types);
        }

        $sourceDocuments = $query->get();

        if ($sourceDocuments->isEmpty()) {
            return [];
        }

        return DB::transaction(function () use ($sourceDocuments, $sourceEntity, $targetEntity) {
            $copiedDocuments = [];
            $copiedFiles = []; // Track copied files for cleanup on failure

            try {
                foreach ($sourceDocuments as $sourceDoc) {
                    // Generate new path safely (don't rely on str_replace with IDs)
                    $filename = basename($sourceDoc->file_path);
                    $targetType = class_basename($targetEntity);
                    $newPath = "tenants/{$sourceDoc->tenant_id}/{$targetType}/{$targetEntity->id}/documents/{$filename}";

                    // Copy file
                    Storage::disk($sourceDoc->storage_disk)->copy(
                        $sourceDoc->file_path,
                        $newPath
                    );
                    $copiedFiles[] = ['disk' => $sourceDoc->storage_disk, 'path' => $newPath];

                    // Create new document record
                    $copiedDocuments[] = DocumentV2::create([
                        'tenant_id' => $sourceDoc->tenant_id,
                        'documentable_type' => get_class($targetEntity),
                        'documentable_id' => $targetEntity->id,
                        'type' => $sourceDoc->type,
                        'category' => $sourceDoc->category,
                        'file_name' => $sourceDoc->file_name,
                        'file_path' => $newPath,
                        'storage_disk' => $sourceDoc->storage_disk,
                        'mime_type' => $sourceDoc->mime_type,
                        'file_size' => $sourceDoc->file_size,
                        'checksum' => $sourceDoc->checksum,
                        'status' => $sourceDoc->status,
                        'is_sensitive' => $sourceDoc->is_sensitive,
                        'is_encrypted' => $sourceDoc->is_encrypted,
                        'ocr_processed' => $sourceDoc->ocr_processed,
                        'ocr_data' => $sourceDoc->ocr_data,
                        'ocr_confidence' => $sourceDoc->ocr_confidence,
                        'metadata' => array_merge($sourceDoc->metadata ?? [], [
                            'copied_from' => $sourceDoc->id,
                            'copied_at' => now()->toIso8601String(),
                        ]),
                    ]);
                }

                return $copiedDocuments;
            } catch (\Exception $e) {
                // Clean up any copied files on failure
                foreach ($copiedFiles as $file) {
                    try {
                        Storage::disk($file['disk'])->delete($file['path']);
                    } catch (\Exception $cleanupError) {
                        Log::warning('Failed to cleanup copied file', [
                            'path' => $file['path'],
                            'error' => $cleanupError->getMessage(),
                        ]);
                    }
                }

                Log::error('Document copy failed', [
                    'source_id' => $sourceEntity->id,
                    'target_id' => $targetEntity->id,
                    'error' => $e->getMessage(),
                ]);

                throw new \RuntimeException('Error al copiar documentos: ' . $e->getMessage());
            }
        });
    }

    // =====================================================
    // Utility Methods
    // =====================================================

    /**
     * Check if a document type is sensitive.
     */
    protected function isSensitiveType(string $type): bool
    {
        return in_array($type, [
            DocumentV2::TYPE_INE_FRONT,
            DocumentV2::TYPE_INE_BACK,
            DocumentV2::TYPE_PASSPORT,
            DocumentV2::TYPE_CURP_DOC,
            DocumentV2::TYPE_RFC_CONSTANCIA,
            DocumentV2::TYPE_DRIVER_LICENSE_FRONT,
            DocumentV2::TYPE_DRIVER_LICENSE_BACK,
            DocumentV2::TYPE_SELFIE,
            DocumentV2::TYPE_BANK_STATEMENT,
            DocumentV2::TYPE_PAYSLIP,
        ]);
    }
}
