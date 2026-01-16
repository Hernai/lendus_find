<?php

namespace App\Models;

use App\Enums\CompanySize;
use App\Enums\ContractType;
use App\Enums\EmploymentType;
use App\Enums\EmploymentVerificationMethod;
use App\Enums\IncomeType;
use App\Enums\PaymentFrequency;
use App\Traits\HasAuditFields;
use App\Traits\HasTenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmploymentRecord extends Model
{
    use HasFactory, HasUuid, HasTenant, SoftDeletes, HasAuditFields;

    protected $fillable = [
        'tenant_id',
        'applicant_id',
        'is_current',
        'employment_type',
        // Company Info
        'company_name',
        'company_rfc',
        'company_industry',
        'company_size',
        // Position Info
        'position',
        'department',
        'contract_type',
        // Dates
        'start_date',
        'end_date',
        'seniority_months',
        // Income
        'monthly_income',
        'monthly_net_income',
        'payment_frequency',
        'income_type',
        'other_income',
        'other_income_source',
        // Contact at work
        'work_phone',
        'work_phone_ext',
        'supervisor_name',
        'supervisor_phone',
        // Work Address
        'work_address_id',
        // Verification
        'is_verified',
        'verified_at',
        'verified_by',
        'verification_notes',
        'verification_method',
        // Proof document
        'proof_document_id',
    ];

    protected $casts = [
        'employment_type' => EmploymentType::class,
        'company_size' => CompanySize::class,
        'contract_type' => ContractType::class,
        'payment_frequency' => PaymentFrequency::class,
        'income_type' => IncomeType::class,
        'verification_method' => EmploymentVerificationMethod::class,
        'is_current' => 'boolean',
        'start_date' => 'date',
        'end_date' => 'date',
        'seniority_months' => 'integer',
        'monthly_income' => 'decimal:2',
        'monthly_net_income' => 'decimal:2',
        'other_income' => 'decimal:2',
        'is_verified' => 'boolean',
        'verified_at' => 'datetime',
    ];

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    /**
     * Get the applicant that owns this employment record.
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

    /**
     * Get the work address.
     */
    public function workAddress(): BelongsTo
    {
        return $this->belongsTo(Address::class, 'work_address_id');
    }

    /**
     * Get the proof document.
     */
    public function proofDocument(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'proof_document_id');
    }

    /**
     * Get the user who verified this record.
     */
    public function verifiedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    // =========================================================================
    // ACCESSORS
    // =========================================================================

    /**
     * Get employment type label.
     */
    public function getEmploymentTypeLabelAttribute(): string
    {
        return $this->employment_type?->label() ?? '';
    }

    /**
     * Calculate seniority in months from start_date.
     */
    public function getCalculatedSeniorityMonthsAttribute(): int
    {
        if (!$this->start_date) {
            return 0;
        }

        $endDate = $this->end_date ?? now();
        return $this->start_date->diffInMonths($endDate);
    }

    /**
     * Get total monthly income (base + other).
     */
    public function getTotalMonthlyIncomeAttribute(): float
    {
        return ($this->monthly_income ?? 0) + ($this->other_income ?? 0);
    }

    /**
     * Get annual income.
     */
    public function getAnnualIncomeAttribute(): float
    {
        return $this->total_monthly_income * 12;
    }

    /**
     * Check if employment allows income verification.
     */
    public function getAllowsIncomeVerificationAttribute(): bool
    {
        return in_array($this->employment_type, [
            EmploymentType::EMPLOYED->value,
            EmploymentType::RETIRED->value,
        ]);
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    /**
     * Scope to get current employments.
     */
    public function scopeCurrent($query)
    {
        return $query->where('is_current', true);
    }

    /**
     * Scope to get verified employments.
     */
    public function scopeVerified($query)
    {
        return $query->where('is_verified', true);
    }

    /**
     * Scope by employment type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('employment_type', $type);
    }

    /**
     * Scope by minimum income.
     */
    public function scopeMinIncome($query, float $amount)
    {
        return $query->where('monthly_income', '>=', $amount);
    }

    /**
     * Scope by minimum seniority.
     */
    public function scopeMinSeniority($query, int $months)
    {
        return $query->where('seniority_months', '>=', $months);
    }
}
