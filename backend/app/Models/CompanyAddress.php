<?php

namespace App\Models;

use App\Traits\HasTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * CompanyAddress model.
 *
 * Stores company addresses with history support.
 */
class CompanyAddress extends Model
{
    use HasFactory, HasUuids, SoftDeletes, HasTenant;

    protected $fillable = [
        'tenant_id',
        'company_id',
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
        'valid_from',
        'valid_until',
        'is_current',
        'status',
        'verified_at',
        'verified_by',
        'previous_version_id',
        'replaced_at',
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'valid_from' => 'date',
        'valid_until' => 'date',
        'is_current' => 'boolean',
        'verified_at' => 'datetime',
        'replaced_at' => 'datetime',
    ];

    // =====================================================
    // Address Types
    // =====================================================

    public const TYPE_FISCAL = 'FISCAL';
    public const TYPE_HEADQUARTERS = 'HEADQUARTERS';
    public const TYPE_BRANCH = 'BRANCH';
    public const TYPE_WAREHOUSE = 'WAREHOUSE';

    public static function types(): array
    {
        return [
            self::TYPE_FISCAL => 'Domicilio Fiscal',
            self::TYPE_HEADQUARTERS => 'Oficinas Centrales',
            self::TYPE_BRANCH => 'Sucursal',
            self::TYPE_WAREHOUSE => 'Almacén',
        ];
    }

    // =====================================================
    // Statuses
    // =====================================================

    public const STATUS_PENDING = 'PENDING';
    public const STATUS_VERIFIED = 'VERIFIED';
    public const STATUS_REJECTED = 'REJECTED';

    // =====================================================
    // Relationships
    // =====================================================

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function verifiedByStaff(): BelongsTo
    {
        return $this->belongsTo(StaffAccount::class, 'verified_by');
    }

    public function previousVersion(): BelongsTo
    {
        return $this->belongsTo(CompanyAddress::class, 'previous_version_id');
    }

    // =====================================================
    // Accessors
    // =====================================================

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
        $parts[] = $this->state;
        $parts[] = "C.P. {$this->postal_code}";

        return implode(', ', array_filter($parts));
    }

    public function getShortAddressAttribute(): string
    {
        return "{$this->street} {$this->exterior_number}, {$this->neighborhood}";
    }

    public function getTypeLabelAttribute(): string
    {
        return self::types()[$this->type] ?? $this->type;
    }

    // =====================================================
    // Status Helpers
    // =====================================================

    public function isVerified(): bool
    {
        return $this->status === self::STATUS_VERIFIED;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isCurrent(): bool
    {
        return $this->is_current;
    }

    // =====================================================
    // Actions
    // =====================================================

    public function verify(string $staffId): void
    {
        $this->update([
            'status' => self::STATUS_VERIFIED,
            'verified_at' => now(),
            'verified_by' => $staffId,
        ]);
    }

    public function reject(string $staffId): void
    {
        $this->update([
            'status' => self::STATUS_REJECTED,
            'verified_at' => now(),
            'verified_by' => $staffId,
        ]);
    }

    public function markAsReplaced(string $newVersionId): void
    {
        $this->update([
            'is_current' => false,
            'replaced_at' => now(),
        ]);
    }

    // =====================================================
    // Scopes
    // =====================================================

    public function scopeCurrent($query)
    {
        return $query->where('is_current', true);
    }

    public function scopeVerified($query)
    {
        return $query->where('status', self::STATUS_VERIFIED);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeFiscal($query)
    {
        return $query->where('type', self::TYPE_FISCAL);
    }
}
