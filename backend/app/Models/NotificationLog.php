<?php

namespace App\Models;

use App\Enums\NotificationChannel;
use App\Enums\NotificationEvent;
use App\Traits\HasTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationLog extends Model
{
    use HasTenant, HasUuids;

    protected $fillable = [
        'tenant_id',
        'notification_template_id',
        'recipient_id',
        'recipient_type',
        'channel',
        'event',
        'recipient',
        'status',
        'subject',
        'body',
        'html_body',
        'external_id',
        'error_message',
        'retry_count',
        'sent_at',
        'delivered_at',
        'read_at',
        'failed_at',
        'metadata',
    ];

    protected $casts = [
        'retry_count' => 'integer',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
        'failed_at' => 'datetime',
        'metadata' => 'array',
        'event' => NotificationEvent::class,
        'channel' => NotificationChannel::class,
    ];

    /**
     * Status constants.
     */
    const STATUS_PENDING = 'PENDING';
    const STATUS_SENT = 'SENT';
    const STATUS_DELIVERED = 'DELIVERED';
    const STATUS_FAILED = 'FAILED';
    const STATUS_READ = 'READ';

    /**
     * Get the tenant that owns this log.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the template used for this notification.
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(NotificationTemplate::class, 'notification_template_id');
    }

    /**
     * Get the recipient who received this notification (polymorphic).
     */
    public function recipient()
    {
        if ($this->recipient_type === 'APPLICANT') {
            return $this->belongsTo(ApplicantAccount::class, 'recipient_id');
        }

        return $this->belongsTo(StaffAccount::class, 'recipient_id');
    }

    /**
     * Scope to filter by status.
     */
    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to filter by channel.
     */
    public function scopeForChannel($query, NotificationChannel|string $channel)
    {
        if ($channel instanceof NotificationChannel) {
            $channel = $channel->value;
        }

        return $query->where('channel', $channel);
    }

    /**
     * Scope to filter by event.
     */
    public function scopeForEvent($query, NotificationEvent|string $event)
    {
        if ($event instanceof NotificationEvent) {
            $event = $event->value;
        }

        return $query->where('event', $event);
    }

    /**
     * Scope for failed notifications that can be retried.
     */
    public function scopeRetryable($query, int $maxRetries = 3)
    {
        return $query->where('status', self::STATUS_FAILED)
            ->where('retry_count', '<', $maxRetries);
    }

    /**
     * Mark notification as sent.
     */
    public function markAsSent(?string $externalId = null): void
    {
        $this->update([
            'status' => self::STATUS_SENT,
            'sent_at' => now(),
            'external_id' => $externalId,
        ]);
    }

    /**
     * Mark notification as delivered.
     */
    public function markAsDelivered(): void
    {
        $this->update([
            'status' => self::STATUS_DELIVERED,
            'delivered_at' => now(),
        ]);
    }

    /**
     * Mark notification as read.
     */
    public function markAsRead(): void
    {
        $this->update([
            'status' => self::STATUS_READ,
            'read_at' => now(),
        ]);
    }

    /**
     * Mark notification as failed.
     */
    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'failed_at' => now(),
            'error_message' => $errorMessage,
        ]);
    }

    /**
     * Increment retry count.
     */
    public function incrementRetryCount(): void
    {
        $this->increment('retry_count');
    }
}
