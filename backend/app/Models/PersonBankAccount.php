<?php

namespace App\Models;

use App\Enums\BankAccountType;
use App\Traits\HasTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

/**
 * Person bank account for disbursement/collection.
 *
 * Bank accounts are used for loan disbursement and payment collection.
 * Supports polymorphic ownership (person or company).
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $owner_type
 * @property string $owner_id
 * @property string $bank_name
 * @property string|null $bank_code
 * @property string $clabe
 * @property string|null $account_number_last4
 * @property string|null $card_number_last4
 * @property string $account_type
 * @property string $currency
 * @property string $holder_name
 * @property string|null $holder_rfc
 * @property bool $is_primary
 * @property bool $is_for_disbursement
 * @property bool $is_for_collection
 * @property bool $is_verified
 * @property \Carbon\Carbon|null $verified_at
 * @property string|null $verified_by
 * @property string|null $verification_method
 * @property array|null $verification_data
 * @property string $status
 * @property string|null $notes
 * @property array|null $metadata
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 * @property string|null $created_by
 * @property string|null $updated_by
 *
 * @property-read string $masked_clabe
 * @property-read string $account_type_label
 * @property-read string $status_label
 *
 * @property-read Tenant $tenant
 * @property-read Person $owner
 * @property-read StaffAccount|null $verifier
 */
class PersonBankAccount extends Model
{
    use HasFactory, HasUuids, SoftDeletes, HasTenant;

    protected $table = 'person_bank_accounts';

    public const STATUS_ACTIVE = 'ACTIVE';
    public const STATUS_INACTIVE = 'INACTIVE';
    public const STATUS_CLOSED = 'CLOSED';
    public const STATUS_FROZEN = 'FROZEN';

