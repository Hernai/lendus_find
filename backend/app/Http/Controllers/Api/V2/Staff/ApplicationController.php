<?php

namespace App\Http\Controllers\Api\V2\Staff;

use App\Http\Controllers\Controller;
use App\Models\ApplicationV2;
use App\Models\StaffAccount;
use App\Services\ApplicationV2Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Staff Application Controller (v2).
 *
 * Handles loan application management for staff members using the new
 * normalized ApplicationV2 model.
 */
class ApplicationController extends Controller
{
    public function __construct(
        private ApplicationV2Service $service
    ) {}

    /**
     * List applications with filters.
     *
     * GET /v2/staff/applications
     */
    public function index(Request $request): JsonResponse
    {
        // Normalize status to always be an array (supports both ?status=X and ?status[]=X)
        $statusInput = $request->input('status');
        if (is_string($statusInput)) {
            $request->merge(['status' => [$statusInput]]);
        }

        $validStatuses = implode(',', array_keys(ApplicationV2::statuses()));
        $validated = $request->validate([
            'status' => 'nullable|array',
            'status.*' => "string|in:{$validStatuses}",
            'applicant_type' => 'nullable|string|in:individual,company',
            'assigned_to' => 'nullable|uuid',
            'unassigned' => 'nullable|boolean',
            'assignment' => 'nullable|string|in:all,assigned,unassigned',
            'risk_level' => 'nullable|string',
            'product_id' => 'nullable|uuid',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'search' => 'nullable|string|max:100',
            'sort_by' => 'nullable|string|in:created_at,submitted_at,requested_amount,status',
            'sort_dir' => 'nullable|string|in:asc,desc',
            'per_page' => 'nullable|integer|min:5|max:200',
        ]);

        /** @var StaffAccount $staff */
        $staff = $request->user();

        // ANALYST can only see applications assigned to them
        // SUPERVISOR and above can see all applications
        if (!$staff->canViewAllApplications()) {
            $validated['assigned_to'] = $staff->id;
        }

        $applications = $this->service->list(
            $staff->tenant,
            $validated,
            $validated['per_page'] ?? 20
        );

        return response()->json([
            'data' => $applications->map(fn($app) => $this->formatApplication($app)),
            'meta' => [
                'current_page' => $applications->currentPage(),
                'from' => $applications->firstItem(),
                'last_page' => $applications->lastPage(),
                'per_page' => $applications->perPage(),
                'to' => $applications->lastItem(),
                'total' => $applications->total(),
            ],
        ]);
    }

