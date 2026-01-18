<?php

namespace Tests\Unit\Models;

use App\Enums\EmploymentType;
use App\Models\Person;
use App\Models\PersonEmployment;
use App\Models\Tenant;
use Tests\TestCase;

class PersonEmploymentTest extends TestCase
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

    public function test_can_create_employment(): void
    {
        $employment = PersonEmployment::factory()->employee()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
            'employer_name' => 'Empresa SA de CV',
            'job_title' => 'Gerente',
        ]);

        $this->assertDatabaseHas('person_employments', [
            'id' => $employment->id,
            'employment_type' => 'EMPLOYEE',
            'employer_name' => 'Empresa SA de CV',
            'job_title' => 'Gerente',
        ]);
    }

    public function test_employment_belongs_to_person(): void
    {
        $employment = PersonEmployment::factory()->employee()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $this->assertEquals($this->person->id, $employment->person->id);
    }

    // =====================================================
    // Type Check Tests
    // =====================================================

    public function test_is_employee_returns_true_for_employee_type(): void
    {
        $employment = PersonEmployment::factory()->employee()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $this->assertTrue($employment->isEmployee());
        $this->assertFalse($employment->isSelfEmployed());
        $this->assertFalse($employment->isRetired());
    }

    public function test_is_self_employed_returns_true_for_self_employed_type(): void
    {
        $employment = PersonEmployment::factory()->selfEmployed()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $this->assertFalse($employment->isEmployee());
        $this->assertTrue($employment->isSelfEmployed());
    }

    public function test_is_retired_returns_true_for_retired_type(): void
    {
        $employment = PersonEmployment::factory()->retired()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $this->assertTrue($employment->isRetired());
    }

    public function test_requires_proof_of_income(): void
    {
        $employee = PersonEmployment::factory()->employee()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $student = PersonEmployment::factory()->student()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $this->assertTrue($employee->requiresProofOfIncome());
        $this->assertFalse($student->requiresProofOfIncome());
    }

    // =====================================================
    // Status Tests
    // =====================================================

    public function test_is_verified_returns_true_when_verified(): void
    {
        $employment = PersonEmployment::factory()->employee()->verified()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $this->assertTrue($employment->is_verified);
    }

    public function test_is_verified_returns_false_when_pending(): void
    {
        $employment = PersonEmployment::factory()->employee()->pending()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $this->assertFalse($employment->is_verified);
    }

    public function test_is_income_verified(): void
    {
        $verified = PersonEmployment::factory()->employee()->incomeVerified()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $unverified = PersonEmployment::factory()->employee()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
            'income_verified' => false,
        ]);

        $this->assertTrue($verified->is_income_verified);
        $this->assertFalse($unverified->is_income_verified);
    }

    // =====================================================
    // Income Tests
    // =====================================================

    public function test_total_monthly_income(): void
    {
        $employment = PersonEmployment::factory()->employee()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
            'monthly_income' => 25000.00,
            'additional_income' => 5000.00,
        ]);

        $this->assertEquals(30000.00, $employment->total_monthly_income);
    }

    public function test_total_monthly_income_without_additional(): void
    {
        $employment = PersonEmployment::factory()->employee()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
            'monthly_income' => 25000.00,
            'additional_income' => null,
        ]);

        $this->assertEquals(25000.00, $employment->total_monthly_income);
    }

    // =====================================================
    // Employment Duration Tests
    // =====================================================

    public function test_total_months_employed(): void
    {
        $employment = PersonEmployment::factory()->employee()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
            'years_employed' => 3,
            'months_employed' => 6,
        ]);

        $this->assertEquals(42, $employment->total_months_employed);
    }

    public function test_calculate_duration(): void
    {
        $employment = PersonEmployment::factory()->employee()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
            'start_date' => now()->subYears(2)->subMonths(4),
            'years_employed' => 0,
            'months_employed' => 0,
        ]);

        $employment->calculateDuration();

        $fresh = $employment->fresh();
        $this->assertEquals(2, $fresh->years_employed);
        $this->assertEquals(4, $fresh->months_employed);
    }

    public function test_end_employment(): void
    {
        $employment = PersonEmployment::factory()->employee()->current()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
            'start_date' => now()->subYear(),
        ]);

        $employment->endEmployment();

        $fresh = $employment->fresh();
        $this->assertFalse($fresh->is_current);
        $this->assertNotNull($fresh->end_date);
    }

    // =====================================================
    // Label Tests
    // =====================================================

    public function test_employment_type_label(): void
    {
        $employee = PersonEmployment::factory()->employee()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $this->assertEquals('Empleado', $employee->employment_type_label);

        $selfEmployed = PersonEmployment::factory()->selfEmployed()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $this->assertEquals('Trabajador Independiente', $selfEmployed->employment_type_label);
    }

    public function test_status_label(): void
    {
        $pending = PersonEmployment::factory()->employee()->pending()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $this->assertEquals('Pendiente', $pending->status_label);

        $verified = PersonEmployment::factory()->employee()->verified()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $this->assertEquals('Verificado', $verified->status_label);
    }

    // =====================================================
    // Verification Methods Tests
    // =====================================================

    public function test_mark_as_verified(): void
    {
        $employment = PersonEmployment::factory()->employee()->pending()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $employment->markAsVerified('PHONE_CALL', null, 'Confirmed employment', ['call_duration' => 120]);

        $fresh = $employment->fresh();
        $this->assertEquals(PersonEmployment::STATUS_VERIFIED, $fresh->status);
        $this->assertNotNull($fresh->verified_at);
        $this->assertNull($fresh->verified_by);
        $this->assertEquals('PHONE_CALL', $fresh->verification_method);
        $this->assertEquals('Confirmed employment', $fresh->verification_notes);
    }

    public function test_verify_income(): void
    {
        $employment = PersonEmployment::factory()->employee()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
            'monthly_income' => 25000.00,
        ]);

        $employment->verifyIncome(24500.00, 'PAYSLIP', null);

        $fresh = $employment->fresh();
        $this->assertTrue($fresh->income_verified);
        $this->assertNotNull($fresh->income_verified_at);
        $this->assertNull($fresh->income_verified_by);
        $this->assertEquals('PAYSLIP', $fresh->income_verification_method);
        $this->assertEquals(24500.00, $fresh->verified_income);
    }

    public function test_mark_as_rejected(): void
    {
        $employment = PersonEmployment::factory()->employee()->pending()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $employment->markAsRejected('Employer denied employment');

        $fresh = $employment->fresh();
        $this->assertEquals(PersonEmployment::STATUS_REJECTED, $fresh->status);
        $this->assertEquals('Employer denied employment', $fresh->verification_notes);
    }

    public function test_mark_as_unreachable(): void
    {
        $employment = PersonEmployment::factory()->employee()->pending()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $employment->markAsUnreachable('Phone number invalid');

        $fresh = $employment->fresh();
        $this->assertEquals(PersonEmployment::STATUS_UNREACHABLE, $fresh->status);
        $this->assertEquals('Phone number invalid', $fresh->verification_notes);
    }

    // =====================================================
    // Scope Tests
    // =====================================================

    public function test_current_scope(): void
    {
        PersonEmployment::factory()->employee()->current()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);
        PersonEmployment::factory()->employee()->past()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $current = PersonEmployment::current()->count();

        $this->assertEquals(1, $current);
    }

    public function test_verified_scope(): void
    {
        PersonEmployment::factory()->employee()->verified()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);
        PersonEmployment::factory()->employee()->pending()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $verified = PersonEmployment::verified()->count();

        $this->assertEquals(1, $verified);
    }

    public function test_income_verified_scope(): void
    {
        PersonEmployment::factory()->employee()->incomeVerified()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);
        PersonEmployment::factory()->employee()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
            'income_verified' => false,
        ]);

        $incomeVerified = PersonEmployment::incomeVerified()->count();

        $this->assertEquals(1, $incomeVerified);
    }

    public function test_of_type_scope(): void
    {
        PersonEmployment::factory()->employee()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);
        PersonEmployment::factory()->selfEmployed()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $employees = PersonEmployment::ofType(EmploymentType::EMPLOYEE->value)->count();

        $this->assertEquals(1, $employees);
    }

    // =====================================================
    // Static Finder Tests
    // =====================================================

    public function test_find_current_for_person(): void
    {
        $current = PersonEmployment::factory()->employee()->current()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        PersonEmployment::factory()->employee()->past()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $found = PersonEmployment::findCurrentForPerson($this->person->id);

        $this->assertNotNull($found);
        $this->assertEquals($current->id, $found->id);
    }

    public function test_get_history_for_person(): void
    {
        PersonEmployment::factory()->employee()->current()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
            'start_date' => now()->subYear(),
        ]);
        PersonEmployment::factory()->employee()->past()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
            'start_date' => now()->subYears(3),
        ]);
        PersonEmployment::factory()->employee()->past()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
            'start_date' => now()->subYears(5),
        ]);

        $history = PersonEmployment::getHistoryForPerson($this->person->id);

        $this->assertCount(3, $history);
        // Should be ordered by start_date desc
        $this->assertTrue($history[0]->start_date > $history[1]->start_date);
    }
}
