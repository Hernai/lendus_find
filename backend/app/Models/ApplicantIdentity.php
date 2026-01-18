<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Applicant identity for multi-channel authentication.
 *
 * An applicant can have multiple identities (phone, email, WhatsApp)
 * linked to a single account. Each identity can be used for OTP authentication.
 */
class ApplicantIdentity extends Model
{
    use HasFactory, HasUuids;

    // =====================================================
    // Identity Type Constants
    // =====================================================

    public const TYPE_PHONE = 'PHONE';
    public const TYPE_EMAIL = 'EMAIL';
    public const TYPE_WHATSAPP = 'WHATSAPP';

    public const TYPES = [
        self::TYPE_PHONE,
        self::TYPE_EMAIL,
        self::TYPE_WHATSAPP,
    ];

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

    /**
     * Get OTP requests for this identity.
     */
    public function otpRequests(): HasMany
    {
        return $this->hasMany(OtpRequest::class, 'identity_id');
    }

    // =====================================================
    // Type Checks
    // =====================================================

    /**
     * Check if identity is phone type.
     */
    public function isPhone(): bool
    {
        return $this->type === self::TYPE_PHONE;
    }

    /**
     * Check if identity is email type.
     */
    public function isEmail(): bool
    {
        return $this->type === self::TYPE_EMAIL;
    }

    /**
     * Check if identity is WhatsApp type.
     */
    public function isWhatsApp(): bool
    {
        return $this->type === self::TYPE_WHATSAPP;
    }

    // =====================================================
    // Verification
    // =====================================================

    /**
     * Check if identity is verified.
     */
    public function isVerified(): bool
    {
        return !is_null($this->verified_at);
    }

    /**
     * Generate OTP code.
     */
    public function generateOtp(): string
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $this->update([
            'verification_code' => $code,
            'verification_code_expires_at' => now()->addMinutes(10),
            'verification_attempts' => 0,
        ]);

        return $code;
    }

    /**
     * Verify OTP code.
     */
    public function verifyOtp(string $code): bool
    {
        // Check if code matches
        if ($this->verification_code !== $code) {
            $this->increment('verification_attempts');
            return false;
        }

        // Check if code is expired
        if (!$this->verification_code_expires_at || $this->verification_code_expires_at->isPast()) {
            return false;
        }

        // Mark as verified
        $this->update([
            'verified_at' => now(),
            'verification_code' => null,
            'verification_code_expires_at' => null,
            'verification_attempts' => 0,
            'last_used_at' => now(),
        ]);

        return true;
    }

    /**
     * Check if can request OTP (rate limiting).
     */
    public function canRequestOtp(): bool
    {
        // Max 3 OTPs per hour
        $recentRequests = $this->otpRequests()
            ->where('created_at', '>=', now()->subHour())
            ->count();

        return $recentRequests < 3;
    }

    /**
     * Get remaining OTP attempts before rate limit.
     */
    public function getRemainingOtpRequestsAttribute(): int
    {
        $recentRequests = $this->otpRequests()
            ->where('created_at', '>=', now()->subHour())
            ->count();

        return max(0, 3 - $recentRequests);
    }

    /**
     * Check if OTP verification has too many attempts.
     */
    public function hasTooManyVerificationAttempts(): bool
    {
        return $this->verification_attempts >= 5;
    }

    /**
     * Get remaining verification attempts.
     */
    public function getRemainingVerificationAttemptsAttribute(): int
    {
        return max(0, 5 - $this->verification_attempts);
    }

    // =====================================================
    // Display Helpers
    // =====================================================

    /**
     * Get masked identifier for display.
     */
    public function getMaskedIdentifierAttribute(): string
    {
        if ($this->isEmail()) {
            $parts = explode('@', $this->identifier);
            if (count($parts) !== 2) {
                return '***';
            }
            $local = $parts[0];
            $domain = $parts[1];
            $maskedLocal = substr($local, 0, min(2, strlen($local))) . '***';
            return $maskedLocal . '@' . $domain;
        }

        // Phone number
        $phone = $this->identifier;
        if (strlen($phone) < 6) {
            return '***';
        }
        return substr($phone, 0, 4) . '****' . substr($phone, -2);
    }

    /**
     * Get type label.
     */
    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            self::TYPE_PHONE => 'Teléfono',
            self::TYPE_EMAIL => 'Correo electrónico',
            self::TYPE_WHATSAPP => 'WhatsApp',
            default => $this->type,
        };
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
     * Scope to primary identity.
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
        return $query->where('type', $type);
    }

    /**
     * Find identity by type and identifier within a tenant.
     */
    public static function findByIdentifier(string $type, string $identifier, string $tenantId): ?self
    {
        return static::whereHas('account', function ($query) use ($tenantId) {
            $query->where('tenant_id', $tenantId);
        })
            ->where('type', $type)
            ->where('identifier', $identifier)
            ->first();
    }
}
