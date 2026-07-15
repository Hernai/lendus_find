<?php

namespace App\Services\Decision;

use App\Enums\DecisionOutcome;
use App\Enums\KycStatus;
use App\Models\DecisionPolicy;

/**
 * Motor de decisión (núcleo puro, sin efectos): evalúa insumos contra una
 * política y produce la salida — oferta con rango autorizado, revisión manual
 * o rechazo. El caller (job/probador) persiste la auditoría y ejecuta.
 *
 * Orden de evaluación: reglas duras de rechazo → flags que fuerzan revisión
 * (gate telefónico, insumos incompletos, CLABE) → scoring de bandas → oferta.
 * Las variables declarativas solo aportan puntos: nunca deciden solas.
 */
class DecisionEngineService
{
    /**
     * Evaluación de primer crédito (trigger SUBMIT o DRY_RUN).
     *
     * $context: requested_amount, requested_term_days, product_min_amount,
     * phone_gate (ALLOW|FLAG|BLOCK|null), missing (insumos faltantes tras timeout).
     *
     * @return array{outcome: string, score: int|null, band: string|null, range: array|null, rule_hits: array, reasons: string[]}
     */
    public function evaluate(DecisionPolicy $policy, array $inputs, array $context = []): array
    {
        $ruleHits = [];

        // --- Insumos incompletos (el job ya agotó el timeout): revisión, nunca rechazo ---
        if (!empty($context['missing'])) {
            $ruleHits[] = [
                'rule' => 'inputs_incomplete',
                'effect' => 'REVIEW',
                'detail' => ['missing' => $context['missing']],
            ];
        }

        // --- Reglas duras de rechazo (validaciones fuertes) ---
        foreach ((array) $policy->rule('reject', []) as $rejectRule) {
            if ($hit = $this->evaluateRejectRule($rejectRule, $inputs)) {
                $ruleHits[] = $hit;
            }
        }

        // --- Flags que fuerzan revisión manual ---
        if (($context['phone_gate'] ?? null) === DecisionOutcome::FLAG->value) {
            $ruleHits[] = ['rule' => 'phone_gate_flag', 'effect' => 'REVIEW'];
        }
        $clabeResult = $inputs['bank']['clabe_result'] ?? null;
        if ($clabeResult !== null && ($inputs['bank']['is_verified'] ?? false) === false) {
            $ruleHits[] = ['rule' => 'clabe_mismatch', 'effect' => 'REVIEW', 'detail' => ['clabe' => $clabeResult]];
        }

        // --- Scoring de bandas (siempre se calcula: alimenta panel y auditoría) ---
        [$score, $scoreDetail] = $this->score($policy, $inputs['variables'] ?? []);
        $band = $this->resolveBand($policy, $score);
        $bandDef = $this->bandDefinition($policy, $band);
        $ruleHits[] = ['rule' => 'scoring', 'effect' => 'INFO', 'detail' => ['score' => $score, 'band' => $band, 'points' => $scoreDetail]];

        // --- Rango de oferta según banda ---
        $range = null;
        if ($bandDef) {
            $termDays = (int) $policy->rule('first_credit.term_days', 7);
            $minAmount = (float) ($context['product_min_amount'] ?? $bandDef['min_amount'] ?? 0);
            $maxAmount = (float) $bandDef['max_amount'];
            $requested = (float) ($context['requested_amount'] ?? $maxAmount);
            $range = [
                'min_amount' => $minAmount,
                'max_amount' => $maxAmount,
                'amount' => max($minAmount, min($requested, $maxAmount)),
                'term_days' => $termDays,
                'min_term_days' => $termDays,
                'max_term_days' => $termDays,
            ];
            if ($requested > $maxAmount) {
                $ruleHits[] = [
                    'rule' => 'counter_offer',
                    'effect' => 'INFO',
                    'detail' => ['requested' => $requested, 'authorized' => $maxAmount],
                ];
            }
        } else {
            $ruleHits[] = ['rule' => 'band_unresolved', 'effect' => 'REVIEW', 'detail' => ['score' => $score]];
        }

        $outcome = $this->resolveOutcome($ruleHits);

        return [
            'outcome' => $outcome,
            'score' => $score,
            'band' => $band,
            'range' => $outcome === DecisionOutcome::OFFER->value ? $range : ($range ?: null),
            'rule_hits' => $ruleHits,
            'reasons' => $this->reasons($ruleHits),
        ];
    }

