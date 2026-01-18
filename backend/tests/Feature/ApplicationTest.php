<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\Application;
use App\Models\Product;
use Tests\TestCase;

class ApplicationTest extends TestCase
{
    protected Applicant $applicant;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->setUpUser();

        $this->applicant = Applicant::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
        ]);

        $this->product = Product::where('tenant_id', $this->tenant->id)->first();
    }

    public function test_can_list_applications(): void
    {
        Application::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'applicant_id' => $this->applicant->id,
            'product_id' => $this->product->id,
        ]);

        $response = $this->actingAsUser()
            ->getJson('/api/applications');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'folio',
                        'status',
                        'requested_amount',
                    ],
                ],
            ]);

        $this->assertCount(3, $response->json('data'));
    }

    public function test_can_create_application(): void
    {
        $response = $this->actingAsUser()
            ->postJson('/api/applications', [
                'product_id' => $this->product->id,
                'requested_amount' => 50000,
                'term_months' => 12,
                'payment_frequency' => 'MONTHLY',
                'purpose' => 'Gastos personales',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'folio',
                    'status',
                    'requested_amount',
                ],
            ]);

        $this->assertDatabaseHas('applications', [
            'applicant_id' => $this->applicant->id,
            'requested_amount' => 50000,
            'status' => 'DRAFT',
        ]);
    }

    public function test_can_view_application(): void
    {
        $application = Application::factory()->create([
            'tenant_id' => $this->tenant->id,
            'applicant_id' => $this->applicant->id,
            'product_id' => $this->product->id,
            'requested_amount' => 75000,
        ]);

        $response = $this->actingAsUser()
            ->getJson("/api/applications/{$application->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.requested_amount', 75000);
    }

    public function test_submit_requires_complete_data(): void
    {
        $application = Application::factory()->create([
            'tenant_id' => $this->tenant->id,
            'applicant_id' => $this->applicant->id,
            'product_id' => $this->product->id,
            'status' => 'DRAFT',
        ]);

        // Submit should fail because applicant doesn't have all required data
        $response = $this->actingAsUser()
            ->postJson("/api/applications/{$application->id}/submit");

        // Returns 422 with validation errors (address, employment, signature, references)
        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors',
            ]);
    }

    public function test_can_cancel_application(): void
    {
        $application = Application::factory()->create([
            'tenant_id' => $this->tenant->id,
            'applicant_id' => $this->applicant->id,
            'product_id' => $this->product->id,
            'status' => 'DRAFT',
        ]);

        $response = $this->actingAsUser()
            ->postJson("/api/applications/{$application->id}/cancel");

        $response->assertStatus(200);

        $this->assertDatabaseHas('applications', [
            'id' => $application->id,
            'status' => 'CANCELLED',
        ]);
    }

    public function test_cannot_view_others_application(): void
    {
        // Create another user's application
        $otherUser = \App\Models\User::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
        $otherApplicant = Applicant::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $otherUser->id,
        ]);
        $application = Application::factory()->create([
            'tenant_id' => $this->tenant->id,
            'applicant_id' => $otherApplicant->id,
            'product_id' => $this->product->id,
        ]);

        $response = $this->actingAsUser()
            ->getJson("/api/applications/{$application->id}");

        $response->assertStatus(404);
    }
}
