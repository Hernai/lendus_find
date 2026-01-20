<?php

namespace App\Http\Controllers\Api\V2\Staff;

use App\Http\Controllers\Api\V2\Traits\ApiResponses;
use App\Http\Controllers\Controller;
use App\Models\ApplicationStatusHistory;
use App\Models\ApplicationV2;
use App\Models\DataVerification;
use App\Models\Person;
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
    use ApiResponses;
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

        return $this->success([
            'applications' => $applications->map(fn($app) => $this->formatApplication($app)),
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

        return $this->success($boardData);
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

        return $this->success($stats);
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

        return $this->success([
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

        return $this->success([
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
                'person.documents' => fn($q) => $q->whereNull('replaced_at')->orderByDesc('created_at'),
                'person.account',
                'company',
                'assignedTo',
                'statusHistory',
                'documents',
            ])
            ->first();

        if (!$application) {
            return $this->notFound('Solicitud no encontrada.');
        }

        return $this->success($this->formatApplicationDetail($application));
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
            return $this->notFound('Solicitud no encontrada.');
        }

        $assignee = StaffAccount::where('id', $validated['user_id'])
            ->where('tenant_id', $staff->tenant_id)
            ->first();

        if (!$assignee) {
            return $this->notFound('El analista seleccionado no existe.');
        }

        $application = $this->service->assign($application, $assignee, $staff);

        return $this->success([
            'application' => $this->formatApplication($application),
        ], 'Solicitud asignada exitosamente.');
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
            return $this->notFound('Solicitud no encontrada.');
        }

        try {
            $application = $this->service->changeStatus(
                $application,
                $validated['status'],
                $staff,
                $validated['notes'] ?? null
            );

            return $this->success([
                'application' => $this->formatApplication($application),
            ], 'Estado actualizado exitosamente.');
        } catch (\InvalidArgumentException $e) {
            return $this->badRequest('STATUS_CHANGE_FAILED', $e->getMessage());
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
            return $this->notFound('Solicitud no encontrada.');
        }

        if (!$application->canBeApproved()) {
            return $this->badRequest('NOT_APPROVABLE', 'Esta solicitud no puede ser aprobada en su estado actual.');
        }

        $application = $this->service->approve(
            $application,
            $staff,
            $validated['amount'] ?? null,
            $validated['term_months'] ?? null,
            $validated['interest_rate'] ?? null,
            $validated['notes'] ?? null
        );

        return $this->success([
            'application' => $this->formatApplication($application),
        ], 'Solicitud aprobada exitosamente.');
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
            return $this->notFound('Solicitud no encontrada.');
        }

        if (!$application->canBeRejected()) {
            return $this->badRequest('NOT_REJECTABLE', 'Esta solicitud no puede ser rechazada en su estado actual.');
        }

        $application = $this->service->reject(
            $application,
            $staff,
            $validated['reason'],
            $validated['notes'] ?? null
        );

        return $this->success([
            'application' => $this->formatApplication($application),
        ], 'Solicitud rechazada.');
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
            return $this->notFound('Solicitud no encontrada.');
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

        return $this->success([
            'application' => $this->formatApplication($application),
        ], 'Contraoferta enviada al solicitante.');
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
            return $this->notFound('Solicitud no encontrada.');
        }

        $application = $this->service->updateVerification($application, $validated['checks']);

        return $this->success([
            'verification_checklist' => $application->verification_checklist,
        ], 'Checklist actualizado.');
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
            return $this->notFound('Solicitud no encontrada.');
        }

        $application = $this->service->setRiskAssessment(
            $application,
            $validated['level'],
            $validated['data'] ?? null
        );

        return $this->success([
            'risk_level' => $application->risk_level,
            'risk_data' => $application->risk_data,
        ], 'Evaluación de riesgo actualizada.');
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
            return $this->notFound('Solicitud no encontrada.');
        }

        $history = $this->service->getStatusHistory($application);

        return $this->success([
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
        // Generate folio from created_at date + short UUID
        // Format: YYYYMMDD-XXXX (e.g., 20260119-ABC1)
        $folio = $app->created_at?->format('Ymd') . '-' . strtoupper(substr($app->id, 0, 4));

        return [
            'id' => $app->id,
            'folio' => $folio,
            'status' => $app->status,
            'status_label' => $app->status_label,
            'applicant_type' => $app->applicant_type,
            // Uses model's applicant_name accessor which handles person/company
            'applicant_name' => $app->applicant_name,
            'applicant_phone' => $app->is_individual
                ? $app->person?->account?->primary_phone
                : $app->company?->phone,
            'applicant_rfc' => $app->is_individual
                ? $app->person?->rfc
                : $app->company?->rfc,
            'product' => [
                'id' => $app->product_id,
                'name' => $app->product?->name,
                'type' => $app->product?->type,
                'required_documents' => $app->product?->required_documents ?? [],
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
            'updated_at' => $app->updated_at?->toIso8601String(),
            'submitted_at' => $app->submitted_at?->toIso8601String(),
        ];
    }

    /**
     * Format application for detail view.
     *
     * Response structure:
     * - id, folio, status: Basic identifiers
     * - loan: Financial details (product, amounts, terms, rates)
     * - applicant: Person/Company with address, employment, references, bank_accounts
     * - verification: KYC status and field verifications
     * - documents: All documents with status
     * - workflow: Status history, notes, assignment
     * - integration: External system sync info
     */
    private function formatApplicationDetail(ApplicationV2 $app): array
    {
        $person = $app->person;
        $company = $app->company;
        $folio = $app->created_at?->format('Ymd') . '-' . strtoupper(substr($app->id, 0, 4));

        // =========================================================
        // HEADER - Basic application info
        // =========================================================
        $data = [
            'id' => $app->id,
            'folio' => $folio,
            'status' => $app->status,
            'status_label' => $app->status_label,
            'applicant_type' => $app->applicant_type,
            'created_at' => $app->created_at?->toIso8601String(),
            'updated_at' => $app->updated_at?->toIso8601String(),
            'submitted_at' => $app->submitted_at?->toIso8601String(),
        ];

        // =========================================================
        // LOAN - Financial details (all stored in application)
        // =========================================================
        $data['loan'] = [
            // Product reference (only name/type for display)
            'product_id' => $app->product_id,
            'product_name' => $app->product?->name,
            'product_type' => $app->product?->type,

            // Requested by applicant
            'requested_amount' => $app->requested_amount,
            'requested_term_months' => $app->requested_term_months,
            'purpose' => $app->purpose,
            'purpose_description' => $app->purpose_description,

            // Calculated values (stored at application creation)
            'interest_rate' => $app->interest_rate,
            'monthly_payment' => $app->monthly_payment,
            'total_interest' => $app->total_interest,
            'total_amount' => $app->total_amount,
            'cat' => $app->cat,

            // Approved values (set when approved)
            'approved_amount' => $app->approved_amount,
            'approved_term_months' => $app->approved_term_months,
            'approved_interest_rate' => $app->approved_interest_rate,

            // Counter offer
            'has_counter_offer' => $app->has_counter_offer ?? false,
            'counter_offer' => $app->counter_offer,
            'counter_offer_accepted' => $app->counter_offer_accepted,

            // Risk assessment
            'risk_level' => $app->risk_level,
            'risk_data' => $app->risk_data,
        ];

        // Required documents from product (for document checklist)
        $data['required_documents'] = $app->product?->required_documents ?? [];

        // =========================================================
        // APPLICANT - Person or Company with all related data
        // Structure mirrors profile API response
        // =========================================================
        if ($person) {
            $data['applicant'] = [
                'type' => 'INDIVIDUAL',
                'person' => [
                    'id' => $person->id,
                    'personal_data' => [
                        'first_name' => $person->first_name,
                        'last_name_1' => $person->last_name_1,
                        'last_name_2' => $person->last_name_2,
                        'full_name' => $person->full_name,
                        'birth_date' => $person->birth_date?->format('Y-m-d'),
                        'birth_state' => $person->birth_state,
                        'gender' => $person->gender,
                        'nationality' => $person->nationality,
                        'marital_status' => $person->marital_status,
                        'education_level' => $person->education_level,
                        'dependents_count' => $person->dependents_count ?? 0,
                    ],
                    'identifications' => [
                        'curp' => $person->curp,
                        'rfc' => $person->rfc,
                    ],
                    'contact' => [
                        'email' => $person->account?->identities?->where('type', 'email')->first()?->identifier,
                        'phone' => $person->account?->primary_phone,
                    ],
                    'address' => $this->formatAddress($person->addresses?->where('is_current', true)->first()),
                    'employment' => $this->formatEmployment($person->employments?->where('is_current', true)->first()),
                    'references' => $person->references?->map(fn($r) => $this->formatReference($r))->values()->toArray() ?? [],
                    'bank_accounts' => $person->bankAccounts?->map(fn($ba) => $this->formatBankAccount($ba))->values()->toArray() ?? [],
                    'kyc_status' => $person->kyc_status,
                    'kyc_verified_at' => $person->kyc_verified_at?->toIso8601String(),
                    'profile_completeness' => $person->profile_completeness,
                ],
            ];
        } elseif ($company) {
            $data['applicant'] = [
                'type' => 'COMPANY',
                'company' => [
                    'id' => $company->id,
                    'legal_name' => $company->legal_name,
                    'trade_name' => $company->trade_name,
                    'rfc' => $company->rfc,
                    'contact' => [
                        'email' => $company->email,
                        'phone' => $company->phone,
                    ],
                ],
            ];
        } else {
            $data['applicant'] = null;
        }

        // =========================================================
        // VERIFICATION - KYC and field verifications
        // =========================================================
        $data['verification'] = [
            'kyc_status' => $person?->kyc_status ?? $company?->kyc_status ?? 'PENDING',
            'kyc_verified_at' => $person?->kyc_verified_at?->toIso8601String() ?? $company?->kyc_verified_at?->toIso8601String(),
            'fields' => $this->getFieldVerifications($app),
            'signature' => $this->getSignatureData($app),
            'checklist' => $app->verification_checklist ?? [],
        ];

        // =========================================================
        // DOCUMENTS - All documents with status
        // =========================================================
        $data['documents'] = $this->getDocuments($app);

        // =========================================================
        // WORKFLOW - Status history, notes, assignment
        // =========================================================
        $data['workflow'] = [
            'assigned_to' => $app->assignedTo ? [
                'id' => $app->assignedTo->id,
                'name' => $app->assignedTo->name,
                'email' => $app->assignedTo->email,
            ] : null,
            'status_history' => $app->statusHistory->map(fn($h) => [
                'from_status' => $h->from_status,
                'from_status_label' => $h->from_status_label,
                'to_status' => $h->to_status,
                'to_status_label' => $h->to_status_label,
                'changed_by' => $h->changed_by_name,
                'notes' => $h->notes,
                'created_at' => $h->created_at->toIso8601String(),
            ])->values()->toArray(),
            'notes' => collect($app->notes ?? [])->map(fn($n) => [
                'id' => $n['id'] ?? uniqid(),
                'content' => $n['content'] ?? $n['text'] ?? '',
                'author' => $n['author'] ?? ['name' => 'Sistema'],
                'created_at' => $n['created_at'] ?? now()->toIso8601String(),
            ])->values()->toArray(),
        ];

        // =========================================================
        // INTEGRATION - External system sync info
        // =========================================================
        $data['integration'] = [
            'external_id' => $app->external_id,
            'external_system' => $app->external_system,
            'synced_at' => $app->synced_at?->toIso8601String(),
            'snapshot_data' => $app->snapshot_data,
        ];

        return $data;
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
            return $this->notFound('Solicitud no encontrada.');
        }

        $notes = $application->notes ?? [];

        return $this->success([
            'notes' => collect($notes)->map(fn($n) => [
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
            return $this->notFound('Solicitud no encontrada.');
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

        // Record history
        $truncatedContent = strlen($validated['content']) > 50
            ? substr($validated['content'], 0, 50) . '...'
            : $validated['content'];
        ApplicationStatusHistory::create([
            'application_id' => $application->id,
            'from_status' => 'NOTE_ADDED',
            'to_status' => 'NOTE_ADDED',
            'changed_by' => $staff->id,
            'changed_by_type' => StaffAccount::class,
            'notes' => "Nota agregada: \"{$truncatedContent}\"",
            'metadata' => [
                'action' => 'note_added',
                'note_id' => $newNote['id'],
                'content_preview' => $truncatedContent,
            ],
        ]);

        return $this->created($newNote, 'Nota agregada exitosamente.');
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
            return $this->notFound('Solicitud no encontrada.');
        }

        $document = $application->documents()->where('id', $docId)->first();

        if (!$document) {
            return $this->notFound('Documento no encontrado.');
        }

        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = \Illuminate\Support\Facades\Storage::disk('s3');
        $url = $disk->temporaryUrl(
            $document->file_path,
            now()->addMinutes(15)
        );

        return $this->success([
            'url' => $url,
            'mime_type' => $document->mime_type,
            'original_name' => $document->file_name,
        ]);
    }

    /**
     * Get document history (all versions).
     *
     * GET /v2/staff/applications/{appId}/documents/{docId}/history
     */
    public function getDocumentHistory(Request $request, string $appId, string $docId): JsonResponse
    {
        /** @var StaffAccount $staff */
        $staff = $request->user();

        $application = ApplicationV2::where('id', $appId)
            ->where('tenant_id', $staff->tenant_id)
            ->with('person')
            ->first();

        if (!$application) {
            return $this->notFound('Solicitud no encontrada.');
        }

        $document = $this->findApplicationDocument($application, $docId);

        if (!$document) {
            return $this->notFound('Documento no encontrado.');
        }

        // Get all versions of this document type for the same entity
        $history = \App\Models\DocumentV2::where('documentable_type', $document->documentable_type)
            ->where('documentable_id', $document->documentable_id)
            ->where('type', $document->type)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($doc) {
                return [
                    'id' => $doc->id,
                    'file_name' => $doc->file_name,
                    'status' => $doc->status,
                    'rejection_reason' => $doc->rejection_reason,
                    'replaced_at' => $doc->replaced_at?->toIso8601String(),
                    'created_at' => $doc->created_at?->toIso8601String(),
                    'is_current' => $doc->replaced_at === null,
                ];
            });

        return $this->success([
            'document_type' => $document->type,
            'history' => $history,
            'total_versions' => $history->count(),
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
            ->with('person')
            ->first();

        if (!$application) {
            return $this->notFound('Solicitud no encontrada.');
        }

        $document = $this->findApplicationDocument($application, $docId);

        if (!$document) {
            return $this->notFound('Documento no encontrado.');
        }

        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = \Illuminate\Support\Facades\Storage::disk($document->storage_disk ?? 'local');

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
            ->with('person')
            ->first();

        if (!$application) {
            return $this->notFound('Solicitud no encontrada.');
        }

        $document = $this->findApplicationDocument($application, $docId);

        if (!$document) {
            return $this->notFound('Documento no encontrado.');
        }

        $oldStatus = $document->status;
        $document->status = 'APPROVED';
        $document->reviewed_at = now();
        $document->reviewed_by = $staff->id;
        $document->save();

        // Record history
        ApplicationStatusHistory::create([
            'application_id' => $application->id,
            'from_status' => 'DOCUMENT_REVIEW',
            'to_status' => 'DOCUMENT_REVIEW',
            'changed_by' => $staff->id,
            'changed_by_type' => StaffAccount::class,
            'notes' => "Documento '{$document->type}' aprobado",
            'metadata' => [
                'action' => 'document_approved',
                'document_id' => $document->id,
                'document_type' => $document->type,
                'old_status' => $oldStatus,
                'new_status' => 'APPROVED',
            ],
        ]);

        return $this->success(null, 'Documento aprobado.');
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
            ->with('person')
            ->first();

        if (!$application) {
            return $this->notFound('Solicitud no encontrada.');
        }

        $document = $this->findApplicationDocument($application, $docId);

        if (!$document) {
            return $this->notFound('Documento no encontrado.');
        }

        $oldStatus = $document->status;
        $document->status = 'REJECTED';
        $document->rejection_reason = $validated['reason'];
        $document->reviewed_at = now();
        $document->reviewed_by = $staff->id;
        $document->save();

        // Record history
        ApplicationStatusHistory::create([
            'application_id' => $application->id,
            'from_status' => 'DOCUMENT_REVIEW',
            'to_status' => 'DOCUMENT_REVIEW',
            'changed_by' => $staff->id,
            'changed_by_type' => StaffAccount::class,
            'notes' => "Documento '{$document->type}' rechazado: {$validated['reason']}",
            'metadata' => [
                'action' => 'document_rejected',
                'document_id' => $document->id,
                'document_type' => $document->type,
                'old_status' => $oldStatus,
                'new_status' => 'REJECTED',
                'reason' => $validated['reason'],
                'comment' => $validated['comment'] ?? null,
            ],
        ]);

        return $this->success(null, 'Documento rechazado.');
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
            ->with('person')
            ->first();

        if (!$application) {
            return $this->notFound('Solicitud no encontrada.');
        }

        $document = $this->findApplicationDocument($application, $docId);

        if (!$document) {
            return $this->notFound('Documento no encontrado.');
        }

        $oldStatus = $document->status;
        $document->status = 'PENDING';
        $document->rejection_reason = null;
        $document->reviewed_at = null;
        $document->reviewed_by = null;
        $document->save();

        // Record history
        ApplicationStatusHistory::create([
            'application_id' => $application->id,
            'from_status' => 'DOCUMENT_REVIEW',
            'to_status' => 'DOCUMENT_REVIEW',
            'changed_by' => $staff->id,
            'changed_by_type' => StaffAccount::class,
            'notes' => "Documento '{$document->type}' regresado a pendiente",
            'metadata' => [
                'action' => 'document_unapproved',
                'document_id' => $document->id,
                'document_type' => $document->type,
                'old_status' => $oldStatus,
                'new_status' => 'PENDING',
            ],
        ]);

        return $this->success(null, 'Documento regresado a pendiente.');
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
            return $this->notFound('Solicitud no encontrada.');
        }

        $reference = $application->person->references->firstWhere('id', $refId);

        if (!$reference) {
            return $this->notFound('Referencia no encontrada.');
        }

        $statusMap = [
            'VERIFIED' => 'VERIFIED',
            'NOT_VERIFIED' => 'REJECTED',
            'NO_ANSWER' => 'UNREACHABLE',
        ];

        $oldStatus = $reference->status ?? 'PENDING';
        $reference->status = $statusMap[$validated['result']];
        $reference->verification_notes = $validated['notes'] ?? null;
        $reference->verified_at = now();
        $reference->verified_by = $staff->id;
        $reference->save();

        // Record history
        $resultLabels = [
            'VERIFIED' => 'verificada',
            'NOT_VERIFIED' => 'no verificada',
            'NO_ANSWER' => 'sin respuesta',
        ];
        ApplicationStatusHistory::create([
            'application_id' => $application->id,
            'from_status' => 'REFERENCE_VERIFICATION',
            'to_status' => 'REFERENCE_VERIFICATION',
            'changed_by' => $staff->id,
            'changed_by_type' => StaffAccount::class,
            'notes' => "Referencia '{$reference->full_name}' {$resultLabels[$validated['result']]}" . ($validated['notes'] ? ": {$validated['notes']}" : ''),
            'metadata' => [
                'action' => 'reference_verified',
                'reference_id' => $reference->id,
                'reference_name' => $reference->full_name,
                'old_status' => $oldStatus,
                'new_status' => $statusMap[$validated['result']],
                'result' => $validated['result'],
                'notes' => $validated['notes'] ?? null,
            ],
        ]);

        return $this->success(null, 'Referencia verificada.');
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
            return $this->notFound('Solicitud no encontrada.');
        }

        $bankAccount = $application->person->bankAccounts->firstWhere('id', $baId);

        if (!$bankAccount) {
            return $this->notFound('Cuenta bancaria no encontrada.');
        }

        $wasVerified = $bankAccount->is_verified;
        $bankAccount->is_verified = true;
        $bankAccount->verified_at = now();
        $bankAccount->verified_by = $staff->id;
        $bankAccount->save();

        // Record history
        ApplicationStatusHistory::create([
            'application_id' => $application->id,
            'from_status' => 'BANK_ACCOUNT_VERIFICATION',
            'to_status' => 'BANK_ACCOUNT_VERIFICATION',
            'changed_by' => $staff->id,
            'changed_by_type' => StaffAccount::class,
            'notes' => "Cuenta bancaria '{$bankAccount->bank_name}' verificada (CLABE: ***{$this->maskClabe($bankAccount->clabe)})",
            'metadata' => [
                'action' => 'bank_account_verified',
                'bank_account_id' => $bankAccount->id,
                'bank_name' => $bankAccount->bank_name,
                'was_verified' => $wasVerified,
            ],
        ]);

        return $this->success(null, 'Cuenta bancaria verificada.');
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
            return $this->notFound('Solicitud no encontrada.');
        }

        $bankAccount = $application->person->bankAccounts->firstWhere('id', $baId);

        if (!$bankAccount) {
            return $this->notFound('Cuenta bancaria no encontrada.');
        }

        $wasVerified = $bankAccount->is_verified;
        $bankAccount->is_verified = false;
        $bankAccount->verified_at = null;
        $bankAccount->verified_by = null;
        $bankAccount->save();

        // Record history
        ApplicationStatusHistory::create([
            'application_id' => $application->id,
            'from_status' => 'BANK_ACCOUNT_VERIFICATION',
            'to_status' => 'BANK_ACCOUNT_VERIFICATION',
            'changed_by' => $staff->id,
            'changed_by_type' => StaffAccount::class,
            'notes' => "Verificación de cuenta bancaria '{$bankAccount->bank_name}' removida",
            'metadata' => [
                'action' => 'bank_account_unverified',
                'bank_account_id' => $bankAccount->id,
                'bank_name' => $bankAccount->bank_name,
                'was_verified' => $wasVerified,
            ],
        ]);

        return $this->success(null, 'Verificación de cuenta bancaria removida.');
    }

    /**
     * Mask CLABE for display (show only last 4 digits).
     */
    private function maskClabe(?string $clabe): string
    {
        if (!$clabe || strlen($clabe) < 4) {
            return '****';
        }
        return substr($clabe, -4);
    }

    /**
     * Get human-readable label for verification method.
     */
    private function getMethodLabel(?string $method): ?string
    {
        if (!$method) {
            return null;
        }

        $labels = [
            'MANUAL' => 'Manual',
            'KYC' => 'KYC Automático',
            'OCR' => 'OCR Automático',
            'FACE_MATCH' => 'Face Match',
            'LIVENESS' => 'Prueba de vida',
            'INE_VALIDATION' => 'Validación INE',
            'CURP_VALIDATION' => 'Validación CURP',
            'RFC_VALIDATION' => 'Validación RFC',
            'BANK_VALIDATION' => 'Validación bancaria',
            'PHONE_CALL' => 'Llamada telefónica',
            'EMAIL' => 'Email',
            'DOCUMENT' => 'Documento',
        ];

        return $labels[strtoupper($method)] ?? $method;
    }

    // =========================================================================
    // Formatting Helper Methods
    // =========================================================================

    /**
     * Format address for detail view.
     */
    private function formatAddress($address): ?array
    {
        if (!$address) {
            return null;
        }

        return [
            'id' => $address->id,
            'type' => $address->type,
            'street' => $address->street,
            'exterior_number' => $address->exterior_number,
            'interior_number' => $address->interior_number,
            'neighborhood' => $address->neighborhood,
            'municipality' => $address->municipality,
            'state' => $address->state,
            'postal_code' => $address->postal_code,
            'country' => $address->country ?? 'México',
            'housing_type' => $address->housing_type,
            'years_at_address' => $address->years_at_address,
            'months_at_address' => $address->months_at_address,
            'is_current' => $address->is_current,
            'verification_status' => $address->status,
        ];
    }

    /**
     * Format employment for detail view.
     */
    private function formatEmployment($employment): ?array
    {
        if (!$employment) {
            return null;
        }

        return [
            'id' => $employment->id,
            'employment_type' => $employment->employment_type,
            'employer_name' => $employment->employer_name,
            'employer_rfc' => $employment->employer_rfc,
            'employer_phone' => $employment->employer_phone,
            'job_title' => $employment->job_title,
            'department' => $employment->department,
            'monthly_income' => $employment->monthly_income,
            'additional_income' => $employment->additional_income,
            'payment_frequency' => $employment->payment_frequency,
            'start_date' => $employment->start_date?->format('Y-m-d'),
            'years_employed' => $employment->years_employed,
            'months_employed' => $employment->months_employed,
            'is_current' => $employment->is_current,
            'verification_status' => $employment->status,
        ];
    }

    /**
     * Format reference for detail view.
     */
    private function formatReference($reference): array
    {
        return [
            'id' => $reference->id,
            'full_name' => $reference->full_name,
            'first_name' => $reference->first_name,
            'last_name_1' => $reference->last_name_1,
            'last_name_2' => $reference->last_name_2,
            'phone' => $reference->phone,
            'email' => $reference->email,
            'relationship' => $reference->relationship,
            'type' => $reference->type,
            'years_known' => $reference->years_known,
            'verification_status' => $reference->status,
            'verified_at' => $reference->verified_at?->toIso8601String(),
            'verification_notes' => $reference->verification_notes,
        ];
    }

    /**
     * Format bank account for detail view.
     */
    private function formatBankAccount($bankAccount): array
    {
        return [
            'id' => $bankAccount->id,
            'bank_name' => $bankAccount->bank_name,
            'bank_code' => $bankAccount->bank_code,
            'clabe' => $bankAccount->clabe,
            'account_number' => $bankAccount->account_number,
            'account_type' => $bankAccount->account_type,
            'holder_name' => $bankAccount->holder_name,
            'is_primary' => $bankAccount->is_primary,
            'is_verified' => $bankAccount->is_verified,
            'verified_at' => $bankAccount->verified_at?->toIso8601String(),
            'created_at' => $bankAccount->created_at?->toIso8601String(),
        ];
    }

    /**
     * Get field verifications for an application.
     *
     * Combines DataVerification records and verification_checklist fallback.
     */
    private function getFieldVerifications(ApplicationV2 $app): array
    {
        $fieldVerifications = [];
        $lockedMethods = ['KYC', 'OCR', 'FACE_MATCH', 'LIVENESS', 'INE_VALIDATION', 'CURP_VALIDATION', 'RFC_VALIDATION'];

        // Try to get verifications by entity (Person or Company)
        $verifications = collect();
        if ($app->person) {
            // First try new entity_id/entity_type columns
            $verifications = \App\Models\DataVerification::forEntity($app->person)
                ->orderBy('created_at', 'desc')
                ->get();

            // Fallback to legacy applicant_id if no results
            if ($verifications->isEmpty()) {
                $applicant = $app->person->account?->applicant;
                if ($applicant) {
                    $verifications = \App\Models\DataVerification::where('applicant_id', $applicant->id)
                        ->orderBy('created_at', 'desc')
                        ->get();
                }
            }
        } elseif ($app->company) {
            $verifications = \App\Models\DataVerification::forEntity($app->company)
                ->orderBy('created_at', 'desc')
                ->get();
        }

        // Group by field_name and take the most recent
        $verifications = $verifications->groupBy('field_name')
            ->map(fn($group) => $group->first());

        foreach ($verifications as $fieldName => $v) {
            $method = $v->method?->value ?? $v->method;
            $fieldVerifications[$fieldName] = [
                'status' => $v->is_verified ? 'VERIFIED' : 'PENDING',
                'verified' => $v->is_verified,
                'method' => $method,
                'method_label' => $this->getMethodLabel($method),
                'rejection_reason' => $v->rejection_reason,
                'notes' => $v->notes,
                'verified_at' => $v->created_at?->toIso8601String(),
                'verified_by' => $v->verified_by,
                'is_locked' => $v->is_locked ?? ($v->is_verified && $method && in_array(strtoupper($method), $lockedMethods)),
                'metadata' => $v->metadata,
            ];
        }

        // Fallback to verification_checklist if no DataVerification records
        if (empty($fieldVerifications)) {
            $checklist = $app->verification_checklist ?? [];
            foreach ($checklist as $field => $info) {
                $method = $info['method'] ?? null;
                $isVerified = ($info['status'] ?? '') === 'verified';
                $fieldVerifications[$field] = [
                    'status' => strtoupper($info['status'] ?? 'pending'),
                    'verified' => $isVerified,
                    'method' => $method,
                    'method_label' => $this->getMethodLabel($method),
                    'rejection_reason' => $info['rejection_reason'] ?? null,
                    'notes' => $info['notes'] ?? null,
                    'verified_at' => $info['verified_at'] ?? null,
                    'verified_by' => $info['verified_by'] ?? null,
                    'is_locked' => $isVerified && $method && in_array(strtoupper($method), $lockedMethods),
                ];
            }
        }

        return $fieldVerifications;
    }

    /**
     * Get signature data for an application.
     */
    private function getSignatureData(ApplicationV2 $app): array
    {
        $person = $app->person;
        $signatureDoc = $person?->documents?->where('type', 'SIGNATURE')->first();

        if ($signatureDoc) {
            // Load signature from document
            $signatureBase64 = null;
            try {
                $disk = \Illuminate\Support\Facades\Storage::disk($signatureDoc->storage_disk ?? 'local');
                if ($disk->exists($signatureDoc->file_path)) {
                    $signatureBase64 = 'data:image/png;base64,' . base64_encode($disk->get($signatureDoc->file_path));
                }
            } catch (\Exception $e) {
                // Ignore storage errors
            }

            return [
                'has_signed' => true,
                'signature_base64' => $signatureBase64,
                'signature_date' => $signatureDoc->ocr_data['signed_at'] ?? $signatureDoc->created_at?->toIso8601String(),
                'signature_ip' => $signatureDoc->ocr_data['ip_address'] ?? null,
                'document_id' => $signatureDoc->id,
            ];
        } elseif ($person && $person->signature_date) {
            // Fallback to legacy person record
            return [
                'has_signed' => true,
                'signature_base64' => $person->signature_base64,
                'signature_date' => $person->signature_date?->toIso8601String(),
                'signature_ip' => $person->signature_ip,
            ];
        }

        return [
            'has_signed' => false,
            'signature_base64' => null,
            'signature_date' => null,
            'signature_ip' => null,
        ];
    }

    /**
     * Get all documents for an application.
     *
     * Combines documents from application and person (avoiding duplicates).
     */
    private function getDocuments(ApplicationV2 $app): array
    {
        $allDocuments = collect();
        $seenTypes = [];

        // First add application documents (direct uploads to application)
        foreach ($app->documents ?? [] as $d) {
            $allDocuments->push($d);
            $seenTypes[$d->type] = true;
        }

        // Then add person's documents (from onboarding/profile)
        if ($app->person && $app->person->documents) {
            foreach ($app->person->documents as $d) {
                // Only add if we don't already have a document of this type
                if (!isset($seenTypes[$d->type])) {
                    $allDocuments->push($d);
                    $seenTypes[$d->type] = true;
                }
            }
        }

        // Get KYC verification methods that should lock documents
        $kycMethods = [
            \App\Enums\VerificationMethod::KYC_INE_OCR->value,
            \App\Enums\VerificationMethod::KYC_INE_LIST->value,
            \App\Enums\VerificationMethod::KYC_FACE_MATCH->value,
            \App\Enums\VerificationMethod::KYC_LIVENESS->value,
            \App\Enums\VerificationMethod::NUBARIUM->value,
        ];

        // Map document types to field names for DataVerification lookup
        $docTypeToFieldMap = [
            'INE_FRONT' => ['ine_front', 'curp', 'rfc', 'first_name', 'last_name_1'],
            'INE_BACK' => ['ine_back', 'address'],
            'SELFIE' => ['selfie', 'face_match', 'liveness'],
        ];

        // Get all KYC-verified fields for this person
        $kycVerifiedFields = [];
        if ($app->person) {
            $verifications = DataVerification::where('entity_type', Person::class)
                ->where('entity_id', $app->person->id)
                ->where('is_verified', true)
                ->whereIn('method', $kycMethods)
                ->pluck('field_name')
                ->toArray();
            $kycVerifiedFields = array_flip($verifications);
        }

        return $allDocuments->map(function ($d) use ($kycVerifiedFields, $docTypeToFieldMap, $kycMethods) {
            $ocrData = $d->ocr_data ?? [];

            // Check if document was verified by KYC from ocr_data metadata
            $isKycFromMetadata = $d->status === 'APPROVED' && (
                ($ocrData['kyc_validated'] ?? false) ||
                ($ocrData['face_match_passed'] ?? false) ||
                ($ocrData['liveness_passed'] ?? false) ||
                ($ocrData['nubarium_validated'] ?? false) ||
                in_array($ocrData['validation_method'] ?? '', ['KYC', 'KYC_INE_OCR', 'KYC_FACE_MATCH', 'KYC_LIVENESS', 'FACE_MATCH', 'LIVENESS', 'INE_VALIDATION', 'NUBARIUM'])
            );

            // Check if document type has associated KYC-verified fields in DataVerification
            $isKycFromVerification = false;
            $relatedFields = $docTypeToFieldMap[$d->type] ?? [];
            foreach ($relatedFields as $field) {
                if (isset($kycVerifiedFields[$field])) {
                    $isKycFromVerification = true;
                    break;
                }
            }

            $isKycLocked = $isKycFromMetadata || $isKycFromVerification;

            return [
                'id' => $d->id,
                'type' => $d->type,
                'category' => $d->category,
                'file_name' => $d->file_name,
                'mime_type' => $d->mime_type,
                'file_size' => $d->file_size,
                'status' => $d->status,
                'rejection_reason' => $d->rejection_reason,
                'reviewed_at' => $d->reviewed_at?->toIso8601String(),
                'ocr_data' => $ocrData,
                'created_at' => $d->created_at?->toIso8601String(),
                'is_kyc_locked' => $isKycLocked,
            ];
        })->values()->toArray();
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
            return $this->notFound('Solicitud no encontrada.');
        }

        $checklist = $application->verification_checklist ?? [];
        $newStatus = match ($validated['action']) {
            'verify' => 'verified',
            'reject' => 'rejected',
            'unverify' => 'pending',
        };
        $oldStatus = $checklist[$validated['field']]['status'] ?? 'pending';

        $checklist[$validated['field']] = [
            'status' => $newStatus,
            'method' => $validated['method'] ?? null,
            'rejection_reason' => $validated['rejection_reason'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'verified_by' => $staff->id,
            'verified_at' => now()->toIso8601String(),
        ];

        $application->verification_checklist = $checklist;
        $application->save();

        // Record history entry for verification change
        ApplicationStatusHistory::create([
            'application_id' => $application->id,
            'from_status' => 'DATA_VERIFICATION',
            'to_status' => 'DATA_VERIFICATION',
            'changed_by' => $staff->id,
            'changed_by_type' => StaffAccount::class,
            'notes' => match ($validated['action']) {
                'verify' => "Campo '{$validated['field']}' verificado",
                'reject' => "Campo '{$validated['field']}' rechazado: " . ($validated['rejection_reason'] ?? ''),
                'unverify' => "Verificación removida del campo '{$validated['field']}'" . ($validated['notes'] ? ": {$validated['notes']}" : ''),
            },
            'metadata' => [
                'action' => 'data_verification',
                'field' => $validated['field'],
                'verification_action' => $validated['action'],
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'method' => $validated['method'] ?? null,
                'rejection_reason' => $validated['rejection_reason'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ],
        ]);

        return $this->success(null, 'Dato verificado.');
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
            ->with('person.account')
            ->first();

        if (!$application) {
            return $this->notFound('Solicitud no encontrada.');
        }

        // Get API logs related to this application or its entity (Person/Company)
        $applicantId = $application->person?->account?->applicant?->id;

        $logs = \App\Models\ApiLog::where('tenant_id', $staff->tenant_id)
            ->where(function ($q) use ($application, $applicantId) {
                $q->where('application_id', $application->id);

                // Search by entity_id (new V2 logs with polymorphic entity)
                if ($application->person) {
                    $q->orWhere(function ($q2) use ($application) {
                        $q2->where('entity_type', \App\Models\Person::class)
                            ->where('entity_id', $application->person_id);
                    });
                } elseif ($application->company) {
                    $q->orWhere(function ($q2) use ($application) {
                        $q2->where('entity_type', \App\Models\Company::class)
                            ->where('entity_id', $application->company_id);
                    });
                }

                // Fallback: Search by legacy applicant_id
                if ($applicantId) {
                    $q->orWhere('applicant_id', $applicantId);
                }
            })
            ->orderBy('created_at', 'desc')
            ->limit(100)
            ->get();

        return $this->success([
            'logs' => $logs->map(fn($log) => [
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

    // =========================================================================
    // Helper Methods
    // =========================================================================

    /**
     * Find a document by ID within an application's scope.
     *
     * Searches in both application documents and person's documents.
     */
    private function findApplicationDocument(ApplicationV2 $application, string $documentId): ?\App\Models\DocumentV2
    {
        // First, try to find in application's direct documents
        $document = $application->documents()->where('id', $documentId)->first();

        if ($document) {
            return $document;
        }

        // If not found, try to find in person's documents
        if ($application->person) {
            $document = $application->person->documents()
                ->where('id', $documentId)
                ->whereNull('replaced_at')
                ->first();
        }

        return $document;
    }
}
