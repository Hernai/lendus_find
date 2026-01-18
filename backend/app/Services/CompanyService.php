<?php

namespace App\Services;

use App\Enums\CompanySize;
use App\Models\ApplicantAccount;
use App\Models\Company;
use App\Models\CompanyAddress;
use App\Models\CompanyMember;
use App\Models\StaffAccount;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class CompanyService
{
    /**
     * Create a new company.
     *
     * @param Tenant $tenant
     * @param ApplicantAccount $createdBy
     * @param array $data Company data
     * @return Company
     */
    public function create(Tenant $tenant, ApplicantAccount $createdBy, array $data): Company
    {
        return DB::transaction(function () use ($tenant, $createdBy, $data) {
            // Create company
            $company = Company::create([
                'tenant_id' => $tenant->id,
                'created_by_account_id' => $createdBy->id,
                'legal_name' => $data['legal_name'],
                'trade_name' => $data['trade_name'] ?? null,
                'rfc' => strtoupper($data['rfc']),
                'legal_entity_type' => $data['legal_entity_type'] ?? Company::ENTITY_SA_DE_CV,
                'incorporation_date' => $data['incorporation_date'] ?? null,
                'notary_number' => $data['notary_number'] ?? null,
                'commercial_folio' => $data['commercial_folio'] ?? null,
                'industry_code' => $data['industry_code'] ?? null,
                'industry_description' => $data['industry_description'] ?? null,
                'main_activity' => $data['main_activity'] ?? null,
                'company_size' => $data['company_size'] ?? Company::SIZE_SMALL,
                'employees_count' => $data['employees_count'] ?? null,
                'annual_revenue' => $data['annual_revenue'] ?? null,
                'annual_revenue_currency' => $data['annual_revenue_currency'] ?? 'MXN',
                'website' => $data['website'] ?? null,
                'main_phone' => $data['main_phone'] ?? null,
                'main_email' => $data['main_email'] ?? null,
                'status' => Company::STATUS_PENDING,
                'kyb_status' => Company::KYB_PENDING,
                'metadata' => $data['metadata'] ?? null,
                'created_by' => $createdBy->id,
            ]);

            // Add creator as owner member
            $this->addMember($company, $createdBy, [
                'role' => CompanyMember::ROLE_OWNER,
                'is_legal_representative' => $data['creator_is_legal_rep'] ?? true,
                'is_shareholder' => $data['creator_is_shareholder'] ?? true,
                'ownership_percentage' => $data['creator_ownership'] ?? 100.00,
                'status' => CompanyMember::STATUS_ACTIVE,
            ]);

            // Add fiscal address if provided
            if (!empty($data['fiscal_address'])) {
                $this->addAddress($company, array_merge($data['fiscal_address'], [
                    'type' => CompanyAddress::TYPE_FISCAL,
                ]));
            }

            return $company->fresh(['members', 'addresses']);
        });
    }

    /**
     * Update company data.
     *
     * @param Company $company
     * @param array $data
     * @return Company
     */
    public function update(Company $company, array $data): Company
    {
        $updateData = array_filter([
            'legal_name' => $data['legal_name'] ?? null,
            'trade_name' => $data['trade_name'] ?? null,
            'rfc' => isset($data['rfc']) ? strtoupper($data['rfc']) : null,
            'legal_entity_type' => $data['legal_entity_type'] ?? null,
            'incorporation_date' => $data['incorporation_date'] ?? null,
            'notary_number' => $data['notary_number'] ?? null,
            'commercial_folio' => $data['commercial_folio'] ?? null,
            'industry_code' => $data['industry_code'] ?? null,
            'industry_description' => $data['industry_description'] ?? null,
            'main_activity' => $data['main_activity'] ?? null,
            'company_size' => $data['company_size'] ?? null,
            'employees_count' => $data['employees_count'] ?? null,
            'annual_revenue' => $data['annual_revenue'] ?? null,
            'annual_revenue_currency' => $data['annual_revenue_currency'] ?? null,
            'website' => $data['website'] ?? null,
            'main_phone' => $data['main_phone'] ?? null,
            'main_email' => $data['main_email'] ?? null,
        ], fn($v) => $v !== null);

        if (!empty($updateData)) {
            $company->update($updateData);
        }

        return $company->fresh();
    }

    /**
     * Add a member to a company.
     *
     * @param Company $company
     * @param ApplicantAccount $account
     * @param array $data Member data
     * @param ApplicantAccount|null $invitedBy Inviter account
     * @return CompanyMember
     */
    public function addMember(
        Company $company,
        ApplicantAccount $account,
        array $data,
        ?ApplicantAccount $invitedBy = null
    ): CompanyMember {
        // Check if already a member
        $existing = $company->getMemberByAccount($account->id);
        if ($existing) {
            throw new \InvalidArgumentException('Account is already a member of this company');
        }

        // Ensure account has a person_id
        if (!$account->person_id) {
            throw new \InvalidArgumentException('Account must have a person associated');
        }

        // Get inviter member if applicable
        $inviterMemberId = null;
        if ($invitedBy) {
            $inviterMember = $company->getMemberByAccount($invitedBy->id);
            $inviterMemberId = $inviterMember?->id;
        }

        $role = $data['role'] ?? CompanyMember::ROLE_VIEWER;
        $status = $data['status'] ?? CompanyMember::STATUS_INVITED;

        return CompanyMember::create([
            'tenant_id' => $company->tenant_id,
            'company_id' => $company->id,
            'account_id' => $account->id,
            'person_id' => $account->person_id,
            'role' => $role,
            'title' => $data['title'] ?? $data['job_title'] ?? null,
            'is_legal_representative' => $data['is_legal_representative'] ?? false,
            'power_type' => $data['power_type'] ?? null,
            'power_granted_date' => $data['power_granted_date'] ?? null,
            'power_expiry_date' => $data['power_expiry_date'] ?? null,
            'is_shareholder' => $data['is_shareholder'] ?? false,
            'ownership_percentage' => $data['ownership_percentage'] ?? null,
            'permissions' => $data['permissions'] ?? CompanyMember::defaultPermissions($role),
            'status' => $status,
            'invited_by' => $inviterMemberId,
            'invited_at' => $invitedBy ? now() : null,
            'accepted_at' => $status === CompanyMember::STATUS_ACTIVE ? now() : null,
        ]);
    }

    /**
     * Update a member's data.
     *
     * @param CompanyMember $member
     * @param array $data
     * @return CompanyMember
     */
    public function updateMember(CompanyMember $member, array $data): CompanyMember
    {
        $updateData = [];

        // Handle fields that can have null as a valid update value
        if (array_key_exists('role', $data)) {
            $updateData['role'] = $data['role'];
        }
        if (array_key_exists('title', $data) || array_key_exists('job_title', $data)) {
            $updateData['title'] = $data['title'] ?? $data['job_title'] ?? null;
        }
        if (array_key_exists('is_legal_representative', $data)) {
            $updateData['is_legal_representative'] = $data['is_legal_representative'];
        }
        if (array_key_exists('power_type', $data)) {
            $updateData['power_type'] = $data['power_type'];
        }
        if (array_key_exists('power_granted_date', $data)) {
            $updateData['power_granted_date'] = $data['power_granted_date'];
        }
        if (array_key_exists('power_expiry_date', $data)) {
            $updateData['power_expiry_date'] = $data['power_expiry_date'];
        }
        if (array_key_exists('is_shareholder', $data)) {
            $updateData['is_shareholder'] = $data['is_shareholder'];
        }
        if (array_key_exists('ownership_percentage', $data)) {
            $updateData['ownership_percentage'] = $data['ownership_percentage'];
        }
        if (array_key_exists('permissions', $data)) {
            $updateData['permissions'] = $data['permissions'];
        }

        if (!empty($updateData)) {
            $member->update($updateData);
        }

        return $member->fresh();
    }

    /**
     * Remove a member from a company.
     *
     * @param CompanyMember $member
     * @param string|null $reason
     * @return void
     */
    public function removeMember(CompanyMember $member, ?string $reason = null): void
    {
        // Cannot remove the last owner
        $company = $member->company;
        if ($member->role === CompanyMember::ROLE_OWNER) {
            $otherOwners = $company->activeMembers()
                ->where('role', CompanyMember::ROLE_OWNER)
                ->where('id', '!=', $member->id)
                ->count();

            if ($otherOwners === 0) {
                throw new \InvalidArgumentException('Cannot remove the last owner');
            }
        }

        // Add reason to notes if provided
        if ($reason) {
            $member->update(['notes' => ($member->notes ? $member->notes . "\n" : '') . "Removed: " . $reason]);
        }

        $member->remove();
    }

    /**
     * Accept an invitation to join a company.
     *
     * @param CompanyMember $member
     * @return CompanyMember
     */
    public function acceptInvitation(CompanyMember $member): CompanyMember
    {
        if ($member->status !== CompanyMember::STATUS_INVITED) {
            throw new \InvalidArgumentException('Member is not in invited status');
        }

        $member->accept();
        return $member->fresh();
    }

    /**
     * Add an address to a company.
     *
     * @param Company $company
     * @param array $data Address data
     * @return CompanyAddress
     */
    public function addAddress(Company $company, array $data): CompanyAddress
    {
        // If this is set as current and there's an existing current address of same type, mark it as replaced
        if ($data['is_current'] ?? true) {
            $existing = $company->addresses()
                ->where('type', $data['type'])
                ->where('is_current', true)
                ->first();

            if ($existing) {
                $existing->update([
                    'is_current' => false,
                    'valid_until' => now(),
                    'replaced_at' => now(),
                ]);
            }
        }

        return CompanyAddress::create([
            'tenant_id' => $company->tenant_id,
            'company_id' => $company->id,
            'type' => $data['type'] ?? CompanyAddress::TYPE_FISCAL,
            'street' => $data['street'],
            'exterior_number' => $data['exterior_number'],
            'interior_number' => $data['interior_number'] ?? null,
            'neighborhood' => $data['neighborhood'],
            'municipality' => $data['municipality'],
            'city' => $data['city'] ?? $data['municipality'],
            'state' => $data['state'],
            'postal_code' => $data['postal_code'],
            'country' => $data['country'] ?? 'MX',
            'between_streets' => $data['between_streets'] ?? null,
            'references' => $data['references'] ?? null,
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'valid_from' => $data['valid_from'] ?? now(),
            'is_current' => $data['is_current'] ?? true,
            'status' => CompanyAddress::STATUS_PENDING,
            'previous_version_id' => $existing->id ?? null,
        ]);
    }

    /**
     * Update an address.
     *
     * @param CompanyAddress $address
     * @param array $data
     * @return CompanyAddress
     */
    public function updateAddress(CompanyAddress $address, array $data): CompanyAddress
    {
        $updateData = array_filter([
            'street' => $data['street'] ?? null,
            'exterior_number' => $data['exterior_number'] ?? null,
            'interior_number' => $data['interior_number'] ?? null,
            'neighborhood' => $data['neighborhood'] ?? null,
            'municipality' => $data['municipality'] ?? null,
            'city' => $data['city'] ?? null,
            'state' => $data['state'] ?? null,
            'postal_code' => $data['postal_code'] ?? null,
            'country' => $data['country'] ?? null,
            'between_streets' => $data['between_streets'] ?? null,
            'references' => $data['references'] ?? null,
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
        ], fn($v) => $v !== null);

        if (!empty($updateData)) {
            $address->update($updateData);
        }

        return $address->fresh();
    }

    /**
     * Verify a company (KYB verification).
     *
     * @param Company $company
     * @param StaffAccount $verifiedBy
     * @param array|null $kybData
     * @return Company
     */
    public function verify(Company $company, StaffAccount $verifiedBy, ?array $kybData = null): Company
    {
        $company->update([
            'status' => Company::STATUS_VERIFIED,
            'verified_at' => now(),
            'verified_by' => $verifiedBy->id,
            'kyb_status' => Company::KYB_VERIFIED,
            'kyb_verified_at' => now(),
            'kyb_data' => array_merge($company->kyb_data ?? [], $kybData ?? [], [
                'verified_at' => now()->toIso8601String(),
                'verified_by' => $verifiedBy->id,
            ]),
        ]);

        return $company->fresh();
    }

    /**
     * Reject KYB verification.
     *
     * @param Company $company
     * @param StaffAccount $rejectedBy
     * @param string $reason
     * @return Company
     */
    public function rejectKyb(Company $company, StaffAccount $rejectedBy, string $reason): Company
    {
        $company->update([
            'kyb_status' => Company::KYB_REJECTED,
            'kyb_data' => array_merge($company->kyb_data ?? [], [
                'rejected_at' => now()->toIso8601String(),
                'rejected_by' => $rejectedBy->id,
                'rejection_reason' => $reason,
            ]),
        ]);

        return $company->fresh();
    }

    /**
     * Suspend a company.
     *
     * @param Company $company
     * @param StaffAccount $suspendedBy
     * @param string|null $reason
     * @return Company
     */
    public function suspend(Company $company, StaffAccount $suspendedBy, ?string $reason = null): Company
    {
        $company->update([
            'status' => Company::STATUS_SUSPENDED,
            'metadata' => array_merge($company->metadata ?? [], [
                'suspended_at' => now()->toIso8601String(),
                'suspended_by' => $suspendedBy->id,
                'suspension_reason' => $reason,
            ]),
        ]);

        return $company->fresh();
    }

    /**
     * Reactivate a suspended company.
     *
     * @param Company $company
     * @param StaffAccount $reactivatedBy
     * @return Company
     */
    public function reactivate(Company $company, StaffAccount $reactivatedBy): Company
    {
        if ($company->status !== Company::STATUS_SUSPENDED) {
            throw new \InvalidArgumentException('Company is not suspended');
        }

        $company->update([
            'status' => $company->isKybVerified() ? Company::STATUS_VERIFIED : Company::STATUS_PENDING,
            'metadata' => array_merge($company->metadata ?? [], [
                'reactivated_at' => now()->toIso8601String(),
                'reactivated_by' => $reactivatedBy->id,
            ]),
        ]);

        return $company->fresh();
    }

    /**
     * Close a company account.
     *
     * @param Company $company
     * @param StaffAccount|null $closedBy
     * @param string|null $reason
     * @return Company
     */
    public function close(Company $company, ?StaffAccount $closedBy = null, ?string $reason = null): Company
    {
        $company->update([
            'status' => Company::STATUS_CLOSED,
            'metadata' => array_merge($company->metadata ?? [], [
                'closed_at' => now()->toIso8601String(),
                'closed_by' => $closedBy?->id,
                'closure_reason' => $reason,
            ]),
        ]);

        return $company->fresh();
    }

    /**
     * Search companies with filters.
     *
     * @param Tenant $tenant
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function search(Tenant $tenant, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Company::where('tenant_id', $tenant->id);

        if (!empty($filters['search'])) {
            $query->search($filters['search']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['kyb_status'])) {
            $query->where('kyb_status', $filters['kyb_status']);
        }

        if (!empty($filters['company_size'])) {
            $query->where('company_size', $filters['company_size']);
        }

        if (!empty($filters['legal_entity_type'])) {
            $query->where('legal_entity_type', $filters['legal_entity_type']);
        }

        if (!empty($filters['rfc'])) {
            $query->byRfc($filters['rfc']);
        }

        if (isset($filters['verified_only']) && $filters['verified_only']) {
            $query->verified()->kybVerified();
        }

        if (isset($filters['active_only']) && $filters['active_only']) {
            $query->active();
        }

        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortDir = $filters['sort_dir'] ?? 'desc';
        $query->orderBy($sortBy, $sortDir);

        return $query->with(['currentAddresses', 'activeMembers'])
            ->paginate($perPage);
    }

    /**
     * Get companies for an account.
     *
     * @param ApplicantAccount $account
     * @return Collection
     */
    public function getCompaniesForAccount(ApplicantAccount $account): Collection
    {
        return Company::whereHas('members', function ($query) use ($account) {
            $query->where('account_id', $account->id)
                ->active();
        })
            ->with(['currentAddresses', 'activeMembers'])
            ->get();
    }

    /**
     * Check if account can manage company.
     *
     * @param Company $company
     * @param ApplicantAccount $account
     * @param string $permission
     * @return bool
     */
    public function canManage(Company $company, ApplicantAccount $account, string $permission): bool
    {
        $member = $company->getMemberByAccount($account->id);

        if (!$member || !$member->isActive()) {
            return false;
        }

        return $member->hasPermission($permission);
    }

    /**
     * Transfer ownership to another member.
     *
     * @param Company $company
     * @param CompanyMember $currentOwner
     * @param CompanyMember $newOwner
     * @return void
     */
    public function transferOwnership(Company $company, CompanyMember $currentOwner, CompanyMember $newOwner): void
    {
        if ($currentOwner->role !== CompanyMember::ROLE_OWNER) {
            throw new \InvalidArgumentException('Current user is not an owner');
        }

        if ($newOwner->company_id !== $company->id) {
            throw new \InvalidArgumentException('New owner is not a member of this company');
        }

        if (!$newOwner->isActive()) {
            throw new \InvalidArgumentException('New owner must have active status');
        }

        DB::transaction(function () use ($currentOwner, $newOwner) {
            // Update new owner
            $newOwner->update([
                'role' => CompanyMember::ROLE_OWNER,
                'permissions' => CompanyMember::defaultPermissions(CompanyMember::ROLE_OWNER),
            ]);

            // Demote current owner to admin
            $currentOwner->update([
                'role' => CompanyMember::ROLE_ADMIN,
                'permissions' => CompanyMember::defaultPermissions(CompanyMember::ROLE_ADMIN),
            ]);
        });
    }
}
