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
            self::APPLICATION_REJECTED => true,
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
            self::APPLICATION_SUBMITTED,
            self::APPLICATION_IN_REVIEW => [
                NotificationChannel::EMAIL,
                NotificationChannel::IN_APP,
            ],
            self::DOCUMENT_REJECTED,
            self::APPLICATION_CORRECTIONS_REQUESTED => [
                NotificationChannel::SMS,
                NotificationChannel::WHATSAPP,
                NotificationChannel::IN_APP,
            ],
            default => [NotificationChannel::IN_APP],
        };
    }
}
