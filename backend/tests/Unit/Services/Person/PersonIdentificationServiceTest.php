<?php

namespace Tests\Unit\Services\Person;

use App\Models\Person;
use App\Models\PersonIdentification;
use App\Models\Tenant;
use App\Services\Person\PersonIdentificationService;
use Tests\TestCase;

class PersonIdentificationServiceTest extends TestCase
{
    protected PersonIdentificationService $service;
    protected ?Person $person = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PersonIdentificationService();
        $this->tenant = Tenant::factory()->create();
        $this->person = Person::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    public function test_can_create_identification(): void
    {
        $identification = $this->service->create($this->person, [
            'type' => 'CURP',
            'identifier_value' => 'GALO850101HDFRPR09',
        ]);

        $this->assertDatabaseHas('person_identifications', [
            'id' => $identification->id,
            'type' => 'CURP',
            'identifier_value' => 'GALO850101HDFRPR09',
        ]);
    }

    public function test_creating_current_marks_existing_as_historical(): void
    {
        $old = $this->service->create($this->person, [
            'type' => 'CURP',
            'identifier_value' => 'OLD0000000000000000',
            'is_current' => true,
        ]);

        $new = $this->service->create($this->person, [
            'type' => 'CURP',
            'identifier_value' => 'NEW0000000000000000',
            'is_current' => true,
        ]);

        $this->assertFalse($old->fresh()->is_current);
        $this->assertTrue($new->is_current);
    }

    public function test_can_get_current_by_type(): void
    {
        PersonIdentification::factory()->curp()->current()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
            'identifier_value' => 'CURRENT123',
        ]);
        PersonIdentification::factory()->curp()->historical()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
            'identifier_value' => 'OLD123',
        ]);

        $current = $this->service->getCurrentByType($this->person->id, 'CURP');

        $this->assertNotNull($current);
        $this->assertEquals('CURRENT123', $current->identifier_value);
    }

    public function test_can_verify_identification(): void
    {
        $identification = PersonIdentification::factory()->curp()->pending()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $verified = $this->service->verify($identification, 'RENAPO_API', null, ['response' => 'valid'], 99.5);

        $this->assertEquals(PersonIdentification::STATUS_VERIFIED, $verified->status);
        $this->assertNotNull($verified->verified_at);
        $this->assertEquals('RENAPO_API', $verified->verification_method);
    }

    public function test_can_reject_identification(): void
    {
        $identification = PersonIdentification::factory()->curp()->pending()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $rejected = $this->service->reject($identification, 'Invalid format');

        $this->assertEquals(PersonIdentification::STATUS_REJECTED, $rejected->status);
        $this->assertEquals('Invalid format', $rejected->notes);
    }

    public function test_set_curp_creates_new_if_not_exists(): void
    {
        $identification = $this->service->setCurp($this->person, 'GALO850101HDFRPR09');

        $this->assertEquals('CURP', $identification->type);
        $this->assertEquals('GALO850101HDFRPR09', $identification->identifier_value);
        $this->assertTrue($identification->is_current);
    }

    public function test_set_curp_returns_existing_if_same(): void
    {
        $original = $this->service->setCurp($this->person, 'GALO850101HDFRPR09');
        $same = $this->service->setCurp($this->person, 'GALO850101HDFRPR09');

        $this->assertEquals($original->id, $same->id);
    }

    public function test_has_verified_returns_true_when_verified(): void
    {
        PersonIdentification::factory()->curp()->verified()->current()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $this->assertTrue($this->service->hasVerified($this->person->id, 'CURP'));
    }

    public function test_has_verified_returns_false_when_pending(): void
    {
        PersonIdentification::factory()->curp()->pending()->current()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $this->assertFalse($this->service->hasVerified($this->person->id, 'CURP'));
    }

    public function test_get_pending(): void
    {
        PersonIdentification::factory()->curp()->pending()->current()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);
        PersonIdentification::factory()->rfc()->verified()->current()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $pending = $this->service->getPending($this->person->id);

        $this->assertCount(1, $pending);
    }
}
