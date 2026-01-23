<?php

namespace App\Models;

use App\Enums\NotificationChannel;
use App\Enums\NotificationEvent;
use App\Traits\HasTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class NotificationTemplate extends Model
{
    use HasTenant, HasUuids, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'name',
        'event',
        'channel',
        'is_active',
        'priority',
        'subject',
        'body',
        'html_body',
        'available_variables',
        'metadata',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'priority' => 'integer',
        'available_variables' => 'array',
        'metadata' => 'array',
        'event' => NotificationEvent::class,
        'channel' => NotificationChannel::class,
    ];

    /**
     * Get the tenant that owns this template.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the user who created this template.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(StaffAccount::class, 'created_by');
    }

    /**
     * Get the user who last updated this template.
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(StaffAccount::class, 'updated_by');
    }

    /**
     * Get notification logs for this template.
     */
    public function logs(): HasMany
    {
        return $this->hasMany(NotificationLog::class);
    }

    /**
     * Scope to only active templates.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
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
     * Get the default available variables for this template's event.
     */
    public function getDefaultAvailableVariables(): array
    {
        if (! $this->event instanceof NotificationEvent) {
            return [];
        }

        return $this->event->getAvailableVariables();
    }

    /**
     * Check if this template supports HTML content.
     */
    public function supportsHtml(): bool
    {
        return $this->channel instanceof NotificationChannel
            && $this->channel->supportsHtml();
    }

    /**
     * Get character limit for this channel.
     */
    public function getCharacterLimit(): ?int
    {
        return $this->channel instanceof NotificationChannel
            ? $this->channel->characterLimit()
            : null;
    }
}
