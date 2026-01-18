<?php

namespace Tests\Unit\Models;

use App\Models\DocumentV2;
use App\Models\Person;
use App\Models\PersonIdentification;
use App\Models\StaffAccount;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentV2Test extends TestCase
{
    use RefreshDatabase;

    protected Person $person;
    protected PersonIdentification $identification;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();
        $this->person = Person::factory()->for($this->tenant)->create();
        $this->identification = PersonIdentification::factory()
            ->for($this->tenant)
            ->for($this->person)
            ->curp()
            ->create();
    }

    // =====================================================
    // Relationship Tests
    // =====================================================

    public function test_belongs_to_tenant(): void
    {
        $document = DocumentV2::factory()
            ->for($this->tenant)
            ->forIdentification($this->identification)
            ->create();

        $this->assertInstanceOf(Tenant::class, $document->tenant);
        $this->assertEquals($this->tenant->id, $document->tenant->id);
    }

    public function test_polymorphic_relationship_to_identification(): void
    {
        $document = DocumentV2::factory()
            ->for($this->tenant)
            ->forIdentification($this->identification)
            ->create();

        $this->assertInstanceOf(PersonIdentification::class, $document->documentable);
        $this->assertEquals($this->identification->id, $document->documentable->id);
    }

    public function test_belongs_to_reviewer(): void
    {
        $staff = StaffAccount::factory()->for($this->tenant)->create();

        $document = DocumentV2::factory()
            ->for($this->tenant)
            ->forIdentification($this->identification)
            ->approved()
            ->create(['reviewed_by' => $staff->id]);

        $this->assertInstanceOf(StaffAccount::class, $document->reviewedBy);
        $this->assertEquals($staff->id, $document->reviewedBy->id);
    }

    public function test_has_previous_version(): void
    {
        $oldDoc = DocumentV2::factory()
            ->for($this->tenant)
            ->forIdentification($this->identification)
            ->superseded()
            ->create();

        $newDoc = DocumentV2::factory()
            ->for($this->tenant)
            ->forIdentification($this->identification)
            ->create([
                'previous_version_id' => $oldDoc->id,
                'version_number' => 2,
            ]);

        $this->assertInstanceOf(DocumentV2::class, $newDoc->previousVersion);
        $this->assertEquals($oldDoc->id, $newDoc->previousVersion->id);
    }

    public function test_has_newer_versions(): void
    {
        $oldDoc = DocumentV2::factory()
            ->for($this->tenant)
            ->forIdentification($this->identification)
            ->superseded()
            ->create();

        DocumentV2::factory()
            ->for($this->tenant)
            ->forIdentification($this->identification)
            ->count(2)
            ->create(['previous_version_id' => $oldDoc->id]);

        $this->assertCount(2, $oldDoc->newerVersions);
    }

    // =====================================================
    // Accessor Tests
    // =====================================================

    public function test_status_label_accessor(): void
    {
        $document = DocumentV2::factory()
            ->for($this->tenant)
            ->forIdentification($this->identification)
            ->pending()
            ->create();

        $this->assertEquals('Pendiente', $document->status_label);
    }

    public function test_category_label_accessor(): void
    {
        $document = DocumentV2::factory()
            ->for($this->tenant)
            ->forIdentification($this->identification)
            ->ineFront()
            ->create();

        $this->assertEquals('Identidad', $document->category_label);
    }

    public function test_file_size_formatted_accessor(): void
    {
        $document = DocumentV2::factory()
            ->for($this->tenant)
            ->forIdentification($this->identification)
            ->create(['file_size' => 2097152]); // 2MB

        $this->assertEquals('2 MB', $document->file_size_formatted);
    }

    public function test_is_expired_accessor(): void
    {
        $expired = DocumentV2::factory()
            ->for($this->tenant)
            ->forIdentification($this->identification)
            ->create(['valid_until' => now()->subDay()]);

        $valid = DocumentV2::factory()
            ->for($this->tenant)
            ->forIdentification($this->identification)
            ->withExpiration(30)
            ->create();

        $this->assertTrue($expired->is_expired);
        $this->assertFalse($valid->is_expired);
    }

    public function test_is_current_version_accessor(): void
    {
        $current = DocumentV2::factory()
            ->for($this->tenant)
            ->forIdentification($this->identification)
            ->create();

        $superseded = DocumentV2::factory()
            ->for($this->tenant)
            ->forIdentification($this->identification)
            ->superseded()
            ->create();

        $this->assertTrue($current->is_current_version);
        $this->assertFalse($superseded->is_current_version);
    }

    // =====================================================
    // Status Helper Tests
    // =====================================================

    public function test_is_pending(): void
    {
        $document = DocumentV2::factory()
            ->for($this->tenant)
            ->forIdentification($this->identification)
            ->pending()
            ->create();

        $this->assertTrue($document->isPending());
        $this->assertFalse($document->isApproved());
    }

    public function test_is_approved(): void
    {
        $document = DocumentV2::factory()
            ->for($this->tenant)
            ->forIdentification($this->identification)
            ->approved()
            ->create();

        $this->assertTrue($document->isApproved());
        $this->assertFalse($document->isPending());
    }

    public function test_is_rejected(): void
    {
        $document = DocumentV2::factory()
            ->for($this->tenant)
            ->forIdentification($this->identification)
            ->rejected('Documento borroso')
            ->create();

        $this->assertTrue($document->isRejected());
        $this->assertEquals('Documento borroso', $document->rejection_reason);
    }

    // =====================================================
    // Action Tests
    // =====================================================

    public function test_approve_action(): void
    {
        $staff = StaffAccount::factory()->for($this->tenant)->create();

        $document = DocumentV2::factory()
            ->for($this->tenant)
            ->forIdentification($this->identification)
            ->pending()
            ->create();

        $document->approve($staff->id);

        $this->assertTrue($document->isApproved());
        $this->assertNotNull($document->reviewed_at);
        $this->assertEquals($staff->id, $document->reviewed_by);
    }

    public function test_reject_action(): void
    {
        $staff = StaffAccount::factory()->for($this->tenant)->create();

        $document = DocumentV2::factory()
            ->for($this->tenant)
            ->forIdentification($this->identification)
            ->pending()
            ->create();

        $document->reject($staff->id, 'Imagen no legible');

        $this->assertTrue($document->isRejected());
        $this->assertEquals('Imagen no legible', $document->rejection_reason);
    }

    public function test_mark_expired_action(): void
    {
        $document = DocumentV2::factory()
            ->for($this->tenant)
            ->forIdentification($this->identification)
            ->approved()
            ->create();

        $document->markExpired();

        $this->assertTrue($document->isExpired());
    }

    public function test_supersede_action(): void
    {
        $oldDoc = DocumentV2::factory()
            ->for($this->tenant)
            ->forIdentification($this->identification)
            ->approved()
            ->create();

        $newDoc = DocumentV2::factory()
            ->for($this->tenant)
            ->forIdentification($this->identification)
            ->create();

        $oldDoc->supersede($newDoc->id, DocumentV2::REASON_BETTER_QUALITY);

        $this->assertTrue($oldDoc->isSuperseded());
        $this->assertNotNull($oldDoc->replaced_at);
        $this->assertEquals(DocumentV2::REASON_BETTER_QUALITY, $oldDoc->replacement_reason);
    }

    public function test_set_ocr_data(): void
    {
        $document = DocumentV2::factory()
            ->for($this->tenant)
            ->forIdentification($this->identification)
            ->ineFront()
            ->create();

        $ocrData = [
            'nombre' => 'MARIA GARCIA LOPEZ',
            'curp' => 'GALM900515MDFRRS09',
        ];

        $document->setOcrData($ocrData, 95.5);

        $this->assertTrue($document->ocr_processed);
        $this->assertNotNull($document->ocr_processed_at);
        $this->assertEquals($ocrData, $document->ocr_data);
        $this->assertEquals(95.5, $document->ocr_confidence);
    }

    // =====================================================
    // Scope Tests
    // =====================================================

    public function test_pending_scope(): void
    {
        DocumentV2::factory()
            ->for($this->tenant)
            ->forIdentification($this->identification)
            ->pending()
            ->count(3)
            ->create();

        DocumentV2::factory()
            ->for($this->tenant)
            ->forIdentification($this->identification)
            ->approved()
            ->count(2)
            ->create();

        $pending = DocumentV2::pending()->get();

        $this->assertCount(3, $pending);
    }

    public function test_approved_scope(): void
    {
        DocumentV2::factory()
            ->for($this->tenant)
            ->forIdentification($this->identification)
            ->approved()
            ->count(2)
            ->create();

        DocumentV2::factory()
            ->for($this->tenant)
            ->forIdentification($this->identification)
            ->pending()
            ->count(3)
            ->create();

        $approved = DocumentV2::approved()->get();

        $this->assertCount(2, $approved);
    }

    public function test_active_scope(): void
    {
        DocumentV2::factory()
            ->for($this->tenant)
            ->forIdentification($this->identification)
            ->pending()
            ->count(2)
            ->create();

        DocumentV2::factory()
            ->for($this->tenant)
            ->forIdentification($this->identification)
            ->approved()
            ->count(3)
            ->create();

        DocumentV2::factory()
            ->for($this->tenant)
            ->forIdentification($this->identification)
            ->superseded()
            ->create();

        $active = DocumentV2::active()->get();

        $this->assertCount(5, $active);
    }

    public function test_current_version_scope(): void
    {
        DocumentV2::factory()
            ->for($this->tenant)
            ->forIdentification($this->identification)
            ->count(3)
            ->create();

        DocumentV2::factory()
            ->for($this->tenant)
            ->forIdentification($this->identification)
            ->superseded()
            ->count(2)
            ->create();

        $current = DocumentV2::currentVersion()->get();

        $this->assertCount(3, $current);
    }

    public function test_of_type_scope(): void
    {
        DocumentV2::factory()
            ->for($this->tenant)
            ->forIdentification($this->identification)
            ->ineFront()
            ->count(2)
            ->create();

        DocumentV2::factory()
            ->for($this->tenant)
            ->forIdentification($this->identification)
            ->ineBack()
            ->create();

        $ineFront = DocumentV2::ofType(DocumentV2::TYPE_INE_FRONT)->get();

        $this->assertCount(2, $ineFront);
    }

    public function test_of_category_scope(): void
    {
        DocumentV2::factory()
            ->for($this->tenant)
            ->forIdentification($this->identification)
            ->ineFront()
            ->create();

        DocumentV2::factory()
            ->for($this->tenant)
            ->forIdentification($this->identification)
            ->ineBack()
            ->create();

        DocumentV2::factory()
            ->for($this->tenant)
            ->forIdentification($this->identification)
            ->proofOfAddress()
            ->create();

        $identity = DocumentV2::ofCategory(DocumentV2::CATEGORY_IDENTITY)->get();

        $this->assertCount(2, $identity);
    }

    public function test_expiring_soon_scope(): void
    {
        // Expiring in 15 days
        DocumentV2::factory()
            ->for($this->tenant)
            ->forIdentification($this->identification)
            ->expiringSoon()
            ->count(2)
            ->create();

        // Expiring in 60 days (not soon)
        DocumentV2::factory()
            ->for($this->tenant)
            ->forIdentification($this->identification)
            ->withExpiration(60)
            ->create();

        $expiringSoon = DocumentV2::expiringSoon(30)->get();

        $this->assertCount(2, $expiringSoon);
    }

    public function test_with_ocr_scope(): void
    {
        DocumentV2::factory()
            ->for($this->tenant)
            ->forIdentification($this->identification)
            ->withOcr()
            ->count(2)
            ->create();

        DocumentV2::factory()
            ->for($this->tenant)
            ->forIdentification($this->identification)
            ->create();

        $withOcr = DocumentV2::withOcr()->get();

        $this->assertCount(2, $withOcr);
    }

    // =====================================================
    // Static Method Tests
    // =====================================================

    public function test_get_category_for_type(): void
    {
        $this->assertEquals(
            DocumentV2::CATEGORY_IDENTITY,
            DocumentV2::getCategoryForType(DocumentV2::TYPE_INE_FRONT)
        );

        $this->assertEquals(
            DocumentV2::CATEGORY_ADDRESS,
            DocumentV2::getCategoryForType(DocumentV2::TYPE_PROOF_OF_ADDRESS)
        );

        $this->assertEquals(
            DocumentV2::CATEGORY_INCOME,
            DocumentV2::getCategoryForType(DocumentV2::TYPE_PAYSLIP)
        );

        $this->assertEquals(
            DocumentV2::CATEGORY_COMPANY,
            DocumentV2::getCategoryForType(DocumentV2::TYPE_CONSTITUTIVE_ACT)
        );

        $this->assertEquals(
            DocumentV2::CATEGORY_OTHER,
            DocumentV2::getCategoryForType('UNKNOWN_TYPE')
        );
    }

    public function test_types_by_category(): void
    {
        $types = DocumentV2::typesByCategory();

        $this->assertArrayHasKey(DocumentV2::CATEGORY_IDENTITY, $types);
        $this->assertArrayHasKey(DocumentV2::CATEGORY_ADDRESS, $types);
        $this->assertArrayHasKey(DocumentV2::CATEGORY_INCOME, $types);
        $this->assertArrayHasKey(DocumentV2::CATEGORY_COMPANY, $types);

        $this->assertContains(DocumentV2::TYPE_INE_FRONT, $types[DocumentV2::CATEGORY_IDENTITY]);
        $this->assertContains(DocumentV2::TYPE_PAYSLIP, $types[DocumentV2::CATEGORY_INCOME]);
    }

    public function test_categories_method(): void
    {
        $categories = DocumentV2::categories();

        $this->assertArrayHasKey(DocumentV2::CATEGORY_IDENTITY, $categories);
        $this->assertEquals('Identidad', $categories[DocumentV2::CATEGORY_IDENTITY]);
    }

    public function test_statuses_method(): void
    {
        $statuses = DocumentV2::statuses();

        $this->assertArrayHasKey(DocumentV2::STATUS_PENDING, $statuses);
        $this->assertArrayHasKey(DocumentV2::STATUS_APPROVED, $statuses);
        $this->assertArrayHasKey(DocumentV2::STATUS_REJECTED, $statuses);
        $this->assertEquals('Pendiente', $statuses[DocumentV2::STATUS_PENDING]);
    }
}
