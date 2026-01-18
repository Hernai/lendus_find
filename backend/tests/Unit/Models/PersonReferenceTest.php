<?php

namespace Tests\Unit\Models;

use App\Enums\ReferenceType;
use App\Enums\Relationship;
use App\Models\Person;
use App\Models\PersonReference;
use App\Models\Tenant;
use Tests\TestCase;

class PersonReferenceTest extends TestCase
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

    public function test_can_create_personal_reference(): void
    {
        $reference = PersonReference::factory()->personal()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
            'first_name' => 'Juan',
            'last_name_1' => 'Pérez',
            'phone' => '5512345678',
        ]);

        $this->assertDatabaseHas('person_references', [
            'id' => $reference->id,
            'type' => 'PERSONAL',
            'first_name' => 'Juan',
            'phone' => '5512345678',
        ]);
    }

    public function test_reference_belongs_to_person(): void
    {
        $reference = PersonReference::factory()->personal()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $this->assertEquals($this->person->id, $reference->person->id);
    }

    // =====================================================
    // Type Check Tests
    // =====================================================

    public function test_is_personal_returns_true_for_personal_type(): void
    {
        $reference = PersonReference::factory()->personal()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $this->assertTrue($reference->isPersonal());
        $this->assertFalse($reference->isWork());
    }

    public function test_is_work_returns_true_for_work_type(): void
    {
        $reference = PersonReference::factory()->work()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $this->assertFalse($reference->isPersonal());
        $this->assertTrue($reference->isWork());
    }

    public function test_is_family_relationship(): void
    {
        $sibling = PersonReference::factory()->sibling()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $friend = PersonReference::factory()->friend()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $this->assertTrue($sibling->isFamilyRelationship());
        $this->assertFalse($friend->isFamilyRelationship());
    }

    // =====================================================
    // Status Tests
    // =====================================================

    public function test_is_verified_returns_true_when_verified(): void
    {
        $reference = PersonReference::factory()->personal()->verified()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $this->assertTrue($reference->is_verified);
    }

    public function test_is_verified_returns_false_when_pending(): void
    {
        $reference = PersonReference::factory()->personal()->pending()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $this->assertFalse($reference->is_verified);
    }

    // =====================================================
    // Full Name Accessor Tests
    // =====================================================

    public function test_full_name_with_both_last_names(): void
    {
        $reference = PersonReference::factory()->personal()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
            'first_name' => 'María',
            'last_name_1' => 'González',
            'last_name_2' => 'Hernández',
        ]);

        $this->assertEquals('María González Hernández', $reference->full_name);
    }

    public function test_full_name_without_second_last_name(): void
    {
        $reference = PersonReference::factory()->personal()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
            'first_name' => 'Carlos',
            'last_name_1' => 'Rodríguez',
            'last_name_2' => null,
        ]);

        $this->assertEquals('Carlos Rodríguez', $reference->full_name);
    }

    // =====================================================
    // Label Tests
    // =====================================================

    public function test_type_label(): void
    {
        $personal = PersonReference::factory()->personal()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $this->assertEquals('Personal', $personal->type_label);

        $work = PersonReference::factory()->work()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $this->assertEquals('Laboral', $work->type_label);
    }

    public function test_relationship_label(): void
    {
        $friend = PersonReference::factory()->friend()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $this->assertEquals('Amigo(a)', $friend->relationship_label);

        $sibling = PersonReference::factory()->sibling()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $this->assertEquals('Hermano(a)', $sibling->relationship_label);
    }

    public function test_status_label(): void
    {
        $pending = PersonReference::factory()->personal()->pending()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $this->assertEquals('Pendiente', $pending->status_label);

        $verified = PersonReference::factory()->personal()->verified()->create([
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
        $reference = PersonReference::factory()->personal()->pending()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $reference->markAsVerified(null, 'Confirmed relationship');

        $fresh = $reference->fresh();
        $this->assertEquals(PersonReference::STATUS_VERIFIED, $fresh->status);
        $this->assertNotNull($fresh->verified_at);
        $this->assertNull($fresh->verified_by);
        $this->assertEquals('Confirmed relationship', $fresh->verification_notes);
    }

    public function test_mark_as_unreachable(): void
    {
        $reference = PersonReference::factory()->personal()->pending()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $reference->markAsUnreachable('Phone disconnected');

        $fresh = $reference->fresh();
        $this->assertEquals(PersonReference::STATUS_UNREACHABLE, $fresh->status);
        $this->assertEquals('Phone disconnected', $fresh->verification_notes);
    }

    public function test_mark_as_rejected(): void
    {
        $reference = PersonReference::factory()->personal()->pending()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $reference->markAsRejected('Reference denied knowing applicant');

        $fresh = $reference->fresh();
        $this->assertEquals(PersonReference::STATUS_REJECTED, $fresh->status);
        $this->assertEquals('Reference denied knowing applicant', $fresh->verification_notes);
    }

    public function test_mark_as_no_answer(): void
    {
        $reference = PersonReference::factory()->personal()->pending()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $reference->markAsNoAnswer();

        $fresh = $reference->fresh();
        $this->assertEquals(PersonReference::STATUS_NO_ANSWER, $fresh->status);
    }

    // =====================================================
    // Contact Attempts Tests
    // =====================================================

    public function test_log_contact_attempt(): void
    {
        $reference = PersonReference::factory()->personal()->pending()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
            'contact_attempts' => null,
        ]);

        $reference->logContactAttempt('no_answer', 'Went to voicemail', null);

        $fresh = $reference->fresh();
        $this->assertCount(1, $fresh->contact_attempts);
        $this->assertEquals('no_answer', $fresh->contact_attempts[0]['result']);
        $this->assertEquals('Went to voicemail', $fresh->contact_attempts[0]['notes']);
    }

    public function test_contact_attempts_count(): void
    {
        $reference = PersonReference::factory()->personal()->withContactAttempts(3)->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $this->assertEquals(3, $reference->contact_attempts_count);
    }

    public function test_get_last_contact_attempt(): void
    {
        $reference = PersonReference::factory()->personal()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
            'contact_attempts' => [
                ['date' => '2026-01-15', 'time' => '10:00', 'result' => 'no_answer', 'notes' => null, 'by' => null],
                ['date' => '2026-01-16', 'time' => '14:00', 'result' => 'answered', 'notes' => 'Spoke with reference', 'by' => null],
            ],
        ]);

        $last = $reference->getLastContactAttempt();

        $this->assertNotNull($last);
        $this->assertEquals('2026-01-16', $last['date']);
        $this->assertEquals('answered', $last['result']);
    }

    public function test_get_last_contact_attempt_returns_null_when_no_attempts(): void
    {
        $reference = PersonReference::factory()->personal()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
            'contact_attempts' => null,
        ]);

        $this->assertNull($reference->getLastContactAttempt());
    }

    // =====================================================
    // Scope Tests
    // =====================================================

    public function test_verified_scope(): void
    {
        PersonReference::factory()->personal()->verified()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);
        PersonReference::factory()->personal()->pending()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $verified = PersonReference::verified()->count();

        $this->assertEquals(1, $verified);
    }

    public function test_pending_scope(): void
    {
        PersonReference::factory()->personal()->verified()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);
        PersonReference::factory()->personal()->pending()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);
        PersonReference::factory()->personal()->pending()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $pending = PersonReference::pending()->count();

        $this->assertEquals(2, $pending);
    }

    public function test_personal_scope(): void
    {
        PersonReference::factory()->personal()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);
        PersonReference::factory()->work()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $personal = PersonReference::personal()->count();

        $this->assertEquals(1, $personal);
    }

    public function test_work_scope(): void
    {
        PersonReference::factory()->personal()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);
        PersonReference::factory()->work()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $work = PersonReference::work()->count();

        $this->assertEquals(1, $work);
    }

    public function test_by_phone_scope(): void
    {
        PersonReference::factory()->personal()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
            'phone' => '5512345678',
        ]);
        PersonReference::factory()->personal()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
            'phone' => '5587654321',
        ]);

        $results = PersonReference::byPhone('5512345678')->count();

        $this->assertEquals(1, $results);
    }

    // =====================================================
    // Static Finder Tests
    // =====================================================

    public function test_find_by_person_and_type(): void
    {
        PersonReference::factory()->personal()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);
        PersonReference::factory()->personal()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);
        PersonReference::factory()->work()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $personalRefs = PersonReference::findByPersonAndType($this->person->id, ReferenceType::PERSONAL->value);

        $this->assertCount(2, $personalRefs);
    }

    public function test_phone_exists_for_person(): void
    {
        PersonReference::factory()->personal()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
            'phone' => '5512345678',
        ]);

        $this->assertTrue(PersonReference::phoneExistsForPerson($this->person->id, '5512345678'));
        $this->assertFalse(PersonReference::phoneExistsForPerson($this->person->id, '5599999999'));
    }
}
