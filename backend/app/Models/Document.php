<?php

namespace App\Models;

use App\Traits\HasAuditFields;
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
 * Document model with polymorphic relationships.
 *
 * Documents can be attached to various entities:
 * - persons
 * - person_identifications
 * - addresses
 * - person_employments
 * - applications
 */
class Document extends Model
{
    use HasFactory, HasUuids, SoftDeletes, HasTenant, HasAuditFields;

    protected $table = 'documents';

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
        // Active Document Pattern
        'is_active',
        'valid_from',
        'valid_to',
        'superseded_by_id',
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
        // Active Document Pattern
        'is_active' => 'boolean',
        'valid_from' => 'datetime',
        'valid_to' => 'datetime',
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

    /**
     * Determine if a document type should be associated with Person (vs Application).
     *
     * Person-level documents are stored once with Person and reused across applications:
     * - IDENTITY: INE, Passport, CURP, RFC, Driver License
     * - VERIFICATION: Selfie
     * - INCOME (proof): Payslips, IMSS statements (recent documents from person)
     *
     * Application-level documents are unique per application:
     * - ADDRESS: Proof of address, utility bills (can change between applications)
     * - INCOME (statements): Bank statements, tax returns (application-specific)
     * - COMPANY: Company documents
     * - OTHER: Signature
     */
    public static function isPersonLevelDocument(string $type): bool
    {
        // Explicitly define person-level documents
        $personLevelTypes = [
            // Identity documents
            self::TYPE_INE_FRONT,
            self::TYPE_INE_BACK,
            self::TYPE_PASSPORT,
            self::TYPE_CURP_DOC,
            self::TYPE_RFC_CONSTANCIA,
            self::TYPE_DRIVER_LICENSE_FRONT,
            self::TYPE_DRIVER_LICENSE_BACK,

            // Verification
            self::TYPE_SELFIE,

            // Income proof documents (recent payslips, IMSS)
            self::TYPE_PAYSLIP,
            self::TYPE_IMSS_STATEMENT,
            self::TYPE_EMPLOYMENT_LETTER,
        ];

        return in_array($type, $personLevelTypes);
    }

    /**
     * Get all valid document types.
     */
    public static function validTypes(): array
    {
        $types = [];
        foreach (self::typesByCategory() as $categoryTypes) {
            $types = array_merge($types, $categoryTypes);
        }
        return $types;
    }

    /**
     * Get type labels for display (Spanish, user-friendly).
     * Uses DocumentType enum for consistency.
     */
    public static function typeLabels(): array
    {
        $labels = [];
        foreach (\App\Enums\DocumentType::cases() as $type) {
            $labels[$type->value] = $type->description();
        }
        return $labels;
    }

    /**
     * Get type label for a specific type.
     */
    public function getTypeLabelAttribute(): string
    {
        return self::typeLabels()[$this->type] ?? $this->type;
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
    // Allowed Documentable Types (Security)
    // =====================================================

    /**
     * Whitelist of allowed entity types for polymorphic relationship.
     * This prevents arbitrary class instantiation attacks.
     */
    public const ALLOWED_DOCUMENTABLE_TYPES = [
        'persons' => Person::class,
        'person_identifications' => PersonIdentification::class,
        'addresses' => Address::class,
        'person_employments' => PersonEmployment::class,
        'applications' => Application::class,
    ];

    /**
     * Get the full class name for a documentable type alias.
     */
    public static function resolveDocumentableType(string $type): ?string
    {
        return self::ALLOWED_DOCUMENTABLE_TYPES[$type] ?? null;
    }

    /**
     * Check if a documentable type is allowed.
     */
    public static function isValidDocumentableType(string $type): bool
    {
        // Allow both short aliases and full class names
        return isset(self::ALLOWED_DOCUMENTABLE_TYPES[$type])
            || in_array($type, self::ALLOWED_DOCUMENTABLE_TYPES, true);
    }

    /**
     * Get all allowed documentable type aliases.
     */
    public static function getAllowedDocumentableTypes(): array
    {
        return array_keys(self::ALLOWED_DOCUMENTABLE_TYPES);
    }

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
        return $this->belongsTo(Document::class, 'previous_version_id');
    }

    public function newerVersions(): HasMany
    {
        return $this->hasMany(Document::class, 'previous_version_id');
    }

