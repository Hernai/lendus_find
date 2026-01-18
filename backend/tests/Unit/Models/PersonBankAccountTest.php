<?php

namespace Tests\Unit\Models;

use App\Enums\BankAccountType;
use App\Models\Person;
use App\Models\PersonBankAccount;
use App\Models\Tenant;
use Tests\TestCase;

class PersonBankAccountTest extends TestCase
{
    private Person $person;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();
        $this->person = Person::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    // =====================================================
    // Basic Model Tests
    // =====================================================

    public function test_can_create_bank_account(): void
    {
        $account = PersonBankAccount::factory()->create([
            'tenant_id' => $this->tenant->id,
            'owner_type' => 'persons',
            'owner_id' => $this->person->id,
            'bank_name' => 'BBVA México',
            'clabe' => '012180001234567897',
        ]);

        $this->assertDatabaseHas('person_bank_accounts', [
            'id' => $account->id,
            'bank_name' => 'BBVA México',
            'clabe' => '012180001234567897',
        ]);
    }

    public function test_bank_account_belongs_to_person(): void
    {
        $account = PersonBankAccount::factory()->create([
            'tenant_id' => $this->tenant->id,
            'owner_type' => 'persons',
            'owner_id' => $this->person->id,
        ]);

        $this->assertEquals($this->person->id, $account->person->id);
    }

    // =====================================================
    // Status Tests
    // =====================================================

    public function test_is_active(): void
    {
        $active = PersonBankAccount::factory()->active()->create([
            'tenant_id' => $this->tenant->id,
            'owner_type' => 'persons',
            'owner_id' => $this->person->id,
        ]);

        $inactive = PersonBankAccount::factory()->inactive()->create([
            'tenant_id' => $this->tenant->id,
            'owner_type' => 'persons',
            'owner_id' => $this->person->id,
        ]);

        $this->assertTrue($active->isActive());
        $this->assertFalse($inactive->isActive());
    }

    public function test_can_receive_disbursement(): void
    {
        $canReceive = PersonBankAccount::factory()
            ->verified()
            ->active()
            ->forDisbursement()
            ->create([
                'tenant_id' => $this->tenant->id,
                'owner_type' => 'persons',
                'owner_id' => $this->person->id,
            ]);

        $cannotReceive = PersonBankAccount::factory()
            ->unverified()
            ->forDisbursement()
            ->create([
                'tenant_id' => $this->tenant->id,
                'owner_type' => 'persons',
                'owner_id' => $this->person->id,
            ]);

        $this->assertTrue($canReceive->canReceiveDisbursement());
        $this->assertFalse($cannotReceive->canReceiveDisbursement());
    }

    public function test_can_be_used_for_collection(): void
    {
        $canCollect = PersonBankAccount::factory()
            ->verified()
            ->active()
            ->forCollection()
            ->create([
                'tenant_id' => $this->tenant->id,
                'owner_type' => 'persons',
                'owner_id' => $this->person->id,
            ]);

        $cannotCollect = PersonBankAccount::factory()
            ->verified()
            ->inactive()
            ->forCollection()
            ->create([
                'tenant_id' => $this->tenant->id,
                'owner_type' => 'persons',
                'owner_id' => $this->person->id,
            ]);

        $this->assertTrue($canCollect->canBeUsedForCollection());
        $this->assertFalse($cannotCollect->canBeUsedForCollection());
    }

    // =====================================================
    // Masked CLABE Tests
    // =====================================================

    public function test_masked_clabe(): void
    {
        $account = PersonBankAccount::factory()->create([
            'tenant_id' => $this->tenant->id,
            'owner_type' => 'persons',
            'owner_id' => $this->person->id,
            'clabe' => '012180001234567897',
        ]);

        $this->assertEquals('0121**********7897', $account->masked_clabe);
    }

    // =====================================================
    // Label Tests
    // =====================================================

    public function test_account_type_label(): void
    {
        $debit = PersonBankAccount::factory()->debit()->create([
            'tenant_id' => $this->tenant->id,
            'owner_type' => 'persons',
            'owner_id' => $this->person->id,
        ]);

        $this->assertEquals('Débito', $debit->account_type_label);

        $payroll = PersonBankAccount::factory()->payroll()->create([
            'tenant_id' => $this->tenant->id,
            'owner_type' => 'persons',
            'owner_id' => $this->person->id,
        ]);

        $this->assertEquals('Nómina', $payroll->account_type_label);
    }

    public function test_status_label(): void
    {
        $active = PersonBankAccount::factory()->active()->create([
            'tenant_id' => $this->tenant->id,
            'owner_type' => 'persons',
            'owner_id' => $this->person->id,
        ]);

        $this->assertEquals('Activa', $active->status_label);

        $frozen = PersonBankAccount::factory()->frozen()->create([
            'tenant_id' => $this->tenant->id,
            'owner_type' => 'persons',
            'owner_id' => $this->person->id,
        ]);

        $this->assertEquals('Congelada', $frozen->status_label);
    }

