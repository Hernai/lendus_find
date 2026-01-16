<?php

namespace App\Contracts;

use App\Models\Document;
use Illuminate\Http\UploadedFile;

/**
 * Interface for document storage operations.
 *
 * Abstracts storage implementation (S3, local, MinIO)
 * to allow consistent document handling across environments.
 */
interface DocumentStorageInterface
{
    /**
     * Get a temporary signed URL for a document.
     *
     * @param Document $document The document to get URL for
     * @param int $expirationMinutes URL expiration time in minutes
     * @return string The signed URL
     */
    public function getSignedUrl(Document $document, int $expirationMinutes = 15): string;

    /**
     * Delete a document and its file.
     *
     * @param Document $document The document to delete
     * @return bool True if deleted successfully
     */
    public function delete(Document $document): bool;

    /**
     * Replace a document with a new file.
     *
     * @param Document $document The document to replace
     * @param UploadedFile $file The new file
     * @return Document The updated document
     */
    public function replace(Document $document, UploadedFile $file): Document;
}
