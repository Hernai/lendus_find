<?php

namespace Tests\Unit\Services\Person;

use App\Models\Person;
use App\Models\PersonBankAccount;
use App\Models\Tenant;
use App\Services\Person\PersonBankAccountService;
use Tests\TestCase;

class PersonBankAccountServiceTest extends TestCase
{
    protected PersonBankAccountService $service;
    protected ?Person $person = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PersonBankAccountService();
        $this->tenant = Tenant::factory()->create();
        $this->person = Person::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    public function test_can_create_bank_account(): void
    {
        $account = $this->service->create($this->person, [
            'bank_name' => 'BBVA México',
            'bank_code' => '012',
            'clabe' => '012180000000000015',
            'account_type' => 'DEBIT',
        ]);

        $this->assertDatabaseHas('person_bank_accounts', [
            'id' => $account->id,
            'bank_name' => 'BBVA México',
        ]);
    }

    public function test_creating_primary_unsets_existing_primary(): void
    {
        $old = $this->service->create($this->person, [
            'bank_name' => 'BBVA',
            'clabe' => '012180000000000015',
            'holder_name' => 'Test',
            'is_primary' => true,
        ]);

        $new = $this->service->create($this->person, [
            'bank_name' => 'Banorte',
            'clabe' => '072180000000000023',
            'holder_name' => 'Test',
            'is_primary' => true,
        ]);

        $this->assertFalse($old->fresh()->is_primary);
        $this->assertTrue($new->is_primary);
    }

    public function test_can_get_primary(): void
    {
        PersonBankAccount::factory()->primary()->create([
            'tenant_id' => $this->tenant->id,
            'owner_type' => 'persons',
            'owner_id' => $this->person->id,
            'bank_name' => 'Primary Bank',
        ]);
        PersonBankAccount::factory()->notPrimary()->create([
            'tenant_id' => $this->tenant->id,
            'owner_type' => 'persons',
            'owner_id' => $this->person->id,
            'bank_name' => 'Secondary Bank',
        ]);

        $primary = $this->service->getPrimary($this->person->id);

        $this->assertNotNull($primary);
        $this->assertEquals('Primary Bank', $primary->bank_name);
    }

    public function test_can_set_as_primary(): void
    {
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

        $this->service->setPrimary($account2);

        $this->assertFalse($account1->fresh()->is_primary);
        $this->assertTrue($account2->fresh()->is_primary);
    }

    public function test_can_verify_account(): void
    {
        $account = PersonBankAccount::factory()->unverified()->create([
            'tenant_id' => $this->tenant->id,
            'owner_type' => 'persons',
            'owner_id' => $this->person->id,
        ]);

        $verified = $this->service->verify($account, 'MICRO_DEPOSIT', null, ['amount' => 0.01]);

        $this->assertTrue($verified->is_verified);
        $this->assertNotNull($verified->verified_at);
        $this->assertEquals('MICRO_DEPOSIT', $verified->verification_method);
    }

    public function test_can_unverify_account(): void
    {
        $account = PersonBankAccount::factory()->verified()->create([
            'tenant_id' => $this->tenant->id,
            'owner_type' => 'persons',
            'owner_id' => $this->person->id,
        ]);

        $unverified = $this->service->unverify($account);

        $this->assertFalse($unverified->is_verified);
        $this->assertNull($unverified->verified_at);
    }

    public function test_can_deactivate_account(): void
    {
        $account = PersonBankAccount::factory()->active()->create([
            'tenant_id' => $this->tenant->id,
            'owner_type' => 'persons',
            'owner_id' => $this->person->id,
        ]);

        $deactivated = $this->service->deactivate($account);

        $this->assertEquals(PersonBankAccount::STATUS_INACTIVE, $deactivated->status);
    }

    public function test_can_close_account(): void
    {
        $account = PersonBankAccount::factory()->active()->primary()->create([
            'tenant_id' => $this->tenant->id,
            'owner_type' => 'persons',
            'owner_id' => $this->person->id,
        ]);

        $closed = $this->service->close($account);

        $this->assertEquals(PersonBankAccount::STATUS_CLOSED, $closed->status);
        $this->assertFalse($closed->is_primary);
    }

    public function test_can_freeze_account(): void
    {
        $account = PersonBankAccount::factory()->active()->create([
            'tenant_id' => $this->tenant->id,
            'owner_type' => 'persons',
            'owner_id' => $this->person->id,
        ]);

        $frozen = $this->service->freeze($account);

        $this->assertEquals(PersonBankAccount::STATUS_FROZEN, $frozen->status);
    }

    public function test_can_reactivate_account(): void
    {
        $account = PersonBankAccount::factory()->inactive()->create([
            'tenant_id' => $this->tenant->id,
            'owner_type' => 'persons',
            'owner_id' => $this->person->id,
        ]);

        $reactivated = $this->service->reactivate($account);

        $this->assertEquals(PersonBankAccount::STATUS_ACTIVE, $reactivated->status);
    }

