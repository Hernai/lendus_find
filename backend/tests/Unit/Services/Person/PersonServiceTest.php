<?php

namespace Tests\Unit\Services\Person;

use App\Models\Person;
use App\Models\PersonAddress;
use App\Models\PersonBankAccount;
use App\Models\PersonEmployment;
use App\Models\PersonIdentification;
use App\Models\Tenant;
use App\Services\Person\PersonService;
use Tests\TestCase;

class PersonServiceTest extends TestCase
{
    protected PersonService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PersonService();
        $this->tenant = Tenant::factory()->create();
    }

    public function test_can_create_person(): void
    {
        $person = $this->service->create([
            'first_name' => 'Juan',
            'last_name_1' => 'García',
            'last_name_2' => 'López',
            'birth_date' => '1990-01-15',
            'gender' => 'M',
        ], $this->tenant->id);

        $this->assertDatabaseHas('persons', [
            'id' => $person->id,
            'first_name' => 'Juan',
            'last_name_1' => 'García',
        ]);
    }

    public function test_can_update_person(): void
    {
        $person = Person::factory()->create(['tenant_id' => $this->tenant->id]);

        $updated = $this->service->update($person, [
            'first_name' => 'Carlos',
            'marital_status' => 'MARRIED',
        ]);

        $this->assertEquals('Carlos', $updated->first_name);
        $this->assertEquals('MARRIED', $updated->marital_status);
    }

    public function test_can_delete_person(): void
    {
        $person = Person::factory()->create(['tenant_id' => $this->tenant->id]);

        $result = $this->service->delete($person);

        $this->assertTrue($result);
        $this->assertSoftDeleted('persons', ['id' => $person->id]);
    }

    public function test_can_find_person_by_id(): void
    {
        $person = Person::factory()->create(['tenant_id' => $this->tenant->id]);

        $found = $this->service->find($person->id);

        $this->assertNotNull($found);
        $this->assertEquals($person->id, $found->id);
    }

    public function test_can_find_person_by_curp(): void
    {
        $person = Person::factory()->create(['tenant_id' => $this->tenant->id]);
        PersonIdentification::factory()->curp()->current()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $person->id,
            'identifier_value' => 'GALO850101HDFRPR09',
        ]);

        $found = $this->service->findByCurp('GALO850101HDFRPR09', $this->tenant->id);

        $this->assertNotNull($found);
        $this->assertEquals($person->id, $found->id);
    }

    public function test_can_paginate_persons(): void
    {
        Person::factory()->count(25)->create(['tenant_id' => $this->tenant->id]);

        $result = $this->service->paginate($this->tenant->id, 10);

        $this->assertEquals(10, $result->perPage());
        $this->assertEquals(25, $result->total());
    }

    public function test_paginate_filters_by_kyc_status(): void
    {
        Person::factory()->kycVerified()->count(3)->create(['tenant_id' => $this->tenant->id]);
        Person::factory()->kycPending()->count(5)->create(['tenant_id' => $this->tenant->id]);

        $result = $this->service->paginate($this->tenant->id, 15, ['kyc_status' => 'VERIFIED']);

        $this->assertEquals(3, $result->total());
    }

    public function test_can_update_kyc_status(): void
    {
        $person = Person::factory()->kycPending()->create(['tenant_id' => $this->tenant->id]);

        $updated = $this->service->updateKycStatus($person, 'VERIFIED', ['curp_verified' => true], null);

        $this->assertEquals('VERIFIED', $updated->kyc_status);
        $this->assertNotNull($updated->kyc_verified_at);
    }

    public function test_can_get_statistics(): void
    {
        Person::factory()->kycVerified()->count(3)->create(['tenant_id' => $this->tenant->id]);
        Person::factory()->kycPending()->count(2)->create(['tenant_id' => $this->tenant->id]);

        $stats = $this->service->getStatistics($this->tenant->id);

        $this->assertEquals(5, $stats['total']);
        $this->assertEquals(3, $stats['kyc_verified']);
        $this->assertEquals(2, $stats['kyc_pending']);
    }

    public function test_recalculate_completeness(): void
    {
        $person = Person::factory()->create([
            'tenant_id' => $this->tenant->id,
            'profile_completeness' => 0,
        ]);

        $completeness = $this->service->recalculateCompleteness($person);

        $this->assertIsInt($completeness);
        $this->assertEquals($completeness, $person->fresh()->profile_completeness);
    }
}
