<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\ApplicationNote;
use App\Models\DataVerification;
use App\Models\Document;
use App\Models\Reference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ApplicationController extends Controller
{
    /**
     * List all applications with filters.
     * Agents only see applications assigned to them.
     */
    public function index(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');
        $user = $request->user();

        $query = Application::where('tenant_id', $tenant->id)
            ->with([
                'applicant:id,first_name,last_name_1,last_name_2,full_name,phone,email,curp',
                'product:id,name,type',
                'assignedAgent:id,name',
            ]);

        // Agents only see assigned applications
        if ($user->isAgent() && !$user->canViewAllApplications()) {
            $query->where('assigned_to', $user->id);
        }

        // Filters
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('folio', 'LIKE', "%{$search}%")
                    ->orWhereHas('applicant', function ($q) use ($search) {
                        $q->where('curp', 'LIKE', "%{$search}%")
                            ->orWhere('first_name', 'LIKE', "%{$search}%")
                            ->orWhere('last_name_1', 'LIKE', "%{$search}%")
                            ->orWhere('last_name_2', 'LIKE', "%{$search}%")
                            ->orWhere('full_name', 'LIKE', "%{$search}%")
                            ->orWhere('phone', 'LIKE', "%{$search}%")
                            ->orWhere('email', 'LIKE', "%{$search}%");
                    });
            });
        }

        if ($dateFrom = $request->input('date_from')) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo = $request->input('date_to')) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        if ($assignedTo = $request->input('assigned_to')) {
            $query->where('assigned_to', $assignedTo);
        }

        // Sorting
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $perPage = min($request->input('per_page', 20), 100);
        $applications = $query->paginate($perPage);

        return response()->json([
            'data' => collect($applications->items())->map(fn($app) => $this->formatApplication($app)),
            'meta' => [
                'current_page' => $applications->currentPage(),
                'last_page' => $applications->lastPage(),
                'per_page' => $applications->perPage(),
                'total' => $applications->total(),
            ]
        ]);
    }

    /**
     * Get a specific application with full details.
     */
    public function show(Request $request, Application $application): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        if ($application->tenant_id !== $tenant->id) {
            return response()->json(['message' => 'Application not found'], 404);
        }

        $application->load([
            'applicant.addresses',
            'applicant.currentEmployment',
            'applicant.bankAccounts',
            'product',
            'documents',
            'references',
            'notes.user:id,name',
            'assignedAgent:id,name,email',
        ]);

        return response()->json([
            'data' => $this->formatApplicationDetailed($application)
        ]);
    }

    /**
     * Update application status.
     */
    public function updateStatus(Request $request, Application $application): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        if ($application->tenant_id !== $tenant->id) {
            return response()->json(['message' => 'Application not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:' . implode(',', [
                Application::STATUS_IN_REVIEW,
                Application::STATUS_DOCS_PENDING,
                Application::STATUS_APPROVED,
                Application::STATUS_REJECTED,
                Application::STATUS_CANCELLED,
                Application::STATUS_DISBURSED,
            ]),
            'reason' => 'nullable|string|max:500',
            'rejection_reason' => 'required_if:status,REJECTED|nullable|string|max:500',
            'disbursement_reference' => 'required_if:status,DISBURSED|nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $newStatus = $request->status;
        $reason = $request->input('reason', $request->rejection_reason);

        // Additional validations based on status transition
        if ($newStatus === Application::STATUS_DISBURSED) {
            if ($application->status !== Application::STATUS_APPROVED) {
                return response()->json([
                    'message' => 'Only approved applications can be disbursed'
                ], 400);
            }
            $application->disbursement_reference = $request->disbursement_reference;
        }

        $application->changeStatus($newStatus, $reason, $request->user()->id);

        return response()->json([
            'message' => 'Status updated',
            'data' => $this->formatApplication($application->fresh()->load(['applicant', 'product']))
        ]);
    }

    /**
     * Create a counter-offer.
     */
    public function counterOffer(Request $request, Application $application): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        if ($application->tenant_id !== $tenant->id) {
            return response()->json(['message' => 'Application not found'], 404);
        }

        if (!in_array($application->status, [
            Application::STATUS_IN_REVIEW,
            Application::STATUS_DOCS_PENDING,
        ])) {
            return response()->json([
                'message' => 'Counter-offer can only be made for applications in review'
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:1000',
            'term_months' => 'required|integer|min:1|max:120',
            'interest_rate' => 'required|numeric|min:0|max:100',
            'payment_frequency' => 'required|in:WEEKLY,BIWEEKLY,QUINCENAL,MONTHLY,MENSUAL',
            'reason' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $application->createCounterOffer(
            $request->amount,
            $request->term_months,
            $request->interest_rate,
            $request->payment_frequency,
            $request->reason,
            $request->user()->id
        );

        return response()->json([
            'message' => 'Counter-offer created',
            'data' => $this->formatApplication($application->fresh()->load(['applicant', 'product']))
        ]);
    }

    /**
     * Assign application to an agent.
     */
    public function assign(Request $request, Application $application): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        if ($application->tenant_id !== $tenant->id) {
            return response()->json(['message' => 'Application not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $application->assigned_to = $request->user_id;
        $application->assigned_at = now();
        $application->save();

        // Add to timeline
        $application->changeStatus(
            $application->status,
            'Assigned to agent',
            $request->user()->id
        );

        return response()->json([
            'message' => 'Application assigned',
            'data' => $this->formatApplication($application->fresh()->load(['applicant', 'product', 'assignedAgent']))
        ]);
    }

    /**
     * Add a note to an application.
     */
    public function addNote(Request $request, Application $application): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        if ($application->tenant_id !== $tenant->id) {
            return response()->json(['message' => 'Application not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'content' => 'required|string|max:2000',
            'is_internal' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $note = ApplicationNote::create([
            'tenant_id' => $tenant->id,
            'application_id' => $application->id,
            'user_id' => $request->user()->id,
            'content' => $request->content,
            'is_internal' => $request->input('is_internal', true),
        ]);

        // Add to application timeline
        $history = $application->status_history ?? [];
        $history[] = [
            'action' => 'NOTE_ADDED',
            'note_preview' => mb_substr($request->content, 0, 50) . (mb_strlen($request->content) > 50 ? '...' : ''),
            'is_internal' => $request->input('is_internal', true),
            'user_id' => $request->user()->id,
            'timestamp' => now()->toIso8601String(),
        ];
        $application->status_history = $history;
        $application->save();

        return response()->json([
            'message' => 'Note added',
            'data' => [
                'id' => $note->id,
                'content' => $note->content,
                'author' => $request->user()->name,
                'is_internal' => $note->is_internal,
                'created_at' => $note->created_at->toIso8601String(),
            ]
        ], 201);
    }

    /**
     * Approve a document.
     */
    public function approveDocument(Request $request, Application $application, Document $document): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        if ($application->tenant_id !== $tenant->id || $document->application_id !== $application->id) {
            return response()->json(['message' => 'Document not found'], 404);
        }

        if ($document->status !== Document::STATUS_PENDING) {
            return response()->json([
                'message' => 'Only pending documents can be approved'
            ], 400);
        }

        $document->status = Document::STATUS_APPROVED;
        $document->reviewed_by = $request->user()->id;
        $document->reviewed_at = now();
        $document->save();

        // Add to application timeline
        $history = $application->status_history ?? [];
        $history[] = [
            'action' => 'DOC_APPROVED',
            'document' => $document->type,
            'user_id' => $request->user()->id,
            'timestamp' => now()->toIso8601String(),
        ];
        $application->status_history = $history;
        $application->save();

        return response()->json([
            'message' => 'Document approved',
            'data' => [
                'id' => $document->id,
                'type' => $document->type,
                'status' => $document->status,
            ]
        ]);
    }

    /**
     * Reject a document.
     */
    public function rejectDocument(Request $request, Application $application, Document $document): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        if ($application->tenant_id !== $tenant->id || $document->application_id !== $application->id) {
            return response()->json(['message' => 'Document not found'], 404);
        }

        if ($document->status !== Document::STATUS_PENDING) {
            return response()->json([
                'message' => 'Only pending documents can be rejected'
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:50',
            'comment' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $document->status = Document::STATUS_REJECTED;
        $document->rejection_reason = $request->reason;
        $document->rejection_comment = $request->comment;
        $document->reviewed_by = $request->user()->id;
        $document->reviewed_at = now();
        $document->save();

        // Add to application timeline
        $history = $application->status_history ?? [];
        $history[] = [
            'action' => 'DOC_REJECTED',
            'document' => $document->type,
            'reason' => $request->reason,
            'user_id' => $request->user()->id,
            'timestamp' => now()->toIso8601String(),
        ];
        $application->status_history = $history;
        $application->save();

        // Optionally change application status to DOCS_PENDING
        if ($application->status === Application::STATUS_IN_REVIEW) {
            $application->changeStatus(
                Application::STATUS_DOCS_PENDING,
                "Document rejected: {$document->type}",
                $request->user()->id
            );
        }

        return response()->json([
            'message' => 'Document rejected',
            'data' => [
                'id' => $document->id,
                'type' => $document->type,
                'status' => $document->status,
                'rejection_reason' => $document->rejection_reason,
            ]
        ]);
    }

    /**
     * Verify a reference.
     */
    public function verifyReference(Request $request, Application $application, Reference $reference): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        if ($application->tenant_id !== $tenant->id || $reference->application_id !== $application->id) {
            return response()->json(['message' => 'Reference not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'result' => 'required|in:VERIFIED,NOT_VERIFIED,NO_ANSWER',
            'notes' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $reference->is_verified = $request->result === 'VERIFIED';
        $reference->verification_result = $request->result;
        $reference->verification_notes = $request->notes;
        $reference->verified_by = $request->user()->id;
        $reference->verified_at = now();
        $reference->save();

        // Add to application timeline
        $history = $application->status_history ?? [];
        $history[] = [
            'action' => 'REF_VERIFIED',
            'reference' => $reference->full_name,
            'result' => $request->result,
            'user_id' => $request->user()->id,
            'timestamp' => now()->toIso8601String(),
        ];
        $application->status_history = $history;
        $application->save();

        return response()->json([
            'message' => 'Reference verification recorded',
            'data' => [
                'id' => $reference->id,
                'full_name' => $reference->full_name,
                'is_verified' => $reference->is_verified,
                'result' => $reference->verification_result,
            ]
        ]);
    }

    /**
     * Format application for list response.
     */
    private function formatApplication(Application $application): array
    {
        return [
            'id' => $application->id,
            'folio' => $application->folio,
            'status' => $application->status,
            'applicant' => $application->applicant ? [
                'id' => $application->applicant->id,
                'name' => $application->applicant->full_name,
                'phone' => $application->applicant->phone,
                'email' => $application->applicant->email,
            ] : null,
            'product' => $application->product ? [
                'id' => $application->product->id,
                'name' => $application->product->name,
                'type' => $application->product->type,
            ] : null,
            'requested_amount' => (float) $application->requested_amount,
            'approved_amount' => $application->approved_amount ? (float) $application->approved_amount : null,
            'term_months' => $application->term_months,
            'payment_frequency' => $application->payment_frequency,
            'monthly_payment' => (float) $application->monthly_payment,
            'assigned_to' => $application->assignedAgent?->name,
            'risk_level' => $application->risk_level,
            'created_at' => $application->created_at->toIso8601String(),
            'updated_at' => $application->updated_at->toIso8601String(),
        ];
    }

    /**
     * Format application with full details for admin view.
     */
    private function formatApplicationDetailed(Application $application): array
    {
        $applicant = $application->applicant;
        $primaryAddress = $applicant?->addresses?->where('is_primary', true)->first()
            ?? $applicant?->addresses?->first();
        $currentEmployment = $applicant?->currentEmployment;
        $primaryBankAccount = $applicant?->bankAccounts?->where('is_primary', true)->first()
            ?? $applicant?->bankAccounts?->first();

        return [
            'id' => $application->id,
            'folio' => $application->folio,
            'status' => $application->status,
            'created_at' => $application->created_at->toIso8601String(),
            'updated_at' => $application->updated_at->toIso8601String(),
            'assigned_to' => $application->assignedAgent?->name,

            // Applicant data
            'applicant' => $applicant ? [
                'id' => $applicant->id,
                'full_name' => $applicant->full_name,
                'first_name' => $applicant->first_name,
                'last_name_1' => $applicant->last_name_1,
                'last_name_2' => $applicant->last_name_2,
                'email' => $applicant->email,
                'phone' => $applicant->phone,
                'phone_secondary' => $applicant->phone_secondary,
                'curp' => $applicant->curp,
                'rfc' => $applicant->rfc,
                'ine_clave' => $applicant->ine_clave,
                'birth_date' => $applicant->birth_date?->format('Y-m-d'),
                'nationality' => $applicant->nationality,
                'gender' => $applicant->gender,
                'marital_status' => $applicant->marital_status,
                'education_level' => $applicant->education_level,
                'dependents_count' => $applicant->dependents_count,
            ] : null,

            // Primary Address
            'address' => $primaryAddress ? [
                'id' => $primaryAddress->id,
                'type' => $primaryAddress->type,
                'street' => $primaryAddress->street,
                'ext_number' => $primaryAddress->ext_number,
                'int_number' => $primaryAddress->int_number,
                'neighborhood' => $primaryAddress->neighborhood,
                'postal_code' => $primaryAddress->postal_code,
                'city' => $primaryAddress->city,
                'municipality' => $primaryAddress->municipality,
                'state' => $primaryAddress->state,
                'housing_type' => $primaryAddress->housing_type,
                'housing_type_label' => $primaryAddress->housing_type_label,
                'years_at_address' => $primaryAddress->years_at_address,
                'monthly_rent' => $primaryAddress->monthly_rent ? (float) $primaryAddress->monthly_rent : null,
                'is_verified' => $primaryAddress->is_verified,
                'full_address' => $primaryAddress->full_address,
            ] : null,

            // All addresses
            'addresses' => $applicant?->addresses?->map(fn($addr) => [
                'id' => $addr->id,
                'type' => $addr->type,
                'is_primary' => $addr->is_primary,
                'street' => $addr->street,
                'postal_code' => $addr->postal_code,
                'city' => $addr->city,
                'state' => $addr->state,
            ]),

            // Current Employment
            'employment' => $currentEmployment ? [
                'id' => $currentEmployment->id,
                'employment_type' => $currentEmployment->employment_type,
                'occupation' => $currentEmployment->occupation,
                'company_name' => $currentEmployment->company_name,
                'company_industry' => $currentEmployment->company_industry,
                'job_title' => $currentEmployment->job_title,
                'start_date' => $currentEmployment->start_date?->format('Y-m-d'),
                'seniority_years' => $currentEmployment->seniority_years,
                'contract_type' => $currentEmployment->contract_type,
                'monthly_income' => $currentEmployment->monthly_income ? (float) $currentEmployment->monthly_income : null,
                'monthly_net_income' => $currentEmployment->monthly_net_income ? (float) $currentEmployment->monthly_net_income : null,
                'payment_frequency' => $currentEmployment->payment_frequency,
                'other_income' => $currentEmployment->other_income ? (float) $currentEmployment->other_income : null,
                'other_income_source' => $currentEmployment->other_income_source,
                'is_verified' => $currentEmployment->is_verified,
            ] : null,

            // Primary Bank Account
            'bank_account' => $primaryBankAccount ? [
                'id' => $primaryBankAccount->id,
                'type' => $primaryBankAccount->type,
                'bank_name' => $primaryBankAccount->bank_name,
                'clabe' => $primaryBankAccount->clabe,
                'account_type' => $primaryBankAccount->account_type,
                'holder_name' => $primaryBankAccount->holder_name,
                'is_own_account' => $primaryBankAccount->is_own_account,
                'is_verified' => $primaryBankAccount->is_verified,
            ] : null,

            // Loan details
            'loan' => [
                'product_name' => $application->product?->name,
                'product_id' => $application->product?->id,
                'requested_amount' => (float) $application->requested_amount,
                'approved_amount' => $application->approved_amount ? (float) $application->approved_amount : null,
                'term_months' => $application->term_months,
                'payment_frequency' => $application->payment_frequency,
                'interest_rate' => (float) $application->interest_rate,
                'opening_commission' => (float) $application->opening_commission,
                'monthly_payment' => (float) $application->monthly_payment,
                'total_to_pay' => (float) $application->total_to_pay,
                'cat' => $application->cat ? (float) $application->cat : null,
                'purpose' => $application->purpose,
                'purpose_description' => $application->purpose_description,
            ],

            // Risk scoring
            'risk' => [
                'score' => $application->risk_score,
                'level' => $application->risk_level,
                'data' => $application->scoring_data,
            ],

            // Signature data
            'signature' => $applicant ? [
                'has_signed' => $applicant->hasSigned(),
                'signature_base64' => $applicant->signature_base64,
                'signature_date' => $applicant->signature_date?->toIso8601String(),
                'signature_ip' => $applicant->signature_ip,
            ] : null,

            // Field-level verifications
            'field_verifications' => $applicant?->dataVerifications?->mapWithKeys(fn($v) => [
                $v->field_name => [
                    'verified' => $v->is_verified,
                    'method' => $v->method,
                    'verified_at' => $v->created_at?->toIso8601String(),
                    'verified_by' => $v->verifier?->name,
                    'notes' => $v->notes,
                ]
            ]) ?? [],

            // Legacy verification status (for backwards compatibility)
            'verification' => [
                'phone_verified' => $applicant?->phone_verified_at !== null,
                'phone_verified_at' => $applicant?->phone_verified_at?->toIso8601String(),
                'email_verified' => $applicant?->email_verified_at !== null,
                'email_verified_at' => $applicant?->email_verified_at?->toIso8601String(),
                'identity_verified' => $applicant?->identity_verified_at !== null,
                'identity_verified_at' => $applicant?->identity_verified_at?->toIso8601String(),
                'address_verified' => $primaryAddress?->is_verified ?? false,
                'employment_verified' => $currentEmployment?->is_verified ?? false,
            ],

            // Required documents from product
            'required_documents' => $application->product?->required_docs ?? [],

            // Documents (uploaded)
            'documents' => $application->documents->map(fn($doc) => [
                'id' => $doc->id,
                'type' => $doc->type,
                'name' => $doc->name ?? $doc->file_name,
                'status' => $doc->status,
                'rejection_reason' => $doc->rejection_reason,
                'rejection_comment' => $doc->rejection_comment,
                'uploaded_at' => $doc->created_at->toIso8601String(),
                'reviewed_at' => $doc->reviewed_at?->toIso8601String(),
                'mime_type' => $doc->mime_type,
            ]),

            // References
            'references' => $application->references->map(fn($ref) => [
                'id' => $ref->id,
                'full_name' => $ref->full_name,
                'relationship' => $ref->relationship,
                'phone' => $ref->phone,
                'verified' => $ref->is_verified,
                'verification_result' => $ref->verification_result,
                'verification_notes' => $ref->verification_notes,
                'verified_at' => $ref->verified_at?->toIso8601String(),
            ]),

            // Notes
            'notes' => $application->notes->map(fn($note) => [
                'id' => $note->id,
                'text' => $note->content,
                'author' => $note->user?->name ?? 'System',
                'is_internal' => $note->is_internal,
                'created_at' => $note->created_at->toIso8601String(),
            ]),

            // Timeline (from status_history)
            'timeline' => collect($application->status_history ?? [])->map(fn($h, $i) => [
                'id' => (string) ($i + 1),
                'action' => $h['action'] ?? 'STATUS_CHANGE',
                'description' => $this->getTimelineDescription($h),
                'author' => 'System', // Could look up user_id if needed
                'created_at' => $h['timestamp'] ?? $application->created_at->toIso8601String(),
            ]),

            // Additional data
            'rejection_reason' => $application->rejection_reason,
            'internal_notes' => $application->internal_notes,
            'disbursement_reference' => $application->disbursement_reference,
            'approved_at' => $application->approved_at?->toIso8601String(),
            'disbursed_at' => $application->disbursed_at?->toIso8601String(),
        ];
    }

    /**
     * Get a temporary URL for viewing a document.
     */
    public function getDocumentUrl(Request $request, Application $application, Document $document): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        if ($application->tenant_id !== $tenant->id || $document->application_id !== $application->id) {
            return response()->json(['message' => 'Document not found'], 404);
        }

        if ($document->storage_disk === 's3') {
            $url = \Illuminate\Support\Facades\Storage::disk('s3')->temporaryUrl(
                $document->file_path,
                now()->addMinutes(15)
            );
        } else {
            // For local development, return a route URL with a signed token
            $url = route('api.admin.documents.download', [
                'application' => $application->id,
                'document' => $document->id
            ]);
        }

        return response()->json([
            'url' => $url,
            'mime_type' => $document->mime_type,
            'original_name' => $document->name ?? $document->file_name,
            'expires_in' => 900 // 15 minutes
        ]);
    }

    /**
     * Download a document (for local development).
     */
    public function downloadDocument(Request $request, Application $application, Document $document)
    {
        $tenant = $request->attributes->get('tenant');

        if ($application->tenant_id !== $tenant->id || $document->application_id !== $application->id) {
            abort(404);
        }

        // Only for local storage
        if ($document->storage_disk !== 'local') {
            abort(400, 'Direct download not available for this storage type');
        }

        $path = \Illuminate\Support\Facades\Storage::disk('local')->path($document->file_path);

        if (!file_exists($path)) {
            abort(404, 'File not found');
        }

        $fileName = $document->name ?? $document->file_name ?? 'document';

        return response()->file($path, [
            'Content-Type' => $document->mime_type,
            'Content-Disposition' => 'inline; filename="' . $fileName . '"',
        ]);
    }

    /**
     * Get description for timeline entry.
     */
    private function getTimelineDescription(array $historyEntry): string
    {
        $action = $historyEntry['action'] ?? 'STATUS_CHANGE';

        return match ($action) {
            'DOC_UPLOADED' => 'Documento subido: ' . ($historyEntry['document'] ?? ''),
            'DOC_DELETED' => 'Documento eliminado: ' . ($historyEntry['document'] ?? ''),
            'DOC_APPROVED' => 'Documento aprobado: ' . ($historyEntry['document'] ?? ''),
            'DOC_REJECTED' => 'Documento rechazado: ' . ($historyEntry['document'] ?? '') .
                ($historyEntry['reason'] ? ' - ' . $historyEntry['reason'] : ''),
            'REF_VERIFIED' => 'Referencia verificada: ' . ($historyEntry['reference'] ?? '') .
                ' (' . ($historyEntry['result'] ?? '') . ')',
            'NOTE_ADDED' => 'Nota agregada: ' . ($historyEntry['note_preview'] ?? ''),
            'DATA_VERIFIED' => ($historyEntry['verified'] ?? true)
                ? 'Dato verificado: ' . ($historyEntry['field_label'] ?? $historyEntry['field'] ?? '')
                : 'Verificación removida: ' . ($historyEntry['field_label'] ?? $historyEntry['field'] ?? ''),
            default => isset($historyEntry['to']) ?
                'Estado cambiado a ' . $historyEntry['to'] .
                    ($historyEntry['reason'] ? ': ' . $historyEntry['reason'] : '') :
                ($historyEntry['reason'] ?? 'Actualización')
        };
    }

    /**
     * Verify applicant data field.
     */
    public function verifyData(Request $request, Application $application): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        if ($application->tenant_id !== $tenant->id) {
            return response()->json(['message' => 'Application not found'], 404);
        }

        $validFields = [
            'first_name', 'last_name_1', 'last_name_2', 'curp', 'rfc', 'ine_clave',
            'birth_date', 'phone', 'email', 'address', 'employment'
        ];

        $validator = Validator::make($request->all(), [
            'field' => 'required|in:' . implode(',', $validFields),
            'verified' => 'required|boolean',
            'method' => 'sometimes|in:MANUAL,OTP,API,DOCUMENT,BUREAU',
            'notes' => 'sometimes|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $field = $request->field;
        $verified = $request->verified;
        $method = $request->input('method', 'MANUAL');
        $notes = $request->notes;
        $applicant = $application->applicant;

        if (!$applicant) {
            return response()->json(['message' => 'Applicant not found'], 404);
        }

        // Get the field value being verified
        $fieldValue = match ($field) {
            'address' => $applicant->primaryAddress?->full_address,
            'employment' => $applicant->currentEmployment?->company_name,
            default => $applicant->{$field} ?? null
        };

        if ($verified) {
            // Create or update verification record
            $verification = DataVerification::updateOrCreate(
                [
                    'applicant_id' => $applicant->id,
                    'field_name' => $field,
                ],
                [
                    'tenant_id' => $tenant->id,
                    'field_value' => $fieldValue,
                    'method' => $method,
                    'is_verified' => true,
                    'notes' => $notes,
                    'verified_by' => $request->user()->id,
                ]
            );
        } else {
            // Remove verification
            DataVerification::where('applicant_id', $applicant->id)
                ->where('field_name', $field)
                ->delete();
        }

        // Also update legacy fields for backwards compatibility
        $verifiedAt = $verified ? now() : null;
        switch ($field) {
            case 'phone':
                $applicant->update(['phone_verified_at' => $verifiedAt]);
                break;
            case 'email':
                $applicant->update(['email_verified_at' => $verifiedAt]);
                break;
            case 'address':
                $address = $applicant->primaryAddress;
                if ($address) {
                    $address->update(['is_verified' => $verified]);
                }
                break;
        }

        // Add to status history
        $history = $application->status_history ?? [];
        $history[] = [
            'action' => 'DATA_VERIFIED',
            'field' => $field,
            'field_label' => DataVerification::getFieldLabel($field),
            'method' => $method,
            'verified' => $verified,
            'timestamp' => now()->toIso8601String(),
            'user_id' => $request->user()->id,
        ];
        $application->update(['status_history' => $history]);

        return response()->json([
            'message' => $verified
                ? DataVerification::getFieldLabel($field) . ' verificado'
                : 'Verificación de ' . DataVerification::getFieldLabel($field) . ' removida',
            'data' => [
                'field' => $field,
                'verified' => $verified,
                'method' => $method,
                'verified_at' => $verified ? now()->toIso8601String() : null,
            ]
        ]);
    }
}
