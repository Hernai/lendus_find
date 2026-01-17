<?php

namespace App\Http\Controllers\Api\V1\Applicant;

use App\Enums\AddressType;
use App\Enums\HousingType;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApplicantHelpers;
use App\Http\Controllers\Api\Traits\ValidationHelpers;
use App\Http\Resources\AddressResource;
use App\Models\Address;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Controller for managing applicant addresses.
 *
 * Single Responsibility: Handle CRUD operations for addresses only.
 */
class AddressController extends Controller
{
    use ApplicantHelpers;
    use ValidationHelpers;

    /**
     * List all addresses for the applicant.
     */
    public function index(Request $request): JsonResponse
    {
        $applicant = $request->user()->applicant;

        if (!$applicant) {
            return response()->json(['data' => []]);
        }

        return response()->json([
            'data' => AddressResource::collection($applicant->addresses)
        ]);
    }

    /**
     * Add a new address.
     */
    public function store(Request $request): JsonResponse
    {
        $applicant = $this->getOrCreateApplicant($request);

        $validation = $this->validateRequest($request->all(), [
            'type' => ['required', Rule::in(AddressType::values())],
            'is_primary' => 'sometimes|boolean',
            'street' => 'required|string|max:200',
            'ext_number' => 'nullable|string|max:20',
            'int_number' => 'nullable|string|max:20',
            'neighborhood' => 'required|string|max:100',
            'municipality' => 'nullable|string|max:100',
            'postal_code' => 'required|string|size:5',
            'city' => 'required|string|max:100',
            'state' => 'required|string|max:50',
            'country' => 'sometimes|string|max:50',
            'housing_type' => ['nullable', Rule::in(HousingType::values())],
            'monthly_rent' => 'nullable|numeric|min:0',
            'years_at_address' => 'nullable|integer|min:0',
            'months_at_address' => 'nullable|integer|min:0|max:11',
        ]);

        if ($this->isValidationError($validation)) {
            return $validation;
        }

        // If setting as primary, unset other primaries of same type
        if ($request->input('is_primary', false)) {
            $applicant->addresses()
                ->where('type', $request->type)
                ->where('is_primary', true)
                ->update(['is_primary' => false]);
        }

        $address = Address::create([
            'id' => Str::uuid(),
            'tenant_id' => $applicant->tenant_id,
            'applicant_id' => $applicant->id,
            'type' => $request->type,
            'is_primary' => $request->input('is_primary', false),
            'street' => $request->street,
            'ext_number' => $request->ext_number,
            'int_number' => $request->int_number,
            'neighborhood' => $request->neighborhood,
            'municipality' => $request->municipality,
            'postal_code' => $request->postal_code,
            'city' => $request->city,
            'state' => $request->state,
            'country' => $request->input('country', 'MEXICO'),
            'housing_type' => $request->housing_type,
            'monthly_rent' => $request->monthly_rent,
            'years_at_address' => $request->years_at_address,
            'months_at_address' => $request->months_at_address,
        ]);

        return response()->json([
            'message' => 'Address added',
            'data' => new AddressResource($address)
        ], 201);
    }

    /**
     * Update an address by ID.
     */
    public function update(Request $request, Address $address): JsonResponse
    {
        $applicant = $request->user()->applicant;

        if (!$applicant || $address->applicant_id !== $applicant->id) {
            return $this->notFoundResponse('Address');
        }

        $validation = $this->validateRequest($request->all(), [
            'type' => ['sometimes', Rule::in(AddressType::values())],
            'is_primary' => 'sometimes|boolean',
            'street' => 'sometimes|string|max:200',
            'ext_number' => 'sometimes|string|max:20',
            'int_number' => 'nullable|string|max:20',
            'neighborhood' => 'sometimes|string|max:100',
            'municipality' => 'nullable|string|max:100',
            'postal_code' => 'sometimes|string|size:5',
            'city' => 'sometimes|string|max:100',
            'state' => 'sometimes|string|max:50',
            'country' => 'sometimes|string|max:50',
            'housing_type' => ['nullable', Rule::in(HousingType::values())],
            'monthly_rent' => 'nullable|numeric|min:0',
            'years_at_address' => 'nullable|integer|min:0',
            'months_at_address' => 'nullable|integer|min:0|max:11',
        ]);

        if ($this->isValidationError($validation)) {
            return $validation;
        }

        $address->fill($request->only([
            'type', 'is_primary', 'street', 'ext_number', 'int_number',
            'neighborhood', 'municipality', 'postal_code', 'city', 'state', 'country',
            'housing_type', 'monthly_rent', 'years_at_address', 'months_at_address'
        ]));
        $address->save();

        return response()->json([
            'message' => 'Dirección actualizada',
            'data' => new AddressResource($address)
        ]);
    }

    /**
     * Delete an address.
     */
    public function destroy(Request $request, Address $address): JsonResponse
    {
        $applicant = $request->user()->applicant;

        if (!$applicant || $address->applicant_id !== $applicant->id) {
            return $this->notFoundResponse('Address');
        }

        $address->delete();

        return response()->json(['message' => 'Dirección eliminada']);
    }

    /**
     * Update the primary home address (Step 3 of onboarding).
     */
    public function updatePrimary(Request $request): JsonResponse
    {
        $applicant = $this->getOrCreateApplicant($request);

        $validation = $this->validateRequest($request->all(), [
            'street' => 'required|string|max:200',
            'ext_number' => 'nullable|string|max:20',
            'int_number' => 'nullable|string|max:20',
            'neighborhood' => 'required|string|max:100',
            'municipality' => 'nullable|string|max:100',
            'postal_code' => 'required|string|size:5',
            'city' => 'required|string|max:100',
            'state' => 'required|string|max:50',
            'country' => 'sometimes|string|max:50',
            'housing_type' => ['required', Rule::in(HousingType::values())],
            'monthly_rent' => 'nullable|numeric|min:0',
            'years_at_address' => 'required|integer|min:0',
            'months_at_address' => 'required|integer|min:0|max:11',
        ]);

        if ($this->isValidationError($validation)) {
            return $validation;
        }

        // Update or create primary home address
        $address = $applicant->addresses()->where('type', AddressType::HOME->value)->first();

        $addressData = [
            'tenant_id' => $applicant->tenant_id,
            'applicant_id' => $applicant->id,
            'type' => AddressType::HOME->value,
            'is_primary' => true,
            'street' => $request->street,
            'ext_number' => $request->ext_number,
            'int_number' => $request->int_number,
            'neighborhood' => $request->neighborhood,
            'municipality' => $request->municipality,
            'postal_code' => $request->postal_code,
            'city' => $request->city,
            'state' => $request->state,
            'country' => $request->input('country', 'MEXICO'),
            'housing_type' => $request->housing_type,
            'monthly_rent' => $request->housing_type === HousingType::RENTED->value ? $request->monthly_rent : null,
            'years_at_address' => $request->years_at_address,
            'months_at_address' => $request->months_at_address,
        ];

        if ($address) {
            $address->update($addressData);
        } else {
            $addressData['id'] = Str::uuid();
            $address = Address::create($addressData);
        }

        return response()->json([
            'message' => 'Dirección actualizada',
            'data' => new AddressResource($address)
        ]);
    }
}
