<?php

namespace Tests\Unit\Models;

use App\Enums\BankAccountType;
use App\Models\Person;
use App\Models\BankAccount;
use App\Models\Tenant;
use Tests\TestCase;

class BankAccountTest extends TestCase
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
        $account = BankAccount::factory()->create([
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
        $account = BankAccount::factory()->create([
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
        $active = BankAccount::factory()->active()->create([
            'tenant_id' => $this->tenant->id,
            'owner_type' => 'persons',
            'owner_id' => $this->person->id,
        ]);

        $inactive = BankAccount::factory()->inactive()->create([
            'tenant_id' => $this->tenant->id,
            'owner_type' => 'persons',
            'owner_id' => $this->person->id,
        ]);

        $this->assertTrue($active->isActive());
        $this->assertFalse($inactive->isActive());
    }

    public function test_can_receive_disbursement(): void
    {
        $canReceive = BankAccount::factory()
            ->verified()
            ->active()
            ->forDisbursement()
            ->create([
                'tenant_id' => $this->tenant->id,
                'owner_type' => 'persons',
                'owner_id' => $this->person->id,
            ]);

        $cannotReceive = BankAccount::factory()
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
        $canCollect = BankAccount::factory()
            ->verified()
            ->active()
            ->forCollection()
            ->create([
                'tenant_id' => $this->tenant->id,
                'owner_type' => 'persons',
                'owner_id' => $this->person->id,
            ]);

        $cannotCollect = BankAccount::factory()
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
        $account = BankAccount::factory()->create([
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
        $debit = BankAccount::factory()->debit()->create([
            'tenant_id' => $this->tenant->id,
            'owner_type' => 'persons',
            'owner_id' => $this->person->id,
        ]);

        $this->assertEquals('Débito', $debit->account_type_label);

        $payroll = BankAccount::factory()->payroll()->create([
            'tenant_id' => $this->tenant->id,
            'owner_type' => 'persons',
            'owner_id' => $this->person->id,
        ]);

        $this->assertEquals('Nómina', $payroll->account_type_label);
    }

    public function test_status_label(): void
    {
        $active = BankAccount::factory()->active()->create([
            'tenant_id' => $this->tenant->id,
            'owner_type' => 'persons',
            'owner_id' => $this->person->id,
        ]);

        $this->assertEquals('Activa', $active->status_label);

        $frozen = BankAccount::factory()->frozen()->create([
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
        $account = BankAccount::factory()->unverified()->create([
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
        $account = BankAccount::factory()->verified()->create([
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
        $account1 = BankAccount::factory()->primary()->create([
            'tenant_id' => $this->tenant->id,
            'owner_type' => 'persons',
            'owner_id' => $this->person->id,
        ]);

        $account2 = BankAccount::factory()->notPrimary()->create([
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
        $account = BankAccount::factory()->active()->create([
            'tenant_id' => $this->tenant->id,
            'owner_type' => 'persons',
            'owner_id' => $this->person->id,
        ]);

        $account->deactivate();

        $this->assertEquals(BankAccount::STATUS_INACTIVE, $account->fresh()->status);
    }

    public function test_close(): void
    {
        $account = BankAccount::factory()->active()->primary()->create([
            'tenant_id' => $this->tenant->id,
            'owner_type' => 'persons',
            'owner_id' => $this->person->id,
        ]);

        $account->close();

        $fresh = $account->fresh();
        $this->assertEquals(BankAccount::STATUS_CLOSED, $fresh->status);
        $this->assertFalse($fresh->is_primary);
    }

    public function test_freeze(): void
    {
        $account = BankAccount::factory()->active()->create([
            'tenant_id' => $this->tenant->id,
            'owner_type' => 'persons',
            'owner_id' => $this->person->id,
        ]);

        $account->freeze();

        $this->assertEquals(BankAccount::STATUS_FROZEN, $account->fresh()->status);
    }

    public function test_reactivate(): void
    {
        $account = BankAccount::factory()->inactive()->create([
            'tenant_id' => $this->tenant->id,
            'owner_type' => 'persons',
            'owner_id' => $this->person->id,
        ]);

        $account->reactivate();

        $this->assertEquals(BankAccount::STATUS_ACTIVE, $account->fresh()->status);
    }

    // =====================================================
    // CLABE Validation Tests
    // =====================================================

    public function test_is_valid_clabe_with_correct_check_digit(): void
    {
        // Valid CLABEs with correct check digits (calculated)
        $this->assertTrue(BankAccount::isValidClabe('012180001234567899'));
        $this->assertTrue(BankAccount::isValidClabe('014180123456789014'));
    }

    public function test_is_valid_clabe_with_incorrect_check_digit(): void
    {
        // Invalid CLABE (wrong check digit)
        $this->assertFalse(BankAccount::isValidClabe('012180001234567890'));
    }

    public function test_is_valid_clabe_with_wrong_length(): void
    {
        $this->assertFalse(BankAccount::isValidClabe('01218000123456789')); // 17 digits
        $this->assertFalse(BankAccount::isValidClabe('0121800012345678901')); // 19 digits
    }

    public function test_is_valid_clabe_with_non_numeric(): void
    {
        $this->assertFalse(BankAccount::isValidClabe('01218000123456789A'));
    }

    public function test_extract_bank_code(): void
    {
        $this->assertEquals('012', BankAccount::extractBankCode('012180001234567897'));
        $this->assertEquals('072', BankAccount::extractBankCode('072180001234567890'));
    }

    // =====================================================
    // Scope Tests
    // =====================================================

    public function test_verified_scope(): void
    {
        BankAccount::factory()->verified()->create([
            'tenant_id' => $this->tenant->id,
            'owner_type' => 'persons',
            'owner_id' => $this->person->id,
        ]);
        BankAccount::factory()->unverified()->create([
            'tenant_id' => $this->tenant->id,
            'owner_type' => 'persons',
            'owner_id' => $this->person->id,
        ]);

        $verified = BankAccount::verified()->count();

        $this->assertEquals(1, $verified);
    }

    public function test_primary_scope(): void
    {
        BankAccount::factory()->primary()->create([
            'tenant_id' => $this->tenant->id,
            'owner_type' => 'persons',
            'owner_id' => $this->person->id,
        ]);
        BankAccount::factory()->notPrimary()->create([
            'tenant_id' => $this->tenant->id,
            'owner_type' => 'persons',
            'owner_id' => $this->person->id,
        ]);

        $primary = BankAccount::primary()->count();

        $this->assertEquals(1, $primary);
    }

    public function test_active_scope(): void
    {
        BankAccount::factory()->active()->create([
            'tenant_id' => $this->tenant->id,
            'owner_type' => 'persons',
            'owner_id' => $this->person->id,
        ]);
        BankAccount::factory()->inactive()->create([
            'tenant_id' => $this->tenant->id,
            'owner_type' => 'persons',
            'owner_id' => $this->person->id,
        ]);
        BankAccount::factory()->closed()->create([
            'tenant_id' => $this->tenant->id,
            'owner_type' => 'persons',
            'owner_id' => $this->person->id,
        ]);

        $active = BankAccount::active()->count();

        $this->assertEquals(1, $active);
    }

    public function test_for_disbursement_scope(): void
    {
        BankAccount::factory()->forDisbursement()->create([
            'tenant_id' => $this->tenant->id,
            'owner_type' => 'persons',
            'owner_id' => $this->person->id,
        ]);
        BankAccount::factory()->create([
            'tenant_id' => $this->tenant->id,
            'owner_type' => 'persons',
            'owner_id' => $this->person->id,
            'is_for_disbursement' => false,
        ]);

        $forDisbursement = BankAccount::forDisbursement()->count();

        $this->assertEquals(1, $forDisbursement);
    }

    // =====================================================
    // Static Finder Tests
    // =====================================================

    public function test_find_primary_for_person(): void
    {
        $primary = BankAccount::factory()->primary()->create([
            'tenant_id' => $this->tenant->id,
            'owner_type' => 'persons',
            'owner_id' => $this->person->id,
        ]);

        BankAccount::factory()->notPrimary()->create([
            'tenant_id' => $this->tenant->id,
            'owner_type' => 'persons',
            'owner_id' => $this->person->id,
        ]);

        $found = BankAccount::findPrimaryForPerson($this->person->id);

        $this->assertNotNull($found);
        $this->assertEquals($primary->id, $found->id);
    }

    public function test_find_by_clabe(): void
    {
        $account = BankAccount::factory()->create([
            'tenant_id' => $this->tenant->id,
            'owner_type' => 'persons',
            'owner_id' => $this->person->id,
            'clabe' => '012180001234567897',
        ]);

        $found = BankAccount::findByClabe('012180001234567897', $this->tenant->id);

        $this->assertNotNull($found);
        $this->assertEquals($account->id, $found->id);
    }

    public function test_find_by_clabe_returns_null_for_different_tenant(): void
    {
        BankAccount::factory()->create([
            'tenant_id' => $this->tenant->id,
            'owner_type' => 'persons',
            'owner_id' => $this->person->id,
            'clabe' => '012180001234567897',
        ]);

        $otherTenant = Tenant::factory()->create();
        $found = BankAccount::findByClabe('012180001234567897', $otherTenant->id);

        $this->assertNull($found);
    }

    public function test_get_for_person(): void
    {
        BankAccount::factory()->primary()->create([
            'tenant_id' => $this->tenant->id,
            'owner_type' => 'persons',
            'owner_id' => $this->person->id,
        ]);
        BankAccount::factory()->notPrimary()->create([
            'tenant_id' => $this->tenant->id,
            'owner_type' => 'persons',
            'owner_id' => $this->person->id,
        ]);
        BankAccount::factory()->notPrimary()->create([
            'tenant_id' => $this->tenant->id,
            'owner_type' => 'persons',
            'owner_id' => $this->person->id,
        ]);

        $accounts = BankAccount::getForPerson($this->person->id);

        $this->assertCount(3, $accounts);
        // Primary should be first
        $this->assertTrue($accounts->first()->is_primary);
    }
}
