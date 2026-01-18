<?php

namespace App\Http\Controllers\Api\Person;

use App\Http\Controllers\Controller;
use App\Http\Resources\Person\PersonResource;
use App\Models\Person;
use App\Services\Person\PersonService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PersonController extends Controller
{
    public function __construct(
        protected PersonService $personService
    ) {}

    /**
     * List persons with pagination and filters.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $tenantId = $request->header('X-Tenant-ID') ?? $request->user()?->tenant_id;

        $persons = $this->personService->paginate(
            tenantId: $tenantId,
            perPage: $request->integer('per_page', 15),
            filters: $request->only(['search', 'kyc_status', 'marital_status', 'education_level', 'gender', 'kyc_verified', 'profile_complete', 'min_completeness']),
            sortBy: $request->string('sort_by', 'created_at'),
            sortDirection: $request->string('sort_direction', 'desc')
        );

        return PersonResource::collection($persons);
    }

    /**
     * Create a new person.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name_1' => 'required|string|max:100',
            'last_name_2' => 'nullable|string|max:100',
            'birth_date' => 'nullable|date|before:today',
            'birth_state' => 'nullable|string|max:5',
            'birth_country' => 'nullable|string|max:3',
            'gender' => 'nullable|string|in:M,F,O',
            'nationality' => 'nullable|string|max:3',
            'marital_status' => 'nullable|string|in:SINGLE,MARRIED,DIVORCED,WIDOWED,COHABITING,SEPARATED',
            'education_level' => 'nullable|string|in:NONE,PRIMARY,SECONDARY,HIGH_SCHOOL,TECHNICAL,BACHELOR,MASTER,DOCTORATE',
            'dependents_count' => 'nullable|integer|min:0|max:20',
        ]);

        $tenantId = $request->header('X-Tenant-ID') ?? $request->user()?->tenant_id;
        $person = $this->personService->create($validated, $tenantId);

        return (new PersonResource($person))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Show a person with relations.
     */
    public function show(Request $request, Person $person): PersonResource
    {
        $relations = $request->query('include', '');
        $availableRelations = [
            'identifications', 'currentCurp', 'currentRfc', 'currentIne',
            'addresses', 'currentHomeAddress',
            'employments', 'currentEmployment',
            'bankAccounts', 'primaryBankAccount',
            'references'
        ];

        $requestedRelations = array_filter(
            explode(',', $relations),
            fn($r) => in_array(trim($r), $availableRelations)
        );

        if (!empty($requestedRelations)) {
            $person->load($requestedRelations);
        }

        return new PersonResource($person);
    }

    /**
     * Update a person.
     */
    public function update(Request $request, Person $person): PersonResource
    {
        $validated = $request->validate([
            'first_name' => 'sometimes|string|max:100',
            'last_name_1' => 'sometimes|string|max:100',
            'last_name_2' => 'nullable|string|max:100',
            'birth_date' => 'nullable|date|before:today',
            'birth_state' => 'nullable|string|max:5',
            'birth_country' => 'nullable|string|max:3',
            'gender' => 'nullable|string|in:M,F,O',
            'nationality' => 'nullable|string|max:3',
            'marital_status' => 'nullable|string|in:SINGLE,MARRIED,DIVORCED,WIDOWED,COHABITING,SEPARATED',
            'education_level' => 'nullable|string|in:NONE,PRIMARY,SECONDARY,HIGH_SCHOOL,TECHNICAL,BACHELOR,MASTER,DOCTORATE',
            'dependents_count' => 'nullable|integer|min:0|max:20',
        ]);

        $person = $this->personService->update($person, $validated);

        return new PersonResource($person);
    }

    /**
     * Delete a person (soft delete).
     */
    public function destroy(Request $request, Person $person): JsonResponse
    {
        $deletedBy = $request->user()?->id;
        $this->personService->delete($person, $deletedBy);

        return response()->json(['message' => 'Person deleted successfully']);
    }

    /**
     * Get person profile summary.
     */
    public function summary(Person $person): JsonResponse
    {
        $summary = $this->personService->getProfileSummary($person);

        return response()->json(['data' => $summary]);
    }

    /**
     * Update KYC status.
     */
    public function updateKycStatus(Request $request, Person $person): PersonResource
    {
        $validated = $request->validate([
            'status' => 'required|string|in:PENDING,IN_PROGRESS,VERIFIED,REJECTED,EXPIRED',
            'kyc_data' => 'nullable|array',
        ]);

        $verifiedBy = $request->user()?->id;
        $person = $this->personService->updateKycStatus(
            $person,
            $validated['status'],
            $validated['kyc_data'] ?? null,
            $verifiedBy
        );

        return new PersonResource($person);
    }

    /**
     * Recalculate profile completeness.
     */
    public function recalculateCompleteness(Person $person): JsonResponse
    {
        $completeness = $this->personService->recalculateCompleteness($person);

        return response()->json([
            'data' => [
                'profile_completeness' => $completeness
            ]
        ]);
    }

    /**
     * Get statistics for tenant.
     */
    public function statistics(Request $request): JsonResponse
    {
        $tenantId = $request->header('X-Tenant-ID') ?? $request->user()?->tenant_id;
        $stats = $this->personService->getStatistics($tenantId);

        return response()->json(['data' => $stats]);
    }

    /**
     * Find person by CURP.
     */
    public function findByCurp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'curp' => 'required|string|size:18',
        ]);

        $tenantId = $request->header('X-Tenant-ID') ?? $request->user()?->tenant_id;
        $person = $this->personService->findByCurp($validated['curp'], $tenantId);

        if (!$person) {
            return response()->json(['data' => null, 'found' => false]);
        }

        return response()->json([
            'data' => new PersonResource($person),
            'found' => true
        ]);
    }

    /**
     * Find person by RFC.
     */
    public function findByRfc(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'rfc' => 'required|string|min:12|max:13',
        ]);

        $tenantId = $request->header('X-Tenant-ID') ?? $request->user()?->tenant_id;
        $person = $this->personService->findByRfc($validated['rfc'], $tenantId);

        if (!$person) {
            return response()->json(['data' => null, 'found' => false]);
        }

        return response()->json([
            'data' => new PersonResource($person),
            'found' => true
        ]);
    }
}
