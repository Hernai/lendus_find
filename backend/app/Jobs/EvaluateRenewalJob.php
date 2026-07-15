<?php

namespace App\Jobs;

use App\Enums\DecisionOutcome;
use App\Enums\DecisionTrigger;
use App\Enums\LoanStatus;
use App\Models\Application;
use App\Models\ApplicationDecision;
use App\Models\DecisionPolicy;
use App\Models\Loan;
use App\Services\ApplicationService;
use App\Services\Decision\DecisionEngineService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Renovación (Regla 05/20): al liquidarse un préstamo, evalúa la graduación
 * por niveles y — si procede y la política está en ACTIVE — auto-crea la
 * solicitud de renovación con su oferta de rango, sin re-onboarding.
 *
 * En SHADOW solo registra qué oferta se habría generado.
 */
class EvaluateRenewalJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 60;

    public function __construct(public string $loanId)
    {
    }

    public function handle(ApplicationService $applications, DecisionEngineService $engine): void
    {
        $loan = Loan::withoutGlobalScopes()
            ->with(['application.product', 'person'])
            ->find($this->loanId);

        if (!$loan || $loan->status !== LoanStatus::COMPLETED || !$loan->person_id) {
            return;
        }

        $product = $loan->application?->product;
        if (!$product) {
            return;
        }

        $policy = DecisionPolicy::withoutGlobalScopes()
            ->where('tenant_id', $loan->tenant_id)
            ->where('product_id', $product->id)
            ->where('is_active', true)
            ->first();

        if (!$policy || !$policy->modeEnum()->evaluates() || !$policy->rule('graduation.levels')) {
            return;
        }

        // Elegibilidad: sin otra solicitud activa ni crédito vigente (dedup
        // consistente con la validación de creación de solicitudes).
        $blockers = $this->eligibilityBlockers($loan);

        $history = $this->paymentHistory($loan);
        $result = empty($blockers)
            ? $engine->evaluateRenewal($policy, $history, [
                'product_min_amount' => (float) $product->min_amount,
            ])
            : [
                'outcome' => DecisionOutcome::NO_OFFER->value,
                'score' => null,
                'band' => null,
                'range' => null,
                'rule_hits' => [['rule' => 'not_eligible', 'effect' => 'NO_OFFER', 'detail' => $blockers]],
                'reasons' => $blockers,
            ];

        $executes = $policy->modeEnum()->executes()
            && $result['outcome'] === DecisionOutcome::OFFER->value;

        ApplicationDecision::create([
            'tenant_id' => $loan->tenant_id,
            'person_id' => $loan->person_id,
            'account_id' => $loan->applicant_account_id,
            'loan_id' => $loan->id,
            'decision_policy_id' => $policy->id,
            'policy_version' => $policy->version,
            'trigger' => DecisionTrigger::RENEWAL->value,
            'mode' => $policy->mode,
            'inputs' => ['history' => $history],
            'rule_hits' => $result['rule_hits'],
            'score' => $result['score'],
            'band' => $result['band'],
            'outcome' => $result['outcome'],
            'outcome_detail' => [
                'range' => $result['range'],
                'reasons' => $result['reasons'],
            ],
            'executed' => $executes,
        ]);

        if (!$executes) {
            return;
        }

        try {
            $applications->createRenewal($loan, $policy, $result);
        } catch (\Throwable $e) {
            Log::error('EvaluateRenewalJob: falló la creación de la renovación', [
                'loan_id' => $loan->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Historial cronológico de préstamos liquidados de la persona para la
     * graduación: puntual, con prórroga o con atraso (días).
     *
     * @return array<int, array{on_time: bool, used_extension: bool, late_days: int}>
     */
    private function paymentHistory(Loan $loan): array
    {
        return Loan::withoutGlobalScopes()
            ->with(['payments', 'extensions'])
            ->where('tenant_id', $loan->tenant_id)
            ->where('person_id', $loan->person_id)
            ->where('status', LoanStatus::COMPLETED)
            ->orderBy('completed_at')
            ->get()
            ->map(function (Loan $completed) {
                $lastPayment = $completed->payments
                    ->where('status', 'COMPLETED')
                    ->sortByDesc('paid_at')
                    ->first();
                $paidAt = $lastPayment
                    ? Carbon::parse($lastPayment->paid_at)
                    : ($completed->completed_at ?? now());
                $dueDate = Carbon::parse($completed->due_date)->endOfDay();

                $usedExtension = $completed->extensions
                    ->where('status', 'APPROVED')
                    ->isNotEmpty();
                $lateDays = max(0, (int) $dueDate->diffInDays($paidAt, false));

                return [
                    'loan_id' => $completed->id,
                    'on_time' => $paidAt->lte($dueDate),
                    'used_extension' => $usedExtension,
                    'late_days' => $lateDays,
                ];
            })
            ->values()
            ->all();
    }

    /** @return string[] Motivos de inelegibilidad (vacío = elegible). */
    private function eligibilityBlockers(Loan $loan): array
    {
        $blockers = [];

        $activeApplication = Application::withoutGlobalScopes()
            ->where('tenant_id', $loan->tenant_id)
            ->where('person_id', $loan->person_id)
            ->whereNotIn('status', [
                Application::STATUS_REJECTED,
                Application::STATUS_CANCELLED,
                Application::STATUS_SYNCED,
            ])
            ->whereNull('deleted_at')
            ->exists();
        if ($activeApplication) {
            $blockers[] = 'La persona ya tiene una solicitud activa';
        }

        $activeLoan = Loan::withoutGlobalScopes()
            ->where('tenant_id', $loan->tenant_id)
            ->where('person_id', $loan->person_id)
            ->where('id', '!=', $loan->id)
            ->whereIn('status', [LoanStatus::DISBURSED, LoanStatus::ACTIVE])
            ->exists();
        if ($activeLoan) {
            $blockers[] = 'La persona tiene un crédito vigente';
        }

        return $blockers;
    }
}
