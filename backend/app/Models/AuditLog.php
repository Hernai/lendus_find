<?php

namespace App\Models;

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
        'old_values' => 'array',
        'new_values' => 'array',
        'metadata' => 'array',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'created_at' => 'datetime',
    ];

    /**
     * Action constants.
     */
    // Authentication
    public const ACTION_OTP_REQUESTED = 'OTP_REQUESTED';
    public const ACTION_OTP_VERIFIED = 'OTP_VERIFIED';
    public const ACTION_LOGIN_SUCCESS = 'LOGIN_SUCCESS';
    public const ACTION_LOGIN_FAILED = 'LOGIN_FAILED';
    public const ACTION_LOGOUT = 'LOGOUT';
    public const ACTION_PIN_SET = 'PIN_SET';

    // User/Applicant
    public const ACTION_USER_CREATED = 'USER_CREATED';
    public const ACTION_APPLICANT_CREATED = 'APPLICANT_CREATED';
    public const ACTION_APPLICANT_UPDATED = 'APPLICANT_UPDATED';

    // Application
    public const ACTION_APPLICATION_CREATED = 'APPLICATION_CREATED';
    public const ACTION_APPLICATION_UPDATED = 'APPLICATION_UPDATED';
    public const ACTION_APPLICATION_SUBMITTED = 'APPLICATION_SUBMITTED';
    public const ACTION_STATUS_CHANGED = 'STATUS_CHANGED';

    // Documents
    public const ACTION_DOCUMENT_UPLOADED = 'DOCUMENT_UPLOADED';
    public const ACTION_DOCUMENT_DELETED = 'DOCUMENT_DELETED';
    public const ACTION_DOCUMENT_APPROVED = 'DOCUMENT_APPROVED';
    public const ACTION_DOCUMENT_REJECTED = 'DOCUMENT_REJECTED';

    // Verification
    public const ACTION_DATA_VERIFIED = 'DATA_VERIFIED';
    public const ACTION_DATA_REJECTED = 'DATA_REJECTED';
    public const ACTION_DATA_CORRECTED = 'DATA_CORRECTED';
    public const ACTION_REFERENCE_VERIFIED = 'REFERENCE_VERIFIED';

    // Admin
    public const ACTION_NOTE_ADDED = 'NOTE_ADDED';
    public const ACTION_ASSIGNED = 'ASSIGNED';

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
            self::ACTION_OTP_REQUESTED,
            self::ACTION_OTP_VERIFIED,
            self::ACTION_LOGIN_SUCCESS,
            self::ACTION_LOGIN_FAILED,
            self::ACTION_LOGOUT,
            self::ACTION_PIN_SET,
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
        return match ($this->action) {
            self::ACTION_OTP_REQUESTED => 'Solicitó código OTP',
            self::ACTION_OTP_VERIFIED => 'Verificó código OTP',
            self::ACTION_LOGIN_SUCCESS => 'Inició sesión exitosamente',
            self::ACTION_LOGIN_FAILED => 'Intento de inicio de sesión fallido',
            self::ACTION_LOGOUT => 'Cerró sesión',
            self::ACTION_PIN_SET => 'Configuró PIN de acceso',
            self::ACTION_USER_CREATED => 'Usuario creado',
            self::ACTION_APPLICANT_CREATED => 'Solicitante creado',
            self::ACTION_APPLICANT_UPDATED => 'Datos del solicitante actualizados',
            self::ACTION_APPLICATION_CREATED => 'Solicitud creada',
            self::ACTION_APPLICATION_UPDATED => 'Solicitud actualizada',
            self::ACTION_APPLICATION_SUBMITTED => 'Solicitud enviada',
            self::ACTION_STATUS_CHANGED => 'Estado de solicitud cambiado',
            self::ACTION_DOCUMENT_UPLOADED => 'Documento subido',
            self::ACTION_DOCUMENT_DELETED => 'Documento eliminado',
            self::ACTION_DOCUMENT_APPROVED => 'Documento aprobado',
            self::ACTION_DOCUMENT_REJECTED => 'Documento rechazado',
            self::ACTION_DATA_VERIFIED => 'Dato verificado',
            self::ACTION_DATA_REJECTED => 'Dato rechazado',
            self::ACTION_DATA_CORRECTED => 'Dato corregido',
            self::ACTION_REFERENCE_VERIFIED => 'Referencia verificada',
            self::ACTION_NOTE_ADDED => 'Nota agregada',
            self::ACTION_ASSIGNED => 'Solicitud asignada',
            default => $this->action,
        };
    }
}
