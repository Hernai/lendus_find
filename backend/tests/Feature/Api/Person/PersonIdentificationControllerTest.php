<?php

namespace Tests\Feature\Api\Person;

use App\Models\Person;
use App\Models\PersonIdentification;
use App\Models\StaffAccount;
use App\Models\Tenant;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PersonIdentificationControllerTest extends TestCase
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

    public function test_can_list_identifications(): void
    {
        PersonIdentification::factory()->curp()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $response = $this->getJson("/api/persons/{$this->person->id}/identifications");

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_can_create_identification(): void
    {
        $response = $this->postJson("/api/persons/{$this->person->id}/identifications", [
            'type' => 'CURP',
            'identifier_value' => 'GALO850101HDFRPR09',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.type', 'CURP')
            ->assertJsonPath('data.identifier_value', 'GALO850101HDFRPR09');
    }

    public function test_can_show_identification(): void
    {
        $identification = PersonIdentification::factory()->curp()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $response = $this->getJson("/api/persons/{$this->person->id}/identifications/{$identification->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $identification->id);
    }

    public function test_can_update_identification(): void
    {
        $identification = PersonIdentification::factory()->curp()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $response = $this->putJson("/api/persons/{$this->person->id}/identifications/{$identification->id}", [
            'notes' => 'Updated notes',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.notes', 'Updated notes');
    }

    public function test_can_delete_identification(): void
    {
        $identification = PersonIdentification::factory()->curp()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $response = $this->deleteJson("/api/persons/{$this->person->id}/identifications/{$identification->id}");

        $response->assertOk();
        $this->assertSoftDeleted('person_identifications', ['id' => $identification->id]);
    }

    public function test_can_get_current_identifications(): void
    {
        PersonIdentification::factory()->curp()->current()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);
        PersonIdentification::factory()->rfc()->current()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);
        PersonIdentification::factory()->curp()->historical()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $response = $this->getJson("/api/persons/{$this->person->id}/identifications/current");

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_can_get_current_by_type(): void
    {
        PersonIdentification::factory()->curp()->current()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
            'identifier_value' => 'CURRENT_CURP',
        ]);

        $response = $this->getJson("/api/persons/{$this->person->id}/identifications/current/curp");

        $response->assertOk()
            ->assertJsonPath('found', true)
            ->assertJsonPath('data.identifier_value', 'CURRENT_CURP');
    }

    public function test_can_verify_identification(): void
    {
        $identification = PersonIdentification::factory()->curp()->pending()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $response = $this->postJson("/api/persons/{$this->person->id}/identifications/{$identification->id}/verify", [
            'method' => 'RENAPO_API',
            'verification_data' => ['response' => 'valid'],
            'confidence' => 99.5,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'VERIFIED')
            ->assertJsonPath('data.verification_method', 'RENAPO_API');
    }

    public function test_can_reject_identification(): void
    {
        $identification = PersonIdentification::factory()->curp()->pending()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $response = $this->postJson("/api/persons/{$this->person->id}/identifications/{$identification->id}/reject", [
            'reason' => 'Invalid format',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'REJECTED');
    }

    public function test_can_set_curp(): void
    {
        $response = $this->postJson("/api/persons/{$this->person->id}/identifications/curp", [
            'curp' => 'GALO850101HDFRPR09',
        ]);

        $response->assertSuccessful()
            ->assertJsonPath('data.type', 'CURP')
            ->assertJsonPath('data.identifier_value', 'GALO850101HDFRPR09');
    }

    public function test_can_set_rfc(): void
    {
        $response = $this->postJson("/api/persons/{$this->person->id}/identifications/rfc", [
            'rfc' => 'GALO850101AB1',
        ]);

        $response->assertSuccessful()
            ->assertJsonPath('data.type', 'RFC')
            ->assertJsonPath('data.identifier_value', 'GALO850101AB1');
    }

    public function test_can_set_ine(): void
    {
        $response = $this->postJson("/api/persons/{$this->person->id}/identifications/ine", [
            'cic' => '123456789012',
            'ocr' => '987654321',
            'expires_at' => '2030-12-31',
        ]);

        $response->assertSuccessful()
            ->assertJsonPath('data.type', 'INE');
    }

    public function test_can_get_pending(): void
    {
        PersonIdentification::factory()->curp()->pending()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);
        PersonIdentification::factory()->rfc()->verified()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $response = $this->getJson("/api/persons/{$this->person->id}/identifications/pending");

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_can_check_has_verified(): void
    {
        PersonIdentification::factory()->curp()->verified()->current()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $response = $this->getJson("/api/persons/{$this->person->id}/identifications/has-verified/curp");

        $response->assertOk()
            ->assertJsonPath('has_verified', true);
    }
}
