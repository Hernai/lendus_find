<?php

namespace Tests\Unit\Services;

use App\Models\ApplicantAccount;
use App\Models\Company;
use App\Models\CompanyAddress;
use App\Models\CompanyMember;
use App\Models\StaffAccount;
use App\Models\Tenant;
use App\Services\CompanyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyServiceTest extends TestCase
{
    use RefreshDatabase;

    protected CompanyService $service;
    protected ApplicantAccount $account;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();
        $this->account = ApplicantAccount::factory()->for($this->tenant)->withPerson()->create();
        $this->service = new CompanyService();
    }

    // =====================================================
    // Create Company Tests
    // =====================================================

    public function test_can_create_company(): void
    {
        $data = [
            'legal_name' => 'Empresa de Prueba',
            'trade_name' => 'Prueba SA',
            'rfc' => 'ABC123456789',
            'legal_entity_type' => Company::ENTITY_SA_DE_CV,
            'company_size' => Company::SIZE_SMALL,
        ];

        $company = $this->service->create($this->tenant, $this->account, $data);

        $this->assertInstanceOf(Company::class, $company);
        $this->assertEquals('Empresa de Prueba', $company->legal_name);
        $this->assertEquals('ABC123456789', $company->rfc);
        $this->assertEquals(Company::STATUS_PENDING, $company->status);
        $this->assertEquals(Company::KYB_PENDING, $company->kyb_status);
    }

    public function test_create_company_adds_creator_as_owner(): void
    {
        $data = [
            'legal_name' => 'Mi Empresa',
            'rfc' => 'XYZ987654321',
        ];

        $company = $this->service->create($this->tenant, $this->account, $data);

        $this->assertCount(1, $company->members);

        $member = $company->members->first();
        $this->assertEquals($this->account->id, $member->account_id);
        $this->assertEquals(CompanyMember::ROLE_OWNER, $member->role);
        $this->assertTrue($member->is_legal_representative);
        $this->assertTrue($member->is_shareholder);
        $this->assertEquals(CompanyMember::STATUS_ACTIVE, $member->status);
    }

    public function test_create_company_with_fiscal_address(): void
    {
        $data = [
            'legal_name' => 'Empresa con Dirección',
            'rfc' => 'DIR123456789',
            'fiscal_address' => [
                'street' => 'Av. Reforma',
                'exterior_number' => '222',
                'neighborhood' => 'Juárez',
                'municipality' => 'Cuauhtémoc',
                'state' => 'CDMX',
                'postal_code' => '06600',
            ],
        ];

        $company = $this->service->create($this->tenant, $this->account, $data);

        $this->assertCount(1, $company->addresses);

        $address = $company->addresses->first();
        $this->assertEquals(CompanyAddress::TYPE_FISCAL, $address->type);
        $this->assertEquals('Av. Reforma', $address->street);
        $this->assertTrue($address->is_current);
    }

    // =====================================================
    // Update Company Tests
    // =====================================================

    public function test_can_update_company(): void
    {
        $company = Company::factory()
            ->for($this->tenant)
            ->create(['legal_name' => 'Nombre Original']);

        $this->service->update($company, [
            'legal_name' => 'Nombre Actualizado',
            'trade_name' => 'Nuevo Nombre Comercial',
        ]);

        $company->refresh();

        $this->assertEquals('Nombre Actualizado', $company->legal_name);
        $this->assertEquals('Nuevo Nombre Comercial', $company->trade_name);
    }

    // =====================================================
    // Member Management Tests
    // =====================================================

    public function test_can_add_member_to_company(): void
    {
        $company = Company::factory()
            ->for($this->tenant)
            ->create();

        $newAccount = ApplicantAccount::factory()->for($this->tenant)->withPerson()->create();

        $member = $this->service->addMember($company, $newAccount, [
            'role' => CompanyMember::ROLE_ADMIN,
            'title' => 'Director Financiero',
        ], $this->account);

        $this->assertInstanceOf(CompanyMember::class, $member);
        $this->assertEquals($newAccount->id, $member->account_id);
        $this->assertEquals(CompanyMember::ROLE_ADMIN, $member->role);
        $this->assertEquals('Director Financiero', $member->title);
        $this->assertEquals(CompanyMember::STATUS_INVITED, $member->status);
    }

    public function test_cannot_add_duplicate_member(): void
    {
        $company = Company::factory()
            ->for($this->tenant)
            ->create();

        CompanyMember::factory()
            ->for($this->tenant)
            ->for($company)
            ->create(['account_id' => $this->account->id]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Account is already a member of this company');

        $this->service->addMember($company, $this->account, [
            'role' => CompanyMember::ROLE_VIEWER,
        ]);
    }

    public function test_can_update_member(): void
    {
        $company = Company::factory()
            ->for($this->tenant)
            ->create();

        $member = CompanyMember::factory()
            ->for($this->tenant)
            ->for($company)
            ->create(['role' => CompanyMember::ROLE_VIEWER]);

        $this->service->updateMember($member, [
            'role' => CompanyMember::ROLE_ADMIN,
            'title' => 'Nuevo Cargo',
        ]);

        $member->refresh();

        $this->assertEquals(CompanyMember::ROLE_ADMIN, $member->role);
        $this->assertEquals('Nuevo Cargo', $member->title);
    }

    public function test_can_remove_member(): void
    {
        $company = Company::factory()
            ->for($this->tenant)
            ->create();

        // Add owner first
        CompanyMember::factory()
            ->for($this->tenant)
            ->for($company)
            ->owner()
            ->create();

        $member = CompanyMember::factory()
            ->for($this->tenant)
            ->for($company)
            ->admin()
            ->create();

        $this->service->removeMember($member, 'Ya no trabaja aquí');

        $member->refresh();

        $this->assertEquals(CompanyMember::STATUS_REMOVED, $member->status);
        $this->assertNotNull($member->removed_at);
    }

    public function test_cannot_remove_last_owner(): void
    {
        $company = Company::factory()
            ->for($this->tenant)
            ->create();

        $owner = CompanyMember::factory()
            ->for($this->tenant)
            ->for($company)
            ->owner()
            ->create();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot remove the last owner');

        $this->service->removeMember($owner);
    }

    public function test_can_accept_invitation(): void
    {
        $company = Company::factory()
            ->for($this->tenant)
            ->create();

        $member = CompanyMember::factory()
            ->for($this->tenant)
            ->for($company)
            ->invited()
            ->create();

        $this->service->acceptInvitation($member);

        $member->refresh();

        $this->assertEquals(CompanyMember::STATUS_ACTIVE, $member->status);
        $this->assertNotNull($member->accepted_at);
    }

    // =====================================================
    // Address Management Tests
    // =====================================================

    public function test_can_add_address_to_company(): void
    {
        $company = Company::factory()
            ->for($this->tenant)
            ->create();

        $address = $this->service->addAddress($company, [
            'type' => CompanyAddress::TYPE_HEADQUARTERS,
            'street' => 'Paseo de la Reforma',
            'exterior_number' => '500',
            'neighborhood' => 'Juárez',
            'municipality' => 'Cuauhtémoc',
            'state' => 'CDMX',
            'postal_code' => '06600',
        ]);

        $this->assertInstanceOf(CompanyAddress::class, $address);
        $this->assertEquals(CompanyAddress::TYPE_HEADQUARTERS, $address->type);
        $this->assertTrue($address->is_current);
    }

    public function test_adding_new_current_address_replaces_existing(): void
    {
        $company = Company::factory()
            ->for($this->tenant)
            ->create();

        $oldAddress = CompanyAddress::factory()
            ->for($this->tenant)
            ->for($company)
            ->fiscal()
            ->create(['is_current' => true]);

        $newAddress = $this->service->addAddress($company, [
            'type' => CompanyAddress::TYPE_FISCAL,
            'street' => 'Nueva Calle',
            'exterior_number' => '100',
            'neighborhood' => 'Centro',
            'municipality' => 'Centro',
            'state' => 'CDMX',
            'postal_code' => '06000',
        ]);

        $oldAddress->refresh();

        $this->assertFalse($oldAddress->is_current);
        $this->assertNotNull($oldAddress->replaced_at);
        $this->assertTrue($newAddress->is_current);
        $this->assertEquals($oldAddress->id, $newAddress->previous_version_id);
    }

    // =====================================================
    // Verification Tests
    // =====================================================

    public function test_can_verify_company(): void
    {
        $company = Company::factory()
            ->for($this->tenant)
            ->create(['status' => Company::STATUS_PENDING]);

        $staff = StaffAccount::factory()->for($this->tenant)->create();

        $this->service->verify($company, $staff, ['verified_rfc' => true]);

        $company->refresh();

        $this->assertEquals(Company::STATUS_VERIFIED, $company->status);
        $this->assertEquals(Company::KYB_VERIFIED, $company->kyb_status);
        $this->assertNotNull($company->verified_at);
        $this->assertEquals($staff->id, $company->verified_by);
    }

    public function test_can_reject_kyb(): void
    {
        $company = Company::factory()
            ->for($this->tenant)
            ->create(['kyb_status' => Company::KYB_IN_PROGRESS]);

        $staff = StaffAccount::factory()->for($this->tenant)->create();

        $this->service->rejectKyb($company, $staff, 'Documentos incompletos');

        $company->refresh();

        $this->assertEquals(Company::KYB_REJECTED, $company->kyb_status);
        $this->assertArrayHasKey('rejection_reason', $company->kyb_data);
        $this->assertEquals('Documentos incompletos', $company->kyb_data['rejection_reason']);
    }

    // =====================================================
    // Status Management Tests
    // =====================================================

    public function test_can_suspend_company(): void
    {
        $company = Company::factory()
            ->for($this->tenant)
            ->verified()
            ->create();

        $staff = StaffAccount::factory()->for($this->tenant)->create();

        $this->service->suspend($company, $staff, 'Revisión de cumplimiento');

        $company->refresh();

        $this->assertEquals(Company::STATUS_SUSPENDED, $company->status);
        $this->assertArrayHasKey('suspension_reason', $company->metadata);
    }

    public function test_can_reactivate_suspended_company(): void
    {
        $company = Company::factory()
            ->for($this->tenant)
            ->create([
                'status' => Company::STATUS_SUSPENDED,
                'kyb_status' => Company::KYB_VERIFIED,
            ]);

        $staff = StaffAccount::factory()->for($this->tenant)->create();

        $this->service->reactivate($company, $staff);

        $company->refresh();

        $this->assertEquals(Company::STATUS_VERIFIED, $company->status);
    }

    public function test_cannot_reactivate_non_suspended_company(): void
    {
        $company = Company::factory()
            ->for($this->tenant)
            ->verified()
            ->create();

        $staff = StaffAccount::factory()->for($this->tenant)->create();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Company is not suspended');

        $this->service->reactivate($company, $staff);
    }

    public function test_can_close_company(): void
    {
        $company = Company::factory()
            ->for($this->tenant)
            ->create();

        $staff = StaffAccount::factory()->for($this->tenant)->create();

        $this->service->close($company, $staff, 'Solicitud del cliente');

        $company->refresh();

        $this->assertEquals(Company::STATUS_CLOSED, $company->status);
        $this->assertArrayHasKey('closure_reason', $company->metadata);
    }

    // =====================================================
    // Search Tests
    // =====================================================

    public function test_can_search_companies(): void
    {
        Company::factory()
            ->for($this->tenant)
            ->count(5)
            ->verified()
            ->create();

        Company::factory()
            ->for($this->tenant)
            ->count(3)
            ->create(['status' => Company::STATUS_PENDING]);

        $results = $this->service->search($this->tenant, ['verified_only' => true]);

        $this->assertCount(5, $results->items());
    }

    public function test_can_search_by_status(): void
    {
        Company::factory()
            ->for($this->tenant)
            ->count(3)
            ->create(['status' => Company::STATUS_PENDING]);

        Company::factory()
            ->for($this->tenant)
            ->count(2)
            ->verified()
            ->create();

        $results = $this->service->search($this->tenant, [
            'status' => Company::STATUS_PENDING,
        ]);

        $this->assertCount(3, $results->items());
    }

    public function test_can_search_by_term(): void
    {
        Company::factory()
            ->for($this->tenant)
            ->create(['legal_name' => 'Empresa Específica SA']);

        Company::factory()
            ->for($this->tenant)
            ->count(3)
            ->create();

        $results = $this->service->search($this->tenant, ['search' => 'Específica']);

        $this->assertCount(1, $results->items());
    }

    // =====================================================
    // Account Companies Tests
    // =====================================================

    public function test_get_companies_for_account(): void
    {
        $company1 = Company::factory()->for($this->tenant)->create();
        $company2 = Company::factory()->for($this->tenant)->create();
        Company::factory()->for($this->tenant)->create(); // Not a member

        CompanyMember::factory()
            ->for($this->tenant)
            ->for($company1)
            ->create([
                'account_id' => $this->account->id,
                'person_id' => $this->account->person_id,
                'status' => CompanyMember::STATUS_ACTIVE,
            ]);

        CompanyMember::factory()
            ->for($this->tenant)
            ->for($company2)
            ->create([
                'account_id' => $this->account->id,
                'person_id' => $this->account->person_id,
                'status' => CompanyMember::STATUS_ACTIVE,
            ]);

        $companies = $this->service->getCompaniesForAccount($this->account);

        $this->assertCount(2, $companies);
    }

    // =====================================================
    // Permission Tests
    // =====================================================

    public function test_can_manage_checks_permission(): void
    {
        $company = Company::factory()->for($this->tenant)->create();

        CompanyMember::factory()
            ->for($this->tenant)
            ->for($company)
            ->admin()
            ->create([
                'account_id' => $this->account->id,
                'person_id' => $this->account->person_id,
                'permissions' => [
                    'can_edit_company_info' => true,
                    'can_manage_members' => true,
                    'can_delete_company' => false,
                ],
            ]);

        $this->assertTrue($this->service->canManage($company, $this->account, 'can_edit_company_info'));
        $this->assertTrue($this->service->canManage($company, $this->account, 'can_manage_members'));
        $this->assertFalse($this->service->canManage($company, $this->account, 'can_delete_company'));
    }

    // =====================================================
    // Ownership Transfer Tests
    // =====================================================

    public function test_can_transfer_ownership(): void
    {
        $company = Company::factory()->for($this->tenant)->create();

        $currentOwner = CompanyMember::factory()
            ->for($this->tenant)
            ->for($company)
            ->owner()
            ->create([
                'account_id' => $this->account->id,
                'person_id' => $this->account->person_id,
            ]);

        $newOwnerAccount = ApplicantAccount::factory()->for($this->tenant)->withPerson()->create();
        $newOwner = CompanyMember::factory()
            ->for($this->tenant)
            ->for($company)
            ->admin()
            ->create([
                'account_id' => $newOwnerAccount->id,
                'person_id' => $newOwnerAccount->person_id,
            ]);

        $this->service->transferOwnership($company, $currentOwner, $newOwner);

        $currentOwner->refresh();
        $newOwner->refresh();

        $this->assertEquals(CompanyMember::ROLE_ADMIN, $currentOwner->role);
        $this->assertEquals(CompanyMember::ROLE_OWNER, $newOwner->role);
    }

    public function test_cannot_transfer_ownership_from_non_owner(): void
    {
        $company = Company::factory()->for($this->tenant)->create();

        $admin = CompanyMember::factory()
            ->for($this->tenant)
            ->for($company)
            ->admin()
            ->create();

        $newOwner = CompanyMember::factory()
            ->for($this->tenant)
            ->for($company)
            ->viewer()
            ->create();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Current user is not an owner');

        $this->service->transferOwnership($company, $admin, $newOwner);
    }
}
