<?php

namespace App\Models;

use App\Traits\HasAuditFields;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Applicant identity for multi-identity authentication.
 *
 * Supports multiple login identifiers (phone, email, WhatsApp) linked to a single account.
 * Each identity can be verified independently.
 *
 * @property string $id
 * @property string $account_id
 * @property string $type PHONE, EMAIL, WHATSAPP
 * @property string $identifier The actual phone/email/whatsapp number
 * @property \Carbon\Carbon|null $verified_at
 * @property string|null $verification_code
 * @property \Carbon\Carbon|null $verification_code_expires_at
 * @property int $verification_attempts
 * @property bool $is_primary
 * @property \Carbon\Carbon|null $last_used_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class ApplicantIdentity extends Model
{
    use HasFactory, HasUuids, HasAuditFields;

    protected $table = 'applicant_identities';

    protected $fillable = [
        'account_id',
        'type',
        'identifier',
        'verified_at',
        'verification_code',
        'verification_code_expires_at',
        'verification_attempts',
        'is_primary',
        'last_used_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
            'verification_code_expires_at' => 'datetime',
            'last_used_at' => 'datetime',
            'is_primary' => 'boolean',
        ];
    }

    // =====================================================
    // Relationships
    // =====================================================

    /**
     * Get the account this identity belongs to.
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(ApplicantAccount::class, 'account_id');
    }

    // =====================================================
    // Query Methods
    // =====================================================

    /**
     * Find an identity by type and identifier for a tenant.
     */
    public static function findByIdentifier(string $type, string $identifier, string $tenantId): ?self
    {
        return static::query()
            ->whereHas('account', function ($query) use ($tenantId) {
                $query->where('tenant_id', $tenantId);
            })
            ->where('type', strtoupper($type))
            ->where('identifier', $identifier)
            ->first();
    }

    /**
     * Find an identity by type and identifier (global).
     */
    public static function findByIdentifierGlobal(string $type, string $identifier): ?self
    {
        return static::query()
            ->where('type', strtoupper($type))
            ->where('identifier', $identifier)
            ->first();
    }

    // =====================================================
    // Status Methods
    // =====================================================

    /**
     * Check if identity is verified.
     */
    public function isVerified(): bool
    {
        return !is_null($this->verified_at);
    }

    /**
     * Mark identity as verified.
     */
    public function markAsVerified(): void
    {
        $this->update([
            'verified_at' => now(),
            'verification_code' => null,
            'verification_code_expires_at' => null,
            'verification_attempts' => 0,
        ]);
    }

    /**
     * Update last used timestamp.
     */
    public function touchLastUsed(): void
    {
        $this->update(['last_used_at' => now()]);
    }

    // =====================================================
    // Scopes
    // =====================================================

    /**
     * Scope to verified identities.
     */
    public function scopeVerified($query)
    {
        return $query->whereNotNull('verified_at');
    }

    /**
     * Scope to primary identities.
     */
    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }

    /**
     * Scope by type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', strtoupper($type));
    }
}
