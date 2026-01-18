<?php

namespace App\Models;

use App\Traits\HasTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

/**
 * Document v2 model with polymorphic relationships.
 *
 * Documents can be attached to various entities:
 * - person_identifications
 * - person_addresses
 * - person_employments
 * - companies
 * - company_addresses
 * - applications_v2
 */
class DocumentV2 extends Model
{
    use HasFactory, HasUuids, SoftDeletes, HasTenant;

    protected $table = 'documents_v2';

    protected $fillable = [
        'tenant_id',
        'documentable_type',
        'documentable_id',
        'type',
        'category',
        'file_name',
        'file_path',
        'storage_disk',
        'mime_type',
        'file_size',
        'checksum',
        'status',
        'rejection_reason',
        'reviewed_at',
        'reviewed_by',
        'ocr_processed',
        'ocr_processed_at',
        'ocr_data',
        'ocr_confidence',
        'is_sensitive',
        'is_encrypted',
        'previous_version_id',
        'version_number',
        'replaced_at',
        'replacement_reason',
        'valid_until',
        'expiration_notified',
        'metadata',
        'notes',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'ocr_processed' => 'boolean',
        'ocr_processed_at' => 'datetime',
        'ocr_data' => 'array',
        'ocr_confidence' => 'decimal:2',
        'is_sensitive' => 'boolean',
        'is_encrypted' => 'boolean',
        'reviewed_at' => 'datetime',
        'replaced_at' => 'datetime',
        'valid_until' => 'date',
        'expiration_notified' => 'boolean',
        'metadata' => 'array',
    ];

    // =====================================================
    // Document Types
    // =====================================================

    // Identity
    public const TYPE_INE_FRONT = 'INE_FRONT';
    public const TYPE_INE_BACK = 'INE_BACK';
    public const TYPE_PASSPORT = 'PASSPORT';
    public const TYPE_CURP_DOC = 'CURP_DOC';
    public const TYPE_RFC_CONSTANCIA = 'RFC_CONSTANCIA';
    public const TYPE_DRIVER_LICENSE_FRONT = 'DRIVER_LICENSE_FRONT';
    public const TYPE_DRIVER_LICENSE_BACK = 'DRIVER_LICENSE_BACK';

    // Address
    public const TYPE_PROOF_OF_ADDRESS = 'PROOF_OF_ADDRESS';
    public const TYPE_UTILITY_BILL = 'UTILITY_BILL';
    public const TYPE_BANK_STATEMENT_ADDRESS = 'BANK_STATEMENT_ADDRESS';
    public const TYPE_LEASE_AGREEMENT = 'LEASE_AGREEMENT';
    public const TYPE_PROPERTY_DEED = 'PROPERTY_DEED';

    // Income
    public const TYPE_PAYSLIP = 'PAYSLIP';
    public const TYPE_BANK_STATEMENT = 'BANK_STATEMENT';
    public const TYPE_TAX_RETURN = 'TAX_RETURN';
    public const TYPE_IMSS_STATEMENT = 'IMSS_STATEMENT';
    public const TYPE_EMPLOYMENT_LETTER = 'EMPLOYMENT_LETTER';
    public const TYPE_INCOME_AFFIDAVIT = 'INCOME_AFFIDAVIT';

    // Company
    public const TYPE_CONSTITUTIVE_ACT = 'CONSTITUTIVE_ACT';
    public const TYPE_POWER_OF_ATTORNEY = 'POWER_OF_ATTORNEY';
    public const TYPE_TAX_ID_COMPANY = 'TAX_ID_COMPANY';
    public const TYPE_FISCAL_SITUATION = 'FISCAL_SITUATION';
    public const TYPE_LEGAL_REP_ID = 'LEGAL_REP_ID';
    public const TYPE_SHAREHOLDER_STRUCTURE = 'SHAREHOLDER_STRUCTURE';

    // Other
    public const TYPE_SELFIE = 'SELFIE';
    public const TYPE_SIGNATURE = 'SIGNATURE';
    public const TYPE_OTHER = 'OTHER';

    // =====================================================
    // Categories
    // =====================================================