    // =====================================================
    // Verification Methods Tests
    // =====================================================

    public function test_mark_as_verified(): void
    {
        $account = PersonBankAccount::factory()->unverified()->create([
            'tenant_id' => $this->tenant->id,
            'owner_type' => 'persons',
            'owner_id' => $this->person->id,
        ]);

        $account->markAsVerified('MICRO_DEPOSIT', null, ['deposit_amount' => 0.01]);

        $fresh = $account->fresh();
        $this->assertTrue($fresh->is_verified);
        $this->assertNotNull($fresh->verified_at);
        $this->assertNull($fresh->verified_by);
        $this->assertEquals('MICRO_DEPOSIT', $fresh->verification_method);
    }

    public function test_mark_as_unverified(): void
    {
        $account = PersonBankAccount::factory()->verified()->create([
            'tenant_id' => $this->tenant->id,
            'owner_type' => 'persons',
            'owner_id' => $this->person->id,
        ]);

        $account->markAsUnverified();

        $fresh = $account->fresh();
        $this->assertFalse($fresh->is_verified);
        $this->assertNull($fresh->verified_at);
        $this->assertNull($fresh->verified_by);
    }

    // =====================================================
    // Status Methods Tests
    // =====================================================

    public function test_set_as_primary(): void
    {
        // Create two accounts, one primary
        $account1 = PersonBankAccount::factory()->primary()->create([
            'tenant_id' => $this->tenant->id,
            'owner_type' => 'persons',
            'owner_id' => $this->person->id,
        ]);

        $account2 = PersonBankAccount::factory()->notPrimary()->create([
            'tenant_id' => $this->tenant->id,
            'owner_type' => 'persons',
            'owner_id' => $this->person->id,
        ]);

        // Set account2 as primary
        $account2->setAsPrimary();

        $this->assertFalse($account1->fresh()->is_primary);
        $this->assertTrue($account2->fresh()->is_primary);
    }

    public function test_deactivate(): void
    {
        $account = PersonBankAccount::factory()->active()->create([
            'tenant_id' => $this->tenant->id,
            'owner_type' => 'persons',
            'owner_id' => $this->person->id,
        ]);

        $account->deactivate();

        $this->assertEquals(PersonBankAccount::STATUS_INACTIVE, $account->fresh()->status);
    }

    public function test_close(): void
    {
        $account = PersonBankAccount::factory()->active()->primary()->create([
            'tenant_id' => $this->tenant->id,
            'owner_type' => 'persons',
            'owner_id' => $this->person->id,
        ]);

        $account->close();

        $fresh = $account->fresh();
        $this->assertEquals(PersonBankAccount::STATUS_CLOSED, $fresh->status);
        $this->assertFalse($fresh->is_primary);
    }

    public function test_freeze(): void
    {
        $account = PersonBankAccount::factory()->active()->create([
            'tenant_id' => $this->tenant->id,
            'owner_type' => 'persons',
            'owner_id' => $this->person->id,
        ]);

        $account->freeze();

        $this->assertEquals(PersonBankAccount::STATUS_FROZEN, $account->fresh()->status);
    }

    public function test_reactivate(): void
    {
        $account = PersonBankAccount::factory()->inactive()->create([
            'tenant_id' => $this->tenant->id,
            'owner_type' => 'persons',
            'owner_id' => $this->person->id,
        ]);

        $account->reactivate();

        $this->assertEquals(PersonBankAccount::STATUS_ACTIVE, $account->fresh()->status);
    }

    // =====================================================
    // CLABE Validation Tests
    // =====================================================

    public function test_is_valid_clabe_with_correct_check_digit(): void
    {
        // Valid CLABEs with correct check digits (calculated)
        $this->assertTrue(PersonBankAccount::isValidClabe('012180001234567899'));
        $this->assertTrue(PersonBankAccount::isValidClabe('014180123456789014'));
    }

    public function test_is_valid_clabe_with_incorrect_check_digit(): void
    {
        // Invalid CLABE (wrong check digit)
        $this->assertFalse(PersonBankAccount::isValidClabe('012180001234567890'));
    }

    public function test_is_valid_clabe_with_wrong_length(): void
    {
        $this->assertFalse(PersonBankAccount::isValidClabe('01218000123456789')); // 17 digits
        $this->assertFalse(PersonBankAccount::isValidClabe('0121800012345678901')); // 19 digits
    }

    public function test_is_valid_clabe_with_non_numeric(): void
    {
        $this->assertFalse(PersonBankAccount::isValidClabe('01218000123456789A'));
    }

    public function test_extract_bank_code(): void
    {
        $this->assertEquals('012', PersonBankAccount::extractBankCode('012180001234567897'));
        $this->assertEquals('072', PersonBankAccount::extractBankCode('072180001234567890'));
    }

