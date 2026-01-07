<?php

namespace App\Models;

use App\Traits\HasTenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Address extends Model
{
    use HasFactory, HasUuid, HasTenant, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'applicant_id',
        'type',
        'is_primary',
        // Street Address
        'street',
        'ext_number',
        'int_number',
        'neighborhood',
        'municipality',
        // Location
        'postal_code',
        'city',
        'state',
        'country',
        // Additional Info
        'between_streets',
        'references',
        // Housing Info
        'housing_type',
        'monthly_rent',
        'years_at_address',
        'months_at_address',
        // Geolocation
        'latitude',
        'longitude',
        // Verification
        'is_verified',
        'verified_at',
        'verification_method',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'monthly_rent' => 'decimal:2',
        'years_at_address' => 'integer',
        'months_at_address' => 'integer',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'is_verified' => 'boolean',
        'verified_at' => 'datetime',
    ];

    /**
     * Address type constants.
     */
    public const TYPE_HOME = 'HOME';
    public const TYPE_WORK = 'WORK';
    public const TYPE_FISCAL = 'FISCAL';
    public const TYPE_CORRESPONDENCE = 'CORRESPONDENCE';

    /**
     * Housing type constants.
     */
    public const HOUSING_OWNED_PAID = 'PROPIA_PAGADA';
    public const HOUSING_OWNED_MORTGAGE = 'PROPIA_HIPOTECA';
    public const HOUSING_RENTED = 'RENTADA';
    public const HOUSING_FAMILY = 'FAMILIAR';
    public const HOUSING_BORROWED = 'PRESTADA';
    public const HOUSING_OTHER = 'OTRO';

    /**
     * Verification method constants.
     */
    public const VERIFICATION_DOCUMENT = 'DOCUMENT';
    public const VERIFICATION_GEOLOCATION = 'GEOLOCATION';
    public const VERIFICATION_VISIT = 'VISIT';

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    /**
     * Get the applicant that owns this address.
     */
    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Applicant::class);
    }

    /**
     * Get the tenant.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    // =========================================================================
    // ACCESSORS
    // =========================================================================

    /**
     * Get full address as a single line.
     */
    public function getFullAddressAttribute(): string
    {
        $parts = [
            $this->street,
            $this->ext_number,
        ];

        if ($this->int_number) {
            $parts[] = "Int. {$this->int_number}";
        }

        $parts[] = "Col. {$this->neighborhood}";
        $parts[] = "C.P. {$this->postal_code}";
        $parts[] = $this->city;
        $parts[] = $this->state;

        return implode(', ', array_filter($parts));
    }

    /**
     * Get time at address in months.
     */
    public function getTotalMonthsAtAddressAttribute(): int
    {
        return (($this->years_at_address ?? 0) * 12) + ($this->months_at_address ?? 0);
    }

    /**
     * Get housing type label.
     */
    public function getHousingTypeLabelAttribute(): string
    {
        $labels = [
            self::HOUSING_OWNED_PAID => 'Propia (pagada)',
            self::HOUSING_OWNED_MORTGAGE => 'Propia (hipoteca)',
            self::HOUSING_RENTED => 'Rentada',
            self::HOUSING_FAMILY => 'Familiar',
            self::HOUSING_BORROWED => 'Prestada',
            self::HOUSING_OTHER => 'Otro',
        ];

        return $labels[$this->housing_type] ?? $this->housing_type;
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    /**
     * Scope to get primary addresses.
     */
    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }

    /**
     * Scope to get verified addresses.
     */
    public function scopeVerified($query)
    {
        return $query->where('is_verified', true);
    }

    /**
     * Scope by type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope by state.
     */
    public function scopeInState($query, string $state)
    {
        return $query->where('state', $state);
    }

    /**
     * Scope by postal code.
     */
    public function scopeByPostalCode($query, string $postalCode)
    {
        return $query->where('postal_code', $postalCode);
    }
}
