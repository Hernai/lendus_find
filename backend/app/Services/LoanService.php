<?php

namespace App\Services;

use App\Enums\LoanStatus;
use App\Jobs\EvaluateRenewalJob;
use App\Models\Application;
use App\Models\BankAccount;
use App\Models\Loan;
use App\Models\LoanExtension;
use App\Models\LoanPayment;
use App\Models\LoanReward;
use App\Services\ExternalApi\StpService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Servicio del módulo Loan Portfolio.
 *
 * Solo se ejecuta para tenants con `features.loan_portfolio=true`.
 * Se invoca desde ApplicationService al transicionar
 * APPROVED → DISBURSED tras aceptar la oferta preaprobada.
 */
class LoanService
{
    public function __construct(private StpService $stp) {}

    /**
     * Crea un Loan a partir de una Application APPROVED.
     *
     * @param Application $app
     * @param array{amount: float, term_days: int, interest_rate?: float, bank_account_id?: string} $offer
     */
    public function createFromApplication(Application $app, array $offer): Loan
    {
        return DB::transaction(function () use ($app, $offer) {
            $principal = (float) $offer['amount'];
            $termDays = (int) $offer['term_days'];
            $annualRate = (float) ($offer['interest_rate'] ?? $app->product?->interest_rate ?? 36);

            // Cálculo simple: interés simple sobre principal por plazo en días.
            $interestAmount = round(($principal * $annualRate / 100) * ($termDays / 365), 2);
            $openingCommissionPct = (float) ($app->product?->opening_commission ?? 0);
            $openingCommissionAmount = round($principal * $openingCommissionPct / 100, 2);
            $totalToPay = round($principal + $interestAmount + $openingCommissionAmount, 2);

            $bankAccount = null;
            if (!empty($offer['bank_account_id'])) {
                $bankAccount = BankAccount::find($offer['bank_account_id']);
            } else {
                // Cuenta de dispersión del applicant. BankAccount es polimórfica:
                // entity_type/entity_id (no existe columna person_id).
                $bankAccount = BankAccount::where('entity_type', 'persons')
                    ->where('entity_id', $app->person_id)
                    ->where('is_for_disbursement', true)
                    ->first()
                    ?? BankAccount::findPrimaryForPerson($app->person_id);
            }

            // Dispersión externa (cartera): el crédito queda PENDIENTE hasta que
            // la cartera confirme la dispersión (webhook entrante). No se
            // dispersa por STP interno ni se fija disbursed_at/due_date todavía.
            $externalDisbursement = (bool) $app->tenant?->hasFeature('external_disbursement');

            $loan = Loan::create([
                'tenant_id' => $app->tenant_id,
                'application_id' => $app->id,
                'applicant_account_id' => $app->applicant_account_id ?? $this->resolveApplicantAccountId($app),
                'person_id' => $app->person_id,
                'bank_account_id' => $bankAccount?->id,
                'principal_amount' => $principal,
                'interest_rate' => $annualRate,
                'term_days' => $termDays,
                'opening_commission_amount' => $openingCommissionAmount,
                'total_to_pay' => $totalToPay,
                'outstanding_balance' => $totalToPay,
                'paid_amount' => 0,
                'late_fee_accrued' => 0,
                'status' => $externalDisbursement
                    ? LoanStatus::PENDING_DISBURSEMENT->value
                    : LoanStatus::DISBURSED->value,
                'disbursed_at' => $externalDisbursement ? null : now(),
                'due_date' => $externalDisbursement ? null : Carbon::today()->addDays($termDays),
            ]);

            // Si auto_disbursement está activo (y no es cartera externa), dispersar via STP.
            if (! $externalDisbursement && $app->tenant?->hasFeature('auto_disbursement') && $bankAccount) {
                try {
                    $result = $this->stp->disburse($loan, $bankAccount);
                    $loan->update([
                        'disbursement_provider' => 'STP',
                        'disbursement_reference' => $result['reference'] ?? null,
                        'status' => LoanStatus::ACTIVE->value,
                    ]);
                } catch (\Throwable $e) {
                    Log::warning('Auto-disbursement failed, keeping Loan in DISBURSED', [
                        'loan_id' => $loan->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return $loan->fresh();
        });
    }

    /**
     * Calcula el costo de extender la fecha de pago N días sin crear nada.
     *
     * @return array{fee: float, new_due_date: string, days_added: int}
     */
    public function quoteExtension(Loan $loan, int $days): array
    {
        // Fee simple: 1.5% del outstanding por cada 7 días.
        $weeks = max(1, (int) ceil($days / 7));
        $fee = round((float) $loan->outstanding_balance * 0.015 * $weeks, 2);
        $newDue = Carbon::parse($loan->due_date)->addDays($days);
        return [
            'fee' => $fee,
            'new_due_date' => $newDue->toDateString(),
            'days_added' => $days,
        ];
    }

    public function requestExtension(Loan $loan, int $days): LoanExtension
    {
        $quote = $this->quoteExtension($loan, $days);
        return LoanExtension::create([
            'tenant_id' => $loan->tenant_id,
            'loan_id' => $loan->id,
            'days_added' => $days,
            'fee_amount' => $quote['fee'],
            'previous_due_date' => $loan->due_date,
            'new_due_date' => $quote['new_due_date'],
            'status' => LoanExtension::STATUS_PENDING,
            'requested_at' => now(),
        ]);
    }

    public function approveExtension(LoanExtension $extension, ?string $approvedBy = null): void
    {
        DB::transaction(function () use ($extension, $approvedBy) {
            $extension->update([
                'status' => LoanExtension::STATUS_APPROVED,
                'approved_at' => now(),
                'approved_by' => $approvedBy,
            ]);
            // Mover el due_date del Loan + sumar el fee al outstanding.
            $loan = $extension->loan;
            $loan->update([
                'due_date' => $extension->new_due_date,
                'outstanding_balance' => (float) $loan->outstanding_balance + (float) $extension->fee_amount,
                'total_to_pay' => (float) $loan->total_to_pay + (float) $extension->fee_amount,
            ]);
        });
    }

    /**
     * Confirma la dispersión hecha por la cartera externa: pasa el Loan de
     * PENDING_DISBURSEMENT a ACTIVE (fija disbursed_at/due_date/referencia),
     * marca la Application SYNCED con el external_id, y emite loan.disbursed.
     *
     * @param  array{external_id?:string,external_system?:string,disbursement_reference?:string,disbursed_at?:string}  $payload
     */
    public function confirmDisbursement(Loan $loan, array $payload): Loan
    {
        if ($loan->status !== LoanStatus::PENDING_DISBURSEMENT) {
            throw new \InvalidArgumentException('El crédito no está pendiente de dispersión.');
        }

        $disbursedAt = isset($payload['disbursed_at']) ? Carbon::parse($payload['disbursed_at']) : now();

        DB::transaction(function () use ($loan, $payload, $disbursedAt) {
            $loan->update([
                'status' => LoanStatus::ACTIVE->value,
                'disbursed_at' => $disbursedAt,
                'due_date' => $disbursedAt->copy()->addDays((int) $loan->term_days),
                'disbursement_provider' => $payload['external_system'] ?? 'CARTERA',
                'disbursement_reference' => $payload['disbursement_reference'] ?? null,
            ]);

            // Cierra el handoff: la solicitud queda SYNCED con el id de la cartera.
            $app = $loan->application;
            if ($app && $app->status === \App\Models\Application::STATUS_APPROVED) {
                $app->markSynced(
                    $payload['external_id'] ?? $loan->id,
                    $payload['external_system'] ?? 'CARTERA',
                    ['disbursement_reference' => $payload['disbursement_reference'] ?? null]
                );
            }
        });

        $loan->refresh();
        $this->emitWebhook(\App\Services\Webhook\WebhookEvent::LOAN_DISBURSED, $loan);

        return $loan;
    }

    public function recordPayment(Loan $loan, array $payload): LoanPayment
    {
        $wasCompleted = $loan->status === LoanStatus::COMPLETED;

        $payment = DB::transaction(function () use ($loan, $payload) {
            $payment = LoanPayment::create([
                'tenant_id' => $loan->tenant_id,
                'loan_id' => $loan->id,
                'amount' => $payload['amount'],
                'paid_at' => $payload['paid_at'] ?? now(),
                'status' => $payload['status'] ?? LoanPayment::STATUS_COMPLETED,
                'channel' => $payload['channel'] ?? LoanPayment::CHANNEL_MANUAL,
                'provider' => $payload['provider'] ?? null,
                'provider_reference' => $payload['provider_reference'] ?? null,
                'metadata' => $payload['metadata'] ?? null,
            ]);

            $loan->recalculateBalance();

            // Si pagó puntual (antes o en la fecha de vencimiento), otorgar recompensa.
            $paidOnTime = Carbon::parse($payment->paid_at)->lte(Carbon::parse($loan->due_date)->endOfDay());
            if ($loan->status === LoanStatus::COMPLETED && $paidOnTime) {
                LoanReward::create([
                    'tenant_id' => $loan->tenant_id,
                    'applicant_account_id' => $loan->applicant_account_id,
                    'loan_id' => $loan->id,
                    'type' => LoanReward::TYPE_PUNCTUAL_PAYMENT,
                    'points' => 100,
                    'description' => 'Pago puntual completado',
                    'earned_at' => now(),
                ]);
            }

            return $payment;
        });

        $loan->refresh();

        // Webhook: pago aplicado (con el último pago en el sobre).
        $this->emitWebhook(\App\Services\Webhook\WebhookEvent::PAYMENT_RECEIVED, $loan, [
            'last_payment' => [
                'amount' => (float) $payment->amount,
                'channel' => $payment->channel,
                'provider_reference' => $payment->provider_reference,
                'paid_at' => Carbon::parse($payment->paid_at)->format(\DateTimeInterface::ATOM),
            ],
        ]);

        // Renovación (Regla 05): recién liquidado → el motor evalúa la
        // graduación y, si procede, genera la oferta de renovación. Fuera de
        // la transacción: el pago nunca depende del motor.
        if (!$wasCompleted && $loan->status === LoanStatus::COMPLETED) {
            $this->emitWebhook(\App\Services\Webhook\WebhookEvent::LOAN_COMPLETED, $loan);
            EvaluateRenewalJob::dispatch($loan->id);
        }

        return $payment;
    }

    /**
     * Emite un webhook del ciclo del crédito (best-effort). Resuelve el service
     * por el contenedor.
     *
     * @param  array<string, mixed>  $extra
     */
    protected function emitWebhook(string $event, Loan $loan, array $extra = []): void
    {
        app(\App\Services\Webhook\WebhookService::class)->emit($event, $loan, $extra);
    }

    /**
     * Backfill: si la application no tiene applicant_account_id (modelo legacy),
     * lo resolvemos via person_id buscando el ApplicantAccount asociado.
     */
    private function resolveApplicantAccountId(Application $app): ?string
    {
        if ($app->applicant_account_id ?? null) {
            return $app->applicant_account_id;
        }
        return \App\Models\ApplicantAccount::where('person_id', $app->person_id)
            ->value('id');
    }
}
