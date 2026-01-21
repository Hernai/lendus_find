<?php

namespace Tests\Unit\Models;

use App\Models\ApplicantAccount;
use App\Models\Person;
use App\Models\Address;
use App\Models\BankAccount;
use App\Models\PersonEmployment;
use App\Models\PersonIdentification;
use App\Models\PersonReference;
use App\Models\Tenant;
use Tests\TestCase;

class PersonTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();
    }

    // =====================================================
    // Basic Model Tests
    // =====================================================

    public function test_can_create_person(): void
    {
        $person = Person::factory()->create([
            'tenant_id' => $this->tenant->id,
            'first_name' => 'Juan',
            'last_name_1' => 'García',
            'last_name_2' => 'López',
        ]);

        $this->assertDatabaseHas('persons', [
            'id' => $person->id,
            'tenant_id' => $this->tenant->id,
            'first_name' => 'Juan',
            'last_name_1' => 'García',
            'last_name_2' => 'López',
        ]);
    }

    public function test_person_belongs_to_tenant(): void
    {
        $person = Person::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->assertEquals($this->tenant->id, $person->tenant->id);
    }

    // =====================================================
    // Full Name Accessor Tests
    // =====================================================

    public function test_full_name_with_both_last_names(): void
    {
        $person = Person::factory()->create([
            'tenant_id' => $this->tenant->id,
            'first_name' => 'María',
            'last_name_1' => 'González',
            'last_name_2' => 'Hernández',
        ]);

        $this->assertEquals('María González Hernández', $person->full_name);
    }

    public function test_full_name_without_second_last_name(): void
    {
        $person = Person::factory()->create([
            'tenant_id' => $this->tenant->id,
            'first_name' => 'Carlos',
            'last_name_1' => 'Rodríguez',
            'last_name_2' => null,
        ]);

        $this->assertEquals('Carlos Rodríguez', $person->full_name);
    }

    // =====================================================
    // Age Accessor Tests
    // =====================================================

    public function test_age_calculation(): void
    {
        $person = Person::factory()->create([
            'tenant_id' => $this->tenant->id,
            'birth_date' => now()->subYears(30)->subDays(10),
        ]);

        $this->assertEquals(30, $person->age);
    }

    public function test_age_returns_null_when_no_birth_date(): void
    {
        $person = Person::factory()->create([
            'tenant_id' => $this->tenant->id,
            'birth_date' => null,
        ]);

        $this->assertNull($person->age);
    }

    // =====================================================
    // KYC Status Tests
    // =====================================================

    public function test_is_kyc_verified_returns_true_when_verified(): void
    {
        $person = Person::factory()->kycVerified()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $this->assertTrue($person->is_kyc_verified);
    }

    public function test_is_kyc_verified_returns_false_when_pending(): void
    {
        $person = Person::factory()->kycPending()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $this->assertFalse($person->is_kyc_verified);
    }

    public function test_update_kyc_status(): void
    {
        $person = Person::factory()->kycPending()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $person->updateKycStatus('VERIFIED', ['curp_verified' => true], null);

        $this->assertEquals('VERIFIED', $person->fresh()->kyc_status);
        $this->assertNotNull($person->fresh()->kyc_verified_at);
        $this->assertNull($person->fresh()->kyc_verified_by);
    }

    // =====================================================
    // Relationship Tests
    // =====================================================

    public function test_person_can_have_account(): void
    {
        $account = ApplicantAccount::factory()->create(['tenant_id' => $this->tenant->id]);
        $person = Person::factory()->create([
            'tenant_id' => $this->tenant->id,
            'account_id' => $account->id,
        ]);

        $this->assertEquals($account->id, $person->account->id);
    }

    public function test_person_has_many_identifications(): void
    {
        $person = Person::factory()->create(['tenant_id' => $this->tenant->id]);

        PersonIdentification::factory()->curp()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $person->id,
        ]);
        PersonIdentification::factory()->rfc()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $person->id,
        ]);

        $this->assertCount(2, $person->identifications);
    }

    public function test_person_has_many_addresses(): void
    {
        $person = Person::factory()->create(['tenant_id' => $this->tenant->id]);

        Address::factory()->home()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $person->id,
        ]);
        Address::factory()->work()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $person->id,
        ]);

        $this->assertCount(2, $person->addresses);
    }

    public function test_person_has_many_employments(): void
    {
        $person = Person::factory()->create(['tenant_id' => $this->tenant->id]);

        PersonEmployment::factory()->current()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $person->id,
        ]);
        PersonEmployment::factory()->past()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $person->id,
        ]);

        $this->assertCount(2, $person->employments);
    }

    public function test_person_has_many_references(): void
    {
        $person = Person::factory()->create(['tenant_id' => $this->tenant->id]);

        PersonReference::factory()->personal()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $person->id,
        ]);
        PersonReference::factory()->work()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $person->id,
        ]);

        $this->assertCount(2, $person->references);
    }

    public function test_person_has_many_bank_accounts(): void
    {
        $person = Person::factory()->create(['tenant_id' => $this->tenant->id]);

        BankAccount::factory()->create([
            'tenant_id' => $this->tenant->id,
            'owner_type' => 'persons',
            'owner_id' => $person->id,
        ]);
        BankAccount::factory()->create([
            'tenant_id' => $this->tenant->id,
            'owner_type' => 'persons',
            'owner_id' => $person->id,
        ]);

        $this->assertCount(2, $person->bankAccounts);
    }

    // =====================================================
    // Current Record Relationship Tests
    // =====================================================

    public function test_current_curp_relationship(): void
    {
        $person = Person::factory()->create(['tenant_id' => $this->tenant->id]);

        $curp = PersonIdentification::factory()->curp()->current()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $person->id,
            'identifier_value' => 'GALO850101HDFRPR09',
        ]);

        // Create old non-current CURP
        PersonIdentification::factory()->curp()->historical()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $person->id,
        ]);

        $this->assertEquals($curp->id, $person->currentCurp->id);
        $this->assertEquals('GALO850101HDFRPR09', $person->curp);
    }

    public function test_current_home_address_relationship(): void
    {
        $person = Person::factory()->create(['tenant_id' => $this->tenant->id]);

        $address = Address::factory()->home()->current()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $person->id,
        ]);

        // Create old non-current address
        Address::factory()->home()->historical()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $person->id,
        ]);

        $this->assertEquals($address->id, $person->currentHomeAddress->id);
    }

    public function test_current_employment_relationship(): void
    {
        $person = Person::factory()->create(['tenant_id' => $this->tenant->id]);

        $employment = PersonEmployment::factory()->current()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $person->id,
        ]);

        // Create past employment
        PersonEmployment::factory()->past()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $person->id,
        ]);

        $this->assertEquals($employment->id, $person->currentEmployment->id);
    }

    public function test_primary_bank_account_relationship(): void
    {
        $person = Person::factory()->create(['tenant_id' => $this->tenant->id]);

        $primaryAccount = BankAccount::factory()->primary()->create([
            'tenant_id' => $this->tenant->id,
            'owner_type' => 'persons',
            'owner_id' => $person->id,
        ]);

        // Create non-primary account
        BankAccount::factory()->notPrimary()->create([
            'tenant_id' => $this->tenant->id,
            'owner_type' => 'persons',
            'owner_id' => $person->id,
        ]);

        $this->assertEquals($primaryAccount->id, $person->primaryBankAccount->id);
    }

    // =====================================================
    // Label Accessor Tests
    // =====================================================

    public function test_marital_status_label(): void
    {
        $person = Person::factory()->married()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $this->assertEquals('Casado/a', $person->marital_status_label);
    }

    public function test_education_level_label(): void
    {
        $person = Person::factory()->create([
            'tenant_id' => $this->tenant->id,
            'education_level' => 'BACHELOR',
        ]);

        $this->assertEquals('Licenciatura', $person->education_level_label);
    }

    public function test_gender_label(): void
    {
        $person = Person::factory()->male()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $this->assertEquals('Masculino', $person->gender_label);
    }

    // =====================================================
    // Scope Tests
    // =====================================================

    public function test_kyc_verified_scope(): void
    {
        Person::factory()->kycVerified()->create(['tenant_id' => $this->tenant->id]);
        Person::factory()->kycPending()->create(['tenant_id' => $this->tenant->id]);
        Person::factory()->kycRejected()->create(['tenant_id' => $this->tenant->id]);

        $verified = Person::kycVerified()->count();

        $this->assertEquals(1, $verified);
    }

    public function test_profile_complete_scope(): void
    {
        Person::factory()->profileComplete()->create(['tenant_id' => $this->tenant->id]);
        Person::factory()->profileIncomplete()->create(['tenant_id' => $this->tenant->id]);

        $complete = Person::profileComplete()->count();

        $this->assertEquals(1, $complete);
    }

    public function test_search_by_name_scope(): void
    {
        Person::factory()->create([
            'tenant_id' => $this->tenant->id,
            'first_name' => 'Roberto',
            'last_name_1' => 'Sánchez',
        ]);
        Person::factory()->create([
            'tenant_id' => $this->tenant->id,
            'first_name' => 'Ana',
            'last_name_1' => 'Martínez',
        ]);

        $results = Person::searchByName('Roberto')->get();

        $this->assertCount(1, $results);
        $this->assertEquals('Roberto', $results->first()->first_name);
    }

    // =====================================================
    // Static Finder Tests
    // =====================================================

    public function test_find_by_curp(): void
    {
        $person = Person::factory()->create(['tenant_id' => $this->tenant->id]);

        PersonIdentification::factory()->curp()->current()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $person->id,
            'identifier_value' => 'GALO850101HDFRPR09',
        ]);

        $found = Person::findByCurp('GALO850101HDFRPR09', $this->tenant->id);

        $this->assertNotNull($found);
        $this->assertEquals($person->id, $found->id);
    }

    public function test_find_by_curp_returns_null_for_different_tenant(): void
    {
        $person = Person::factory()->create(['tenant_id' => $this->tenant->id]);

        PersonIdentification::factory()->curp()->current()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $person->id,
            'identifier_value' => 'GALO850101HDFRPR09',
        ]);

        $otherTenant = Tenant::factory()->create();
        $found = Person::findByCurp('GALO850101HDFRPR09', $otherTenant->id);

        $this->assertNull($found);
    }

    public function test_find_by_rfc(): void
    {
        $person = Person::factory()->create(['tenant_id' => $this->tenant->id]);

        PersonIdentification::factory()->rfc()->current()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $person->id,
            'identifier_value' => 'GALO850101AB1',
        ]);

        $found = Person::findByRfc('GALO850101AB1', $this->tenant->id);

        $this->assertNotNull($found);
        $this->assertEquals($person->id, $found->id);
    }

    // =====================================================
    // Soft Delete Tests
    // =====================================================

    public function test_person_can_be_soft_deleted(): void
    {
        $person = Person::factory()->create(['tenant_id' => $this->tenant->id]);

        $person->delete();

        $this->assertSoftDeleted('persons', ['id' => $person->id]);
        $this->assertNull(Person::find($person->id));
        $this->assertNotNull(Person::withTrashed()->find($person->id));
    }
}
