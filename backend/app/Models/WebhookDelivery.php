<?php

namespace App\Models;

use App\Traits\HasTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Log de entregas de webhook (sobre la tabla `webhooks`): un intento de entrega
 * de un evento a un endpoint, con su estado y reintentos.
 */
class WebhookDelivery extends Model
{
    use HasFactory, HasUuids, HasTenant;

    protected $table = 'webhooks';

    public const STATUS_PENDING = 'PENDING';
    public const STATUS_SENT = 'SENT';
    public const STATUS_FAILED = 'FAILED';
    public const STATUS_RETRYING = 'RETRYING';

    protected $fillable = [
        'tenant_id',
        'webhook_endpoint_id',
        'event',
        'event_id',
        'idempotency_key',
        'model_type',
        'model_id',
        'payload',
        'url',
        'status',
        'attempts',
        'max_attempts',
        'last_attempt_at',
        'next_retry_at',
        'response_code',
        'response_body',
        'error_message',
        'signature',
    ];

    protected $casts = [
        'payload' => 'array',
        'attempts' => 'integer',
        'max_attempts' => 'integer',
        'response_code' => 'integer',
        'last_attempt_at' => 'datetime',
        'next_retry_at' => 'datetime',
    ];

    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(WebhookEndpoint::class, 'webhook_endpoint_id');
    }

    public function isDeliverable(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_RETRYING], true);
    }

    /** @return array<string, mixed> */
    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'webhook_endpoint_id' => $this->webhook_endpoint_id,
            'event' => $this->event,
            'event_id' => $this->event_id,
            'status' => $this->status,
            'attempts' => $this->attempts,
            'max_attempts' => $this->max_attempts,
            'response_code' => $this->response_code,
            'response_body' => $this->response_body ? mb_substr($this->response_body, 0, 500) : null,
            'error_message' => $this->error_message,
            'last_attempt_at' => $this->last_attempt_at?->toIso8601String(),
            'next_retry_at' => $this->next_retry_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
