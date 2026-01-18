<?php

namespace Tests\Unit\Models;

use App\Models\ApplicantAccount;
use App\Models\Company;
use App\Models\CompanyMember;
use App\Models\Person;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyMemberTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected Person $person;
    protected ApplicantAccount $account;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->account = ApplicantAccount::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->person = Person::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->company = Company::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by_account_id' => $this->account->id,
        ]);
    }

    public function test_can_create_member(): void
    {
        $member = CompanyMember::factory()->create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'person_id' => $this->person->id,
            'account_id' => $this->account->id,
            'role' => CompanyMember::ROLE_ADMIN,
        ]);

        $this->assertDatabaseHas('company_members', [
            'id' => $member->id,
            'role' => CompanyMember::ROLE_ADMIN,
        ]);
    }

    public function test_member_has_company_relationship(): void
    {
        $member = CompanyMember::factory()->create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'person_id' => $this->person->id,
            'account_id' => $this->account->id,
        ]);

        $this->assertInstanceOf(Company::class, $member->company);
        $this->assertEquals($this->company->id, $member->company->id);
    }

    public function test_member_has_person_relationship(): void
    {
        $member = CompanyMember::factory()->create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'person_id' => $this->person->id,
            'account_id' => $this->account->id,
        ]);

        $this->assertInstanceOf(Person::class, $member->person);
        $this->assertEquals($this->person->id, $member->person->id);
    }

    public function test_owner_has_all_permissions(): void
    {
        $member = CompanyMember::factory()->owner()->create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'person_id' => $this->person->id,
            'account_id' => $this->account->id,
        ]);

        $this->assertTrue($member->canApplyCredit());
        $this->assertTrue($member->canSignContracts());
        $this->assertTrue($member->canInviteMembers());
        $this->assertTrue($member->canManageMembers());
        $this->assertTrue($member->canEditCompanyInfo());
    }

    public function test_viewer_has_limited_permissions(): void
    {
        $member = CompanyMember::factory()->viewer()->create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'person_id' => $this->person->id,
            'account_id' => $this->account->id,
        ]);

        $this->assertFalse($member->canApplyCredit());
        $this->assertFalse($member->canSignContracts());
        $this->assertFalse($member->canInviteMembers());
        $this->assertFalse($member->canManageMembers());
        $this->assertFalse($member->canEditCompanyInfo());
    }

    public function test_legal_representative_can_legally_bind(): void
    {
        $member = CompanyMember::factory()->legalRepresentative()->create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'person_id' => $this->person->id,
            'account_id' => $this->account->id,
            'status' => CompanyMember::STATUS_ACTIVE,
        ]);

        $this->assertTrue($member->canLegallyBind());
    }

    public function test_non_legal_rep_cannot_legally_bind(): void
    {
        $member = CompanyMember::factory()->viewer()->create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'person_id' => $this->person->id,
            'account_id' => $this->account->id,
        ]);

        $this->assertFalse($member->canLegallyBind());
    }

    public function test_expired_power_cannot_legally_bind(): void
    {
        $member = CompanyMember::factory()->legalRepresentative()->create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'person_id' => $this->person->id,
            'account_id' => $this->account->id,
            'power_expiry_date' => now()->subMonth(),
        ]);

        $this->assertFalse($member->isPowerValid());
        $this->assertFalse($member->canLegallyBind());
    }

    public function test_accept_changes_status(): void
    {
        $member = CompanyMember::factory()->invited()->create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'person_id' => $this->person->id,
            'account_id' => $this->account->id,
        ]);

        $this->assertTrue($member->isInvited());

        $member->accept();
        $member->refresh();

        $this->assertTrue($member->isActive());
        $this->assertNotNull($member->accepted_at);
    }

    public function test_suspend_changes_status(): void
    {
        $member = CompanyMember::factory()->create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'person_id' => $this->person->id,
            'account_id' => $this->account->id,
            'status' => CompanyMember::STATUS_ACTIVE,
        ]);

        $member->suspend('Reason for suspension');
        $member->refresh();

        $this->assertTrue($member->isSuspended());
        $this->assertNotNull($member->suspended_at);
    }

    public function test_remove_changes_status(): void
    {
        $member = CompanyMember::factory()->create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'person_id' => $this->person->id,
            'account_id' => $this->account->id,
            'status' => CompanyMember::STATUS_ACTIVE,
        ]);

        $member->remove();
        $member->refresh();

        $this->assertTrue($member->isRemoved());
        $this->assertNotNull($member->removed_at);
    }

    public function test_default_permissions_for_roles(): void
    {
        $ownerPerms = CompanyMember::defaultPermissions(CompanyMember::ROLE_OWNER);
        $viewerPerms = CompanyMember::defaultPermissions(CompanyMember::ROLE_VIEWER);

        $this->assertTrue($ownerPerms['can_sign_contracts']);
        $this->assertFalse($viewerPerms['can_sign_contracts']);
    }

    public function test_scope_active(): void
    {
        CompanyMember::factory()->create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'person_id' => $this->person->id,
            'account_id' => $this->account->id,
            'status' => CompanyMember::STATUS_ACTIVE,
        ]);

        $person2 = Person::factory()->create(['tenant_id' => $this->tenant->id]);
        $account2 = ApplicantAccount::factory()->create(['tenant_id' => $this->tenant->id]);
        CompanyMember::factory()->suspended()->create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'person_id' => $person2->id,
            'account_id' => $account2->id,
        ]);

        $activeMembers = CompanyMember::active()->get();

        $this->assertCount(1, $activeMembers);
    }

    public function test_scope_legal_representatives(): void
    {
        CompanyMember::factory()->legalRepresentative()->create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'person_id' => $this->person->id,
            'account_id' => $this->account->id,
        ]);

        $person2 = Person::factory()->create(['tenant_id' => $this->tenant->id]);
        $account2 = ApplicantAccount::factory()->create(['tenant_id' => $this->tenant->id]);
        CompanyMember::factory()->viewer()->create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'person_id' => $person2->id,
            'account_id' => $account2->id,
        ]);

        $legalReps = CompanyMember::legalRepresentatives()->get();

        $this->assertCount(1, $legalReps);
    }

    public function test_roles_returns_array(): void
    {
        $roles = CompanyMember::roles();

        $this->assertIsArray($roles);
        $this->assertArrayHasKey('OWNER', $roles);
        $this->assertArrayHasKey('VIEWER', $roles);
    }

    public function test_power_types_returns_array(): void
    {
        $types = CompanyMember::powerTypes();

        $this->assertIsArray($types);
        $this->assertArrayHasKey('GENERAL', $types);
        $this->assertEquals('Poder General', $types['GENERAL']);
    }
}
