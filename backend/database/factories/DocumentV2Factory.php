<?php

namespace Database\Factories;

use App\Models\DocumentV2;
use App\Models\Person;
use App\Models\PersonIdentification;
use App\Models\StaffAccount;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DocumentV2>
 */
class DocumentV2Factory extends Factory
{
    protected $model = DocumentV2::class;

    public function definition(): array
    {
        $type = fake()->randomElement([
            DocumentV2::TYPE_INE_FRONT,
            DocumentV2::TYPE_INE_BACK,
            DocumentV2::TYPE_PROOF_OF_ADDRESS,
            DocumentV2::TYPE_PAYSLIP,
        ]);

        $category = DocumentV2::getCategoryForType($type);

        return [
            'tenant_id' => Tenant::factory(),
            'documentable_type' => PersonIdentification::class,
            'documentable_id' => PersonIdentification::factory(),
            'type' => $type,
            'category' => $category,
            'file_name' => fake()->word() . '.' . fake()->randomElement(['jpg', 'png', 'pdf']),
            'file_path' => 'tenants/' . fake()->uuid() . '/documents/' . fake()->uuid() . '.jpg',
            'storage_disk' => 'local',
            'mime_type' => fake()->randomElement(['image/jpeg', 'image/png', 'application/pdf']),
            'file_size' => fake()->numberBetween(100000, 5000000),
            'checksum' => md5(fake()->uuid()),
            'status' => DocumentV2::STATUS_PENDING,
            'is_sensitive' => in_array($type, [
                DocumentV2::TYPE_INE_FRONT,
                DocumentV2::TYPE_INE_BACK,
                DocumentV2::TYPE_PASSPORT,
                DocumentV2::TYPE_CURP_DOC,
                DocumentV2::TYPE_RFC_CONSTANCIA,
            ]),
            'is_encrypted' => false,
            'version_number' => 1,
        ];
    }

    /**
     * For person identification.
     */
    public function forIdentification(PersonIdentification $identification = null): static
    {
        return $this->state(function (array $attributes) use ($identification) {
            $ident = $identification ?? PersonIdentification::factory()->create();
            return [
                'tenant_id' => $ident->tenant_id,
                'documentable_type' => PersonIdentification::class,
                'documentable_id' => $ident->id,
            ];
        });
    }

    /**
     * INE front document.
     */
    public function ineFront(): static
    {
        return $this->state(fn(array $attributes) => [
            'type' => DocumentV2::TYPE_INE_FRONT,
            'category' => DocumentV2::CATEGORY_IDENTITY,
            'mime_type' => 'image/jpeg',
            'is_sensitive' => true,
        ]);
    }

    /**
     * INE back document.
     */
    public function ineBack(): static
    {
        return $this->state(fn(array $attributes) => [
            'type' => DocumentV2::TYPE_INE_BACK,
            'category' => DocumentV2::CATEGORY_IDENTITY,
            'mime_type' => 'image/jpeg',
            'is_sensitive' => true,
        ]);
    }

    /**
     * Proof of address document.
     */
    public function proofOfAddress(): static
    {
        return $this->state(fn(array $attributes) => [
            'type' => DocumentV2::TYPE_PROOF_OF_ADDRESS,
            'category' => DocumentV2::CATEGORY_ADDRESS,
            'mime_type' => 'application/pdf',
            'is_sensitive' => false,
        ]);
    }

    /**
     * Payslip document.
     */
    public function payslip(): static
    {
        return $this->state(fn(array $attributes) => [
            'type' => DocumentV2::TYPE_PAYSLIP,
            'category' => DocumentV2::CATEGORY_INCOME,
            'mime_type' => 'application/pdf',
            'is_sensitive' => true,
        ]);
    }

