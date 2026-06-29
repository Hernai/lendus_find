<?php

namespace App\Models;

use App\Traits\HasTenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;

/**
 * Evaluación de riesgo de un solicitante (phone_risk / email_risk de Nubarium;
 * a futuro PLD / Buró / Círculo). Ver RunContactRiskJob y el tab "Riesgos".
 */
class RiskAssessment extends Model
{
    use HasUuid, HasTenant;

    public const TYPE_PHONE_RISK = 'phone_risk';
    public const TYPE_EMAIL_RISK = 'email_risk';

    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'tenant_id',
        'account_id',
        'person_id',
        'type',
        'provider',
        'identifier',
        'status',
        'score',
        'level',
        'recommendation',
        'result',
        'error',
    ];

    protected $casts = [
        'result' => 'array',
        'score' => 'integer',
    ];

    /**
     * Forma segura para el front (sin el payload crudo completo por defecto).
     *
     * @return array<string, mixed>
     */
    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'provider' => $this->provider,
            'identifier' => $this->identifier,
            'status' => $this->status,
            'score' => $this->score,
            'level' => $this->level,
            'recommendation' => $this->recommendation,
            'result' => $this->result,
            'error' => $this->error,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
