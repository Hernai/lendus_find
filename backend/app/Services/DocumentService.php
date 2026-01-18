<?php

namespace App\Services;

use App\Contracts\DocumentStorageInterface;
use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\Application;
use App\Models\Applicant;
use App\Models\Tenant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Facades\Image;

/**
 * Document storage and management service.
 *
 * Implements DocumentStorageInterface for consistent storage operations.
 */
class DocumentService implements DocumentStorageInterface
{
    /**
     * The storage disk to use.
     */
    protected string $disk;

    /**
     * Max file size in bytes (10MB).
     */
    protected int $maxFileSize = 10485760;

    /**
     * Allowed MIME types.
     */
    protected array $allowedMimeTypes = [
        'image/jpeg',
        'image/jpg',
        'image/png',
        'image/gif',
        'image/webp',
        'application/pdf',
    ];

    /**
     * Create a new DocumentService instance.
     */
    public function __construct()
    {
        $this->disk = config('app.env') === 'production' ? 's3' : 'local';
    }

    /**
     * Upload a document file.
     */
    public function upload(
        UploadedFile $file,
        Tenant $tenant,
        Applicant $applicant,
        ?Application $application = null,
        string $type = 'OTHER',
        array $metadata = []
    ): Document {
        // Validate file
        $this->validateFile($file);

        // Generate storage path
        $path = $this->generatePath($tenant, $applicant, $application, $type, $file);

        // Process image if needed (resize, compress)
        $fileContent = $this->processFile($file);

        // Upload to storage
        Storage::disk($this->disk)->put($path, $fileContent, 'private');

        // Calculate checksum
        $checksum = md5($fileContent);

        // Create document record
        return Document::create([
            'tenant_id' => $tenant->id,
            'applicant_id' => $applicant->id,
            'application_id' => $application?->id,
            'type' => strtoupper($type),
            'name' => $file->getClientOriginalName(),
            'file_name' => $file->getClientOriginalName(),
            'storage_disk' => $this->disk,
            'file_path' => $path,
            'mime_type' => $file->getMimeType(),
            'file_size' => strlen($fileContent),
            'status' => DocumentStatus::PENDING,
            'checksum' => $checksum,
            'metadata' => $metadata,
            'is_sensitive' => $this->isSensitiveDocType($type),
        ]);
    }

    /**
     * Get a temporary signed URL for a document.
     */
    public function getSignedUrl(Document $document, int $expirationMinutes = 15): string
    {
        if ($this->disk === 's3') {
            return Storage::disk('s3')->temporaryUrl(
                $document->file_path,
                now()->addMinutes($expirationMinutes)
            );
        }

        // For local development, return a route URL
        return route('api.documents.download', ['document' => $document->uuid]);
    }

    /**
     * Get a presigned URL for direct upload (S3).
     */
    public function getPresignedUploadUrl(
        Tenant $tenant,
        Applicant $applicant,
        ?Application $application,
        string $type,
        string $fileName,
        string $mimeType
    ): array {
        if ($this->disk !== 's3') {
            throw new \RuntimeException('Presigned URLs are only available for S3 storage');
        }

        $extension = pathinfo($fileName, PATHINFO_EXTENSION);
        $path = $this->generatePathFromParts($tenant, $applicant, $application, $type, $extension);

        // Generate presigned POST URL
        $s3Client = Storage::disk('s3')->getClient();
        $bucket = config('filesystems.disks.s3.bucket');

        $formInputs = [
            'acl' => 'private',
            'key' => $path,
        ];

        $options = [
            ['acl' => 'private'],
            ['bucket' => $bucket],
            ['starts-with', '$key', $path],
            ['content-length-range', 0, $this->maxFileSize],
        ];

        $expires = '+15 minutes';

        $postObject = new \Aws\S3\PostObjectV4(
            $s3Client,
            $bucket,
            $formInputs,
            $options,
            $expires
        );

        return [
            'url' => $postObject->getFormAttributes()['action'],
            'fields' => $postObject->getFormInputs(),
            'path' => $path,
            'expires_at' => now()->addMinutes(15)->toIso8601String(),
        ];
    }

