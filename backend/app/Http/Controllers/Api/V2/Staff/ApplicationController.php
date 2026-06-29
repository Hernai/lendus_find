<?php

namespace App\Http\Controllers\Api\V2\Staff;

use App\Http\Controllers\Api\V2\Traits\ApiResponses;
use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\DataVerification;
use App\Models\Document;
use App\Models\Person;
use App\Models\StaffAccount;
use App\Services\ApplicationEventService;
use App\Services\ApplicationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Staff Application Controller (v2).
 *
 * Handles loan application management for staff members using the new
 * normalized Application model.
 */
class ApplicationController extends Controller
{
    use ApiResponses;
    public function __construct(
        private ApplicationService $service
    ) {}

    /**
     * Tenant scope para queries:
     * - SUPER_ADMIN puede ver cualquier tenant vía X-Tenant-ID (TenantSwitcher).
     * - Cualquier otro rol queda scoped a su propio tenant.
     */
    private function scopedTenantId(StaffAccount $staff): string
    {
        if ($staff->isSuperAdmin()) {
            $tenant = app('tenant');
            if ($tenant && isset($tenant->id)) {
                return (string) $tenant->id;
            }
        }
        return (string) $staff->tenant_id;
    }

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

        $validStatuses = implode(',', array_keys(Application::statuses()));
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

        // Super admin puede listar aplicaciones de cualquier tenant usando el
        // header X-Tenant-ID (TenantSwitcher). El resto queda scoped a su tenant.
        $scopeTenant = $staff->isSuperAdmin() ? app('tenant') : $staff->tenant;

