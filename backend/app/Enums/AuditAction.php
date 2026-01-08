<?php

namespace App\Enums;

enum AuditAction: string
{
    case OTP_REQUESTED = 'OTP_REQUESTED';
    case OTP_VERIFIED = 'OTP_VERIFIED';
    case LOGIN_SUCCESS = 'LOGIN_SUCCESS';
    case LOGIN_FAILED = 'LOGIN_FAILED';
    case LOGOUT = 'LOGOUT';
    case PIN_SET = 'PIN_SET';
    case PIN_CHANGED = 'PIN_CHANGED';
    case PIN_RESET = 'PIN_RESET';
    case USER_CREATED = 'USER_CREATED';
    case USER_UPDATED = 'USER_UPDATED';
    case APPLICANT_CREATED = 'APPLICANT_CREATED';
    case APPLICANT_UPDATED = 'APPLICANT_UPDATED';
    case APPLICATION_CREATED = 'APPLICATION_CREATED';
    case APPLICATION_UPDATED = 'APPLICATION_UPDATED';
    case APPLICATION_SUBMITTED = 'APPLICATION_SUBMITTED';
    case APPLICATION_APPROVED = 'APPLICATION_APPROVED';
    case APPLICATION_REJECTED = 'APPLICATION_REJECTED';
    case DOCUMENT_UPLOADED = 'DOCUMENT_UPLOADED';
    case DOCUMENT_APPROVED = 'DOCUMENT_APPROVED';
    case DOCUMENT_REJECTED = 'DOCUMENT_REJECTED';
    case DATA_VERIFIED = 'DATA_VERIFIED';
    case DATA_REJECTED = 'DATA_REJECTED';
    case DATA_CORRECTED = 'DATA_CORRECTED';
    case REFERENCE_VERIFIED = 'REFERENCE_VERIFIED';
    case STEP_COMPLETED = 'STEP_COMPLETED';

    public function label(): string
    {
        return match ($this) {
            self::OTP_REQUESTED => 'OTP solicitado',
            self::OTP_VERIFIED => 'OTP verificado',
            self::LOGIN_SUCCESS => 'Inicio de sesión exitoso',
            self::LOGIN_FAILED => 'Inicio de sesión fallido',
            self::LOGOUT => 'Cierre de sesión',
            self::PIN_SET => 'PIN establecido',
            self::PIN_CHANGED => 'PIN cambiado',
            self::PIN_RESET => 'PIN restablecido',
            self::USER_CREATED => 'Usuario creado',
            self::USER_UPDATED => 'Usuario actualizado',
            self::APPLICANT_CREATED => 'Solicitante creado',
            self::APPLICANT_UPDATED => 'Solicitante actualizado',
            self::APPLICATION_CREATED => 'Solicitud creada',
            self::APPLICATION_UPDATED => 'Solicitud actualizada',
            self::APPLICATION_SUBMITTED => 'Solicitud enviada',
            self::APPLICATION_APPROVED => 'Solicitud aprobada',
            self::APPLICATION_REJECTED => 'Solicitud rechazada',
            self::DOCUMENT_UPLOADED => 'Documento subido',
            self::DOCUMENT_APPROVED => 'Documento aprobado',
            self::DOCUMENT_REJECTED => 'Documento rechazado',
            self::DATA_VERIFIED => 'Dato verificado',
            self::DATA_REJECTED => 'Dato rechazado',
            self::DATA_CORRECTED => 'Dato corregido',
            self::REFERENCE_VERIFIED => 'Referencia verificada',
            self::STEP_COMPLETED => 'Paso completado',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
