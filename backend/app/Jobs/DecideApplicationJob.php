<?php

namespace App\Jobs;

use App\Enums\DecisionTrigger;
use App\Models\Application;
use App\Models\ApplicationDecision;
use App\Models\DecisionPolicy;
use App\Services\ApplicationService;
use App\Services\Decision\DecisionEngineService;
use App\Services\Decision\DecisionInputCollector;
use App\Services\Decision\PhoneRiskGateService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Evalúa una solicitud recién enviada con el motor de decisión.
 *
 * Insumo faltante (CLABE por webhook, KYC asentándose) → release con backoff
 * hasta `review.input_timeout_minutes` de la política; agotado el timeout se
 * decide REVIEW (`inputs_incomplete`) — nunca rechazo por insumo faltante.
 *
 * En SHADOW solo persiste la evaluación (`executed=false`) sin tocar el
 * estado; en ACTIVE ejecuta la salida vía ApplicationService.
 */
class DecideApplicationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Reintentos vía release(); el corte real lo pone el timeout de la política. */
    public int $tries = 30;
    public int $timeout = 60;

    public function __construct(public string $applicationId)
    {
    }

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [30, 60, 120, 300];
    }

    public function handle(
        ApplicationService $applications,
        DecisionEngineService $engine,
        DecisionInputCollector $collector,
        PhoneRiskGateService $gate
    ): void {
        $application = Application::withoutGlobalScopes()
            ->with(['person.account', 'person.bankAccounts', 'product'])
            ->find($this->applicationId);

        if (!$application) {
            return;
        }

        // Si el staff ya la movió (o ya fue decidida), el motor no interviene.
        if ($application->status !== Application::STATUS_SUBMITTED) {
            return;
        }

        $policy = DecisionPolicy::withoutGlobalScopes()
            ->where('tenant_id', $application->tenant_id)
            ->where('product_id', $application->product_id)
            ->where('is_active', true)
            ->first();

        if (!$policy || !$policy->modeEnum()->evaluates()) {
            return;
        }

        $collected = $collector->collect($application);

        // Insumos pendientes: reintentar hasta agotar el timeout de la política.
        $timeoutMinutes = (int) $policy->rule('review.input_timeout_minutes', 30);
        $submittedAt = $application->submitted_at ?? $application->created_at;
        $timedOut = $submittedAt->addMinutes($timeoutMinutes)->isPast();

        if (!empty($collected['missing']) && !$timedOut) {
            $this->release($this->backoff()[min($this->attempts() - 1, count($this->backoff()) - 1)]);

            return;
        }

        $person = $application->person;
        $account = $person?->account;

        $result = $engine->evaluate($policy, $collected['inputs'], [
            'requested_amount' => (float) $application->requested_amount,
            'requested_term_days' => $application->requested_term_days,
            'product_min_amount' => (float) $application->product->min_amount,
            'phone_gate' => $gate->lastGateOutcome($application->tenant_id, $account?->id, $person?->id),
            'missing' => $timedOut ? $collected['missing'] : [],
        ]);

        $executes = $policy->modeEnum()->executes();

        ApplicationDecision::create([
            'tenant_id' => $application->tenant_id,
            'application_id' => $application->id,
            'person_id' => $person?->id,
            'account_id' => $account?->id,
            'decision_policy_id' => $policy->id,
            'policy_version' => $policy->version,
            'trigger' => DecisionTrigger::SUBMIT->value,
            'mode' => $policy->mode,
            'inputs' => $collected['inputs'],
            'rule_hits' => $result['rule_hits'],
            'score' => $result['score'],
            'band' => $result['band'],
            'outcome' => $result['outcome'],
            'outcome_detail' => [
                'range' => $result['range'],
                'reasons' => $result['reasons'],
                'missing' => $timedOut ? $collected['missing'] : [],
            ],
            'executed' => $executes,
        ]);

        if (!$executes) {
            return;
        }

        try {
            $applications->applyEngineOutcome($application, $result, $policy);
        } catch (\Throwable $e) {
            // Una falla al ejecutar no debe dejar la solicitud en el limbo:
            // queda en SUBMITTED (bandeja manual) y se registra el error.
            Log::error('DecideApplicationJob: falló la ejecución de la salida', [
                'application_id' => $application->id,
                'outcome' => $result['outcome'],
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
