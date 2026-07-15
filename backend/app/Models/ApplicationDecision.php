<?php

namespace App\Models;

use App\Enums\DecisionOutcome;
use App\Traits\HasTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Auditoría del motor de decisión: una fila por evaluación (submit, renovación,
 * gate telefónico, dry-run) con la política/versión aplicada, insumos
 * congelados, reglas disparadas y salida. `executed=false` en sombra/dry-run.
 */
class ApplicationDecision extends Model
{
    use HasFactory, HasUuids, HasTenant;

    protected $fillable = [
        'tenant_id',
        'application_id',
        'person_id',
        'account_id',
        'loan_id',
        'decision_policy_id',
        'policy_version',
        'trigger',
        'mode',
        'inputs',
        'rule_hits',
        'score',
        'band',
        'outcome',
        'outcome_detail',
        'executed',
    ];

    protected $casts = [
        'policy_version' => 'integer',
        'inputs' => 'array',
        'rule_hits' => 'array',
        'score' => 'integer',
        'outcome_detail' => 'array',
        'executed' => 'boolean',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function policy(): BelongsTo
    {
        return $this->belongsTo(DecisionPolicy::class, 'decision_policy_id');
    }

    /** @return array<string, mixed> */
    public function toApiArray(): array
    {
        $outcome = DecisionOutcome::tryFrom($this->outcome);

        return [
            'id' => $this->id,
            'application_id' => $this->application_id,
            'trigger' => $this->trigger,
            'mode' => $this->mode,
            'policy_version' => $this->policy_version,
            'inputs' => $this->inputs,
            'rule_hits' => $this->rule_hits,
            'score' => $this->score,
            'band' => $this->band,
            'outcome' => $this->outcome,
            'outcome_label' => $outcome?->label(),
            'outcome_color' => $outcome?->color(),
            'outcome_detail' => $this->outcome_detail,
            'executed' => $this->executed,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
