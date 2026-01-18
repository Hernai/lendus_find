<?php

namespace Tests\Unit\Models;

use App\Models\Person;
use App\Models\PersonIdentification;
use App\Models\Tenant;
use Tests\TestCase;

class PersonIdentificationTest extends TestCase
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

    public function test_can_create_curp_identification(): void
    {
        $identification = PersonIdentification::factory()->curp()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
            'identifier_value' => 'GALO850101HDFRPR09',
        ]);

        $this->assertDatabaseHas('person_identifications', [
            'id' => $identification->id,
            'type' => 'CURP',
            'identifier_value' => 'GALO850101HDFRPR09',
        ]);
    }

    public function test_can_create_rfc_identification(): void
    {
        $identification = PersonIdentification::factory()->rfc()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $this->assertEquals('RFC', $identification->type);
        $this->assertNotNull($identification->document_data);
    }

    public function test_can_create_ine_identification(): void
    {
        $identification = PersonIdentification::factory()->ine()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $this->assertEquals('INE', $identification->type);
        $this->assertNotNull($identification->expires_at);
        $this->assertArrayHasKey('cic', $identification->document_data);
    }

    public function test_identification_belongs_to_person(): void
    {
        $identification = PersonIdentification::factory()->curp()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $this->assertEquals($this->person->id, $identification->person->id);
    }

    // =====================================================
    // Type Check Tests
    // =====================================================

    public function test_is_curp_returns_true_for_curp_type(): void
    {
        $identification = PersonIdentification::factory()->curp()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $this->assertTrue($identification->isCurp());
        $this->assertFalse($identification->isRfc());
        $this->assertFalse($identification->isIne());
    }

    public function test_is_rfc_returns_true_for_rfc_type(): void
    {
        $identification = PersonIdentification::factory()->rfc()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $this->assertFalse($identification->isCurp());
        $this->assertTrue($identification->isRfc());
        $this->assertFalse($identification->isIne());
    }

    public function test_is_ine_returns_true_for_ine_type(): void
    {
        $identification = PersonIdentification::factory()->ine()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $this->assertFalse($identification->isCurp());
        $this->assertFalse($identification->isRfc());
        $this->assertTrue($identification->isIne());
    }

    // =====================================================
    // Status Tests
    // =====================================================

    public function test_is_verified_returns_true_when_verified(): void
    {
        $identification = PersonIdentification::factory()->curp()->verified()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $this->assertTrue($identification->is_verified);
    }

    public function test_is_verified_returns_false_when_pending(): void
    {
        $identification = PersonIdentification::factory()->curp()->pending()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $this->assertFalse($identification->is_verified);
    }

    public function test_is_expired_returns_true_for_expired_document(): void
    {
        $identification = PersonIdentification::factory()->ine()->expired()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $this->assertTrue($identification->is_expired);
    }

    public function test_is_expired_returns_false_for_valid_document(): void
    {
        $identification = PersonIdentification::factory()->ine()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
            'expires_at' => now()->addYear(),
        ]);

        $this->assertFalse($identification->is_expired);
    }

    public function test_is_expired_returns_false_when_no_expiry(): void
    {
        $identification = PersonIdentification::factory()->curp()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
            'expires_at' => null,
        ]);

        $this->assertFalse($identification->is_expired);
    }

    // =====================================================
    // Verification Methods Tests
    // =====================================================

    public function test_mark_as_verified(): void
    {
        $identification = PersonIdentification::factory()->curp()->pending()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $identification->markAsVerified('RENAPO_API', null, ['api_response' => 'valid'], 99.5);

        $fresh = $identification->fresh();
        $this->assertEquals(PersonIdentification::STATUS_VERIFIED, $fresh->status);
        $this->assertNotNull($fresh->verified_at);
        $this->assertNull($fresh->verified_by);
        $this->assertEquals('RENAPO_API', $fresh->verification_method);
        $this->assertEquals(99.5, $fresh->verification_confidence);
    }

    public function test_mark_as_expired(): void
    {
        $identification = PersonIdentification::factory()->ine()->verified()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $identification->markAsExpired();

        $fresh = $identification->fresh();
        $this->assertEquals(PersonIdentification::STATUS_EXPIRED, $fresh->status);
        $this->assertFalse($fresh->is_current);
    }

    public function test_replace_with_creates_new_version(): void
    {
        $oldIdentification = PersonIdentification::factory()->ine()->verified()->current()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
            'identifier_value' => 'OLD123456789012345',
        ]);

        $newIdentification = $oldIdentification->replaceWith([
            'identifier_value' => 'NEW123456789012345',
            'document_data' => ['cic' => '999999999999'],
        ], 'RENEWED');

        // Check old version
        $oldFresh = $oldIdentification->fresh();
        $this->assertEquals(PersonIdentification::STATUS_SUPERSEDED, $oldFresh->status);
        $this->assertFalse($oldFresh->is_current);
        $this->assertNotNull($oldFresh->replaced_at);
        $this->assertEquals('RENEWED', $oldFresh->replacement_reason);

        // Check new version
        $this->assertTrue($newIdentification->is_current);
        $this->assertEquals(PersonIdentification::STATUS_PENDING, $newIdentification->status);
        $this->assertEquals('NEW123456789012345', $newIdentification->identifier_value);
        $this->assertEquals($oldIdentification->id, $newIdentification->previous_version_id);
    }

    // =====================================================
    // Label Tests
    // =====================================================

    public function test_type_label(): void
    {
        $curp = PersonIdentification::factory()->curp()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $this->assertEquals('CURP', $curp->type_label);

        $ine = PersonIdentification::factory()->ine()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $this->assertEquals('INE', $ine->type_label);
    }

    public function test_status_label(): void
    {
        $pending = PersonIdentification::factory()->curp()->pending()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $this->assertEquals('Pendiente', $pending->status_label);

        $verified = PersonIdentification::factory()->curp()->verified()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $this->assertEquals('Verificado', $verified->status_label);
    }

    // =====================================================
    // Scope Tests
    // =====================================================

    public function test_current_scope(): void
    {
        PersonIdentification::factory()->curp()->current()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);
        PersonIdentification::factory()->curp()->historical()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $current = PersonIdentification::current()->count();

        $this->assertEquals(1, $current);
    }

    public function test_verified_scope(): void
    {
        PersonIdentification::factory()->curp()->verified()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);
        PersonIdentification::factory()->curp()->pending()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $verified = PersonIdentification::verified()->count();

        $this->assertEquals(1, $verified);
    }

    public function test_of_type_scope(): void
    {
        PersonIdentification::factory()->curp()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);
        PersonIdentification::factory()->rfc()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);
        PersonIdentification::factory()->ine()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $curps = PersonIdentification::ofType('CURP')->count();

        $this->assertEquals(1, $curps);
    }

    // =====================================================
    // Static Finder Tests
    // =====================================================

    public function test_find_current_by_type(): void
    {
        $curp = PersonIdentification::factory()->curp()->current()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $found = PersonIdentification::findCurrentByType($this->person->id, 'CURP');

        $this->assertNotNull($found);
        $this->assertEquals($curp->id, $found->id);
    }

    public function test_find_by_identifier(): void
    {
        $curp = PersonIdentification::factory()->curp()->current()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
            'identifier_value' => 'GALO850101HDFRPR09',
        ]);

        $found = PersonIdentification::findByIdentifier('CURP', 'GALO850101HDFRPR09', $this->tenant->id);

        $this->assertNotNull($found);
        $this->assertEquals($curp->id, $found->id);
    }

    public function test_find_by_identifier_returns_null_for_different_tenant(): void
    {
        PersonIdentification::factory()->curp()->current()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
            'identifier_value' => 'GALO850101HDFRPR09',
        ]);

        $otherTenant = Tenant::factory()->create();
        $found = PersonIdentification::findByIdentifier('CURP', 'GALO850101HDFRPR09', $otherTenant->id);

        $this->assertNull($found);
    }

    // =====================================================
    // Document Data Helpers Tests
    // =====================================================

    public function test_get_document_data_value(): void
    {
        $identification = PersonIdentification::factory()->ine()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
            'document_data' => ['cic' => '123456789012', 'emision' => 2020],
        ]);

        $this->assertEquals('123456789012', $identification->getDocumentDataValue('cic'));
        $this->assertEquals(2020, $identification->getDocumentDataValue('emision'));
        $this->assertNull($identification->getDocumentDataValue('nonexistent'));
        $this->assertEquals('default', $identification->getDocumentDataValue('nonexistent', 'default'));
    }
}
