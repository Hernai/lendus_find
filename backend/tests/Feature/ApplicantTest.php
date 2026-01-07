<?php

namespace Tests\Feature;

use App\Models\Applicant;
use Tests\TestCase;

class ApplicantTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->setUpUser();
    }

    public function test_can_create_applicant_profile(): void
    {
        $response = $this->actingAsUser()
            ->postJson('/api/applicant', [
                'first_name' => 'Juan',
                'last_name_1' => 'Pérez',
                'last_name_2' => 'García',
                'birth_date' => '1990-05-15',
                'curp' => 'PEGJ900515HDFRRC09',
                'rfc' => 'PEGJ900515XXX',
                'gender' => 'M',
                'nationality' => 'MEXICANA',
                'country_of_birth' => 'MEXICO',
                'state_of_birth' => 'CDMX',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'first_name',
                    'last_name_1',
                    'full_name',
                    'curp',
                ],
                'message',
            ]);

        $this->assertDatabaseHas('applicants', [
            'user_id' => $this->user->id,
            'curp' => 'PEGJ900515HDFRRC09',
        ]);
    }

    public function test_can_get_applicant_profile(): void
    {
        Applicant::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'first_name' => 'María',
            'last_name_1' => 'López',
        ]);

        $response = $this->actingAsUser()
            ->getJson('/api/applicant');

        $response->assertStatus(200)
            ->assertJsonPath('data.first_name', 'María')
            ->assertJsonPath('data.last_name_1', 'López');
    }

    public function test_can_update_personal_data(): void
    {
        Applicant::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'first_name' => 'Test',
            'last_name_1' => 'User',
        ]);

        $response = $this->actingAsUser()
            ->putJson('/api/applicant/personal-data', [
                'first_name' => 'Updated',
                'last_name_1' => 'Name',
                'birth_date' => '1985-10-20',
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('applicants', [
            'user_id' => $this->user->id,
            'first_name' => 'Updated',
            'last_name_1' => 'Name',
        ]);
    }

    public function test_requires_authentication(): void
    {
        $response = $this->withTenant()
            ->getJson('/api/applicant');

        $response->assertStatus(401);
    }

    public function test_can_validate_clabe(): void
    {
        Applicant::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
        ]);

        // Valid CLABE format
        $response = $this->actingAsUser()
            ->postJson('/api/applicant/validate-clabe', [
                'clabe' => '012345678901234567',
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'valid',
                    'bank_code',
                    'bank_name',
                ],
            ]);
    }
}
