<?php

namespace App\Services\Webhook;

/**
 * Catálogo de eventos salientes v1 (originación + cartera). Reutiliza los
 * nombres de NotificationEvent. Ver docs/integracion/webhooks.md.
 */
final class WebhookEvent
{
    public const APPLICATION_APPROVED = 'application.approved';
    public const APPLICATION_REJECTED = 'application.rejected';
    public const LOAN_DISBURSED = 'loan.disbursed';
    public const PAYMENT_RECEIVED = 'payment.received';
    public const LOAN_COMPLETED = 'loan.completed';

    /** @return string[] */
    public static function all(): array
    {
        return [
            self::APPLICATION_APPROVED,
            self::APPLICATION_REJECTED,
            self::LOAN_DISBURSED,
            self::PAYMENT_RECEIVED,
            self::LOAN_COMPLETED,
        ];
    }

    /** @return array<array{value:string,label:string}> Para el checklist del panel. */
    public static function toOptions(): array
    {
        $labels = [
            self::APPLICATION_APPROVED => 'Crédito autorizado',
            self::APPLICATION_REJECTED => 'Solicitud rechazada',
            self::LOAN_DISBURSED => 'Dispersión confirmada',
            self::PAYMENT_RECEIVED => 'Pago aplicado',
            self::LOAN_COMPLETED => 'Crédito liquidado',
        ];

        return array_map(fn ($e) => ['value' => $e, 'label' => $labels[$e]], self::all());
    }
}
