<?php

namespace App\Enums;

use App\Enums\Traits\HasOptions;

/**
 * System events that can trigger notifications.
 */
enum NotificationEvent: string
{
    use HasOptions;

    // Authentication & Onboarding
    case OTP_SENT = 'otp.sent';
    case USER_REGISTERED = 'user.registered';
    case PROFILE_COMPLETED = 'profile.completed';

    // Application Lifecycle
    case APPLICATION_CREATED = 'application.created';
    case APPLICATION_SUBMITTED = 'application.submitted';
    case APPLICATION_IN_REVIEW = 'application.in_review';
    case APPLICATION_APPROVED = 'application.approved';
    case APPLICATION_REJECTED = 'application.rejected';
    case APPLICATION_DOCS_PENDING = 'application.docs_pending';
    case APPLICATION_CORRECTIONS_REQUESTED = 'application.corrections_requested';
    case APPLICATION_CANCELLED = 'application.cancelled';
    case APPLICATION_COUNTER_OFFERED = 'application.counter_offered';
    case COUNTER_OFFER_ACCEPTED = 'counter_offer.accepted';
    case COUNTER_OFFER_REJECTED = 'counter_offer.rejected';
    case APPLICATION_SYNCED = 'application.synced';

    // Documents
    case DOCUMENT_UPLOADED = 'document.uploaded';
    case DOCUMENT_APPROVED = 'document.approved';
    case DOCUMENT_REJECTED = 'document.rejected';
    case DOCUMENTS_COMPLETE = 'documents.complete';

    // KYC & Validation
    case KYC_STARTED = 'kyc.started';
    case KYC_COMPLETED = 'kyc.completed';
    case KYC_FAILED = 'kyc.failed';
    case REFERENCE_VERIFIED = 'reference.verified';

    // Staff Actions
    case ANALYST_ASSIGNED = 'analyst.assigned';
    case STATUS_CHANGED = 'status.changed';
    case COMMENT_ADDED = 'comment.added';

    // Bank Account & Security
    case BANK_ACCOUNT_VERIFIED = 'bank_account.verified';
    case SECURITY_PIN_CHANGED = 'security.pin_changed';

    // Loan & Payments
    case LOAN_DISBURSED = 'loan.disbursed';
    case PAYMENT_RECEIVED = 'payment.received';
    case PAYMENT_UPCOMING = 'payment.upcoming';
    case PAYMENT_OVERDUE = 'payment.overdue';
    case LOAN_COMPLETED = 'loan.completed';
    case LOAN_DEFAULT = 'loan.default';

    // System
    case WEBHOOK_FAILED = 'webhook.failed';
    case REMINDER_PENDING_DOCS = 'reminder.pending_docs';
    case REMINDER_INCOMPLETE_PROFILE = 'reminder.incomplete_profile';

    public function label(): string
    {
        return match ($this) {
            self::OTP_SENT => 'Código OTP Enviado',
            self::USER_REGISTERED => 'Usuario Registrado',
            self::PROFILE_COMPLETED => 'Perfil Completado',

            self::APPLICATION_CREATED => 'Solicitud Creada',
            self::APPLICATION_SUBMITTED => 'Solicitud Enviada',
            self::APPLICATION_IN_REVIEW => 'Solicitud en Revisión',
            self::APPLICATION_APPROVED => 'Solicitud Aprobada',
            self::APPLICATION_REJECTED => 'Solicitud Rechazada',
            self::APPLICATION_DOCS_PENDING => 'Documentos Pendientes',
            self::APPLICATION_CORRECTIONS_REQUESTED => 'Correcciones Solicitadas',
            self::APPLICATION_CANCELLED => 'Solicitud Cancelada',
            self::APPLICATION_COUNTER_OFFERED => 'Contraoferta Enviada',
            self::COUNTER_OFFER_ACCEPTED => 'Contraoferta Aceptada',
            self::COUNTER_OFFER_REJECTED => 'Contraoferta Rechazada',
            self::APPLICATION_SYNCED => 'Solicitud Sincronizada',

            self::DOCUMENT_UPLOADED => 'Documento Subido',
            self::DOCUMENT_APPROVED => 'Documento Aprobado',
            self::DOCUMENT_REJECTED => 'Documento Rechazado',
            self::DOCUMENTS_COMPLETE => 'Documentos Completos',

            self::KYC_STARTED => 'Validación KYC Iniciada',
            self::KYC_COMPLETED => 'Validación KYC Completada',
            self::KYC_FAILED => 'Validación KYC Fallida',
            self::REFERENCE_VERIFIED => 'Referencia Verificada',

            self::ANALYST_ASSIGNED => 'Analista Asignado',
            self::STATUS_CHANGED => 'Estado Cambiado',
            self::COMMENT_ADDED => 'Comentario Agregado',

            self::BANK_ACCOUNT_VERIFIED => 'Cuenta Bancaria Verificada',
            self::SECURITY_PIN_CHANGED => 'PIN de Seguridad Cambiado',

            self::LOAN_DISBURSED => 'Crédito Desembolsado',
            self::PAYMENT_RECEIVED => 'Pago Recibido',
            self::PAYMENT_UPCOMING => 'Pago Próximo a Vencer',
            self::PAYMENT_OVERDUE => 'Pago Vencido',
            self::LOAN_COMPLETED => 'Crédito Liquidado',
            self::LOAN_DEFAULT => 'Crédito en Mora',

            self::WEBHOOK_FAILED => 'Webhook Fallido',
            self::REMINDER_PENDING_DOCS => 'Recordatorio: Documentos Pendientes',
            self::REMINDER_INCOMPLETE_PROFILE => 'Recordatorio: Perfil Incompleto',
        };
    }

