<?php

namespace App\Http\Controllers\Api\Person;

use App\Http\Controllers\Controller;
use App\Http\Resources\Person\PersonIdentificationResource;
use App\Models\Person;
use App\Models\PersonIdentification;
use App\Services\Person\PersonIdentificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PersonIdentificationController extends Controller
{
    public function __construct(
        protected PersonIdentificationService $identificationService
    ) {}

    /**
     * List identifications for a person.
     */
    public function index(Person $person): AnonymousResourceCollection
    {
        $identifications = $this->identificationService->getForPerson($person->id);

        return PersonIdentificationResource::collection($identifications);
    }

    /**
     * Create a new identification.
     */
    public function store(Request $request, Person $person): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|string|in:CURP,RFC,INE,PASSPORT,FM2,FM3,VISA,DRIVER_LICENSE,PROFESSIONAL_ID,MILITARY_ID',
            'identifier_value' => 'required|string|max:50',
            'document_data' => 'nullable|array',
            'issued_at' => 'nullable|date',
            'expires_at' => 'nullable|date|after:issued_at',
            'is_current' => 'nullable|boolean',
        ]);

        $identification = $this->identificationService->create($person, $validated);

        return (new PersonIdentificationResource($identification))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Show an identification.
     */
    public function show(Person $person, PersonIdentification $identification): PersonIdentificationResource
    {
        return new PersonIdentificationResource($identification);
    }

    /**
     * Update an identification.
     */
    public function update(Request $request, Person $person, PersonIdentification $identification): PersonIdentificationResource
    {
        $validated = $request->validate([
            'identifier_value' => 'sometimes|string|max:50',
            'document_data' => 'nullable|array',
            'issued_at' => 'nullable|date',
            'expires_at' => 'nullable|date',
            'notes' => 'nullable|string|max:500',
        ]);

        $identification = $this->identificationService->update($identification, $validated);

        return new PersonIdentificationResource($identification);
    }

    /**
     * Delete an identification.
     */
    public function destroy(Person $person, PersonIdentification $identification): JsonResponse
    {
        $this->identificationService->delete($identification);

        return response()->json(['message' => 'Identification deleted successfully']);
    }

    /**
     * Get current identifications.
     */
    public function current(Person $person): AnonymousResourceCollection
    {
        $identifications = $this->identificationService->getCurrentForPerson($person->id);

        return PersonIdentificationResource::collection($identifications);
    }

    /**
     * Get current identification by type.
     */
    public function currentByType(Person $person, string $type): JsonResponse
    {
        $type = strtoupper($type);
        $identification = $this->identificationService->getCurrentByType($person->id, $type);

        if (!$identification) {
            return response()->json(['data' => null, 'found' => false]);
        }

        return response()->json([
            'data' => new PersonIdentificationResource($identification),
            'found' => true
        ]);
    }

    /**
     * Verify an identification.
     */
    public function verify(Request $request, Person $person, PersonIdentification $identification): PersonIdentificationResource
    {
        $validated = $request->validate([
            'method' => 'required|string|max:50',
            'verification_data' => 'nullable|array',
            'confidence' => 'nullable|numeric|min:0|max:100',
        ]);

        $verifiedBy = $request->user()?->id;
        $identification = $this->identificationService->verify(
            $identification,
            $validated['method'],
            $verifiedBy,
            $validated['verification_data'] ?? null,
            $validated['confidence'] ?? null
        );

        return new PersonIdentificationResource($identification);
    }

    /**
     * Reject an identification.
     */
    public function reject(Request $request, Person $person, PersonIdentification $identification): PersonIdentificationResource
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $identification = $this->identificationService->reject($identification, $validated['reason']);

        return new PersonIdentificationResource($identification);
    }

    /**
     * Set CURP for a person.
     */
    public function setCurp(Request $request, Person $person): PersonIdentificationResource
    {
        $validated = $request->validate([
            'curp' => 'required|string|size:18',
        ]);

        $identification = $this->identificationService->setCurp($person, $validated['curp']);

        return new PersonIdentificationResource($identification);
    }

    /**
     * Set RFC for a person.
     */
    public function setRfc(Request $request, Person $person): PersonIdentificationResource
    {
        $validated = $request->validate([
            'rfc' => 'required|string|min:12|max:13',
        ]);

        $identification = $this->identificationService->setRfc($person, $validated['rfc']);

        return new PersonIdentificationResource($identification);
    }

    /**
     * Set INE for a person.
     */
    public function setIne(Request $request, Person $person): PersonIdentificationResource
    {
        $validated = $request->validate([
            'cic' => 'required|string|max:20',
            'ocr' => 'nullable|string|max:20',
            'expires_at' => 'nullable|date',
            'document_data' => 'nullable|array',
        ]);

        $expiresAt = isset($validated['expires_at'])
            ? \Carbon\Carbon::parse($validated['expires_at'])
            : null;

        $identification = $this->identificationService->setIne(
            $person,
            $validated['cic'],
            $validated['ocr'] ?? '',
            $expiresAt,
            $validated['document_data'] ?? null
        );

        return new PersonIdentificationResource($identification);
    }

    /**
     * Get pending verifications for a person.
     */
    public function pending(Person $person): AnonymousResourceCollection
    {
        $identifications = $this->identificationService->getPending($person->id);

        return PersonIdentificationResource::collection($identifications);
    }

    /**
     * Check if person has verified identification of type.
     */
    public function hasVerified(Person $person, string $type): JsonResponse
    {
        $type = strtoupper($type);
        $hasVerified = $this->identificationService->hasVerified($person->id, $type);

        return response()->json(['has_verified' => $hasVerified]);
    }
}
