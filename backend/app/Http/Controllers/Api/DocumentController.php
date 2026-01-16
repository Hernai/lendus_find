<?php

namespace App\Http\Controllers\Api;

use App\Enums\ApplicationStatus;
use App\Enums\AuditAction;
use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\VerificationMethod;
use App\Enums\VerificationStatus;
use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\AuditLog;
use App\Models\DataVerification;
use App\Models\Document;
use App\Services\VerificationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class DocumentController extends Controller
{
    protected VerificationService $verificationService;

    public function __construct(VerificationService $verificationService)
    {
        $this->verificationService = $verificationService;
    }

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

        $type = strtoupper($request->type ?? '');

        // Allow SELFIE (profile photo) uploads at any time
        $isSelfie = $type === 'SELFIE';

        if (!$isSelfie &&
            !$application->isEditable() &&
            $application->status !== ApplicationStatus::DOCS_PENDING &&
            $application->status !== ApplicationStatus::CORRECTIONS_PENDING &&
            $application->status !== ApplicationStatus::SUBMITTED &&
            $application->status !== ApplicationStatus::IN_REVIEW) {
            return response()->json([
                'message' => 'Cannot upload documents in current status'
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'type' => 'required|string|max:50',
            'file' => 'required|file|mimes:jpg,jpeg,png,webp,pdf|max:10240', // 10MB max
            'metadata' => 'nullable', // Accept metadata from client as JSON string or array
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

        // Check if existing document is verified - cannot replace verified documents
        if ($existingDoc && $existingDoc->status === DocumentStatus::APPROVED) {
            return response()->json([
                'message' => 'No se puede reemplazar un documento verificado'
            ], 400);
        }

        // Capture old document info BEFORE deleting (for timeline comparison)
        $oldDocInfo = null;
        $isReplacement = false;
        if ($existingDoc) {
            $isReplacement = true;
            $oldDocInfo = [
                'filename' => $existingDoc->original_name ?? $existingDoc->file_name ?? 'unknown',
                'size' => $existingDoc->size ?? $existingDoc->file_size ?? 0,
                'mime_type' => $existingDoc->mime_type,
                'status' => $existingDoc->status instanceof DocumentStatus ? $existingDoc->status->value : $existingDoc->status,
                'rejection_reason' => $existingDoc->rejection_reason,
            ];

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

        // Prepare document metadata (merge client metadata with system metadata)
        // Note: metadata comes as JSON string from FormData, need to decode it
        $metadataInput = $request->input('metadata', '{}');
        $docMetadata = is_string($metadataInput) ? json_decode($metadataInput, true) ?? [] : ($metadataInput ?? []);

        // Determine initial document status
        // Auto-approve INE documents when they come from validated KYC process
        // Auto-approve SELFIE when it comes with face match validation
        $initialStatus = DocumentStatus::PENDING;
        $reviewedBy = null;
        $reviewedAt = null;

        $isIneDocument = in_array($type, ['INE_FRONT', 'INE_BACK']);
        $isSelfieDocument = $type === 'SELFIE';
        $hasIneKycValidation = !empty($docMetadata['ine_valid']) && $docMetadata['ine_valid'] === true;
        $hasFaceMatchValidation = !empty($docMetadata['face_match_passed']) && $docMetadata['face_match_passed'] === true;

        if (($isIneDocument && $hasIneKycValidation) || ($isSelfieDocument && $hasFaceMatchValidation)) {
            $initialStatus = DocumentStatus::APPROVED;
            $reviewedBy = $request->user()->id; // System auto-approval recorded under user
            $reviewedAt = now();

            $logContext = [
                'applicant_id' => $applicant->id,
                'application_id' => $application->id,
            ];

            if ($isIneDocument) {
                $logContext['ocr_curp'] = $docMetadata['ocr_curp'] ?? null;
                \Log::info("[DocumentController] Auto-approving {$type} - KYC INE validated", $logContext);
            } else {
                $logContext['face_match_score'] = $docMetadata['face_match_score'] ?? null;
                \Log::info("[DocumentController] Auto-approving {$type} - Face match validated", $logContext);
            }
        }

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
            'status' => $initialStatus,
            'metadata' => $docMetadata,
            'reviewed_by' => $reviewedBy,
            'reviewed_at' => $reviewedAt,
        ]);

        // Record document verification if auto-approved
        if ($initialStatus === DocumentStatus::APPROVED) {
            if ($isIneDocument) {
                $side = $type === 'INE_FRONT' ? 'front' : 'back';

                // Extract OCR data from various possible metadata formats
                $ocrDataFromMeta = $docMetadata['ocr_data'] ?? [];
                $ocrData = [
                    'curp' => $docMetadata['ocr_curp'] ?? $ocrDataFromMeta['curp'] ?? null,
                    'first_name' => $docMetadata['ocr_first_name'] ?? $ocrDataFromMeta['nombres'] ?? null,
                    'last_name_1' => $docMetadata['ocr_last_name_1'] ?? $ocrDataFromMeta['apellido_paterno'] ?? null,
                    'last_name_2' => $docMetadata['ocr_last_name_2'] ?? $ocrDataFromMeta['apellido_materno'] ?? null,
                    'birth_date' => $docMetadata['ocr_birth_date'] ?? $ocrDataFromMeta['fecha_nacimiento'] ?? null,
                ];
                $this->verificationService->verifyIneDocument($applicant, $side, $document->id, $ocrData);
            } elseif ($isSelfieDocument) {
                // Record selfie verification with face match data
                $faceMatchData = [
                    'face_match_score' => $docMetadata['face_match_score'] ?? null,
                    'face_match_passed' => $docMetadata['face_match_passed'] ?? false,
                    'liveness_passed' => $docMetadata['liveness_passed'] ?? null,
                    'liveness_score' => $docMetadata['liveness_score'] ?? null,
                ];
                $this->verificationService->verifySelfieDocument($applicant, $document->id, $faceMatchData);
            }
        }

        // Get metadata for timeline
        $metadata = $request->attributes->get('metadata', []);
        $ipAddress = $metadata['ip_address'] ?? $request->ip();
        $userAgent = $metadata['user_agent'] ?? $request->userAgent();
        $approximateLocation = $this->getApproximateLocation($ipAddress);

        // Get document type label
        $docTypeEnum = DocumentType::tryFrom($type);
        $docTypeLabel = $docTypeEnum ? $docTypeEnum->description() : $type;

        // Build new document info
        $newDocInfo = [
            'filename' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'extension' => strtoupper($extension),
        ];

        // Add to application timeline with metadata
        $history = $application->status_history ?? [];
        $timelineEntry = [
            'action' => 'DOC_UPLOADED',
            'document' => $type,
            'document_label' => $docTypeLabel,
            'is_replacement' => $isReplacement,
            'new_file' => $newDocInfo,
            'user_id' => $request->user()->id,
            'timestamp' => now()->toIso8601String(),
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'location' => $approximateLocation,
        ];

        // Add auto-approval info if applicable
        if ($initialStatus === DocumentStatus::APPROVED) {
            $timelineEntry['auto_approved'] = true;
            $timelineEntry['approval_reason'] = 'Validado por KYC (Nubarium)';
        }

        // Add old file info if this was a replacement
        if ($isReplacement && $oldDocInfo) {
            $timelineEntry['old_file'] = $oldDocInfo;
        }

        $history[] = $timelineEntry;
        $application->status_history = $history;
        $application->save();

        // Add to audit log with full metadata
        AuditLog::log(
            AuditAction::DOCUMENT_UPLOADED->value,
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

        // Broadcast document uploaded event
        event(new \App\Events\DocumentUploaded($document, $request->user()));

        // Check if all corrections are complete and update status
        if ($application->status === ApplicationStatus::CORRECTIONS_PENDING) {
            $this->checkAndUpdateCorrectionStatus($application, $applicant, $request->user());
        }

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

        if (!$application->isEditable() && $document->status !== DocumentStatus::REJECTED) {
            return response()->json([
                'message' => 'Cannot delete document in current status'
            ], 400);
        }

        // Soft delete - NO eliminar archivo físico para permitir restauración
        $docId = $document->id;
        $docType = $document->type instanceof \App\Enums\DocumentType ? $document->type->value : $document->type;
        $docName = $document->original_name ?? $document->file_name ?? 'unknown';
        $document->delete(); // Soft delete por trait SoftDeletes

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
            AuditAction::DOCUMENT_DELETED->value,
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

        // Broadcast document deleted event
        event(new \App\Events\DocumentDeleted(
            $docId,
            $docType,
            $application->id,
            $applicant->id,
            $tenant->id,
            $request->user()
        ));

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
            'type' => $document->type instanceof \App\Enums\DocumentType ? $document->type->value : $document->type,
            'name' => $document->original_name,
            'status' => $document->status instanceof \App\Enums\DocumentStatus ? $document->status->value : $document->status,
            'rejection_reason' => $document->rejection_reason,
            'mime_type' => $document->mime_type,
            'size' => $document->size,
            'uploaded_at' => $document->created_at->toIso8601String(),
        ];
    }

    /**
     * Get approximate location from IP address using a free geolocation service.
     */
    private function getApproximateLocation(string $ip): ?string
    {
        // Skip for localhost/private IPs
        if (in_array($ip, ['127.0.0.1', '::1']) ||
            str_starts_with($ip, '192.168.') ||
            str_starts_with($ip, '10.') ||
            str_starts_with($ip, '172.')) {
            return 'Local/Privada';
        }

        try {
            $response = @file_get_contents("http://ip-api.com/json/{$ip}?fields=status,city,regionName,country&lang=es");
            if ($response) {
                $data = json_decode($response, true);
                if ($data && ($data['status'] ?? '') === 'success') {
                    $parts = array_filter([
                        $data['city'] ?? null,
                        $data['regionName'] ?? null,
                        $data['country'] ?? null,
                    ]);
                    return implode(', ', $parts) ?: null;
                }
            }
        } catch (\Exception $e) {
            // Silently fail, location is optional
        }

        return null;
    }

    /**
     * Check if all corrections (data fields and documents) are complete and update application status.
     */
    private function checkAndUpdateCorrectionStatus(Application $application, $applicant, $user): void
    {
        // Check if there are still rejected data fields
        $stillRejectedFields = DataVerification::where('applicant_id', $applicant->id)
            ->where('status', VerificationStatus::REJECTED)
            ->exists();

        if ($stillRejectedFields) {
            return; // Still has field corrections pending
        }

        // Check if there are still rejected documents for this applicant's applications
        $applicationIds = Application::where('applicant_id', $applicant->id)->pluck('id');
        $stillRejectedDocs = Document::whereIn('application_id', $applicationIds)
            ->where('status', DocumentStatus::REJECTED)
            ->exists();

        if ($stillRejectedDocs) {
            return; // Still has document corrections pending
        }

        // Find when the application entered CORRECTIONS_PENDING status
        $correctionStartDate = $this->getCorrectionCycleStartDate($application);

        // Get fields corrected AFTER entering CORRECTIONS_PENDING
        $correctedFieldLabels = [];
        if ($correctionStartDate) {
            $correctedFields = DataVerification::where('applicant_id', $applicant->id)
                ->where('status', VerificationStatus::CORRECTED)
                ->where('corrected_at', '>=', $correctionStartDate)
                ->pluck('field_name')
                ->unique();

            foreach ($correctedFields as $fieldName) {
                $correctedFieldLabels[] = DataVerification::getFieldLabel($fieldName);
            }
        }

        // Get documents uploaded AFTER entering CORRECTIONS_PENDING (from timeline)
        $uploadedDocLabels = $this->getUploadedDocumentsDuringCorrection($application, $correctionStartDate);

        // Build reason message based on what was actually corrected
        $reason = $this->buildCorrectionReasonMessage($correctedFieldLabels, $uploadedDocLabels);

        // Update all applications in CORRECTIONS_PENDING status
        $pendingApplications = Application::where('applicant_id', $applicant->id)
            ->where('status', ApplicationStatus::CORRECTIONS_PENDING)
            ->get();

        foreach ($pendingApplications as $app) {
            $app->changeStatus(
                ApplicationStatus::IN_REVIEW->value,
                $reason,
                $user->id
            );
        }
    }

    /**
     * Get the date when the application entered CORRECTIONS_PENDING status.
     */
    private function getCorrectionCycleStartDate(Application $application): ?Carbon
    {
        $history = $application->status_history ?? [];

        // Find the most recent entry where status changed TO CORRECTIONS_PENDING
        $correctionEntries = collect($history)
            ->filter(fn ($entry) => ($entry['to'] ?? null) === ApplicationStatus::CORRECTIONS_PENDING->value)
            ->sortByDesc('timestamp');

        $lastEntry = $correctionEntries->first();

        if ($lastEntry && isset($lastEntry['timestamp'])) {
            return Carbon::parse($lastEntry['timestamp']);
        }

        return null;
    }

    /**
     * Get list of documents uploaded during the correction cycle.
     */
    private function getUploadedDocumentsDuringCorrection(Application $application, ?Carbon $startDate): array
    {
        if (!$startDate) {
            return [];
        }

        $history = $application->status_history ?? [];

        $uploadedDocs = collect($history)
            ->filter(function ($entry) use ($startDate) {
                if (($entry['action'] ?? null) !== 'DOC_UPLOADED') {
                    return false;
                }
                if (!isset($entry['timestamp'])) {
                    return false;
                }
                return Carbon::parse($entry['timestamp'])->gte($startDate);
            })
            ->pluck('document')
            ->unique()
            ->map(function ($docType) {
                // Convert document type to human-readable label
                $enumType = DocumentType::tryFrom($docType);
                return $enumType ? $enumType->description() : $docType;
            })
            ->values()
            ->toArray();

        return $uploadedDocs;
    }

    /**
     * Build a reason message for the status change based on what was corrected.
     */
    private function buildCorrectionReasonMessage(array $correctedFields, array $uploadedDocs): string
    {
        $parts = [];

        if (!empty($correctedFields)) {
            $parts[] = 'Datos corregidos: ' . implode(', ', $correctedFields);
        }

        if (!empty($uploadedDocs)) {
            $parts[] = 'Documentos actualizados: ' . implode(', ', $uploadedDocs);
        }

        if (empty($parts)) {
            return 'Correcciones completadas';
        }

        return implode('. ', $parts);
    }
}
