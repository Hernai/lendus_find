<?php

namespace App\Services\Decision;

use App\Enums\DecisionOutcome;
use App\Enums\DecisionTrigger;
use App\Models\ApplicantAccount;
use App\Models\ApplicationDecision;
use App\Models\DecisionPolicy;
use App\Models\RiskAssessment;
use Illuminate\Support\Facades\Log;

/**
 * Gate telefónico temprano (Regla 21): evalúa el Phone Risk Score de Nubarium
 * contra la política de tenant ANTES de consumir validaciones caras (INE,
 * biometría).
 *
 * Fail-open: sin score (403 del proveedor, timeout, servicio no contratado o
 * assessment inexistente) el cliente continúa y la corrida queda marcada como
 * FLAG (`score_unavailable`) — una falla técnica nunca bloquea el onboarding.
 */
class PhoneRiskGateService
{
    /**
     * Evaluación pura contra la política de tenant (sin efectos).
     *
     * @return array{outcome: string, score: int|null, rule_hits: array}
     */
    public function evaluate(?RiskAssessment $assessment, DecisionPolicy $policy): array
    {
        $gate = (array) $policy->rule('phone_risk_gate', []);

        if (!($gate['enabled'] ?? false)) {
            return [
                'outcome' => DecisionOutcome::ALLOW->value,
                'score' => null,
                'rule_hits' => [['rule' => 'gate_disabled', 'effect' => 'ALLOW']],
            ];
        }

        $hasScore = $assessment
            && $assessment->status === RiskAssessment::STATUS_COMPLETED
            && $assessment->score !== null;

        if (!$hasScore) {
            return [
                'outcome' => DecisionOutcome::FLAG->value,
                'score' => null,
                'rule_hits' => [[
                    'rule' => 'score_unavailable',
                    'effect' => 'REVIEW',
                    'detail' => ['error' => $assessment?->error],
                ]],
            ];
        }

        $score = (int) $assessment->score;
        $flagFrom = (int) ($gate['flag_from'] ?? 401);
        $blockFrom = (int) ($gate['block_from'] ?? 601);

        if ($score >= $blockFrom) {
            $outcome = DecisionOutcome::BLOCK->value;
            $effect = 'BLOCK';
        } elseif ($score >= $flagFrom) {
            $outcome = DecisionOutcome::FLAG->value;
            $effect = 'REVIEW';
        } else {
            $outcome = DecisionOutcome::ALLOW->value;
            $effect = 'ALLOW';
        }

        return [
            'outcome' => $outcome,
            'score' => $score,
            'rule_hits' => [[
                'rule' => 'phone_risk_score',
                'effect' => $effect,
                'detail' => [
                    'score' => $score,
                    'level' => $assessment->level,
                    'flag_from' => $flagFrom,
                    'block_from' => $blockFrom,
                ],
            ]],
        ];
    }

    /**
     * Corre el gate para una cuenta en el paso de INE del onboarding: evalúa,
     * persiste la corrida en application_decisions y devuelve el outcome que el
     * caller debe APLICAR (en SHADOW nunca se bloquea — se registra qué habría
     * pasado y se devuelve ALLOW).
     */
    public function check(ApplicantAccount $account): string
    {
        $policy = DecisionPolicy::withoutGlobalScopes()
            ->where('tenant_id', $account->tenant_id)
            ->whereNull('product_id')
            ->where('is_active', true)
            ->first();

        if (!$policy || !$policy->modeEnum()->evaluates()) {
            return DecisionOutcome::ALLOW->value;
        }

        $gate = (array) $policy->rule('phone_risk_gate', []);
        if (!($gate['enabled'] ?? false)) {
            return DecisionOutcome::ALLOW->value;
        }

        $person = $account->person;
        $assessment = RiskAssessment::withoutGlobalScopes()
            ->where('tenant_id', $account->tenant_id)
            ->where('type', RiskAssessment::TYPE_PHONE_RISK)
            ->where(function ($q) use ($account, $person) {
                $q->where('account_id', $account->id);
                if ($person) {
                    $q->orWhere('person_id', $person->id);
                }
            })
            ->orderByDesc('created_at')
            ->first();

        $result = $this->evaluate($assessment, $policy);
        $executes = $policy->modeEnum()->executes();

        // El paso de INE/biometría toca varios endpoints: no duplicar la fila
        // de auditoría si la última corrida ya es de esta versión de política
        // con el mismo resultado.
        $previous = ApplicationDecision::withoutGlobalScopes()
            ->where('tenant_id', $account->tenant_id)
            ->where('trigger', DecisionTrigger::PHONE_GATE->value)
            ->where('account_id', $account->id)
            ->orderByDesc('created_at')
            ->first();
        if ($previous
            && $previous->decision_policy_id === $policy->id
            && $previous->policy_version === $policy->version
            && $previous->outcome === $result['outcome']
        ) {
            return $executes ? $result['outcome'] : DecisionOutcome::ALLOW->value;
        }

        try {
            ApplicationDecision::create([
                'tenant_id' => $account->tenant_id,
                'account_id' => $account->id,
                'person_id' => $person?->id,
                'decision_policy_id' => $policy->id,
                'policy_version' => $policy->version,
                'trigger' => DecisionTrigger::PHONE_GATE->value,
                'mode' => $policy->mode,
                'inputs' => [
                    'phone' => $account->primary_phone,
                    'score' => $result['score'],
                    'assessment_id' => $assessment?->id,
                ],
                'rule_hits' => $result['rule_hits'],
                'score' => $result['score'],
                'outcome' => $result['outcome'],
                'executed' => $executes && $result['outcome'] === DecisionOutcome::BLOCK->value,
            ]);
        } catch (\Throwable $e) {
            // La auditoría nunca debe tirar el onboarding.
            Log::warning('PhoneRiskGate: no se pudo registrar la corrida', [
                'account_id' => $account->id,
                'error' => $e->getMessage(),
            ]);
        }

        // En sombra se registra qué habría pasado pero nunca se bloquea.
        return $executes ? $result['outcome'] : DecisionOutcome::ALLOW->value;
    }

    /**
     * Último resultado del gate para una persona/cuenta (lo consume el motor
     * al decidir: FLAG fuerza revisión manual).
     */
    public function lastGateOutcome(string $tenantId, ?string $accountId, ?string $personId): ?string
    {
        if (!$accountId && !$personId) {
            return null;
        }

        return ApplicationDecision::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('trigger', DecisionTrigger::PHONE_GATE->value)
            ->where(function ($q) use ($accountId, $personId) {
                if ($accountId) {
                    $q->orWhere('account_id', $accountId);
                }
                if ($personId) {
                    $q->orWhere('person_id', $personId);
                }
            })
            ->orderByDesc('created_at')
            ->value('outcome');
    }
}
