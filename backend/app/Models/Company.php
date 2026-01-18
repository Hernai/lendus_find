<?php

namespace App\Models;

use App\Traits\HasTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Company model (Persona Moral).
 *
 * Represents a legal entity that can apply for business loans.
 * Similar to how Clara/Konfío handle business accounts.
 */
class Company extends Model
{
    use HasFactory, HasUuids, SoftDeletes, HasTenant;

    protected $fillable = [
        'tenant_id',
        'created_by_account_id',
        'legal_name',
        'trade_name',
        'rfc',
        'legal_entity_type',
        'incorporation_date',
        'notary_number',
        'commercial_folio',
        'industry_code',
        'industry_description',
        'main_activity',
        'company_size',
        'employees_count',
        'annual_revenue',
        'annual_revenue_currency',
        'website',
        'main_phone',
        'main_email',
        'status',
        'verified_at',
        'verified_by',
        'kyb_status',
        'kyb_verified_at',
        'kyb_data',
        'notes',
        'metadata',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'incorporation_date' => 'date',
        'annual_revenue' => 'decimal:2',
        'verified_at' => 'datetime',
        'kyb_verified_at' => 'datetime',
        'kyb_data' => 'array',
        'metadata' => 'array',
    ];

    // =====================================================
    // Legal Entity Types
    // =====================================================

    public const ENTITY_SA = 'SA';
    public const ENTITY_SAPI = 'SAPI';
    public const ENTITY_SA_DE_CV = 'SA_DE_CV';
    public const ENTITY_SAPI_DE_CV = 'SAPI_DE_CV';
    public const ENTITY_SC = 'SC';
    public const ENTITY_SRL = 'SRL';
    public const ENTITY_SRLCV = 'SRLCV';
    public const ENTITY_AC = 'AC';
    public const ENTITY_SC_RL = 'SC_RL';
    public const ENTITY_SOFOM = 'SOFOM';

    public static function entityTypes(): array
    {
        return [
            self::ENTITY_SA => 'Sociedad Anónima',
            self::ENTITY_SAPI => 'Sociedad Anónima Promotora de Inversión',
            self::ENTITY_SA_DE_CV => 'Sociedad Anónima de Capital Variable',
            self::ENTITY_SAPI_DE_CV => 'S.A.P.I. de C.V.',
            self::ENTITY_SC => 'Sociedad Civil',
            self::ENTITY_SRL => 'Sociedad de Responsabilidad Limitada',
            self::ENTITY_SRLCV => 'S. de R.L. de C.V.',
            self::ENTITY_AC => 'Asociación Civil',
            self::ENTITY_SC_RL => 'S.C. de R.L.',
            self::ENTITY_SOFOM => 'SOFOM',
        ];
    }

    // =====================================================
    // Company Sizes
    // =====================================================

    public const SIZE_MICRO = 'MICRO';
    public const SIZE_SMALL = 'SMALL';
    public const SIZE_MEDIUM = 'MEDIUM';
    public const SIZE_LARGE = 'LARGE';

    public static function companySizes(): array
    {
        return [
            self::SIZE_MICRO => 'Micro (1-10 empleados)',
            self::SIZE_SMALL => 'Pequeña (11-50 empleados)',
            self::SIZE_MEDIUM => 'Mediana (51-250 empleados)',
            self::SIZE_LARGE => 'Grande (250+ empleados)',
        ];
    }

    // =====================================================
    // Statuses
    // =====================================================

    public const STATUS_PENDING = 'PENDING_VERIFICATION';
    public const STATUS_VERIFIED = 'VERIFIED';
    public const STATUS_SUSPENDED = 'SUSPENDED';
    public const STATUS_CLOSED = 'CLOSED';

    public const KYB_PENDING = 'PENDING';
    public const KYB_IN_PROGRESS = 'IN_PROGRESS';
    public const KYB_VERIFIED = 'VERIFIED';
    public const KYB_REJECTED = 'REJECTED';

    // =====================================================
    // Relationships
    // =====================================================

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function createdByAccount(): BelongsTo
    {
        return $this->belongsTo(ApplicantAccount::class, 'created_by_account_id');
    }

    public function verifiedByStaff(): BelongsTo
    {
        return $this->belongsTo(StaffAccount::class, 'verified_by');
    }

    public function members(): HasMany
    {
        return $this->hasMany(CompanyMember::class);
    }

    public function activeMembers(): HasMany
    {
        return $this->members()->where('status', 'ACTIVE');
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(CompanyAddress::class);
    }

    public function currentAddresses(): HasMany
    {
        return $this->addresses()->where('is_current', true);
    }

    // =====================================================
    // Accessors
    // =====================================================

    public function getDisplayNameAttribute(): string
    {
        return $this->trade_name ?? $this->legal_name;
    }

    public function getFullLegalNameAttribute(): string
    {
        $type = self::entityTypes()[$this->legal_entity_type] ?? '';
        return trim("{$this->legal_name} {$type}");
    }

    public function getFiscalAddressAttribute(): ?CompanyAddress
    {
        return $this->addresses()
            ->where('type', 'FISCAL')
            ->where('is_current', true)
            ->first();
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

    public function isSuspended(): bool
    {
        return $this->status === self::STATUS_SUSPENDED;
    }

    public function isKybVerified(): bool
    {
        return $this->kyb_status === self::KYB_VERIFIED;
    }

    public function canApplyForCredit(): bool
    {
        return $this->isVerified() && $this->isKybVerified();
    }

    // =====================================================
    // Member Helpers
    // =====================================================

    public function getLegalRepresentatives()
    {
        return $this->activeMembers()
            ->where('is_legal_representative', true)
            ->get();
    }

    public function getShareholders()
    {
        return $this->activeMembers()
            ->where('is_shareholder', true)
            ->orderByDesc('ownership_percentage')
            ->get();
    }

    public function getMemberByPerson(string $personId): ?CompanyMember
    {
        return $this->members()
            ->where('person_id', $personId)
            ->first();
    }

    public function getMemberByAccount(string $accountId): ?CompanyMember
    {
        return $this->members()
            ->where('account_id', $accountId)
            ->first();
    }

    public function hasMember(string $personId): bool
    {
        return $this->members()
            ->where('person_id', $personId)
            ->exists();
    }

    // =====================================================
    // Scopes
    // =====================================================

    public function scopeVerified($query)
    {
        return $query->where('status', self::STATUS_VERIFIED);
    }

    public function scopeKybVerified($query)
    {
        return $query->where('kyb_status', self::KYB_VERIFIED);
    }

    public function scopeActive($query)
    {
        return $query->whereNotIn('status', [self::STATUS_SUSPENDED, self::STATUS_CLOSED]);
    }

    public function scopeByRfc($query, string $rfc)
    {
        return $query->where('rfc', strtoupper($rfc));
    }

    public function scopeSearch($query, string $term)
    {
        $term = strtolower($term);
        return $query->where(function ($q) use ($term) {
            $q->whereRaw('LOWER(legal_name) LIKE ?', ["%{$term}%"])
                ->orWhereRaw('LOWER(trade_name) LIKE ?', ["%{$term}%"])
                ->orWhereRaw('LOWER(rfc) LIKE ?', ["%{$term}%"]);
        });
    }
}
