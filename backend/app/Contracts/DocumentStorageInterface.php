<?php

namespace App\Contracts;

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
     * Store an uploaded file.
     *
     * @param UploadedFile $file The file to store
     * @param string $path Storage path/prefix
     * @param array $options Additional storage options
     * @return array{success: bool, path?: string, error?: string}
     */
    public function store(UploadedFile $file, string $path, array $options = []): array;

    /**
     * Store a file from base64 content.
     *
     * @param string $base64Content Base64 encoded file content
     * @param string $path Storage path including filename
     * @param string|null $mimeType MIME type of the file
     * @return array{success: bool, path?: string, error?: string}
     */
    public function storeBase64(string $base64Content, string $path, ?string $mimeType = null): array;

    /**
     * Get a temporary signed URL for a stored file.
     *
     * @param string $path Path to the stored file
     * @param int $expirationMinutes URL expiration time in minutes
     * @return string|null The signed URL or null if not available
     */
    public function getSignedUrl(string $path, int $expirationMinutes = 60): ?string;

    /**
     * Delete a stored file.
     *
     * @param string $path Path to the file
     * @return bool True if deleted successfully
     */
    public function delete(string $path): bool;

    /**
     * Check if a file exists.
     *
     * @param string $path Path to check
     * @return bool True if file exists
     */
    public function exists(string $path): bool;

    /**
     * Get file contents.
     *
     * @param string $path Path to the file
     * @return string|null File contents or null if not found
     */
    public function get(string $path): ?string;
}
