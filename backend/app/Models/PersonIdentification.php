<?php

namespace App\Models;

use App\Traits\HasTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Person identification document with version history.
 *
 * Stores official identification documents (CURP, RFC, INE, passport, etc.)
 * with full history tracking. When an INE expires and is renewed,
 * a new record is created and linked to the previous version.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $person_id
 * @property string $type
 * @property string|null $identifier_value
 * @property array|null $document_data
 * @property \Carbon\Carbon|null $issued_at
 * @property \Carbon\Carbon|null $expires_at
 * @property bool $is_current
 * @property string $status
 * @property \Carbon\Carbon|null $verified_at
 * @property string|null $verified_by
 * @property string|null $verification_method
 * @property array|null $verification_data
 * @property float|null $verification_confidence
 * @property string|null $previous_version_id
 * @property \Carbon\Carbon|null $replaced_at
 * @property string|null $replacement_reason
 * @property string|null $notes
 * @property array|null $metadata
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 * @property string|null $created_by
 * @property string|null $updated_by
 *
 * @property-read bool $is_verified
 * @property-read bool $is_expired
 * @property-read string $type_label
 * @property-read string $status_label
 *
 * @property-read Tenant $tenant
 * @property-read Person $person
 * @property-read StaffAccount|null $verifier
 * @property-read PersonIdentification|null $previousVersion
 * @property-read PersonIdentification|null $nextVersion
 */
class PersonIdentification extends Model
{
    use HasFactory, HasUuids, SoftDeletes, HasTenant;

    protected $table = 'person_identifications';

    public const TYPE_CURP = 'CURP';
    public const TYPE_RFC = 'RFC';
    public const TYPE_INE = 'INE';
    public const TYPE_PASSPORT = 'PASSPORT';
    public const TYPE_FM2 = 'FM2';
    public const TYPE_FM3 = 'FM3';
    public const TYPE_VISA = 'VISA';
    public const TYPE_DRIVER_LICENSE = 'DRIVER_LICENSE';
    public const TYPE_PROFESSIONAL_ID = 'PROFESSIONAL_ID';
    public const TYPE_MILITARY_ID = 'MILITARY_ID';

    public const STATUS_PENDING = 'PENDING';
    public const STATUS_VERIFIED = 'VERIFIED';
    public const STATUS_REJECTED = 'REJECTED';
    public const STATUS_EXPIRED = 'EXPIRED';
    public const STATUS_REVOKED = 'REVOKED';
    public const STATUS_SUPERSEDED = 'SUPERSEDED';