    // =====================================================
    // Scope Tests
    // =====================================================

    public function test_verified_scope(): void
    {
        PersonBankAccount::factory()->verified()->create([
            'tenant_id' => $this->tenant->id,
            'owner_type' => 'persons',
            'owner_id' => $this->person->id,
        ]);
        PersonBankAccount::factory()->unverified()->create([
            'tenant_id' => $this->tenant->id,
            'owner_type' => 'persons',
            'owner_id' => $this->person->id,
        ]);

        $verified = PersonBankAccount::verified()->count();

        $this->assertEquals(1, $verified);
    }

    public function test_primary_scope(): void
    {
        PersonBankAccount::factory()->primary()->create([
            'tenant_id' => $this->tenant->id,
            'owner_type' => 'persons',
            'owner_id' => $this->person->id,
        ]);
        PersonBankAccount::factory()->notPrimary()->create([
            'tenant_id' => $this->tenant->id,
            'owner_type' => 'persons',
            'owner_id' => $this->person->id,
        ]);

        $primary = PersonBankAccount::primary()->count();

        $this->assertEquals(1, $primary);
    }

    public function test_active_scope(): void
    {
        PersonBankAccount::factory()->active()->create([
            'tenant_id' => $this->tenant->id,
            'owner_type' => 'persons',
            'owner_id' => $this->person->id,
        ]);
        PersonBankAccount::factory()->inactive()->create([
            'tenant_id' => $this->tenant->id,
            'owner_type' => 'persons',
            'owner_id' => $this->person->id,
        ]);
        PersonBankAccount::factory()->closed()->create([
            'tenant_id' => $this->tenant->id,
            'owner_type' => 'persons',
            'owner_id' => $this->person->id,
        ]);

        $active = PersonBankAccount::active()->count();

        $this->assertEquals(1, $active);
    }

    public function test_for_disbursement_scope(): void
    {
        PersonBankAccount::factory()->forDisbursement()->create([
            'tenant_id' => $this->tenant->id,
            'owner_type' => 'persons',
            'owner_id' => $this->person->id,
        ]);
        PersonBankAccount::factory()->create([
            'tenant_id' => $this->tenant->id,
            'owner_type' => 'persons',
            'owner_id' => $this->person->id,
            'is_for_disbursement' => false,
        ]);

        $forDisbursement = PersonBankAccount::forDisbursement()->count();

        $this->assertEquals(1, $forDisbursement);
    }

    // =====================================================
    // Static Finder Tests
    // =====================================================

    public function test_find_primary_for_person(): void
    {
        $primary = PersonBankAccount::factory()->primary()->create([
            'tenant_id' => $this->tenant->id,
            'owner_type' => 'persons',
            'owner_id' => $this->person->id,
        ]);

        PersonBankAccount::factory()->notPrimary()->create([
            'tenant_id' => $this->tenant->id,
            'owner_type' => 'persons',
            'owner_id' => $this->person->id,
        ]);

        $found = PersonBankAccount::findPrimaryForPerson($this->person->id);

        $this->assertNotNull($found);
        $this->assertEquals($primary->id, $found->id);
    }

    public function test_find_by_clabe(): void
    {
        $account = PersonBankAccount::factory()->create([
            'tenant_id' => $this->tenant->id,
            'owner_type' => 'persons',
            'owner_id' => $this->person->id,
            'clabe' => '012180001234567897',
        ]);

        $found = PersonBankAccount::findByClabe('012180001234567897', $this->tenant->id);

        $this->assertNotNull($found);
        $this->assertEquals($account->id, $found->id);
    }

    public function test_find_by_clabe_returns_null_for_different_tenant(): void
    {
        PersonBankAccount::factory()->create([
            'tenant_id' => $this->tenant->id,
            'owner_type' => 'persons',
            'owner_id' => $this->person->id,
            'clabe' => '012180001234567897',
        ]);

        $otherTenant = Tenant::factory()->create();
        $found = PersonBankAccount::findByClabe('012180001234567897', $otherTenant->id);

        $this->assertNull($found);
    }

    public function test_get_for_person(): void
    {
        PersonBankAccount::factory()->primary()->create([
            'tenant_id' => $this->tenant->id,
            'owner_type' => 'persons',
            'owner_id' => $this->person->id,
        ]);
        PersonBankAccount::factory()->notPrimary()->create([
            'tenant_id' => $this->tenant->id,
            'owner_type' => 'persons',
            'owner_id' => $this->person->id,
        ]);
        PersonBankAccount::factory()->notPrimary()->create([
            'tenant_id' => $this->tenant->id,
            'owner_type' => 'persons',
            'owner_id' => $this->person->id,
        ]);

        $accounts = PersonBankAccount::getForPerson($this->person->id);

        $this->assertCount(3, $accounts);
        // Primary should be first
        $this->assertTrue($accounts->first()->is_primary);
    }
}
