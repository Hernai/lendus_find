<?php

namespace App\Http\Controllers\Api\V2\Applicant;

use App\Http\Controllers\Api\V2\Traits\ApiResponses;
use App\Http\Controllers\Controller;
use App\Models\ApplicantAccount;
use App\Models\Document;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Document History Controller.
 *
 * Provides endpoints for viewing document history, supersession chains,
 * and temporal validity queries for audit and compliance purposes.
 */
class DocumentHistoryController extends Controller
{
    use ApiResponses;

    /**
     * Get document history for a specific type.
     *
     * Returns chronological history of all documents of a given type for the person.
     *
     * GET /v2/applicant/documents/history/{type}
     *
     * @param Request $request
     * @param string $type Document type (e.g., PROOF_OF_ADDRESS)
     * @return JsonResponse
     */
    public function index(Request $request, string $type): JsonResponse
    {
        /** @var ApplicantAccount $account */
        $account = $request->user();
        $person = $account->person;

        if (!$person) {
            \Log::warning('Document history access without person', [
                'account_id' => $account->id,
            ]);
            return $this->badRequest('PROFILE_INCOMPLETE', 'Debes completar tu perfil.');
        }

        // Validate and sanitize document type
        $type = strtoupper(trim($type));
        if (!in_array($type, Document::validTypes(), true)) {
            \Log::warning('Invalid document type requested in history', [
                'person_id' => $person->id,
                'requested_type' => $type,
            ]);
            return $this->badRequest('INVALID_TYPE', 'Tipo de documento inválido.');
        }

        // Get all documents of this type for the person (chronological order)
        try {
            $documents = Document::where('documentable_type', get_class($person))
                ->where('documentable_id', $person->id)
                ->where('type', $type)
                ->orderBy('created_at', 'desc')
                ->get();
        } catch (\Exception $e) {
            \Log::error('Failed to fetch document history', [
                'person_id' => $person->id,
                'type' => $type,
                'error' => $e->getMessage(),
            ]);
            return $this->error('FETCH_FAILED', 'No se pudo obtener el historial de documentos.');
        }

        // For each document, get the applications it was used in
        $history = $documents->map(function ($doc) {
            // Get applications where this document was used (via USAGE relations)
            $applications = DB::table('documentable_relations')
                ->join('applications', 'documentable_relations.relatable_id', '=', 'applications.id')
                ->where('documentable_relations.document_id', $doc->id)
                ->where('documentable_relations.relatable_type', 'App\\Models\\Application')
                ->where('documentable_relations.relation_context', 'USAGE')
                ->whereNull('documentable_relations.deleted_at')
                ->select('applications.id', 'applications.folio', 'applications.status', 'applications.created_at')
                ->get();

            return [
                'id' => $doc->id,
                'status' => $doc->status,
                'status_label' => $doc->status_label,
                'is_active' => $doc->is_active,
                'is_currently_valid' => $doc->isCurrentlyValid(),
                'valid_from' => $doc->valid_from?->toIso8601String(),
                'valid_to' => $doc->valid_to?->toIso8601String(),
                'superseded_by_id' => $doc->superseded_by_id,
                'replacement_reason' => $doc->replacement_reason,
                'rejection_reason' => $doc->rejection_reason,
                'reviewed_at' => $doc->reviewed_at?->toIso8601String(),
                'created_at' => $doc->created_at?->toIso8601String(),
                'applications' => $applications->map(fn($app) => [
                    'id' => $app->id,
                    'folio' => $app->folio,
                    'status' => $app->status,
                    'created_at' => $app->created_at,
                ])->toArray(),
            ];
        });

        return $this->success([
            'type' => $type,
            'type_label' => Document::typeLabels()[$type] ?? $type,
            'documents' => $history,
            'total' => $history->count(),
        ]);
    }