    /**
     * Get available variables for this event.
     */
    public function getAvailableVariables(): array
    {
        $common = [
            'user.first_name' => 'Nombre del usuario',
            'user.last_name' => 'Apellido del usuario',
            'user.phone' => 'Teléfono del usuario',
            'user.email' => 'Correo del usuario',
            'tenant.name' => 'Nombre de la empresa',
            'tenant.phone' => 'Teléfono de la empresa',
            'tenant.email' => 'Correo de la empresa',
            'tenant.website' => 'Sitio web de la empresa',
        ];

        $specific = match ($this) {
            self::OTP_SENT => [
                'otp.code' => 'Código OTP',
                'otp.expires_in' => 'Tiempo de expiración (minutos)',
            ],

            self::APPLICATION_CREATED,
            self::APPLICATION_SUBMITTED,
            self::APPLICATION_IN_REVIEW,
            self::APPLICATION_APPROVED,
            self::APPLICATION_REJECTED,
            self::APPLICATION_DOCS_PENDING,
            self::APPLICATION_CORRECTIONS_REQUESTED => [
                'application.id' => 'ID de la solicitud',
                'application.folio' => 'Folio de la solicitud',
                'application.amount' => 'Monto solicitado',
                'application.term_months' => 'Plazo en meses',
                'application.product_name' => 'Nombre del producto',
                'application.status' => 'Estado de la solicitud',
                'application.status_label' => 'Estado (etiqueta)',
            ],

            self::APPLICATION_CANCELLED => [
                'application.id' => 'ID de la solicitud',
                'application.folio' => 'Folio de la solicitud',
                'application.amount' => 'Monto solicitado',
                'application.product_name' => 'Nombre del producto',
                'cancellation.reason' => 'Razón de cancelación',
            ],

            self::APPLICATION_COUNTER_OFFERED,
            self::COUNTER_OFFER_ACCEPTED,
            self::COUNTER_OFFER_REJECTED => [
                'application.id' => 'ID de la solicitud',
                'application.folio' => 'Folio de la solicitud',
                'application.amount' => 'Monto original',
                'application.product_name' => 'Nombre del producto',
                'counter_offer.amount' => 'Monto de contraoferta',
                'counter_offer.term_months' => 'Plazo contraoferta (meses)',
                'counter_offer.monthly_payment' => 'Pago mensual contraoferta',
                'counter_offer.interest_rate' => 'Tasa de interés contraoferta',
                'counter_offer.reason' => 'Razón de la contraoferta',
            ],

            self::APPLICATION_SYNCED => [
                'application.folio' => 'Folio de la solicitud',
                'sync.system' => 'Sistema externo',
                'sync.external_id' => 'ID externo',
            ],

            self::BANK_ACCOUNT_VERIFIED => [
                'bank_account.bank_name' => 'Nombre del banco',
                'bank_account.masked_clabe' => 'CLABE enmascarada',
                'bank_account.holder_name' => 'Titular de la cuenta',
            ],

            self::SECURITY_PIN_CHANGED => [],

            self::LOAN_DISBURSED => [
                'application.folio' => 'Folio de la solicitud',
                'application.product_name' => 'Nombre del producto',
                'loan.disbursed_amount' => 'Monto desembolsado',
                'loan.disbursement_date' => 'Fecha de desembolso',
                'loan.bank_account' => 'Cuenta de depósito',
                'loan.reference' => 'Referencia de depósito',
            ],

            self::PAYMENT_RECEIVED => [
                'application.folio' => 'Folio de la solicitud',
                'payment.amount' => 'Monto del pago',
                'payment.date' => 'Fecha del pago',
                'payment.method' => 'Método de pago',
                'payment.reference' => 'Referencia del pago',
                'payment.remaining_balance' => 'Saldo restante',
            ],

            self::PAYMENT_UPCOMING => [
                'application.folio' => 'Folio de la solicitud',
                'payment.amount' => 'Monto del pago',
                'payment.due_date' => 'Fecha de vencimiento',
                'payment.payment_number' => 'Número de pago',
                'payment.total_payments' => 'Total de pagos',
            ],

            self::PAYMENT_OVERDUE => [
                'application.folio' => 'Folio de la solicitud',
                'payment.amount' => 'Monto del pago',
                'payment.due_date' => 'Fecha de vencimiento',
                'payment.days_overdue' => 'Días de atraso',
                'payment.late_fee' => 'Recargo por mora',
            ],

            self::LOAN_COMPLETED => [
                'application.folio' => 'Folio de la solicitud',
                'loan.total_paid' => 'Total pagado',
                'loan.completion_date' => 'Fecha de liquidación',
            ],

            self::LOAN_DEFAULT => [
                'application.folio' => 'Folio de la solicitud',
                'loan.overdue_amount' => 'Monto vencido',
                'loan.days_overdue' => 'Días de atraso',
                'loan.late_fees' => 'Recargos acumulados',
            ],

            self::DOCUMENT_UPLOADED,
            self::DOCUMENT_APPROVED,
            self::DOCUMENT_REJECTED => [
                'document.type' => 'Tipo de documento',
                'document.status' => 'Estado del documento',
                'document.rejection_reason' => 'Razón de rechazo',
            ],

            self::ANALYST_ASSIGNED => [
                'analyst.name' => 'Nombre del analista',
                'analyst.email' => 'Correo del analista',
            ],

            default => [],
        };

        return array_merge($common, $specific);
    }

