<?php

namespace Tests\Feature\Api\Person;

use App\Models\Person;
use App\Models\PersonEmployment;
use App\Models\StaffAccount;
use App\Models\Tenant;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PersonEmploymentControllerTest extends TestCase
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

    public function test_can_list_employments(): void
    {
        PersonEmployment::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $response = $this->getJson("/api/persons/{$this->person->id}/employments");

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_can_create_employment(): void
    {
        $response = $this->postJson("/api/persons/{$this->person->id}/employments", [
            'employment_type' => 'EMPLOYEE',
            'employer_name' => 'Empresa ABC',
            'job_title' => 'Developer',
            'monthly_income' => 35000.00,
            'is_current' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.employer_name', 'Empresa ABC')
            ->assertJsonPath('data.monthly_income', '35000.00');
    }

    public function test_can_show_employment(): void
    {
        $employment = PersonEmployment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $response = $this->getJson("/api/persons/{$this->person->id}/employments/{$employment->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $employment->id);
    }

    public function test_can_update_employment(): void
    {
        $employment = PersonEmployment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $response = $this->putJson("/api/persons/{$this->person->id}/employments/{$employment->id}", [
            'job_title' => 'Senior Developer',
            'monthly_income' => 45000.00,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.job_title', 'Senior Developer')
            ->assertJsonPath('data.monthly_income', '45000.00');
    }

    public function test_can_delete_employment(): void
    {
        $employment = PersonEmployment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $response = $this->deleteJson("/api/persons/{$this->person->id}/employments/{$employment->id}");

        $response->assertOk();
        $this->assertSoftDeleted('person_employments', ['id' => $employment->id]);
    }

    public function test_can_get_current_employment(): void
    {
        PersonEmployment::factory()->current()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
            'employer_name' => 'Current Employer',
        ]);

        $response = $this->getJson("/api/persons/{$this->person->id}/employments/current");

        $response->assertOk()
            ->assertJsonPath('found', true)
            ->assertJsonPath('data.employer_name', 'Current Employer');
    }

    public function test_can_set_current_employment(): void
    {
        $response = $this->postJson("/api/persons/{$this->person->id}/employments/current", [
            'employment_type' => 'EMPLOYEE',
            'employer_name' => 'Nueva Empresa',
            'job_title' => 'Manager',
            'monthly_income' => 50000.00,
        ]);

        $response->assertSuccessful()
            ->assertJsonPath('data.employer_name', 'Nueva Empresa')
            ->assertJsonPath('data.is_current', true);
    }

    public function test_can_verify_employment(): void
    {
        $employment = PersonEmployment::factory()->pending()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $response = $this->postJson("/api/persons/{$this->person->id}/employments/{$employment->id}/verify", [
            'method' => 'PHONE_CALL',
            'verification_data' => ['contacted' => true],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'VERIFIED')
            ->assertJsonPath('data.verification_method', 'PHONE_CALL');
    }

    public function test_can_verify_income(): void
    {
        $employment = PersonEmployment::factory()->verified()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
            'monthly_income' => 30000.00,
        ]);

        $response = $this->postJson("/api/persons/{$this->person->id}/employments/{$employment->id}/verify-income", [
            'verified_income' => 32000.00,
            'verification_data' => ['source' => 'payslip'],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.income_verified', true)
            ->assertJsonPath('data.verified_income', '32000.00');
    }

    public function test_can_reject_employment(): void
    {
        $employment = PersonEmployment::factory()->pending()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $response = $this->postJson("/api/persons/{$this->person->id}/employments/{$employment->id}/reject", [
            'reason' => 'Could not verify employer',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'REJECTED');
    }

    public function test_can_end_employment(): void
    {
        $employment = PersonEmployment::factory()->current()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $response = $this->postJson("/api/persons/{$this->person->id}/employments/{$employment->id}/end", [
            'end_date' => '2024-12-31',
            'reason' => 'Resigned',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.is_current', false);
    }

    public function test_can_calculate_dti(): void
    {
        PersonEmployment::factory()->verified()->incomeVerified()->current()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
            'verified_income' => 50000.00,
        ]);

        $response = $this->postJson("/api/persons/{$this->person->id}/employments/calculate-dti", [
            'proposed_payment' => 10000.00,
        ]);

        $response->assertOk()
            ->assertJson(['dti_ratio' => 20]);
    }

    public function test_can_get_income_summary(): void
    {
        PersonEmployment::factory()->verified()->incomeVerified()->current()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
            'monthly_income' => 40000.00,
            'verified_income' => 42000.00,
        ]);

        $response = $this->getJson("/api/persons/{$this->person->id}/employments/income-summary");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'has_employment',
                    'monthly_income',
                    'verified_income',
                    'is_verified',
                ]
            ]);
    }

    public function test_can_check_has_verified_current(): void
    {
        PersonEmployment::factory()->verified()->current()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $response = $this->getJson("/api/persons/{$this->person->id}/employments/has-verified-current");

        $response->assertOk()
            ->assertJsonPath('has_verified_current', true);
    }

    public function test_can_check_has_verified_income(): void
    {
        PersonEmployment::factory()->incomeVerified()->current()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $response = $this->getJson("/api/persons/{$this->person->id}/employments/has-verified-income");

        $response->assertOk()
            ->assertJsonPath('has_verified_income', true);
    }
}
