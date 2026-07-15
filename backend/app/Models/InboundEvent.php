<?php

namespace App\Models;

use App\Traits\HasTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Evento entrante de la cartera externa (dispersión, pago, acuse de ingesta).
 * Único por (tenant_id, external_event_id) → idempotencia.
 */
class InboundEvent extends Model
{
    use HasFactory, HasUuids, HasTenant;

    public const TYPE_DISBURSEMENT = 'disbursement';
    public const TYPE_PAYMENT = 'payment';
    public const TYPE_INGEST_ACK = 'ingest_ack';

    public const STATUS_PROCESSED = 'processed';
    public const STATUS_DUPLICATE = 'duplicate';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'tenant_id',
        'endpoint_id',
        'external_event_id',
        'type',
        'payload',
        'status',
        'result',
        'error',
        'received_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'result' => 'array',
        'received_at' => 'datetime',
    ];
}
