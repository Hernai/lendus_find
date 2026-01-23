<?php

namespace App\Models;

use App\Enums\NotificationChannel;
use App\Enums\NotificationEvent;
use App\Traits\HasTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationPreference extends Model
{
    use HasTenant, HasUuids;

    protected $fillable = [
        'tenant_id',
        'recipient_id',
        'recipient_type',
        'sms_enabled',
        'whatsapp_enabled',
        'email_enabled',
        'in_app_enabled',
        'disabled_events',
    ];

    protected $casts = [
        'sms_enabled' => 'boolean',
        'whatsapp_enabled' => 'boolean',
        'email_enabled' => 'boolean',
        'in_app_enabled' => 'boolean',
        'disabled_events' => 'array',
    ];

    /**
     * Get the tenant that owns this preference.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the recipient who owns this preference (polymorphic).
     */
    public function recipient()
    {
        if ($this->recipient_type === 'APPLICANT') {
            return $this->belongsTo(ApplicantAccount::class, 'recipient_id');
        }

        return $this->belongsTo(StaffAccount::class, 'recipient_id');
    }

    /**
     * Check if a specific channel is enabled.
     */
    public function isChannelEnabled(NotificationChannel|string $channel): bool
    {
        if ($channel instanceof NotificationChannel) {
            $channel = $channel->value;
        }

        return match ($channel) {
            'SMS' => $this->sms_enabled,
            'WHATSAPP' => $this->whatsapp_enabled,
            'EMAIL' => $this->email_enabled,
            'IN_APP' => $this->in_app_enabled,
            default => false,
        };
    }

    /**
     * Check if a specific event is enabled.
     */
    public function isEventEnabled(NotificationEvent|string $event): bool
    {
        if ($event instanceof NotificationEvent) {
            $event = $event->value;
        }

        $disabledEvents = $this->disabled_events ?? [];

        return ! in_array($event, $disabledEvents);
    }

    /**
     * Check if user should receive notification for this event and channel.
     */
    public function shouldReceive(NotificationEvent|string $event, NotificationChannel|string $channel): bool
    {
        return $this->isChannelEnabled($channel) && $this->isEventEnabled($event);
    }

    /**
     * Disable a specific event.
     */
    public function disableEvent(NotificationEvent|string $event): void
    {
        if ($event instanceof NotificationEvent) {
            $event = $event->value;
        }

        $disabledEvents = $this->disabled_events ?? [];

        if (! in_array($event, $disabledEvents)) {
            $disabledEvents[] = $event;
            $this->update(['disabled_events' => $disabledEvents]);
        }
    }

    /**
     * Enable a specific event.
     */
    public function enableEvent(NotificationEvent|string $event): void
    {
        if ($event instanceof NotificationEvent) {
            $event = $event->value;
        }

        $disabledEvents = $this->disabled_events ?? [];
        $disabledEvents = array_filter($disabledEvents, fn ($e) => $e !== $event);

        $this->update(['disabled_events' => array_values($disabledEvents)]);
    }
}
