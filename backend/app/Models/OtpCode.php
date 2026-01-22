<?php

namespace App\Models;

use App\Enums\OtpChannel;
use App\Enums\OtpPurpose;
use App\Traits\HasTenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class OtpCode extends Model
{
    use HasFactory, HasUuid, HasTenant;

    // Enable timestamps but only for created_at
    public $timestamps = true;

    const CREATED_AT = 'created_at';
    const UPDATED_AT = null;

    protected $fillable = [
        'tenant_id',
        'phone',
        'email',
        'code',
        'channel',
        'purpose',
        'is_used',
        'used_at',
        'expires_at',
        'attempts',
        'ip_address',
        'user_agent',
        'provider_message_id',
        'provider_status',
        'sent_at',
    ];

    protected $casts = [
        'channel' => OtpChannel::class,
        'purpose' => OtpPurpose::class,
        'is_used' => 'boolean',
        'used_at' => 'datetime',
        'expires_at' => 'datetime',
        'sent_at' => 'datetime',
    ];

    /**
     * Generate a new OTP code.
     */
    public static function generate(
        string $destination,
        string $channel = 'SMS',
        string $purpose = 'LOGIN',
        ?string $tenantId = null,
        int $expirationMinutes = 10
    ): self {
        // Invalidate previous unused codes (using DB::table to bypass timestamps)
        \DB::table('otp_codes')
            ->where(function ($q) use ($destination) {
                $q->where('phone', $destination)->orWhere('email', $destination);
            })
            ->where('purpose', $purpose)
            ->where('is_used', false)
            ->update(['is_used' => true, 'used_at' => now()]);

        $code = static::generateCode();

        $otp = static::create([
            'tenant_id' => $tenantId,
            'phone' => $channel !== OtpChannel::EMAIL->value ? $destination : null,
            'email' => $channel === OtpChannel::EMAIL->value ? $destination : null,
            'code' => $code,
            'channel' => $channel,
            'purpose' => $purpose,
            'expires_at' => now()->addMinutes($expirationMinutes),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return $otp;
    }

    /**
     * Generate a random code.
     */
    public static function generateCode(int $length = 6): string
    {
        // Use fixed code 123456 if OTP_USE_FIXED is true (for testing)
        if (config('app.otp_use_fixed', false)) {
            return '123456';
        }

        return str_pad((string) random_int(0, pow(10, $length) - 1), $length, '0', STR_PAD_LEFT);
    }

    /**
     * Verify an OTP code.
     */
    public static function verify(
        string $destination,
        string $code,
        string $purpose = 'LOGIN'
    ): ?self {
        $otp = static::where(function ($q) use ($destination) {
                $q->where('phone', $destination)->orWhere('email', $destination);
            })
            ->where('code', $code)
            ->where('purpose', $purpose)
            ->where('is_used', false)
            ->where('expires_at', '>', now())
            ->where('attempts', '<', 5)
            ->first();

        if (!$otp) {
            // Increment attempts on failed verification
            static::where(function ($q) use ($destination) {
                    $q->where('phone', $destination)->orWhere('email', $destination);
                })
                ->where('purpose', $purpose)
                ->where('is_used', false)
                ->increment('attempts');

            return null;
        }

        // Mark as used
        $otp->is_used = true;
        $otp->used_at = now();
        $otp->saveQuietly();

        return $otp;
    }

    /**
     * Check if OTP is expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Check if OTP has too many attempts.
     */
    public function hasTooManyAttempts(): bool
    {
        return $this->attempts >= 5;
    }

    /**
     * Scope to valid OTPs.
     */
    public function scopeValid($query)
    {
        return $query->where('is_used', false)
            ->where('expires_at', '>', now())
            ->where('attempts', '<', 5);
    }

    /**
     * Check if OTP was sent successfully.
     */
    public function wasSent(): bool
    {
        return $this->sent_at !== null;
    }

    /**
     * Mark OTP as sent.
     */
    public function markAsSent(?string $messageId = null, ?string $status = null): self
    {
        $this->sent_at = now();
        $this->provider_message_id = $messageId;
        $this->provider_status = $status ?? 'sent';
        $this->saveQuietly();

        return $this;
    }

    /**
     * Check if OTP can be sent (rate limiting).
     * Limits to 5 OTP requests per hour per destination.
     */
    public static function canSendOtp(string $type, string $destination): bool
    {
        $column = $type === 'EMAIL' ? 'email' : 'phone';

        $recentCount = static::where($column, $destination)
            ->where('created_at', '>', now()->subHour())
            ->count();

        return $recentCount < 5;
    }
}
