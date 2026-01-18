<?php

namespace Tests\Unit\Models;

use App\Models\ApplicantAccount;
use App\Models\Company;
use App\Models\CompanyAddress;
use App\Models\CompanyMember;
use App\Models\Person;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
    }

    public function test_can_create_company(): void
    {
        $account = ApplicantAccount::factory()->create(['tenant_id' => $this->tenant->id]);

        $company = Company::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by_account_id' => $account->id,
            'legal_name' => 'Empresa Test SA de CV',
            'rfc' => 'ETE200101ABC',
        ]);

        $this->assertDatabaseHas('companies', [
            'id' => $company->id,
            'legal_name' => 'Empresa Test SA de CV',
            'rfc' => 'ETE200101ABC',
        ]);
    }

    public function test_company_has_creator_relationship(): void
    {
        $account = ApplicantAccount::factory()->create(['tenant_id' => $this->tenant->id]);
        $company = Company::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by_account_id' => $account->id,
        ]);

        $this->assertInstanceOf(ApplicantAccount::class, $company->createdByAccount);
        $this->assertEquals($account->id, $company->createdByAccount->id);
    }

    public function test_company_has_members_relationship(): void
    {
        $account = ApplicantAccount::factory()->create(['tenant_id' => $this->tenant->id]);
        $person = Person::factory()->create(['tenant_id' => $this->tenant->id]);
        $company = Company::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by_account_id' => $account->id,
        ]);

        CompanyMember::factory()->create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $company->id,
            'person_id' => $person->id,
            'account_id' => $account->id,
        ]);

        $this->assertCount(1, $company->members);
    }

    public function test_company_has_addresses_relationship(): void
    {
        $account = ApplicantAccount::factory()->create(['tenant_id' => $this->tenant->id]);
        $company = Company::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by_account_id' => $account->id,
        ]);

        CompanyAddress::factory()->fiscal()->create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $company->id,
        ]);

        $this->assertCount(1, $company->addresses);
    }

    public function test_display_name_returns_trade_name_if_available(): void
    {
        $account = ApplicantAccount::factory()->create(['tenant_id' => $this->tenant->id]);
        $company = Company::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by_account_id' => $account->id,
            'legal_name' => 'Empresa Legal SA',
            'trade_name' => 'Mi Negocio',
        ]);

        $this->assertEquals('Mi Negocio', $company->display_name);
    }

    public function test_display_name_returns_legal_name_if_no_trade_name(): void
    {
        $account = ApplicantAccount::factory()->create(['tenant_id' => $this->tenant->id]);
        $company = Company::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by_account_id' => $account->id,
            'legal_name' => 'Empresa Legal SA',
            'trade_name' => null,
        ]);

        $this->assertEquals('Empresa Legal SA', $company->display_name);
    }

    public function test_is_verified_returns_correct_status(): void
    {
        $account = ApplicantAccount::factory()->create(['tenant_id' => $this->tenant->id]);

        $pendingCompany = Company::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by_account_id' => $account->id,
            'status' => Company::STATUS_PENDING,
        ]);

        $verifiedCompany = Company::factory()->verified()->create([
            'tenant_id' => $this->tenant->id,
            'created_by_account_id' => $account->id,
        ]);

        $this->assertFalse($pendingCompany->isVerified());
        $this->assertTrue($verifiedCompany->isVerified());
    }

    public function test_can_apply_for_credit_requires_verification(): void
    {
        $account = ApplicantAccount::factory()->create(['tenant_id' => $this->tenant->id]);

        $pendingCompany = Company::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by_account_id' => $account->id,
        ]);

        $verifiedCompany = Company::factory()->verified()->create([
            'tenant_id' => $this->tenant->id,
            'created_by_account_id' => $account->id,
        ]);

        $this->assertFalse($pendingCompany->canApplyForCredit());
        $this->assertTrue($verifiedCompany->canApplyForCredit());
    }

    public function test_get_legal_representatives(): void
    {
        $account = ApplicantAccount::factory()->create(['tenant_id' => $this->tenant->id]);
        $company = Company::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by_account_id' => $account->id,
        ]);

        // Owner (legal rep)
        $owner = Person::factory()->create(['tenant_id' => $this->tenant->id]);
        CompanyMember::factory()->owner()->create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $company->id,
            'person_id' => $owner->id,
            'account_id' => $account->id,
        ]);

        // Regular member (not legal rep)
        $employee = Person::factory()->create(['tenant_id' => $this->tenant->id]);
        $employeeAccount = ApplicantAccount::factory()->create(['tenant_id' => $this->tenant->id]);
        CompanyMember::factory()->viewer()->create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $company->id,
            'person_id' => $employee->id,
            'account_id' => $employeeAccount->id,
        ]);

        $legalReps = $company->getLegalRepresentatives();

        $this->assertCount(1, $legalReps);
        $this->assertEquals($owner->id, $legalReps->first()->person_id);
    }

    public function test_get_shareholders(): void
    {
        $account = ApplicantAccount::factory()->create(['tenant_id' => $this->tenant->id]);
        $company = Company::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by_account_id' => $account->id,
        ]);

        // Majority shareholder
        $major = Person::factory()->create(['tenant_id' => $this->tenant->id]);
        CompanyMember::factory()->shareholder(60)->create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $company->id,
            'person_id' => $major->id,
            'account_id' => $account->id,
        ]);

        // Minority shareholder
        $minor = Person::factory()->create(['tenant_id' => $this->tenant->id]);
        $minorAccount = ApplicantAccount::factory()->create(['tenant_id' => $this->tenant->id]);
        CompanyMember::factory()->shareholder(40)->create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $company->id,
            'person_id' => $minor->id,
            'account_id' => $minorAccount->id,
        ]);

        $shareholders = $company->getShareholders();

        $this->assertCount(2, $shareholders);
        // Should be ordered by ownership percentage desc
        $this->assertEquals(60.00, $shareholders->first()->ownership_percentage);
    }

    public function test_scope_verified(): void
    {
        $account = ApplicantAccount::factory()->create(['tenant_id' => $this->tenant->id]);

        Company::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by_account_id' => $account->id,
            'status' => Company::STATUS_PENDING,
        ]);

        Company::factory()->verified()->create([
            'tenant_id' => $this->tenant->id,
            'created_by_account_id' => $account->id,
        ]);

        $this->assertCount(1, Company::verified()->get());
    }

    public function test_scope_search(): void
    {
        $account = ApplicantAccount::factory()->create(['tenant_id' => $this->tenant->id]);

        Company::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by_account_id' => $account->id,
            'legal_name' => 'Empresa Alpha SA',
            'trade_name' => 'Alpha Corp',
        ]);

        Company::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by_account_id' => $account->id,
            'legal_name' => 'Empresa Beta SA',
            'trade_name' => 'Beta Inc',
        ]);

        $this->assertCount(1, Company::search('Alpha')->get());
        $this->assertCount(1, Company::search('Beta')->get());
        $this->assertCount(2, Company::search('SA')->get());
    }

    public function test_entity_types_returns_array(): void
    {
        $types = Company::entityTypes();

        $this->assertIsArray($types);
        $this->assertArrayHasKey('SA_DE_CV', $types);
        $this->assertEquals('Sociedad Anónima de Capital Variable', $types['SA_DE_CV']);
    }

    public function test_company_sizes_returns_array(): void
    {
        $sizes = Company::companySizes();

        $this->assertIsArray($sizes);
        $this->assertArrayHasKey('MICRO', $sizes);
        $this->assertArrayHasKey('LARGE', $sizes);
    }
}