    protected $fillable = [
        'tenant_id',
        'person_id',
        'type',
        'identifier_value',
        'document_data',
        'issued_at',
        'expires_at',
        'is_current',
        'status',
        'verified_at',
        'verified_by',
        'verification_method',
        'verification_data',
        'verification_confidence',
        'previous_version_id',
        'replaced_at',
        'replacement_reason',
        'notes',
        'metadata',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'date',
            'expires_at' => 'date',
            'verified_at' => 'datetime',
            'replaced_at' => 'datetime',
            'is_current' => 'boolean',
            'document_data' => 'array',
            'verification_data' => 'array',
            'metadata' => 'array',
            'verification_confidence' => 'decimal:2',
        ];
    }

    // =====================================================
    // Relationships
    // =====================================================

    /**
     * Get the person this identification belongs to.
     */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /**
     * Get the staff who verified this identification.
     */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(StaffAccount::class, 'verified_by');
    }

    /**
     * Get the previous version of this identification.
     */
    public function previousVersion(): BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_version_id');
    }

    /**
     * Get the next version of this identification.
     */
    public function nextVersion(): BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_version_id', 'id');
    }

    // =====================================================
    // Accessors
    // =====================================================

    /**
     * Check if identification is verified.
     */
    public function getIsVerifiedAttribute(): bool
    {
        return $this->status === self::STATUS_VERIFIED && !is_null($this->verified_at);
    }

    /**
     * Check if identification is expired.
     */
    public function getIsExpiredAttribute(): bool
    {
        if (!$this->expires_at) {
            return false;
        }

        return $this->expires_at->isPast();
    }

    /**
     * Get type label.
     */
    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            self::TYPE_CURP => 'CURP',
            self::TYPE_RFC => 'RFC',
            self::TYPE_INE => 'INE',
            self::TYPE_PASSPORT => 'Pasaporte',
            self::TYPE_FM2 => 'FM2',
            self::TYPE_FM3 => 'FM3',
            self::TYPE_VISA => 'Visa',
            self::TYPE_DRIVER_LICENSE => 'Licencia de Conducir',
            self::TYPE_PROFESSIONAL_ID => 'Cédula Profesional',
            self::TYPE_MILITARY_ID => 'Cartilla Militar',
            default => $this->type,
        };
    }

    /**
     * Get status label.
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'Pendiente',
            self::STATUS_VERIFIED => 'Verificado',
            self::STATUS_EXPIRED => 'Expirado',
            self::STATUS_REVOKED => 'Revocado',
            self::STATUS_SUPERSEDED => 'Reemplazado',
            default => $this->status,
        };
    }

    // =====================================================
    // Document Data Helpers
    // =====================================================

    /**
     * Get document data value by key.
     */
    public function getDocumentDataValue(string $key, mixed $default = null): mixed
    {
        return data_get($this->document_data, $key, $default);
    }

    /**
     * Set document data value by key.
     */
    public function setDocumentDataValue(string $key, mixed $value): void
    {
        $data = $this->document_data ?? [];
        data_set($data, $key, $value);
        $this->update(['document_data' => $data]);
    }

    // =====================================================
    // Verification Methods
    // =====================================================

    /**
     * Mark as verified.
     */
    public function markAsVerified(
        string $method,
        ?string $verifiedBy = null,
        ?array $verificationData = null,
        ?float $confidence = null
    ): void {
        $this->update([
            'status' => self::STATUS_VERIFIED,
            'verified_at' => now(),
            'verified_by' => $verifiedBy,
            'verification_method' => $method,
            'verification_data' => $verificationData,
            'verification_confidence' => $confidence,
        ]);
    }

    /**
     * Mark as expired.
     */
    public function markAsExpired(): void
    {
        $this->update([
            'status' => self::STATUS_EXPIRED,
            'is_current' => false,
        ]);
    }

    /**
     * Replace with a new version.
     */
    public function replaceWith(array $newData, string $reason = 'RENEWED'): self
    {
        // Mark current as superseded
        $this->update([
            'status' => self::STATUS_SUPERSEDED,
            'is_current' => false,
            'replaced_at' => now(),
            'replacement_reason' => $reason,
        ]);

        // Create new version
        $newVersion = self::create(array_merge($newData, [
            'tenant_id' => $this->tenant_id,
            'person_id' => $this->person_id,
            'type' => $this->type,
            'is_current' => true,
            'status' => self::STATUS_PENDING,
            'previous_version_id' => $this->id,
        ]));

        return $newVersion;
    }

    // =====================================================
    // Scopes
    // =====================================================

    /**
     * Scope to current identifications.
     *
     * @param \Illuminate\Database\Eloquent\Builder<PersonIdentification> $query
     * @return \Illuminate\Database\Eloquent\Builder<PersonIdentification>
     */
    public function scopeCurrent($query)
    {
        return $query->where('is_current', true);
    }

    /**
     * Scope to verified identifications.
     *
     * @param \Illuminate\Database\Eloquent\Builder<PersonIdentification> $query
     * @return \Illuminate\Database\Eloquent\Builder<PersonIdentification>
     */
    public function scopeVerified($query)
    {
        return $query->where('status', self::STATUS_VERIFIED);
    }

    /**
     * Scope to specific type.
     *
     * @param \Illuminate\Database\Eloquent\Builder<PersonIdentification> $query
     * @param string $type
     * @return \Illuminate\Database\Eloquent\Builder<PersonIdentification>
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope to non-expired identifications.
     *
     * @param \Illuminate\Database\Eloquent\Builder<PersonIdentification> $query
     * @return \Illuminate\Database\Eloquent\Builder<PersonIdentification>
     */
    public function scopeNotExpired($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
              ->orWhere('expires_at', '>', now());
        });
    }

    // =====================================================
    // Type Checks
    // =====================================================

    /**
     * Check if this is a CURP.
     */
    public function isCurp(): bool
    {
        return $this->type === self::TYPE_CURP;
    }

    /**
     * Check if this is an RFC.
     */
    public function isRfc(): bool
    {
        return $this->type === self::TYPE_RFC;
    }

    /**
     * Check if this is an INE.
     */
    public function isIne(): bool
    {
        return $this->type === self::TYPE_INE;
    }

    // =====================================================
    // Static Finders
    // =====================================================

    /**
     * Find current identification by type for a person.
     */
    public static function findCurrentByType(string $personId, string $type): ?self
    {
        return self::where('person_id', $personId)
            ->where('type', $type)
            ->where('is_current', true)
            ->first();
    }

    /**
     * Find by identifier value (e.g., find by CURP string).
     */
    public static function findByIdentifier(string $type, string $value, string $tenantId): ?self
    {
        return self::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->where('type', $type)
            ->where('identifier_value', $value)
            ->where('is_current', true)
            ->first();
    }

    /**
     * Get available document types.
     */
    public static function getTypes(): array
    {
        return [
            self::TYPE_CURP => 'CURP',
            self::TYPE_RFC => 'RFC',
            self::TYPE_INE => 'INE',
            self::TYPE_PASSPORT => 'Pasaporte',
            self::TYPE_FM2 => 'FM2',
            self::TYPE_FM3 => 'FM3',
            self::TYPE_VISA => 'Visa',
            self::TYPE_DRIVER_LICENSE => 'Licencia de Conducir',
            self::TYPE_PROFESSIONAL_ID => 'Cédula Profesional',
            self::TYPE_MILITARY_ID => 'Cartilla Militar',
        ];
    }
}
