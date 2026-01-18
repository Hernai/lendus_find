<?php

namespace Tests\Unit\Services;

use App\Models\ApplicantAccount;
use App\Models\ApplicationV2;
use App\Models\Company;
use App\Models\Person;
use App\Models\Product;
use App\Models\StaffAccount;
use App\Models\Tenant;
use App\Services\ApplicationV2Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplicationV2ServiceTest extends TestCase
{
    use RefreshDatabase;

    protected ApplicationV2Service $service;
    protected Person $person;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(ApplicationV2Service::class);
        $this->tenant = Tenant::factory()->create();
        $this->person = Person::factory()->for($this->tenant)->create();
        $this->product = Product::factory()->for($this->tenant)->create([
            'interest_rate' => 24.0,  // Annual rate as percentage
            'opening_commission' => 2.0,  // Commission as percentage
            'min_amount' => 5000,
            'max_amount' => 500000,
        ]);
    }

    // =====================================================
    // Create Tests
    // =====================================================

    public function test_create_for_person(): void
    {
        $loanData = [
            'amount' => 50000,
            'term_months' => 12,
            'purpose' => 'PERSONAL',
        ];

        $application = $this->service->createForPerson(
            $this->tenant,
            $this->person,
            $this->product,
            $loanData
        );

        $this->assertInstanceOf(ApplicationV2::class, $application);
        $this->assertEquals(ApplicationV2::TYPE_INDIVIDUAL, $application->applicant_type);
        $this->assertEquals($this->person->id, $application->person_id);
        $this->assertNull($application->company_id);
        $this->assertEquals(50000, $application->requested_amount);
        $this->assertEquals(12, $application->requested_term_months);
        $this->assertEquals(ApplicationV2::STATUS_DRAFT, $application->status);
        $this->assertNotNull($application->monthly_payment);
        $this->assertNotNull($application->expires_at);
    }

    public function test_create_for_company(): void
    {
        $company = Company::factory()->for($this->tenant)->create();

        $loanData = [
            'amount' => 100000,
            'term_months' => 24,
            'purpose' => 'BUSINESS',
        ];

        $application = $this->service->createForCompany(
            $this->tenant,
            $company,
            $this->product,
            $loanData
        );

        $this->assertInstanceOf(ApplicationV2::class, $application);
        $this->assertEquals(ApplicationV2::TYPE_COMPANY, $application->applicant_type);
        $this->assertEquals($company->id, $application->company_id);
        $this->assertNull($application->person_id);
    }

    // =====================================================
    // Update Tests
    // =====================================================

    public function test_update_loan_terms(): void
    {
        $application = ApplicationV2::factory()
            ->for($this->tenant)
            ->for($this->product)
            ->individual($this->person)
            ->draft()
            ->create([
                'requested_amount' => 50000,
                'requested_term_months' => 12,
            ]);

        $result = $this->service->updateLoanTerms($application, [
            'amount' => 75000,
            'term_months' => 18,
            'purpose' => 'VEHICLE',
        ]);

        $this->assertEquals(75000, $result->requested_amount);
        $this->assertEquals(18, $result->requested_term_months);
        $this->assertEquals('VEHICLE', $result->purpose);
    }

    public function test_update_loan_terms_throws_for_non_draft(): void
    {
        $application = ApplicationV2::factory()
            ->for($this->tenant)
            ->for($this->product)
            ->individual($this->person)
            ->submitted()
            ->create();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Application cannot be edited in current status');

        $this->service->updateLoanTerms($application, ['amount' => 75000]);
    }

    // =====================================================
    // Assignment Tests
    // =====================================================

    public function test_assign_to_staff(): void
    {
        $staff = StaffAccount::factory()->for($this->tenant)->create();
        $supervisor = StaffAccount::factory()->for($this->tenant)->create();

        $application = ApplicationV2::factory()
            ->for($this->tenant)
            ->for($this->product)
            ->individual($this->person)
            ->submitted()
            ->create();

        $result = $this->service->assign($application, $staff, $supervisor);

        $this->assertEquals($staff->id, $result->assigned_to);
        $this->assertEquals($supervisor->id, $result->assigned_by);
        $this->assertNotNull($result->assigned_at);
    }

    // =====================================================
    // Status Change Tests
    // =====================================================

    public function test_change_status(): void
    {
        $staff = StaffAccount::factory()->for($this->tenant)->create();

        $application = ApplicationV2::factory()
            ->for($this->tenant)
            ->for($this->product)
            ->individual($this->person)
            ->submitted()
            ->create();

        $result = $this->service->changeStatus(
            $application,
            ApplicationV2::STATUS_IN_REVIEW,
            $staff,
            'Iniciando revisión'
        );

        $this->assertEquals(ApplicationV2::STATUS_IN_REVIEW, $result->status);
        $this->assertCount(1, $result->statusHistory);
    }

    public function test_approve(): void
    {
        $staff = StaffAccount::factory()->for($this->tenant)->create();

        $application = ApplicationV2::factory()
            ->for($this->tenant)
            ->for($this->product)
            ->individual($this->person)
            ->inReview()
            ->create([
                'requested_amount' => 50000,
                'requested_term_months' => 12,
            ]);

        $result = $this->service->approve(
            $application,
            $staff,
            45000, // Approved with lower amount
            12,
            0.22,
            'Approved with minor adjustment'
        );

        $this->assertTrue($result->isApproved());
        $this->assertEquals(45000, $result->approved_amount);
        $this->assertEquals(0.22, $result->approved_interest_rate);
        $this->assertEquals($staff->id, $result->decision_by);
    }

    public function test_reject(): void
    {
        $staff = StaffAccount::factory()->for($this->tenant)->create();

        $application = ApplicationV2::factory()
            ->for($this->tenant)
            ->for($this->product)
            ->individual($this->person)
            ->inReview()
            ->create();

        $result = $this->service->reject(
            $application,
            $staff,
            'Insufficient income',
            'DTI ratio exceeds 40%'
        );

        $this->assertTrue($result->isRejected());
        $this->assertEquals('Insufficient income', $result->rejection_reason);
        $this->assertEquals('DTI ratio exceeds 40%', $result->decision_notes);
    }

    // =====================================================
    // Counter Offer Tests
    // =====================================================

    public function test_send_counter_offer(): void
    {
        $staff = StaffAccount::factory()->for($this->tenant)->create();

        $application = ApplicationV2::factory()
            ->for($this->tenant)
            ->for($this->product)
            ->individual($this->person)
            ->inReview()
            ->create([
                'requested_amount' => 100000,
                'requested_term_months' => 12,
            ]);

        $offer = [
            'amount' => 75000,
            'term_months' => 18,
        ];

        $result = $this->service->sendCounterOffer(
            $application,
            $staff,
            $offer,
            'Lower amount due to income constraints'
        );

        $this->assertTrue($result->has_counter_offer);
        $this->assertEquals(75000, $result->counter_offer['amount']);
        $this->assertEquals(18, $result->counter_offer['term_months']);
        $this->assertArrayHasKey('monthly_payment', $result->counter_offer);
    }

    public function test_respond_to_counter_offer_accept(): void
    {
        $account = ApplicantAccount::factory()->for($this->tenant)->create();

        $application = ApplicationV2::factory()
            ->for($this->tenant)
            ->for($this->product)
            ->individual($this->person)
            ->inReview()
            ->withCounterOffer([
                'amount' => 75000,
                'term_months' => 18,
                'interest_rate' => 0.24,
                'monthly_payment' => 4500,
            ])
            ->create();

        $result = $this->service->respondToCounterOffer($application, $account, true);

        $this->assertTrue($result->counter_offer_accepted);
        $this->assertTrue($result->isApproved());
        $this->assertEquals(75000, $result->approved_amount);
    }

    public function test_respond_to_counter_offer_throws_if_no_offer(): void
    {
        $account = ApplicantAccount::factory()->for($this->tenant)->create();

        $application = ApplicationV2::factory()
            ->for($this->tenant)
            ->for($this->product)
            ->individual($this->person)
            ->inReview()
            ->create();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('No counter offer to respond to');

        $this->service->respondToCounterOffer($application, $account, true);
    }

    // =====================================================
    // Cancel Tests
    // =====================================================

    public function test_cancel(): void
    {
        $account = ApplicantAccount::factory()->for($this->tenant)->create();

        $application = ApplicationV2::factory()
            ->for($this->tenant)
            ->for($this->product)
            ->individual($this->person)
            ->submitted()
            ->create();

        $result = $this->service->cancel(
            $application,
            $account->id,
            ApplicantAccount::class,
            'Changed my mind'
        );

        $this->assertTrue($result->isCancelled());
    }

    public function test_cancel_throws_for_final_status(): void
    {
        $application = ApplicationV2::factory()
            ->for($this->tenant)
            ->for($this->product)
            ->individual($this->person)
            ->synced()
            ->create();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Application cannot be cancelled in current status');

        $this->service->cancel($application, 'user-id', 'User', 'Reason');
    }

    // =====================================================
    // Sync Tests
    // =====================================================

    public function test_mark_synced(): void
    {
        $application = ApplicationV2::factory()
            ->for($this->tenant)
            ->for($this->product)
            ->individual($this->person)
            ->approved()
            ->create();

        $syncData = ['contract_id' => 'CTR-12345'];

        $result = $this->service->markSynced(
            $application,
            'EXT-001',
            'SAP',
            $syncData
        );

        $this->assertTrue($result->isSynced());
        $this->assertEquals('EXT-001', $result->external_id);
        $this->assertEquals('SAP', $result->external_system);
        $this->assertEquals($syncData, $result->sync_data);
    }

    // =====================================================
    // Verification and Risk Tests
    // =====================================================

    public function test_update_verification(): void
    {
        $application = ApplicationV2::factory()
            ->for($this->tenant)
            ->for($this->product)
            ->individual($this->person)
            ->create();

        $result = $this->service->updateVerification($application, [
            'identity_verified' => true,
            'address_verified' => true,
        ]);

        $this->assertTrue($result->verification_checklist['identity_verified']);
        $this->assertTrue($result->verification_checklist['address_verified']);
    }

    public function test_set_risk_assessment(): void
    {
        $application = ApplicationV2::factory()
            ->for($this->tenant)
            ->for($this->product)
            ->individual($this->person)
            ->create();

        $riskData = [
            'score' => 720,
            'factors' => ['low_income'],
        ];

        $result = $this->service->setRiskAssessment(
            $application,
            ApplicationV2::RISK_MEDIUM,
            $riskData
        );

        $this->assertEquals(ApplicationV2::RISK_MEDIUM, $result->risk_level);
        $this->assertEquals(720, $result->risk_data['score']);
    }

    // =====================================================
    // Query Tests
    // =====================================================

    public function test_get_for_person(): void
    {
        ApplicationV2::factory()
            ->for($this->tenant)
            ->for($this->product)
            ->individual($this->person)
            ->count(3)
            ->create();

        $applications = $this->service->getForPerson($this->person);

        $this->assertCount(3, $applications);
    }

    public function test_get_for_company(): void
    {
        $company = Company::factory()->for($this->tenant)->create();

        ApplicationV2::factory()
            ->for($this->tenant)
            ->for($this->product)
            ->forCompany($company)
            ->count(2)
            ->create();

        $applications = $this->service->getForCompany($company);

        $this->assertCount(2, $applications);
    }

    public function test_get_assigned_to(): void
    {
        $staff = StaffAccount::factory()->for($this->tenant)->create();

        ApplicationV2::factory()
            ->for($this->tenant)
            ->for($this->product)
            ->individual($this->person)
            ->assigned($staff)
            ->count(3)
            ->create();

        ApplicationV2::factory()
            ->for($this->tenant)
            ->for($this->product)
            ->individual($this->person)
            ->create(); // Not assigned

        $applications = $this->service->getAssignedTo($staff);

        $this->assertCount(3, $applications);
    }

    public function test_get_unassigned(): void
    {
        ApplicationV2::factory()
            ->for($this->tenant)
            ->for($this->product)
            ->individual($this->person)
            ->submitted()
            ->count(2)
            ->create();

        $staff = StaffAccount::factory()->for($this->tenant)->create();
        ApplicationV2::factory()
            ->for($this->tenant)
            ->for($this->product)
            ->individual($this->person)
            ->submitted()
            ->assigned($staff)
            ->create();

        $unassigned = $this->service->getUnassigned($this->tenant);

        $this->assertCount(2, $unassigned);
    }

    public function test_list_with_filters(): void
    {
        ApplicationV2::factory()
            ->for($this->tenant)
            ->for($this->product)
            ->individual($this->person)
            ->submitted()
            ->count(5)
            ->create();

        ApplicationV2::factory()
            ->for($this->tenant)
            ->for($this->product)
            ->individual($this->person)
            ->approved()
            ->count(3)
            ->create();

        $result = $this->service->list($this->tenant, ['status' => ApplicationV2::STATUS_SUBMITTED]);

        $this->assertEquals(5, $result->total());
    }

    public function test_get_statistics(): void
    {
        ApplicationV2::factory()
            ->for($this->tenant)
            ->for($this->product)
            ->individual($this->person)
            ->draft()
            ->count(2)
            ->create();

        ApplicationV2::factory()
            ->for($this->tenant)
            ->for($this->product)
            ->individual($this->person)
            ->submitted()
            ->count(3)
            ->create();

        ApplicationV2::factory()
            ->for($this->tenant)
            ->for($this->product)
            ->individual($this->person)
            ->approved()
            ->count(1)
            ->create();

        $stats = $this->service->getStatistics($this->tenant);

        $this->assertEquals(6, $stats['total']);
        $this->assertEquals(2, $stats['by_status']['draft']);
        $this->assertEquals(3, $stats['by_status']['submitted']);
        $this->assertEquals(1, $stats['by_status']['approved']);
    }
}
