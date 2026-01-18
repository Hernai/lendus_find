<?php

namespace Tests\Feature\Api\Person;

use App\Models\Person;
use App\Models\PersonReference;
use App\Models\StaffAccount;
use App\Models\Tenant;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PersonReferenceControllerTest extends TestCase
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

    public function test_can_list_references(): void
    {
        PersonReference::factory()->personal()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $response = $this->getJson("/api/persons/{$this->person->id}/references");

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_can_create_reference(): void
    {
        $response = $this->postJson("/api/persons/{$this->person->id}/references", [
            'type' => 'PERSONAL',
            'first_name' => 'María',
            'last_name_1' => 'López',
            'phone' => '5512345678',
            'relationship' => 'FRIEND',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.first_name', 'María')
            ->assertJsonPath('data.type', 'PERSONAL');
    }

    public function test_can_show_reference(): void
    {
        $reference = PersonReference::factory()->personal()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $response = $this->getJson("/api/persons/{$this->person->id}/references/{$reference->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $reference->id);
    }

    public function test_can_update_reference(): void
    {
        $reference = PersonReference::factory()->personal()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $response = $this->putJson("/api/persons/{$this->person->id}/references/{$reference->id}", [
            'phone' => '5598765432',
            'years_known' => 10,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.phone', '5598765432')
            ->assertJsonPath('data.years_known', 10);
    }

    public function test_can_delete_reference(): void
    {
        $reference = PersonReference::factory()->personal()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $response = $this->deleteJson("/api/persons/{$this->person->id}/references/{$reference->id}");

        $response->assertOk();
        $this->assertSoftDeleted('person_references', ['id' => $reference->id]);
    }

    public function test_can_add_personal_reference(): void
    {
        $response = $this->postJson("/api/persons/{$this->person->id}/references/personal", [
            'first_name' => 'Carlos',
            'last_name_1' => 'García',
            'phone' => '5512345678',
            'relationship' => 'SIBLING',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.type', 'PERSONAL');
    }

    public function test_can_add_work_reference(): void
    {
        $response = $this->postJson("/api/persons/{$this->person->id}/references/work", [
            'first_name' => 'Ana',
            'last_name_1' => 'Martínez',
            'phone' => '5512345678',
            'relationship' => 'SUPERVISOR',
            'employer_name' => 'Empresa XYZ',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.type', 'WORK');
    }

    public function test_can_verify_reference(): void
    {
        $reference = PersonReference::factory()->personal()->pending()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $response = $this->postJson("/api/persons/{$this->person->id}/references/{$reference->id}/verify", [
            'notes' => 'Confirmed relationship via phone call',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'VERIFIED');
    }

    public function test_can_reject_reference(): void
    {
        $reference = PersonReference::factory()->personal()->pending()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $response = $this->postJson("/api/persons/{$this->person->id}/references/{$reference->id}/reject", [
            'reason' => 'Reference denied knowing the applicant',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'REJECTED');
    }

    public function test_can_log_contact_attempt(): void
    {
        $reference = PersonReference::factory()->personal()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
            'contact_attempts' => [],
        ]);

        $response = $this->postJson("/api/persons/{$this->person->id}/references/{$reference->id}/contact-attempt", [
            'notes' => 'No answer',
        ]);

        $response->assertOk();

        // Verify that contact_attempts is now an array with 1 element
        $this->assertCount(1, $reference->fresh()->contact_attempts);
    }

    public function test_can_get_by_type(): void
    {
        PersonReference::factory()->personal()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);
        PersonReference::factory()->work()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $response = $this->getJson("/api/persons/{$this->person->id}/references/type/personal");

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_can_get_verified(): void
    {
        PersonReference::factory()->personal()->verified()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);
        PersonReference::factory()->personal()->pending()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $response = $this->getJson("/api/persons/{$this->person->id}/references/verified");

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_can_get_pending(): void
    {
        PersonReference::factory()->personal()->pending()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);
        PersonReference::factory()->personal()->verified()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $response = $this->getJson("/api/persons/{$this->person->id}/references/pending");

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_can_get_summary(): void
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

        $response = $this->getJson("/api/persons/{$this->person->id}/references/summary");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'total',
                    'verified_count',
                    'personal',
                    'work',
                ]
            ]);
    }

    public function test_can_check_phone_exists(): void
    {
        PersonReference::factory()->personal()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
            'phone' => '5512345678',
        ]);

        $response = $this->postJson("/api/persons/{$this->person->id}/references/phone-exists", [
            'phone' => '5512345678',
        ]);

        $response->assertOk()
            ->assertJsonPath('exists', true);
    }

    public function test_can_check_has_required(): void
    {
        PersonReference::factory()->personal()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);
        PersonReference::factory()->work()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $response = $this->postJson("/api/persons/{$this->person->id}/references/has-required", [
            'min_personal' => 1,
            'min_work' => 1,
        ]);

        $response->assertOk()
            ->assertJsonPath('has_required', true);
    }

    public function test_can_bulk_verify(): void
    {
        $ref1 = PersonReference::factory()->personal()->pending()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);
        $ref2 = PersonReference::factory()->personal()->pending()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $response = $this->postJson("/api/persons/{$this->person->id}/references/bulk-verify", [
            'reference_ids' => [$ref1->id, $ref2->id],
        ]);

        $response->assertOk()
            ->assertJsonPath('verified_count', 2);

        $this->assertEquals('VERIFIED', $ref1->fresh()->status);
        $this->assertEquals('VERIFIED', $ref2->fresh()->status);
    }
}