    public const CATEGORY_IDENTITY = 'IDENTITY';
    public const CATEGORY_ADDRESS = 'ADDRESS';
    public const CATEGORY_INCOME = 'INCOME';
    public const CATEGORY_COMPANY = 'COMPANY';
    public const CATEGORY_VERIFICATION = 'VERIFICATION';
    public const CATEGORY_OTHER = 'OTHER';

    public static function categories(): array
    {
        return [
            self::CATEGORY_IDENTITY => 'Identidad',
            self::CATEGORY_ADDRESS => 'Domicilio',
            self::CATEGORY_INCOME => 'Ingresos',
            self::CATEGORY_COMPANY => 'Empresa',
            self::CATEGORY_VERIFICATION => 'Verificación',
            self::CATEGORY_OTHER => 'Otro',
        ];
    }

    public static function typesByCategory(): array
    {
        return [
            self::CATEGORY_IDENTITY => [
                self::TYPE_INE_FRONT,
                self::TYPE_INE_BACK,
                self::TYPE_PASSPORT,
                self::TYPE_CURP_DOC,
                self::TYPE_RFC_CONSTANCIA,
                self::TYPE_DRIVER_LICENSE_FRONT,
                self::TYPE_DRIVER_LICENSE_BACK,
            ],
            self::CATEGORY_ADDRESS => [
                self::TYPE_PROOF_OF_ADDRESS,
                self::TYPE_UTILITY_BILL,
                self::TYPE_BANK_STATEMENT_ADDRESS,
                self::TYPE_LEASE_AGREEMENT,
                self::TYPE_PROPERTY_DEED,
            ],
            self::CATEGORY_INCOME => [
                self::TYPE_PAYSLIP,
                self::TYPE_BANK_STATEMENT,
                self::TYPE_TAX_RETURN,
                self::TYPE_IMSS_STATEMENT,
                self::TYPE_EMPLOYMENT_LETTER,
                self::TYPE_INCOME_AFFIDAVIT,
            ],
            self::CATEGORY_COMPANY => [
                self::TYPE_CONSTITUTIVE_ACT,
                self::TYPE_POWER_OF_ATTORNEY,
                self::TYPE_TAX_ID_COMPANY,
                self::TYPE_FISCAL_SITUATION,
                self::TYPE_LEGAL_REP_ID,
                self::TYPE_SHAREHOLDER_STRUCTURE,
            ],
            self::CATEGORY_VERIFICATION => [
                self::TYPE_SELFIE,
            ],
            self::CATEGORY_OTHER => [
                self::TYPE_SIGNATURE,
                self::TYPE_OTHER,
            ],
        ];
    }

    public static function getCategoryForType(string $type): string
    {
        foreach (self::typesByCategory() as $category => $types) {
            if (in_array($type, $types)) {
                return $category;
            }
        }
        return self::CATEGORY_OTHER;
    }

    // =====================================================
    // Statuses
    // =====================================================

    public const STATUS_PENDING = 'PENDING';
    public const STATUS_APPROVED = 'APPROVED';
    public const STATUS_REJECTED = 'REJECTED';
    public const STATUS_EXPIRED = 'EXPIRED';
    public const STATUS_SUPERSEDED = 'SUPERSEDED';

    public static function statuses(): array
    {
        return [
            self::STATUS_PENDING => 'Pendiente',
            self::STATUS_APPROVED => 'Aprobado',
            self::STATUS_REJECTED => 'Rechazado',
            self::STATUS_EXPIRED => 'Expirado',
            self::STATUS_SUPERSEDED => 'Reemplazado',
        ];
    }

    // =====================================================
    // Replacement Reasons
    // =====================================================

    public const REASON_REJECTED = 'REJECTED';
    public const REASON_EXPIRED = 'EXPIRED';
    public const REASON_UPDATED = 'UPDATED';
    public const REASON_BETTER_QUALITY = 'BETTER_QUALITY';

