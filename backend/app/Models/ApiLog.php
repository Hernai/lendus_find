<?php

namespace App\Models;

use App\Traits\HasTenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiLog extends Model
{
    use HasUuid, HasTenant;

    protected $fillable = [
        'tenant_id',
        'applicant_id',
        'entity_type',
        'entity_id',
        'application_id',
        'user_id',
        'provider',
        'service',
        'endpoint',
        'method',
        'request_headers',
        'request_payload',
        'response_status',
        'response_headers',
        'response_body',
        'success',
        'error_code',
        'error_message',
        'duration_ms',
        'cost',
        'metadata',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'request_headers' => 'array',
        'request_payload' => 'array',
        'response_headers' => 'array',
        'response_body' => 'array',
        'metadata' => 'array',
        'success' => 'boolean',
        'duration_ms' => 'integer',
        'cost' => 'decimal:4',
    ];

    /**
     * Known API providers.
     */
    public const PROVIDER_NUBARIUM = 'NUBARIUM';
    public const PROVIDER_TWILIO = 'TWILIO';
    public const PROVIDER_RENAPO = 'RENAPO';
    public const PROVIDER_SAT = 'SAT';
    public const PROVIDER_INE = 'INE';
    public const PROVIDER_SEPOMEX = 'SEPOMEX';

    /**
     * Get the applicant associated with this log (legacy).
     */
    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Applicant::class);
    }

    /**
     * Get the entity (Person or Company) - polymorphic relationship.
     */
    public function entity(): \Illuminate\Database\Eloquent\Relations\MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the application associated with this log.
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    /**
     * Scope to filter by entity (Person or Company).
     */
    public function scopeForEntity($query, $entity)
    {
        return $query->where('entity_type', get_class($entity))
            ->where('entity_id', $entity->id);
    }

    /**
     * Get the user associated with this log.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Mask sensitive data in payload/response for storage.
     */
    public static function maskSensitiveData(array $data, array $sensitiveKeys = []): array
    {
        $defaultSensitiveKeys = [
            'password',
            'token',
            'api_key',
            'apikey',
            'secret',
            'authorization',
            'bearer',
            'access_token',
            'refresh_token',
            'credit_card',
            'cvv',
            'ssn',
            'nss', // Mexican social security
        ];

        $keysToMask = array_merge($defaultSensitiveKeys, $sensitiveKeys);

        array_walk_recursive($data, function (&$value, $key) use ($keysToMask) {
            foreach ($keysToMask as $sensitiveKey) {
                if (stripos($key, $sensitiveKey) !== false && is_string($value)) {
                    $value = '***MASKED***';
                    break;
                }
            }
        });

        return $data;
    }

    /**
     * Scope to filter by provider.
     */
    public function scopeProvider($query, string $provider)
    {
        return $query->where('provider', $provider);
    }

    /**
     * Scope to filter by service.
     */
    public function scopeService($query, string $service)
    {
        return $query->where('service', $service);
    }

    /**
     * Scope to filter successful calls.
     */
    public function scopeSuccessful($query)
    {
        return $query->where('success', true);
    }

    /**
     * Scope to filter failed calls.
     */
    public function scopeFailed($query)
    {
        return $query->where('success', false);
    }

    /**
     * Scope to filter by date range.
     */
    public function scopeDateRange($query, $from, $to)
    {
        return $query->whereBetween('created_at', [$from, $to]);
    }
}