    /**
     * Get Kanban board data with applications grouped by status.
     *
     * GET /v2/staff/applications/board
     */
    public function board(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'columns' => 'nullable|array',
            'columns.*' => 'string|in:' . implode(',', array_keys(ApplicationV2::statuses())),
            'limit_per_column' => 'nullable|integer|min:5|max:50',
            'assigned_to' => 'nullable|uuid',
            'sort_by' => 'nullable|string|in:created_at,submitted_at,requested_amount',
            'sort_dir' => 'nullable|string|in:asc,desc',
        ]);

        /** @var StaffAccount $staff */
        $staff = $request->user();

        // Default columns for Kanban (active workflow statuses)
        $columns = $validated['columns'] ?? [
            ApplicationV2::STATUS_SUBMITTED,
            ApplicationV2::STATUS_IN_REVIEW,
            ApplicationV2::STATUS_DOCS_PENDING,
            ApplicationV2::STATUS_APPROVED,
        ];

        $limitPerColumn = $validated['limit_per_column'] ?? 15;

        // ANALYST can only see applications assigned to them
        $assignedTo = null;
        if (!$staff->canViewAllApplications()) {
            $assignedTo = $staff->id;
        } elseif (!empty($validated['assigned_to'])) {
            $assignedTo = $validated['assigned_to'];
        }

        $boardData = $this->service->getBoardData(
            $staff->tenant,
            $columns,
            $limitPerColumn,
            $assignedTo,
            $validated['sort_by'] ?? 'created_at',
            $validated['sort_dir'] ?? 'desc'
        );

        return response()->json(['data' => $boardData]);
    }

    /**
     * Get dashboard statistics.
     *
     * GET /v2/staff/applications/statistics
     */
    public function statistics(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
        ]);

        /** @var StaffAccount $staff */
        $staff = $request->user();

        $stats = $this->service->getStatistics(
            $staff->tenant,
            $validated['date_from'] ?? null,
            $validated['date_to'] ?? null
        );

        return response()->json(['data' => $stats]);
    }

    /**
     * Get unassigned applications.
     *
     * GET /v2/staff/applications/unassigned
     */
    public function unassigned(Request $request): JsonResponse
    {
        /** @var StaffAccount $staff */
        $staff = $request->user();

        $applications = $this->service->getUnassigned($staff->tenant);

        return response()->json([
            'applications' => $applications->map(fn($app) => $this->formatApplication($app)),
        ]);
    }

    /**
     * Get applications assigned to current staff.
     *
     * GET /v2/staff/applications/my-queue
     */
    public function myQueue(Request $request): JsonResponse
    {
        $status = $request->query('status');

        /** @var StaffAccount $staff */
        $staff = $request->user();

        $applications = $this->service->getAssignedTo($staff, $status);

        return response()->json([
            'applications' => $applications->map(fn($app) => $this->formatApplication($app)),
        ]);
    }

    /**
     * Show application details.
     *
     * GET /v2/staff/applications/{id}
     */
    public function show(Request $request, string $id): JsonResponse
    {
        /** @var StaffAccount $staff */
        $staff = $request->user();

        $application = ApplicationV2::where('id', $id)
            ->where('tenant_id', $staff->tenant_id)
            ->with([
                'product',
                'person.identifications',
                'person.addresses' => fn($q) => $q->where('is_current', true),
                'person.employments' => fn($q) => $q->where('is_current', true),
                'person.references',
                'person.bankAccounts',
                'company',
                'assignedTo',
                'statusHistory',
                'documents',
            ])
            ->first();

        if (!$application) {
            return response()->json([
                'error' => 'NOT_FOUND',
                'message' => 'Solicitud no encontrada.',
            ], 404);
        }

        return response()->json([
            'data' => $this->formatApplicationDetail($application),
        ]);
    }

    /**
     * Assign application to staff member.
     *
     * POST /v2/staff/applications/{id}/assign
     */
    public function assign(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|uuid|exists:staff_accounts,id',
        ]);

        /** @var StaffAccount $staff */
        $staff = $request->user();

        $application = ApplicationV2::where('id', $id)
            ->where('tenant_id', $staff->tenant_id)
            ->first();

        if (!$application) {
            return response()->json([
                'error' => 'NOT_FOUND',
                'message' => 'Solicitud no encontrada.',
            ], 404);
        }

        $assignee = StaffAccount::where('id', $validated['user_id'])
            ->where('tenant_id', $staff->tenant_id)
            ->first();

        if (!$assignee) {
            return response()->json([
                'error' => 'STAFF_NOT_FOUND',
                'message' => 'El analista seleccionado no existe.',
            ], 404);
        }

        $application = $this->service->assign($application, $assignee, $staff);

        return response()->json([
            'message' => 'Solicitud asignada exitosamente.',
            'application' => $this->formatApplication($application),
        ]);
    }

    /**
     * Change application status.
     *
     * POST /v2/staff/applications/{id}/status
     */
    public function changeStatus(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|string|in:' . implode(',', array_keys(ApplicationV2::statuses())),
            'notes' => 'nullable|string|max:1000',
        ]);

        /** @var StaffAccount $staff */
        $staff = $request->user();

        $application = ApplicationV2::where('id', $id)
            ->where('tenant_id', $staff->tenant_id)
            ->first();

        if (!$application) {
            return response()->json([
                'error' => 'NOT_FOUND',
                'message' => 'Solicitud no encontrada.',
            ], 404);
        }

        try {
            $application = $this->service->changeStatus(
                $application,
                $validated['status'],
                $staff,
                $validated['notes'] ?? null
            );

            return response()->json([
                'message' => 'Estado actualizado exitosamente.',
                'application' => $this->formatApplication($application),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'error' => 'STATUS_CHANGE_FAILED',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Approve application.
     *
     * POST /v2/staff/applications/{id}/approve
     */
    public function approve(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'amount' => 'nullable|numeric|min:1000',
            'term_months' => 'nullable|integer|min:1|max:120',
            'interest_rate' => 'nullable|numeric|min:0|max:100',
            'notes' => 'nullable|string|max:1000',
        ]);

        /** @var StaffAccount $staff */
        $staff = $request->user();

        $application = ApplicationV2::where('id', $id)
            ->where('tenant_id', $staff->tenant_id)
            ->first();

        if (!$application) {
            return response()->json([
                'error' => 'NOT_FOUND',
                'message' => 'Solicitud no encontrada.',
            ], 404);
        }

        if (!$application->canBeApproved()) {
            return response()->json([
                'error' => 'NOT_APPROVABLE',
                'message' => 'Esta solicitud no puede ser aprobada en su estado actual.',
            ], 400);
        }

        $application = $this->service->approve(
            $application,
            $staff,
            $validated['amount'] ?? null,
            $validated['term_months'] ?? null,
            $validated['interest_rate'] ?? null,
            $validated['notes'] ?? null
        );

        return response()->json([
            'message' => 'Solicitud aprobada exitosamente.',
            'application' => $this->formatApplication($application),
        ]);
    }

    /**
     * Reject application.
     *
     * POST /v2/staff/applications/{id}/reject
     */
    public function reject(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:500',
            'notes' => 'nullable|string|max:1000',
        ]);

        /** @var StaffAccount $staff */
        $staff = $request->user();

        $application = ApplicationV2::where('id', $id)
            ->where('tenant_id', $staff->tenant_id)
            ->first();

        if (!$application) {
            return response()->json([
                'error' => 'NOT_FOUND',
                'message' => 'Solicitud no encontrada.',
            ], 404);
        }

        if (!$application->canBeRejected()) {
            return response()->json([
                'error' => 'NOT_REJECTABLE',
                'message' => 'Esta solicitud no puede ser rechazada en su estado actual.',
            ], 400);
        }

        $application = $this->service->reject(
            $application,
            $staff,
            $validated['reason'],
            $validated['notes'] ?? null
        );

        return response()->json([
            'message' => 'Solicitud rechazada.',
            'application' => $this->formatApplication($application),
        ]);
    }

    /**
     * Send counter offer.
     *
     * POST /v2/staff/applications/{id}/counter-offer
     */
    public function sendCounterOffer(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:1000',
            'term_months' => 'required|integer|min:1|max:120',
            'interest_rate' => 'nullable|numeric|min:0|max:100',
            'reason' => 'nullable|string|max:500',
        ]);

        /** @var StaffAccount $staff */
        $staff = $request->user();

        $application = ApplicationV2::where('id', $id)
            ->where('tenant_id', $staff->tenant_id)
            ->first();

        if (!$application) {
            return response()->json([
                'error' => 'NOT_FOUND',
                'message' => 'Solicitud no encontrada.',
            ], 404);
        }

        $application = $this->service->sendCounterOffer(
            $application,
            $staff,
            [
                'amount' => $validated['amount'],
                'term_months' => $validated['term_months'],
                'interest_rate' => $validated['interest_rate'] ?? null,
            ],
            $validated['reason'] ?? null
        );

        return response()->json([
            'message' => 'Contraoferta enviada al solicitante.',
            'application' => $this->formatApplication($application),
        ]);
    }

    /**
     * Update verification checklist.
     *
     * PATCH /v2/staff/applications/{id}/verification
     */
    public function updateVerification(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'checks' => 'required|array',
            'checks.*' => 'boolean',
        ]);

        /** @var StaffAccount $staff */
        $staff = $request->user();

        $application = ApplicationV2::where('id', $id)
            ->where('tenant_id', $staff->tenant_id)
            ->first();

        if (!$application) {
            return response()->json([
                'error' => 'NOT_FOUND',
                'message' => 'Solicitud no encontrada.',
            ], 404);
        }

        $application = $this->service->updateVerification($application, $validated['checks']);

        return response()->json([
            'message' => 'Checklist actualizado.',
            'verification_checklist' => $application->verification_checklist,
        ]);
    }

    /**
     * Set risk assessment.
     *
     * POST /v2/staff/applications/{id}/risk-assessment
     */
    public function setRiskAssessment(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'level' => 'required|string|in:' . implode(',', [
                ApplicationV2::RISK_LOW,
                ApplicationV2::RISK_MEDIUM,
                ApplicationV2::RISK_HIGH,
                ApplicationV2::RISK_VERY_HIGH,
            ]),
            'data' => 'nullable|array',
        ]);

        /** @var StaffAccount $staff */
        $staff = $request->user();

        $application = ApplicationV2::where('id', $id)
            ->where('tenant_id', $staff->tenant_id)
            ->first();

        if (!$application) {
            return response()->json([
                'error' => 'NOT_FOUND',
                'message' => 'Solicitud no encontrada.',
            ], 404);
        }

        $application = $this->service->setRiskAssessment(
            $application,
            $validated['level'],
            $validated['data'] ?? null
        );

        return response()->json([
            'message' => 'Evaluación de riesgo actualizada.',
            'risk_level' => $application->risk_level,
            'risk_data' => $application->risk_data,
        ]);
    }

    /**
     * Get status history.
     *
     * GET /v2/staff/applications/{id}/history
     */
    public function history(Request $request, string $id): JsonResponse
    {
        /** @var StaffAccount $staff */
        $staff = $request->user();

        $application = ApplicationV2::where('id', $id)
            ->where('tenant_id', $staff->tenant_id)
            ->first();

        if (!$application) {
            return response()->json([
                'error' => 'NOT_FOUND',
                'message' => 'Solicitud no encontrada.',
            ], 404);
        }

        $history = $this->service->getStatusHistory($application);

        return response()->json([
            'history' => $history->map(fn($h) => [
                'from_status' => $h->from_status,
                'from_status_label' => $h->from_status_label,
                'to_status' => $h->to_status,
                'to_status_label' => $h->to_status_label,
                'changed_by' => $h->changed_by_name,
                'notes' => $h->notes,
                'created_at' => $h->created_at->toIso8601String(),
            ]),
        ]);
    }

    /**
     * Format application for list view.
     */
    private function formatApplication(ApplicationV2 $app): array
    {
        return [
            'id' => $app->id,
            'status' => $app->status,
            'status_label' => $app->status_label,
            'applicant_type' => $app->applicant_type,
            'applicant_name' => $app->is_individual
                ? $app->person?->full_name
                : $app->company?->legal_name,
            'applicant_rfc' => $app->is_individual
                ? $app->person?->rfc
                : $app->company?->rfc,
            'product' => [
                'id' => $app->product_id,
                'name' => $app->product?->name,
                'type' => $app->product?->type,
            ],
            'requested_amount' => $app->requested_amount,
            'requested_term_months' => $app->requested_term_months,
            'monthly_payment' => $app->monthly_payment,
            'risk_level' => $app->risk_level,
            'assigned_to' => $app->assignedTo ? [
                'id' => $app->assignedTo->id,
                'name' => $app->assignedTo->profile?->full_name ?? $app->assignedTo->email,
            ] : null,
            'created_at' => $app->created_at?->toIso8601String(),
            'submitted_at' => $app->submitted_at?->toIso8601String(),
        ];
    }

    /**
     * Format application for detail view.
     */
    private function formatApplicationDetail(ApplicationV2 $app): array
    {
        $data = $this->formatApplication($app);

        $data['interest_rate'] = $app->interest_rate;
        $data['total_interest'] = $app->total_interest;
        $data['total_amount'] = $app->total_amount;
        $data['cat'] = $app->cat;
        $data['purpose'] = $app->purpose;
        $data['purpose_description'] = $app->purpose_description;

        $data['approved_amount'] = $app->approved_amount;
        $data['approved_term_months'] = $app->approved_term_months;
        $data['approved_interest_rate'] = $app->approved_interest_rate;
        $data['rejection_reason'] = $app->rejection_reason;
        $data['decision_notes'] = $app->decision_notes;
        $data['decision_at'] = $app->decision_at?->toIso8601String();
        $data['decision_by'] = $app->decision_by;

        $data['has_counter_offer'] = $app->has_counter_offer;
        $data['counter_offer'] = $app->counter_offer;
        $data['counter_offer_accepted'] = $app->counter_offer_accepted;

        $data['verification_checklist'] = $app->verification_checklist;

        // Map verification_checklist to field_verifications for frontend compatibility
        $checklist = $app->verification_checklist ?? [];
        $fieldVerifications = [];
        foreach ($checklist as $field => $info) {
            $fieldVerifications[$field] = [
                'status' => strtoupper($info['status'] ?? 'pending'),
                'verified' => ($info['status'] ?? '') === 'verified',
                'method' => $info['method'] ?? null,
                'rejection_reason' => $info['rejection_reason'] ?? null,
                'notes' => $info['notes'] ?? null,
                'verified_at' => $info['verified_at'] ?? null,
                'verified_by' => $info['verified_by'] ?? null,
            ];
        }
        $data['field_verifications'] = $fieldVerifications;

        $data['risk_data'] = $app->risk_data;
        $data['snapshot_data'] = $app->snapshot_data;

        $data['external_id'] = $app->external_id;
        $data['external_system'] = $app->external_system;
        $data['synced_at'] = $app->synced_at?->toIso8601String();

        // Full applicant data with all relations (for detail view)
        $data['applicant'] = $app->person ? $this->formatPerson($app->person) : null;

        // Keep simplified person for backward compatibility
        $data['person'] = $app->person ? [
            'id' => $app->person->id,
            'full_name' => $app->person->full_name,
            'curp' => $app->person->curp,
            'rfc' => $app->person->rfc,
            'email' => $app->person->email,
            'phone' => $app->person->phone,
        ] : null;

        $data['company'] = $app->company ? [
            'id' => $app->company->id,
            'legal_name' => $app->company->legal_name,
            'trade_name' => $app->company->trade_name,
            'rfc' => $app->company->rfc,
        ] : null;

        $data['status_history'] = $app->statusHistory->map(fn($h) => [
            'from_status' => $h->from_status,
            'to_status' => $h->to_status,
            'changed_by' => $h->changed_by_name,
            'notes' => $h->notes,
            'created_at' => $h->created_at->toIso8601String(),
        ]);

        // Documents attached to the application
        $data['documents'] = $app->documents->map(fn($d) => [
            'id' => $d->id,
            'type' => $d->type,
            'category' => $d->category,
            'file_name' => $d->file_name,
            'mime_type' => $d->mime_type,
            'file_size' => $d->file_size,
            'status' => $d->status,
            'rejection_reason' => $d->rejection_reason,
            'reviewed_at' => $d->reviewed_at?->toIso8601String(),
            'ocr_data' => $d->ocr_data,
            'created_at' => $d->created_at?->toIso8601String(),
        ]);

        // Application notes (stored as JSON array)
        $data['notes'] = collect($app->notes ?? [])->map(fn($n) => [
            'id' => $n['id'] ?? uniqid(),
            'content' => $n['content'] ?? $n['text'] ?? '',
            'author' => $n['author'] ?? ['name' => 'Sistema'],
            'created_at' => $n['created_at'] ?? now()->toIso8601String(),
        ])->values();

        return $data;
    }

    /**
     * Format person with all relations for detail view.
     */
    private function formatPerson($person): array
    {
        $currentAddress = $person->addresses->first();
        $currentEmployment = $person->employments->first();

        return [
            'id' => $person->id,
            'full_name' => $person->full_name,
            'first_name' => $person->first_name,
            'last_name_1' => $person->last_name_1,
            'last_name_2' => $person->last_name_2,
            'email' => $person->email,
            'phone' => $person->phone,
            'curp' => $person->curp,
            'rfc' => $person->rfc,
            'birth_date' => $person->birth_date?->format('Y-m-d'),
            'nationality' => $person->nationality,
            'gender' => $person->gender,
            'marital_status' => $person->marital_status,
            'education_level' => $person->education_level,
            'dependents_count' => $person->dependents_count,
            'kyc_status' => $person->kyc_status,
            'kyc_verified_at' => $person->kyc_verified_at?->toIso8601String(),
            'current_home_address' => $currentAddress ? [
                'id' => $currentAddress->id,
                'street' => $currentAddress->street,
                'exterior_number' => $currentAddress->exterior_number,
                'interior_number' => $currentAddress->interior_number,
                'neighborhood' => $currentAddress->neighborhood,
                'municipality' => $currentAddress->municipality,
                'state' => $currentAddress->state,
                'postal_code' => $currentAddress->postal_code,
                'housing_type' => $currentAddress->housing_type,
                'years_at_address' => $currentAddress->years_at_address,
                'months_at_address' => $currentAddress->months_at_address,
                'verification_status' => $currentAddress->status,
            ] : null,
            'current_employment' => $currentEmployment ? [
                'id' => $currentEmployment->id,
                'employment_type' => $currentEmployment->employment_type,
                'company_name' => $currentEmployment->employer_name,
                'employer_name' => $currentEmployment->employer_name,
                'employer_rfc' => $currentEmployment->employer_rfc,
                'job_title' => $currentEmployment->job_title,
                'position' => $currentEmployment->job_title,
                'department' => $currentEmployment->department,
                'monthly_income' => $currentEmployment->monthly_income,
                'additional_income' => $currentEmployment->additional_income,
                'start_date' => $currentEmployment->start_date?->format('Y-m-d'),
                'years_employed' => $currentEmployment->years_employed,
                'months_employed' => $currentEmployment->months_employed,
                'verification_status' => $currentEmployment->status,
            ] : null,
            'references' => $person->references->map(fn($r) => [
                'id' => $r->id,
                'full_name' => $r->full_name,
                'first_name' => $r->first_name,
                'last_name_1' => $r->last_name_1,
                'last_name_2' => $r->last_name_2,
                'phone' => $r->phone,
                'email' => $r->email,
                'relationship' => $r->relationship,
                'type' => $r->type,
                'years_known' => $r->years_known,
                'verification_status' => $r->status,
                'verified_at' => $r->verified_at?->toIso8601String(),
                'notes' => $r->verification_notes,
            ]),
            'bank_accounts' => $person->bankAccounts->map(fn($ba) => [
                'id' => $ba->id,
                'bank_name' => $ba->bank_name,
                'bank_code' => $ba->bank_code,
                'clabe' => $ba->clabe,
                'account_type' => $ba->account_type,
                'account_holder_name' => $ba->holder_name,
                'is_primary' => $ba->is_primary,
                'is_verified' => $ba->is_verified,
                'created_at' => $ba->created_at?->toIso8601String(),
            ]),
        ];
    }

    // =========================================================================
    // Notes Operations
    // =========================================================================

    /**
     * Get application notes.
     *
     * GET /v2/staff/applications/{id}/notes
     */
    public function getNotes(Request $request, string $id): JsonResponse
    {
        /** @var StaffAccount $staff */
        $staff = $request->user();

        $application = ApplicationV2::where('id', $id)
            ->where('tenant_id', $staff->tenant_id)
            ->first();

        if (!$application) {
            return response()->json([
                'error' => 'NOT_FOUND',
                'message' => 'Solicitud no encontrada.',
            ], 404);
        }

        $notes = $application->notes ?? [];

        return response()->json([
            'data' => collect($notes)->map(fn($n) => [
                'id' => $n['id'] ?? uniqid(),
                'content' => $n['content'] ?? $n['text'] ?? '',
                'author' => $n['author'] ?? 'Sistema',
                'created_at' => $n['created_at'] ?? now()->toIso8601String(),
            ])->values(),
        ]);
    }

    /**
     * Add note to application.
     *
     * POST /v2/staff/applications/{id}/notes
     */
    public function addNote(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'content' => 'required|string|max:2000',
        ]);

        /** @var StaffAccount $staff */
        $staff = $request->user();

        $application = ApplicationV2::where('id', $id)
            ->where('tenant_id', $staff->tenant_id)
            ->first();

        if (!$application) {
            return response()->json([
                'error' => 'NOT_FOUND',
                'message' => 'Solicitud no encontrada.',
            ], 404);
        }

        $notes = $application->notes ?? [];
        $newNote = [
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'content' => $validated['content'],
            'author' => [
                'id' => $staff->id,
                'name' => $staff->name,
            ],
            'created_at' => now()->toIso8601String(),
        ];

        $notes[] = $newNote;
        $application->notes = $notes;
        $application->save();

        return response()->json([
            'data' => $newNote,
            'message' => 'Nota agregada exitosamente.',
        ], 201);
    }

    // =========================================================================
    // Document Operations (nested under application)
    // =========================================================================

    /**
     * Get document download URL.
     *
     * GET /v2/staff/applications/{appId}/documents/{docId}/url
     */
    public function getDocumentUrl(Request $request, string $appId, string $docId): JsonResponse
    {
        /** @var StaffAccount $staff */
        $staff = $request->user();

        $application = ApplicationV2::where('id', $appId)
            ->where('tenant_id', $staff->tenant_id)
            ->first();

        if (!$application) {
            return response()->json(['error' => 'NOT_FOUND', 'message' => 'Solicitud no encontrada.'], 404);
        }

        $document = $application->documents()->where('id', $docId)->first();

        if (!$document) {
            return response()->json(['error' => 'NOT_FOUND', 'message' => 'Documento no encontrado.'], 404);
        }

        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = \Illuminate\Support\Facades\Storage::disk('s3');
        $url = $disk->temporaryUrl(
            $document->file_path,
            now()->addMinutes(15)
        );

        return response()->json([
            'data' => [
                'url' => $url,
                'mime_type' => $document->mime_type,
                'original_name' => $document->file_name,
            ],
        ]);
    }

    /**
     * Download document.
     *
     * GET /v2/staff/applications/{appId}/documents/{docId}/download
     */
    public function downloadDocument(Request $request, string $appId, string $docId)
    {
        /** @var StaffAccount $staff */
        $staff = $request->user();

        $application = ApplicationV2::where('id', $appId)
            ->where('tenant_id', $staff->tenant_id)
            ->first();

        if (!$application) {
            return response()->json(['error' => 'NOT_FOUND', 'message' => 'Solicitud no encontrada.'], 404);
        }

        $document = $application->documents()->where('id', $docId)->first();

        if (!$document) {
            return response()->json(['error' => 'NOT_FOUND', 'message' => 'Documento no encontrado.'], 404);
        }

        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = \Illuminate\Support\Facades\Storage::disk('s3');

        return $disk->download(
            $document->file_path,
            $document->file_name
        );
    }

    /**
     * Approve document.
     *
     * PUT /v2/staff/applications/{appId}/documents/{docId}/approve
     */
    public function approveDocument(Request $request, string $appId, string $docId): JsonResponse
    {
        /** @var StaffAccount $staff */
        $staff = $request->user();

        $application = ApplicationV2::where('id', $appId)
            ->where('tenant_id', $staff->tenant_id)
            ->first();

        if (!$application) {
            return response()->json(['error' => 'NOT_FOUND', 'message' => 'Solicitud no encontrada.'], 404);
        }

        $document = $application->documents()->where('id', $docId)->first();

        if (!$document) {
            return response()->json(['error' => 'NOT_FOUND', 'message' => 'Documento no encontrado.'], 404);
        }

        $document->status = 'APPROVED';
        $document->reviewed_at = now();
        $document->reviewed_by = $staff->id;
        $document->save();

        return response()->json([
            'data' => null,
            'message' => 'Documento aprobado.',
        ]);
    }

    /**
     * Reject document.
     *
     * PUT /v2/staff/applications/{appId}/documents/{docId}/reject
     */
    public function rejectDocument(Request $request, string $appId, string $docId): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:500',
            'comment' => 'nullable|string|max:1000',
        ]);

        /** @var StaffAccount $staff */
        $staff = $request->user();

        $application = ApplicationV2::where('id', $appId)
            ->where('tenant_id', $staff->tenant_id)
            ->first();

        if (!$application) {
            return response()->json(['error' => 'NOT_FOUND', 'message' => 'Solicitud no encontrada.'], 404);
        }

        $document = $application->documents()->where('id', $docId)->first();

        if (!$document) {
            return response()->json(['error' => 'NOT_FOUND', 'message' => 'Documento no encontrado.'], 404);
        }

        $document->status = 'REJECTED';
        $document->rejection_reason = $validated['reason'];
        $document->reviewed_at = now();
        $document->reviewed_by = $staff->id;
        $document->save();

        return response()->json([
            'data' => null,
            'message' => 'Documento rechazado.',
        ]);
    }

    /**
     * Unapprove document (set back to pending).
     *
     * PUT /v2/staff/applications/{appId}/documents/{docId}/unapprove
     */
    public function unapproveDocument(Request $request, string $appId, string $docId): JsonResponse
    {
        /** @var StaffAccount $staff */
        $staff = $request->user();

        $application = ApplicationV2::where('id', $appId)
            ->where('tenant_id', $staff->tenant_id)
            ->first();

        if (!$application) {
            return response()->json(['error' => 'NOT_FOUND', 'message' => 'Solicitud no encontrada.'], 404);
        }

        $document = $application->documents()->where('id', $docId)->first();

        if (!$document) {
            return response()->json(['error' => 'NOT_FOUND', 'message' => 'Documento no encontrado.'], 404);
        }

        $document->status = 'PENDING';
        $document->rejection_reason = null;
        $document->reviewed_at = null;
        $document->reviewed_by = null;
        $document->save();

        return response()->json([
            'data' => null,
            'message' => 'Documento regresado a pendiente.',
        ]);
    }

    // =========================================================================
    // Reference Operations
    // =========================================================================

    /**
     * Verify reference.
     *
     * PUT /v2/staff/applications/{appId}/references/{refId}/verify
     */
    public function verifyReference(Request $request, string $appId, string $refId): JsonResponse
    {
        $validated = $request->validate([
            'result' => 'required|string|in:VERIFIED,NOT_VERIFIED,NO_ANSWER',
            'notes' => 'nullable|string|max:1000',
        ]);

        /** @var StaffAccount $staff */
        $staff = $request->user();

        $application = ApplicationV2::where('id', $appId)
            ->where('tenant_id', $staff->tenant_id)
            ->with('person.references')
            ->first();

        if (!$application || !$application->person) {
            return response()->json(['error' => 'NOT_FOUND', 'message' => 'Solicitud no encontrada.'], 404);
        }

        $reference = $application->person->references->firstWhere('id', $refId);

        if (!$reference) {
            return response()->json(['error' => 'NOT_FOUND', 'message' => 'Referencia no encontrada.'], 404);
        }

        $statusMap = [
            'VERIFIED' => 'VERIFIED',
            'NOT_VERIFIED' => 'REJECTED',
            'NO_ANSWER' => 'UNREACHABLE',
        ];

        $reference->status = $statusMap[$validated['result']];
        $reference->verification_notes = $validated['notes'] ?? null;
        $reference->verified_at = now();
        $reference->verified_by = $staff->id;
        $reference->save();

        return response()->json([
            'data' => null,
            'message' => 'Referencia verificada.',
        ]);
    }

    // =========================================================================
    // Bank Account Operations
    // =========================================================================

    /**
     * Verify bank account.
     *
     * PUT /v2/staff/applications/{appId}/bank-accounts/{baId}/verify
     */
    public function verifyBankAccount(Request $request, string $appId, string $baId): JsonResponse
    {
        /** @var StaffAccount $staff */
        $staff = $request->user();

        $application = ApplicationV2::where('id', $appId)
            ->where('tenant_id', $staff->tenant_id)
            ->with('person.bankAccounts')
            ->first();

        if (!$application || !$application->person) {
            return response()->json(['error' => 'NOT_FOUND', 'message' => 'Solicitud no encontrada.'], 404);
        }

        $bankAccount = $application->person->bankAccounts->firstWhere('id', $baId);

        if (!$bankAccount) {
            return response()->json(['error' => 'NOT_FOUND', 'message' => 'Cuenta bancaria no encontrada.'], 404);
        }

        $bankAccount->is_verified = true;
        $bankAccount->verified_at = now();
        $bankAccount->verified_by = $staff->id;
        $bankAccount->save();

        return response()->json([
            'data' => null,
            'message' => 'Cuenta bancaria verificada.',
        ]);
    }

    /**
     * Unverify bank account.
     *
     * PUT /v2/staff/applications/{appId}/bank-accounts/{baId}/unverify
     */
    public function unverifyBankAccount(Request $request, string $appId, string $baId): JsonResponse
    {
        /** @var StaffAccount $staff */
        $staff = $request->user();

        $application = ApplicationV2::where('id', $appId)
            ->where('tenant_id', $staff->tenant_id)
            ->with('person.bankAccounts')
            ->first();

        if (!$application || !$application->person) {
            return response()->json(['error' => 'NOT_FOUND', 'message' => 'Solicitud no encontrada.'], 404);
        }

        $bankAccount = $application->person->bankAccounts->firstWhere('id', $baId);

        if (!$bankAccount) {
            return response()->json(['error' => 'NOT_FOUND', 'message' => 'Cuenta bancaria no encontrada.'], 404);
        }

        $bankAccount->is_verified = false;
        $bankAccount->verified_at = null;
        $bankAccount->verified_by = null;
        $bankAccount->save();

        return response()->json([
            'data' => null,
            'message' => 'Verificación de cuenta bancaria removida.',
        ]);
    }

    // =========================================================================
    // Data Verification Operations
    // =========================================================================

    /**
     * Verify application data field.
     *
     * PUT /v2/staff/applications/{id}/verify-data
     */
    public function verifyData(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'field' => 'required|string|max:100',
            'action' => 'required|string|in:verify,reject,unverify',
            'method' => 'nullable|string|max:100',
            'rejection_reason' => 'nullable|string|max:500',
            'notes' => 'nullable|string|max:1000',
        ]);

        /** @var StaffAccount $staff */
        $staff = $request->user();

        $application = ApplicationV2::where('id', $id)
            ->where('tenant_id', $staff->tenant_id)
            ->first();

        if (!$application) {
            return response()->json(['error' => 'NOT_FOUND', 'message' => 'Solicitud no encontrada.'], 404);
        }

        $checklist = $application->verification_checklist ?? [];
        $checklist[$validated['field']] = [
            'status' => match ($validated['action']) {
                'verify' => 'verified',
                'reject' => 'rejected',
                'unverify' => 'pending',
            },
            'method' => $validated['method'] ?? null,
            'rejection_reason' => $validated['rejection_reason'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'verified_by' => $staff->id,
            'verified_at' => now()->toIso8601String(),
        ];

        $application->verification_checklist = $checklist;
        $application->save();

        return response()->json([
            'data' => null,
            'message' => 'Dato verificado.',
        ]);
    }

    // =========================================================================
    // API Logs Operations
    // =========================================================================

    /**
     * Get API logs for application.
     *
     * GET /v2/staff/applications/{id}/api-logs
     */
    public function getApiLogs(Request $request, string $id): JsonResponse
    {
        /** @var StaffAccount $staff */
        $staff = $request->user();

        $application = ApplicationV2::where('id', $id)
            ->where('tenant_id', $staff->tenant_id)
            ->first();

        if (!$application) {
            return response()->json(['error' => 'NOT_FOUND', 'message' => 'Solicitud no encontrada.'], 404);
        }

        // Get API logs related to this application or its person (applicant)
        $logs = \App\Models\ApiLog::where('tenant_id', $staff->tenant_id)
            ->where(function ($q) use ($application) {
                $q->where('application_id', $application->id);

                if ($application->person_id) {
                    $q->orWhere('applicant_id', $application->person_id);
                }
            })
            ->orderBy('created_at', 'desc')
            ->limit(100)
            ->get();

        return response()->json([
            'data' => $logs->map(fn($log) => [
                'id' => $log->id,
                'provider' => $log->provider,
                'service' => $log->service,
                'endpoint' => $log->endpoint,
                'method' => $log->method,
                'request_method' => $log->method,
                'request_url' => $log->endpoint,
                'response_status' => $log->response_status,
                'success' => $log->success,
                'error_message' => $log->error_message,
                'duration_ms' => $log->duration_ms,
                'request_payload' => $log->request_payload,
                'response_payload' => $log->response_body,
                'response_body' => $log->response_body,
                'created_at' => $log->created_at?->toIso8601String(),
            ]),
        ]);
    }
}