    // =====================================================
    // Relationships
    // =====================================================

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Polymorphic relationship - can belong to various entities.
     */
    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(StaffAccount::class, 'reviewed_by');
    }

    public function previousVersion(): BelongsTo
    {
        return $this->belongsTo(DocumentV2::class, 'previous_version_id');
    }

    public function newerVersions(): HasMany
    {
        return $this->hasMany(DocumentV2::class, 'previous_version_id');
    }

    // =====================================================
    // Accessors
    // =====================================================

    public function getStatusLabelAttribute(): string
    {
        return self::statuses()[$this->status] ?? $this->status;
    }

    public function getCategoryLabelAttribute(): string
    {
        return self::categories()[$this->category] ?? $this->category;
    }

    public function getFileSizeFormattedAttribute(): string
    {
        $bytes = $this->file_size;
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return round($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' bytes';
    }

    public function getIsExpiredAttribute(): bool
    {
        return $this->valid_until && $this->valid_until->isPast();
    }

    public function getIsCurrentVersionAttribute(): bool
    {
        return is_null($this->replaced_at);
    }

    // =====================================================
    // Status Helpers
    // =====================================================

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    public function isExpired(): bool
    {
        return $this->status === self::STATUS_EXPIRED;
    }

    public function isSuperseded(): bool
    {
        return $this->status === self::STATUS_SUPERSEDED;
    }

    // =====================================================
    // Actions
    // =====================================================

    /**
     * Approve the document.
     */
    public function approve(string $staffId): void
    {
        $this->update([
            'status' => self::STATUS_APPROVED,
            'reviewed_at' => now(),
            'reviewed_by' => $staffId,
            'rejection_reason' => null,
        ]);
    }

    /**
     * Reject the document.
     */
    public function reject(string $staffId, string $reason): void
    {
        $this->update([
            'status' => self::STATUS_REJECTED,
            'reviewed_at' => now(),
            'reviewed_by' => $staffId,
            'rejection_reason' => $reason,
        ]);
    }

    /**
     * Mark as expired.
     */
    public function markExpired(): void
    {
        $this->update([
            'status' => self::STATUS_EXPIRED,
        ]);
    }

    /**
     * Mark as superseded by a new version.
     */
    public function supersede(string $newDocumentId, string $reason = self::REASON_UPDATED): void
    {
        $this->update([
            'status' => self::STATUS_SUPERSEDED,
            'replaced_at' => now(),
            'replacement_reason' => $reason,
        ]);
    }

    /**
     * Update OCR data.
     */
    public function setOcrData(array $data, float $confidence): void
    {
        $this->update([
            'ocr_processed' => true,
            'ocr_processed_at' => now(),
            'ocr_data' => $data,
            'ocr_confidence' => $confidence,
        ]);
    }

    // =====================================================
    // URL Generation
    // =====================================================

    /**
     * Get a signed URL for accessing the document.
     */
    public function getSignedUrl(int $expirationMinutes = 15): ?string
    {
        $disk = Storage::disk($this->storage_disk);

        if (!$disk->exists($this->file_path)) {
            return null;
        }

        if ($this->storage_disk === 's3' || $this->storage_disk === 'gcs') {
            return $disk->temporaryUrl(
                $this->file_path,
                now()->addMinutes($expirationMinutes)
            );
        }

        // For local storage, return route-based URL
        return route('api.documents.v2.download', ['document' => $this->id]);
    }

    /**
     * Get the full file path.
     */
    public function getFullPath(): string
    {
        return Storage::disk($this->storage_disk)->path($this->file_path);
    }

    // =====================================================
    // Scopes
    // =====================================================

    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public function scopeRejected($query)
    {
        return $query->where('status', self::STATUS_REJECTED);
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', [self::STATUS_PENDING, self::STATUS_APPROVED])
            ->whereNull('replaced_at');
    }

    public function scopeCurrentVersion($query)
    {
        return $query->whereNull('replaced_at');
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeOfCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public function scopeExpiringSoon($query, int $days = 30)
    {
        return $query->whereNotNull('valid_until')
            ->whereBetween('valid_until', [now(), now()->addDays($days)])
            ->where('expiration_notified', false);
    }

    public function scopeForEntity($query, string $type, string $id)
    {
        return $query->where('documentable_type', $type)
            ->where('documentable_id', $id);
    }

    public function scopeSensitive($query)
    {
        return $query->where('is_sensitive', true);
    }

    public function scopeWithOcr($query)
    {
        return $query->where('ocr_processed', true);
    }
}
