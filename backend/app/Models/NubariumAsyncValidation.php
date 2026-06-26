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
     * Resumen normalizado del resultado de una validación de CLABE / tarjeta de
     * débito: estado, mensaje, similitud y el titular REAL que el banco reporta.
     *
     * Contra qué compara Nubarium: hace un micro-depósito SPEI a la CLABE/tarjeta
     * y lee el nombre REAL del titular de la cuenta en el banco
     * (`data.spei.beneficiary.name`); lo coteja contra el `name` que enviamos
     * (el `holder_name` de la cuenta) y devuelve un `similarity` (0–1) como score
     * de coincidencia. messageCode 0 = coincide; 3 = "Match not found".
     *
     * Lo consume el webhook (para persistir el resultado en la cuenta) y la UI.
     *
     * @return array<string, mixed>|null  null si el tipo no es CLABE/tarjeta.
     */
    public function clabeSummary(): ?array
    {
        if (!in_array($this->type, [self::TYPE_CLABE, self::TYPE_DEBIT_CARD], true)) {
            return null;
        }

        $body = $this->result ?? [];
        $data = $body['data'] ?? [];
        $beneficiary = $data['spei']['beneficiary'] ?? [];
        $similarity = $data['similarity'] ?? null;

        return [
            'status' => $this->status,
            'message_code' => $body['messageCode'] ?? $body['claveMensaje'] ?? null,
            'message' => $body['message'] ?? $body['mensaje'] ?? null,
            'similarity' => is_numeric($similarity) ? (float) $similarity : null,
            // Titular real reportado por el banco vía el micro-depósito SPEI.
            'holder_name_real' => $beneficiary['name'] ?? null,
            'bank' => $beneficiary['receivingBank'] ?? null,
            'validation_code' => $this->validation_code,
            'validation_id' => $this->id,
            'validated_at' => $this->received_at?->toIso8601String(),
        ];
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
