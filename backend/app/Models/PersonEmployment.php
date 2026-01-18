<?php

namespace App\Models;

use App\Enums\CompanySize;
use App\Enums\ContractType;
use App\Enums\EmploymentType;
use App\Enums\PaymentFrequency;
use App\Traits\HasTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Person employment record with history.
 *
 * Stores current and past employment records for credit analysis.
 * Tracks income, employer data, and verification status.
 *
 * Employment history is important for:
 * - Evaluating job stability
 * - Verifying income claims
 * - Understanding career progression
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $person_id
 * @property string $employment_type
 * @property bool $is_current
 * @property string|null $employer_name
 * @property string|null $employer_rfc
 * @property string|null $employer_phone
 * @property string|null $employer_address
 * @property string|null $industry_code
 * @property string|null $industry_description
 * @property string|null $company_size
 * @property string|null $job_title
 * @property string|null $department
 * @property string|null $employee_number
 * @property string|null $contract_type
 * @property \Carbon\Carbon|null $start_date
 * @property \Carbon\Carbon|null $end_date
 * @property int|null $years_employed
 * @property int|null $months_employed
 * @property float|null $monthly_income
 * @property float|null $additional_income
 * @property string|null $payment_frequency
 * @property string $income_currency
 * @property bool $income_verified
 * @property \Carbon\Carbon|null $income_verified_at
 * @property string|null $income_verified_by
 * @property string|null $income_verification_method
 * @property float|null $verified_income
 * @property string $status
 * @property \Carbon\Carbon|null $verified_at
 * @property string|null $verified_by
 * @property string|null $verification_method
 * @property string|null $verification_notes
 * @property array|null $verification_data
 * @property string|null $notes
 * @property array|null $metadata
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 * @property string|null $created_by
 * @property string|null $updated_by
 *
 * @property-read bool $is_verified
 * @property-read bool $is_income_verified
 * @property-read float $total_monthly_income
 * @property-read int $total_months_employed
 * @property-read string $employment_type_label
 * @property-read string|null $contract_type_label
 * @property-read string|null $payment_frequency_label
 *
 * @property-read Tenant $tenant
 * @property-read Person $person
 * @property-read StaffAccount|null $verifier
 * @property-read StaffAccount|null $incomeVerifier
 */
class PersonEmployment extends Model
{
    use HasFactory, HasUuids, SoftDeletes, HasTenant;

    protected $table = 'person_employments';

    public const STATUS_PENDING = 'PENDING';
    public const STATUS_VERIFIED = 'VERIFIED';
    public const STATUS_REJECTED = 'REJECTED';
    public const STATUS_UNREACHABLE = 'UNREACHABLE';

