<?php

namespace App\Policies;

use App\Models\ApplicantAccount;
use App\Models\Company;
use App\Models\StaffAccount;

class CompanyPolicy
{
    /**
     * Determine if the user can view any companies.
     */
    public function viewAny(ApplicantAccount|StaffAccount $user): bool
    {
        return true;
    }

    /**
     * Determine if the user can view the company.
     */
    public function view(ApplicantAccount|StaffAccount $user, Company $company): bool
    {
        // Staff can view all companies in their tenant
        if ($user instanceof StaffAccount) {
            return $user->tenant_id === $company->tenant_id;
        }

        // Applicant can view if they are a member
        return $company->getMemberByAccount($user->id)?->isActive() ?? false;
    }

    /**
     * Determine if the user can create a company.
     */
    public function create(ApplicantAccount|StaffAccount $user): bool
    {
        // Only applicants with persons can create companies
        if ($user instanceof ApplicantAccount) {
            return $user->person_id !== null;
        }

        return false;
    }

    /**
     * Determine if the user can update the company.
     */
    public function update(ApplicantAccount|StaffAccount $user, Company $company): bool
    {
        // Staff can update
        if ($user instanceof StaffAccount) {
            return $user->tenant_id === $company->tenant_id;
        }

        // Check member permission
        $member = $company->getMemberByAccount($user->id);
        return $member?->isActive() && $member->hasPermission('can_edit_company_info');
    }

    /**
     * Determine if the user can manage members.
     */
    public function manageMember(ApplicantAccount|StaffAccount $user, Company $company): bool
    {
        // Staff can manage
        if ($user instanceof StaffAccount) {
            return $user->tenant_id === $company->tenant_id;
        }

        // Check member permission
        $member = $company->getMemberByAccount($user->id);
        return $member?->isActive() && $member->hasPermission('can_manage_members');
    }

    /**
     * Determine if the user can invite members.
     */
    public function inviteMember(ApplicantAccount|StaffAccount $user, Company $company): bool
    {
        // Staff cannot invite directly
        if ($user instanceof StaffAccount) {
            return false;
        }

        // Check member permission
        $member = $company->getMemberByAccount($user->id);
        return $member?->isActive() && $member->hasPermission('can_invite_members');
    }

    /**
     * Determine if the user can verify the company (KYB).
     */
    public function verify(ApplicantAccount|StaffAccount $user, Company $company): bool
    {
        // Only staff can verify
        return $user instanceof StaffAccount && $user->tenant_id === $company->tenant_id;
    }

    /**
     * Determine if the user can verify members.
     */
    public function verifyMember(ApplicantAccount|StaffAccount $user, Company $company): bool
    {
        // Only staff can verify members
        return $user instanceof StaffAccount && $user->tenant_id === $company->tenant_id;
    }

    /**
     * Determine if the user can verify addresses.
     */
    public function verifyAddress(ApplicantAccount|StaffAccount $user, Company $company): bool
    {
        // Only staff can verify addresses
        return $user instanceof StaffAccount && $user->tenant_id === $company->tenant_id;
    }

    /**
     * Determine if the user can suspend the company.
     */
    public function suspend(ApplicantAccount|StaffAccount $user, Company $company): bool
    {
        // Only staff can suspend
        return $user instanceof StaffAccount && $user->tenant_id === $company->tenant_id;
    }

    /**
     * Determine if the user can close the company.
     */
    public function close(ApplicantAccount|StaffAccount $user, Company $company): bool
    {
        // Only staff can close
        return $user instanceof StaffAccount && $user->tenant_id === $company->tenant_id;
    }

    /**
     * Determine if the user can transfer ownership.
     */
    public function transferOwnership(ApplicantAccount|StaffAccount $user, Company $company): bool
    {
        // Staff cannot transfer ownership directly
        if ($user instanceof StaffAccount) {
            return false;
        }

        // Only owner can transfer
        $member = $company->getMemberByAccount($user->id);
        return $member?->isActive() && $member->role === \App\Models\CompanyMember::ROLE_OWNER;
    }

    /**
     * Determine if the user can delete the company.
     */
    public function delete(ApplicantAccount|StaffAccount $user, Company $company): bool
    {
        // Only staff can delete
        return $user instanceof StaffAccount && $user->tenant_id === $company->tenant_id;
    }
}