    protected $fillable = [
        'tenant_id',
        'owner_type',
        'owner_id',
        'bank_name',
        'bank_code',
        'clabe',
        'account_number_last4',
        'card_number_last4',
        'account_type',
        'currency',
        'holder_name',
        'holder_rfc',
        'is_primary',
        'is_for_disbursement',
        'is_for_collection',
        'is_verified',
        'verified_at',
        'verified_by',
        'verification_method',
        'verification_data',
        'status',
        'notes',
        'metadata',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
            'is_primary' => 'boolean',
            'is_for_disbursement' => 'boolean',
            'is_for_collection' => 'boolean',
            'is_verified' => 'boolean',
            'verification_data' => 'array',
            'metadata' => 'array',
        ];
    }

    // =====================================================
    // Relationships
    // =====================================================

    /**
     * Get the owner of this bank account (polymorphic).
     */
    public function owner(): MorphTo
    {
        return $this->morphTo('owner', 'owner_type', 'owner_id');
    }

    /**
     * Get the person if owner is a person.
     */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'owner_id');
    }

    /**
     * Get the staff who verified this account.
     */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(StaffAccount::class, 'verified_by');
    }

    // =====================================================
    // Accessors
    // =====================================================

    /**
     * Get masked CLABE for display.
     */
    public function getMaskedClabeAttribute(): string
    {
        if (strlen($this->clabe) !== 18) {
            return $this->clabe;
        }

        return substr($this->clabe, 0, 4) . '**********' . substr($this->clabe, -4);
    }

    /**
     * Get account type label.
     */
    public function getAccountTypeLabelAttribute(): string
    {
        $enum = BankAccountType::tryFrom($this->account_type);
        return $enum?->label() ?? $this->account_type;
    }

    /**
     * Get status label.
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_ACTIVE => 'Activa',
            self::STATUS_INACTIVE => 'Inactiva',
            self::STATUS_CLOSED => 'Cerrada',
            self::STATUS_FROZEN => 'Congelada',
            default => $this->status,
        };
    }

    // =====================================================
    // Verification Methods
    // =====================================================

    /**
     * Mark as verified.
     */
    public function markAsVerified(
        string $method,
        ?string $verifiedBy = null,
        ?array $verificationData = null
    ): void {
        $this->update([
            'is_verified' => true,
            'verified_at' => now(),
            'verified_by' => $verifiedBy,
            'verification_method' => $method,
            'verification_data' => $verificationData,
        ]);
    }

    /**
     * Mark as unverified.
     */
    public function markAsUnverified(): void
    {
        $this->update([
            'is_verified' => false,
            'verified_at' => null,
            'verified_by' => null,
            'verification_method' => null,
            'verification_data' => null,
        ]);
    }

    // =====================================================
    // Status Methods
    // =====================================================

    /**
     * Set as primary account.
     *
     * Uses a transaction to ensure atomic update of all accounts.
     */
    public function setAsPrimary(): void
    {
        DB::transaction(function () {
            // Remove primary from other accounts of same owner
            self::where('owner_type', $this->owner_type)
                ->where('owner_id', $this->owner_id)
                ->where('id', '!=', $this->id)
                ->update(['is_primary' => false]);

            $this->update(['is_primary' => true]);
        });
    }

    /**
     * Deactivate account.
     */
    public function deactivate(): void
    {
        $this->update(['status' => self::STATUS_INACTIVE]);
    }

    /**
     * Close account.
     */
    public function close(): void
    {
        $this->update([
            'status' => self::STATUS_CLOSED,
            'is_primary' => false,
        ]);
    }

    /**
     * Freeze account.
     */
    public function freeze(): void
    {
        $this->update(['status' => self::STATUS_FROZEN]);
    }

    /**
     * Reactivate account.
     */
    public function reactivate(): void
    {
        $this->update(['status' => self::STATUS_ACTIVE]);
    }

    // =====================================================
    // Status Checks
    // =====================================================

    /**
     * Check if account is active.
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Check if account can receive disbursement.
     */
    public function canReceiveDisbursement(): bool
    {
        return $this->is_for_disbursement
            && $this->isActive()
            && $this->is_verified;
    }

    /**
     * Check if account can be used for collection.
     */
    public function canBeUsedForCollection(): bool
    {
        return $this->is_for_collection
            && $this->isActive()
            && $this->is_verified;
    }

    // =====================================================
    // CLABE Validation
    // =====================================================

    /**
     * Validate CLABE format and check digit.
     */
    public static function isValidClabe(string $clabe): bool
    {
        // CLABE must be 18 digits
        if (!preg_match('/^\d{18}$/', $clabe)) {
            return false;
        }

        // Validate check digit (algorithm)
        $weights = [3, 7, 1, 3, 7, 1, 3, 7, 1, 3, 7, 1, 3, 7, 1, 3, 7];
        $sum = 0;

        for ($i = 0; $i < 17; $i++) {
            $sum += ((int) $clabe[$i] * $weights[$i]) % 10;
        }

        $checkDigit = (10 - ($sum % 10)) % 10;

        return $checkDigit === (int) $clabe[17];
    }

    /**
     * Extract bank code from CLABE.
     */
    public static function extractBankCode(string $clabe): string
    {
        return substr($clabe, 0, 3);
    }

    // =====================================================
    // Scopes
    // =====================================================

    /**
     * Scope to verified accounts.
     *
     * @param \Illuminate\Database\Eloquent\Builder<PersonBankAccount> $query
     * @return \Illuminate\Database\Eloquent\Builder<PersonBankAccount>
     */
    public function scopeVerified($query)
    {
        return $query->where('is_verified', true);
    }

    /**
     * Scope to primary accounts.
     *
     * @param \Illuminate\Database\Eloquent\Builder<PersonBankAccount> $query
     * @return \Illuminate\Database\Eloquent\Builder<PersonBankAccount>
     */
    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }

    /**
     * Scope to active accounts.
     *
     * @param \Illuminate\Database\Eloquent\Builder<PersonBankAccount> $query
     * @return \Illuminate\Database\Eloquent\Builder<PersonBankAccount>
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Scope to disbursement accounts.
     *
     * @param \Illuminate\Database\Eloquent\Builder<PersonBankAccount> $query
     * @return \Illuminate\Database\Eloquent\Builder<PersonBankAccount>
     */
    public function scopeForDisbursement($query)
    {
        return $query->where('is_for_disbursement', true);
    }

    /**
     * Scope to collection accounts.
     *
     * @param \Illuminate\Database\Eloquent\Builder<PersonBankAccount> $query
     * @return \Illuminate\Database\Eloquent\Builder<PersonBankAccount>
     */
    public function scopeForCollection($query)
    {
        return $query->where('is_for_collection', true);
    }

    /**
     * Scope by owner.
     *
     * @param \Illuminate\Database\Eloquent\Builder<PersonBankAccount> $query
     * @param string $ownerType
     * @param string $ownerId
     * @return \Illuminate\Database\Eloquent\Builder<PersonBankAccount>
     */
    public function scopeForOwner($query, string $ownerType, string $ownerId)
    {
        return $query->where('owner_type', $ownerType)
            ->where('owner_id', $ownerId);
    }

    // =====================================================
    // Static Finders
    // =====================================================

    /**
     * Find primary account for a person.
     */
    public static function findPrimaryForPerson(string $personId): ?self
    {
        return self::where('owner_type', 'persons')
            ->where('owner_id', $personId)
            ->where('is_primary', true)
            ->first();
    }

    /**
     * Find by CLABE.
     */
    public static function findByClabe(string $clabe, string $tenantId): ?self
    {
        return self::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->where('clabe', $clabe)
            ->first();
    }

    /**
     * Get all accounts for a person.
     */
    public static function getForPerson(string $personId): \Illuminate\Database\Eloquent\Collection
    {
        return self::where('owner_type', 'persons')
            ->where('owner_id', $personId)
            ->orderBy('is_primary', 'desc')
            ->get();
    }
}
