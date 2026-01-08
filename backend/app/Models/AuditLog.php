<?php

namespace App\Models;

use App\Enums\AuditAction;
use App\Traits\HasTenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;

class AuditLog extends Model
{
    use HasUuid, HasTenant;

    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'applicant_id',
        'application_id',
        'action',
        'entity_type',
        'entity_id',
        'old_values',
        'new_values',
        'metadata',
        'ip_address',
        'user_agent',
        'latitude',
        'longitude',
        'city',
        'region',
        'country',
        'device_type',
        'browser',
        'browser_version',
        'os',
        'os_version',
        'created_at',
    ];

    protected $casts = [
        'action' => AuditAction::class,
        'old_values' => 'array',
        'new_values' => 'array',
        'metadata' => 'array',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'created_at' => 'datetime',
    ];

    /**
     * Static method to easily log an action.
     */
    public static function log(
        string $action,
        ?string $tenantId = null,
        array $options = []
    ): self {
        $request = request();

        $data = [
            'tenant_id' => $tenantId ?? $request->attributes->get('tenant')?->id,
            'action' => $action,
            'user_id' => $options['user_id'] ?? $request->user()?->id,
            'applicant_id' => $options['applicant_id'] ?? null,
            'application_id' => $options['application_id'] ?? null,
            'entity_type' => $options['entity_type'] ?? null,
            'entity_id' => $options['entity_id'] ?? null,
            'old_values' => $options['old_values'] ?? null,
            'new_values' => $options['new_values'] ?? null,
            'metadata' => $options['metadata'] ?? null,
            'ip_address' => $options['ip_address'] ?? $request->ip(),
            'user_agent' => $options['user_agent'] ?? $request->userAgent(),
            'created_at' => now(),
        ];

        // Merge device info if provided
        if (isset($options['device_info'])) {
            $data = array_merge($data, $options['device_info']);
        }

        // Merge geolocation if provided
        if (isset($options['geolocation'])) {
            $data = array_merge($data, $options['geolocation']);
        }

        return static::create($data);
    }

    /**
     * Log with full metadata from MetadataService.
     */
    public static function logWithMetadata(
        string $action,
        array $metadata,
        array $options = []
    ): self {
        $options['ip_address'] = $metadata['ip_address'] ?? null;
        $options['user_agent'] = $metadata['user_agent'] ?? null;
        $options['device_info'] = $metadata['device_info'] ?? [];
        $options['geolocation'] = $metadata['geolocation'] ?? [];

        return static::log($action, $metadata['tenant_id'] ?? null, $options);
    }

    /**
     * Get the user who performed the action.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the applicant affected.
     */
    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Applicant::class);
    }

    /**
     * Get the application affected.
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    /**
     * Scope by action type.
     */
    public function scopeByAction($query, string $action)
    {
        return $query->where('action', $action);
    }

    /**
     * Scope by user.
     */
    public function scopeByUser($query, string $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope by applicant.
     */
    public function scopeByApplicant($query, string $applicantId)
    {
        return $query->where('applicant_id', $applicantId);
    }

    /**
     * Scope by application.
     */
    public function scopeByApplication($query, string $applicationId)
    {
        return $query->where('application_id', $applicationId);
    }

    /**
     * Scope by date range.
     */
    public function scopeByDateRange($query, $startDate, $endDate = null)
    {
        $query->where('created_at', '>=', $startDate);

        if ($endDate) {
            $query->where('created_at', '<=', $endDate);
        }

        return $query;
    }

    /**
     * Scope for authentication events.
     */
    public function scopeAuthEvents($query)
    {
        return $query->whereIn('action', [
            AuditAction::OTP_REQUESTED->value,
            AuditAction::OTP_VERIFIED->value,
            AuditAction::LOGIN_SUCCESS->value,
            AuditAction::LOGIN_FAILED->value,
            AuditAction::LOGOUT->value,
            AuditAction::PIN_SET->value,
        ]);
    }

    /**
     * Get formatted device info.
     */
    public function getDeviceInfoAttribute(): string
    {
        $parts = array_filter([
            $this->device_type,
            $this->browser,
            $this->os,
        ]);

        return implode(' / ', $parts) ?: 'Unknown';
    }

    /**
     * Get formatted location.
     */
    public function getLocationAttribute(): ?string
    {
        $parts = array_filter([
            $this->city,
            $this->region,
            $this->country,
        ]);

        return $parts ? implode(', ', $parts) : null;
    }

    /**
     * Get human-readable action description.
     */
    public function getActionDescriptionAttribute(): string
    {
        return $this->action?->label() ?? (string) $this->action;
    }
}
