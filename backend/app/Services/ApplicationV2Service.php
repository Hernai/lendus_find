<?php

namespace App\Services;

use App\Models\ApplicantAccount;
use App\Models\ApplicationStatusHistory;
use App\Models\ApplicationV2;
use App\Models\Company;
use App\Models\Person;
use App\Models\Product;
use App\Models\StaffAccount;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ApplicationV2Service
{
    public function __construct(
        protected LoanCalculationService $loanCalculator,
        protected DocumentV2Service $documentService
    ) {}

    /**
     * Create a new application for an individual (Person).
     */
    public function createForPerson(
        Tenant $tenant,
        Person $person,
        Product $product,
        array $loanData,
        ?ApplicantAccount $submittedBy = null
    ): ApplicationV2 {
        return $this->create(
            $tenant,
            $product,
            ApplicationV2::TYPE_INDIVIDUAL,
            $person->id,
            null,
            $loanData,
            $submittedBy
        );
    }

    /**
     * Create a new application for a company.
     */
    public function createForCompany(
        Tenant $tenant,
        Company $company,
        Product $product,
        array $loanData,
        ?ApplicantAccount $submittedBy = null,
        ?string $submittedByMemberId = null
    ): ApplicationV2 {
        return $this->create(
            $tenant,
            $product,
            ApplicationV2::TYPE_COMPANY,
            null,
            $company->id,
            $loanData,
            $submittedBy,
            $submittedByMemberId
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
        ?string $companyId,
        array $loanData,
        ?ApplicantAccount $submittedBy = null,
        ?string $submittedByMemberId = null
    ): ApplicationV2 {
        // Calculate loan terms
        $calculation = $this->loanCalculator->calculateSimulation(
            $loanData['amount'],
            $loanData['term_months'],
            $loanData['frequency'] ?? 'MONTHLY',
            $product->annual_rate,
            $product->opening_commission_rate ?? 0
        );

        return ApplicationV2::create([
            'tenant_id' => $tenant->id,
            'product_id' => $product->id,
            'applicant_type' => $applicantType,
            'person_id' => $personId,
            'company_id' => $companyId,
            'submitted_by_account_id' => $submittedBy?->id,
            'submitted_by_member_id' => $submittedByMemberId,
            'requested_amount' => $loanData['amount'],
            'requested_term_months' => $loanData['term_months'],
            'purpose' => $loanData['purpose'] ?? null,
            'purpose_description' => $loanData['purpose_description'] ?? null,
            'interest_rate' => $product->annual_rate,
            'monthly_payment' => $calculation['payment_amount'],
            'total_interest' => $calculation['total_interest'],
            'total_amount' => $calculation['total_to_pay'],
            'cat' => $calculation['cat'] ?? null,
            'status' => ApplicationV2::STATUS_DRAFT,
            'expires_at' => now()->addDays(30), // Draft applications expire after 30 days
        ]);
    }

    /**
     * Update loan terms for a draft application.
     */
    public function updateLoanTerms(ApplicationV2 $application, array $loanData): ApplicationV2
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

    /**
     * Submit an application for review.
     */
    public function submit(
        ApplicationV2 $application,
        ApplicantAccount $submittedBy,
        ?string $ip = null,
        ?string $device = null
    ): ApplicationV2 {
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
    public function validateForSubmission(ApplicationV2 $application): array
    {
        $errors = [];

        // Validate applicant exists
        if ($application->is_individual) {
            if (!$application->person) {
                $errors[] = 'Person data is missing';
            }
        } else {
            if (!$application->company) {
                $errors[] = 'Company data is missing';
            }
        }

        // Validate purpose
        if (empty($application->purpose)) {
            $errors[] = 'Loan purpose is required';
        }

        // Validate required documents
        $product = $application->product;
        $requiredDocs = $product->required_documents ?? [];

        if (!empty($requiredDocs)) {
            $documentable = $application->is_individual ? $application->person : $application->company;

            if ($documentable) {
                $missingDocs = $this->documentService->getMissingRequired($documentable, $requiredDocs);
                if (!empty($missingDocs)) {
                    $errors[] = 'Missing required documents: ' . implode(', ', $missingDocs);
                }
            }
        }

        return $errors;
    }

    /**
     * Create a snapshot of applicant data.
     */
    protected function createSnapshot(ApplicationV2 $application): array
    {
        if ($application->is_individual) {
            $person = $application->person;
            return [
                'type' => 'individual',
                'person_id' => $person->id,
                'full_name' => $person->full_name,
                'curp' => $person->curp,
                'rfc' => $person->rfc,
                'birth_date' => $person->birth_date?->format('Y-m-d'),
                'nationality' => $person->nationality,
                'current_address' => $person->currentAddress()?->toArray(),
                'current_employment' => $person->currentEmployment()?->toArray(),
            ];
        }

        $company = $application->company;
        return [
            'type' => 'company',
            'company_id' => $company->id,
            'legal_name' => $company->legal_name,
            'trade_name' => $company->trade_name,
            'rfc' => $company->rfc,
            'fiscal_regime' => $company->fiscal_regime,
            'legal_representative' => $company->legalRepresentative()?->person?->full_name,
        ];
    }

    /**
     * Assign application to a staff member.
     */
    public function assign(
        ApplicationV2 $application,
        StaffAccount $assignee,
        StaffAccount $assignedBy
    ): ApplicationV2 {
        $application->assignTo($assignee->id, $assignedBy->id);

        return $application->fresh();
    }

    /**
     * Change application status.
     */
    public function changeStatus(
        ApplicationV2 $application,
        string $newStatus,
        StaffAccount $changedBy,
        ?string $notes = null
    ): ApplicationV2 {
        $application->changeStatus($newStatus, $changedBy->id, StaffAccount::class, $notes);

        return $application->fresh();
    }

    /**
     * Approve application.
     */
    public function approve(
        ApplicationV2 $application,
        StaffAccount $approvedBy,
        ?float $amount = null,
        ?int $termMonths = null,
        ?float $interestRate = null,
        ?string $notes = null
    ): ApplicationV2 {
        $application->approve($approvedBy->id, $amount, $termMonths, $interestRate, $notes);

        return $application->fresh();
    }

    /**
     * Reject application.
     */
    public function reject(
        ApplicationV2 $application,
        StaffAccount $rejectedBy,
        string $reason,
        ?string $notes = null
    ): ApplicationV2 {
        $application->reject($rejectedBy->id, $reason, $notes);

        return $application->fresh();
    }

    /**
     * Send counter offer.
     */
    public function sendCounterOffer(
        ApplicationV2 $application,
        StaffAccount $staff,
        array $offer,
        ?string $reason = null
    ): ApplicationV2 {
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
        ApplicationV2 $application,
        ApplicantAccount $account,
        bool $accepted
    ): ApplicationV2 {
        if (!$application->has_counter_offer) {
            throw new \InvalidArgumentException('No counter offer to respond to');
        }

        $application->respondToCounterOffer($accepted, $account->id);

        return $application->fresh();
    }

    /**
     * Cancel application.
     */
    public function cancel(
        ApplicationV2 $application,
        string $cancelledById,
        string $cancelledByType,
        ?string $reason = null
    ): ApplicationV2 {
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
        ApplicationV2 $application,
        string $externalId,
        string $system,
        ?array $syncData = null
    ): ApplicationV2 {
        $application->markSynced($externalId, $system, $syncData);

        return $application->fresh();
    }

    /**
     * Update verification checklist.
     */
    public function updateVerification(ApplicationV2 $application, array $checks): ApplicationV2
    {
        $application->updateVerification($checks);

        return $application->fresh();
    }

    /**
     * Set risk assessment.
     */
    public function setRiskAssessment(
        ApplicationV2 $application,
        string $level,
        ?array $data = null
    ): ApplicationV2 {
        $application->setRiskAssessment($level, $data);

        return $application->fresh();
    }

    /**
     * Find an application by ID for a specific tenant.
     */
    public function findByIdForTenant(string $id, Tenant $tenant, array $relations = []): ?ApplicationV2
    {
        $query = ApplicationV2::where('id', $id)
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
    public function findByIdForTenantOrFail(string $id, Tenant $tenant, array $relations = []): ApplicationV2
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
        $query = ApplicationV2::forPerson($person->id)
            ->with(['product', 'assignedTo'])
            ->orderByDesc('created_at');

        if ($status) {
            $query->status($status);
        }

        return $query->get();
    }

    /**
     * Get applications for a company.
     */
    public function getForCompany(Company $company, ?string $status = null): Collection
    {
        $query = ApplicationV2::forCompany($company->id)
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
        $query = ApplicationV2::assignedToStaff($staff->id)
            ->with(['person', 'company', 'product'])
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
        $query = ApplicationV2::where('tenant_id', $tenant->id)
            ->unassigned()
            ->whereIn('status', [
                ApplicationV2::STATUS_SUBMITTED,
                ApplicationV2::STATUS_IN_REVIEW,
            ])
            ->with(['person', 'company', 'product'])
            ->orderBy('submitted_at');

        if ($status) {
            $query->status($status);
        }

        return $query->get();
    }

    /**
     * Get applications list with filters and pagination.
     */
    public function list(
        Tenant $tenant,
        array $filters = [],
        int $perPage = 20
    ): LengthAwarePaginator {
        $query = ApplicationV2::where('tenant_id', $tenant->id)
            ->with(['person', 'company', 'product', 'assignedTo']);

        // Apply filters
        if (!empty($filters['status'])) {
            $query->status($filters['status']);
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
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->whereHas('person', function ($pq) use ($search) {
                    $pq->where('first_name', 'ILIKE', "%{$search}%")
                        ->orWhere('last_name', 'ILIKE', "%{$search}%")
                        ->orWhere('curp', 'ILIKE', "%{$search}%");
                })->orWhereHas('company', function ($cq) use ($search) {
                    $cq->where('legal_name', 'ILIKE', "%{$search}%")
                        ->orWhere('trade_name', 'ILIKE', "%{$search}%")
                        ->orWhere('rfc', 'ILIKE', "%{$search}%");
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

    /**
     * Get application statistics for dashboard.
     */
    public function getStatistics(Tenant $tenant, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $query = ApplicationV2::where('tenant_id', $tenant->id);

        if ($dateFrom) {
            $query->where('created_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->where('created_at', '<=', $dateTo);
        }

        $total = (clone $query)->count();
        $draft = (clone $query)->draft()->count();
        $submitted = (clone $query)->submitted()->count();
        $inReview = (clone $query)->inReview()->count();
        $approved = (clone $query)->approved()->count();
        $rejected = (clone $query)->rejected()->count();

        $totalAmount = (clone $query)->sum('requested_amount');
        $approvedAmount = (clone $query)->approved()->sum('approved_amount');

        $byRisk = (clone $query)
            ->whereNotNull('risk_level')
            ->selectRaw('risk_level, count(*) as count')
            ->groupBy('risk_level')
            ->pluck('count', 'risk_level')
            ->toArray();

        return [
            'total' => $total,
            'by_status' => [
                'draft' => $draft,
                'submitted' => $submitted,
                'in_review' => $inReview,
                'approved' => $approved,
                'rejected' => $rejected,
            ],
            'amounts' => [
                'total_requested' => $totalAmount,
                'total_approved' => $approvedAmount,
            ],
            'by_risk' => $byRisk,
            'conversion_rate' => $total > 0 ? round(($approved / $total) * 100, 2) : 0,
        ];
    }

    /**
     * Get status history for an application.
     */
    public function getStatusHistory(ApplicationV2 $application): Collection
    {
        return $application->statusHistory()->get();
    }
}