        $applications = $this->service->list(
            $scopeTenant,
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
            'columns.*' => 'string|in:' . implode(',', array_keys(Application::statuses())),
            'limit_per_column' => 'nullable|integer|min:5|max:50',
            'assigned_to' => 'nullable|uuid',
            'sort_by' => 'nullable|string|in:created_at,submitted_at,requested_amount',
            'sort_dir' => 'nullable|string|in:asc,desc',
        ]);

        /** @var StaffAccount $staff */
        $staff = $request->user();

        // Default columns for Kanban (active workflow statuses)
        $columns = $validated['columns'] ?? [
            Application::STATUS_SUBMITTED,
            Application::STATUS_IN_REVIEW,
            Application::STATUS_DOCS_PENDING,
            Application::STATUS_APPROVED,
        ];

        $limitPerColumn = $validated['limit_per_column'] ?? 15;

        // ANALYST can only see applications assigned to them
        $assignedTo = null;
        if (!$staff->canViewAllApplications()) {
            $assignedTo = $staff->id;
        } elseif (!empty($validated['assigned_to'])) {
            $assignedTo = $validated['assigned_to'];
        }

        $scopeTenant = $staff->isSuperAdmin() ? app('tenant') : $staff->tenant;
        $boardData = $this->service->getBoardData(
            $scopeTenant,
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
            $staff->isSuperAdmin() ? app('tenant') : $staff->tenant,
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

        try {
            $tenant = $staff->isSuperAdmin() ? app('tenant') : $staff->tenant;
            if (!$tenant) {
                return $this->error('TENANT_NOT_RESOLVED', 'No se pudo resolver el tenant para este staff.', 422);
            }

            $applications = $this->service->getUnassigned($tenant);

            return $this->success([
                'applications' => $applications->map(fn ($app) => $this->formatApplication($app))->all(),
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('GET /v2/staff/applications/unassigned failed', [
                'staff_id' => $staff?->id,
                'tenant_id' => $staff?->tenant_id,
                'error' => $e->getMessage(),
                'file' => basename($e->getFile()),
                'line' => $e->getLine(),
                'trace_short' => collect($e->getTrace())->take(5)->map(fn ($t) => ($t['file'] ?? '?') . ':' . ($t['line'] ?? '?'))->all(),
            ]);
            return $this->error('UNASSIGNED_LIST_FAILED', 'No se pudo cargar la lista de solicitudes sin asignar.', 500);
        }
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

        try {
            $applications = $this->service->getAssignedTo($staff, $status);

            return $this->success([
                'applications' => $applications->map(fn ($app) => $this->formatApplication($app))->all(),
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('GET /v2/staff/applications/my-queue failed', [
                'staff_id' => $staff?->id,
                'tenant_id' => $staff?->tenant_id,
                'status_filter' => $status,
                'error' => $e->getMessage(),
                'file' => basename($e->getFile()),
                'line' => $e->getLine(),
                'trace_short' => collect($e->getTrace())->take(5)->map(fn ($t) => ($t['file'] ?? '?') . ':' . ($t['line'] ?? '?'))->all(),
            ]);
            return $this->error('MY_QUEUE_FAILED', 'No se pudo cargar tu cola de trabajo.', 500);
        }
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

        $application = Application::where('id', $id)
            ->where('tenant_id', $this->scopedTenantId($staff))
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
                // 'statusHistory' removido del eager load: ahora se consulta
                // bajo demanda via GET /applications/{id}/activity (feed unificado).
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

        $application = Application::where('id', $id)
            ->where('tenant_id', $this->scopedTenantId($staff))
            ->first();

        if (!$application) {
            return $this->notFound('Solicitud no encontrada.');
        }

        $assignee = StaffAccount::where('id', $validated['user_id'])
            ->where('tenant_id', $this->scopedTenantId($staff))
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
            'status' => 'required|string|in:' . implode(',', array_keys(Application::statuses())),
            'notes' => 'nullable|string|max:1000',
        ]);

        /** @var StaffAccount $staff */
        $staff = $request->user();

        $application = Application::where('id', $id)
            ->where('tenant_id', $this->scopedTenantId($staff))
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
            // El mínimo real lo valida el producto; aquí solo evitamos 0/negativos.
            'amount' => 'nullable|numeric|min:1',
            'term_months' => 'nullable|integer|min:1|max:120',
            'interest_rate' => 'nullable|numeric|min:0|max:100',
            'notes' => 'nullable|string|max:1000',
        ]);

        /** @var StaffAccount $staff */
        $staff = $request->user();

        $application = Application::where('id', $id)
            ->where('tenant_id', $this->scopedTenantId($staff))
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

        $application = Application::where('id', $id)
            ->where('tenant_id', $this->scopedTenantId($staff))
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
            'amount' => 'required|numeric|min:1',
            'term_months' => 'required|integer|min:1|max:120',
            'interest_rate' => 'nullable|numeric|min:0|max:100',
            'reason' => 'nullable|string|max:500',
        ]);

        /** @var StaffAccount $staff */
        $staff = $request->user();

        $application = Application::where('id', $id)
            ->where('tenant_id', $this->scopedTenantId($staff))
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

        $application = Application::where('id', $id)
            ->where('tenant_id', $this->scopedTenantId($staff))
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
                Application::RISK_LOW,
                Application::RISK_MEDIUM,
                Application::RISK_HIGH,
                Application::RISK_VERY_HIGH,
            ]),
            'data' => 'nullable|array',
        ]);

        /** @var StaffAccount $staff */
        $staff = $request->user();

        $application = Application::where('id', $id)
            ->where('tenant_id', $this->scopedTenantId($staff))
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
     * Format application for list view.
     */
    private function formatApplication(Application $app): array
    {
        try {
            return $this->doFormatApplication($app);
        } catch (\Throwable $e) {
            // Datos inconsistentes en una application no deben tirar 500 del
            // endpoint entero. Logueamos para diagnosticar y devolvemos
            // payload minimo con `_error: true` para que el frontend lo
            // muestre como "registro con problema" sin romper la lista.
            \Illuminate\Support\Facades\Log::warning('formatApplication failed', [
                'application_id' => $app->id,
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => basename($e->getFile()),
            ]);

            return [
                'id' => $app->id,
                'folio' => ($app->created_at?->format('Ymd') ?? 'XXXXXXXX') . '-' . strtoupper(substr((string) $app->id, 0, 4)),
                'status' => $app->status,
                'status_label' => $app->status_label ?? $app->status,
                'applicant_type' => $app->applicant_type,
                'applicant_name' => null,
                'applicant_phone' => null,
                'applicant_rfc' => null,
                'product' => ['id' => $app->product_id, 'name' => null, 'type' => null, 'required_documents' => []],
                'requested_amount' => $app->requested_amount,
                'requested_term_months' => $app->requested_term_months,
                'requested_term_days' => $app->requested_term_days,
                'term_in_days' => false,
                'monthly_payment' => $app->monthly_payment,
                'risk_level' => $app->risk_level,
                'assigned_to' => null,
                'created_at' => $app->created_at?->toIso8601String(),
                'updated_at' => $app->updated_at?->toIso8601String(),
                'submitted_at' => $app->submitted_at?->toIso8601String(),
                '_error' => 'Datos incompletos en este registro',
            ];
        }
    }

    private function doFormatApplication(Application $app): array
    {
        // Generate folio from created_at date + short UUID
        // Format: YYYYMMDD-XXXX (e.g., 20260119-ABC1)
        $folio = $app->created_at?->format('Ymd') . '-' . strtoupper(substr($app->id, 0, 4));

        // Cache de relaciones para evitar acceso encadenado con `null` que
        // en PHP 8 con strict mode puede escalar a 500 al indexar nullables.
        $product = $app->product;
        $productRules = is_array($product?->rules) ? $product->rules : [];
        $assignedTo = $app->assignedTo;

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
                'name' => $product?->name,
                'type' => $product?->type,
                'required_documents' => $product?->required_documents ?? [],
            ],
            'requested_amount' => $app->requested_amount,
            'requested_term_months' => $app->requested_term_months,
            'requested_term_days' => $app->requested_term_days,
            'term_in_days' => (bool) ($productRules['term_in_days'] ?? false),
            'monthly_payment' => $app->monthly_payment,
            'risk_level' => $app->risk_level,
            'assigned_to' => $assignedTo ? [
                'id' => $assignedTo->id,
                'name' => $assignedTo->profile?->full_name ?? $assignedTo->email,
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
    private function formatApplicationDetail(Application $app): array
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
            'requested_term_days' => $app->requested_term_days,
            'term_in_days' => (bool) ((is_array($app->product?->rules) ? $app->product->rules : [])['term_in_days'] ?? false),
            'payment_frequency' => (is_array($app->product?->payment_frequencies) ? $app->product->payment_frequencies : [])[0] ?? null,
            'purpose' => $app->purpose,
            'purpose_label' => $app->purpose ? (\App\Enums\LoanPurpose::tryFrom($app->purpose)?->label() ?? $app->purpose) : null,
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

        // Préstamos en línea solicitados, captado en el onboarding (metadata).
        // Aceptamos la clave nueva y la legacy ('credit_history' = id del paso).
        $data['online_loans_count'] = $app->metadata['online_loans_count']
            ?? $app->metadata['credit_history']
            ?? null;

        // =========================================================
        // APPLICANT - Person or Company with all related data
        // Structure mirrors profile API response
        // =========================================================
        if ($person) {
            // Get CURP and RFC from person_identifications table
            $identifications = $person->identifications ?? collect();
            $curp = $identifications->firstWhere('type', 'CURP')?->identifier_value;
            $rfc = $identifications->firstWhere('type', 'RFC')?->identifier_value;
            $ineData = $identifications->firstWhere('type', 'INE');
            $passportData = $identifications->firstWhere('type', 'PASSPORT');

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
                        'nationality_info' => \App\Helpers\CountryHelper::getCountryInfo($person->nationality),
                        'marital_status' => $person->marital_status,
                        'marital_status_label' => $person->marital_status
                            ? (\App\Enums\MaritalStatus::tryFrom($person->marital_status)?->label() ?? $person->marital_status)
                            : null,
                        'education_level' => $person->education_level,
                        'education_level_label' => $person->education_level
                            ? (\App\Enums\EducationLevel::tryFrom($person->education_level)?->label() ?? $person->education_level)
                            : null,
                        'dependents_count' => $person->dependents_count ?? 0,
                    ],
                    'identifications' => [
                        'curp' => $curp,
                        'curp_verified' => $identifications->firstWhere('type', 'CURP')?->status === 'VERIFIED',
                        'rfc' => $rfc,
                        'rfc_verified' => $identifications->firstWhere('type', 'RFC')?->status === 'VERIFIED',
                        // INE: clave de elector, OCR y folio (CIC) viven en document_data.
                        'ine_clave' => ($ineData?->document_data['clave_elector'] ?? null) ?: $ineData?->identifier_value,
                        'ine_ocr' => $ineData?->document_data['ocr'] ?? null,
                        'ine_folio' => $ineData?->document_data['cic'] ?? $ineData?->identifier_value,
                        'ine_verified' => $ineData?->status === 'VERIFIED',
                        // Passport data for foreigners
                        'passport_number' => $passportData?->identifier_value,
                        'passport_issue_date' => $passportData?->document_data['issue_date'] ?? null,
                        'passport_expiry_date' => $passportData?->document_data['expiry_date'] ?? null,
                        'passport_verified' => $passportData?->status === 'VERIFIED',
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
            // status_history removido del payload: el frontend ahora consulta
            // GET /applications/{id}/activity para el feed unificado (eventos
            // de negocio + auditoria + llamadas a APIs externas).
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

        $application = Application::where('id', $id)
            ->where('tenant_id', $this->scopedTenantId($staff))
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

        $application = Application::where('id', $id)
            ->where('tenant_id', $this->scopedTenantId($staff))
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
        \App\Services\ActivityRecorder::recordApplicationEvent($application, 'APPLICATION_UPDATED', [
            'from_status' => 'NOTE_ADDED',
            'to_status' => 'NOTE_ADDED',
            'notes' => "Nota agregada: \"{$truncatedContent}\"",
            'metadata' => [
                'kind' => 'note_added',
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

        $application = Application::where('id', $appId)
            ->where('tenant_id', $this->scopedTenantId($staff))
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
     * Download document.
     *
     * GET /v2/staff/applications/{appId}/documents/{docId}/download
     */
    public function downloadDocument(Request $request, string $appId, string $docId)
    {
        /** @var StaffAccount $staff */
        $staff = $request->user();

        $application = Application::where('id', $appId)
            ->where('tenant_id', $this->scopedTenantId($staff))
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

        $application = Application::where('id', $appId)
            ->where('tenant_id', $this->scopedTenantId($staff))
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

        \App\Services\ActivityRecorder::recordApplicationEvent($application, 'DOCUMENT_APPROVED', [
            'from_status' => 'DOCUMENT_REVIEW',
            'to_status' => 'DOCUMENT_REVIEW',
            'notes' => "Documento '{$document->type}' aprobado",
            'entity' => $document,
            'old_values' => ['status' => $oldStatus],
            'new_values' => ['status' => 'APPROVED'],
            'metadata' => [
                'document_type' => $document->type,
            ],
        ]);

        // Check if all verifications are complete to auto-advance status
        $statusChanged = $this->checkAndAdvanceStatus($application, $staff);

        return $this->success([
            'application_status_changed' => $statusChanged,
            'new_application_status' => $application->status,
        ], 'Documento aprobado.');
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

        $application = Application::where('id', $appId)
            ->where('tenant_id', $this->scopedTenantId($staff))
            ->with('person')
            ->first();

        if (!$application) {
            return $this->notFound('Solicitud no encontrada.');
        }

        $document = $this->findApplicationDocument($application, $docId);

        if (!$document) {
            return $this->notFound('Documento no encontrado.');
        }

        $oldDocStatus = $document->status;
        $document->status = 'REJECTED';
        $document->rejection_reason = $validated['reason'];
        $document->reviewed_at = now();
        $document->reviewed_by = $staff->id;
        $document->save();

        // Change application status to DOCS_PENDING if not already
        $oldAppStatus = $application->status;
        $statusChanged = false;
        if ($application->status !== Application::STATUS_DOCS_PENDING) {
            // Only change status if transition is allowed
            if ($application->canTransitionTo(Application::STATUS_DOCS_PENDING)) {
                $application->status = Application::STATUS_DOCS_PENDING;
                $application->save();
                $statusChanged = true;
            }
        }

        \App\Services\ActivityRecorder::recordApplicationEvent($application, 'DOCUMENT_REJECTED', [
            'from_status' => 'DOCUMENT_REVIEW',
            'to_status' => 'DOCUMENT_REVIEW',
            'notes' => "Documento '{$document->type}' rechazado: {$validated['reason']}",
            'entity' => $document,
            'old_values' => ['status' => $oldDocStatus],
            'new_values' => ['status' => 'REJECTED', 'rejection_reason' => $validated['reason']],
            'metadata' => [
                'document_type' => $document->type,
                'reason' => $validated['reason'],
                'comment' => $validated['comment'] ?? null,
            ],
        ]);

        if ($statusChanged) {
            \App\Services\ActivityRecorder::recordApplicationEvent($application, 'APPLICATION_UPDATED', [
                'from_status' => $oldAppStatus,
                'to_status' => Application::STATUS_DOCS_PENDING,
                'notes' => "Solicitud movida a documentos pendientes por rechazo de documento '{$document->type}'",
                'old_values' => ['status' => $oldAppStatus],
                'new_values' => ['status' => Application::STATUS_DOCS_PENDING],
                'metadata' => [
                    'trigger' => 'document_rejected',
                    'document_id' => $document->id,
                    'document_type' => $document->type,
                ],
            ]);
        }

        return $this->success([
            'application_status_changed' => $statusChanged,
            'new_application_status' => $application->status,
        ], 'Documento rechazado.');
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

        $application = Application::where('id', $appId)
            ->where('tenant_id', $this->scopedTenantId($staff))
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

        \App\Services\ActivityRecorder::recordApplicationEvent($application, 'DOCUMENT_UNAPPROVED', [
            'from_status' => 'DOCUMENT_REVIEW',
            'to_status' => 'DOCUMENT_REVIEW',
            'notes' => "Documento '{$document->type}' regresado a pendiente",
            'entity' => $document,
            'old_values' => ['status' => $oldStatus],
            'new_values' => ['status' => 'PENDING'],
            'metadata' => ['document_type' => $document->type],
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

        $application = Application::where('id', $appId)
            ->where('tenant_id', $this->scopedTenantId($staff))
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
        \App\Services\ActivityRecorder::recordApplicationEvent($application, 'REFERENCE_VERIFIED', [
            'from_status' => 'REFERENCE_VERIFICATION',
            'to_status' => 'REFERENCE_VERIFICATION',
            'notes' => "Referencia '{$reference->full_name}' {$resultLabels[$validated['result']]}" . (($validated['notes'] ?? null) ? ": {$validated['notes']}" : ''),
            'entity' => $reference,
            'old_values' => ['status' => $oldStatus],
            'new_values' => ['status' => $statusMap[$validated['result']]],
            'metadata' => [
                'reference_name' => $reference->full_name,
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

        $application = Application::where('id', $appId)
            ->where('tenant_id', $this->scopedTenantId($staff))
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
        // Verificación manual (la distingue de 'nubarium_clabe' para la regla de
        // desverificación: las de Nubarium solo las quita un super admin).
        $bankAccount->verification_method = 'manual';
        $bankAccount->save();

        \App\Services\ActivityRecorder::recordApplicationEvent($application, 'DATA_VERIFIED', [
            'from_status' => 'BANK_ACCOUNT_VERIFICATION',
            'to_status' => 'BANK_ACCOUNT_VERIFICATION',
            'notes' => "Cuenta bancaria '{$bankAccount->bank_name}' verificada (CLABE: ***{$this->maskClabe($bankAccount->clabe)})",
            'entity' => $bankAccount,
            'old_values' => ['is_verified' => $wasVerified],
            'new_values' => ['is_verified' => true],
            'metadata' => [
                'bank_name' => $bankAccount->bank_name,
                'kind' => 'bank_account_verified',
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

        $application = Application::where('id', $appId)
            ->where('tenant_id', $this->scopedTenantId($staff))
            ->with('person.bankAccounts')
            ->first();

        if (!$application || !$application->person) {
            return $this->notFound('Solicitud no encontrada.');
        }

        $bankAccount = $application->person->bankAccounts->firstWhere('id', $baId);

        if (!$bankAccount) {
            return $this->notFound('Cuenta bancaria no encontrada.');
        }

        // Si la cuenta fue validada por Nubarium, solo un SUPER_ADMIN puede quitar
        // la verificación (las verificaciones manuales las puede quitar cualquier
        // analista con permiso).
        if ($bankAccount->verification_method === 'nubarium_clabe' && !$staff->isSuperAdmin()) {
            return $this->forbidden('Esta cuenta fue validada por Nubarium; solo un super admin puede quitar la verificación.');
        }

        $wasVerified = $bankAccount->is_verified;
        $bankAccount->is_verified = false;
        $bankAccount->verified_at = null;
        $bankAccount->verified_by = null;
        $bankAccount->verification_method = null;
        $bankAccount->save();

        \App\Services\ActivityRecorder::recordApplicationEvent($application, 'DATA_REJECTED', [
            'from_status' => 'BANK_ACCOUNT_VERIFICATION',
            'to_status' => 'BANK_ACCOUNT_VERIFICATION',
            'notes' => "Verificación de cuenta bancaria '{$bankAccount->bank_name}' removida",
            'entity' => $bankAccount,
            'old_values' => ['is_verified' => $wasVerified],
            'new_values' => ['is_verified' => false],
            'metadata' => [
                'bank_name' => $bankAccount->bank_name,
                'kind' => 'bank_account_unverified',
            ],
        ]);

        return $this->success(null, 'Verificación de cuenta bancaria removida.');
    }

    /**
     * Edita los datos del titular de una cuenta bancaria (corrección de
     * nombre/RFC). Restringido a SUPER_ADMIN (ver middleware de la ruta).
     *
     * Caso de uso: el onboarding llegó a persistir el titular como "TITULAR"
     * cuando el perfil no tenía nombre cargado; esto permite corregirlo.
     *
     * PUT /v2/staff/applications/{appId}/bank-accounts/{baId}
     */
    public function updateBankAccount(Request $request, string $appId, string $baId): JsonResponse
    {
        /** @var StaffAccount $staff */
        $staff = $request->user();

        $validated = $request->validate([
            'holder_name' => 'required|string|max:200',
            'holder_rfc' => 'nullable|string|max:13',
        ]);

        $application = Application::where('id', $appId)
            ->where('tenant_id', $this->scopedTenantId($staff))
            ->with('person.bankAccounts')
            ->first();

        if (!$application || !$application->person) {
            return $this->notFound('Solicitud no encontrada.');
        }

        $bankAccount = $application->person->bankAccounts->firstWhere('id', $baId);

        if (!$bankAccount) {
            return $this->notFound('Cuenta bancaria no encontrada.');
        }

        $old = ['holder_name' => $bankAccount->holder_name, 'holder_rfc' => $bankAccount->holder_rfc];

        $bankAccount->holder_name = strtoupper(trim($validated['holder_name']));
        if (array_key_exists('holder_rfc', $validated)) {
            $rfc = trim((string) ($validated['holder_rfc'] ?? ''));
            $bankAccount->holder_rfc = $rfc !== '' ? strtoupper($rfc) : null;
        }
        $bankAccount->save();

        \App\Services\ActivityRecorder::recordApplicationEvent($application, 'DATA_UPDATED', [
            'from_status' => 'BANK_ACCOUNT_EDIT',
            'to_status' => 'BANK_ACCOUNT_EDIT',
            'notes' => "Titular de la cuenta '{$bankAccount->bank_name}' actualizado a '{$bankAccount->holder_name}'",
            'entity' => $bankAccount,
            'old_values' => $old,
            'new_values' => ['holder_name' => $bankAccount->holder_name, 'holder_rfc' => $bankAccount->holder_rfc],
            'metadata' => ['bank_name' => $bankAccount->bank_name, 'kind' => 'bank_account_updated'],
        ]);

        return $this->success([
            'holder_name' => $bankAccount->holder_name,
            'holder_rfc' => $bankAccount->holder_rfc,
        ], 'Titular actualizado.');
    }

    /**
     * Inicia la validación de la CLABE de una cuenta bancaria con Nubarium
     * (API Plus, asíncrono por webhook). Devuelve una validación `pending`; el
     * resultado se consulta con getNubariumValidation.
     *
     * POST /v2/staff/applications/{appId}/bank-accounts/{baId}/validate-clabe
     */
    public function validateBankAccountClabe(Request $request, string $appId, string $baId): JsonResponse
    {
        /** @var StaffAccount $staff */
        $staff = $request->user();

        $application = Application::where('id', $appId)
            ->where('tenant_id', $this->scopedTenantId($staff))
            ->with('person.bankAccounts')
            ->first();

        if (!$application || !$application->person) {
            return $this->notFound('Solicitud no encontrada.');
        }

        $bankAccount = $application->person->bankAccounts->firstWhere('id', $baId);

        if (!$bankAccount) {
            return $this->notFound('Cuenta bancaria no encontrada.');
        }

        $tenant = \App\Models\Tenant::withoutGlobalScopes()->find($this->scopedTenantId($staff));
        $service = new \App\Services\ExternalApi\NubariumService($tenant);

        $result = $service->validateClabe($bankAccount->holder_name, $bankAccount->clabe, $bankAccount);

        if (!($result['success'] ?? false)) {
            return $this->badRequest('CLABE_VALIDATION_FAILED', $result['error'] ?? 'No se pudo iniciar la validación de CLABE');
        }

        return $this->success([
            'validation_id' => $result['validation_id'] ?? null,
            'validation_code' => $result['validation_code'] ?? null,
            'status' => $result['status'] ?? 'pending',
        ], $result['message'] ?? 'Validación de CLABE iniciada');
    }

    /**
     * Consulta el estado/resultado de una validación asíncrona de Nubarium.
     *
     * GET /v2/staff/nubarium-validations/{id}
     */
    public function getNubariumValidation(Request $request, string $id): JsonResponse
    {
        /** @var StaffAccount $staff */
        $staff = $request->user();

        $validation = \App\Models\NubariumAsyncValidation::where('id', $id)
            ->where('tenant_id', $this->scopedTenantId($staff))
            ->first();

        if (!$validation) {
            return $this->notFound('Validación no encontrada.');
        }

        return $this->success($validation->toApiArray());
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
            'city' => $address->city,
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
            // Rango de ingresos del onboarding reconstruido desde monthly_income
            // (se captura como rango pero se persiste solo el punto medio).
            'income_range' => \App\Enums\SalaryRange::fromIncome($employment->monthly_income)?->value,
            'income_range_label' => \App\Enums\SalaryRange::fromIncome($employment->monthly_income)?->label(),
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
        // Mask sensitive data
        $maskedClabe = $bankAccount->clabe
            ? substr($bankAccount->clabe, 0, 4) . '****' . substr($bankAccount->clabe, -4)
            : null;
        $maskedAccountNumber = $bankAccount->account_number
            ? '****' . substr($bankAccount->account_number, -4)
            : null;

        return [
            'id' => $bankAccount->id,
            'bank_name' => $bankAccount->bank_name,
            'bank_code' => $bankAccount->bank_code,
            'clabe' => $maskedClabe,
            'account_number' => $maskedAccountNumber,
            'account_type' => $bankAccount->account_type,
            'holder_name' => $bankAccount->holder_name,
            'is_primary' => $bankAccount->is_primary,
            'is_verified' => $bankAccount->is_verified,
            'verified_at' => $bankAccount->verified_at?->toIso8601String(),
            'verification_method' => $bankAccount->verification_method,
            // true si la verificación la hizo Nubarium (solo super admin desverifica).
            'verified_by_nubarium' => $bankAccount->verification_method === 'nubarium_clabe',
            // Resultado persistido de la validación de CLABE con Nubarium (o null).
            'clabe_validation' => $bankAccount->verification_data['nubarium_clabe'] ?? null,
            'created_at' => $bankAccount->created_at?->toIso8601String(),
        ];
    }

    /**
     * Sync verification status from checklist to the related model.
     *
     * When verifying/rejecting data from the checklist, also update
     * the status field on the related model (employment, address, etc.)
     * so the frontend shows consistent data.
     *
     * Also records an ApplicationStatusHistory entry to maintain audit trail.
     */
    private function syncVerificationStatusToRelatedModel(
        Application $application,
        string $field,
        string $checklistStatus,
        ?string $method,
        ?string $rejectionReason,
        string $staffId
    ): void {
        $person = $application->person;
        if (!$person) {
            return;
        }

        // Map checklist status to model status
        $modelStatus = match ($checklistStatus) {
            'verified' => 'VERIFIED',
            'rejected' => 'REJECTED',
            'pending' => 'PENDING',
            default => 'PENDING',
        };

        $entityUpdated = false;
        $entityType = null;
        $entityId = null;
        $oldStatus = null;

        // Handle different field types
        switch ($field) {
            case 'employment':
                $employment = $person->employments?->where('is_current', true)->first();
                if ($employment) {
                    $oldStatus = $employment->status;
                    $employment->update([
                        'status' => $modelStatus,
                        'verified_at' => $checklistStatus === 'verified' ? now() : null,
                        'verified_by' => $checklistStatus === 'verified' ? $staffId : null,
                        'verification_method' => $method,
                        'verification_notes' => $rejectionReason,
                    ]);
                    $entityUpdated = true;
                    $entityType = 'employment';
                    $entityId = $employment->id;
                }
                break;

            case 'address':
                $address = $person->addresses?->where('is_current', true)->first();
                if ($address) {
                    $oldStatus = $address->status;
                    $address->update([
                        'status' => $modelStatus,
                        'verified_at' => $checklistStatus === 'verified' ? now() : null,
                        'verified_by' => $checklistStatus === 'verified' ? $staffId : null,
                        'verification_method' => $method,
                    ]);
                    $entityUpdated = true;
                    $entityType = 'address';
                    $entityId = $address->id;
                }
                break;

            case 'income':
                // Income verification updates the employment record's income fields
                $employment = $person->employments?->where('is_current', true)->first();
                if ($employment) {
                    $oldStatus = $employment->income_verified ? 'VERIFIED' : 'PENDING';
                    if ($checklistStatus === 'verified') {
                        $employment->update([
                            'income_verified' => true,
                            'income_verified_at' => now(),
                            'income_verified_by' => $staffId,
                            'income_verification_method' => $method,
                        ]);
                    } else {
                        $employment->update([
                            'income_verified' => false,
                            'income_verified_at' => null,
                            'income_verified_by' => null,
                        ]);
                    }
                    $entityUpdated = true;
                    $entityType = 'income';
                    $entityId = $employment->id;
                }
                break;

            // Identification documents (CURP, RFC, INE)
            case 'curp':
            case 'rfc':
            case 'ine':
            case 'ine_clave':
                $identType = match ($field) {
                    'curp' => 'CURP',
                    'rfc' => 'RFC',
                    'ine', 'ine_clave' => 'INE',
                    default => strtoupper($field),
                };
                $identification = $person->identifications?->where('type', $identType)->where('is_current', true)->first();
                if ($identification) {
                    $oldStatus = $identification->status;
                    $identification->update([
                        'status' => $modelStatus,
                        'verified_at' => $checklistStatus === 'verified' ? now() : null,
                        'verified_by' => $checklistStatus === 'verified' ? $staffId : null,
                        'verification_method' => $method,
                    ]);
                    $entityUpdated = true;
                    $entityType = 'identification';
                    $entityId = $identification->id;
                }
                // Also check if we should update Person KYC status
                $this->updatePersonKycStatusIfNeeded($person, $checklistStatus, $staffId);
                break;

            // Person data fields (name, birth_date) - these update Person KYC status
            case 'first_name':
            case 'last_name_1':
            case 'last_name_2':
            case 'birth_date':
            case 'gender':
            case 'nationality':
            case 'birth_state':
            case 'birth_country':
                // Person fields don't have individual status columns,
                // but we track via DataVerification and update Person KYC status
                $oldStatus = $person->kyc_status;
                $this->updatePersonKycStatusIfNeeded($person, $checklistStatus, $staffId);
                if ($person->kyc_status !== $oldStatus) {
                    $entityUpdated = true;
                    $entityType = 'person';
                    $entityId = $person->id;
                }
                break;

            // References are handled by their individual IDs (reference_<uuid>)
            default:
                // Check if it's a reference verification (format: reference_<uuid>)
                if (str_starts_with($field, 'reference_')) {
                    $referenceId = str_replace('reference_', '', $field);
                    $reference = $person->references?->where('id', $referenceId)->first();
                    if ($reference) {
                        $oldStatus = $reference->status;
                        $reference->update([
                            'status' => $modelStatus,
                            'verified_at' => $checklistStatus === 'verified' ? now() : null,
                            'verified_by' => $checklistStatus === 'verified' ? $staffId : null,
                            'verification_notes' => $rejectionReason,
                        ]);
                        $entityUpdated = true;
                        $entityType = 'reference';
                        $entityId = $reference->id;
                    }
                }
                // Check if it's a bank account verification (format: bank_account_<uuid>)
                elseif (str_starts_with($field, 'bank_account_')) {
                    $bankAccountId = str_replace('bank_account_', '', $field);
                    $bankAccount = $person->bankAccounts?->where('id', $bankAccountId)->first();
                    if ($bankAccount) {
                        $oldStatus = $bankAccount->is_verified ? 'VERIFIED' : 'PENDING';
                        $bankAccount->update([
                            'is_verified' => $checklistStatus === 'verified',
                            'verified_at' => $checklistStatus === 'verified' ? now() : null,
                            'verified_by' => $checklistStatus === 'verified' ? $staffId : null,
                        ]);
                        $entityUpdated = true;
                        $entityType = 'bank_account';
                        $entityId = $bankAccount->id;
                    }
                }
                // Check if it's an identification by type (format: identification_<TYPE>)
                elseif (str_starts_with($field, 'identification_')) {
                    $identType = str_replace('identification_', '', $field);
                    $identification = $person->identifications?->where('type', strtoupper($identType))->where('is_current', true)->first();
                    if ($identification) {
                        $oldStatus = $identification->status;
                        $identification->update([
                            'status' => $modelStatus,
                            'verified_at' => $checklistStatus === 'verified' ? now() : null,
                            'verified_by' => $checklistStatus === 'verified' ? $staffId : null,
                            'verification_method' => $method,
                        ]);
                        $entityUpdated = true;
                        $entityType = 'identification';
                        $entityId = $identification->id;
                    }
                }
                break;
        }

        // Record history entry if entity was updated
        if ($entityUpdated && $oldStatus !== $modelStatus) {
            $actionLabel = match ($checklistStatus) {
                'verified' => 'verificado',
                'rejected' => 'rechazado',
                'pending' => 'pendiente',
                default => 'actualizado',
            };

            $fieldLabel = match ($entityType) {
                'employment' => 'Información laboral',
                'address' => 'Dirección',
                'income' => 'Ingresos',
                'reference' => 'Referencia',
                'bank_account' => 'Cuenta bancaria',
                'identification' => match ($field) {
                    'curp' => 'CURP',
                    'rfc' => 'RFC',
                    'ine', 'ine_clave' => 'INE',
                    default => 'Identificación',
                },
                'person' => match ($field) {
                    'first_name' => 'Nombre',
                    'last_name_1' => 'Apellido paterno',
                    'last_name_2' => 'Apellido materno',
                    'birth_date' => 'Fecha de nacimiento',
                    'gender' => 'Género',
                    'nationality' => 'Nacionalidad',
                    'birth_state' => 'Estado de nacimiento',
                    'birth_country' => 'País de nacimiento',
                    default => 'Datos personales',
                },
                default => ucfirst($field),
            };

            \App\Services\ActivityRecorder::recordApplicationEvent(
                $application,
                $modelStatus === 'REJECTED' ? 'DATA_REJECTED' : 'DATA_VERIFIED',
                [
                    'from_status' => 'ENTITY_VERIFICATION',
                    'to_status' => 'ENTITY_VERIFICATION',
                    'notes' => "{$fieldLabel} {$actionLabel}" . ($rejectionReason ? ": {$rejectionReason}" : ''),
                    'old_values' => ['status' => $oldStatus],
                    'new_values' => ['status' => $modelStatus],
                    'metadata' => [
                        'entity_type' => $entityType,
                        'entity_id' => $entityId,
                        'field' => $field,
                        'method' => $method,
                        'rejection_reason' => $rejectionReason,
                    ],
                ]
            );
        }
    }

    /**
     * Update Person KYC status based on verification state.
     *
     * Checks if all critical fields are verified (via DataVerification table)
     * and updates Person.kyc_status accordingly.
     */
    private function updatePersonKycStatusIfNeeded(Person $person, string $checklistStatus, string $staffId): void
    {
        // If rejecting any field, mark KYC as rejected
        if ($checklistStatus === 'rejected') {
            if ($person->kyc_status !== Person::KYC_REJECTED) {
                $person->updateKycStatus(Person::KYC_REJECTED, [
                    'rejection_reason' => 'Manual rejection by staff',
                    'rejected_at' => now()->toIso8601String(),
                ], $staffId);
            }
            return;
        }

        // If verifying, check if all critical fields are now verified
        if ($checklistStatus === 'verified') {
            $criticalFields = ['curp', 'first_name', 'last_name_1', 'birth_date'];

            $verifiedCount = DataVerification::where('applicant_id', $person->id)
                ->whereIn('field_name', $criticalFields)
                ->where('is_verified', true)
                ->count();

            // If all critical fields are verified, mark KYC as verified
            if ($verifiedCount >= count($criticalFields)) {
                if ($person->kyc_status !== Person::KYC_VERIFIED) {
                    $person->updateKycStatus(Person::KYC_VERIFIED, [
                        'verified_at' => now()->toIso8601String(),
                        'method' => 'manual_staff_verification',
                    ], $staffId);
                }
            } else {
                // Some fields verified but not all - mark as in progress
                if ($person->kyc_status === Person::KYC_PENDING) {
                    $person->updateKycStatus(Person::KYC_IN_PROGRESS);
                }
            }
        }
    }

    /**
     * Get field verifications for an application.
     *
     * Combines DataVerification records and verification_checklist fallback.
     */
    private function getFieldVerifications(Application $app): array
    {
        $fieldVerifications = [];
        $lockedMethods = ['KYC', 'OCR', 'FACE_MATCH', 'LIVENESS', 'INE_VALIDATION', 'CURP_VALIDATION', 'RFC_VALIDATION'];

        // Try to get verifications by entity (Person or Company)
        $verifications = collect();
        if ($app->person) {
            // First try applicant_id (which stores person_id in VerificationService)
            $verifications = \App\Models\DataVerification::where('applicant_id', $app->person->id)
                ->orderBy('created_at', 'desc')
                ->get();

            // Fallback to entity_type/entity_id columns if no results
            if ($verifications->isEmpty()) {
                $verifications = \App\Models\DataVerification::forEntity($app->person)
                    ->orderBy('created_at', 'desc')
                    ->get();
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

        // Also include verification_checklist entries for fields NOT in DataVerification
        // This handles manual verifications (employment, address, references, bank_accounts)
        $checklist = $app->verification_checklist ?? [];
        foreach ($checklist as $field => $info) {
            // Skip if already have a DataVerification record for this field
            if (isset($fieldVerifications[$field])) {
                continue;
            }

            $method = $info['method'] ?? null;
            $status = strtolower($info['status'] ?? 'pending');
            $isVerified = $status === 'verified';
            $isRejected = $status === 'rejected';

            $fieldVerifications[$field] = [
                'status' => strtoupper($status),
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

        // Teléfono / email verificados por OTP. La verificación ocurre al
        // registrarse (a veces ANTES de que exista la Person), por lo que no
        // siempre queda un DataVerification ligado; la fuente autoritativa es el
        // verified_at de la identidad. Si está verificada y no hay registro
        // previo del campo, lo marcamos como verificado por OTP (bloqueado).
        $account = $app->person?->account;
        if ($account) {
            $otpIdentities = [
                'phone' => $account->phoneIdentity,
                'email' => $account->emailIdentity,
            ];
            foreach ($otpIdentities as $field => $identity) {
                if ($identity?->verified_at && empty($fieldVerifications[$field])) {
                    $fieldVerifications[$field] = [
                        'status' => 'VERIFIED',
                        'verified' => true,
                        'method' => 'OTP',
                        'method_label' => 'Verificado por OTP',
                        'rejection_reason' => null,
                        'notes' => null,
                        'verified_at' => $identity->verified_at?->toIso8601String(),
                        'verified_by' => null,
                        'is_locked' => true,
                        'metadata' => null,
                    ];
                }
            }
        }

        return $fieldVerifications;
    }

    /**
     * Get signature data for an application.
     */
    private function getSignatureData(Application $app): array
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
     * Check if all verifications are complete and update status if needed.
     *
     * When all data fields are verified (not rejected/pending) and all documents
     * are approved (not rejected/pending), automatically move the application
     * from CORRECTIONS_PENDING or DOCS_PENDING back to IN_REVIEW.
     *
     * @param Application $app
     * @param StaffAccount $staff
     * @return bool True if status was changed
     */
    private function checkAndAdvanceStatus(Application $app, StaffAccount $staff): bool
    {
        // Only auto-advance from these statuses
        $autoAdvanceFrom = [
            Application::STATUS_CORRECTIONS_PENDING,
            Application::STATUS_DOCS_PENDING,
        ];

        if (!in_array($app->status, $autoAdvanceFrom)) {
            return false;
        }

        // Check for rejected fields in verification_checklist
        $checklist = $app->verification_checklist ?? [];
        $hasRejectedFields = false;
        $hasPendingFields = false;

        foreach ($checklist as $fieldData) {
            $status = strtolower($fieldData['status'] ?? 'pending');
            if ($status === 'rejected') {
                $hasRejectedFields = true;
                break;
            }
            if ($status === 'pending') {
                $hasPendingFields = true;
            }
        }

        if ($hasRejectedFields) {
            return false;
        }

        // Check for rejected/pending documents (both from application and person)
        $hasRejectedDocs = Document::where(function ($q) use ($app) {
                $q->where(function ($q2) use ($app) {
                    $q2->where('documentable_type', Application::class)
                        ->where('documentable_id', $app->id);
                })->orWhere(function ($q2) use ($app) {
                    if ($app->person_id) {
                        $q2->where('documentable_type', Person::class)
                            ->where('documentable_id', $app->person_id);
                    }
                });
            })
            ->where('status', \App\Enums\DocumentStatus::REJECTED)
            ->whereNull('replaced_at')
            ->exists();

        if ($hasRejectedDocs) {
            return false;
        }

        // Check for pending documents (not approved yet)
        $hasPendingDocs = Document::where(function ($q) use ($app) {
                $q->where(function ($q2) use ($app) {
                    $q2->where('documentable_type', Application::class)
                        ->where('documentable_id', $app->id);
                })->orWhere(function ($q2) use ($app) {
                    if ($app->person_id) {
                        $q2->where('documentable_type', Person::class)
                            ->where('documentable_id', $app->person_id);
                    }
                });
            })
            ->where('status', \App\Enums\DocumentStatus::PENDING)
            ->whereNull('replaced_at')
            ->exists();

        // If there are no rejected items and no pending documents, advance the status
        // Note: We allow pending fields (fields that haven't been manually verified yet)
        // because not all fields are required to be manually verified
        if (!$hasRejectedDocs && !$hasPendingDocs) {
            $oldStatus = $app->status;

            // Move to IN_REVIEW
            if ($app->canTransitionTo(Application::STATUS_IN_REVIEW)) {
                $app->status = Application::STATUS_IN_REVIEW;
                $app->save();

                \App\Services\ActivityRecorder::recordApplicationEvent($app, 'APPLICATION_UPDATED', [
                    'from_status' => $oldStatus,
                    'to_status' => Application::STATUS_IN_REVIEW,
                    'notes' => 'Verificaciones completadas, solicitud lista para revisión',
                    'old_values' => ['status' => $oldStatus],
                    'new_values' => ['status' => Application::STATUS_IN_REVIEW],
                    'metadata' => [
                        'trigger' => 'verifications_complete',
                        'kind' => 'auto_status_advance',
                    ],
                ]);

                return true;
            }
        }

        return false;
    }

    /**
     * Get all documents for an application.
     *
     * Combines documents from application and person (avoiding duplicates).
     */
    private function getDocuments(Application $app): array
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
            // Check both ocr_data and metadata (KYC controller uses metadata)
            $ocrData = $d->ocr_data ?? [];
            $metadata = $d->metadata ?? [];
            $combinedData = array_merge($ocrData, $metadata);

            // Check if document was verified by KYC from ocr_data or metadata
            $isKycFromMetadata = $d->status === 'APPROVED' && (
                ($combinedData['kyc_validated'] ?? false) ||
                ($combinedData['face_match_passed'] ?? false) ||
                ($combinedData['liveness_passed'] ?? false) ||
                ($combinedData['nubarium_validated'] ?? false) ||
                in_array($combinedData['validation_method'] ?? '', ['KYC', 'KYC_INE_OCR', 'KYC_FACE_MATCH', 'KYC_LIVENESS', 'FACE_MATCH', 'LIVENESS', 'INE_VALIDATION', 'NUBARIUM'])
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
                'metadata' => $metadata,
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

        $application = Application::where('id', $id)
            ->where('tenant_id', $this->scopedTenantId($staff))
            ->with(['person.employments', 'person.addresses', 'person.references', 'person.bankAccounts', 'person.identifications'])
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

        // Sync verification status to related model (employment, address, references, bank_accounts)
        $this->syncVerificationStatusToRelatedModel(
            $application,
            $validated['field'],
            $newStatus,
            $validated['method'] ?? null,
            $validated['rejection_reason'] ?? null,
            $staff->id
        );

        // Change application status to CORRECTIONS_PENDING if data was rejected
        $oldAppStatus = $application->status;
        $statusChanged = false;
        if ($validated['action'] === 'reject' && $application->status !== Application::STATUS_CORRECTIONS_PENDING) {
            // Only change status if transition is allowed
            if ($application->canTransitionTo(Application::STATUS_CORRECTIONS_PENDING)) {
                $application->status = Application::STATUS_CORRECTIONS_PENDING;
                $application->save();
                $statusChanged = true;
            }
        }

        $verificationAction = match ($validated['action']) {
            'verify' => 'DATA_VERIFIED',
            'reject' => 'DATA_REJECTED',
            'unverify' => 'DATA_CORRECTED',
        };
        \App\Services\ActivityRecorder::recordApplicationEvent($application, $verificationAction, [
            'from_status' => 'DATA_VERIFICATION',
            'to_status' => 'DATA_VERIFICATION',
            'notes' => match ($validated['action']) {
                'verify' => "Campo '{$validated['field']}' verificado",
                'reject' => "Campo '{$validated['field']}' rechazado: " . ($validated['rejection_reason'] ?? ''),
                'unverify' => "Verificación removida del campo '{$validated['field']}'" . (($validated['notes'] ?? null) ? ": {$validated['notes']}" : ''),
            },
            'old_values' => ['status' => $oldStatus],
            'new_values' => ['status' => $newStatus],
            'metadata' => [
                'field' => $validated['field'],
                'verification_action' => $validated['action'],
                'method' => $validated['method'] ?? null,
                'rejection_reason' => $validated['rejection_reason'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ],
        ]);

        if ($statusChanged) {
            \App\Services\ActivityRecorder::recordApplicationEvent($application, 'APPLICATION_UPDATED', [
                'from_status' => $oldAppStatus,
                'to_status' => Application::STATUS_CORRECTIONS_PENDING,
                'notes' => "Solicitud movida a correcciones pendientes por rechazo de campo '{$validated['field']}'",
                'old_values' => ['status' => $oldAppStatus],
                'new_values' => ['status' => Application::STATUS_CORRECTIONS_PENDING],
                'metadata' => [
                    'trigger' => 'data_rejected',
                    'field' => $validated['field'],
                ],
            ]);
        }

        // If verifying (not rejecting), check if all verifications are complete
        // to auto-advance status from CORRECTIONS_PENDING/DOCS_PENDING to IN_REVIEW
        if ($validated['action'] === 'verify') {
            $autoAdvanced = $this->checkAndAdvanceStatus($application, $staff);
            if ($autoAdvanced) {
                $statusChanged = true;
            }
        }

        return $this->success([
            'application_status_changed' => $statusChanged,
            'new_application_status' => $application->status,
        ], 'Dato ' . ($validated['action'] === 'verify' ? 'verificado' : ($validated['action'] === 'reject' ? 'rechazado' : 'actualizado')) . '.');
    }

    // =========================================================================
    // Activity Feed (reemplaza /audit-logs y /api-logs viejos)
    // =========================================================================

    /**
     * Feed unificado de actividad de una solicitud.
     *
     * GET /v2/staff/applications/{id}/activity
     *
     * Combina application_status_history + audit_logs + api_logs en una sola
     * linea de tiempo cronologica, paginada con cursor. Reemplaza los
     * endpoints viejos /audit-logs y /api-logs.
     *
     * Filtros:
     *   ?cursor=<base64>        cursor temporal para paginar (desc)
     *   ?kind=event|audit|api   limita a una sola fuente (default: todas)
     *   ?q=<texto>              busqueda libre en title/summary/actor
     *   ?per_page=50            tamaño de pagina (max 200)
     *   ?include_http=1         incluye HTTP_REQUEST en audits (default: no)
     */
    public function activity(Request $request, string $id, \App\Services\ActivityFeedService $feed): JsonResponse
    {
        /** @var StaffAccount $staff */
        $staff = $request->user();

        $application = Application::where('id', $id)
            ->where('tenant_id', $this->scopedTenantId($staff))
            ->with('person')
            ->first();

        if (! $application) {
            return $this->notFound('Solicitud no encontrada.');
        }

        $result = $feed->forApplication($application, [
            'cursor' => $request->query('cursor'),
            'kind' => $request->query('kind'),
            'q' => $request->query('q'),
            'per_page' => $request->query('per_page'),
            'include_http' => filter_var($request->query('include_http', false), FILTER_VALIDATE_BOOLEAN),
        ]);

        return $this->success($result);
    }

    // =========================================================================
    // Helper Methods
    // =========================================================================

    /**
     * Find a document by ID within an application's scope.
     *
     * Searches in both application documents and person's documents.
     */
    private function findApplicationDocument(Application $application, string $documentId): ?\App\Models\Document
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
