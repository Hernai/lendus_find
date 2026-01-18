<?php

namespace Tests\Feature\Api\Person;

use App\Models\Person;
use App\Models\PersonAddress;
use App\Models\StaffAccount;
use App\Models\Tenant;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PersonAddressControllerTest extends TestCase
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

    public function test_can_list_addresses(): void
    {
        PersonAddress::factory()->home()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $response = $this->getJson("/api/persons/{$this->person->id}/addresses");

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_can_create_address(): void
    {
        $response = $this->postJson("/api/persons/{$this->person->id}/addresses", [
            'type' => 'HOME',
            'street' => 'Calle Principal',
            'exterior_number' => '123',
            'neighborhood' => 'Centro',
            'municipality' => 'Guadalajara',
            'state' => 'JAL',
            'postal_code' => '44100',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.street', 'Calle Principal')
            ->assertJsonPath('data.type', 'HOME');
    }

    public function test_create_address_validates_required_fields(): void
    {
        $response = $this->postJson("/api/persons/{$this->person->id}/addresses", []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['street', 'exterior_number', 'neighborhood', 'municipality', 'state', 'postal_code']);
    }

    public function test_can_show_address(): void
    {
        $address = PersonAddress::factory()->home()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $response = $this->getJson("/api/persons/{$this->person->id}/addresses/{$address->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $address->id);
    }

    public function test_can_update_address(): void
    {
        $address = PersonAddress::factory()->home()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $response = $this->putJson("/api/persons/{$this->person->id}/addresses/{$address->id}", [
            'street' => 'Nueva Calle',
            'housing_type' => 'OWNED',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.street', 'Nueva Calle')
            ->assertJsonPath('data.housing_type', 'OWNED');
    }

    public function test_can_delete_address(): void
    {
        $address = PersonAddress::factory()->home()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $response = $this->deleteJson("/api/persons/{$this->person->id}/addresses/{$address->id}");

        $response->assertOk();
        $this->assertSoftDeleted('person_addresses', ['id' => $address->id]);
    }

    public function test_can_get_current_addresses(): void
    {
        PersonAddress::factory()->home()->current()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);
        PersonAddress::factory()->work()->current()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);
        PersonAddress::factory()->home()->historical()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $response = $this->getJson("/api/persons/{$this->person->id}/addresses/current");

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_can_get_current_home(): void
    {
        PersonAddress::factory()->home()->current()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
            'street' => 'Current Home Street',
        ]);

        $response = $this->getJson("/api/persons/{$this->person->id}/addresses/current-home");

        $response->assertOk()
            ->assertJsonPath('found', true)
            ->assertJsonPath('data.street', 'Current Home Street');
    }

    public function test_can_set_home_address(): void
    {
        $response = $this->postJson("/api/persons/{$this->person->id}/addresses/home", [
            'street' => 'Nueva Casa',
            'exterior_number' => '456',
            'neighborhood' => 'Polanco',
            'municipality' => 'CDMX',
            'state' => 'CDMX',
            'postal_code' => '11550',
        ]);

        $response->assertSuccessful()
            ->assertJsonPath('data.type', 'HOME')
            ->assertJsonPath('data.street', 'Nueva Casa');
    }

    public function test_can_verify_address(): void
    {
        $address = PersonAddress::factory()->home()->pending()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $response = $this->postJson("/api/persons/{$this->person->id}/addresses/{$address->id}/verify", [
            'method' => 'GEOLOCATION',
            'verification_data' => ['accuracy' => 'high'],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'VERIFIED')
            ->assertJsonPath('data.verification_method', 'GEOLOCATION');
    }

    public function test_can_reject_address(): void
    {
        $address = PersonAddress::factory()->home()->pending()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $response = $this->postJson("/api/persons/{$this->person->id}/addresses/{$address->id}/reject", [
            'reason' => 'Invalid address',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'REJECTED');
    }

    public function test_can_set_geolocation(): void
    {
        $address = PersonAddress::factory()->home()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $response = $this->postJson("/api/persons/{$this->person->id}/addresses/{$address->id}/geolocation", [
            'latitude' => 19.4326,
            'longitude' => -99.1332,
            'accuracy' => 5.0,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.latitude', '19.43260000')
            ->assertJsonPath('data.longitude', '-99.13320000');
    }

    public function test_can_get_history(): void
    {
        PersonAddress::factory()->home()->current()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);
        PersonAddress::factory()->home()->historical()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $response = $this->getJson("/api/persons/{$this->person->id}/addresses/history/home");

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_can_check_has_verified(): void
    {
        PersonAddress::factory()->home()->verified()->current()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $this->person->id,
        ]);

        $response = $this->getJson("/api/persons/{$this->person->id}/addresses/has-verified/home");

        $response->assertOk()
            ->assertJsonPath('has_verified', true);
    }
}