    /**
     * Check if this event should trigger notifications by default.
     */
    public function isEnabledByDefault(): bool
    {
        return match ($this) {
            self::OTP_SENT,
            self::APPLICATION_SUBMITTED,
            self::APPLICATION_APPROVED,
            self::APPLICATION_REJECTED,
            self::APPLICATION_COUNTER_OFFERED,
            self::LOAN_DISBURSED,
            self::PAYMENT_RECEIVED,
            self::PAYMENT_OVERDUE,
            self::LOAN_DEFAULT => true,
            default => false,
        };
    }

    /**
     * Get recommended channels for this event.
     */
    public function getRecommendedChannels(): array
    {
        return match ($this) {
            self::OTP_SENT => [NotificationChannel::SMS, NotificationChannel::WHATSAPP],
            self::APPLICATION_APPROVED,
            self::APPLICATION_REJECTED => [
                NotificationChannel::SMS,
                NotificationChannel::WHATSAPP,
                NotificationChannel::EMAIL,
                NotificationChannel::IN_APP,
            ],
            self::APPLICATION_COUNTER_OFFERED,
            self::COUNTER_OFFER_ACCEPTED,
            self::LOAN_DISBURSED,
            self::PAYMENT_RECEIVED,
            self::LOAN_COMPLETED => [
                NotificationChannel::SMS,
                NotificationChannel::WHATSAPP,
                NotificationChannel::EMAIL,
                NotificationChannel::IN_APP,
            ],
            self::APPLICATION_SUBMITTED,
            self::APPLICATION_IN_REVIEW => [
                NotificationChannel::EMAIL,
                NotificationChannel::IN_APP,
            ],
            self::COUNTER_OFFER_REJECTED,
            self::BANK_ACCOUNT_VERIFIED => [
                NotificationChannel::EMAIL,
                NotificationChannel::IN_APP,
            ],
            self::DOCUMENT_REJECTED,
            self::APPLICATION_CORRECTIONS_REQUESTED => [
                NotificationChannel::SMS,
                NotificationChannel::WHATSAPP,
                NotificationChannel::IN_APP,
            ],
            self::APPLICATION_CANCELLED,
            self::PAYMENT_UPCOMING,
            self::PAYMENT_OVERDUE,
            self::LOAN_DEFAULT,
            self::SECURITY_PIN_CHANGED => [
                NotificationChannel::EMAIL,
                NotificationChannel::SMS,
                NotificationChannel::IN_APP,
            ],
            self::APPLICATION_SYNCED => [NotificationChannel::IN_APP],
            default => [NotificationChannel::IN_APP],
        };
    }
}
