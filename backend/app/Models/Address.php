<?php

namespace App\Models;

use App\Enums\AddressType;
use App\Enums\AddressVerificationMethod;
use App\Enums\HousingType;
use App\Traits\HasAuditFields;
use App\Traits\HasTenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Address extends Model
{
    use HasFactory, HasUuid, HasTenant, SoftDeletes, HasAuditFields;

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
        'type' => AddressType::class,
        'housing_type' => HousingType::class,
        'verification_method' => AddressVerificationMethod::class,
        'is_primary' => 'boolean',
        'monthly_rent' => 'decimal:2',
        'years_at_address' => 'integer',
        'months_at_address' => 'integer',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'is_verified' => 'boolean',
        'verified_at' => 'datetime',
    ];

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
        return $this->housing_type?->label() ?? '';
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
