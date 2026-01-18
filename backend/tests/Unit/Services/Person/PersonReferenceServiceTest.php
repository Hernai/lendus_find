<?php

namespace Tests\Unit\Services\Person;

use App\Models\Person;
use App\Models\PersonReference;
use App\Models\Tenant;
use App\Services\Person\PersonReferenceService;
use Tests\TestCase;

class PersonReferenceServiceTest extends TestCase
{
    protected PersonReferenceService $service;
    protected ?Person $person = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PersonReferenceService();
        $this->tenant = Tenant::factory()->create();
        $this->person = Person::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    public function test_can_create_reference(): void
    {
        $reference = $this->service->create($this->person, [
            'type' => 'PERSONAL',
            'first_name' => 'Juan',
            'last_name_1' => 'Pérez',
            'phone' => '5512345678',
            'relationship' => 'FRIEND',
        ]);

        $this->assertDatabaseHas('person_references', [
            'id' => $reference->id,
            'first_name' => 'Juan',
            'type' => 'PERSONAL',
        ]);
    }

    public function test_can_add_personal_reference(): void
    {
        $reference = $this->service->addPersonalReference($this->person, [
            'first_name' => 'María',
            'last_name_1' => 'López',
            'phone' => '5512345678',
            'relationship' => 'FRIEND',
        ]);

        $this->assertEquals('PERSONAL', $reference->type);
    }

    public function test_can_add_work_reference(): void
    {
        $reference = $this->service->addWorkReference($this->person, [
            'first_name' => 'Carlos',
            'last_name_1' => 'García',
            'phone' => '5512345678',
            'relationship' => 'COWORKER',
        ]);

        $this->assertEquals('WORK', $reference->type);
    }

    public function test_can_verify_reference(): void
    {
        $reference = PersonReference::factory()->personal()->pending()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $verified = $this->service->verify($reference, null, 'Confirmed relationship');

        $this->assertEquals(PersonReference::STATUS_VERIFIED, $verified->status);
        $this->assertNotNull($verified->verified_at);
        $this->assertEquals('Confirmed relationship', $verified->verification_notes);
    }

    public function test_can_reject_reference(): void
    {
        $reference = PersonReference::factory()->personal()->pending()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $rejected = $this->service->reject($reference, 'Reference denied knowing applicant');

        $this->assertEquals(PersonReference::STATUS_REJECTED, $rejected->status);
    }

    public function test_phone_exists(): void
    {
        PersonReference::factory()->personal()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
            'phone' => '5512345678',
        ]);

        $this->assertTrue($this->service->phoneExists($this->person->id, '5512345678'));
        $this->assertFalse($this->service->phoneExists($this->person->id, '5599999999'));
    }

    public function test_get_verified_count(): void
    {
        PersonReference::factory()->personal()->verified()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);
        PersonReference::factory()->personal()->pending()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $count = $this->service->getVerifiedCount($this->person->id);

        $this->assertEquals(3, $count);
    }

    public function test_get_summary(): void
    {
        PersonReference::factory()->personal()->verified()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);
        PersonReference::factory()->personal()->pending()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);
        PersonReference::factory()->work()->verified()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $summary = $this->service->getSummary($this->person->id);

        $this->assertEquals(3, $summary['total']);
        $this->assertEquals(2, $summary['personal']['total']);
        $this->assertEquals(1, $summary['personal']['verified']);
        $this->assertEquals(1, $summary['work']['total']);
        $this->assertEquals(2, $summary['verified_count']);
    }

    public function test_has_required_references(): void
    {
        PersonReference::factory()->personal()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);
        PersonReference::factory()->work()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $this->assertTrue($this->service->hasRequiredReferences($this->person->id, 1, 1));
        $this->assertFalse($this->service->hasRequiredReferences($this->person->id, 2, 1));
    }

    public function test_bulk_verify(): void
    {
        $ref1 = PersonReference::factory()->personal()->pending()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);
        $ref2 = PersonReference::factory()->personal()->pending()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);
        $ref3 = PersonReference::factory()->personal()->verified()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $count = $this->service->bulkVerify([$ref1->id, $ref2->id, $ref3->id], null);

        $this->assertEquals(2, $count);
        $this->assertEquals(PersonReference::STATUS_VERIFIED, $ref1->fresh()->status);
        $this->assertEquals(PersonReference::STATUS_VERIFIED, $ref2->fresh()->status);
    }
}
