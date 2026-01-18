<?php

namespace Tests\Feature\Api\Person;

use App\Models\Person;
use App\Models\PersonBankAccount;
use App\Models\StaffAccount;
use App\Models\Tenant;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PersonBankAccountControllerTest extends TestCase
{
    protected StaffAccount $staff;
    protected Person $person;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();
        $this->staff = StaffAccount::factory()->admin()->create(['tenant_id' => $this->tenant->id]);
        $this->person = Person::factory()->create(['tenant_id' => $this->tenant->id]);
        Sanctum::actingAs($this->staff, ['*']);
    }

    public function test_can_list_bank_accounts(): void
    {
        PersonBankAccount::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'owner_type' => 'persons',
            'owner_id' => $this->person->id,
        ]);

        $response = $this->getJson("/api/persons/{$this->person->id}/bank-accounts");

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_can_create_bank_account(): void
    {
        $response = $this->postJson("/api/persons/{$this->person->id}/bank-accounts", [
            'bank_name' => 'BBVA México',
            'bank_code' => '012',
            'clabe' => '012180001234567899',
            'holder_name' => 'Juan García López',
            'account_type' => 'DEBIT',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.bank_name', 'BBVA México');
    }

    public function test_create_validates_clabe(): void
    {
        $response = $this->postJson("/api/persons/{$this->person->id}/bank-accounts", [
            'bank_name' => 'BBVA México',
            'clabe' => '012180001234567890', // Invalid check digit
            'holder_name' => 'Juan García',
        ]);

        $response->assertUnprocessable();
    }

    public function test_can_show_bank_account(): void
    {
        $account = PersonBankAccount::factory()->create([
            'tenant_id' => $this->tenant->id,
            'owner_type' => 'persons',
            'owner_id' => $this->person->id,
        ]);

        $response = $this->getJson("/api/persons/{$this->person->id}/bank-accounts/{$account->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $account->id);
    }

    public function test_can_update_bank_account(): void
    {
        $account = PersonBankAccount::factory()->create([
            'tenant_id' => $this->tenant->id,
            'owner_type' => 'persons',
            'owner_id' => $this->person->id,
        ]);

        $response = $this->putJson("/api/persons/{$this->person->id}/bank-accounts/{$account->id}", [
            'holder_name' => 'Updated Name',
            'is_for_disbursement' => true,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.holder_name', 'Updated Name')
            ->assertJsonPath('data.is_for_disbursement', true);
    }

    public function test_can_delete_bank_account(): void
    {
        $account = PersonBankAccount::factory()->create([
            'tenant_id' => $this->tenant->id,
            'owner_type' => 'persons',
            'owner_id' => $this->person->id,
        ]);

        $response = $this->deleteJson("/api/persons/{$this->person->id}/bank-accounts/{$account->id}");

        $response->assertOk();
        $this->assertSoftDeleted('person_bank_accounts', ['id' => $account->id]);
    }

    public function test_can_get_primary(): void
    {
        PersonBankAccount::factory()->primary()->create([
            'tenant_id' => $this->tenant->id,
            'owner_type' => 'persons',
            'owner_id' => $this->person->id,
            'bank_name' => 'Primary Bank',
        ]);

        $response = $this->getJson("/api/persons/{$this->person->id}/bank-accounts/primary");

        $response->assertOk()
            ->assertJsonPath('found', true)
            ->assertJsonPath('data.bank_name', 'Primary Bank');
    }

    public function test_can_set_primary(): void
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

        $response = $this->postJson("/api/persons/{$this->person->id}/bank-accounts/{$account2->id}/set-primary");

        $response->assertOk()
            ->assertJsonPath('data.is_primary', true);

        $this->assertFalse($account1->fresh()->is_primary);
    }

    public function test_can_set_primary_account_with_data(): void
    {
        $response = $this->postJson("/api/persons/{$this->person->id}/bank-accounts/primary", [
            'bank_name' => 'BBVA México',
            'bank_code' => '012',
            'clabe' => '012180001234567899',
            'holder_name' => 'Juan García',
        ]);

        $response->assertSuccessful()
            ->assertJsonPath('data.is_primary', true)
            ->assertJsonPath('data.bank_name', 'BBVA México');
    }

    public function test_can_verify_account(): void
    {
        $account = PersonBankAccount::factory()->unverified()->create([
            'tenant_id' => $this->tenant->id,
            'owner_type' => 'persons',
            'owner_id' => $this->person->id,
        ]);

        $response = $this->postJson("/api/persons/{$this->person->id}/bank-accounts/{$account->id}/verify", [
            'method' => 'MICRO_DEPOSIT',
            'verification_data' => ['amount' => 0.01],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.is_verified', true)
            ->assertJsonPath('data.verification_method', 'MICRO_DEPOSIT');
    }

    public function test_can_unverify_account(): void
    {
        $account = PersonBankAccount::factory()->verified()->create([
            'tenant_id' => $this->tenant->id,
            'owner_type' => 'persons',
            'owner_id' => $this->person->id,
        ]);

        $response = $this->postJson("/api/persons/{$this->person->id}/bank-accounts/{$account->id}/unverify");

        $response->assertOk()
            ->assertJsonPath('data.is_verified', false);
    }

    public function test_can_deactivate_account(): void
    {
        $account = PersonBankAccount::factory()->active()->create([
            'tenant_id' => $this->tenant->id,
            'owner_type' => 'persons',
            'owner_id' => $this->person->id,
        ]);

        $response = $this->postJson("/api/persons/{$this->person->id}/bank-accounts/{$account->id}/deactivate");

        $response->assertOk()
            ->assertJsonPath('data.status', 'INACTIVE');
    }

    public function test_can_reactivate_account(): void
    {
        $account = PersonBankAccount::factory()->inactive()->create([
            'tenant_id' => $this->tenant->id,
            'owner_type' => 'persons',
            'owner_id' => $this->person->id,
        ]);

        $response = $this->postJson("/api/persons/{$this->person->id}/bank-accounts/{$account->id}/reactivate");

        $response->assertOk()
            ->assertJsonPath('data.status', 'ACTIVE');
    }

    public function test_can_close_account(): void
    {
        $account = PersonBankAccount::factory()->active()->create([
            'tenant_id' => $this->tenant->id,
            'owner_type' => 'persons',
            'owner_id' => $this->person->id,
        ]);

        $response = $this->postJson("/api/persons/{$this->person->id}/bank-accounts/{$account->id}/close");

        $response->assertOk()
            ->assertJsonPath('data.status', 'CLOSED');
    }

    public function test_can_freeze_account(): void
    {
        $account = PersonBankAccount::factory()->active()->create([
            'tenant_id' => $this->tenant->id,
            'owner_type' => 'persons',
            'owner_id' => $this->person->id,
        ]);

        $response = $this->postJson("/api/persons/{$this->person->id}/bank-accounts/{$account->id}/freeze");

        $response->assertOk()
            ->assertJsonPath('data.status', 'FROZEN');
    }

    public function test_can_get_for_disbursement(): void
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

        $response = $this->getJson("/api/persons/{$this->person->id}/bank-accounts/for-disbursement");

        $response->assertOk()
            ->assertJsonCount(1, 'data'); // Only verified
    }

    public function test_can_check_can_receive_disbursement(): void
    {
        PersonBankAccount::factory()->verified()->active()->forDisbursement()->create([
            'tenant_id' => $this->tenant->id,
            'owner_type' => 'persons',
            'owner_id' => $this->person->id,
        ]);

        $response = $this->getJson("/api/persons/{$this->person->id}/bank-accounts/can-receive-disbursement");

        $response->assertOk()
            ->assertJsonPath('can_receive_disbursement', true);
    }

    public function test_can_get_summary(): void
    {
        PersonBankAccount::factory()->primary()->verified()->active()->create([
            'tenant_id' => $this->tenant->id,
            'owner_type' => 'persons',
            'owner_id' => $this->person->id,
            'bank_name' => 'BBVA',
            'is_for_disbursement' => true,
        ]);

        $response = $this->getJson("/api/persons/{$this->person->id}/bank-accounts/summary");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'total',
                    'verified',
                    'active',
                    'has_primary',
                    'primary_bank',
                    'can_receive_disbursement',
                ]
            ]);
    }

    public function test_can_validate_clabe(): void
    {
        $response = $this->postJson('/api/persons/validate-clabe', [
            'clabe' => '012180001234567899',
        ]);

        $response->assertOk()
            ->assertJsonPath('is_valid', true)
            ->assertJsonPath('bank_code', '012')
            ->assertJsonPath('bank_name', 'BBVA México');
    }

    public function test_validate_clabe_returns_invalid(): void
    {
        $response = $this->postJson('/api/persons/validate-clabe', [
            'clabe' => '012180001234567890', // Invalid check digit
        ]);

        $response->assertOk()
            ->assertJsonPath('is_valid', false);
    }

    public function test_can_get_bank_name(): void
    {
        $response = $this->getJson('/api/persons/bank/012');

        $response->assertOk()
            ->assertJsonPath('bank_code', '012')
            ->assertJsonPath('bank_name', 'BBVA México');
    }

    public function test_can_check_has_verified(): void
    {
        PersonBankAccount::factory()->verified()->create([
            'tenant_id' => $this->tenant->id,
            'owner_type' => 'persons',
            'owner_id' => $this->person->id,
        ]);

        $response = $this->getJson("/api/persons/{$this->person->id}/bank-accounts/has-verified");

        $response->assertOk()
            ->assertJsonPath('has_verified', true);
    }
}