    /**
     * Confirm an upload made via presigned URL.
     */
    public function confirmPresignedUpload(
        Tenant $tenant,
        Applicant $applicant,
        ?Application $application,
        string $type,
        string $path,
        string $originalName
    ): Document {
        // Verify file exists
        if (!Storage::disk($this->disk)->exists($path)) {
            throw new \RuntimeException('File not found at specified path');
        }

        // Get file info
        $mimeType = Storage::disk($this->disk)->mimeType($path);
        $size = Storage::disk($this->disk)->size($path);

        // Create document record
        return Document::create([
            'tenant_id' => $tenant->id,
            'applicant_id' => $applicant->id,
            'application_id' => $application?->id,
            'type' => strtoupper($type),
            'name' => $originalName,
            'file_name' => $originalName,
            'storage_disk' => $this->disk,
            'file_path' => $path,
            'mime_type' => $mimeType,
            'file_size' => $size,
            'status' => DocumentStatus::PENDING,
            'is_sensitive' => $this->isSensitiveDocType($type),
        ]);
    }

    /**
     * Delete a document and its file.
     */
    public function delete(Document $document): bool
    {
        // Delete from storage
        if ($document->file_path) {
            Storage::disk($document->storage_disk)->delete($document->file_path);
        }

        // Soft delete the record
        return $document->delete();
    }

    /**
     * Replace a document with a new file.
     */
    public function replace(
        Document $document,
        UploadedFile $file
    ): Document {
        // Delete old file
        if ($document->file_path) {
            Storage::disk($document->storage_disk)->delete($document->file_path);
        }

        // Validate new file
        $this->validateFile($file);

        // Generate new path
        $tenant = $document->tenant ?? Tenant::find($document->tenant_id);
        $applicant = $document->applicant;
        $application = $document->application;

        $docType = $document->type instanceof \App\Enums\DocumentType ? $document->type->value : $document->type;
        $path = $this->generatePath($tenant, $applicant, $application, $docType, $file);

        // Process and upload
        $fileContent = $this->processFile($file);
        Storage::disk($this->disk)->put($path, $fileContent, 'private');

        // Update document record
        $document->update([
            'name' => $file->getClientOriginalName(),
            'file_name' => $file->getClientOriginalName(),
            'storage_disk' => $this->disk,
            'file_path' => $path,
            'mime_type' => $file->getMimeType(),
            'file_size' => strlen($fileContent),
            'status' => DocumentStatus::PENDING,
            'checksum' => md5($fileContent),
            'rejection_reason' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
        ]);

        return $document->fresh();
    }

    /**
     * Copy a document to a new application.
     */
    public function copyToApplication(Document $document, Application $newApplication): Document
    {
        // Copy file in storage
        $newPath = str_replace(
            "applications/{$document->application->uuid}",
            "applications/{$newApplication->uuid}",
            $document->file_path
        );

        Storage::disk($document->storage_disk)->copy(
            $document->file_path,
            $newPath
        );

        // Create new document record
        return Document::create([
            'tenant_id' => $document->tenant_id,
            'applicant_id' => $document->applicant_id,
            'application_id' => $newApplication->id,
            'type' => $document->type,
            'name' => $document->name ?? $document->file_name,
            'file_name' => $document->file_name,
            'storage_disk' => $document->storage_disk,
            'file_path' => $newPath,
            'mime_type' => $document->mime_type,
            'file_size' => $document->file_size,
            'status' => DocumentStatus::PENDING,
            'checksum' => $document->checksum,
            'metadata' => $document->metadata,
            'is_sensitive' => $document->is_sensitive,
        ]);
    }

    /**
     * Get all documents for an application grouped by type.
     */
    public function getApplicationDocuments(Application $application): array
    {
        $documents = $application->documents()->get();

        return [
            'all' => $documents->map(fn($doc) => $this->formatDocument($doc)),
            'by_type' => $documents->groupBy('type')->map(fn($group) => $group->first()),
            'pending' => $documents->where('status', DocumentStatus::PENDING)->count(),
            'approved' => $documents->where('status', DocumentStatus::APPROVED)->count(),
            'rejected' => $documents->where('status', DocumentStatus::REJECTED)->count(),
        ];
    }

