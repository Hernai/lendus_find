<?php

namespace Database\Factories;

use App\Models\ApplicantAccount;
use App\Models\Company;
use App\Models\CompanyMember;
use App\Models\Person;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CompanyMember>
 */
class CompanyMemberFactory extends Factory
{
    protected $model = CompanyMember::class;

    public function definition(): array
    {
        $role = fake()->randomElement([
            CompanyMember::ROLE_OWNER,
            CompanyMember::ROLE_LEGAL_REP,
            CompanyMember::ROLE_ADMIN,
            CompanyMember::ROLE_FINANCE,
            CompanyMember::ROLE_OPERATIONS,
            CompanyMember::ROLE_VIEWER,
        ]);

        return [
            'tenant_id' => Tenant::factory(),
            'company_id' => Company::factory(),
            'person_id' => Person::factory(),
            'account_id' => ApplicantAccount::factory(),
            'role' => $role,
            'title' => fake()->jobTitle(),
            'is_legal_representative' => false,
            'is_shareholder' => false,
            'permissions' => CompanyMember::defaultPermissions($role),
            'status' => CompanyMember::STATUS_ACTIVE,
            'accepted_at' => now(),
            'is_verified' => false,
        ];
    }

    /**
     * Owner member (creator of the company).
     */
    public function owner(): static
    {
        return $this->state(fn(array $attributes) => [
            'role' => CompanyMember::ROLE_OWNER,
            'title' => 'Propietario',
            'is_legal_representative' => true,
            'is_shareholder' => true,
            'ownership_percentage' => 100.00,
            'power_type' => CompanyMember::POWER_GENERAL,
            'power_granted_date' => now()->subYear(),
            'permissions' => CompanyMember::defaultPermissions(CompanyMember::ROLE_OWNER),
            'status' => CompanyMember::STATUS_ACTIVE,
            'accepted_at' => now(),
            'is_verified' => true,
            'verified_at' => now(),
        ]);
    }

    /**
     * Legal representative member.
     */
    public function legalRepresentative(): static
    {
        return $this->state(fn(array $attributes) => [
            'role' => CompanyMember::ROLE_LEGAL_REP,
            'title' => 'Representante Legal',
            'is_legal_representative' => true,
            'power_type' => CompanyMember::POWER_GENERAL,
            'power_granted_date' => now()->subMonths(6),
            'permissions' => CompanyMember::defaultPermissions(CompanyMember::ROLE_LEGAL_REP),
        ]);
    }

    /**
     * Shareholder member.
     */
    public function shareholder(float $percentage = 25.00): static
    {
        return $this->state(fn(array $attributes) => [
            'is_shareholder' => true,
            'ownership_percentage' => $percentage,
        ]);
    }

    /**
     * Admin member.
     */
    public function admin(): static
    {
        return $this->state(fn(array $attributes) => [
            'role' => CompanyMember::ROLE_ADMIN,
            'title' => 'Administrador',
            'permissions' => CompanyMember::defaultPermissions(CompanyMember::ROLE_ADMIN),
        ]);
    }

    /**
     * Finance member.
     */
    public function finance(): static
    {
        return $this->state(fn(array $attributes) => [
            'role' => CompanyMember::ROLE_FINANCE,
            'title' => 'Director de Finanzas',
            'permissions' => CompanyMember::defaultPermissions(CompanyMember::ROLE_FINANCE),
        ]);
    }

    /**
     * Viewer member.
     */
    public function viewer(): static
    {
        return $this->state(fn(array $attributes) => [
            'role' => CompanyMember::ROLE_VIEWER,
            'title' => 'Observador',
            'permissions' => CompanyMember::defaultPermissions(CompanyMember::ROLE_VIEWER),
        ]);
    }

    /**
     * Invited member (not yet accepted).
     */
    public function invited(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => CompanyMember::STATUS_INVITED,
            'invited_at' => now(),
            'accepted_at' => null,
        ]);
    }

    /**
     * Suspended member.
     */
    public function suspended(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => CompanyMember::STATUS_SUSPENDED,
            'suspended_at' => now(),
        ]);
    }

    /**
     * Verified member.
     */
    public function verified(): static
    {
        return $this->state(fn(array $attributes) => [
            'is_verified' => true,
            'verified_at' => now(),
        ]);
    }
}
