<?php

namespace App\Services;

use App\Models\Document;
use App\Models\StaffAccount;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentService
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
    ): Document {
        // Validate documentable entity type (security: prevent arbitrary class associations)
        $documentableClass = get_class($documentable);
        if (!Document::isValidDocumentableType($documentableClass)) {
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

        $category = Document::getCategoryForType($type);
        $isSensitive = $this->isSensitiveType($type);

        // Generate storage path
        $filename = Str::lower($type) . '_' . now()->format('YmdHis') . '.' . $extension;
        $entityType = class_basename($documentable);
        $path = "tenants/{$tenant->id}/{$entityType}/{$documentable->id}/documents/{$filename}";

        // Disk de almacenamiento. Antes se hardcodeaba `s3` en producción,
        // pero eso truena con "Class League\Flysystem\AwsS3V3\PortableVisibilityConverter
        // not found" si el tenant no tiene S3/MinIO instalado (caso de SOFOMS
        // arrancando en disco local + nginx-served). Ahora se lee de
        // `filesystems.documents_disk` que cada tenant configura en `.env`
        // con DOCUMENTS_DISK=local|s3|minio. Si no está declarado, default
        // al `filesystems.default` de Laravel, último fallback `local`.
        $disk = config('filesystems.documents_disk')
            ?? config('filesystems.default')
            ?? 'local';

        // Check for existing document of same type BEFORE storing file
        $existingDoc = Document::where('documentable_type', get_class($documentable))
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
                $versionNumber = $existingDoc ? $existingDoc->version_number + 1 : 1;

                // Crear PRIMERO el nuevo documento; así podemos enlazar la cadena de
                // versionado con el objeto real. Antes se llamaba
                // $existingDoc->supersede($existingDoc->id) — pasaba el id del doc
                // VIEJO, que supersede() resolvía al MISMO doc y lo reemplazaba por sí
                // mismo (superseded_by_id apuntando a sí, is_active inconsistente).
                $newDocument = Document::create([
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
                    'status' => $options['status'] ?? Document::STATUS_PENDING,
                    'is_sensitive' => $isSensitive,
                    'is_encrypted' => $options['encrypt'] ?? false,
                    'previous_version_id' => $existingDoc?->id,
                    'version_number' => $versionNumber,
                    'valid_until' => $options['valid_until'] ?? null,
                    'metadata' => $options['metadata'] ?? null,
                    'created_by' => $options['created_by'] ?? null,
                ]);

                // Supersede la versión anterior con el documento NUEVO real: enlaza
                // superseded_by_id, marca SUPERSEDED y activa el nuevo (Active Document
                // Pattern). Corre dentro de la transacción actual (savepoint anidado).
                if ($existingDoc) {
                    $reason = $existingDoc->isRejected()
                        ? Document::REASON_REJECTED
                        : Document::REASON_UPDATED;

                    // supersedeWith() ya activa el nuevo documento (Active Document Pattern).
                    $existingDoc->supersedeWith($newDocument, $reason);
                } else {
                    // Primera versión (sin doc previo que reemplazar): activamos aquí para
                    // que upload() SIEMPRE deje el documento activo. Antes esto dependía de
                    // que el controller llamara activate() después (fácil de olvidar).
                    $newDocument->activate();
                }

                return $newDocument;
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
        Document $document,
        ?string $approvedBy = null,
        ?array $metadata = null
    ): Document {
        $document->update([
            'status' => Document::STATUS_APPROVED,
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
    public function approve(Document $document, StaffAccount $staff, ?string $notes = null): Document
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
    public function reject(Document $document, StaffAccount $staff, string $reason): Document
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
    public function setOcrData(Document $document, array $ocrData, float $confidence): Document
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
    public function getSignedUrl(Document $document, int $expirationMinutes = 15): ?string
    {
        return $document->getSignedUrl($expirationMinutes);
    }

    // =====================================================
    // Document Deletion
    // =====================================================

    /**
     * Delete a document (soft delete).
     */
    public function delete(Document $document, ?string $deletedBy = null): bool
    {
        if ($deletedBy) {
            $document->update(['deleted_by' => $deletedBy]);
        }

        return $document->delete();
    }

    /**
     * Permanently delete a document and its file.
     */
    public function forceDelete(Document $document): bool
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
        $query = Document::where('documentable_type', get_class($documentable))
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
        $query = Document::where('tenant_id', $tenant->id)
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
        return Document::where('tenant_id', $tenant->id)
            ->expiringSoon($days)
            ->with('documentable')
            ->get();
    }

    /**
     * Mark documents as expiration notified.
     */
    public function markExpirationNotified(array $documentIds): int
    {
        return Document::whereIn('id', $documentIds)
            ->update(['expiration_notified' => true]);
    }

    /**
     * Check if all required documents are approved for an entity.
     */
    public function areAllRequiredApproved(Model $documentable, array $requiredTypes): bool
    {
        $approvedTypes = Document::where('documentable_type', get_class($documentable))
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
        // Handle new structure: {nationals: [], foreigners: []}
        $docTypes = [];

        if (isset($requiredTypes['nationals']) || isset($requiredTypes['foreigners'])) {
            // New format: select based on nationality
            $isForeigner = false;

            // Check if documentable is a Person with nationality
            if ($documentable instanceof \App\Models\Person && $documentable->nationality) {
                $isForeigner = $documentable->nationality !== 'MX';
            }

            // Select appropriate document list
            $docTypes = $isForeigner
                ? ($requiredTypes['foreigners'] ?? [])
                : ($requiredTypes['nationals'] ?? []);
        } else {
            // Legacy format: flat array
            $docTypes = $requiredTypes;
        }

        // Normaliza $docTypes para que siempre sea array<string>.
        // El seeder actual guarda cada doc como ['type'=>..., 'required'=>...,
        // 'description'=>...]; el formato legacy es array<string>. Solo
        // consideramos los marcados required=true para no bloquear el submit
        // por documentos opcionales.
        $docTypes = array_values(array_filter(array_map(function ($d) {
            if (is_array($d)) {
                $required = $d['required'] ?? true;
                return $required ? ($d['type'] ?? null) : null;
            }
            return $d;
        }, $docTypes)));

        $existingTypes = Document::where('documentable_type', get_class($documentable))
            ->where('documentable_id', $documentable->id)
            ->currentVersion()
            ->whereIn('status', [Document::STATUS_PENDING, Document::STATUS_APPROVED])
            ->pluck('type')
            ->toArray();

        return array_values(array_diff($docTypes, $existingTypes));
    }

    /**
     * Get rejected documents that need re-upload.
     */
    public function getRejectedForReupload(Model $documentable): \Illuminate\Database\Eloquent\Collection
    {
        return Document::where('documentable_type', get_class($documentable))
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
        $query = Document::where('documentable_type', get_class($sourceEntity))
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
                    $copiedDocuments[] = Document::create([
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
            Document::TYPE_INE_FRONT,
            Document::TYPE_INE_BACK,
            Document::TYPE_PASSPORT,
            Document::TYPE_CURP_DOC,
            Document::TYPE_RFC_CONSTANCIA,
            Document::TYPE_DRIVER_LICENSE_FRONT,
            Document::TYPE_DRIVER_LICENSE_BACK,
            Document::TYPE_SELFIE,
            Document::TYPE_BANK_STATEMENT,
            Document::TYPE_PAYSLIP,
        ]);
    }
}
