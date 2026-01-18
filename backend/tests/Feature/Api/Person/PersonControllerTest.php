<?php

namespace Tests\Feature\Api\Person;

use App\Models\Person;
use App\Models\PersonIdentification;
use App\Models\StaffAccount;
use App\Models\Tenant;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PersonControllerTest extends TestCase
{
    protected StaffAccount $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();
        $this->staff = StaffAccount::factory()->admin()->create(['tenant_id' => $this->tenant->id]);
        Sanctum::actingAs($this->staff, ['*']);
    }

    public function test_can_list_persons(): void
    {
        Person::factory()->count(5)->create(['tenant_id' => $this->tenant->id]);

        $response = $this->getJson('/api/persons');

        $response->assertOk()
            ->assertJsonCount(5, 'data');
    }

    public function test_can_create_person(): void
    {
        $data = [
            'first_name' => 'Juan',
            'last_name_1' => 'García',
            'last_name_2' => 'López',
            'birth_date' => '1990-05-15',
            'gender' => 'M',
            'marital_status' => 'SINGLE',
        ];

        $response = $this->postJson('/api/persons', $data);

        $response->assertCreated()
            ->assertJsonPath('data.first_name', 'Juan')
            ->assertJsonPath('data.last_name_1', 'García');

        $this->assertDatabaseHas('persons', [
            'first_name' => 'Juan',
            'last_name_1' => 'García',
        ]);
    }

    public function test_create_person_validates_required_fields(): void
    {
        $response = $this->postJson('/api/persons', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['first_name', 'last_name_1']);
    }

    public function test_can_show_person(): void
    {
        $person = Person::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->getJson("/api/persons/{$person->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $person->id);
    }

    public function test_can_show_person_with_relations(): void
    {
        $person = Person::factory()->create(['tenant_id' => $this->tenant->id]);
        PersonIdentification::factory()->curp()->current()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $person->id,
        ]);

        $response = $this->getJson("/api/persons/{$person->id}?include=currentCurp");

        $response->assertOk()
            ->assertJsonPath('data.id', $person->id)
            ->assertJsonStructure(['data' => ['current_curp']]);
    }

    public function test_can_update_person(): void
    {
        $person = Person::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->putJson("/api/persons/{$person->id}", [
            'first_name' => 'Carlos',
            'marital_status' => 'MARRIED',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.first_name', 'Carlos')
            ->assertJsonPath('data.marital_status', 'MARRIED');
    }

    public function test_can_delete_person(): void
    {
        $person = Person::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->deleteJson("/api/persons/{$person->id}");

        $response->assertOk();
        $this->assertSoftDeleted('persons', ['id' => $person->id]);
    }

    public function test_can_get_person_summary(): void
    {
        $person = Person::factory()->kycVerified()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->getJson("/api/persons/{$person->id}/summary");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id', 'full_name', 'age', 'kyc_status',
                    'is_kyc_verified', 'profile_completeness'
                ]
            ]);
    }

    public function test_can_update_kyc_status(): void
    {
        $person = Person::factory()->kycPending()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->postJson("/api/persons/{$person->id}/kyc-status", [
            'status' => 'VERIFIED',
            'kyc_data' => ['curp_verified' => true],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.kyc_status', 'VERIFIED');
    }

    public function test_can_recalculate_completeness(): void
    {
        $person = Person::factory()->create([
            'tenant_id' => $this->tenant->id,
            'profile_completeness' => 0,
        ]);

        $response = $this->postJson("/api/persons/{$person->id}/recalculate-completeness");

        $response->assertOk()
            ->assertJsonStructure(['data' => ['profile_completeness']]);
    }

    public function test_can_get_statistics(): void
    {
        Person::factory()->kycVerified()->count(3)->create(['tenant_id' => $this->tenant->id]);
        Person::factory()->kycPending()->count(2)->create(['tenant_id' => $this->tenant->id]);

        $response = $this->getJson('/api/persons/statistics');

        $response->assertOk()
            ->assertJsonPath('data.total', 5)
            ->assertJsonPath('data.kyc_verified', 3)
            ->assertJsonPath('data.kyc_pending', 2);
    }

    public function test_can_find_by_curp(): void
    {
        $person = Person::factory()->create(['tenant_id' => $this->tenant->id]);
        PersonIdentification::factory()->curp()->current()->create([
            'tenant_id' => $this->tenant->id,
            'person_id' => $person->id,
            'identifier_value' => 'GALO850101HDFRPR09',
        ]);

        $response = $this->postJson('/api/persons/find-by-curp', [
            'curp' => 'GALO850101HDFRPR09',
        ]);

        $response->assertOk()
            ->assertJsonPath('found', true)
            ->assertJsonPath('data.id', $person->id);
    }

    public function test_find_by_curp_returns_not_found(): void
    {
        $response = $this->postJson('/api/persons/find-by-curp', [
            'curp' => 'XXXX000000XXXXXXXX',
        ]);

        $response->assertOk()
            ->assertJsonPath('found', false)
            ->assertJsonPath('data', null);
    }

    public function test_can_filter_persons_by_kyc_status(): void
    {
        Person::factory()->kycVerified()->count(3)->create(['tenant_id' => $this->tenant->id]);
        Person::factory()->kycPending()->count(2)->create(['tenant_id' => $this->tenant->id]);

        $response = $this->getJson('/api/persons?kyc_status=VERIFIED');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_can_paginate_persons(): void
    {
        Person::factory()->count(25)->create(['tenant_id' => $this->tenant->id]);

        $response = $this->getJson('/api/persons?per_page=10');

        $response->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('meta.total', 25);
    }
}
