<?php

namespace Tests\Unit\Models;

use App\Models\ApplicantAccount;
use App\Models\ApplicationStatusHistory;
use App\Models\ApplicationV2;
use App\Models\Company;
use App\Models\DocumentV2;
use App\Models\Person;
use App\Models\Product;
use App\Models\StaffAccount;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplicationV2Test extends TestCase
{
    use RefreshDatabase;

    protected Person $person;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();
        $this->person = Person::factory()->for($this->tenant)->create();
        $this->product = Product::factory()->for($this->tenant)->create();
    }

    // =====================================================
    // Relationship Tests
    // =====================================================

    public function test_belongs_to_tenant(): void
    {
        $application = ApplicationV2::factory()
            ->for($this->tenant)
            ->for($this->product)
            ->individual($this->person)
            ->create();

        $this->assertInstanceOf(Tenant::class, $application->tenant);
        $this->assertEquals($this->tenant->id, $application->tenant->id);
    }

    public function test_belongs_to_product(): void
    {
        $application = ApplicationV2::factory()
            ->for($this->tenant)
            ->for($this->product)
            ->individual($this->person)
            ->create();

        $this->assertInstanceOf(Product::class, $application->product);
        $this->assertEquals($this->product->id, $application->product->id);
    }

    public function test_belongs_to_person_for_individual(): void
    {
        $application = ApplicationV2::factory()
            ->for($this->tenant)
            ->for($this->product)
            ->individual($this->person)
            ->create();

        $this->assertInstanceOf(Person::class, $application->person);
        $this->assertEquals($this->person->id, $application->person->id);
        $this->assertTrue($application->is_individual);
    }

    public function test_belongs_to_company_for_company_application(): void
    {
        $company = Company::factory()->for($this->tenant)->create();

        $application = ApplicationV2::factory()
            ->for($this->tenant)
            ->for($this->product)
            ->forCompany($company)
            ->create();

        $this->assertInstanceOf(Company::class, $application->company);
        $this->assertEquals($company->id, $application->company->id);
        $this->assertTrue($application->is_company);
    }

    public function test_belongs_to_assigned_staff(): void
    {
        $staff = StaffAccount::factory()->for($this->tenant)->create();

        $application = ApplicationV2::factory()
            ->for($this->tenant)
            ->for($this->product)
            ->individual($this->person)
            ->assigned($staff)
            ->create();

        $this->assertInstanceOf(StaffAccount::class, $application->assignedTo);
        $this->assertEquals($staff->id, $application->assignedTo->id);
    }

    public function test_has_status_history(): void
    {
        $application = ApplicationV2::factory()
            ->for($this->tenant)
            ->for($this->product)
            ->individual($this->person)
            ->submitted()
            ->create();

        ApplicationStatusHistory::factory()
            ->for($application, 'application')
            ->submitted()
            ->count(2)
            ->create();

        $this->assertCount(2, $application->statusHistory);
    }

    public function test_has_many_documents(): void
    {
        $application = ApplicationV2::factory()
            ->for($this->tenant)
            ->for($this->product)
            ->individual($this->person)
            ->create();

        DocumentV2::factory()
            ->for($this->tenant)
            ->count(3)
            ->create([
                'documentable_type' => ApplicationV2::class,
                'documentable_id' => $application->id,
            ]);

        $this->assertCount(3, $application->documents);
    }

    // =====================================================
    // Accessor Tests
    // =====================================================

    public function test_status_label_accessor(): void
    {
        $application = ApplicationV2::factory()
            ->for($this->tenant)
            ->for($this->product)
            ->individual($this->person)
            ->draft()
            ->create();

        $this->assertEquals('Borrador', $application->status_label);
    }

    public function test_applicant_type_label_accessor(): void
    {
        $application = ApplicationV2::factory()
            ->for($this->tenant)
            ->for($this->product)
            ->individual($this->person)
            ->create();

        $this->assertEquals('Persona Física', $application->applicant_type_label);
    }

    public function test_risk_level_label_accessor(): void
    {
        $application = ApplicationV2::factory()
            ->for($this->tenant)
            ->for($this->product)
            ->individual($this->person)
            ->lowRisk()
            ->create();

        $this->assertEquals('Bajo', $application->risk_level_label);
    }

    public function test_applicant_accessor_returns_person(): void
    {
        $application = ApplicationV2::factory()
            ->for($this->tenant)
            ->for($this->product)
            ->individual($this->person)
            ->create();

        $this->assertInstanceOf(Person::class, $application->applicant);
        $this->assertEquals($this->person->id, $application->applicant->id);
    }

    public function test_applicant_accessor_returns_company(): void
    {
        $company = Company::factory()->for($this->tenant)->create();

        $application = ApplicationV2::factory()
            ->for($this->tenant)
            ->for($this->product)
            ->forCompany($company)
            ->create();

        $this->assertInstanceOf(Company::class, $application->applicant);
        $this->assertEquals($company->id, $application->applicant->id);
    }

    public function test_has_counter_offer_accessor(): void
    {
        $application = ApplicationV2::factory()
            ->for($this->tenant)
            ->for($this->product)
            ->individual($this->person)
            ->withCounterOffer()
            ->create();

        $this->assertTrue($application->has_counter_offer);
    }

    public function test_is_expired_accessor(): void
    {
        $expired = ApplicationV2::factory()
            ->for($this->tenant)
            ->for($this->product)
            ->individual($this->person)
            ->create(['expires_at' => now()->subDay()]);

        $valid = ApplicationV2::factory()
            ->for($this->tenant)
            ->for($this->product)
            ->individual($this->person)
            ->create(['expires_at' => now()->addDays(30)]);

        $this->assertTrue($expired->is_expired);
        $this->assertFalse($valid->is_expired);
    }

    // =====================================================
    // Status Helper Tests
    // =====================================================

    public function test_is_draft(): void
    {
        $application = ApplicationV2::factory()
            ->for($this->tenant)
            ->for($this->product)
            ->individual($this->person)
            ->draft()
            ->create();

        $this->assertTrue($application->isDraft());
        $this->assertFalse($application->isSubmitted());
    }

    public function test_is_submitted(): void
    {
        $application = ApplicationV2::factory()
            ->for($this->tenant)
            ->for($this->product)
            ->individual($this->person)
            ->submitted()
            ->create();

        $this->assertTrue($application->isSubmitted());
        $this->assertFalse($application->isDraft());
    }

    public function test_is_in_review(): void
    {
        $inReview = ApplicationV2::factory()
            ->for($this->tenant)
            ->for($this->product)
            ->individual($this->person)
            ->inReview()
            ->create();

        $this->assertTrue($inReview->isInReview());
    }

    public function test_is_approved(): void
    {
        $application = ApplicationV2::factory()
            ->for($this->tenant)
            ->for($this->product)
            ->individual($this->person)
            ->approved()
            ->create();

        $this->assertTrue($application->isApproved());
        // Approved applications are still active (can be synced), but not final
        $this->assertTrue($application->isActive());
        $this->assertFalse($application->isFinal());
    }

    public function test_is_rejected(): void
    {
        $application = ApplicationV2::factory()
            ->for($this->tenant)
            ->for($this->product)
            ->individual($this->person)
            ->rejected()
            ->create();

        $this->assertTrue($application->isRejected());
        $this->assertFalse($application->isActive());
        $this->assertTrue($application->isFinal());
    }

    public function test_can_be_edited_only_when_draft(): void
    {
        $draft = ApplicationV2::factory()
            ->for($this->tenant)
            ->for($this->product)
            ->individual($this->person)
            ->draft()
            ->create();

        $submitted = ApplicationV2::factory()
            ->for($this->tenant)
            ->for($this->product)
            ->individual($this->person)
            ->submitted()
            ->create();

        $this->assertTrue($draft->canBeEdited());
        $this->assertFalse($submitted->canBeEdited());
    }

    public function test_can_be_cancelled_when_active(): void
    {
        $active = ApplicationV2::factory()
            ->for($this->tenant)
            ->for($this->product)
            ->individual($this->person)
            ->inReview()
            ->create();

        $synced = ApplicationV2::factory()
            ->for($this->tenant)
            ->for($this->product)
            ->individual($this->person)
            ->synced()
            ->create();

        $this->assertTrue($active->canBeCancelled());
        $this->assertFalse($synced->canBeCancelled());
    }

    // =====================================================
    // Action Tests
    // =====================================================

    public function test_submit_action(): void
    {
        $account = ApplicantAccount::factory()->for($this->tenant)->create();

        $application = ApplicationV2::factory()
            ->for($this->tenant)
            ->for($this->product)
            ->individual($this->person)
            ->draft()
            ->create();

        $application->submit($account->id, '192.168.1.1', 'iPhone');

        $application->refresh();

        $this->assertTrue($application->isSubmitted());
        $this->assertNotNull($application->submitted_at);
        $this->assertEquals('192.168.1.1', $application->submission_ip);
        $this->assertEquals('iPhone', $application->submission_device);
        $this->assertCount(1, $application->statusHistory);
    }

    public function test_change_status_action(): void
    {
        $staff = StaffAccount::factory()->for($this->tenant)->create();

        $application = ApplicationV2::factory()
            ->for($this->tenant)
            ->for($this->product)
            ->individual($this->person)
            ->submitted()
            ->create();

        $application->changeStatus(
            ApplicationV2::STATUS_IN_REVIEW,
            $staff->id,
            StaffAccount::class,
            'Iniciando revisión'
        );

        $application->refresh();

        $this->assertTrue($application->isInReview());
        $this->assertCount(1, $application->statusHistory);
        $this->assertEquals('Iniciando revisión', $application->statusHistory->first()->notes);
    }

    public function test_assign_to_action(): void
    {
        $staff = StaffAccount::factory()->for($this->tenant)->create();
        $supervisor = StaffAccount::factory()->for($this->tenant)->create();

        $application = ApplicationV2::factory()
            ->for($this->tenant)
            ->for($this->product)
            ->individual($this->person)
            ->submitted()
            ->create();

        $application->assignTo($staff->id, $supervisor->id);

        $application->refresh();

        $this->assertEquals($staff->id, $application->assigned_to);
        $this->assertEquals($supervisor->id, $application->assigned_by);
        $this->assertNotNull($application->assigned_at);
    }

    public function test_approve_action(): void
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
                'interest_rate' => 0.25,
            ]);

        $application->approve($staff->id, 45000, 12, 0.22, 'Approved with minor adjustment');

        $application->refresh();

        $this->assertTrue($application->isApproved());
        $this->assertEquals(ApplicationV2::DECISION_APPROVED, $application->decision);
        $this->assertEquals(45000, $application->approved_amount);
        $this->assertEquals(0.22, $application->approved_interest_rate);
        $this->assertNotNull($application->decision_at);
    }

    public function test_reject_action(): void
    {
        $staff = StaffAccount::factory()->for($this->tenant)->create();

        $application = ApplicationV2::factory()
            ->for($this->tenant)
            ->for($this->product)
            ->individual($this->person)
            ->inReview()
            ->create();

        $application->reject($staff->id, 'Insufficient income', 'Debt-to-income ratio too high');

        $application->refresh();

        $this->assertTrue($application->isRejected());
        $this->assertEquals(ApplicationV2::DECISION_REJECTED, $application->decision);
        $this->assertEquals('Insufficient income', $application->rejection_reason);
        $this->assertNotNull($application->decision_at);
    }

    public function test_send_counter_offer_action(): void
    {
        $staff = StaffAccount::factory()->for($this->tenant)->create();

        $application = ApplicationV2::factory()
            ->for($this->tenant)
            ->for($this->product)
            ->individual($this->person)
            ->inReview()
            ->create();

        $offer = [
            'amount' => 30000,
            'term_months' => 18,
            'interest_rate' => 0.28,
            'monthly_payment' => 2100,
        ];

        $application->sendCounterOffer($staff->id, $offer, 'Lower amount due to income constraints');

        $application->refresh();

        $this->assertEquals(ApplicationV2::DECISION_COUNTER_OFFER, $application->decision);
        $this->assertTrue($application->has_counter_offer);
        $this->assertEquals(30000, $application->counter_offer['amount']);
        $this->assertNotNull($application->counter_offer['offered_at']);
    }

    public function test_respond_to_counter_offer_accept(): void
    {
        $account = ApplicantAccount::factory()->for($this->tenant)->create();

        $application = ApplicationV2::factory()
            ->for($this->tenant)
            ->for($this->product)
            ->individual($this->person)
            ->withCounterOffer([
                'amount' => 30000,
                'term_months' => 18,
                'interest_rate' => 0.28,
                'monthly_payment' => 2100,
            ])
            ->create();

        $application->respondToCounterOffer(true, $account->id);

        $application->refresh();

        $this->assertTrue($application->counter_offer_accepted);
        $this->assertNotNull($application->counter_offer_responded_at);
        $this->assertTrue($application->isApproved());
        $this->assertEquals(30000, $application->approved_amount);
    }

    public function test_respond_to_counter_offer_reject(): void
    {
        $account = ApplicantAccount::factory()->for($this->tenant)->create();

        $application = ApplicationV2::factory()
            ->for($this->tenant)
            ->for($this->product)
            ->individual($this->person)
            ->inReview()
            ->withCounterOffer()
            ->create();

        $application->respondToCounterOffer(false, $account->id);

        $application->refresh();

        $this->assertFalse($application->counter_offer_accepted);
        $this->assertNotNull($application->counter_offer_responded_at);
        $this->assertTrue($application->isInReview()); // Status unchanged
    }

    public function test_cancel_action(): void
    {
        $account = ApplicantAccount::factory()->for($this->tenant)->create();

        $application = ApplicationV2::factory()
            ->for($this->tenant)
            ->for($this->product)
            ->individual($this->person)
            ->submitted()
            ->create();

        $application->cancel($account->id, ApplicantAccount::class, 'Changed my mind');

        $application->refresh();

        $this->assertTrue($application->isCancelled());
        $this->assertCount(1, $application->statusHistory);
    }

    public function test_mark_synced_action(): void
    {
        $application = ApplicationV2::factory()
            ->for($this->tenant)
            ->for($this->product)
            ->individual($this->person)
            ->approved()
            ->create();

        $syncData = ['contract_id' => 'CTR-12345'];

        $application->markSynced('EXT-001', 'SAP', $syncData);

        $application->refresh();

        $this->assertTrue($application->isSynced());
        $this->assertEquals('EXT-001', $application->external_id);
        $this->assertEquals('SAP', $application->external_system);
        $this->assertEquals($syncData, $application->sync_data);
        $this->assertNotNull($application->synced_at);
    }

    public function test_update_verification(): void
    {
        $application = ApplicationV2::factory()
            ->for($this->tenant)
            ->for($this->product)
            ->individual($this->person)
            ->create();

        $application->updateVerification([
            'identity_verified' => true,
            'address_verified' => true,
        ]);

        $application->refresh();

        $this->assertTrue($application->verification_checklist['identity_verified']);
        $this->assertTrue($application->verification_checklist['address_verified']);

        // Add more checks
        $application->updateVerification([
            'income_verified' => true,
        ]);

        $application->refresh();

        $this->assertTrue($application->verification_checklist['income_verified']);
        $this->assertTrue($application->verification_checklist['identity_verified']); // Previous still there
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
            'factors' => ['low_income', 'short_employment_history'],
        ];

        $application->setRiskAssessment(ApplicationV2::RISK_MEDIUM, $riskData);

        $application->refresh();

        $this->assertEquals(ApplicationV2::RISK_MEDIUM, $application->risk_level);
        $this->assertEquals(720, $application->risk_data['score']);
    }

    // =====================================================
    // Scope Tests
    // =====================================================

    public function test_draft_scope(): void
    {
        ApplicationV2::factory()
            ->for($this->tenant)
            ->for($this->product)
            ->individual($this->person)
            ->draft()
            ->count(3)
            ->create();

        ApplicationV2::factory()
            ->for($this->tenant)
            ->for($this->product)
            ->individual($this->person)
            ->submitted()
            ->count(2)
            ->create();

        $drafts = ApplicationV2::draft()->get();

        $this->assertCount(3, $drafts);
    }

    public function test_in_review_scope(): void
    {
        ApplicationV2::factory()
            ->for($this->tenant)
            ->for($this->product)
            ->individual($this->person)
            ->inReview()
            ->count(2)
            ->create();

        ApplicationV2::factory()
            ->for($this->tenant)
            ->for($this->product)
            ->individual($this->person)
            ->create(['status' => ApplicationV2::STATUS_ANALYST_REVIEW]);

        ApplicationV2::factory()
            ->for($this->tenant)
            ->for($this->product)
            ->individual($this->person)
            ->draft()
            ->create();

        $inReview = ApplicationV2::inReview()->get();

        $this->assertCount(3, $inReview);
    }

    public function test_active_scope(): void
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
            ->inReview()
            ->count(3)
            ->create();

        ApplicationV2::factory()
            ->for($this->tenant)
            ->for($this->product)
            ->individual($this->person)
            ->rejected()
            ->create();

        ApplicationV2::factory()
            ->for($this->tenant)
            ->for($this->product)
            ->individual($this->person)
            ->cancelled()
            ->create();

        $active = ApplicationV2::active()->get();

        $this->assertCount(5, $active);
    }

    public function test_for_person_scope(): void
    {
        $person2 = Person::factory()->for($this->tenant)->create();

        ApplicationV2::factory()
            ->for($this->tenant)
            ->for($this->product)
            ->individual($this->person)
            ->count(3)
            ->create();

        ApplicationV2::factory()
            ->for($this->tenant)
            ->for($this->product)
            ->individual($person2)
            ->count(2)
            ->create();

        $forPerson = ApplicationV2::forPerson($this->person->id)->get();

        $this->assertCount(3, $forPerson);
    }

    public function test_for_company_scope(): void
    {
        $company = Company::factory()->for($this->tenant)->create();
        $company2 = Company::factory()->for($this->tenant)->create();

        ApplicationV2::factory()
            ->for($this->tenant)
            ->for($this->product)
            ->forCompany($company)
            ->count(2)
            ->create();

        ApplicationV2::factory()
            ->for($this->tenant)
            ->for($this->product)
            ->forCompany($company2)
            ->create();

        $forCompany = ApplicationV2::forCompany($company->id)->get();

        $this->assertCount(2, $forCompany);
    }

    public function test_assigned_to_staff_scope(): void
    {
        $staff1 = StaffAccount::factory()->for($this->tenant)->create();
        $staff2 = StaffAccount::factory()->for($this->tenant)->create();

        ApplicationV2::factory()
            ->for($this->tenant)
            ->for($this->product)
            ->individual($this->person)
            ->assigned($staff1)
            ->count(3)
            ->create();

        ApplicationV2::factory()
            ->for($this->tenant)
            ->for($this->product)
            ->individual($this->person)
            ->assigned($staff2)
            ->count(2)
            ->create();

        $assignedToStaff1 = ApplicationV2::assignedToStaff($staff1->id)->get();

        $this->assertCount(3, $assignedToStaff1);
    }

    public function test_unassigned_scope(): void
    {
        $staff = StaffAccount::factory()->for($this->tenant)->create();

        ApplicationV2::factory()
            ->for($this->tenant)
            ->for($this->product)
            ->individual($this->person)
            ->count(3)
            ->create();

        ApplicationV2::factory()
            ->for($this->tenant)
            ->for($this->product)
            ->individual($this->person)
            ->assigned($staff)
            ->count(2)
            ->create();

        $unassigned = ApplicationV2::unassigned()->get();

        $this->assertCount(3, $unassigned);
    }

    public function test_individuals_scope(): void
    {
        $company = Company::factory()->for($this->tenant)->create();

        ApplicationV2::factory()
            ->for($this->tenant)
            ->for($this->product)
            ->individual($this->person)
            ->count(3)
            ->create();

        ApplicationV2::factory()
            ->for($this->tenant)
            ->for($this->product)
            ->forCompany($company)
            ->count(2)
            ->create();

        $individuals = ApplicationV2::individuals()->get();

        $this->assertCount(3, $individuals);
    }

    public function test_companies_scope(): void
    {
        $company = Company::factory()->for($this->tenant)->create();

        ApplicationV2::factory()
            ->for($this->tenant)
            ->for($this->product)
            ->individual($this->person)
            ->count(2)
            ->create();

        ApplicationV2::factory()
            ->for($this->tenant)
            ->for($this->product)
            ->forCompany($company)
            ->count(3)
            ->create();

        $companies = ApplicationV2::companies()->get();

        $this->assertCount(3, $companies);
    }

    public function test_risk_level_scope(): void
    {
        ApplicationV2::factory()
            ->for($this->tenant)
            ->for($this->product)
            ->individual($this->person)
            ->lowRisk()
            ->count(2)
            ->create();

        ApplicationV2::factory()
            ->for($this->tenant)
            ->for($this->product)
            ->individual($this->person)
            ->highRisk()
            ->count(3)
            ->create();

        $lowRisk = ApplicationV2::riskLevel(ApplicationV2::RISK_LOW)->get();
        $highRisk = ApplicationV2::riskLevel(ApplicationV2::RISK_HIGH)->get();

        $this->assertCount(2, $lowRisk);
        $this->assertCount(3, $highRisk);
    }

    public function test_expiring_soon_scope(): void
    {
        // Expiring in 5 days
        ApplicationV2::factory()
            ->for($this->tenant)
            ->for($this->product)
            ->individual($this->person)
            ->expiringSoon()
            ->count(2)
            ->create();

        // Expiring in 30 days (not soon)
        ApplicationV2::factory()
            ->for($this->tenant)
            ->for($this->product)
            ->individual($this->person)
            ->draft()
            ->create(['expires_at' => now()->addDays(30)]);

        $expiringSoon = ApplicationV2::expiringSoon(7)->get();

        $this->assertCount(2, $expiringSoon);
    }

    // =====================================================
    // Static Method Tests
    // =====================================================

    public function test_applicant_types(): void
    {
        $types = ApplicationV2::applicantTypes();

        $this->assertArrayHasKey(ApplicationV2::TYPE_INDIVIDUAL, $types);
        $this->assertArrayHasKey(ApplicationV2::TYPE_COMPANY, $types);
        $this->assertEquals('Persona Física', $types[ApplicationV2::TYPE_INDIVIDUAL]);
    }

    public function test_statuses(): void
    {
        $statuses = ApplicationV2::statuses();

        $this->assertArrayHasKey(ApplicationV2::STATUS_DRAFT, $statuses);
        $this->assertArrayHasKey(ApplicationV2::STATUS_SUBMITTED, $statuses);
        $this->assertArrayHasKey(ApplicationV2::STATUS_APPROVED, $statuses);
        $this->assertArrayHasKey(ApplicationV2::STATUS_REJECTED, $statuses);
        $this->assertEquals('Borrador', $statuses[ApplicationV2::STATUS_DRAFT]);
    }

    public function test_risk_levels(): void
    {
        $levels = ApplicationV2::riskLevels();

        $this->assertArrayHasKey(ApplicationV2::RISK_LOW, $levels);
        $this->assertArrayHasKey(ApplicationV2::RISK_MEDIUM, $levels);
        $this->assertArrayHasKey(ApplicationV2::RISK_HIGH, $levels);
        $this->assertArrayHasKey(ApplicationV2::RISK_VERY_HIGH, $levels);
        $this->assertEquals('Bajo', $levels[ApplicationV2::RISK_LOW]);
    }
}
