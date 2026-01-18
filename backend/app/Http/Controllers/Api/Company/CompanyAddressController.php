<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Http\Resources\Company\CompanyAddressResource;
use App\Models\Company;
use App\Models\CompanyAddress;
use App\Services\CompanyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class CompanyAddressController extends Controller
{
    public function __construct(
        protected CompanyService $companyService
    ) {}

    /**
     * List addresses of a company.
     */
    public function index(Request $request, Company $company): AnonymousResourceCollection
    {
        $this->authorize('view', $company);

        $addresses = $company->addresses()
            ->when($request->boolean('current_only'), fn($q) => $q->current())
            ->when($request->query('type'), fn($q, $type) => $q->byType($type))
            ->orderByDesc('is_current')
            ->orderByDesc('created_at')
            ->get();

        return CompanyAddressResource::collection($addresses);
    }

    /**
     * Add a new address to the company.
     */
    public function store(Request $request, Company $company): JsonResponse
    {
        $this->authorize('update', $company);

        $validated = $request->validate([
            'type' => ['required', 'string', Rule::in(array_keys(CompanyAddress::types()))],
            'street' => 'required|string|max:255',
            'exterior_number' => 'required|string|max:20',
            'interior_number' => 'nullable|string|max:20',
            'neighborhood' => 'required|string|max:255',
            'municipality' => 'required|string|max:255',
            'city' => 'nullable|string|max:255',
            'state' => 'required|string|max:5',
            'postal_code' => 'required|string|size:5',
            'country' => 'nullable|string|size:2',
            'between_streets' => 'nullable|string|max:500',
            'references' => 'nullable|string|max:500',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'is_current' => 'nullable|boolean',
        ]);

        $address = $this->companyService->addAddress($company, $validated);

        return (new CompanyAddressResource($address))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Show an address.
     */
    public function show(Request $request, Company $company, CompanyAddress $address): CompanyAddressResource
    {
        $this->authorize('view', $company);

        // Ensure address belongs to this company
        if ($address->company_id !== $company->id) {
            abort(404);
        }

        return new CompanyAddressResource($address);
    }

    /**
     * Update an address.
     */
    public function update(Request $request, Company $company, CompanyAddress $address): CompanyAddressResource
    {
        $this->authorize('update', $company);

        // Ensure address belongs to this company
        if ($address->company_id !== $company->id) {
            abort(404);
        }

        $validated = $request->validate([
            'street' => 'sometimes|string|max:255',
            'exterior_number' => 'sometimes|string|max:20',
            'interior_number' => 'nullable|string|max:20',
            'neighborhood' => 'sometimes|string|max:255',
            'municipality' => 'sometimes|string|max:255',
            'city' => 'nullable|string|max:255',
            'state' => 'sometimes|string|max:5',
            'postal_code' => 'sometimes|string|size:5',
            'country' => 'nullable|string|size:2',
            'between_streets' => 'nullable|string|max:500',
            'references' => 'nullable|string|max:500',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ]);

        $address = $this->companyService->updateAddress($address, $validated);

        return new CompanyAddressResource($address);
    }

    /**
     * Verify an address (staff only).
     */
    public function verify(Request $request, Company $company, CompanyAddress $address): CompanyAddressResource
    {
        $this->authorize('verifyAddress', $company);

        // Ensure address belongs to this company
        if ($address->company_id !== $company->id) {
            abort(404);
        }

        $staff = $request->user();
        $address->verify($staff->id);

        return new CompanyAddressResource($address->fresh());
    }

    /**
     * Reject an address (staff only).
     */
    public function reject(Request $request, Company $company, CompanyAddress $address): CompanyAddressResource
    {
        $this->authorize('verifyAddress', $company);

        // Ensure address belongs to this company
        if ($address->company_id !== $company->id) {
            abort(404);
        }

        $staff = $request->user();
        $address->reject($staff->id);

        return new CompanyAddressResource($address->fresh());
    }

    /**
     * Delete an address (soft delete).
     */
    public function destroy(Request $request, Company $company, CompanyAddress $address): JsonResponse
    {
        $this->authorize('update', $company);

        // Ensure address belongs to this company
        if ($address->company_id !== $company->id) {
            abort(404);
        }

        // Cannot delete the only current fiscal address
        if ($address->type === CompanyAddress::TYPE_FISCAL && $address->is_current) {
            $otherFiscal = $company->addresses()
                ->where('type', CompanyAddress::TYPE_FISCAL)
                ->where('is_current', true)
                ->where('id', '!=', $address->id)
                ->exists();

            if (!$otherFiscal) {
                return response()->json([
                    'message' => 'Cannot delete the only current fiscal address',
                ], 422);
            }
        }

        $address->delete();

        return response()->json(['message' => 'Address deleted successfully']);
    }
}