    /**
     * Bank statement document.
     */
    public function bankStatement(): static
    {
        return $this->state(fn(array $attributes) => [
            'type' => DocumentV2::TYPE_BANK_STATEMENT,
            'category' => DocumentV2::CATEGORY_INCOME,
            'mime_type' => 'application/pdf',
            'is_sensitive' => true,
        ]);
    }

    /**
     * Selfie for verification.
     */
    public function selfie(): static
    {
        return $this->state(fn(array $attributes) => [
            'type' => DocumentV2::TYPE_SELFIE,
            'category' => DocumentV2::CATEGORY_VERIFICATION,
            'mime_type' => 'image/jpeg',
            'is_sensitive' => true,
        ]);
    }

    /**
     * Company constitutive act.
     */
    public function constitutiveAct(): static
    {
        return $this->state(fn(array $attributes) => [
            'type' => DocumentV2::TYPE_CONSTITUTIVE_ACT,
            'category' => DocumentV2::CATEGORY_COMPANY,
            'mime_type' => 'application/pdf',
            'is_sensitive' => false,
        ]);
    }

    /**
     * Power of attorney.
     */
    public function powerOfAttorney(): static
    {
        return $this->state(fn(array $attributes) => [
            'type' => DocumentV2::TYPE_POWER_OF_ATTORNEY,
            'category' => DocumentV2::CATEGORY_COMPANY,
            'mime_type' => 'application/pdf',
            'is_sensitive' => false,
        ]);
    }

    /**
     * Pending status.
     */
    public function pending(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => DocumentV2::STATUS_PENDING,
            'reviewed_at' => null,
            'reviewed_by' => null,
        ]);
    }

    /**
     * Approved status.
     */
    public function approved(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => DocumentV2::STATUS_APPROVED,
            'reviewed_at' => now(),
        ]);
    }

    /**
     * Rejected status.
     */
    public function rejected(string $reason = null): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => DocumentV2::STATUS_REJECTED,
            'reviewed_at' => now(),
            'rejection_reason' => $reason ?? 'Documento ilegible',
        ]);
    }

    /**
     * Expired status.
     */
    public function expired(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => DocumentV2::STATUS_EXPIRED,
            'valid_until' => now()->subDays(30),
        ]);
    }

    /**
     * Superseded by newer version.
     */
    public function superseded(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => DocumentV2::STATUS_SUPERSEDED,
            'replaced_at' => now(),
            'replacement_reason' => DocumentV2::REASON_UPDATED,
        ]);
    }

    /**
     * With OCR data.
     */
    public function withOcr(array $data = null): static
    {
        return $this->state(fn(array $attributes) => [
            'ocr_processed' => true,
            'ocr_processed_at' => now(),
            'ocr_data' => $data ?? [
                'nombre' => 'JUAN PEREZ GARCIA',
                'curp' => 'PEGJ850101HDFRRS09',
                'clave_elector' => fake()->numerify('############'),
            ],
            'ocr_confidence' => fake()->randomFloat(2, 85, 99),
        ]);
    }

    /**
     * With expiration date.
     */
    public function withExpiration(int $daysFromNow = 365): static
    {
        return $this->state(fn(array $attributes) => [
            'valid_until' => now()->addDays($daysFromNow),
            'expiration_notified' => false,
        ]);
    }

    /**
     * Expiring soon.
     */
    public function expiringSoon(): static
    {
        return $this->state(fn(array $attributes) => [
            'valid_until' => now()->addDays(15),
            'expiration_notified' => false,
        ]);
    }

    /**
     * Encrypted document.
     */
    public function encrypted(): static
    {
        return $this->state(fn(array $attributes) => [
            'is_encrypted' => true,
        ]);
    }

    /**
     * S3 storage.
     */
    public function onS3(): static
    {
        return $this->state(fn(array $attributes) => [
            'storage_disk' => 's3',
        ]);
    }

    /**
     * Version 2+ document.
     */
    public function version(int $number): static
    {
        return $this->state(fn(array $attributes) => [
            'version_number' => $number,
        ]);
    }
}
