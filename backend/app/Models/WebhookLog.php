<?php

namespace App\Models;

use App\Traits\HasTenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookLog extends Model
{
    use HasUuid, HasTenant;

    /**
     * Table name (using existing webhooks table).
     */
    protected $table = 'webhooks';

    /**
     * Primary key type.
     */
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'tenant_id',
        'event',
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
    ];

    protected $casts = [
        'payload' => 'array',
        'last_attempt_at' => 'datetime',
        'next_retry_at' => 'datetime',
    ];

    /**
     * Status constants.
     */
    public const STATUS_PENDING = 'PENDING';
    public const STATUS_SENT = 'SENT';
    public const STATUS_FAILED = 'FAILED';
    public const STATUS_RETRYING = 'RETRYING';

    /**
     * Get the tenant.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Scope to successful webhooks.
     */
    public function scopeSuccessful($query)
    {
        return $query->where('status', self::STATUS_SENT);
    }

    /**
     * Scope to failed webhooks.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    /**
     * Scope to pending webhooks.
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope to retrying webhooks.
     */
    public function scopeRetrying($query)
    {
        return $query->where('status', self::STATUS_RETRYING)
            ->where(function ($q) {
                $q->whereNull('next_retry_at')
                    ->orWhere('next_retry_at', '<=', now());
            });
    }

    /**
     * Mark as sent.
     */
    public function markAsSent(int $responseCode, ?string $responseBody = null): void
    {
        $this->update([
            'status' => self::STATUS_SENT,
            'response_code' => $responseCode,
            'response_body' => $responseBody,
            'last_attempt_at' => now(),
            'attempts' => $this->attempts + 1,
        ]);
    }

    /**
     * Mark as failed.
     */
    public function markAsFailed(string $error, ?int $responseCode = null): void
    {
        $attempts = $this->attempts + 1;
        $status = $attempts >= $this->max_attempts ? self::STATUS_FAILED : self::STATUS_RETRYING;

        $this->update([
            'status' => $status,
            'error_message' => $error,
            'response_code' => $responseCode,
            'last_attempt_at' => now(),
            'attempts' => $attempts,
            'next_retry_at' => $status === self::STATUS_RETRYING
                ? now()->addMinutes(pow(2, $attempts)) // Exponential backoff
                : null,
        ]);
    }
}