    /**
     * Get supersession chain for a specific document.
     *
     * Returns the complete history chain (both forward and backward) for a document.
     *
     * GET /v2/applicant/documents/{id}/supersession-chain
     *
     * @param Request $request
     * @param string $id Document ID
     * @return JsonResponse
     */
    public function supersessionChain(Request $request, string $id): JsonResponse
    {
        /** @var ApplicantAccount $account */
        $account = $request->user();
        $person = $account->person;

        if (!$person) {
            return $this->badRequest('PROFILE_INCOMPLETE', 'Debes completar tu perfil.');
        }

        // Find the document and verify ownership
        $document = Document::where('id', $id)
            ->where('tenant_id', $account->tenant_id)
            ->where('documentable_type', get_class($person))
            ->where('documentable_id', $person->id)
            ->first();

        if (!$document) {
            return $this->notFound('Documento no encontrado.');
        }

        // Get complete history chain
        try {
            $chain = $document->getCompleteHistoryChain();
        } catch (\Exception $e) {
            \Log::error('Failed to get supersession chain', [
                'document_id' => $id,
                'person_id' => $person->id,
                'error' => $e->getMessage(),
            ]);
            return $this->error('CHAIN_FETCH_FAILED', 'No se pudo obtener la cadena de reemplazos.');
        }

        $formattedChain = $chain->map(function ($doc) use ($id) {
            return [
                'id' => $doc->id,
                'is_current' => $doc->id === $id,
                'status' => $doc->status,
                'status_label' => $doc->status_label,
                'is_active' => $doc->is_active,
                'is_currently_valid' => $doc->isCurrentlyValid(),
                'valid_from' => $doc->valid_from?->toIso8601String(),
                'valid_to' => $doc->valid_to?->toIso8601String(),
                'superseded_by_id' => $doc->superseded_by_id,
                'replacement_reason' => $doc->replacement_reason,
                'rejection_reason' => $doc->rejection_reason,
                'created_at' => $doc->created_at?->toIso8601String(),
                'reviewed_at' => $doc->reviewed_at?->toIso8601String(),
            ];
        });

        return $this->success([
            'document_id' => $id,
            'type' => $document->type,
            'type_label' => $document->type_label,
            'chain' => $formattedChain,
            'chain_length' => $formattedChain->count(),
        ]);
    }

    /**
     * Get documents valid at a specific date.
     *
     * Returns all documents that were valid at a given date for the person.
     *
     * GET /v2/applicant/documents/valid-at?date={date}&type={type}
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function validAt(Request $request): JsonResponse
    {
        /** @var ApplicantAccount $account */
        $account = $request->user();
        $person = $account->person;

        if (!$person) {
            return $this->badRequest('PROFILE_INCOMPLETE', 'Debes completar tu perfil.');
        }

        $validated = $request->validate([
            'date' => 'required|date',
            'type' => 'nullable|string|in:' . implode(',', Document::validTypes()),
        ]);

        $date = \Carbon\Carbon::parse($validated['date']);
        $type = $validated['type'] ?? null;

        // Query documents valid at the given date
        $query = Document::where('documentable_type', get_class($person))
            ->where('documentable_id', $person->id)
            ->validAt($date);

        if ($type) {
            $query->where('type', $type);
        }

        $documents = $query->orderBy('type')->get();

        $formattedDocuments = $documents->map(function ($doc) use ($date) {
            return [
                'id' => $doc->id,
                'type' => $doc->type,
                'type_label' => $doc->type_label,
                'status' => $doc->status,
                'status_label' => $doc->status_label,
                'is_active' => $doc->is_active,
                'was_valid_at_date' => $doc->isValidAt($date),
                'valid_from' => $doc->valid_from?->toIso8601String(),
                'valid_to' => $doc->valid_to?->toIso8601String(),
                'created_at' => $doc->created_at?->toIso8601String(),
            ];
        });

