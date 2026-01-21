<?php

namespace App\Http\Controllers\Api\Person;

use App\Http\Controllers\Controller;
use App\Http\Resources\Person\AddressResource;
use App\Models\Person;
use App\Models\Address;
use App\Services\Person\AddressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AddressController extends Controller
{
    public function __construct(
        protected AddressService $addressService
    ) {}

    /**
     * List addresses for a person.
     */
    public function index(Person $person): AnonymousResourceCollection
    {
        $addresses = $this->addressService->getForPerson($person->id);

        return AddressResource::collection($addresses);
    }

    /**
     * Create a new address.
     */
    public function store(Request $request, Person $person): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'nullable|string|in:HOME,WORK,FISCAL,BILLING,CORRESPONDENCE,DELIVERY',
            'street' => 'required|string|max:255',
            'exterior_number' => 'required|string|max:20',
            'interior_number' => 'nullable|string|max:20',
            'neighborhood' => 'required|string|max:100',
            'municipality' => 'required|string|max:100',
            'city' => 'nullable|string|max:100',
            'state' => 'required|string|max:5',
            'postal_code' => 'required|string|size:5',
            'country' => 'nullable|string|max:3',
            'between_streets' => 'nullable|string|max:255',
            'references' => 'nullable|string|max:255',
            'latitude' => 'nullable|numeric|min:-90|max:90',
            'longitude' => 'nullable|numeric|min:-180|max:180',
            'years_at_address' => 'nullable|integer|min:0|max:100',
            'months_at_address' => 'nullable|integer|min:0|max:11',
            'housing_type' => 'nullable|string|in:OWNED,RENTED,FAMILY,MORTGAGED,EMPLOYER',
            'monthly_rent' => 'nullable|numeric|min:0',
            'is_current' => 'nullable|boolean',
        ]);

        $address = $this->addressService->create($person, $validated);

        return (new AddressResource($address))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Show an address.
     */
    public function show(Person $person, Address $address): AddressResource
    {
        return new AddressResource($address);
    }

    /**
     * Update an address.
     */
    public function update(Request $request, Person $person, Address $address): AddressResource
    {
        $validated = $request->validate([
            'street' => 'sometimes|string|max:255',
            'exterior_number' => 'sometimes|string|max:20',
            'interior_number' => 'nullable|string|max:20',
            'neighborhood' => 'sometimes|string|max:100',
            'municipality' => 'sometimes|string|max:100',
            'city' => 'nullable|string|max:100',
            'state' => 'sometimes|string|max:5',
            'postal_code' => 'sometimes|string|size:5',
            'country' => 'nullable|string|max:3',
            'between_streets' => 'nullable|string|max:255',
            'references' => 'nullable|string|max:255',
            'years_at_address' => 'nullable|integer|min:0|max:100',
            'months_at_address' => 'nullable|integer|min:0|max:11',
            'housing_type' => 'nullable|string|in:OWNED,RENTED,FAMILY,MORTGAGED,EMPLOYER',
            'monthly_rent' => 'nullable|numeric|min:0',
        ]);

        $address = $this->addressService->update($address, $validated);

        return new AddressResource($address);
    }

    /**
     * Delete an address.
     */
    public function destroy(Person $person, Address $address): JsonResponse
    {
        $this->addressService->delete($address);

        return response()->json(['message' => 'Address deleted successfully']);
    }

    /**
     * Get current addresses.
     */
    public function current(Person $person): AnonymousResourceCollection
    {
        $addresses = $this->addressService->getCurrentForPerson($person->id);

        return AddressResource::collection($addresses);
    }

    /**
     * Get current home address.
     */
    public function currentHome(Person $person): JsonResponse
    {
        $address = $this->addressService->getCurrentHome($person->id);

        if (!$address) {
            return response()->json(['data' => null, 'found' => false]);
        }

        return response()->json([
            'data' => new AddressResource($address),
            'found' => true
        ]);
    }

    /**
     * Set home address for a person.
     */
    public function setHomeAddress(Request $request, Person $person): AddressResource
    {
        $validated = $request->validate([
            'street' => 'required|string|max:255',
            'exterior_number' => 'required|string|max:20',
            'interior_number' => 'nullable|string|max:20',
            'neighborhood' => 'required|string|max:100',
            'municipality' => 'required|string|max:100',
            'city' => 'nullable|string|max:100',
            'state' => 'required|string|max:5',
            'postal_code' => 'required|string|size:5',
            'country' => 'nullable|string|max:3',
            'between_streets' => 'nullable|string|max:255',
            'references' => 'nullable|string|max:255',
            'years_at_address' => 'nullable|integer|min:0|max:100',
            'months_at_address' => 'nullable|integer|min:0|max:11',
            'housing_type' => 'nullable|string|in:OWNED,RENTED,FAMILY,MORTGAGED,EMPLOYER',
            'monthly_rent' => 'nullable|numeric|min:0',
        ]);

        $address = $this->addressService->setHomeAddress($person, $validated);

        return new AddressResource($address);
    }

    /**
     * Verify an address.
     */
    public function verify(Request $request, Person $person, Address $address): AddressResource
    {
        $validated = $request->validate([
            'method' => 'required|string|max:50',
            'verification_data' => 'nullable|array',
        ]);

        $verifiedBy = $request->user()?->id;
        $address = $this->addressService->verify(
            $address,
            $validated['method'],
            $verifiedBy,
            $validated['verification_data'] ?? null
        );

        return new AddressResource($address);
    }

    /**
     * Reject an address.
     */
    public function reject(Request $request, Person $person, Address $address): AddressResource
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $address = $this->addressService->reject($address, $validated['reason']);

        return new AddressResource($address);
    }

    /**
     * Set geolocation for an address.
     */
    public function setGeolocation(Request $request, Person $person, Address $address): AddressResource
    {
        $validated = $request->validate([
            'latitude' => 'required|numeric|min:-90|max:90',
            'longitude' => 'required|numeric|min:-180|max:180',
            'accuracy' => 'nullable|numeric|min:0',
        ]);

        $address = $this->addressService->setGeolocation(
            $address,
            $validated['latitude'],
            $validated['longitude'],
            $validated['accuracy'] ?? null
        );

        return new AddressResource($address);
    }

    /**
     * Get address history for a person and type.
     */
    public function history(Person $person, string $type): AnonymousResourceCollection
    {
        $type = strtoupper($type);
        $addresses = $this->addressService->getHistory($person->id, $type);

        return AddressResource::collection($addresses);
    }

    /**
     * Check if person has verified address of type.
     */
    public function hasVerified(Person $person, string $type): JsonResponse
    {
        $type = strtoupper($type);
        $hasVerified = $this->addressService->hasVerified($person->id, $type);

        return response()->json(['has_verified' => $hasVerified]);
    }
}
