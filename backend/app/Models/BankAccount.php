<?php

namespace App\Models;

use App\Enums\BankAccountType;
use App\Enums\BankAccountUsageType;
use App\Enums\BankVerificationMethod;
use App\Traits\HasTenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class BankAccount extends Model
{
    use HasFactory, HasUuid, HasTenant, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'applicant_id',
        'type',
        'is_primary',
        // Bank Info
        'bank_name',
        'bank_code',
        // Account Details
        'clabe',
        'account_number',
        'card_number_last4',
        'account_type',
        // Account Holder
        'holder_name',
        'holder_rfc',
        'is_own_account',
        // Verification
        'is_verified',
        'verified_at',
        'verification_method',
        'verification_reference',
        // Status
        'is_active',
        'deactivated_at',
        'deactivation_reason',
    ];

    protected $casts = [
        'type' => BankAccountUsageType::class,
        'account_type' => BankAccountType::class,
        'verification_method' => BankVerificationMethod::class,
        'is_primary' => 'boolean',
        'is_own_account' => 'boolean',
        'is_verified' => 'boolean',
        'verified_at' => 'datetime',
        'is_active' => 'boolean',
        'deactivated_at' => 'datetime',
    ];

    /**
     * Hidden attributes for serialization.
     */
    protected $hidden = [
        'clabe',
        'account_number',
    ];

    /**
     * Mexican bank codes (SPEI).
     */
    public const BANKS = [
        '002' => 'BANAMEX',
        '012' => 'BBVA MEXICO',
        '014' => 'SANTANDER',
        '021' => 'HSBC',
        '030' => 'BAJIO',
        '036' => 'INBURSA',
        '042' => 'MIFEL',
        '044' => 'SCOTIABANK',
        '058' => 'BANREGIO',
        '059' => 'INVEX',
        '060' => 'BANSI',
        '062' => 'AFIRME',
        '072' => 'BANORTE',
        '106' => 'BANK OF AMERICA',
        '108' => 'MUFG',
        '110' => 'JP MORGAN',
        '112' => 'BMONEX',
        '113' => 'VE POR MAS',
        '127' => 'AZTECA',
        '128' => 'AUTOFIN',
        '129' => 'BARCLAYS',
        '130' => 'COMPARTAMOS',
        '131' => 'BANCO FAMSA',
        '132' => 'MULTIVA BANCO',
        '133' => 'ACTINVER',
        '134' => 'WAL-MART',
        '135' => 'NAFIN',
        '136' => 'INTERBANCO',
        '137' => 'BANCOPPEL',
        '138' => 'ABC CAPITAL',
        '139' => 'UBS BANK',
        '140' => 'CONSUBANCO',
        '141' => 'VOLKSWAGEN',
        '143' => 'CIBANCO',
        '145' => 'BBASE',
        '147' => 'BANKAOOL',
        '148' => 'PAGATODO',
        '150' => 'INMOBILIARIO',
        '152' => 'BANCREA',
        '156' => 'SABADELL',
        '157' => 'SHINHAN',
        '158' => 'MIZUHO BANK',
        '159' => 'BANK OF CHINA',
        '160' => 'BANCO S3',
        '166' => 'BANSEFI',
        '168' => 'HIPOTECARIA FEDERAL',
        '600' => 'MONEXCB',
        '601' => 'GBM',
        '602' => 'MASARI',
        '605' => 'VALUE',
        '606' => 'ESTRUCTURADORES',
        '607' => 'TIBER',
        '608' => 'VECTOR',
        '610' => 'B&B',
        '614' => 'ACCIVAL',
        '615' => 'MERRILL LYNCH',
        '616' => 'FINAMEX',
        '617' => 'VALMEX',
        '618' => 'UNICA',
        '619' => 'MAPFRE',
        '620' => 'PROFUTURO',
        '621' => 'CB ACTINVER',
        '622' => 'OACTIN',
        '623' => 'SKANDIA',
        '626' => 'CBDEUTSCHE',
        '627' => 'ZURICH',
        '628' => 'ZURICHVI',
        '629' => 'SU CASITA',
        '630' => 'CB INTERCAM',
        '631' => 'CI BOLSA',
        '632' => 'BULLTICK CB',
        '633' => 'STERLING',
        '634' => 'FINCOMUN',
        '636' => 'HDI SEGUROS',
        '637' => 'ORDER',
        '638' => 'AKALA',
        '640' => 'CB JPMORGAN',
        '642' => 'REFORMA',
        '646' => 'STP',
        '647' => 'TELECOMM',
        '648' => 'EVERCORE',
        '649' => 'SKANDIA',
        '651' => 'SEGMTY',
        '652' => 'ASEA',
        '653' => 'KUSPIT',
        '655' => 'UNAGRA',
        '656' => 'SOFIEXPRESS',
        '659' => 'ASP INTEGRA OPC',
        '670' => 'LIBERTAD',
        '901' => 'CLS',
        '902' => 'INDEVAL',
        '903' => 'CoDi Valida',
    ];

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    /**
     * Get the applicant that owns this bank account.
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
     * Get masked CLABE (show only last 4 digits).
     */
    public function getMaskedClabeAttribute(): string
    {
        if (!$this->clabe) {
            return '';
        }
        return str_repeat('*', 14) . substr($this->clabe, -4);
    }

    /**
     * Get bank name from code.
     */
    public function getBankNameFromCodeAttribute(): ?string
    {
        if (!$this->bank_code) {
            return null;
        }
        return self::BANKS[$this->bank_code] ?? null;
    }

    /**
     * Get account type label.
     */
    public function getAccountTypeLabelAttribute(): string
    {
        return $this->account_type?->label() ?? '';
    }

    /**
     * Extract bank code from CLABE.
     */
    public function getBankCodeFromClabeAttribute(): ?string
    {
        if (!$this->clabe || strlen($this->clabe) < 3) {
            return null;
        }
        return substr($this->clabe, 0, 3);
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    /**
     * Scope to get primary accounts.
     */
    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }

    /**
     * Scope to get active accounts.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get verified accounts.
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
     * Scope for disbursement accounts.
     */
    public function scopeForDisbursement($query)
    {
        return $query->whereIn('type', [self::TYPE_DISBURSEMENT, self::TYPE_BOTH]);
    }

    /**
     * Scope for payment accounts.
     */
    public function scopeForPayment($query)
    {
        return $query->whereIn('type', [self::TYPE_PAYMENT, self::TYPE_BOTH]);
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    /**
     * Validate CLABE checksum.
     */
    public static function validateClabe(string $clabe): bool
    {
        if (strlen($clabe) !== 18 || !ctype_digit($clabe)) {
            return false;
        }

        $weights = [3, 7, 1, 3, 7, 1, 3, 7, 1, 3, 7, 1, 3, 7, 1, 3, 7];
        $sum = 0;

        for ($i = 0; $i < 17; $i++) {
            $product = (int)$clabe[$i] * $weights[$i];
            $sum += $product % 10;
        }

        $checkDigit = (10 - ($sum % 10)) % 10;

        return (int)$clabe[17] === $checkDigit;
    }

    /**
     * Get bank name from CLABE.
     */
    public static function getBankFromClabe(string $clabe): ?string
    {
        if (strlen($clabe) < 3) {
            return null;
        }
        $code = substr($clabe, 0, 3);
        return self::BANKS[$code] ?? null;
    }
}
