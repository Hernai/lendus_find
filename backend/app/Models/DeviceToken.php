<?php

namespace App\Models;

use App\Traits\HasAuditFields;
use App\Traits\HasTenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Token de dispositivo para push notifications.
 *
 * El owner es polimórfico: puede apuntar a `ApplicantAccount` o
 * `StaffAccount` según quién registró el dispositivo.
 */
class DeviceToken extends Model
{
    use HasUuid, HasTenant, HasAuditFields;

    public const PROVIDER_FCM = 'fcm';
    public const PROVIDER_APNS = 'apns';
    public const PROVIDER_WEBPUSH = 'webpush';

    public const PLATFORM_IOS = 'ios';
    public const PLATFORM_ANDROID = 'android';
    public const PLATFORM_WEB = 'web';

    protected $fillable = [
        'tenant_id',
        'owner_type',
        'owner_id',
        'provider',
        'token',
        'platform',
        'app_version',
        'device_id',
        'last_seen_at',
        'revoked_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeActive($query)
    {
        return $query->whereNull('revoked_at');
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null;
    }

    public function revoke(): void
    {
        $this->forceFill(['revoked_at' => now()])->save();
    }
}