    /**
     * Check if all required documents are uploaded and approved.
     */
    public function hasAllRequiredDocuments(Application $application, array $requiredTypes): array
    {
        $documents = $application->documents()->get()->keyBy('type');

        $missing = [];
        $pending = [];
        $rejected = [];

        foreach ($requiredTypes as $type) {
            if (!isset($documents[$type])) {
                $missing[] = $type;
            } elseif ($documents[$type]->status === DocumentStatus::PENDING) {
                $pending[] = $type;
            } elseif ($documents[$type]->status === DocumentStatus::REJECTED) {
                $rejected[] = $type;
            }
        }

        return [
            'complete' => empty($missing) && empty($pending) && empty($rejected),
            'missing' => $missing,
            'pending' => $pending,
            'rejected' => $rejected,
        ];
    }

    /**
     * Validate the uploaded file.
     */
    protected function validateFile(UploadedFile $file): void
    {
        if ($file->getSize() > $this->maxFileSize) {
            throw new \InvalidArgumentException('File size exceeds maximum allowed size of 10MB');
        }

        if (!in_array($file->getMimeType(), $this->allowedMimeTypes)) {
            throw new \InvalidArgumentException('File type not allowed. Allowed types: JPG, PNG, GIF, WEBP, PDF');
        }
    }

    /**
     * Process the file (resize images, etc.).
     */
    protected function processFile(UploadedFile $file): string
    {
        $mimeType = $file->getMimeType();

        // For images, resize if too large
        if (str_starts_with($mimeType, 'image/') && $mimeType !== 'application/pdf') {
            return $this->processImage($file);
        }

        return file_get_contents($file->getPathname());
    }

    /**
     * Process and resize image if needed.
     */
    protected function processImage(UploadedFile $file): string
    {
        $maxWidth = 2000;
        $maxHeight = 2000;
        $quality = 85;

        // Use Intervention Image if available
        if (class_exists('Intervention\Image\Facades\Image')) {
            $image = Image::make($file->getPathname());

            // Resize if larger than max dimensions
            if ($image->width() > $maxWidth || $image->height() > $maxHeight) {
                $image->resize($maxWidth, $maxHeight, function ($constraint) {
                    $constraint->aspectRatio();
                    $constraint->upsize();
                });
            }

            // Auto-orient based on EXIF
            $image->orientate();

            return $image->encode($file->extension(), $quality)->encoded;
        }

        // Fallback: return original
        return file_get_contents($file->getPathname());
    }

    /**
     * Generate storage path for document.
     */
    protected function generatePath(
        Tenant $tenant,
        Applicant $applicant,
        ?Application $application,
        string $type,
        UploadedFile $file
    ): string {
        $extension = $file->getClientOriginalExtension();
        return $this->generatePathFromParts($tenant, $applicant, $application, $type, $extension);
    }

    /**
     * Generate storage path from parts.
     */
    protected function generatePathFromParts(
        Tenant $tenant,
        Applicant $applicant,
        ?Application $application,
        string $type,
        string $extension
    ): string {
        $timestamp = now()->format('YmdHis');
        $filename = Str::lower($type) . '_' . $timestamp . '.' . strtolower($extension);

        if ($application) {
            return "tenants/{$tenant->uuid}/applications/{$application->uuid}/{$filename}";
        }

        return "tenants/{$tenant->uuid}/applicants/{$applicant->uuid}/{$filename}";
    }

    /**
     * Check if document type is sensitive (requires encryption).
     */
    protected function isSensitiveDocType(string $type): bool
    {
        $sensitiveTypes = [
            'INE_FRONT',
            'INE_BACK',
            'PASSPORT',
            'RFC_CONSTANCIA',
            'CURP',
            'BANK_STATEMENT',
            'PROOF_OF_INCOME',
            'TAX_RETURN',
        ];

        return in_array(strtoupper($type), $sensitiveTypes);
    }

    /**
     * Format document for API response.
     */
    protected function formatDocument(Document $document): array
    {
        return [
            'id' => $document->uuid,
            'type' => $document->type instanceof \App\Enums\DocumentType ? $document->type->value : $document->type,
            'name' => $document->name ?? $document->file_name,
            'status' => $document->status instanceof \App\Enums\DocumentStatus ? $document->status->value : $document->status,
            'rejection_reason' => $document->rejection_reason,
            'mime_type' => $document->mime_type,
            'size' => $document->file_size,
            'uploaded_at' => $document->created_at->toIso8601String(),
            'reviewed_at' => $document->reviewed_at?->toIso8601String(),
        ];
    }
}
