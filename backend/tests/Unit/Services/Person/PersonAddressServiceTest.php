<?php

namespace Tests\Unit\Services\Person;

use App\Models\Person;
use App\Models\PersonAddress;
use App\Models\Tenant;
use App\Services\Person\PersonAddressService;
use Tests\TestCase;

class PersonAddressServiceTest extends TestCase
{
    protected PersonAddressService $service;
    protected ?Person $person = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PersonAddressService();
        $this->tenant = Tenant::factory()->create();
        $this->person = Person::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    public function test_can_create_address(): void
    {
        $address = $this->service->create($this->person, [
            'type' => 'HOME',
            'street' => 'Calle Principal',
            'exterior_number' => '123',
            'neighborhood' => 'Centro',
            'municipality' => 'Guadalajara',
            'state' => 'JAL',
            'postal_code' => '44100',
        ]);

        $this->assertDatabaseHas('person_addresses', [
            'id' => $address->id,
            'street' => 'Calle Principal',
            'postal_code' => '44100',
        ]);
    }

    public function test_creating_current_marks_existing_as_historical(): void
    {
        $old = $this->service->create($this->person, [
            'type' => 'HOME',
            'street' => 'Calle Vieja',
            'exterior_number' => '100',
            'neighborhood' => 'Centro',
            'municipality' => 'Guadalajara',
            'state' => 'JAL',
            'postal_code' => '44100',
            'is_current' => true,
        ]);

        $new = $this->service->create($this->person, [
            'type' => 'HOME',
            'street' => 'Calle Nueva',
            'exterior_number' => '200',
            'neighborhood' => 'Centro',
            'municipality' => 'Guadalajara',
            'state' => 'JAL',
            'postal_code' => '44100',
            'is_current' => true,
        ]);

        $this->assertFalse($old->fresh()->is_current);
        $this->assertTrue($new->is_current);
    }

    public function test_can_get_current_home(): void
    {
        PersonAddress::factory()->home()->current()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
            'street' => 'Current Home',
        ]);
        PersonAddress::factory()->home()->historical()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
            'street' => 'Old Home',
        ]);

        $current = $this->service->getCurrentHome($this->person->id);

        $this->assertNotNull($current);
        $this->assertEquals('Current Home', $current->street);
    }

    public function test_can_verify_address(): void
    {
        $address = PersonAddress::factory()->home()->pending()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $verified = $this->service->verify($address, 'GEOLOCATION', null, ['accuracy' => 'high']);

        $this->assertEquals(PersonAddress::STATUS_VERIFIED, $verified->status);
        $this->assertNotNull($verified->verified_at);
    }

    public function test_can_reject_address(): void
    {
        $address = PersonAddress::factory()->home()->pending()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $rejected = $this->service->reject($address, 'Invalid address');

        $this->assertEquals(PersonAddress::STATUS_REJECTED, $rejected->status);
    }

    public function test_can_replace_address(): void
    {
        $old = PersonAddress::factory()->home()->verified()->current()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
            'street' => 'Old Street',
        ]);

        $new = $this->service->replace($old, [
            'street' => 'New Street',
            'exterior_number' => '300',
            'neighborhood' => 'New Neighborhood',
            'municipality' => 'New Municipality',
            'state' => 'JAL',
            'postal_code' => '44200',
        ], 'MOVED');

        $this->assertFalse($old->fresh()->is_current);
        $this->assertTrue($new->is_current);
        $this->assertEquals('New Street', $new->street);
        $this->assertEquals($old->id, $new->previous_version_id);
    }

    public function test_set_home_address_creates_if_not_exists(): void
    {
        $address = $this->service->setHomeAddress($this->person, [
            'street' => 'Calle Nueva',
            'exterior_number' => '100',
            'neighborhood' => 'Centro',
            'municipality' => 'CDMX',
            'state' => 'CDMX',
            'postal_code' => '06600',
        ]);

        $this->assertEquals('HOME', $address->type);
        $this->assertTrue($address->is_current);
    }

    public function test_can_set_geolocation(): void
    {
        $address = PersonAddress::factory()->home()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $updated = $this->service->setGeolocation($address, 19.4326, -99.1332, 5.0);

        $this->assertEquals(19.4326, $updated->latitude);
        $this->assertEquals(-99.1332, $updated->longitude);
        $this->assertEquals('ROOFTOP', $updated->geocode_accuracy);
        $this->assertEquals(5.0, $updated->metadata['geolocation']['accuracy']);
    }

    public function test_has_verified_returns_true_when_verified(): void
    {
        PersonAddress::factory()->home()->verified()->current()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $this->assertTrue($this->service->hasVerified($this->person->id, 'HOME'));
    }

    public function test_get_history(): void
    {
        PersonAddress::factory()->home()->current()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);
        PersonAddress::factory()->home()->historical()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $history = $this->service->getHistory($this->person->id, 'HOME');

        $this->assertCount(3, $history);
    }

    public function test_format_single_line(): void
    {
        $address = PersonAddress::factory()->home()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
            'street' => 'Av. Reforma',
            'exterior_number' => '500',
            'interior_number' => '10A',
            'neighborhood' => 'Cuauhtémoc',
            'municipality' => 'Cuauhtémoc',
            'state' => 'CDMX',
            'postal_code' => '06500',
        ]);

        $formatted = $this->service->formatSingleLine($address);

        $this->assertStringContainsString('Av. Reforma', $formatted);
        $this->assertStringContainsString('500', $formatted);
    }
}
