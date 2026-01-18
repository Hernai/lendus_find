<?php

namespace Tests\Unit\Models;

use App\Models\Person;
use App\Models\PersonAddress;
use App\Models\Tenant;
use Tests\TestCase;

class PersonAddressTest extends TestCase
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

    public function test_can_create_home_address(): void
    {
        $address = PersonAddress::factory()->home()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
            'street' => 'Avenida Reforma',
            'exterior_number' => '123',
            'neighborhood' => 'Polanco',
        ]);

        $this->assertDatabaseHas('person_addresses', [
            'id' => $address->id,
            'type' => 'HOME',
            'street' => 'Avenida Reforma',
            'neighborhood' => 'Polanco',
        ]);
    }

    public function test_address_belongs_to_person(): void
    {
        $address = PersonAddress::factory()->home()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $this->assertEquals($this->person->id, $address->person->id);
    }

    // =====================================================
    // Type Check Tests
    // =====================================================

    public function test_is_home_returns_true_for_home_type(): void
    {
        $address = PersonAddress::factory()->home()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $this->assertTrue($address->isHome());
        $this->assertFalse($address->isWork());
        $this->assertFalse($address->isFiscal());
    }

    public function test_is_work_returns_true_for_work_type(): void
    {
        $address = PersonAddress::factory()->work()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $this->assertFalse($address->isHome());
        $this->assertTrue($address->isWork());
    }

    public function test_is_fiscal_returns_true_for_fiscal_type(): void
    {
        $address = PersonAddress::factory()->fiscal()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $this->assertTrue($address->isFiscal());
    }

    // =====================================================
    // Status Tests
    // =====================================================

    public function test_is_verified_returns_true_when_verified(): void
    {
        $address = PersonAddress::factory()->home()->verified()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $this->assertTrue($address->is_verified);
    }

    public function test_is_verified_returns_false_when_pending(): void
    {
        $address = PersonAddress::factory()->home()->pending()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $this->assertFalse($address->is_verified);
    }

    // =====================================================
    // Full Address Accessor Tests
    // =====================================================

    public function test_full_address_format(): void
    {
        $address = PersonAddress::factory()->home()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
            'street' => 'Avenida Reforma',
            'exterior_number' => '123',
            'interior_number' => '4B',
            'neighborhood' => 'Polanco',
            'municipality' => 'Miguel Hidalgo',
            'city' => null,
            'state' => 'CDMX',
            'postal_code' => '11550',
        ]);

        $expected = 'Avenida Reforma, 123, Int. 4B, Polanco, Miguel Hidalgo, CDMX, C.P. 11550';
        $this->assertEquals($expected, $address->full_address);
    }

    public function test_full_address_without_interior_number(): void
    {
        $address = PersonAddress::factory()->home()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
            'street' => 'Calle Norte',
            'exterior_number' => '500',
            'interior_number' => null,
            'neighborhood' => 'Centro',
            'municipality' => 'Cuauhtémoc',
            'city' => null,
            'state' => 'CDMX',
            'postal_code' => '06000',
        ]);

        $expected = 'Calle Norte, 500, Centro, Cuauhtémoc, CDMX, C.P. 06000';
        $this->assertEquals($expected, $address->full_address);
    }

    // =====================================================
    // Label Tests
    // =====================================================

    public function test_type_label(): void
    {
        $home = PersonAddress::factory()->home()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $this->assertEquals('Domicilio', $home->type_label);

        $work = PersonAddress::factory()->work()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $this->assertEquals('Trabajo', $work->type_label);
    }

    public function test_housing_type_label(): void
    {
        $owned = PersonAddress::factory()->home()->owned()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $this->assertEquals('Propia (pagada)', $owned->housing_type_label);

        $rented = PersonAddress::factory()->home()->rented()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $this->assertEquals('Rentada', $rented->housing_type_label);
    }

    public function test_status_label(): void
    {
        $pending = PersonAddress::factory()->home()->pending()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $this->assertEquals('Pendiente', $pending->status_label);
    }

    // =====================================================
    // Residence Duration Tests
    // =====================================================

    public function test_total_months_at_address(): void
    {
        $address = PersonAddress::factory()->home()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
            'years_at_address' => 3,
            'months_at_address' => 6,
        ]);

        $this->assertEquals(42, $address->total_months_at_address);
    }

    public function test_calculate_residence_duration(): void
    {
        $address = PersonAddress::factory()->home()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
            'valid_from' => now()->subYears(2)->subMonths(3),
            'years_at_address' => 0,
            'months_at_address' => 0,
        ]);

        $address->calculateResidenceDuration();

        $fresh = $address->fresh();
        $this->assertEquals(2, $fresh->years_at_address);
        $this->assertEquals(3, $fresh->months_at_address);
    }

    // =====================================================
    // Verification Methods Tests
    // =====================================================

    public function test_mark_as_verified(): void
    {
        $address = PersonAddress::factory()->home()->pending()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $address->markAsVerified('GEOLOCATION', null, ['accuracy' => 'high']);

        $fresh = $address->fresh();
        $this->assertEquals(PersonAddress::STATUS_VERIFIED, $fresh->status);
        $this->assertNotNull($fresh->verified_at);
        $this->assertNull($fresh->verified_by);
        $this->assertEquals('GEOLOCATION', $fresh->verification_method);
    }

    public function test_mark_as_rejected(): void
    {
        $address = PersonAddress::factory()->home()->pending()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $address->markAsRejected('Address does not match documents');

        $fresh = $address->fresh();
        $this->assertEquals(PersonAddress::STATUS_REJECTED, $fresh->status);
        $this->assertEquals('Address does not match documents', $fresh->notes);
    }

    // =====================================================
    // Address History Tests
    // =====================================================

    public function test_replace_with_creates_new_address(): void
    {
        $oldAddress = PersonAddress::factory()->home()->verified()->current()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
            'street' => 'Old Street',
        ]);

        $newAddress = $oldAddress->replaceWith([
            'street' => 'New Street',
            'exterior_number' => '999',
            'neighborhood' => 'New Colony',
            'municipality' => 'New Municipality',
            'state' => 'JAL',
            'postal_code' => '44100',
        ], 'MOVED');

        // Check old address
        $oldFresh = $oldAddress->fresh();
        $this->assertFalse($oldFresh->is_current);
        $this->assertNotNull($oldFresh->valid_until);
        $this->assertNotNull($oldFresh->replaced_at);
        $this->assertEquals('MOVED', $oldFresh->replacement_reason);

        // Check new address
        $this->assertTrue($newAddress->is_current);
        $this->assertEquals(PersonAddress::STATUS_PENDING, $newAddress->status);
        $this->assertEquals('New Street', $newAddress->street);
        $this->assertEquals($oldAddress->id, $newAddress->previous_version_id);
    }

    // =====================================================
    // Geolocation Tests
    // =====================================================

    public function test_set_geolocation(): void
    {
        $address = PersonAddress::factory()->home()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
            'latitude' => null,
            'longitude' => null,
        ]);

        $address->setGeolocation(19.4326, -99.1332, 'ROOFTOP');

        $fresh = $address->fresh();
        $this->assertEquals(19.4326, $fresh->latitude);
        $this->assertEquals(-99.1332, $fresh->longitude);
        $this->assertEquals('ROOFTOP', $fresh->geocode_accuracy);
    }

    public function test_has_geolocation(): void
    {
        $withGeo = PersonAddress::factory()->home()->withGeolocation()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $withoutGeo = PersonAddress::factory()->home()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
            'latitude' => null,
            'longitude' => null,
        ]);

        $this->assertTrue($withGeo->hasGeolocation());
        $this->assertFalse($withoutGeo->hasGeolocation());
    }

    // =====================================================
    // Scope Tests
    // =====================================================

    public function test_current_scope(): void
    {
        PersonAddress::factory()->home()->current()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);
        PersonAddress::factory()->home()->historical()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $current = PersonAddress::current()->count();

        $this->assertEquals(1, $current);
    }

    public function test_verified_scope(): void
    {
        PersonAddress::factory()->home()->verified()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);
        PersonAddress::factory()->home()->pending()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $verified = PersonAddress::verified()->count();

        $this->assertEquals(1, $verified);
    }

    public function test_home_scope(): void
    {
        PersonAddress::factory()->home()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);
        PersonAddress::factory()->work()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $homes = PersonAddress::home()->count();

        $this->assertEquals(1, $homes);
    }

    public function test_by_postal_code_scope(): void
    {
        PersonAddress::factory()->home()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
            'postal_code' => '11550',
        ]);
        PersonAddress::factory()->home()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
            'postal_code' => '06600',
        ]);

        $results = PersonAddress::byPostalCode('11550')->count();

        $this->assertEquals(1, $results);
    }

    // =====================================================
    // Static Finder Tests
    // =====================================================

    public function test_find_current_by_type(): void
    {
        $home = PersonAddress::factory()->home()->current()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $found = PersonAddress::findCurrentByType($this->person->id, 'HOME');

        $this->assertNotNull($found);
        $this->assertEquals($home->id, $found->id);
    }

    public function test_find_current_by_type_returns_null_when_not_found(): void
    {
        PersonAddress::factory()->work()->current()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $found = PersonAddress::findCurrentByType($this->person->id, 'HOME');

        $this->assertNull($found);
    }
}