    /**
     * Document that superseded this one (Active Document Pattern).
     */
    public function supersededBy(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'superseded_by_id');
    }

    /**
     * Documents that this one supersedes (Active Document Pattern).
     */
    public function supersedes(): HasMany
    {
        return $this->hasMany(Document::class, 'superseded_by_id');
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

    /**
     * Alias for file_name for backward compatibility.
     */
    public function getOriginalFilenameAttribute(): string
    {
        return $this->file_name ?? '';
    }

    /**
     * Alias for valid_until for API compatibility.
     */
    public function getExpiresAtAttribute(): ?\Carbon\Carbon
    {
        return $this->valid_until;
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
     *
     * @deprecated Use supersedeWith() instead for full Active Document Pattern support
     */
    public function supersede(string $newDocumentId, string $reason = self::REASON_UPDATED): void
    {
        $newDocument = Document::find($newDocumentId);
        if ($newDocument) {
            $this->supersedeWith($newDocument, $reason);
        } else {
            // Fallback to simple update if new document not found
            $this->update([
                'status' => self::STATUS_SUPERSEDED,
                'replaced_at' => now(),
                'replacement_reason' => $reason,
                'superseded_by_id' => $newDocumentId,
                'is_active' => false,
                'valid_to' => now(),
            ]);
        }
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

    // =====================================================
    // Active Document Pattern Scopes
    // =====================================================

    /**
     * Scope to get only active documents (is_active = true).
     */
    public function scopeIsActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get documents valid at a specific date.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param \Carbon\Carbon|string|null $date Date to check validity (defaults to now)
     */
    public function scopeValidAt($query, $date = null)
    {
        $date = $date ? \Carbon\Carbon::parse($date) : now();

        return $query->where(function ($q) use ($date) {
            $q->where('valid_from', '<=', $date)
                ->where(function ($subQ) use ($date) {
                    $subQ->whereNull('valid_to')
                        ->orWhere('valid_to', '>=', $date);
                });
        });
    }

    /**
     * Scope to get currently valid documents (valid_from <= now AND (valid_to IS NULL OR valid_to >= now)).
     */
    public function scopeCurrentlyValid($query)
    {
        return $query->validAt(now());
    }

    /**
     * Scope to get superseded documents.
     */
    public function scopeSuperseded($query)
    {
        return $query->whereNotNull('superseded_by_id');
    }

    /**
     * Scope to get non-superseded documents.
     */
    public function scopeNotSuperseded($query)
    {
        return $query->whereNull('superseded_by_id');
    }

    // =====================================================
    // Active Document Pattern Methods
    // =====================================================

    /**
     * Check if this document is currently valid based on temporal validity.
     *
     * @param \Carbon\Carbon|string|null $date Date to check (defaults to now)
     * @return bool
     */
    public function isValidAt($date = null): bool
    {
        $date = $date ? \Carbon\Carbon::parse($date) : now();

        return $this->valid_from <= $date
            && (is_null($this->valid_to) || $this->valid_to >= $date);
    }

    /**
     * Check if this document is the currently valid one.
     *
     * @return bool
     */
    public function isCurrentlyValid(): bool
    {
        return $this->isValidAt(now());
    }

    /**
     * Activate this document and deactivate others of the same type for the same documentable.
     *
     * This implements the Active Document Pattern: only ONE document per type per person can be active.
     *
     * @return void
     */
    public function activate(): void
    {
        \DB::transaction(function () {
            // Deactivate all other documents of the same type for the same documentable
            Document::where('documentable_type', $this->documentable_type)
                ->where('documentable_id', $this->documentable_id)
                ->where('type', $this->type)
                ->where('id', '!=', $this->id)
                ->where('is_active', true)
                ->update([
                    'is_active' => false,
                    'valid_to' => now(),
                ]);

            // Activate this document
            $this->update([
                'is_active' => true,
                'valid_from' => $this->valid_from ?? now(),
                'valid_to' => null, // Active document has no end date
            ]);
        });

        \Log::info('Document activated', [
            'document_id' => $this->id,
            'type' => $this->type,
            'documentable_type' => $this->documentable_type,
            'documentable_id' => $this->documentable_id,
        ]);
    }

    /**
     * Deactivate this document (mark it as no longer the current version).
     *
     * @return void
     */
    public function deactivate(): void
    {
        $this->update([
            'is_active' => false,
            'valid_to' => now(),
        ]);

        \Log::info('Document deactivated', [
            'document_id' => $this->id,
            'type' => $this->type,
        ]);
    }

    /**
     * Supersede this document with a new one.
     *
     * This method:
     * 1. Marks this document as superseded
     * 2. Sets superseded_by_id to the new document
     * 3. Updates status to SUPERSEDED
     * 4. Deactivates this document (sets is_active = false, valid_to = now)
     * 5. Activates the new document
     *
     * @param Document $newDocument The document that replaces this one
     * @param string $reason Reason for replacement
     * @return void
     */
    public function supersedeWith(Document $newDocument, string $reason = self::REASON_UPDATED): void
    {
        \DB::transaction(function () use ($newDocument, $reason) {
            // Update this document
            $this->update([
                'superseded_by_id' => $newDocument->id,
                'status' => self::STATUS_SUPERSEDED,
                'replacement_reason' => $reason,
                'replaced_at' => now(),
                'is_active' => false,
                'valid_to' => now(),
            ]);

            // Activate the new document
            $newDocument->activate();
        });

        \Log::info('Document superseded', [
            'old_document_id' => $this->id,
            'new_document_id' => $newDocument->id,
            'reason' => $reason,
            'type' => $this->type,
        ]);
    }

    /**
     * Get the full supersession chain starting from this document.
     *
     * Returns array of documents in chronological order (oldest to newest).
     * Optimized with recursive CTE to avoid N+1 queries.
     *
     * @return \Illuminate\Support\Collection
     */
    public function getSupersessionChain(): \Illuminate\Support\Collection
    {
        // Use recursive CTE for optimal performance (single query)
        $results = \DB::select("
            WITH RECURSIVE supersession_chain AS (
                -- Base case: start with current document
                SELECT id, superseded_by_id, type, status, file_name, created_at, valid_from, valid_to,
                       replacement_reason, is_active, 0 as depth
                FROM documents
                WHERE id = ?

                UNION ALL

                -- Recursive case: follow superseded_by_id chain
                SELECT d.id, d.superseded_by_id, d.type, d.status, d.file_name, d.created_at, d.valid_from, d.valid_to,
                       d.replacement_reason, d.is_active, sc.depth + 1
                FROM documents d
                INNER JOIN supersession_chain sc ON d.id = sc.superseded_by_id
                WHERE sc.depth < 100  -- Prevent infinite loops
            )
            SELECT * FROM supersession_chain
            ORDER BY depth ASC
        ", [$this->id]);

        // Convert to Document models
        return collect($results)->map(function ($row) {
            $doc = new Document();
            $doc->id = $row->id;
            $doc->superseded_by_id = $row->superseded_by_id;
            $doc->type = $row->type;
            $doc->status = $row->status;
            $doc->file_name = $row->file_name;
            $doc->created_at = \Carbon\Carbon::parse($row->created_at);
            $doc->valid_from = $row->valid_from ? \Carbon\Carbon::parse($row->valid_from) : null;
            $doc->valid_to = $row->valid_to ? \Carbon\Carbon::parse($row->valid_to) : null;
            $doc->replacement_reason = $row->replacement_reason;
            $doc->is_active = $row->is_active;
            $doc->exists = true;
            return $doc;
        });
    }

    /**
     * Get the reverse supersession chain (all documents this one supersedes).
     *
     * Returns array of documents in reverse chronological order (newest to oldest).
     * Optimized with recursive CTE to avoid N+1 queries.
     *
     * @return \Illuminate\Support\Collection
     */
    public function getReverseSupersessionChain(): \Illuminate\Support\Collection
    {
        // Use recursive CTE for optimal performance (single query)
        $results = \DB::select("
            WITH RECURSIVE reverse_chain AS (
                -- Base case: start with current document
                SELECT id, superseded_by_id, type, status, file_name, created_at, valid_from, valid_to,
                       replacement_reason, is_active, 0 as depth
                FROM documents
                WHERE id = ?

                UNION ALL

                -- Recursive case: find documents that point to current via superseded_by_id
                SELECT d.id, d.superseded_by_id, d.type, d.status, d.file_name, d.created_at, d.valid_from, d.valid_to,
                       d.replacement_reason, d.is_active, rc.depth + 1
                FROM documents d
                INNER JOIN reverse_chain rc ON d.superseded_by_id = rc.id
                WHERE rc.depth < 100  -- Prevent infinite loops
            )
            SELECT * FROM reverse_chain
            ORDER BY depth DESC  -- Oldest first
        ", [$this->id]);

        // Convert to Document models
        return collect($results)->map(function ($row) {
            $doc = new Document();
            $doc->id = $row->id;
            $doc->superseded_by_id = $row->superseded_by_id;
            $doc->type = $row->type;
            $doc->status = $row->status;
            $doc->file_name = $row->file_name;
            $doc->created_at = \Carbon\Carbon::parse($row->created_at);
            $doc->valid_from = $row->valid_from ? \Carbon\Carbon::parse($row->valid_from) : null;
            $doc->valid_to = $row->valid_to ? \Carbon\Carbon::parse($row->valid_to) : null;
            $doc->replacement_reason = $row->replacement_reason;
            $doc->is_active = $row->is_active;
            $doc->exists = true;
            return $doc;
        });
    }

    /**
     * Get the complete history chain (both forward and backward).
     *
     * @return \Illuminate\Support\Collection
     */
    public function getCompleteHistoryChain(): \Illuminate\Support\Collection
    {
        // Get reverse chain (older documents)
        $reverseChain = $this->getReverseSupersessionChain();

        // Get forward chain (newer documents)
        $forwardChain = $this->getSupersessionChain();

        // Merge and remove duplicate of current document
        return $reverseChain->merge($forwardChain->skip(1))->unique('id');
    }
}
