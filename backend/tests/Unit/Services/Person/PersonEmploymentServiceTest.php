<?php

namespace Tests\Unit\Services\Person;

use App\Models\Person;
use App\Models\PersonEmployment;
use App\Models\Tenant;
use App\Services\Person\PersonEmploymentService;
use Tests\TestCase;

class PersonEmploymentServiceTest extends TestCase
{
    protected PersonEmploymentService $service;
    protected ?Person $person = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PersonEmploymentService();
        $this->tenant = Tenant::factory()->create();
        $this->person = Person::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    public function test_can_create_employment(): void
    {
        $employment = $this->service->create($this->person, [
            'employment_type' => 'EMPLOYEE',
            'employer_name' => 'Empresa SA',
            'job_title' => 'Gerente',
            'monthly_income' => 30000.00,
            'is_current' => true,
            'start_date' => now()->subYear(),
        ]);

        $this->assertDatabaseHas('person_employments', [
            'id' => $employment->id,
            'employer_name' => 'Empresa SA',
        ]);
    }

    public function test_can_get_current(): void
    {
        PersonEmployment::factory()->employee()->current()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
            'employer_name' => 'Current Employer',
        ]);
        PersonEmployment::factory()->employee()->past()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
            'employer_name' => 'Old Employer',
        ]);

        $current = $this->service->getCurrent($this->person->id);

        $this->assertNotNull($current);
        $this->assertEquals('Current Employer', $current->employer_name);
    }

    public function test_can_verify_employment(): void
    {
        $employment = PersonEmployment::factory()->employee()->pending()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $verified = $this->service->verify($employment, 'PHONE_CALL', null, 'Confirmed with HR');

        $this->assertEquals(PersonEmployment::STATUS_VERIFIED, $verified->status);
        $this->assertNotNull($verified->verified_at);
        $this->assertEquals('PHONE_CALL', $verified->verification_method);
    }

    public function test_can_verify_income(): void
    {
        $employment = PersonEmployment::factory()->employee()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
            'monthly_income' => 25000.00,
        ]);

        $verified = $this->service->verifyIncome($employment, 24500.00, 'PAYSLIP', null);

        $this->assertTrue($verified->income_verified);
        $this->assertEquals(24500.00, $verified->verified_income);
        $this->assertEquals('PAYSLIP', $verified->income_verification_method);
    }

    public function test_can_reject_employment(): void
    {
        $employment = PersonEmployment::factory()->employee()->pending()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $rejected = $this->service->reject($employment, 'Employer not found');

        $this->assertEquals(PersonEmployment::STATUS_REJECTED, $rejected->status);
    }

    public function test_has_verified_current(): void
    {
        PersonEmployment::factory()->employee()->verified()->current()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $this->assertTrue($this->service->hasVerifiedCurrent($this->person->id));
    }

    public function test_has_verified_income(): void
    {
        PersonEmployment::factory()->employee()->incomeVerified()->current()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $this->assertTrue($this->service->hasVerifiedIncome($this->person->id));
    }

    public function test_calculate_dti(): void
    {
        PersonEmployment::factory()->employee()->current()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
            'monthly_income' => 50000.00,
        ]);

        $dti = $this->service->calculateDti($this->person->id, 15000.00);

        $this->assertEquals(30.0, $dti);
    }

    public function test_get_income_summary(): void
    {
        PersonEmployment::factory()->employee()->incomeVerified()->current()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
            'monthly_income' => 30000.00,
            'additional_income' => 5000.00,
            'verified_income' => 29500.00,
        ]);

        $summary = $this->service->getIncomeSummary($this->person->id);

        $this->assertTrue($summary['has_employment']);
        $this->assertEquals(30000.00, $summary['monthly_income']);
        $this->assertEquals(5000.00, $summary['additional_income']);
        $this->assertEquals(35000.00, $summary['total_income']);
        $this->assertTrue($summary['is_verified']);
    }
}
