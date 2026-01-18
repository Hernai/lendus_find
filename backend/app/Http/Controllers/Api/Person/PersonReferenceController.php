<?php

namespace App\Http\Controllers\Api\Person;

use App\Http\Controllers\Controller;
use App\Http\Resources\Person\PersonReferenceResource;
use App\Models\Person;
use App\Models\PersonReference;
use App\Services\Person\PersonReferenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PersonReferenceController extends Controller
{
    public function __construct(
        protected PersonReferenceService $referenceService
    ) {}

    /**
     * List references for a person.
     */
    public function index(Person $person): AnonymousResourceCollection
    {
        $references = $this->referenceService->getForPerson($person->id);

        return PersonReferenceResource::collection($references);
    }

    /**
     * Create a new reference.
     */
    public function store(Request $request, Person $person): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|string|in:PERSONAL,WORK',
            'first_name' => 'required|string|max:100',
            'last_name_1' => 'required|string|max:100',
            'last_name_2' => 'nullable|string|max:100',
            'phone' => 'required|string|max:15',
            'email' => 'nullable|email|max:100',
            'relationship' => 'required|string|max:50',
            'years_known' => 'nullable|integer|min:0|max:100',
            'employer_name' => 'nullable|string|max:200',
            'job_title' => 'nullable|string|max:100',
        ]);

        $reference = $this->referenceService->create($person, $validated);

        return (new PersonReferenceResource($reference))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Show a reference.
     */
    public function show(Person $person, PersonReference $reference): PersonReferenceResource
    {
        return new PersonReferenceResource($reference);
    }

    /**
     * Update a reference.
     */
    public function update(Request $request, Person $person, PersonReference $reference): PersonReferenceResource
    {
        $validated = $request->validate([
            'first_name' => 'sometimes|string|max:100',
            'last_name_1' => 'sometimes|string|max:100',
            'last_name_2' => 'nullable|string|max:100',
            'phone' => 'sometimes|string|max:15',
            'email' => 'nullable|email|max:100',
            'relationship' => 'sometimes|string|max:50',
            'years_known' => 'nullable|integer|min:0|max:100',
            'employer_name' => 'nullable|string|max:200',
            'job_title' => 'nullable|string|max:100',
        ]);

        $reference = $this->referenceService->update($reference, $validated);

        return new PersonReferenceResource($reference);
    }

    /**
     * Delete a reference.
     */
    public function destroy(Person $person, PersonReference $reference): JsonResponse
    {
        $this->referenceService->delete($reference);

        return response()->json(['message' => 'Reference deleted successfully']);
    }

    /**
     * Add personal reference.
     */
    public function addPersonal(Request $request, Person $person): JsonResponse
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name_1' => 'required|string|max:100',
            'last_name_2' => 'nullable|string|max:100',
            'phone' => 'required|string|max:15',
            'email' => 'nullable|email|max:100',
            'relationship' => 'required|string|max:50',
            'years_known' => 'nullable|integer|min:0|max:100',
        ]);

        $reference = $this->referenceService->addPersonalReference($person, $validated);

        return (new PersonReferenceResource($reference))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Add work reference.
     */
    public function addWork(Request $request, Person $person): JsonResponse
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name_1' => 'required|string|max:100',
            'last_name_2' => 'nullable|string|max:100',
            'phone' => 'required|string|max:15',
            'email' => 'nullable|email|max:100',
            'relationship' => 'required|string|max:50',
            'years_known' => 'nullable|integer|min:0|max:100',
            'employer_name' => 'nullable|string|max:200',
            'job_title' => 'nullable|string|max:100',
        ]);

        $reference = $this->referenceService->addWorkReference($person, $validated);

        return (new PersonReferenceResource($reference))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Verify a reference.
     */
    public function verify(Request $request, Person $person, PersonReference $reference): PersonReferenceResource
    {
        $validated = $request->validate([
            'notes' => 'nullable|string|max:500',
        ]);

        $verifiedBy = $request->user()?->id;
        $reference = $this->referenceService->verify(
            $reference,
            $verifiedBy,
            $validated['notes'] ?? null
        );

        return new PersonReferenceResource($reference);
    }

    /**
     * Reject a reference.
     */
    public function reject(Request $request, Person $person, PersonReference $reference): PersonReferenceResource
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $reference = $this->referenceService->reject($reference, $validated['reason']);

        return new PersonReferenceResource($reference);
    }

    /**
     * Log a contact attempt.
     */
    public function logContactAttempt(Request $request, Person $person, PersonReference $reference): PersonReferenceResource
    {
        $validated = $request->validate([
            'notes' => 'nullable|string|max:500',
        ]);

        $reference = $this->referenceService->logContactAttempt(
            $reference,
            $validated['notes'] ?? null
        );

        return new PersonReferenceResource($reference);
    }

    /**
     * Get references by type.
     */
    public function byType(Person $person, string $type): AnonymousResourceCollection
    {
        $type = strtoupper($type);
        $references = $this->referenceService->getByType($person->id, $type);

        return PersonReferenceResource::collection($references);
    }

    /**
     * Get verified references.
     */
    public function verified(Person $person): AnonymousResourceCollection
    {
        $references = $this->referenceService->getVerified($person->id);

        return PersonReferenceResource::collection($references);
    }

    /**
     * Get pending references.
     */
    public function pending(Person $person): AnonymousResourceCollection
    {
        $references = $this->referenceService->getPending($person->id);

        return PersonReferenceResource::collection($references);
    }

    /**
     * Get reference summary for a person.
     */
    public function summary(Person $person): JsonResponse
    {
        $summary = $this->referenceService->getSummary($person->id);

        return response()->json(['data' => $summary]);
    }

    /**
     * Check if phone already exists for person.
     */
    public function phoneExists(Request $request, Person $person): JsonResponse
    {
        $validated = $request->validate([
            'phone' => 'required|string|max:15',
        ]);

        $exists = $this->referenceService->phoneExists($person->id, $validated['phone']);

        return response()->json(['exists' => $exists]);
    }

    /**
     * Check if person has required references.
     */
    public function hasRequired(Request $request, Person $person): JsonResponse
    {
        $validated = $request->validate([
            'min_personal' => 'nullable|integer|min:0',
            'min_work' => 'nullable|integer|min:0',
        ]);

        $hasRequired = $this->referenceService->hasRequiredReferences(
            $person->id,
            $validated['min_personal'] ?? 1,
            $validated['min_work'] ?? 1
        );

        return response()->json(['has_required' => $hasRequired]);
    }

    /**
     * Bulk verify references.
     */
    public function bulkVerify(Request $request, Person $person): JsonResponse
    {
        $validated = $request->validate([
            'reference_ids' => 'required|array|min:1',
            'reference_ids.*' => 'required|uuid|exists:person_references,id',
        ]);

        $verifiedBy = $request->user()?->id;
        $count = $this->referenceService->bulkVerify($validated['reference_ids'], $verifiedBy);

        return response()->json([
            'verified_count' => $count,
            'message' => "{$count} references verified successfully"
        ]);
    }
}
