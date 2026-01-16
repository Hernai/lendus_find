<?php

namespace App\Http\Controllers\Api\Applicant;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Traits\ApplicantHelpers;
use App\Models\Address;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Controller for managing applicant addresses.
 *
 * Single Responsibility: Handle CRUD operations for addresses only.
 */
class AddressController extends Controller
{
    use ApplicantHelpers;

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
            'data' => $applicant->addresses->map(fn($a) => $this->formatAddress($a))
        ]);
    }

    /**
     * Add a new address.
     */
    public function store(Request $request): JsonResponse
    {
        $applicant = $this->getOrCreateApplicant($request);

        $validator = Validator::make($request->all(), [
            'type' => 'required|in:HOME,WORK,FISCAL,CORRESPONDENCE',
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
            'housing_type' => 'nullable|in:PROPIA_PAGADA,PROPIA_HIPOTECA,RENTADA,FAMILIAR,PRESTADA,OTRO',
            'monthly_rent' => 'nullable|numeric|min:0',
            'years_at_address' => 'nullable|integer|min:0',
            'months_at_address' => 'nullable|integer|min:0|max:11',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
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
            'street' => strtoupper($request->street),
            'ext_number' => $request->ext_number,
            'int_number' => $request->int_number,
            'neighborhood' => strtoupper($request->neighborhood),
            'municipality' => $request->municipality ? strtoupper($request->municipality) : null,
            'postal_code' => $request->postal_code,
            'city' => strtoupper($request->city),
            'state' => strtoupper($request->state),
            'country' => strtoupper($request->input('country', 'MEXICO')),
            'housing_type' => $request->housing_type,
            'monthly_rent' => $request->monthly_rent,
            'years_at_address' => $request->years_at_address,
            'months_at_address' => $request->months_at_address,
        ]);

        return response()->json([
            'message' => 'Address added',
            'data' => $this->formatAddress($address)
        ], 201);
    }

    /**
     * Update an address by ID.
     */
    public function update(Request $request, Address $address): JsonResponse
    {
        $applicant = $request->user()->applicant;

        if (!$applicant || $address->applicant_id !== $applicant->id) {
            return response()->json(['message' => 'Address not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'type' => 'sometimes|in:HOME,WORK,FISCAL,CORRESPONDENCE',
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
            'housing_type' => 'nullable|in:PROPIA_PAGADA,PROPIA_HIPOTECA,RENTADA,FAMILIAR,PRESTADA,OTRO',
            'monthly_rent' => 'nullable|numeric|min:0',
            'years_at_address' => 'nullable|integer|min:0',
            'months_at_address' => 'nullable|integer|min:0|max:11',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $address->fill($request->only([
            'type', 'is_primary', 'street', 'ext_number', 'int_number',
            'neighborhood', 'municipality', 'postal_code', 'city', 'state', 'country',
            'housing_type', 'monthly_rent', 'years_at_address', 'months_at_address'
        ]));
        $address->save();

        return response()->json([
            'message' => 'Address updated',
            'data' => $this->formatAddress($address)
        ]);
    }

    /**
     * Delete an address.
     */
    public function destroy(Request $request, Address $address): JsonResponse
    {
        $applicant = $request->user()->applicant;

        if (!$applicant || $address->applicant_id !== $applicant->id) {
            return response()->json(['message' => 'Address not found'], 404);
        }

        $address->delete();

        return response()->json(['message' => 'Address deleted']);
    }

    /**
     * Update the primary home address (Step 3 of onboarding).
     */
    public function updatePrimary(Request $request): JsonResponse
    {
        $applicant = $this->getOrCreateApplicant($request);

        $validator = Validator::make($request->all(), [
            'street' => 'required|string|max:200',
            'ext_number' => 'nullable|string|max:20',
            'int_number' => 'nullable|string|max:20',
            'neighborhood' => 'required|string|max:100',
            'municipality' => 'nullable|string|max:100',
            'postal_code' => 'required|string|size:5',
            'city' => 'required|string|max:100',
            'state' => 'required|string|max:50',
            'country' => 'sometimes|string|max:50',
            'housing_type' => 'required|in:PROPIA_PAGADA,PROPIA_HIPOTECA,RENTADA,FAMILIAR,PRESTADA,OTRO',
            'monthly_rent' => 'nullable|numeric|min:0',
            'years_at_address' => 'required|integer|min:0',
            'months_at_address' => 'required|integer|min:0|max:11',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Update or create primary home address
        $address = $applicant->addresses()->where('type', 'HOME')->first();

        $addressData = [
            'tenant_id' => $applicant->tenant_id,
            'applicant_id' => $applicant->id,
            'type' => 'HOME',
            'is_primary' => true,
            'street' => strtoupper($request->street),
            'ext_number' => $request->ext_number,
            'int_number' => $request->int_number,
            'neighborhood' => strtoupper($request->neighborhood),
            'municipality' => $request->municipality ? strtoupper($request->municipality) : null,
            'postal_code' => $request->postal_code,
            'city' => strtoupper($request->city),
            'state' => strtoupper($request->state),
            'country' => strtoupper($request->input('country', 'MEXICO')),
            'housing_type' => $request->housing_type,
            'monthly_rent' => $request->housing_type === 'RENTADA' ? $request->monthly_rent : null,
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
            'message' => 'Address updated',
            'data' => $this->formatAddress($address)
        ]);
    }
}
