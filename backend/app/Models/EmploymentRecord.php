<?php

namespace App\Models;

use App\Traits\HasTenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmploymentRecord extends Model
{
    use HasFactory, HasUuid, HasTenant, SoftDeletes;

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

    /**
     * Employment type constants.
     */
    public const TYPE_EMPLOYED = 'EMPLEADO';
    public const TYPE_INDEPENDENT = 'INDEPENDIENTE';
    public const TYPE_BUSINESS_OWNER = 'EMPRESARIO';
    public const TYPE_RETIRED = 'PENSIONADO';
    public const TYPE_STUDENT = 'ESTUDIANTE';
    public const TYPE_HOMEMAKER = 'HOGAR';
    public const TYPE_UNEMPLOYED = 'DESEMPLEADO';
    public const TYPE_OTHER = 'OTRO';

    /**
     * Company size constants.
     */
    public const SIZE_MICRO = 'MICRO';
    public const SIZE_SMALL = 'PEQUENA';
    public const SIZE_MEDIUM = 'MEDIANA';
    public const SIZE_LARGE = 'GRANDE';

    /**
     * Contract type constants.
     */
    public const CONTRACT_PERMANENT = 'INDEFINIDO';
    public const CONTRACT_TEMPORARY = 'TEMPORAL';
    public const CONTRACT_PROJECT = 'POR_OBRA';
    public const CONTRACT_FREELANCE = 'HONORARIOS';
    public const CONTRACT_COMMISSION = 'COMISION';
    public const CONTRACT_OTHER = 'OTRO';

    /**
     * Payment frequency constants.
     */
    public const FREQUENCY_WEEKLY = 'SEMANAL';
    public const FREQUENCY_BIWEEKLY = 'QUINCENAL';
    public const FREQUENCY_MONTHLY = 'MENSUAL';

    /**
     * Income type constants.
     */
    public const INCOME_PAYROLL = 'NOMINA';
    public const INCOME_FREELANCE = 'HONORARIOS';
    public const INCOME_MIXED = 'MIXTO';
    public const INCOME_COMMISSION = 'COMISIONES';
    public const INCOME_BUSINESS = 'NEGOCIO_PROPIO';
    public const INCOME_PENSION = 'PENSION';
    public const INCOME_OTHER = 'OTRO';

    /**
     * Verification method constants.
     */
    public const VERIFICATION_PAYSLIP = 'RECIBO_NOMINA';
    public const VERIFICATION_LETTER = 'CONSTANCIA';
    public const VERIFICATION_CALL = 'LLAMADA';

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
        $labels = [
            self::TYPE_EMPLOYED => 'Empleado',
            self::TYPE_INDEPENDENT => 'Independiente',
            self::TYPE_BUSINESS_OWNER => 'Empresario',
            self::TYPE_RETIRED => 'Pensionado',
            self::TYPE_STUDENT => 'Estudiante',
            self::TYPE_HOMEMAKER => 'Hogar',
            self::TYPE_UNEMPLOYED => 'Desempleado',
            self::TYPE_OTHER => 'Otro',
        ];

        return $labels[$this->employment_type] ?? $this->employment_type;
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
            self::TYPE_EMPLOYED,
            self::TYPE_RETIRED,
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