    protected $fillable = [
        'tenant_id',
        'person_id',
        'employment_type',
        'is_current',
        'employer_name',
        'employer_rfc',
        'employer_phone',
        'employer_address',
        'industry_code',
        'industry_description',
        'company_size',
        'job_title',
        'department',
        'employee_number',
        'contract_type',
        'start_date',
        'end_date',
        'years_employed',
        'months_employed',
        'monthly_income',
        'additional_income',
        'payment_frequency',
        'income_currency',
        'income_verified',
        'income_verified_at',
        'income_verified_by',
        'income_verification_method',
        'verified_income',
        'status',
        'verified_at',
        'verified_by',
        'verification_method',
        'verification_notes',
        'verification_data',
        'notes',
        'metadata',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'verified_at' => 'datetime',
            'income_verified_at' => 'datetime',
            'is_current' => 'boolean',
            'income_verified' => 'boolean',
            'verification_data' => 'array',
            'metadata' => 'array',
            'monthly_income' => 'decimal:2',
            'additional_income' => 'decimal:2',
            'verified_income' => 'decimal:2',
            'years_employed' => 'integer',
            'months_employed' => 'integer',
        ];
    }

    // =====================================================
    // Relationships
    // =====================================================

    /**
     * Get the person this employment belongs to.
     */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /**
     * Get the staff who verified this employment.
     */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(StaffAccount::class, 'verified_by');
    }

    /**
     * Get the staff who verified income.
     */
    public function incomeVerifier(): BelongsTo
    {
        return $this->belongsTo(StaffAccount::class, 'income_verified_by');
    }

    // =====================================================
    // Accessors
    // =====================================================

    /**
     * Check if employment is verified.
     */
    public function getIsVerifiedAttribute(): bool
    {
        return $this->status === self::STATUS_VERIFIED && !is_null($this->verified_at);
    }

    /**
     * Check if income is verified.
     */
    public function getIsIncomeVerifiedAttribute(): bool
    {
        return $this->income_verified && !is_null($this->income_verified_at);
    }

    /**
     * Get total monthly income (base + additional).
     */
    public function getTotalMonthlyIncomeAttribute(): float
    {
        return ($this->monthly_income ?? 0) + ($this->additional_income ?? 0);
    }

    /**
     * Get total months employed.
     */
    public function getTotalMonthsEmployedAttribute(): int
    {
        return ($this->years_employed ?? 0) * 12 + ($this->months_employed ?? 0);
    }

    /**
     * Get employment type label.
     */
    public function getEmploymentTypeLabelAttribute(): string
    {
        $enum = EmploymentType::tryFrom($this->employment_type);
        return $enum?->label() ?? $this->employment_type;
    }

    /**
     * Get contract type label.
     */
    public function getContractTypeLabelAttribute(): ?string
    {
        if (!$this->contract_type) {
            return null;
        }

        $enum = ContractType::tryFrom($this->contract_type);
        return $enum?->label();
    }

    /**
     * Get payment frequency label.
     */
    public function getPaymentFrequencyLabelAttribute(): ?string
    {
        if (!$this->payment_frequency) {
            return null;
        }

        $enum = PaymentFrequency::tryFrom($this->payment_frequency);
        return $enum?->label();
    }

    /**
     * Get company size label.
     */
    public function getCompanySizeLabelAttribute(): ?string
    {
        if (!$this->company_size) {
            return null;
        }

        $enum = CompanySize::tryFrom($this->company_size);
        return $enum?->label();
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
            self::STATUS_UNREACHABLE => 'No contactable',
            default => $this->status,
        };
    }

    // =====================================================
    // Verification Methods
    // =====================================================

    /**
     * Mark employment as verified.
     */
    public function markAsVerified(
        string $method,
        ?string $verifiedBy = null,
        ?string $notes = null,
        ?array $verificationData = null
    ): void {
        $this->update([
            'status' => self::STATUS_VERIFIED,
            'verified_at' => now(),
            'verified_by' => $verifiedBy,
            'verification_method' => $method,
            'verification_notes' => $notes,
            'verification_data' => $verificationData,
        ]);
    }

    /**
     * Mark income as verified.
     */
    public function verifyIncome(
        float $verifiedAmount,
        string $method,
        ?string $verifiedBy = null
    ): void {
        $this->update([
            'income_verified' => true,
            'income_verified_at' => now(),
            'income_verified_by' => $verifiedBy,
            'income_verification_method' => $method,
            'verified_income' => $verifiedAmount,
        ]);
    }

    /**
     * Mark as rejected.
     */
    public function markAsRejected(?string $reason = null): void
    {
        $this->update([
            'status' => self::STATUS_REJECTED,
            'verification_notes' => $reason,
        ]);
    }

    /**
     * Mark as unreachable.
     */
    public function markAsUnreachable(?string $notes = null): void
    {
        $this->update([
            'status' => self::STATUS_UNREACHABLE,
            'verification_notes' => $notes,
        ]);
    }

    // =====================================================
    // Employment Duration Methods
    // =====================================================

    /**
     * Calculate employment duration from start_date.
     */
    public function calculateDuration(): void
    {
        if (!$this->start_date) {
            return;
        }

        $start = $this->start_date;
        $end = $this->end_date ?? now();

        $years = $start->diffInYears($end);
        $months = $start->copy()->addYears($years)->diffInMonths($end);

        $this->update([
            'years_employed' => $years,
            'months_employed' => $months,
        ]);
    }

    /**
     * End current employment.
     */
    public function endEmployment(?\Carbon\Carbon $endDate = null): void
    {
        $this->update([
            'is_current' => false,
            'end_date' => $endDate ?? now(),
        ]);

        $this->calculateDuration();
    }

    // =====================================================
    // Type Checks
    // =====================================================

    /**
     * Check if this is salaried employment.
     */
    public function isEmployee(): bool
    {
        return $this->employment_type === EmploymentType::EMPLOYEE->value;
    }

    /**
     * Check if self-employed.
     */
    public function isSelfEmployed(): bool
    {
        return $this->employment_type === EmploymentType::SELF_EMPLOYED->value;
    }

    /**
     * Check if retired/pensioner.
     */
    public function isRetired(): bool
    {
        return $this->employment_type === EmploymentType::RETIRED->value;
    }

    /**
     * Check if requires proof of income.
     */
    public function requiresProofOfIncome(): bool
    {
        $enum = EmploymentType::tryFrom($this->employment_type);
        return $enum?->requiresProofOfIncome() ?? false;
    }

    // =====================================================
    // Scopes
    // =====================================================

    /**
     * Scope to current employments.
     *
     * @param \Illuminate\Database\Eloquent\Builder<PersonEmployment> $query
     * @return \Illuminate\Database\Eloquent\Builder<PersonEmployment>
     */
    public function scopeCurrent($query)
    {
        return $query->where('is_current', true);
    }

    /**
     * Scope to verified employments.
     *
     * @param \Illuminate\Database\Eloquent\Builder<PersonEmployment> $query
     * @return \Illuminate\Database\Eloquent\Builder<PersonEmployment>
     */
    public function scopeVerified($query)
    {
        return $query->where('status', self::STATUS_VERIFIED);
    }

    /**
     * Scope to income verified.
     *
     * @param \Illuminate\Database\Eloquent\Builder<PersonEmployment> $query
     * @return \Illuminate\Database\Eloquent\Builder<PersonEmployment>
     */
    public function scopeIncomeVerified($query)
    {
        return $query->where('income_verified', true);
    }

    /**
     * Scope to specific employment type.
     *
     * @param \Illuminate\Database\Eloquent\Builder<PersonEmployment> $query
     * @param string $type
     * @return \Illuminate\Database\Eloquent\Builder<PersonEmployment>
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('employment_type', $type);
    }

    /**
     * Scope by employer RFC.
     *
     * @param \Illuminate\Database\Eloquent\Builder<PersonEmployment> $query
     * @param string $rfc
     * @return \Illuminate\Database\Eloquent\Builder<PersonEmployment>
     */
    public function scopeByEmployerRfc($query, string $rfc)
    {
        return $query->where('employer_rfc', $rfc);
    }

    // =====================================================
    // Static Finders
    // =====================================================

    /**
     * Find current employment for a person.
     */
    public static function findCurrentForPerson(string $personId): ?self
    {
        return self::where('person_id', $personId)
            ->where('is_current', true)
            ->first();
    }

    /**
     * Get all employment history for a person.
     */
    public static function getHistoryForPerson(string $personId): \Illuminate\Database\Eloquent\Collection
    {
        return self::where('person_id', $personId)
            ->orderBy('start_date', 'desc')
            ->get();
    }
}
