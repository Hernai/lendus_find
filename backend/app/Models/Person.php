<?php

namespace App\Models;

use App\Enums\EducationLevel;
use App\Enums\Gender;
use App\Enums\MaritalStatus;
use App\Traits\HasTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

/**
 * Person entity - base data for individuals.
 *
 * A person represents a physical individual (persona física).
 * This table only contains immutable or rarely-changing data.
 *
 * Related tables store:
 * - person_identifications: CURP, RFC, INE, etc. (with history)
 * - person_addresses: Addresses (with history)
 * - person_employments: Employment records (with history)
 * - person_references: Personal/work references
 * - person_bank_accounts: Bank accounts for disbursement
 *
 * The "applicant" concept is contextual:
 * - A person becomes an "applicant" when they submit an application
 * - applications.person_id links to this table
 *
 * @property string $id
 * @property string $tenant_id
 * @property string|null $account_id
 * @property string $first_name
 * @property string $last_name_1
 * @property string|null $last_name_2
 * @property \Carbon\Carbon|null $birth_date
 * @property string|null $birth_state
 * @property string $birth_country
 * @property string|null $gender
 * @property string $nationality
 * @property string|null $marital_status
 * @property string|null $education_level
 * @property int $dependents_count
 * @property int $profile_completeness
 * @property array|null $missing_data
 * @property string $kyc_status
 * @property \Carbon\Carbon|null $kyc_verified_at
 * @property string|null $kyc_verified_by
 * @property array|null $kyc_data
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 * @property string|null $created_by
 * @property string|null $updated_by
 * @property string|null $deleted_by
 *
 * @property-read string $full_name
 * @property-read int $age
 * @property-read bool $is_kyc_verified
 *
 * @property-read Tenant $tenant
 * @property-read ApplicantAccount|null $account
 * @property-read \Illuminate\Database\Eloquent\Collection<PersonIdentification> $identifications
 * @property-read \Illuminate\Database\Eloquent\Collection<PersonAddress> $addresses
 * @property-read \Illuminate\Database\Eloquent\Collection<PersonEmployment> $employments
 * @property-read \Illuminate\Database\Eloquent\Collection<PersonReference> $references
 * @property-read \Illuminate\Database\Eloquent\Collection<PersonBankAccount> $bankAccounts
 */
class Person extends Model
{
    use HasFactory, HasUuids, SoftDeletes, HasTenant;

    protected $table = 'persons';

    // =====================================================
    // KYC Status Constants
    // =====================================================

    public const KYC_PENDING = 'PENDING';
    public const KYC_IN_PROGRESS = 'IN_PROGRESS';
    public const KYC_VERIFIED = 'VERIFIED';
    public const KYC_REJECTED = 'REJECTED';
    public const KYC_EXPIRED = 'EXPIRED';

    public const KYC_STATUSES = [
        self::KYC_PENDING,
        self::KYC_IN_PROGRESS,
        self::KYC_VERIFIED,
        self::KYC_REJECTED,
        self::KYC_EXPIRED,
    ];