    public function test_set_primary_account_creates_new(): void
    {
        $account = $this->service->setPrimaryAccount($this->person, [
            'bank_name' => 'BBVA México',
            'bank_code' => '012',
            'clabe' => '012180000000000015',
            'holder_name' => 'Test User',
        ]);

        $this->assertTrue($account->is_primary);
        $this->assertEquals('BBVA México', $account->bank_name);
    }

    public function test_set_primary_account_updates_existing_clabe(): void
    {
        $existing = PersonBankAccount::factory()->notPrimary()->create([
            'tenant_id' => $this->tenant->id,
            'owner_type' => 'persons',
            'owner_id' => $this->person->id,
            'clabe' => '012180000000000015',
            'bank_name' => 'Old Name',
        ]);

        $updated = $this->service->setPrimaryAccount($this->person, [
            'bank_name' => 'BBVA México',
            'bank_code' => '012',
            'clabe' => '012180000000000015',
            'holder_name' => 'Updated Name',
        ]);

        $this->assertEquals($existing->id, $updated->id);
        $this->assertTrue($updated->is_primary);
        $this->assertEquals('BBVA México', $updated->bank_name);
    }

    public function test_get_for_disbursement(): void
    {
        PersonBankAccount::factory()->verified()->active()->forDisbursement()->create([
            'tenant_id' => $this->tenant->id,
            'owner_type' => 'persons',
            'owner_id' => $this->person->id,
        ]);
        PersonBankAccount::factory()->unverified()->active()->forDisbursement()->create([
            'tenant_id' => $this->tenant->id,
            'owner_type' => 'persons',
            'owner_id' => $this->person->id,
        ]);

        $accounts = $this->service->getForDisbursement($this->person->id);

        $this->assertCount(1, $accounts); // Only verified one
    }

    public function test_can_receive_disbursement(): void
    {
        PersonBankAccount::factory()->verified()->active()->forDisbursement()->create([
            'tenant_id' => $this->tenant->id,
            'owner_type' => 'persons',
            'owner_id' => $this->person->id,
        ]);

        $this->assertTrue($this->service->canReceiveDisbursement($this->person->id));
    }

    public function test_cannot_receive_disbursement_without_verified(): void
    {
        PersonBankAccount::factory()->unverified()->active()->forDisbursement()->create([
            'tenant_id' => $this->tenant->id,
            'owner_type' => 'persons',
            'owner_id' => $this->person->id,
        ]);

        $this->assertFalse($this->service->canReceiveDisbursement($this->person->id));
    }

    public function test_validate_clabe(): void
    {
        $this->assertTrue($this->service->validateClabe('012180001234567899'));
        $this->assertFalse($this->service->validateClabe('012180001234567890'));
    }

    public function test_extract_bank_code(): void
    {
        $this->assertEquals('012', $this->service->extractBankCode('012180001234567899'));
        $this->assertEquals('072', $this->service->extractBankCode('072180001234567890'));
    }

    public function test_get_bank_name(): void
    {
        $this->assertEquals('BBVA México', $this->service->getBankName('012'));
        $this->assertEquals('Banorte', $this->service->getBankName('072'));
        $this->assertNull($this->service->getBankName('999'));
    }

    public function test_get_summary(): void
    {
        PersonBankAccount::factory()->primary()->verified()->active()->create([
            'tenant_id' => $this->tenant->id,
            'owner_type' => 'persons',
            'owner_id' => $this->person->id,
            'bank_name' => 'BBVA',
            'is_for_disbursement' => true,
        ]);
        PersonBankAccount::factory()->notPrimary()->unverified()->active()->create([
            'tenant_id' => $this->tenant->id,
            'owner_type' => 'persons',
            'owner_id' => $this->person->id,
        ]);

        $summary = $this->service->getSummary($this->person->id);

        $this->assertEquals(2, $summary['total']);
        $this->assertEquals(1, $summary['verified']);
        $this->assertEquals(2, $summary['active']);
        $this->assertTrue($summary['has_primary']);
        $this->assertEquals('BBVA', $summary['primary_bank']);
        $this->assertTrue($summary['can_receive_disbursement']);
    }

    public function test_has_verified(): void
    {
        PersonBankAccount::factory()->verified()->create([
            'tenant_id' => $this->tenant->id,
            'owner_type' => 'persons',
            'owner_id' => $this->person->id,
        ]);

        $this->assertTrue($this->service->hasVerified($this->person->id));
    }

    public function test_find_by_clabe(): void
    {
        PersonBankAccount::factory()->create([
            'tenant_id' => $this->tenant->id,
            'owner_type' => 'persons',
            'owner_id' => $this->person->id,
            'clabe' => '012180001234567899',
        ]);

        $found = $this->service->findByClabe('012180001234567899', $this->tenant->id);

        $this->assertNotNull($found);
    }

    public function test_find_by_clabe_respects_tenant(): void
    {
        PersonBankAccount::factory()->create([
            'tenant_id' => $this->tenant->id,
            'owner_type' => 'persons',
            'owner_id' => $this->person->id,
            'clabe' => '012180001234567899',
        ]);

        $otherTenant = Tenant::factory()->create();
        $found = $this->service->findByClabe('012180001234567899', $otherTenant->id);

        $this->assertNull($found);
    }
}