        return $this->success([
            'date' => $date->toIso8601String(),
            'type' => $type,
            'documents' => $formattedDocuments,
            'total' => $formattedDocuments->count(),
        ]);
    }

    /**
     * Get timeline view of document history with applications.
     *
     * Returns a combined timeline of documents and applications for comprehensive audit.
     *
     * GET /v2/applicant/documents/timeline?type={type}
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function timeline(Request $request): JsonResponse
    {
        /** @var ApplicantAccount $account */
        $account = $request->user();
        $person = $account->person;

        if (!$person) {
            return $this->badRequest('PROFILE_INCOMPLETE', 'Debes completar tu perfil.');
        }

        $type = $request->query('type');

        if ($type && !in_array($type, Document::validTypes())) {
            return $this->badRequest('INVALID_TYPE', 'Tipo de documento inválido.');
        }

        // Get all documents of the specified type (or all types)
        $documentsQuery = Document::where('documentable_type', get_class($person))
            ->where('documentable_id', $person->id);

        if ($type) {
            $documentsQuery->where('type', $type);
        }

        $documents = $documentsQuery->orderBy('created_at', 'desc')->get();

        // Build timeline
        $timeline = [];

        foreach ($documents as $doc) {
            // Document upload event
            $timeline[] = [
                'type' => 'DOCUMENT_UPLOAD',
                'date' => $doc->created_at->toIso8601String(),
                'timestamp' => $doc->created_at->timestamp,
                'document_id' => $doc->id,
                'document_type' => $doc->type,
                'document_type_label' => $doc->type_label,
                'document_status' => $doc->status,
                'is_active' => $doc->is_active,
            ];

            // Document review event (if reviewed)
            if ($doc->reviewed_at) {
                $timeline[] = [
                    'type' => $doc->status === Document::STATUS_APPROVED ? 'DOCUMENT_APPROVED' : 'DOCUMENT_REJECTED',
                    'date' => $doc->reviewed_at->toIso8601String(),
                    'timestamp' => $doc->reviewed_at->timestamp,
                    'document_id' => $doc->id,
                    'document_type' => $doc->type,
                    'document_type_label' => $doc->type_label,
                    'document_status' => $doc->status,
                    'rejection_reason' => $doc->rejection_reason,
                ];
            }

            // Document supersession event (if superseded)
            if ($doc->superseded_by_id && $doc->replaced_at) {
                $timeline[] = [
                    'type' => 'DOCUMENT_SUPERSEDED',
                    'date' => $doc->replaced_at->toIso8601String(),
                    'timestamp' => $doc->replaced_at->timestamp,
                    'document_id' => $doc->id,
                    'document_type' => $doc->type,
                    'document_type_label' => $doc->type_label,
                    'superseded_by_id' => $doc->superseded_by_id,
                    'replacement_reason' => $doc->replacement_reason,
                ];
            }

            // Get applications where this document was used
            $applications = DB::table('documentable_relations')
                ->join('applications', 'documentable_relations.relatable_id', '=', 'applications.id')
                ->where('documentable_relations.document_id', $doc->id)
                ->where('documentable_relations.relatable_type', 'App\\Models\\Application')
                ->where('documentable_relations.relation_context', 'USAGE')
                ->select('applications.*', 'documentable_relations.created_at as attached_at')
                ->get();

            foreach ($applications as $app) {
                $timeline[] = [
                    'type' => 'DOCUMENT_USED_IN_APPLICATION',
                    'date' => $app->attached_at,
                    'timestamp' => \Carbon\Carbon::parse($app->attached_at)->timestamp,
                    'document_id' => $doc->id,
                    'document_type' => $doc->type,
                    'document_type_label' => $doc->type_label,
                    'application_id' => $app->id,
                    'application_folio' => $app->folio,
                    'application_status' => $app->status,
                ];
            }
        }

        // Sort timeline by timestamp (most recent first)
        usort($timeline, fn($a, $b) => $b['timestamp'] <=> $a['timestamp']);

        // Remove timestamp (used only for sorting)
        $timeline = array_map(function ($item) {
            unset($item['timestamp']);
            return $item;
        }, $timeline);

        return $this->success([
            'type' => $type,
            'timeline' => $timeline,
            'total_events' => count($timeline),
        ]);
    }
}
