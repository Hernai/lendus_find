<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * OTP request for authentication.
 *
 * Tracks OTP requests for rate limiting and verification.
 * Can be linked to an identity (existing user) or standalone (new registration).
 *
 * @property string $id
 * @property string|null $identity_id
 * @property string|null $target_type PHONE, EMAIL, WHATSAPP
 * @property string|null $target_value
 * @property string $code
 * @property string $channel SMS, EMAIL, WHATSAPP
 * @property \Carbon\Carbon $expires_at
 * @property \Carbon\Carbon|null $verified_at
 * @property int $attempts
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class OtpRequest extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'otp_requests';

    /**
     * Maximum verification attempts allowed.
     */
    public const MAX_ATTEMPTS = 5;

    /**
     * OTP validity in minutes.
     */
    public const VALIDITY_MINUTES = 10;

    /**
     * Rate limit: max OTPs per hour.
     */
    public const MAX_OTP_PER_HOUR = 5;

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
     * Get the identity this OTP request is for (if existing user).
     */
    public function identity(): BelongsTo
    {
        return $this->belongsTo(ApplicantIdentity::class, 'identity_id');
    }

    // =====================================================
    // Factory Methods
    // =====================================================

    /**
     * Create an OTP request for a target (phone/email).
     */
    public static function createForTarget(
        string $type,
        string $identifier,
        string $channel,
        ?string $identityId = null
    ): self {
        return self::create([
            'identity_id' => $identityId,
            'target_type' => strtoupper($type),
            'target_value' => $identifier,
            'code' => self::generateCode(),
            'channel' => strtoupper($channel),
            'expires_at' => now()->addMinutes(self::VALIDITY_MINUTES),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    /**
     * Generate a 6-digit OTP code.
     */
    public static function generateCode(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    // =====================================================
    // Query Methods
    // =====================================================

    /**
     * Get the latest valid OTP for a target.
     */
    public static function getLatestValidOtp(string $type, string $identifier): ?self
    {
        return self::query()
            ->where('target_type', strtoupper($type))
            ->where('target_value', $identifier)
            ->where('expires_at', '>', now())
            ->whereNull('verified_at')
            ->where('attempts', '<', self::MAX_ATTEMPTS)
            ->latest()
            ->first();
    }

    /**
     * Count OTPs sent in the last hour for a target.
     */
    public static function countRecentOtps(string $type, string $identifier): int
    {
        return self::query()
            ->where('target_type', strtoupper($type))
            ->where('target_value', $identifier)
            ->where('created_at', '>=', now()->subHour())
            ->count();
    }

    // =====================================================
    // Verification Methods
    // =====================================================

    /**
     * Verify the OTP code.
     */
    public function verify(string $code): bool
    {
        // Increment attempts
        $this->increment('attempts');

        // Check if too many attempts
        if ($this->hasTooManyAttempts()) {
            return false;
        }

        // Check if expired
        if ($this->isExpired()) {
            return false;
        }

        // Check code
        if ($this->code !== $code) {
            return false;
        }

        // Mark as verified
        $this->update(['verified_at' => now()]);

        return true;
    }

    /**
     * Check if OTP is expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Check if too many verification attempts.
     */
    public function hasTooManyAttempts(): bool
    {
        return $this->attempts >= self::MAX_ATTEMPTS;
    }

    /**
     * Get remaining verification attempts.
     */
    public function getRemainingAttemptsAttribute(): int
    {
        return max(0, self::MAX_ATTEMPTS - $this->attempts);
    }

    // =====================================================
    // Scopes
    // =====================================================

    /**
     * Scope to valid (non-expired, not verified, under max attempts) OTPs.
     */
    public function scopeValid($query)
    {
        return $query
            ->where('expires_at', '>', now())
            ->whereNull('verified_at')
            ->where('attempts', '<', self::MAX_ATTEMPTS);
    }

    /**
     * Scope to OTPs for a specific target.
     */
    public function scopeForTarget($query, string $type, string $identifier)
    {
        return $query
            ->where('target_type', strtoupper($type))
            ->where('target_value', $identifier);
    }
}
