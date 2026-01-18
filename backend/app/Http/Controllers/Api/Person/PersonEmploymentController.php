<?php

namespace App\Http\Controllers\Api\Person;

use App\Http\Controllers\Controller;
use App\Http\Resources\Person\PersonEmploymentResource;
use App\Models\Person;
use App\Models\PersonEmployment;
use App\Services\Person\PersonEmploymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PersonEmploymentController extends Controller
{
    public function __construct(
        protected PersonEmploymentService $employmentService
    ) {}

    /**
     * List employments for a person.
     */
    public function index(Person $person): AnonymousResourceCollection
    {
        $employments = $this->employmentService->getForPerson($person->id);

        return PersonEmploymentResource::collection($employments);
    }

    /**
     * Create a new employment record.
     */
    public function store(Request $request, Person $person): JsonResponse
    {
        $validated = $request->validate([
            'employment_type' => 'required|string|in:EMPLOYEE,SELF_EMPLOYED,FREELANCE,BUSINESS_OWNER,RETIRED,UNEMPLOYED,STUDENT,OTHER',
            'employer_name' => 'required|string|max:200',
            'employer_rfc' => 'nullable|string|max:13',
            'employer_phone' => 'nullable|string|max:15',
            'employer_address' => 'nullable|string|max:500',
            'job_title' => 'nullable|string|max:100',
            'department' => 'nullable|string|max:100',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after:start_date',
            'is_current' => 'nullable|boolean',
            'contract_type' => 'nullable|string|in:INDEFINITE,FIXED_TERM,TEMPORARY,FREELANCE,INTERNSHIP,OTHER',
            'monthly_income' => 'nullable|numeric|min:0',
            'payment_frequency' => 'nullable|string|in:WEEKLY,BIWEEKLY,MONTHLY',
            'years_employed' => 'nullable|integer|min:0',
            'months_employed' => 'nullable|integer|min:0|max:11',
        ]);

        $employment = $this->employmentService->create($person, $validated);

        return (new PersonEmploymentResource($employment))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Show an employment record.
     */
    public function show(Person $person, PersonEmployment $employment): PersonEmploymentResource
    {
        return new PersonEmploymentResource($employment);
    }

    /**
     * Update an employment record.
     */
    public function update(Request $request, Person $person, PersonEmployment $employment): PersonEmploymentResource
    {
        $validated = $request->validate([
            'employment_type' => 'sometimes|string|in:EMPLOYEE,SELF_EMPLOYED,FREELANCE,BUSINESS_OWNER,RETIRED,UNEMPLOYED,STUDENT,OTHER',
            'employer_name' => 'sometimes|string|max:200',
            'employer_rfc' => 'nullable|string|max:13',
            'employer_phone' => 'nullable|string|max:15',
            'employer_address' => 'nullable|string|max:500',
            'job_title' => 'nullable|string|max:100',
            'department' => 'nullable|string|max:100',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'contract_type' => 'nullable|string|in:INDEFINITE,FIXED_TERM,TEMPORARY,FREELANCE,INTERNSHIP,OTHER',
            'monthly_income' => 'nullable|numeric|min:0',
            'payment_frequency' => 'nullable|string|in:WEEKLY,BIWEEKLY,MONTHLY',
            'years_employed' => 'nullable|integer|min:0',
            'months_employed' => 'nullable|integer|min:0|max:11',
        ]);

        $employment = $this->employmentService->update($employment, $validated);

        return new PersonEmploymentResource($employment);
    }

    /**
     * Delete an employment record.
     */
    public function destroy(Person $person, PersonEmployment $employment): JsonResponse
    {
        $this->employmentService->delete($employment);

        return response()->json(['message' => 'Employment record deleted successfully']);
    }

    /**
     * Get current employment.
     */
    public function current(Person $person): JsonResponse
    {
        $employment = $this->employmentService->getCurrent($person->id);

        if (!$employment) {
            return response()->json(['data' => null, 'found' => false]);
        }

        return response()->json([
            'data' => new PersonEmploymentResource($employment),
            'found' => true
        ]);
    }

    /**
     * Set current employment for a person.
     */
    public function setCurrentEmployment(Request $request, Person $person): PersonEmploymentResource
    {
        $validated = $request->validate([
            'employment_type' => 'required|string|in:EMPLOYEE,SELF_EMPLOYED,FREELANCE,BUSINESS_OWNER,RETIRED,UNEMPLOYED,STUDENT,OTHER',
            'employer_name' => 'required|string|max:200',
            'employer_rfc' => 'nullable|string|max:13',
            'employer_phone' => 'nullable|string|max:15',
            'employer_address' => 'nullable|string|max:500',
            'job_title' => 'nullable|string|max:100',
            'department' => 'nullable|string|max:100',
            'start_date' => 'nullable|date',
            'contract_type' => 'nullable|string|in:INDEFINITE,FIXED_TERM,TEMPORARY,FREELANCE,INTERNSHIP,OTHER',
            'monthly_income' => 'nullable|numeric|min:0',
            'payment_frequency' => 'nullable|string|in:WEEKLY,BIWEEKLY,MONTHLY',
            'years_employed' => 'nullable|integer|min:0',
            'months_employed' => 'nullable|integer|min:0|max:11',
        ]);

        $employment = $this->employmentService->setCurrentEmployment($person, $validated);

        return new PersonEmploymentResource($employment);
    }

    /**
     * Verify an employment record.
     */
    public function verify(Request $request, Person $person, PersonEmployment $employment): PersonEmploymentResource
    {
        $validated = $request->validate([
            'method' => 'required|string|max:50',
            'notes' => 'nullable|string|max:500',
            'verification_data' => 'nullable|array',
        ]);

        $verifiedBy = $request->user()?->id;
        $employment = $this->employmentService->verify(
            $employment,
            $validated['method'],
            $verifiedBy,
            $validated['notes'] ?? null,
            $validated['verification_data'] ?? null
        );

        return new PersonEmploymentResource($employment);
    }

    /**
     * Verify income for an employment.
     */
    public function verifyIncome(Request $request, Person $person, PersonEmployment $employment): PersonEmploymentResource
    {
        $validated = $request->validate([
            'verified_income' => 'required|numeric|min:0',
            'method' => 'nullable|string|max:50',
            'verification_data' => 'nullable|array',
        ]);

        $verifiedBy = $request->user()?->id;
        $employment = $this->employmentService->verifyIncome(
            $employment,
            $validated['verified_income'],
            $validated['method'] ?? 'MANUAL',
            $verifiedBy
        );

        return new PersonEmploymentResource($employment);
    }

    /**
     * Reject an employment record.
     */
    public function reject(Request $request, Person $person, PersonEmployment $employment): PersonEmploymentResource
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $employment = $this->employmentService->reject($employment, $validated['reason']);

        return new PersonEmploymentResource($employment);
    }

    /**
     * End current employment.
     */
    public function endEmployment(Request $request, Person $person, PersonEmployment $employment): PersonEmploymentResource
    {
        $validated = $request->validate([
            'end_date' => 'nullable|date',
            'reason' => 'nullable|string|max:500',
        ]);

        $endDate = isset($validated['end_date'])
            ? \Carbon\Carbon::parse($validated['end_date'])
            : null;

        $employment = $this->employmentService->endEmployment(
            $employment,
            $endDate,
            $validated['reason'] ?? null
        );

        return new PersonEmploymentResource($employment);
    }

    /**
     * Calculate DTI ratio.
     */
    public function calculateDti(Request $request, Person $person): JsonResponse
    {
        $validated = $request->validate([
            'proposed_payment' => 'required|numeric|min:0',
        ]);

        $dti = $this->employmentService->calculateDti($person->id, $validated['proposed_payment']);

        return response()->json(['dti_ratio' => $dti]);
    }

    /**
     * Get income summary for a person.
     */
    public function incomeSummary(Person $person): JsonResponse
    {
        $summary = $this->employmentService->getIncomeSummary($person->id);

        return response()->json(['data' => $summary]);
    }

    /**
     * Check if person has verified current employment.
     */
    public function hasVerifiedCurrent(Person $person): JsonResponse
    {
        $hasVerified = $this->employmentService->hasVerifiedCurrent($person->id);

        return response()->json(['has_verified_current' => $hasVerified]);
    }

    /**
     * Check if person has verified income.
     */
    public function hasVerifiedIncome(Person $person): JsonResponse
    {
        $hasVerified = $this->employmentService->hasVerifiedIncome($person->id);

        return response()->json(['has_verified_income' => $hasVerified]);
    }
}