    protected $fillable = [
        'tenant_id',
        'account_id',
        'first_name',
        'last_name_1',
        'last_name_2',
        'birth_date',
        'birth_state',
        'birth_country',
        'gender',
        'nationality',
        'marital_status',
        'education_level',
        'dependents_count',
        'profile_completeness',
        'missing_data',
        'kyc_status',
        'kyc_verified_at',
        'kyc_verified_by',
        'kyc_data',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'kyc_verified_at' => 'datetime',
            'missing_data' => 'array',
            'kyc_data' => 'array',
            'dependents_count' => 'integer',
            'profile_completeness' => 'integer',
        ];
    }

    // =====================================================
    // Relationships
    // =====================================================

    /**
     * Get the authentication account for this person.
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(ApplicantAccount::class, 'account_id');
    }

    /**
     * Get all identifications for this person.
     */
    public function identifications(): HasMany
    {
        return $this->hasMany(PersonIdentification::class);
    }

    /**
     * Get all addresses for this person.
     */
    public function addresses(): HasMany
    {
        return $this->hasMany(PersonAddress::class);
    }

    /**
     * Get all employments for this person.
     */
    public function employments(): HasMany
    {
        return $this->hasMany(PersonEmployment::class);
    }

    /**
     * Get all references for this person.
     */
    public function references(): HasMany
    {
        return $this->hasMany(PersonReference::class);
    }

    /**
     * Get all bank accounts for this person.
     */
    public function bankAccounts(): HasMany
    {
        return $this->hasMany(PersonBankAccount::class, 'owner_id')
            ->where('owner_type', 'persons');
    }

    // =====================================================
    // Current Record Relationships (most recent/active)
    // =====================================================

    /**
     * Get the current CURP identification.
     */
    public function currentCurp(): HasOne
    {
        return $this->hasOne(PersonIdentification::class)
            ->where('type', 'CURP')
            ->where('is_current', true);
    }

    /**
     * Get the current RFC identification.
     */
    public function currentRfc(): HasOne
    {
        return $this->hasOne(PersonIdentification::class)
            ->where('type', 'RFC')
            ->where('is_current', true);
    }

    /**
     * Get the current INE identification.
     */
    public function currentIne(): HasOne
    {
        return $this->hasOne(PersonIdentification::class)
            ->where('type', 'INE')
            ->where('is_current', true);
    }

    /**
     * Get the current home address.
     */
    public function currentHomeAddress(): HasOne
    {
        return $this->hasOne(PersonAddress::class)
            ->where('type', 'HOME')
            ->where('is_current', true);
    }

    /**
     * Get the current employment.
     */
    public function currentEmployment(): HasOne
    {
        return $this->hasOne(PersonEmployment::class)
            ->where('is_current', true);
    }

    /**
     * Get the primary bank account.
     */
    public function primaryBankAccount(): HasOne
    {
        return $this->hasOne(PersonBankAccount::class, 'owner_id')
            ->where('owner_type', 'persons')
            ->where('is_primary', true);
    }

    // =====================================================
    // Accessors
    // =====================================================

    /**
     * Get full name.
     */
    public function getFullNameAttribute(): string
    {
        $parts = array_filter([
            $this->first_name,
            $this->last_name_1,
            $this->last_name_2,
        ]);

        return implode(' ', $parts);
    }

    /**
     * Get age based on birth_date.
     */
    public function getAgeAttribute(): ?int
    {
        if (!$this->birth_date) {
            return null;
        }

        return $this->birth_date->age;
    }

    /**
     * Check if KYC is verified.
     */
    public function getIsKycVerifiedAttribute(): bool
    {
        return $this->kyc_status === self::KYC_VERIFIED;
    }

    /**
     * Get marital status label.
     */
    public function getMaritalStatusLabelAttribute(): ?string
    {
        if (!$this->marital_status) {
            return null;
        }

        $enum = MaritalStatus::tryFrom($this->marital_status);
        return $enum?->label();
    }

    /**
     * Get education level label.
     */
    public function getEducationLevelLabelAttribute(): ?string
    {
        if (!$this->education_level) {
            return null;
        }

        $enum = EducationLevel::tryFrom($this->education_level);
        return $enum?->label();
    }

    /**
     * Get gender label.
     */
    public function getGenderLabelAttribute(): ?string
    {
        if (!$this->gender) {
            return null;
        }

        $enum = Gender::tryFrom($this->gender);
        return $enum?->label();
    }

    // =====================================================
    // Identification Helpers
    // =====================================================

    /**
     * Get current identification value by type.
     */
    public function getCurrentIdentification(string $type): ?PersonIdentification
    {
        return $this->identifications()
            ->where('type', $type)
            ->where('is_current', true)
            ->first();
    }

    /**
     * Get CURP value.
     */
    public function getCurpAttribute(): ?string
    {
        return $this->currentCurp?->identifier_value;
    }

    /**
     * Get RFC value.
     */
    public function getRfcAttribute(): ?string
    {
        return $this->currentRfc?->identifier_value;
    }

    // =====================================================
    // Profile Completeness
    // =====================================================

    /**
     * Calculate and update profile completeness.
     *
     * Uses a transaction to ensure atomic read-calculate-update.
     */
    public function calculateCompleteness(): int
    {
        return DB::transaction(function () {
            $requiredFields = [
                'first_name',
                'last_name_1',
                'birth_date',
                'gender',
                'marital_status',
                'education_level',
            ];

            $filledCount = 0;
            $missing = [];

            foreach ($requiredFields as $field) {
                if (!empty($this->$field)) {
                    $filledCount++;
                } else {
                    $missing[] = $field;
                }
            }

            // Check for required related data
            $hasCurrentAddress = $this->addresses()->where('is_current', true)->exists();
            $hasCurp = $this->identifications()->where('type', 'CURP')->where('is_current', true)->exists();
            $hasRfc = $this->identifications()->where('type', 'RFC')->where('is_current', true)->exists();
            $hasEmployment = $this->employments()->where('is_current', true)->exists();

            $totalItems = count($requiredFields) + 4; // +4 for address, CURP, RFC, employment

            if ($hasCurrentAddress) {
                $filledCount++;
            } else {
                $missing[] = 'current_address';
            }

            if ($hasCurp) {
                $filledCount++;
            } else {
                $missing[] = 'curp';
            }

            if ($hasRfc) {
                $filledCount++;
            } else {
                $missing[] = 'rfc';
            }

            if ($hasEmployment) {
                $filledCount++;
            } else {
                $missing[] = 'current_employment';
            }

            $completeness = (int) round(($filledCount / $totalItems) * 100);

            $this->update([
                'profile_completeness' => $completeness,
                'missing_data' => $missing,
            ]);

            return $completeness;
        });
    }

    /**
     * Check if profile is complete.
     */
    public function isProfileComplete(): bool
    {
        return $this->profile_completeness >= 100;
    }

    // =====================================================
    // KYC Methods
    // =====================================================

    /**
     * Update KYC status.
     */
    public function updateKycStatus(string $status, ?array $kycData = null, ?string $verifiedBy = null): void
    {
        $updateData = [
            'kyc_status' => $status,
        ];

        if ($status === self::KYC_VERIFIED) {
            $updateData['kyc_verified_at'] = now();
            $updateData['kyc_verified_by'] = $verifiedBy;
        }

        if ($kycData !== null) {
            $updateData['kyc_data'] = array_merge($this->kyc_data ?? [], $kycData);
        }

        $this->update($updateData);
    }

    // =====================================================
    // Scopes
    // =====================================================

    /**
     * Scope to KYC verified persons.
     *
     * @param \Illuminate\Database\Eloquent\Builder<Person> $query
     * @return \Illuminate\Database\Eloquent\Builder<Person>
     */
    public function scopeKycVerified($query)
    {
        return $query->where('kyc_status', self::KYC_VERIFIED);
    }

    /**
     * Scope to persons with complete profiles.
     *
     * @param \Illuminate\Database\Eloquent\Builder<Person> $query
     * @return \Illuminate\Database\Eloquent\Builder<Person>
     */
    public function scopeProfileComplete($query)
    {
        return $query->where('profile_completeness', '>=', 100);
    }

    /**
     * Scope to search by name.
     *
     * @param \Illuminate\Database\Eloquent\Builder<Person> $query
     * @param string $search
     * @return \Illuminate\Database\Eloquent\Builder<Person>
     */
    public function scopeSearchByName($query, string $search)
    {
        // Escapar wildcards SQL para prevenir inyección
        $search = str_replace(['%', '_'], ['\\%', '\\_'], $search);
        $search = mb_substr($search, 0, 100);

        return $query->where(function ($q) use ($search) {
            $q->where('first_name', 'LIKE', "%{$search}%")
              ->orWhere('last_name_1', 'LIKE', "%{$search}%")
              ->orWhere('last_name_2', 'LIKE', "%{$search}%");
        });
    }

    // =====================================================
    // Static Finders
    // =====================================================

    /**
     * Find person by CURP within a tenant.
     */
    public static function findByCurp(string $curp, string $tenantId): ?self
    {
        return self::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->whereHas('identifications', function ($query) use ($curp) {
                $query->where('type', 'CURP')
                    ->where('identifier_value', $curp)
                    ->where('is_current', true);
            })
            ->first();
    }

    /**
     * Find person by RFC within a tenant.
     */
    public static function findByRfc(string $rfc, string $tenantId): ?self
    {
        return self::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->whereHas('identifications', function ($query) use ($rfc) {
                $query->where('type', 'RFC')
                    ->where('identifier_value', $rfc)
                    ->where('is_current', true);
            })
            ->first();
    }
}