    /**
     * Evaluación de renovación (trigger RENEWAL): graduación por tabla de
     * niveles según comportamiento de pago.
     *
     * $history: lista cronológica de préstamos liquidados, cada uno con
     * on_time (bool), used_extension (bool) y late_days (int).
     */
    public function evaluateRenewal(DecisionPolicy $policy, array $history, array $context = []): array
    {
        $ruleHits = [];
        $levels = (array) $policy->rule('graduation.levels', []);
        $advance = (array) $policy->rule('graduation.advance', []);

        if (empty($levels)) {
            return [
                'outcome' => DecisionOutcome::NO_OFFER->value,
                'score' => null,
                'band' => null,
                'range' => null,
                'rule_hits' => [['rule' => 'graduation_unconfigured', 'effect' => 'NO_OFFER']],
                'reasons' => ['La política no tiene graduación configurada'],
            ];
        }

        // Nivel acumulado: liquidación puntual sube, tarde/prórroga mantiene.
        $level = 0;
        foreach ($history as $loan) {
            $onTime = ($loan['on_time'] ?? false) && !($loan['used_extension'] ?? false);
            $level += $onTime ? (int) ($advance['on_time'] ?? 1) : (int) ($advance['late_or_extension'] ?? 0);
        }
        $maxLevel = max(array_column($levels, 'level'));
        $level = (int) min($level, $maxLevel);

        // Mora relevante en el último crédito → sin oferta automática.
        $last = end($history) ?: [];
        $maxLateDays = (int) ($advance['max_late_days_for_auto'] ?? 5);
        if ((int) ($last['late_days'] ?? 0) > $maxLateDays) {
            return [
                'outcome' => DecisionOutcome::NO_OFFER->value,
                'score' => null,
                'band' => "NIVEL_{$level}",
                'range' => null,
                'rule_hits' => [[
                    'rule' => 'relevant_arrears',
                    'effect' => 'NO_OFFER',
                    'detail' => ['late_days' => $last['late_days'], 'max_for_auto' => $maxLateDays],
                ]],
                'reasons' => ["Liquidó con {$last['late_days']} días de atraso (máximo para oferta automática: {$maxLateDays})"],
            ];
        }

        $levelDef = collect($levels)->firstWhere('level', $level)
            ?? collect($levels)->sortBy('level')->last();

        $minTermDays = (int) $policy->rule('first_credit.term_days', 7);
        $range = [
            'min_amount' => (float) ($context['product_min_amount'] ?? 300),
            'max_amount' => (float) $levelDef['max_amount'],
            'amount' => (float) $levelDef['max_amount'],
            'term_days' => (int) $levelDef['max_term_days'],
            'min_term_days' => $minTermDays,
            'max_term_days' => (int) $levelDef['max_term_days'],
        ];

        $ruleHits[] = [
            'rule' => 'graduation',
            'effect' => 'INFO',
            'detail' => ['level' => $level, 'completed_loans' => count($history), 'level_def' => $levelDef],
        ];

        return [
            'outcome' => DecisionOutcome::OFFER->value,
            'score' => null,
            'band' => "NIVEL_{$level}",
            'range' => $range,
            'rule_hits' => $ruleHits,
            'reasons' => ["Nivel {$level}: cupo hasta \${$levelDef['max_amount']} y plazo hasta {$levelDef['max_term_days']} días"],
        ];
    }

    // =====================================================
    // Internos
    // =====================================================

    private function evaluateRejectRule(array $rule, array $inputs): ?array
    {
        $name = $rule['rule'] ?? '';

        $hit = match ($name) {
            'kyc_failed' => ($inputs['kyc_status'] ?? null) === KycStatus::REJECTED->value,
            'identity_mismatch' => ($inputs['identity_mismatch'] ?? false) === true,
            'out_of_coverage' => !empty($rule['states'])
                && !in_array($inputs['variables']['state'] ?? null, $rule['states'], true),
            default => false,
        };

        return $hit ? ['rule' => $name, 'effect' => 'REJECT', 'detail' => $rule] : null;
    }

    /**
     * Suma los puntos configurados por variable. Variable sin dato = 0 puntos
     * (queda visible en el desglose para el panel).
     *
     * @return array{0: int, 1: array}
     */
    private function score(DecisionPolicy $policy, array $variables): array
    {
        $total = 0;
        $detail = [];

        foreach ((array) $policy->rule('scoring.variables', []) as $variable) {
            $key = $variable['key'] ?? null;
            if (!$key) {
                continue;
            }
            $value = $variables[$key] ?? null;
            $points = $this->pointsFor((array) ($variable['points'] ?? []), $value);
            $total += $points;
            $detail[] = ['key' => $key, 'value' => $value, 'points' => $points];
        }

        return [$total, $detail];
    }

    private function pointsFor(array $map, mixed $value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }

        return (int) ($map[(string) $value] ?? 0);
    }

    private function resolveBand(DecisionPolicy $policy, int $score): ?string
    {
        $band = null;
        $cutoffs = collect((array) $policy->rule('scoring.band_cutoffs', []))->sortBy('min_score');
        foreach ($cutoffs as $cutoff) {
            if ($score >= (int) ($cutoff['min_score'] ?? 0)) {
                $band = $cutoff['band'] ?? null;
            }
        }

        return $band;
    }

    private function bandDefinition(DecisionPolicy $policy, ?string $band): ?array
    {
        if (!$band) {
            return null;
        }

        return collect((array) $policy->rule('bands', []))->firstWhere('key', $band);
    }

    /** REJECT gana sobre REVIEW; sin hits negativos = OFFER. */
    private function resolveOutcome(array $ruleHits): string
    {
        $effects = array_column($ruleHits, 'effect');

        if (in_array('REJECT', $effects, true)) {
            return DecisionOutcome::REJECT->value;
        }
        if (in_array('REVIEW', $effects, true)) {
            return DecisionOutcome::REVIEW->value;
        }

        return DecisionOutcome::OFFER->value;
    }

    /** Traducción legible de las reglas disparadas (para panel y notas). */
    private function reasons(array $ruleHits): array
    {
        $labels = [
            'inputs_incomplete' => 'Insumos incompletos al agotar el tiempo de espera',
            'kyc_failed' => 'La validación de identidad (KYC) falló',
            'identity_mismatch' => 'Inconsistencia de identidad detectada',
            'out_of_coverage' => 'Fuera de la cobertura geográfica del piloto',
            'phone_gate_flag' => 'El score telefónico requiere revisión',
            'clabe_mismatch' => 'La cuenta CLABE no coincide con el titular',
            'band_unresolved' => 'No se pudo resolver la banda de oferta',
            'counter_offer' => 'El monto solicitado excede el cupo autorizado (contraoferta)',
        ];

        return collect($ruleHits)
            ->filter(fn ($hit) => ($hit['effect'] ?? '') !== 'INFO' || ($hit['rule'] ?? '') === 'counter_offer')
            ->map(fn ($hit) => $labels[$hit['rule']] ?? $hit['rule'])
            ->values()
            ->all();
    }
}
