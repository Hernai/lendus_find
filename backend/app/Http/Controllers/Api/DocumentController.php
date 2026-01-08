<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\AuditLog;
use App\Models\Document;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class DocumentController extends Controller
{
    /**
     * List documents for an application.
     */
    public function index(Request $request, Application $application): JsonResponse
    {
        $applicant = $request->user()->applicant;

        if (!$applicant || $application->applicant_id !== $applicant->id) {
            return response()->json([
                'message' => 'Application not found'
            ], 404);
        }

        $documents = $application->documents()->orderBy('type')->get();

        return response()->json([
            'data' => $documents->map(fn($doc) => $this->formatDocument($doc))
        ]);
    }

    /**
     * Upload a document for an application.
     */
    public function store(Request $request, Application $application): JsonResponse
    {
        $applicant = $request->user()->applicant;

        if (!$applicant || $application->applicant_id !== $applicant->id) {
            return response()->json([
                'message' => 'Application not found'
            ], 404);
        }

        if (!$application->isEditable() && $application->status !== Application::STATUS_DOCS_PENDING) {
            return response()->json([
                'message' => 'Cannot upload documents in current status'
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'type' => 'required|string|max:50',
            'file' => 'required|file|mimes:jpg,jpeg,png,pdf|max:10240', // 10MB max
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $file = $request->file('file');
        $tenant = $request->attributes->get('tenant');
        $type = strtoupper($request->type);

        // Check if document of this type already exists
        $existingDoc = $application->documents()
            ->where('type', $type)
            ->first();

        if ($existingDoc) {
            // Delete old file
            if ($existingDoc->storage_path) {
                Storage::disk($existingDoc->storage_disk)->delete($existingDoc->storage_path);
            }
            $existingDoc->delete();
        }

        // Generate path: tenants/{tenant_uuid}/applications/{app_uuid}/{type}_{timestamp}.{ext}
        $extension = $file->getClientOriginalExtension();
        $filename = Str::lower($type) . '_' . now()->format('YmdHis') . '.' . $extension;
        $path = "tenants/{$tenant->id}/applications/{$application->id}/{$filename}";

        // Store file (use 's3' in production, 'local' for development)
        $disk = config('app.env') === 'production' ? 's3' : 'local';
        Storage::disk($disk)->put($path, file_get_contents($file), 'private');

        // Create document record
        $document = Document::create([
            'tenant_id' => $tenant->id,
            'applicant_id' => $applicant->id,
            'application_id' => $application->id,
            'type' => $type,
            'original_name' => $file->getClientOriginalName(),
            'storage_disk' => $disk,
            'storage_path' => $path,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'status' => Document::STATUS_PENDING,
        ]);

        // Add to application timeline
        $history = $application->status_history ?? [];
        $history[] = [
            'action' => 'DOC_UPLOADED',
            'document' => $type,
            'filename' => $file->getClientOriginalName(),
            'user_id' => $request->user()->id,
            'timestamp' => now()->toIso8601String(),
        ];
        $application->status_history = $history;
        $application->save();

        // Add to audit log with full metadata
        $metadata = $request->attributes->get('metadata', []);
        AuditLog::log(
            AuditLog::ACTION_DOCUMENT_UPLOADED,
            $tenant->id,
            array_merge($metadata, [
                'user_id' => $request->user()->id,
                'applicant_id' => $applicant->id,
                'application_id' => $application->id,
                'entity_type' => 'Document',
                'entity_id' => $document->id,
                'new_values' => [
                    'type' => $type,
                    'filename' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                    'size' => $file->getSize(),
                ],
            ])
        );

        return response()->json([
            'message' => 'Document uploaded successfully',
            'data' => $this->formatDocument($document)
        ], 201);
    }

    /**
     * Delete a document.
     */
    public function destroy(Request $request, Application $application, Document $document): JsonResponse
    {
        $applicant = $request->user()->applicant;

        if (!$applicant || $application->applicant_id !== $applicant->id) {
            return response()->json([
                'message' => 'Application not found'
            ], 404);
        }

        if ($document->application_id !== $application->id) {
            return response()->json([
                'message' => 'Document not found'
            ], 404);
        }

        if (!$application->isEditable() && $document->status !== Document::STATUS_REJECTED) {
            return response()->json([
                'message' => 'Cannot delete document in current status'
            ], 400);
        }

        // Delete from storage
        if ($document->storage_path) {
            Storage::disk($document->storage_disk)->delete($document->storage_path);
        }

        $docType = $document->type;
        $docName = $document->original_name;
        $document->delete();

        // Add to application timeline
        $history = $application->status_history ?? [];
        $history[] = [
            'action' => 'DOC_DELETED',
            'document' => $docType,
            'filename' => $docName,
            'user_id' => $request->user()->id,
            'timestamp' => now()->toIso8601String(),
        ];
        $application->status_history = $history;
        $application->save();

        // Add to audit log with full metadata
        $metadata = $request->attributes->get('metadata', []);
        $tenant = $request->attributes->get('tenant');
        AuditLog::log(
            AuditLog::ACTION_DOCUMENT_DELETED,
            $tenant->id,
            array_merge($metadata, [
                'user_id' => $request->user()->id,
                'applicant_id' => $applicant->id,
                'application_id' => $application->id,
                'entity_type' => 'Document',
                'old_values' => [
                    'type' => $docType,
                    'filename' => $docName,
                ],
            ])
        );

        return response()->json([
            'message' => 'Document deleted'
        ]);
    }

    /**
     * Get a temporary URL for viewing a document (for S3).
     */
    public function getUrl(Request $request, Document $document): JsonResponse
    {
        $applicant = $request->user()->applicant;
        $application = $document->application;

        if (!$applicant || $application->applicant_id !== $applicant->id) {
            return response()->json([
                'message' => 'Document not found'
            ], 404);
        }

        if ($document->storage_disk === 's3') {
            $url = Storage::disk('s3')->temporaryUrl(
                $document->storage_path,
                now()->addMinutes(15)
            );
        } else {
            // For local development, return a route URL
            $url = route('documents.download', ['document' => $document->id]);
        }

        return response()->json([
            'url' => $url,
            'expires_in' => 900 // 15 minutes
        ]);
    }

    /**
     * Download a document (for local development).
     */
    public function download(Request $request, Document $document)
    {
        $applicant = $request->user()->applicant;
        $application = $document->application;

        // Verify ownership
        if (!$applicant || $application->applicant_id !== $applicant->id) {
            abort(404);
        }

        // Only for local storage
        if ($document->storage_disk !== 'local') {
            abort(400, 'Direct download not available for this storage type');
        }

        $path = Storage::disk('local')->path($document->storage_path);

        if (!file_exists($path)) {
            abort(404, 'File not found');
        }

        return response()->file($path, [
            'Content-Type' => $document->mime_type,
            'Content-Disposition' => 'inline; filename="' . $document->original_name . '"',
        ]);
    }

    /**
     * Format document for response.
     */
    private function formatDocument(Document $document): array
    {
        return [
            'id' => $document->id,
            'type' => $document->type,
            'name' => $document->original_name,
            'status' => $document->status,
            'rejection_reason' => $document->rejection_reason,
            'mime_type' => $document->mime_type,
            'size' => $document->size,
            'uploaded_at' => $document->created_at->toIso8601String(),
        ];
    }
}
