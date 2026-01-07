<?php

namespace App\Models;

use App\Traits\HasTenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Applicant extends Model
{
    use HasFactory, HasUuid, HasTenant, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'type',
        // Identification
        'curp',
        'rfc',
        'ine_clave',
        'ine_ocr',
        'ine_folio',
        'passport_number',
        'passport_issue_date',
        'passport_expiry_date',
        // Personal Data
        'first_name',
        'last_name_1',
        'last_name_2',
        'full_name',
        'birth_date',
        'gender',
        'marital_status',
        'nationality',
        'birth_state',
        'birth_country',
        // Contact Info
        'phone',
        'phone_secondary',
        'email',
        // Additional Info
        'education_level',
        'dependents_count',
        // KYC
        'kyc_status',
        'kyc_data',
        'kyc_verified_at',
        // Signature
        'signature_base64',
        'signature_date',
        'signature_ip',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'passport_issue_date' => 'date',
        'passport_expiry_date' => 'date',
        'dependents_count' => 'integer',
        'kyc_data' => 'array',
        'kyc_verified_at' => 'datetime',
        'signature_date' => 'datetime',
    ];

    /**
     * Applicant type constants.
     */
    public const TYPE_PERSONA_FISICA = 'PERSONA_FISICA';
    public const TYPE_PERSONA_MORAL = 'PERSONA_MORAL';

    /**
     * KYC Status constants.
     */
    public const KYC_PENDING = 'PENDING';
    public const KYC_IN_PROGRESS = 'IN_PROGRESS';
    public const KYC_VERIFIED = 'VERIFIED';
    public const KYC_REJECTED = 'REJECTED';

    /**
     * Gender constants.
     */
    public const GENDER_MALE = 'M';
    public const GENDER_FEMALE = 'F';
    public const GENDER_OTHER = 'O';

    /**
     * Marital status constants.
     */
    public const MARITAL_SINGLE = 'SOLTERO';
    public const MARITAL_MARRIED = 'CASADO';
    public const MARITAL_DIVORCED = 'DIVORCIADO';
    public const MARITAL_WIDOWED = 'VIUDO';
    public const MARITAL_FREE_UNION = 'UNION_LIBRE';

    /**
     * Education level constants.
     */
    public const EDUCATION_PRIMARY = 'PRIMARIA';
    public const EDUCATION_SECONDARY = 'SECUNDARIA';
    public const EDUCATION_HIGH_SCHOOL = 'PREPARATORIA';
    public const EDUCATION_TECHNICAL = 'TECNICO';
    public const EDUCATION_BACHELOR = 'LICENCIATURA';
    public const EDUCATION_MASTER = 'MAESTRIA';
    public const EDUCATION_DOCTORATE = 'DOCTORADO';

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($applicant) {
            // Auto-generate full_name from parts
            if ($applicant->first_name || $applicant->last_name_1) {
                $applicant->full_name = trim(implode(' ', array_filter([
                    $applicant->first_name,
                    $applicant->last_name_1,
                    $applicant->last_name_2,
                ])));
            }
        });
    }

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    /**
     * Get the user associated with this applicant.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get all applications for this applicant.
     */
    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    /**
     * Get all references for this applicant.
     */
    public function references(): HasMany
    {
        return $this->hasMany(Reference::class);
    }

    /**
     * Get all documents for this applicant.
     */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    /**
     * Get all addresses for this applicant.
     */
    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    /**
     * Get the primary address.
     */
    public function primaryAddress(): HasOne
    {
        return $this->hasOne(Address::class)->where('is_primary', true);
    }

    /**
     * Get the home address.
     */
    public function homeAddress(): HasOne
    {
        return $this->hasOne(Address::class)->where('type', 'HOME')->latest();
    }

    /**
     * Get all employment records for this applicant.
     */
    public function employmentRecords(): HasMany
    {
        return $this->hasMany(EmploymentRecord::class);
    }

    /**
     * Get the current employment.
     */
    public function currentEmployment(): HasOne
    {
        return $this->hasOne(EmploymentRecord::class)->where('is_current', true);
    }

    /**
     * Get all bank accounts for this applicant.
     */
    public function bankAccounts(): HasMany
    {
        return $this->hasMany(BankAccount::class);
    }

    /**
     * Get the primary bank account.
     */
    public function primaryBankAccount(): HasOne
    {
        return $this->hasOne(BankAccount::class)->where('is_primary', true);
    }

    // =========================================================================
    // ACCESSORS
    // =========================================================================

    /**
     * Get formatted full name.
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->full_name ?? '';
    }

    /**
     * Get age from birth_date.
     */
    public function getAgeAttribute(): ?int
    {
        if (!$this->birth_date) {
            return null;
        }
        return $this->birth_date->age;
    }

    /**
     * Get monthly income from current employment.
     */
    public function getMonthlyIncomeAttribute(): ?float
    {
        if ($this->relationLoaded('currentEmployment') && $this->currentEmployment) {
            return $this->currentEmployment->monthly_income;
        }

        $employment = $this->currentEmployment()->first();
        return $employment?->monthly_income;
    }

    /**
     * Get gender label.
     */
    public function getGenderLabelAttribute(): string
    {
        $labels = [
            self::GENDER_MALE => 'Masculino',
            self::GENDER_FEMALE => 'Femenino',
            self::GENDER_OTHER => 'Otro',
        ];
        return $labels[$this->gender] ?? '';
    }

    /**
     * Get marital status label.
     */
    public function getMaritalStatusLabelAttribute(): string
    {
        $labels = [
            self::MARITAL_SINGLE => 'Soltero(a)',
            self::MARITAL_MARRIED => 'Casado(a)',
            self::MARITAL_DIVORCED => 'Divorciado(a)',
            self::MARITAL_WIDOWED => 'Viudo(a)',
            self::MARITAL_FREE_UNION => 'Unión Libre',
        ];
        return $labels[$this->marital_status] ?? '';
    }

    /**
     * Get education level label.
     */
    public function getEducationLevelLabelAttribute(): string
    {
        $labels = [
            self::EDUCATION_PRIMARY => 'Primaria',
            self::EDUCATION_SECONDARY => 'Secundaria',
            self::EDUCATION_HIGH_SCHOOL => 'Preparatoria',
            self::EDUCATION_TECHNICAL => 'Técnico',
            self::EDUCATION_BACHELOR => 'Licenciatura',
            self::EDUCATION_MASTER => 'Maestría',
            self::EDUCATION_DOCTORATE => 'Doctorado',
        ];
        return $labels[$this->education_level] ?? '';
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    /**
     * Check if KYC is verified.
     */
    public function isKycVerified(): bool
    {
        return $this->kyc_status === self::KYC_VERIFIED;
    }

    /**
     * Check if applicant has signed.
     */
    public function hasSigned(): bool
    {
        return !empty($this->signature_base64);
    }

    /**
     * Check if personal data is complete.
     */
    public function hasCompletePersonalData(): bool
    {
        return $this->first_name
            && $this->last_name_1
            && $this->birth_date
            && $this->gender
            && $this->curp;
    }

    /**
     * Check if has address.
     */
    public function hasAddress(): bool
    {
        return $this->addresses()->exists();
    }

    /**
     * Check if has verified address.
     */
    public function hasVerifiedAddress(): bool
    {
        return $this->addresses()->where('is_verified', true)->exists();
    }

    /**
     * Check if has employment.
     */
    public function hasEmployment(): bool
    {
        return $this->employmentRecords()->where('is_current', true)->exists();
    }

    /**
     * Check if has verified employment.
     */
    public function hasVerifiedEmployment(): bool
    {
        return $this->employmentRecords()->where('is_verified', true)->exists();
    }

    /**
     * Check if has bank account.
     */
    public function hasBankAccount(): bool
    {
        return $this->bankAccounts()->exists();
    }

    /**
     * Check if has verified bank account.
     */
    public function hasVerifiedBankAccount(): bool
    {
        return $this->bankAccounts()->where('is_verified', true)->exists();
    }

    /**
     * Get completeness percentage.
     */
    public function getCompletenessPercentAttribute(): int
    {
        $total = 6;
        $completed = 0;

        if ($this->hasCompletePersonalData()) $completed++;
        if ($this->hasAddress()) $completed++;
        if ($this->hasEmployment()) $completed++;
        if ($this->hasBankAccount()) $completed++;
        if ($this->documents()->count() > 0) $completed++;
        if ($this->hasSigned()) $completed++;

        return (int) round(($completed / $total) * 100);
    }

    /**
     * Get completeness details.
     */
    public function getCompletenessDetailsAttribute(): array
    {
        return [
            'personal_data' => $this->hasCompletePersonalData(),
            'address' => $this->hasAddress(),
            'employment' => $this->hasEmployment(),
            'bank_account' => $this->hasBankAccount(),
            'documents' => $this->documents()->count() > 0,
            'signature' => $this->hasSigned(),
        ];
    }
}
