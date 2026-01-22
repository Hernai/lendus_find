<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmsLog extends Model
{
    use HasUuid;

    // Enable timestamps but only for created_at
    public $timestamps = true;

    const CREATED_AT = 'created_at';
    const UPDATED_AT = null;

    protected $fillable = [
        'tenant_id',
        'to',
        'from',
        'body',
        'type',
        'direction',
        'sid',
        'status',
        'num_segments',
        'price',
        'price_unit',
        'error_code',
        'error_message',
        'metadata',
        'sent_at',
        'delivered_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'num_segments' => 'integer',
        'error_code' => 'integer',
        'price' => 'decimal:4',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    /**
     * Get the tenant.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Check if message was successfully sent.
     */
    public function isSuccessful(): bool
    {
        return in_array($this->status, ['sent', 'delivered']);
    }

    /**
     * Check if message failed.
     */
    public function isFailed(): bool
    {
        return in_array($this->status, ['failed', 'undelivered']);
    }

    /**
     * Check if message is pending.
     */
    public function isPending(): bool
    {
        return in_array($this->status, ['queued', 'sending']);
    }

    /**
     * Mark as delivered.
     */
    public function markAsDelivered(): void
    {
        $this->status = 'delivered';
        $this->delivered_at = now();
        $this->saveQuietly();
    }

    /**
     * Scope to filter by tenant.
     */
    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Scope to filter by type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope to filter by status.
     */
    public function scopeWithStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to get recent logs.
     */
    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Get formatted phone number (without whatsapp: prefix).
     */
    public function getFormattedToAttribute(): string
    {
        return str_replace('whatsapp:', '', $this->to);
    }

    /**
     * Get formatted from number (without whatsapp: prefix).
     */
    public function getFormattedFromAttribute(): string
    {
        return str_replace('whatsapp:', '', $this->from);
    }
}
