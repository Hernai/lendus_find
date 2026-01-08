<?php

namespace App\Models;

use App\Enums\ApplicantType;
use App\Enums\EducationLevel;
use App\Enums\Gender;
use App\Enums\KycStatus;
use App\Enums\MaritalStatus;
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
        'phone_verified_at',
        'phone_secondary',
        'email',
        'email_verified_at',
        // Additional Info
        'education_level',
        'dependents_count',
        // KYC
        'kyc_status',
        'kyc_data',
        'kyc_verified_at',
        // Identity Verification
        'identity_verified_at',
        'identity_verified_by',
        // Signature
        'signature_base64',
        'signature_date',
        'signature_ip',
    ];

    protected $casts = [
        'type' => ApplicantType::class,
        'kyc_status' => KycStatus::class,
        'gender' => Gender::class,
        'marital_status' => MaritalStatus::class,
        'education_level' => EducationLevel::class,
        'birth_date' => 'date',
        'passport_issue_date' => 'date',
        'passport_expiry_date' => 'date',
        'dependents_count' => 'integer',
        'kyc_data' => 'array',
        'kyc_verified_at' => 'datetime',
        'phone_verified_at' => 'datetime',
        'email_verified_at' => 'datetime',
        'identity_verified_at' => 'datetime',
        'signature_date' => 'datetime',
    ];

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
     * Get all data verifications for this applicant.
     */
    public function dataVerifications(): HasMany
    {
        return $this->hasMany(DataVerification::class);
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
        return $this->gender?->label() ?? '';
    }

    /**
     * Get marital status label.
     */
    public function getMaritalStatusLabelAttribute(): string
    {
        return $this->marital_status?->label() ?? '';
    }

    /**
     * Get education level label.
     */
    public function getEducationLevelLabelAttribute(): string
    {
        return $this->education_level?->label() ?? '';
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    /**
     * Check if KYC is verified.
     */
    public function isKycVerified(): bool
    {
        return $this->kyc_status === KycStatus::VERIFIED;
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
