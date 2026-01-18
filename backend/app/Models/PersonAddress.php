<?php

namespace App\Models;

use App\Enums\HousingType;
use App\Traits\HasTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Person address with version history.
 *
 * Stores addresses with full history tracking. When a person moves,
 * a new record is created with valid_from date, and the previous
 * address gets valid_until and is_current=false.
 *
 * Supports multiple address types (home, work, fiscal) simultaneously.
 * Each type can have its own history.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $person_id
 * @property string $type
 * @property string $street
 * @property string $exterior_number
 * @property string|null $interior_number
 * @property string $neighborhood
 * @property string $municipality
 * @property string|null $city
 * @property string $state
 * @property string $postal_code
 * @property string $country
 * @property string|null $between_streets
 * @property string|null $references
 * @property float|null $latitude
 * @property float|null $longitude
 * @property string|null $geocode_accuracy
 * @property \Carbon\Carbon|null $valid_from
 * @property \Carbon\Carbon|null $valid_until
 * @property bool $is_current
 * @property int|null $years_at_address
 * @property int|null $months_at_address
 * @property string|null $housing_type
 * @property float|null $monthly_rent
 * @property string $status
 * @property \Carbon\Carbon|null $verified_at
 * @property string|null $verified_by
 * @property string|null $verification_method
 * @property array|null $verification_data
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
 * @property-read string $full_address
 * @property-read string $type_label
 * @property-read string|null $housing_type_label
 * @property-read int $total_months_at_address
 *
 * @property-read Tenant $tenant
 * @property-read Person $person
 * @property-read StaffAccount|null $verifier
 * @property-read PersonAddress|null $previousVersion
 */
class PersonAddress extends Model
{
    use HasFactory, HasUuids, SoftDeletes, HasTenant;

    protected $table = 'person_addresses';

    public const TYPE_HOME = 'HOME';
    public const TYPE_WORK = 'WORK';
    public const TYPE_FISCAL = 'FISCAL';
    public const TYPE_BILLING = 'BILLING';
    public const TYPE_CORRESPONDENCE = 'CORRESPONDENCE';
    public const TYPE_DELIVERY = 'DELIVERY';

    public const STATUS_PENDING = 'PENDING';
    public const STATUS_VERIFIED = 'VERIFIED';
    public const STATUS_REJECTED = 'REJECTED';

