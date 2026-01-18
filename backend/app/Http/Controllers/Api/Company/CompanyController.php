<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Http\Resources\Company\CompanyResource;
use App\Models\Company;
use App\Services\CompanyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class CompanyController extends Controller
{
    public function __construct(
        protected CompanyService $companyService
    ) {}

    /**
     * List companies for current account.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $account = $request->user();
        $companies = $this->companyService->getCompaniesForAccount($account);

        return CompanyResource::collection($companies);
    }

    /**
     * List companies with filters (admin).
     */
    public function adminIndex(Request $request): AnonymousResourceCollection
    {
        $tenant = $request->user()->tenant;

        $companies = $this->companyService->search($tenant, [
            'search' => $request->query('search'),
            'status' => $request->query('status'),
            'kyb_status' => $request->query('kyb_status'),
            'company_size' => $request->query('company_size'),
            'legal_entity_type' => $request->query('legal_entity_type'),
            'rfc' => $request->query('rfc'),
            'verified_only' => $request->boolean('verified_only'),
            'active_only' => $request->boolean('active_only'),
            'sort_by' => $request->query('sort_by', 'created_at'),
            'sort_dir' => $request->query('sort_dir', 'desc'),
        ], $request->integer('per_page', 15));

        return CompanyResource::collection($companies);
    }

    /**
     * Create a new company.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'legal_name' => 'required|string|max:255',
            'trade_name' => 'nullable|string|max:255',
            'rfc' => [
                'required',
                'string',
                'size:12',
                'regex:/^[A-Z&Ñ]{3,4}[0-9]{6}[A-Z0-9]{3}$/i',
            ],
            'legal_entity_type' => ['nullable', 'string', Rule::in(array_keys(Company::entityTypes()))],
            'incorporation_date' => 'nullable|date|before_or_equal:today',
            'notary_number' => 'nullable|string|max:100',
            'commercial_folio' => 'nullable|string|max:100',
            'industry_code' => 'nullable|string|max:10',
            'industry_description' => 'nullable|string|max:255',
            'main_activity' => 'nullable|string|max:255',
            'company_size' => ['nullable', 'string', Rule::in(array_keys(Company::companySizes()))],
            'employees_count' => 'nullable|integer|min:1',
            'annual_revenue' => 'nullable|numeric|min:0',
            'annual_revenue_currency' => 'nullable|string|size:3',
            'website' => 'nullable|url|max:255',
            'main_phone' => 'nullable|string|max:15',
            'main_email' => 'nullable|email|max:255',
            'creator_is_legal_rep' => 'nullable|boolean',
            'creator_is_shareholder' => 'nullable|boolean',
            'creator_ownership' => 'nullable|numeric|min:0|max:100',
            'fiscal_address' => 'nullable|array',
            'fiscal_address.street' => 'required_with:fiscal_address|string|max:255',
            'fiscal_address.exterior_number' => 'required_with:fiscal_address|string|max:20',
            'fiscal_address.interior_number' => 'nullable|string|max:20',
            'fiscal_address.neighborhood' => 'required_with:fiscal_address|string|max:255',
            'fiscal_address.municipality' => 'required_with:fiscal_address|string|max:255',
            'fiscal_address.city' => 'nullable|string|max:255',
            'fiscal_address.state' => 'required_with:fiscal_address|string|max:5',
            'fiscal_address.postal_code' => 'required_with:fiscal_address|string|size:5',
            'fiscal_address.country' => 'nullable|string|size:2',
        ]);

        $account = $request->user();
        $tenant = $account->tenant;

        $company = $this->companyService->create($tenant, $account, $validated);

        return (new CompanyResource($company->load(['addresses', 'members'])))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Show a company.
     */
    public function show(Request $request, Company $company): CompanyResource
    {
        $this->authorize('view', $company);

        $relations = ['currentAddresses', 'activeMembers'];

        if ($request->boolean('include_all_addresses')) {
            $relations[] = 'addresses';
        }
        if ($request->boolean('include_all_members')) {
            $relations[] = 'members';
        }

        $company->load($relations);

        return new CompanyResource($company);
    }

    /**
     * Update a company.
     */
    public function update(Request $request, Company $company): CompanyResource
    {
        $this->authorize('update', $company);

        $validated = $request->validate([
            'legal_name' => 'sometimes|string|max:255',
            'trade_name' => 'nullable|string|max:255',
            'rfc' => [
                'sometimes',
                'string',
                'size:12',
                'regex:/^[A-Z&Ñ]{3,4}[0-9]{6}[A-Z0-9]{3}$/i',
            ],
            'legal_entity_type' => ['nullable', 'string', Rule::in(array_keys(Company::entityTypes()))],
            'incorporation_date' => 'nullable|date|before_or_equal:today',
            'notary_number' => 'nullable|string|max:100',
            'commercial_folio' => 'nullable|string|max:100',
            'industry_code' => 'nullable|string|max:10',
            'industry_description' => 'nullable|string|max:255',
            'main_activity' => 'nullable|string|max:255',
            'company_size' => ['nullable', 'string', Rule::in(array_keys(Company::companySizes()))],
            'employees_count' => 'nullable|integer|min:1',
            'annual_revenue' => 'nullable|numeric|min:0',
            'annual_revenue_currency' => 'nullable|string|size:3',
            'website' => 'nullable|url|max:255',
            'main_phone' => 'nullable|string|max:15',
            'main_email' => 'nullable|email|max:255',
        ]);

        $company = $this->companyService->update($company, $validated);

        return new CompanyResource($company);
    }

    /**
     * Verify a company (staff only).
     */
    public function verify(Request $request, Company $company): CompanyResource
    {
        $this->authorize('verify', $company);

        $validated = $request->validate([
            'kyb_data' => 'nullable|array',
        ]);

        $staff = $request->user();
        $company = $this->companyService->verify($company, $staff, $validated['kyb_data'] ?? null);

        return new CompanyResource($company);
    }

    /**
     * Reject KYB (staff only).
     */
    public function rejectKyb(Request $request, Company $company): CompanyResource
    {
        $this->authorize('verify', $company);

        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $staff = $request->user();
        $company = $this->companyService->rejectKyb($company, $staff, $validated['reason']);

        return new CompanyResource($company);
    }

    /**
     * Suspend a company (staff only).
     */
    public function suspend(Request $request, Company $company): CompanyResource
    {
        $this->authorize('suspend', $company);

        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $staff = $request->user();
        $company = $this->companyService->suspend($company, $staff, $validated['reason'] ?? null);

        return new CompanyResource($company);
    }

    /**
     * Reactivate a suspended company (staff only).
     */
    public function reactivate(Request $request, Company $company): CompanyResource
    {
        $this->authorize('suspend', $company);

        $staff = $request->user();
        $company = $this->companyService->reactivate($company, $staff);

        return new CompanyResource($company);
    }

    /**
     * Close a company (staff only).
     */
    public function close(Request $request, Company $company): CompanyResource
    {
        $this->authorize('close', $company);

        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $staff = $request->user();
        $company = $this->companyService->close($company, $staff, $validated['reason'] ?? null);

        return new CompanyResource($company);
    }

    /**
     * Find company by RFC.
     */
    public function findByRfc(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'rfc' => 'required|string|min:12|max:13',
        ]);

        $tenant = $request->user()->tenant;
        $company = Company::where('tenant_id', $tenant->id)
            ->byRfc($validated['rfc'])
            ->first();

        if (!$company) {
            return response()->json(['data' => null, 'found' => false]);
        }

        return response()->json([
            'data' => new CompanyResource($company),
            'found' => true,
        ]);
    }
}
