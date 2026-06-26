<?php

namespace App\Models;

use App\Traits\HasTenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;

/**
 * Validación asíncrona de Nubarium (por webhook).
 *
 * Representa una validación que Nubarium resuelve de forma diferida: se inicia
 * con una llamada (que regresa `validation_code`) y el resultado llega después
 * a nuestra URL de callback. Ver NubariumComplianceService::startAsyncValidation
 * y NubariumWebhookController.
 */
class NubariumAsyncValidation extends Model
{
    use HasUuid, HasTenant;

    public const TYPE_CLABE = 'clabe';
    public const TYPE_DEBIT_CARD = 'debit_card';
    public const TYPE_IMSS_NSS = 'imss_nss';
    public const TYPE_IMSS_EMPLOYMENT = 'imss_employment';
    public const TYPE_ISSSTE = 'issste';

    public const STATUS_PENDING = 'pending';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'tenant_id',
        'type',
        'callback_token',
        'validation_code',
        'status',
        'request_payload',
        'result',
        'error',
        'entity_type',
        'entity_id',
        'received_at',
        'created_by',
    ];

    protected $casts = [
        'request_payload' => 'array',
        'result' => 'array',
        'received_at' => 'datetime',
    ];

    // Nunca exponer el secreto del callback en respuestas API.
    protected $hidden = [
        'callback_token',
    ];

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Forma segura para el front (sin el token de callback).
     *
     * @return array<string, mixed>
     */
    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'status' => $this->status,
            'validation_code' => $this->validation_code,
            'result' => $this->result,
            'error' => $this->error,
            'received_at' => $this->received_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
