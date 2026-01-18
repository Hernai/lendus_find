<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Http\Resources\Company\CompanyMemberResource;
use App\Models\ApplicantAccount;
use App\Models\Company;
use App\Models\CompanyMember;
use App\Services\CompanyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class CompanyMemberController extends Controller
{
    public function __construct(
        protected CompanyService $companyService
    ) {}

    /**
     * List members of a company.
     */
    public function index(Request $request, Company $company): AnonymousResourceCollection
    {
        $this->authorize('view', $company);

        $members = $company->members()
            ->with('person')
            ->when($request->boolean('active_only'), fn($q) => $q->active())
            ->orderBy('role')
            ->get();

        return CompanyMemberResource::collection($members);
    }

    /**
     * Add a new member to the company.
     */
    public function store(Request $request, Company $company): JsonResponse
    {
        $this->authorize('manageMember', $company);

        $validated = $request->validate([
            'account_id' => [
                'required',
                'uuid',
                Rule::exists('applicant_accounts', 'id')->where('tenant_id', $company->tenant_id),
            ],
            'role' => ['required', 'string', Rule::in(array_keys(CompanyMember::roles()))],
            'title' => 'nullable|string|max:100',
            'is_legal_representative' => 'nullable|boolean',
            'power_type' => ['nullable', 'string', Rule::in(array_keys(CompanyMember::powerTypes()))],
            'power_granted_date' => 'nullable|date',
            'power_expiry_date' => 'nullable|date|after:power_granted_date',
            'is_shareholder' => 'nullable|boolean',
            'ownership_percentage' => 'nullable|numeric|min:0|max:100',
            'permissions' => 'nullable|array',
        ]);

        $newAccount = ApplicantAccount::findOrFail($validated['account_id']);
        $inviter = $request->user();

        $member = $this->companyService->addMember($company, $newAccount, $validated, $inviter);

        return (new CompanyMemberResource($member->load('person')))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Show a member.
     */
    public function show(Request $request, Company $company, CompanyMember $member): CompanyMemberResource
    {
        $this->authorize('view', $company);

        // Ensure member belongs to this company
        if ($member->company_id !== $company->id) {
            abort(404);
        }

        $member->load('person');

        return new CompanyMemberResource($member);
    }

    /**
     * Update a member.
     */
    public function update(Request $request, Company $company, CompanyMember $member): CompanyMemberResource
    {
        $this->authorize('manageMember', $company);

        // Ensure member belongs to this company
        if ($member->company_id !== $company->id) {
            abort(404);
        }

        $validated = $request->validate([
            'role' => ['sometimes', 'string', Rule::in(array_keys(CompanyMember::roles()))],
            'title' => 'nullable|string|max:100',
            'is_legal_representative' => 'nullable|boolean',
            'power_type' => ['nullable', 'string', Rule::in(array_keys(CompanyMember::powerTypes()))],
            'power_granted_date' => 'nullable|date',
            'power_expiry_date' => 'nullable|date|after:power_granted_date',
            'is_shareholder' => 'nullable|boolean',
            'ownership_percentage' => 'nullable|numeric|min:0|max:100',
            'permissions' => 'nullable|array',
        ]);

        $member = $this->companyService->updateMember($member, $validated);

        return new CompanyMemberResource($member->load('person'));
    }

    /**
     * Remove a member from the company.
     */
    public function destroy(Request $request, Company $company, CompanyMember $member): JsonResponse
    {
        $this->authorize('manageMember', $company);

        // Ensure member belongs to this company
        if ($member->company_id !== $company->id) {
            abort(404);
        }

        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $this->companyService->removeMember($member, $validated['reason'] ?? null);

        return response()->json(['message' => 'Member removed successfully']);
    }

    /**
     * Accept an invitation.
     */
    public function acceptInvitation(Request $request, Company $company, CompanyMember $member): CompanyMemberResource
    {
        // Ensure member belongs to this company
        if ($member->company_id !== $company->id) {
            abort(404);
        }

        // Ensure current user is the invited member
        $account = $request->user();
        if ($member->account_id !== $account->id) {
            abort(403, 'You can only accept your own invitation');
        }

        $member = $this->companyService->acceptInvitation($member);

        return new CompanyMemberResource($member);
    }

    /**
     * Transfer ownership to another member.
     */
    public function transferOwnership(Request $request, Company $company, CompanyMember $newOwner): JsonResponse
    {
        $this->authorize('transferOwnership', $company);

        // Ensure new owner belongs to this company
        if ($newOwner->company_id !== $company->id) {
            abort(404);
        }

        // Get current user's member record
        $account = $request->user();
        $currentOwner = $company->getMemberByAccount($account->id);

        if (!$currentOwner || $currentOwner->role !== CompanyMember::ROLE_OWNER) {
            abort(403, 'Only owners can transfer ownership');
        }

        $this->companyService->transferOwnership($company, $currentOwner, $newOwner);

        return response()->json(['message' => 'Ownership transferred successfully']);
    }

    /**
     * Verify a member (staff only).
     */
    public function verify(Request $request, Company $company, CompanyMember $member): CompanyMemberResource
    {
        $this->authorize('verifyMember', $company);

        // Ensure member belongs to this company
        if ($member->company_id !== $company->id) {
            abort(404);
        }

        $staff = $request->user();
        $member->verify($staff->id);

        return new CompanyMemberResource($member->fresh());
    }

    /**
     * Suspend a member (staff only).
     */
    public function suspend(Request $request, Company $company, CompanyMember $member): CompanyMemberResource
    {
        $this->authorize('manageMember', $company);

        // Ensure member belongs to this company
        if ($member->company_id !== $company->id) {
            abort(404);
        }

        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $member->suspend($validated['reason'] ?? null);

        return new CompanyMemberResource($member->fresh());
    }
}
