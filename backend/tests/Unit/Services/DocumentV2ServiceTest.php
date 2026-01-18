<?php

namespace Tests\Unit\Services;

use App\Models\DocumentV2;
use App\Models\Person;
use App\Models\PersonIdentification;
use App\Models\StaffAccount;
use App\Models\Tenant;
use App\Services\DocumentV2Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentV2ServiceTest extends TestCase
{
    use RefreshDatabase;

    protected DocumentV2Service $service;
    protected Person $person;
    protected PersonIdentification $identification;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->service = app(DocumentV2Service::class);
        $this->tenant = Tenant::factory()->create();
        $this->person = Person::factory()->for($this->tenant)->create();
        $this->identification = PersonIdentification::factory()
            ->for($this->tenant)
            ->for($this->person)
            ->curp()
            ->create();
    }

    // =====================================================
    // Upload Tests
    // =====================================================

    public function test_upload_document(): void
    {
        $file = UploadedFile::fake()->image('ine_front.jpg', 800, 600);

        $document = $this->service->upload(
            $this->tenant,
            $this->identification,
            DocumentV2::TYPE_INE_FRONT,
            $file
        );

        $this->assertInstanceOf(DocumentV2::class, $document);
        $this->assertEquals(DocumentV2::TYPE_INE_FRONT, $document->type);
        $this->assertEquals(DocumentV2::CATEGORY_IDENTITY, $document->category);
        $this->assertEquals(DocumentV2::STATUS_PENDING, $document->status);
        $this->assertTrue($document->is_sensitive);
        $this->assertEquals(1, $document->version_number);
        Storage::disk('local')->assertExists($document->file_path);
    }

    public function test_upload_replaces_rejected_document(): void
    {
        // Create a rejected document first
        $existingDoc = DocumentV2::factory()
            ->for($this->tenant)
            ->forIdentification($this->identification)
            ->ineFront()
            ->rejected('Imagen borrosa')
            ->create();

        $file = UploadedFile::fake()->image('ine_front_new.jpg', 800, 600);

        $newDocument = $this->service->upload(
            $this->tenant,
            $this->identification,
            DocumentV2::TYPE_INE_FRONT,
            $file
        );

        $existingDoc->refresh();

        $this->assertEquals(DocumentV2::STATUS_SUPERSEDED, $existingDoc->status);
        $this->assertEquals(2, $newDocument->version_number);
        $this->assertEquals($existingDoc->id, $newDocument->previous_version_id);
    }

    public function test_upload_throws_exception_for_approved_document(): void
    {
        // Create an approved document
        DocumentV2::factory()
            ->for($this->tenant)
            ->forIdentification($this->identification)
            ->ineFront()
            ->approved()
            ->create();

        $file = UploadedFile::fake()->image('ine_front_new.jpg', 800, 600);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot replace an approved document');

        $this->service->upload(
            $this->tenant,
            $this->identification,
            DocumentV2::TYPE_INE_FRONT,
            $file
        );
    }

    public function test_upload_can_replace_approved_with_option(): void
    {
        // Create an approved document
        DocumentV2::factory()
            ->for($this->tenant)
            ->forIdentification($this->identification)
            ->ineFront()
            ->approved()
            ->create();

        $file = UploadedFile::fake()->image('ine_front_new.jpg', 800, 600);

        $newDocument = $this->service->upload(
            $this->tenant,
            $this->identification,
            DocumentV2::TYPE_INE_FRONT,
            $file,
            ['allow_replace_approved' => true]
        );

        $this->assertInstanceOf(DocumentV2::class, $newDocument);
        $this->assertEquals(2, $newDocument->version_number);
    }

    // =====================================================
    // Approval Tests
    // =====================================================

    public function test_approve_document(): void
    {
        $staff = StaffAccount::factory()->for($this->tenant)->create();
        $document = DocumentV2::factory()
            ->for($this->tenant)
            ->forIdentification($this->identification)
            ->pending()
            ->create();

        $result = $this->service->approve($document, $staff, 'Documento verificado');

        $this->assertTrue($result->isApproved());
        $this->assertEquals($staff->id, $result->reviewed_by);
        $this->assertNotNull($result->reviewed_at);
        $this->assertEquals('Documento verificado', $result->notes);
    }

    public function test_auto_approve_document(): void
    {
        $document = DocumentV2::factory()
            ->for($this->tenant)
            ->forIdentification($this->identification)
            ->pending()
            ->create();

        $result = $this->service->autoApprove($document, null, [
            'validation_source' => 'KYC_SYSTEM',
        ]);

        $this->assertTrue($result->isApproved());
        $this->assertTrue($result->metadata['auto_approved']);
    }

    public function test_reject_document(): void
    {
        $staff = StaffAccount::factory()->for($this->tenant)->create();
        $document = DocumentV2::factory()
            ->for($this->tenant)
            ->forIdentification($this->identification)
            ->pending()
            ->create();

        $result = $this->service->reject($document, $staff, 'Imagen no legible');

        $this->assertTrue($result->isRejected());
        $this->assertEquals('Imagen no legible', $result->rejection_reason);
        $this->assertEquals($staff->id, $result->reviewed_by);
    }

    // =====================================================
    // OCR Tests
    // =====================================================

    public function test_set_ocr_data(): void
    {
        $document = DocumentV2::factory()
            ->for($this->tenant)
            ->forIdentification($this->identification)
            ->ineFront()
            ->create();

        $ocrData = [
            'nombre' => 'JUAN PEREZ GARCIA',
            'curp' => 'PEGJ850101HDFRRS09',
        ];

        $result = $this->service->setOcrData($document, $ocrData, 95.5);

        $this->assertTrue($result->ocr_processed);
        $this->assertEquals($ocrData, $result->ocr_data);
        $this->assertEquals(95.5, $result->ocr_confidence);
    }

    // =====================================================
    // Query Tests
    // =====================================================

    public function test_get_documents_for_entity(): void
    {
        DocumentV2::factory()
            ->for($this->tenant)
            ->forIdentification($this->identification)
            ->count(3)
            ->create();

        $documents = $this->service->getDocumentsFor($this->identification);

        $this->assertCount(3, $documents);
    }

    public function test_get_documents_by_type(): void
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

        $documents = $this->service->getDocumentsFor(
            $this->identification,
            DocumentV2::TYPE_INE_FRONT
        );

        $this->assertCount(2, $documents);
    }

    public function test_get_pending_for_review(): void
    {
        DocumentV2::factory()
            ->for($this->tenant)
            ->forIdentification($this->identification)
            ->pending()
            ->count(5)
            ->create();

        DocumentV2::factory()
            ->for($this->tenant)
            ->forIdentification($this->identification)
            ->approved()
            ->count(3)
            ->create();

        $pending = $this->service->getPendingForReview($this->tenant);

        $this->assertCount(5, $pending);
    }

    public function test_get_expiring_soon(): void
    {
        DocumentV2::factory()
            ->for($this->tenant)
            ->forIdentification($this->identification)
            ->expiringSoon()
            ->count(2)
            ->create();

        DocumentV2::factory()
            ->for($this->tenant)
            ->forIdentification($this->identification)
            ->withExpiration(60) // 60 days - not soon
            ->create();

        $expiring = $this->service->getExpiringSoon($this->tenant, 30);

        $this->assertCount(2, $expiring);
    }

    // =====================================================
    // Validation Tests
    // =====================================================

    public function test_are_all_required_approved(): void
    {
        $requiredTypes = [
            DocumentV2::TYPE_INE_FRONT,
            DocumentV2::TYPE_INE_BACK,
        ];

        // Create only INE front as approved
        DocumentV2::factory()
            ->for($this->tenant)
            ->forIdentification($this->identification)
            ->ineFront()
            ->approved()
            ->create();

        $result = $this->service->areAllRequiredApproved($this->identification, $requiredTypes);

        $this->assertFalse($result);

        // Now add INE back as approved
        DocumentV2::factory()
            ->for($this->tenant)
            ->forIdentification($this->identification)
            ->ineBack()
            ->approved()
            ->create();

        $result = $this->service->areAllRequiredApproved($this->identification, $requiredTypes);

        $this->assertTrue($result);
    }

    public function test_get_missing_required(): void
    {
        $requiredTypes = [
            DocumentV2::TYPE_INE_FRONT,
            DocumentV2::TYPE_INE_BACK,
            DocumentV2::TYPE_PROOF_OF_ADDRESS,
        ];

        DocumentV2::factory()
            ->for($this->tenant)
            ->forIdentification($this->identification)
            ->ineFront()
            ->approved()
            ->create();

        $missing = $this->service->getMissingRequired($this->identification, $requiredTypes);

        $this->assertCount(2, $missing);
        $this->assertContains(DocumentV2::TYPE_INE_BACK, $missing);
        $this->assertContains(DocumentV2::TYPE_PROOF_OF_ADDRESS, $missing);
    }

    public function test_get_rejected_for_reupload(): void
    {
        DocumentV2::factory()
            ->for($this->tenant)
            ->forIdentification($this->identification)
            ->rejected('Borroso')
            ->count(2)
            ->create();

        DocumentV2::factory()
            ->for($this->tenant)
            ->forIdentification($this->identification)
            ->approved()
            ->create();

        $rejected = $this->service->getRejectedForReupload($this->identification);

        $this->assertCount(2, $rejected);
    }

    // =====================================================
    // Delete Tests
    // =====================================================

    public function test_delete_document_soft_deletes(): void
    {
        $document = DocumentV2::factory()
            ->for($this->tenant)
            ->forIdentification($this->identification)
            ->create();

        $result = $this->service->delete($document, 'user-123');

        $this->assertTrue($result);
        $this->assertSoftDeleted($document);
        $this->assertEquals('user-123', $document->fresh()->deleted_by);
    }

    public function test_force_delete_removes_file(): void
    {
        $file = UploadedFile::fake()->image('test.jpg', 800, 600);

        $document = $this->service->upload(
            $this->tenant,
            $this->identification,
            DocumentV2::TYPE_INE_FRONT,
            $file
        );

        $filePath = $document->file_path;
        Storage::disk('local')->assertExists($filePath);

        $result = $this->service->forceDelete($document);

        $this->assertTrue($result);
        Storage::disk('local')->assertMissing($filePath);
    }
}
