<?php

namespace App\Models;

use App\Traits\HasAuditFields;
use App\Traits\HasTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;

/**
 * Endpoint suscriptor de webhooks de un tenant: URL destino, secreto de firma
 * (encriptado) y eventos suscritos. Ver cambio integracion-webhooks-cartera.
 */
class WebhookEndpoint extends Model
{
    use HasFactory, HasUuids, SoftDeletes, HasTenant, HasAuditFields;

    protected $fillable = [
        'tenant_id',
        'name',
        'url',
        'secret',
        'events',
        'is_active',
        'is_sandbox',
        'description',
        'last_success_at',
        'last_failure_at',
    ];

    protected $casts = [
        'events' => 'array',
        'is_active' => 'boolean',
        'is_sandbox' => 'boolean',
        'last_success_at' => 'datetime',
        'last_failure_at' => 'datetime',
    ];

    // El secreto NUNCA se serializa por defecto (solo se muestra al crear/rotar).
    protected $hidden = ['secret'];

    public function setSecretAttribute($value): void
    {
        $this->attributes['secret'] = $value ? Crypt::encryptString($value) : null;
    }

    public function getSecretAttribute($value): ?string
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class, 'webhook_endpoint_id');
    }

    /** ¿Este endpoint está suscrito al evento? (`*` = todos). */
    public function subscribesTo(string $event): bool
    {
        $events = $this->events ?? [];

        return in_array('*', $events, true) || in_array($event, $events, true);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /** Forma segura para el front (sin el secreto). */
    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'url' => $this->url,
            'events' => $this->events,
            'is_active' => $this->is_active,
            'is_sandbox' => $this->is_sandbox,
            'description' => $this->description,
            'has_secret' => ! empty($this->attributes['secret']),
            'last_success_at' => $this->last_success_at?->toIso8601String(),
            'last_failure_at' => $this->last_failure_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
