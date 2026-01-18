<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\Application;
use App\Models\Product;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->setUpAdmin();

        $this->product = Product::where('tenant_id', $this->tenant->id)->first();
    }

    public function test_admin_can_view_dashboard(): void
    {
        $response = $this->actingAsAdmin()
            ->getJson('/api/admin/dashboard');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'summary' => [
                        'total_applications',
                        'today_applications',
                        'pending_review',
                    ],
                    'amounts',
                    'by_status',
                    'recent_applications',
                ],
            ]);
    }

    public function test_admin_can_view_stats(): void
    {
        $response = $this->actingAsAdmin()
            ->getJson('/api/admin/dashboard/stats');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'applications_over_time',
                    'conversion',
                    'avg_processing_days',
                    'top_products',
                ],
            ]);
    }

    public function test_admin_can_list_applications(): void
    {
        // Create some applications
        $applicant = Applicant::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => \App\Models\User::factory()->create(['tenant_id' => $this->tenant->id])->id,
        ]);

        Application::factory()->count(5)->create([
            'tenant_id' => $this->tenant->id,
            'applicant_id' => $applicant->id,
            'product_id' => $this->product->id,
        ]);

        $response = $this->actingAsAdmin()
            ->getJson('/api/admin/applications');

        $response->assertStatus(200);
        $this->assertGreaterThanOrEqual(5, count($response->json('data')));
    }

    public function test_admin_can_update_application_status(): void
    {
        $applicant = Applicant::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => \App\Models\User::factory()->create(['tenant_id' => $this->tenant->id])->id,
        ]);

        $application = Application::factory()->create([
            'tenant_id' => $this->tenant->id,
            'applicant_id' => $applicant->id,
            'product_id' => $this->product->id,
            'status' => 'SUBMITTED',
        ]);

        $response = $this->actingAsAdmin()
            ->putJson("/api/admin/applications/{$application->id}/status", [
                'status' => 'IN_REVIEW',
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('applications', [
            'id' => $application->id,
            'status' => 'IN_REVIEW',
        ]);
    }

    public function test_admin_can_export_applications(): void
    {
        $response = $this->actingAsAdmin()
            ->get('/api/admin/reports/applications/export');

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }

    public function test_admin_can_view_portfolio_report(): void
    {
        $response = $this->actingAsAdmin()
            ->getJson('/api/admin/reports/portfolio');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'summary' => [
                    'total_loans',
                    'total_original_amount',
                    'total_outstanding',
                ],
            ]);
    }

    public function test_non_admin_cannot_access_dashboard(): void
    {
        $this->setUpUser();

        $response = $this->actingAsUser()
            ->getJson('/api/admin/dashboard');

        $response->assertStatus(403);
    }
}