    protected $fillable = [
        'tenant_id',
        'person_id',
        'type',
        'street',
        'exterior_number',
        'interior_number',
        'neighborhood',
        'municipality',
        'city',
        'state',
        'postal_code',
        'country',
        'between_streets',
        'references',
        'latitude',
        'longitude',
        'geocode_accuracy',
        'valid_from',
        'valid_until',
        'is_current',
        'years_at_address',
        'months_at_address',
        'housing_type',
        'monthly_rent',
        'status',
        'verified_at',
        'verified_by',
        'verification_method',
        'verification_data',
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
            'valid_from' => 'date',
            'valid_until' => 'date',
            'verified_at' => 'datetime',
            'replaced_at' => 'datetime',
            'is_current' => 'boolean',
            'verification_data' => 'array',
            'metadata' => 'array',
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
            'monthly_rent' => 'decimal:2',
            'years_at_address' => 'integer',
            'months_at_address' => 'integer',
        ];
    }

    // =====================================================
    // Relationships
    // =====================================================

    /**
     * Get the person this address belongs to.
     */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /**
     * Get the staff who verified this address.
     */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(StaffAccount::class, 'verified_by');
    }

    /**
     * Get the previous version of this address.
     */
    public function previousVersion(): BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_version_id');
    }

    // =====================================================
    // Accessors
    // =====================================================

    /**
     * Check if address is verified.
     */
    public function getIsVerifiedAttribute(): bool
    {
        return $this->status === self::STATUS_VERIFIED && !is_null($this->verified_at);
    }

    /**
     * Get full formatted address.
     */
    public function getFullAddressAttribute(): string
    {
        $parts = [
            $this->street,
            $this->exterior_number,
        ];

        if ($this->interior_number) {
            $parts[] = "Int. {$this->interior_number}";
        }

        $parts[] = $this->neighborhood;
        $parts[] = $this->municipality;

        if ($this->city && $this->city !== $this->municipality) {
            $parts[] = $this->city;
        }

        $parts[] = $this->state;
        $parts[] = "C.P. {$this->postal_code}";

        return implode(', ', array_filter($parts));
    }

    /**
     * Get type label.
     */
    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            self::TYPE_HOME => 'Domicilio',
            self::TYPE_WORK => 'Trabajo',
            self::TYPE_FISCAL => 'Fiscal',
            self::TYPE_BILLING => 'Facturación',
            self::TYPE_CORRESPONDENCE => 'Correspondencia',
            self::TYPE_DELIVERY => 'Entrega',
            default => $this->type,
        };
    }

    /**
     * Get housing type label.
     */
    public function getHousingTypeLabelAttribute(): ?string
    {
        if (!$this->housing_type) {
            return null;
        }

        $enum = HousingType::tryFrom($this->housing_type);
        return $enum?->label();
    }

    /**
     * Get total months at address.
     */
    public function getTotalMonthsAtAddressAttribute(): int
    {
        return ($this->years_at_address ?? 0) * 12 + ($this->months_at_address ?? 0);
    }

    /**
     * Get status label.
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'Pendiente',
            self::STATUS_VERIFIED => 'Verificado',
            self::STATUS_REJECTED => 'Rechazado',
            default => $this->status,
        };
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
        ?array $verificationData = null
    ): void {
        $this->update([
            'status' => self::STATUS_VERIFIED,
            'verified_at' => now(),
            'verified_by' => $verifiedBy,
            'verification_method' => $method,
            'verification_data' => $verificationData,
        ]);
    }

    /**
     * Mark as rejected.
     */
    public function markAsRejected(?string $reason = null): void
    {
        $this->update([
            'status' => self::STATUS_REJECTED,
            'notes' => $reason,
        ]);
    }

    // =====================================================
    // Address History Methods
    // =====================================================

    /**
     * Replace with a new address (person moved).
     */
    public function replaceWith(array $newData, string $reason = 'MOVED'): self
    {
        // Mark current as no longer current
        $this->update([
            'is_current' => false,
            'valid_until' => now(),
            'replaced_at' => now(),
            'replacement_reason' => $reason,
        ]);

        // Create new address
        $newAddress = self::create(array_merge($newData, [
            'tenant_id' => $this->tenant_id,
            'person_id' => $this->person_id,
            'type' => $this->type,
            'is_current' => true,
            'valid_from' => now(),
            'status' => self::STATUS_PENDING,
            'previous_version_id' => $this->id,
        ]));

        return $newAddress;
    }

    /**
     * Calculate residence duration from valid_from.
     */
    public function calculateResidenceDuration(): void
    {
        if (!$this->valid_from) {
            return;
        }

        $start = $this->valid_from;
        $end = $this->valid_until ?? now();

        $years = $start->diffInYears($end);
        $months = $start->copy()->addYears($years)->diffInMonths($end);

        $this->update([
            'years_at_address' => $years,
            'months_at_address' => $months,
        ]);
    }

    // =====================================================
    // Geolocation Methods
    // =====================================================

    /**
     * Set geolocation.
     */
    public function setGeolocation(float $latitude, float $longitude, string $accuracy = 'APPROXIMATE'): void
    {
        $this->update([
            'latitude' => $latitude,
            'longitude' => $longitude,
            'geocode_accuracy' => $accuracy,
        ]);
    }

    /**
     * Check if has geolocation.
     */
    public function hasGeolocation(): bool
    {
        return !is_null($this->latitude) && !is_null($this->longitude);
    }

    // =====================================================
    // Scopes
    // =====================================================

    /**
     * Scope to current addresses.
     *
     * @param \Illuminate\Database\Eloquent\Builder<PersonAddress> $query
     * @return \Illuminate\Database\Eloquent\Builder<PersonAddress>
     */
    public function scopeCurrent($query)
    {
        return $query->where('is_current', true);
    }

    /**
     * Scope to verified addresses.
     *
     * @param \Illuminate\Database\Eloquent\Builder<PersonAddress> $query
     * @return \Illuminate\Database\Eloquent\Builder<PersonAddress>
     */
    public function scopeVerified($query)
    {
        return $query->where('status', self::STATUS_VERIFIED);
    }

    /**
     * Scope to specific type.
     *
     * @param \Illuminate\Database\Eloquent\Builder<PersonAddress> $query
     * @param string $type
     * @return \Illuminate\Database\Eloquent\Builder<PersonAddress>
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope to home addresses.
     *
     * @param \Illuminate\Database\Eloquent\Builder<PersonAddress> $query
     * @return \Illuminate\Database\Eloquent\Builder<PersonAddress>
     */
    public function scopeHome($query)
    {
        return $query->where('type', self::TYPE_HOME);
    }

    /**
     * Scope by postal code.
     *
     * @param \Illuminate\Database\Eloquent\Builder<PersonAddress> $query
     * @param string $postalCode
     * @return \Illuminate\Database\Eloquent\Builder<PersonAddress>
     */
    public function scopeByPostalCode($query, string $postalCode)
    {
        return $query->where('postal_code', $postalCode);
    }

    // =====================================================
    // Type Checks
    // =====================================================

    /**
     * Check if this is a home address.
     */
    public function isHome(): bool
    {
        return $this->type === self::TYPE_HOME;
    }

    /**
     * Check if this is a work address.
     */
    public function isWork(): bool
    {
        return $this->type === self::TYPE_WORK;
    }

    /**
     * Check if this is a fiscal address.
     */
    public function isFiscal(): bool
    {
        return $this->type === self::TYPE_FISCAL;
    }

    // =====================================================
    // Static Finders
    // =====================================================

    /**
     * Find current address by type for a person.
     */
    public static function findCurrentByType(string $personId, string $type): ?self
    {
        return self::where('person_id', $personId)
            ->where('type', $type)
            ->where('is_current', true)
            ->first();
    }

    /**
     * Get available address types.
     */
    public static function getTypes(): array
    {
        return [
            self::TYPE_HOME => 'Domicilio',
            self::TYPE_WORK => 'Trabajo',
            self::TYPE_FISCAL => 'Fiscal',
            self::TYPE_BILLING => 'Facturación',
            self::TYPE_CORRESPONDENCE => 'Correspondencia',
            self::TYPE_DELIVERY => 'Entrega',
        ];
    }
}
