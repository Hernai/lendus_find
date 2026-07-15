<?php

namespace App\Services\Webhook;

use App\Models\Application;
use App\Models\BankAccount;
use App\Models\Loan;
use App\Models\Person;

/**
 * Construye el recurso estandarizado (`data`) que viaja en el webhook y también
 * responde la API de re-consulta — un solo builder, un solo esquema. Los
 * ejemplos y el contrato viven en docs/integracion/webhooks.md.
 *
 * La CLABE viaja COMPLETA: la cartera externa dispersa y la necesita.
 */
class WebhookPayloadBuilder
{
    public const SCHEMA_VERSION = '1';

    /**
     * Sobre versionado completo para un evento.
     *
     * @return array<string, mixed>
     */
    public function envelope(string $eventId, string $event, string $tenantId, ?string $tenantSlug, array $data, ?\DateTimeInterface $occurredAt = null): array
    {
        return [
            'id' => $eventId,
            'event' => $event,
            'version' => self::SCHEMA_VERSION,
            'occurred_at' => ($occurredAt ?? now())->format(\DateTimeInterface::ATOM),
            'tenant' => ['id' => $tenantId, 'slug' => $tenantSlug],
            'data' => $data,
        ];
    }

    /** Recurso `application` estandarizado (application.approved / .rejected). */
    public function application(Application $app): array
    {
        $app->loadMissing(['product', 'person']);
        $person = $app->person;

        return [
            'application' => [
                'id' => $app->id,
                'folio' => $app->created_at?->format('Ymd') . '-' . strtoupper(substr($app->id, 0, 4)),
                'status' => $app->status,
                'product' => $app->product ? [
                    'code' => $app->product->code,
                    'name' => $app->product->name,
                ] : null,
                'approved' => $app->approved_amount ? [
                    'amount' => (float) $app->approved_amount,
                    'term_days' => $app->approved_term_days,
                    'term_months' => $app->approved_term_months,
                    'interest_rate' => (float) ($app->approved_interest_rate ?? $app->product?->annual_rate),
                    'opening_commission_rate' => (float) ($app->product?->opening_commission_rate ?? 0),
                    'currency' => 'MXN',
                ] : null,
                'rejection' => $app->status === Application::STATUS_REJECTED ? [
                    'reason' => $app->rejection_reason,
                    'notes' => $app->decision_notes,
                ] : null,
                'person' => $person ? $this->person($person) : null,
                'disbursement_account' => $person ? $this->disbursementAccount($person) : null,
                'approved_at' => $app->decision_at?->format(\DateTimeInterface::ATOM),
            ],
        ];
    }

    /** Recurso `loan` estandarizado (loan.disbursed / payment.received / .completed). */
    public function loan(Loan $loan, ?array $lastPayment = null): array
    {
        $loan->loadMissing(['person']);
        $statusValue = $loan->status instanceof \BackedEnum ? $loan->status->value : $loan->status;

        $data = [
            'loan' => [
                'id' => $loan->id,
                'application_id' => $loan->application_id,
                'status' => $statusValue,
                'principal_amount' => (float) $loan->principal_amount,
                'interest_rate' => (float) $loan->interest_rate,
                'term_days' => $loan->term_days,
                'opening_commission_amount' => (float) $loan->opening_commission_amount,
                'total_to_pay' => (float) $loan->total_to_pay,
                'paid_amount' => (float) $loan->paid_amount,
                'outstanding_balance' => (float) $loan->outstanding_balance,
                'currency' => 'MXN',
                'disbursed_at' => $loan->disbursed_at?->format(\DateTimeInterface::ATOM),
                'due_date' => $loan->due_date?->format('Y-m-d'),
                'completed_at' => $loan->completed_at?->format(\DateTimeInterface::ATOM),
                'disbursement' => $loan->disbursement_reference ? [
                    'provider' => $loan->disbursement_provider,
                    'reference' => $loan->disbursement_reference,
                ] : null,
                'person' => $loan->person ? $this->person($loan->person) : null,
            ],
        ];

        if ($lastPayment !== null) {
            $data['loan']['last_payment'] = $lastPayment;
        }

        return $data;
    }

    /** @return array<string, mixed> */
    private function person(Person $person): array
    {
        return [
            'id' => $person->id,
            'full_name' => $person->full_name,
            'curp' => $person->curp,
            'rfc' => $person->rfc,
            'birth_date' => $person->birth_date?->format('Y-m-d'),
            'kyc_status' => $person->kyc_status,
        ];
    }

    /** Cuenta de dispersión con CLABE COMPLETA (la cartera dispersa). */
    private function disbursementAccount(Person $person): ?array
    {
        $account = BankAccount::where('entity_type', 'persons')
            ->where('entity_id', $person->id)
            ->where('is_for_disbursement', true)
            ->first()
            ?? BankAccount::findPrimaryForPerson($person->id);

        if (! $account) {
            return null;
        }

        return [
            'bank_name' => $account->bank_name,
            'clabe' => $account->clabe,
            'holder_name' => $account->holder_name,
        ];
    }
}
