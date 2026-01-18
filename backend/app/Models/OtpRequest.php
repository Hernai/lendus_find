<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * OTP request tracking for rate limiting and audit.
 *
 * Tracks all OTP requests for security and rate limiting purposes.
 * Can be linked to an identity or standalone for registration flows.
 */
class OtpRequest extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'identity_id',
        'target_type',
        'target_value',
        'code',
        'channel',
        'expires_at',
        'verified_at',
        'attempts',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    // =====================================================
    // Relationships
    // =====================================================

    /**
     * Get the identity this request belongs to.
     */
    public function identity(): BelongsTo
    {
        return $this->belongsTo(ApplicantIdentity::class, 'identity_id');
    }

    // =====================================================
    // Status Checks
    // =====================================================

    /**
     * Check if OTP is expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Check if OTP is verified.
     */
    public function isVerified(): bool
    {
        return !is_null($this->verified_at);
    }

    /**
     * Check if OTP is still valid (not expired and not verified).
     */
    public function isValid(): bool
    {
        return !$this->isExpired() && !$this->isVerified();
    }

    /**
     * Check if too many attempts.
     */
    public function hasTooManyAttempts(): bool
    {
        return $this->attempts >= 5;
    }

    // =====================================================
    // Verification
    // =====================================================

    /**
     * Verify the OTP code.
     */
    public function verify(string $code): bool
    {
        if ($this->isExpired()) {
            return false;
        }

        if ($this->isVerified()) {
            return false;
        }

        if ($this->hasTooManyAttempts()) {
            return false;
        }

        if ($this->code !== $code) {
            $this->increment('attempts');
            return false;
        }

        $this->update(['verified_at' => now()]);
        return true;
    }

    // =====================================================
    // Accessors
    // =====================================================

    /**
     * Get remaining attempts.
     */
    public function getRemainingAttemptsAttribute(): int
    {
        return max(0, 5 - $this->attempts);
    }

    /**
     * Get seconds until expiry.
     */
    public function getSecondsUntilExpiryAttribute(): int
    {
        if ($this->isExpired()) {
            return 0;
        }
        return (int) now()->diffInSeconds($this->expires_at, false);
    }

    /**
     * Get channel label.
     */
    public function getChannelLabelAttribute(): string
    {
        return match ($this->channel) {
            'SMS' => 'SMS',
            'EMAIL' => 'Correo electrónico',
            'WHATSAPP' => 'WhatsApp',
            default => $this->channel,
        };
    }

    // =====================================================
    // Scopes
    // =====================================================

    /**
     * Scope to valid (non-expired, non-verified) OTPs.
     */
    public function scopeValid($query)
    {
        return $query->where('expires_at', '>', now())
            ->whereNull('verified_at');
    }

    /**
     * Scope to recent OTPs (last hour).
     */
    public function scopeRecent($query)
    {
        return $query->where('created_at', '>=', now()->subHour());
    }

    /**
     * Scope by target.
     */
    public function scopeForTarget($query, string $type, string $value)
    {
        return $query->where('target_type', $type)
            ->where('target_value', $value);
    }

    // =====================================================
    // Static Helpers
    // =====================================================

    /**
     * Count recent OTP requests for rate limiting.
     */
    public static function countRecentRequests(string $type, string $value): int
    {
        return static::forTarget($type, $value)
            ->recent()
            ->count();
    }

    /**
     * Check if can send OTP (rate limit check).
     */
    public static function canSendOtp(string $type, string $value): bool
    {
        return static::countRecentRequests($type, $value) < 3;
    }

    /**
     * Get the latest valid OTP for a target.
     */
    public static function getLatestValidOtp(string $type, string $value): ?self
    {
        return static::forTarget($type, $value)
            ->valid()
            ->latest()
            ->first();
    }

    /**
     * Create a new OTP request.
     */
    public static function createForTarget(
        string $type,
        string $value,
        string $channel,
        ?string $identityId = null
    ): self {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        return static::create([
            'identity_id' => $identityId,
            'target_type' => $type,
            'target_value' => $value,
            'code' => $code,
            'channel' => $channel,
            'expires_at' => now()->addMinutes(10),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
