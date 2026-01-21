<?php

namespace App\Services;

use App\Models\ApplicantAccount;
use App\Models\Application;
use App\Models\Person;
use App\Models\Product;
use App\Models\StaffAccount;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ApplicationService
{
    // =====================================================
    // Constructor
    // =====================================================

    public function __construct(
        protected LoanCalculationService $loanCalculator,
        protected DocumentService $documentService
    ) {}

    // =====================================================
    // Application Creation
    // =====================================================

    /**
     * Create a new application for an individual (Person).
     */
    public function createForPerson(
        Tenant $tenant,
        Person $person,
        Product $product,
        array $loanData,
        ?ApplicantAccount $submittedBy = null
    ): Application {
        return $this->create(
            $tenant,
            $product,
            Application::TYPE_INDIVIDUAL,
            $person->id,
            $loanData,
            $submittedBy
        );
    }

    /**
     * Create a new application.
     */
    protected function create(
        Tenant $tenant,
        Product $product,
        string $applicantType,
        ?string $personId,
        array $loanData,
        ?ApplicantAccount $submittedBy = null
    ): Application {
        // Calculate loan terms
        $calculation = $this->loanCalculator->calculateSimulation(
            $loanData['amount'],
            $loanData['term_months'],
            $loanData['frequency'] ?? 'MONTHLY',
            $product->annual_rate,
            $product->opening_commission_rate ?? 0
        );

        return Application::create([
            'tenant_id' => $tenant->id,
            'product_id' => $product->id,
            'applicant_type' => $applicantType,
            'person_id' => $personId,
            'submitted_by_account_id' => $submittedBy?->id,
            'requested_amount' => $loanData['amount'],
            'requested_term_months' => $loanData['term_months'],
            'purpose' => $loanData['purpose'] ?? null,
            'purpose_description' => $loanData['purpose_description'] ?? null,
            'interest_rate' => $product->annual_rate,
            'monthly_payment' => $calculation['payment_amount'],
            'total_interest' => $calculation['total_interest'],
            'total_amount' => $calculation['total_to_pay'],
            'cat' => $calculation['cat'] ?? null,
            'status' => Application::STATUS_DRAFT,
            'expires_at' => now()->addDays(30), // Draft applications expire after 30 days
        ]);
    }

    // =====================================================
    // Application Updates
    // =====================================================

    /**
     * Update loan terms for a draft application.
     */
    public function updateLoanTerms(Application $application, array $loanData): Application
    {
        if (!$application->canBeEdited()) {
            throw new \InvalidArgumentException('Application cannot be edited in current status');
        }

        $product = $application->product;
        $amount = $loanData['amount'] ?? $application->requested_amount;
        $termMonths = $loanData['term_months'] ?? $application->requested_term_months;
        $frequency = $loanData['frequency'] ?? 'MONTHLY';

        $calculation = $this->loanCalculator->calculateSimulation(
            $amount,
            $termMonths,
            $frequency,
            $product->annual_rate,
            $product->opening_commission_rate ?? 0
        );

        $application->update([
            'requested_amount' => $amount,
            'requested_term_months' => $termMonths,
            'monthly_payment' => $calculation['payment_amount'],
            'total_interest' => $calculation['total_interest'],
            'total_amount' => $calculation['total_to_pay'],
            'cat' => $calculation['cat'] ?? null,
            'purpose' => $loanData['purpose'] ?? $application->purpose,
            'purpose_description' => $loanData['purpose_description'] ?? $application->purpose_description,
        ]);

        return $application->fresh();
    }

    // =====================================================
    // Submission & Validation
    // =====================================================

    /**
     * Submit an application for review.
     */
    public function submit(
        Application $application,
        ApplicantAccount $submittedBy,
        ?string $ip = null,
        ?string $device = null
    ): Application {
        if (!$application->canBeSubmitted()) {
            throw new \InvalidArgumentException('Application cannot be submitted in current status');
        }

        // Validate completeness
        $errors = $this->validateForSubmission($application);
        if (!empty($errors)) {
            throw new \InvalidArgumentException(
                'Application is incomplete: ' . implode(', ', $errors)
            );
        }

        // Create snapshot of applicant data at submission time
        $snapshotData = $this->createSnapshot($application);

        DB::transaction(function () use ($application, $submittedBy, $ip, $device, $snapshotData) {
            $application->update([
                'snapshot_data' => $snapshotData,
                'submitted_by_account_id' => $submittedBy->id,
            ]);

            $application->submit($submittedBy->id, $ip, $device);
        });

        return $application->fresh();
    }

    /**
     * Validate application for submission.
     */
    public function validateForSubmission(Application $application): array
    {
        $errors = [];

        // Validate applicant exists (only individual/person applications supported)
        if (!$application->person) {
            $errors[] = 'Person data is missing';
        }

        // Validate purpose
        if (empty($application->purpose)) {
            $errors[] = 'Loan purpose is required';
        }

        // Validate required documents
        $product = $application->product;
        $requiredDocs = $product->required_documents ?? [];

        if (!empty($requiredDocs) && $application->person) {
            $missingDocs = $this->documentService->getMissingRequired($application->person, $requiredDocs);
            if (!empty($missingDocs)) {
                $errors[] = 'Missing required documents: ' . implode(', ', $missingDocs);
            }
        }

        return $errors;
    }

    /**
     * Create a snapshot of applicant data.
     */
    protected function createSnapshot(Application $application): array
    {
        // Only individual/person applications are supported
        $person = $application->person;
        return [
            'type' => 'individual',
            'person_id' => $person->id,
            'full_name' => $person->full_name,
            'curp' => $person->curp,
            'rfc' => $person->rfc,
            'birth_date' => $person->birth_date?->format('Y-m-d'),
            'nationality' => $person->nationality,
            // Access as property to get the loaded model, not the relation
            'current_address' => $person->currentHomeAddress?->toArray(),
            'current_employment' => $person->currentEmployment?->toArray(),
        ];
    }

    // =====================================================
    // Staff Actions
    // =====================================================

    /**
     * Assign application to a staff member.
     */
    public function assign(
        Application $application,
        StaffAccount $assignee,
        StaffAccount $assignedBy
    ): Application {
        $application->assignTo($assignee->id, $assignedBy->id);

        return $application->fresh();
    }

    /**
     * Change application status.
     */
    public function changeStatus(
        Application $application,
        string $newStatus,
        StaffAccount $changedBy,
        ?string $notes = null
    ): Application {
        $application->changeStatus($newStatus, $changedBy->id, StaffAccount::class, $notes);

        return $application->fresh();
    }

    /**
     * Approve application.
     */
    public function approve(
        Application $application,
        StaffAccount $approvedBy,
        ?float $amount = null,
        ?int $termMonths = null,
        ?float $interestRate = null,
        ?string $notes = null
    ): Application {
        $application->approve($approvedBy->id, $amount, $termMonths, $interestRate, $notes);

        return $application->fresh();
    }

    /**
     * Reject application.
     */
    public function reject(
        Application $application,
        StaffAccount $rejectedBy,
        string $reason,
        ?string $notes = null
    ): Application {
        $application->reject($rejectedBy->id, $reason, $notes);

        return $application->fresh();
    }

    // =====================================================
    // Counter Offers
    // =====================================================

    /**
     * Send counter offer.
     */
    public function sendCounterOffer(
        Application $application,
        StaffAccount $staff,
        array $offer,
        ?string $reason = null
    ): Application {
        // Validate offer has required fields
        if (!isset($offer['amount']) || !isset($offer['term_months'])) {
            throw new \InvalidArgumentException('Counter offer must include amount and term_months');
        }

        // Calculate payment for counter offer
        $product = $application->product;
        $interestRate = $offer['interest_rate'] ?? $product->annual_rate;

        $calculation = $this->loanCalculator->calculateSimulation(
            $offer['amount'],
            $offer['term_months'],
            'MONTHLY',
            $interestRate,
            $product->opening_commission_rate ?? 0
        );

        $offer['monthly_payment'] = $calculation['payment_amount'];
        $offer['total_amount'] = $calculation['total_to_pay'];
        $offer['interest_rate'] = $interestRate;

        $application->sendCounterOffer($staff->id, $offer, $reason);

        return $application->fresh();
    }

    /**
     * Respond to counter offer.
     */
    public function respondToCounterOffer(
        Application $application,
        ApplicantAccount $account,
        bool $accepted
    ): Application {
        if (!$application->has_counter_offer) {
            throw new \InvalidArgumentException('No counter offer to respond to');
        }

        $application->respondToCounterOffer($accepted, $account->id);

        return $application->fresh();
    }

    // =====================================================
    // Application Cancellation & Sync
    // =====================================================

    /**
     * Cancel application.
     */
    public function cancel(
        Application $application,
        string $cancelledById,
        string $cancelledByType,
        ?string $reason = null
    ): Application {
        if (!$application->canBeCancelled()) {
            throw new \InvalidArgumentException('Application cannot be cancelled in current status');
        }

        $application->cancel($cancelledById, $cancelledByType, $reason);

        return $application->fresh();
    }

    /**
     * Mark application as synced to external system.
     */
    public function markSynced(
        Application $application,
        string $externalId,
        string $system,
        ?array $syncData = null
    ): Application {
        $application->markSynced($externalId, $system, $syncData);

        return $application->fresh();
    }

    // =====================================================
    // Verification & Risk
    // =====================================================

    /**
     * Update verification checklist.
     */
    public function updateVerification(Application $application, array $checks): Application
    {
        $application->updateVerification($checks);

        return $application->fresh();
    }

    /**
     * Set risk assessment.
     */
    public function setRiskAssessment(
        Application $application,
        string $level,
        ?array $data = null
    ): Application {
        $application->setRiskAssessment($level, $data);

        return $application->fresh();
    }

    // =====================================================
    // Query Methods
    // =====================================================

    /**
     * Find an application by ID for a specific tenant.
     */
    public function findByIdForTenant(string $id, Tenant $tenant, array $relations = []): ?Application
    {
        $query = Application::where('id', $id)
            ->where('tenant_id', $tenant->id);

        if (!empty($relations)) {
            $query->with($relations);
        }

        return $query->first();
    }

    /**
     * Find an application by ID for a specific tenant or fail.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function findByIdForTenantOrFail(string $id, Tenant $tenant, array $relations = []): Application
    {
        $application = $this->findByIdForTenant($id, $tenant, $relations);

        if (!$application) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException(
                "Application [{$id}] not found for tenant [{$tenant->id}]"
            );
        }

        return $application;
    }

    /**
     * Get applications for a person.
     */
    public function getForPerson(Person $person, ?string $status = null): Collection
    {
        $query = Application::forPerson($person->id)
            ->with(['product', 'assignedTo'])
            ->orderByDesc('created_at');

        if ($status) {
            $query->status($status);
        }

        return $query->get();
    }

    /**
     * Get applications assigned to staff.
     */
    public function getAssignedTo(StaffAccount $staff, ?string $status = null): Collection
    {
        $query = Application::assignedToStaff($staff->id)
            ->with(['person', 'product'])
            ->orderByDesc('submitted_at');

        if ($status) {
            $query->status($status);
        }

        return $query->get();
    }

    /**
     * Get unassigned applications for tenant.
     */
    public function getUnassigned(Tenant $tenant, ?string $status = null): Collection
    {
        $query = Application::where('tenant_id', $tenant->id)
            ->unassigned()
            ->whereIn('status', [
                Application::STATUS_SUBMITTED,
                Application::STATUS_IN_REVIEW,
            ])
            ->with(['person', 'product'])
            ->orderBy('submitted_at');

        if ($status) {
            $query->status($status);
        }

        return $query->get();
    }

    // =====================================================
    // Listing & Filtering
    // =====================================================

    /**
     * Get Kanban board data with applications grouped by status.
     *
     * Returns applications organized by column/status with a limit per column.
     * More efficient than fetching all applications for Kanban views.
     */
    public function getBoardData(
        Tenant $tenant,
        array $columns,
        int $limitPerColumn = 15,
        ?string $assignedTo = null,
        string $sortBy = 'created_at',
        string $sortDir = 'desc'
    ): array {
        $statusLabels = Application::statuses();
        $result = [
            'columns' => [],
            'totals' => [
                'all' => 0,
                'by_status' => [],
            ],
        ];

        // Get counts for all requested columns in one query
        $countsQuery = Application::where('tenant_id', $tenant->id)
            ->whereIn('status', $columns);

        if ($assignedTo) {
            $countsQuery->where('assigned_to', $assignedTo);
        }

        $counts = (clone $countsQuery)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $result['totals']['all'] = array_sum($counts);
        $result['totals']['by_status'] = $counts;

        // Fetch limited items per column
        foreach ($columns as $status) {
            $query = Application::where('tenant_id', $tenant->id)
                ->where('status', $status)
                ->with(['person', 'product', 'assignedTo']);

            if ($assignedTo) {
                $query->where('assigned_to', $assignedTo);
            }

            $items = $query
                ->orderBy($sortBy, $sortDir)
                ->limit($limitPerColumn)
                ->get()
                ->map(fn($app) => $this->formatBoardItem($app));

            $result['columns'][] = [
                'status' => $status,
                'status_label' => $statusLabels[$status] ?? $status,
                'count' => $counts[$status] ?? 0,
                'items' => $items,
                'has_more' => ($counts[$status] ?? 0) > $limitPerColumn,
            ];
        }

        return $result;
    }

    /**
     * Format application for board/Kanban view (minimal data).
     */
    protected function formatBoardItem(Application $app): array
    {
        return [
            'id' => $app->id,
            'folio' => $app->folio,
            'status' => $app->status,
            'applicant_type' => $app->applicant_type,
            'applicant_name' => $app->is_individual
                ? $app->person?->full_name
                : null, // Company applications not supported
            'product' => $app->product ? [
                'id' => $app->product->id,
                'name' => $app->product->name,
            ] : null,
            'requested_amount' => $app->requested_amount,
            'assigned_to' => $app->assignedTo ? [
                'id' => $app->assignedTo->id,
                'name' => $app->assignedTo->profile?->full_name ?? $app->assignedTo->email,
            ] : null,
            'created_at' => $app->created_at?->toIso8601String(),
            'submitted_at' => $app->submitted_at?->toIso8601String(),
        ];
    }

    /**
     * Get applications list with filters and pagination.
     */
    public function list(
        Tenant $tenant,
        array $filters = [],
        int $perPage = 20
    ): LengthAwarePaginator {
        $query = Application::where('tenant_id', $tenant->id)
            ->with(['person.account.phoneIdentity', 'product', 'assignedTo']);

        // Apply filters
        if (!empty($filters['status'])) {
            $statuses = is_array($filters['status']) ? $filters['status'] : [$filters['status']];
            $query->whereIn('status', $statuses);
        }

        if (!empty($filters['applicant_type'])) {
            if ($filters['applicant_type'] === 'individual') {
                $query->individuals();
            } else {
                $query->companies();
            }
        }

        if (!empty($filters['assigned_to'])) {
            $query->assignedToStaff($filters['assigned_to']);
        }

        if (!empty($filters['unassigned'])) {
            $query->unassigned();
        }

        // Handle assignment filter (all, assigned, unassigned)
        if (!empty($filters['assignment'])) {
            if ($filters['assignment'] === 'assigned') {
                $query->whereNotNull('assigned_to');
            } elseif ($filters['assignment'] === 'unassigned') {
                $query->unassigned();
            }
            // 'all' doesn't need any filter
        }

        if (!empty($filters['risk_level'])) {
            $query->riskLevel($filters['risk_level']);
        }

        if (!empty($filters['product_id'])) {
            $query->where('product_id', $filters['product_id']);
        }

        if (!empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        if (!empty($filters['search'])) {
            // Sanitize search input: escape SQL wildcards and limit length
            $search = str_replace(['%', '_'], ['\\%', '\\_'], $filters['search']);
            $search = mb_substr($search, 0, 100);
            $query->where(function ($q) use ($search) {
                // Search in persons table (name fields)
                $q->whereHas('person', function ($pq) use ($search) {
                    $pq->where('first_name', 'ILIKE', "%{$search}%")
                        ->orWhere('last_name_1', 'ILIKE', "%{$search}%")
                        ->orWhere('last_name_2', 'ILIKE', "%{$search}%")
                        // Also search in person_identifications for CURP/RFC
                        ->orWhereHas('identifications', function ($iq) use ($search) {
                            $iq->where('identifier_value', 'ILIKE', "%{$search}%")
                                ->where('is_current', true);
                        });
                });
            });
        }

        // Sorting with validation to prevent SQL injection
        $allowedSortColumns = [
            'created_at', 'submitted_at', 'requested_amount',
            'status', 'decision_at', 'updated_at',
        ];
        $sortBy = in_array($filters['sort_by'] ?? '', $allowedSortColumns)
            ? $filters['sort_by']
            : 'created_at';
        $sortDir = in_array(strtolower($filters['sort_dir'] ?? ''), ['asc', 'desc'])
            ? $filters['sort_dir']
            : 'desc';
        $query->orderBy($sortBy, $sortDir);

        return $query->paginate($perPage);
    }

    // =====================================================
    // Statistics & History
    // =====================================================

    /**
     * Get application statistics for dashboard.
     */
    public function getStatistics(Tenant $tenant, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $query = Application::where('tenant_id', $tenant->id);

        if ($dateFrom) {
            $query->where('created_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->where('created_at', '<=', $dateTo);
        }

        $total = (clone $query)->count();

        // Count by each status (lowercase keys for consistency)
        $byStatus = [];
        foreach (array_keys(Application::statuses()) as $status) {
            $byStatus[strtolower($status)] = (clone $query)->where('status', $status)->count();
        }

        // Pending review = submitted
        $pendingReview = $byStatus['submitted'] ?? 0;

        // Pending documents = docs_pending
        $pendingDocuments = $byStatus['docs_pending'] ?? 0;

        // Approved/rejected today
        $today = now()->startOfDay();
        $approvedToday = (clone $query)
            ->where('status', Application::STATUS_APPROVED)
            ->where('status_changed_at', '>=', $today)
            ->count();
        $rejectedToday = (clone $query)
            ->where('status', Application::STATUS_REJECTED)
            ->where('status_changed_at', '>=', $today)
            ->count();

        // Average processing time (from SUBMITTED to APPROVED/REJECTED)
        $avgProcessingTime = 0;
        $processedApps = (clone $query)
            ->whereIn('status', [Application::STATUS_APPROVED, Application::STATUS_REJECTED])
            ->whereNotNull('submitted_at')
            ->whereNotNull('status_changed_at')
            ->get(['submitted_at', 'status_changed_at']);

        if ($processedApps->count() > 0) {
            $totalHours = $processedApps->sum(function ($app) {
                $submitted = \Carbon\Carbon::parse($app->submitted_at);
                $changed = \Carbon\Carbon::parse($app->status_changed_at);
                return $changed->diffInHours($submitted);
            });
            $avgProcessingTime = $totalHours / $processedApps->count();
        }

        return [
            'total' => $total,
            'by_status' => $byStatus,
            'pending_review' => $pendingReview,
            'pending_documents' => $pendingDocuments,
            'approved_today' => $approvedToday,
            'rejected_today' => $rejectedToday,
            'average_processing_time_hours' => round((float) $avgProcessingTime, 2),
        ];
    }

    /**
     * Get status history for an application.
     */
    public function getStatusHistory(Application $application): Collection
    {
        return $application->statusHistory()->get();
    }
}
