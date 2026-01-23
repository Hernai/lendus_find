<?php

namespace Database\Seeders;

/**
 * Professional HTML email templates with premium design
 */
class ProfessionalEmailTemplates
{
    /**
     * Get OTP email HTML template
     */
    public static function getOtpEmailHtml(): string
    {
        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif; background-color: #f3f4f6;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f3f4f6; padding: 40px 0;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 16px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); overflow: hidden;">
                    <!-- Header -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 40px 30px; text-align: center;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 28px; font-weight: 700;">🔐 Código de Seguridad</h1>
                        </td>
                    </tr>

                    <!-- Body -->
                    <tr>
                        <td style="padding: 40px 30px;">
                            <p style="margin: 0 0 24px; font-size: 16px; line-height: 24px; color: #374151;">
                                Hola,
                            </p>

                            <p style="margin: 0 0 32px; font-size: 16px; line-height: 24px; color: #374151;">
                                Se ha solicitado un código de verificación para tu cuenta en <strong>{{tenant.name}}</strong>.
                            </p>

                            <!-- OTP Code Box -->
                            <table width="100%" cellpadding="0" cellspacing="0" style="margin: 0 0 32px;">
                                <tr>
                                    <td align="center" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 12px; padding: 32px;">
                                        <p style="margin: 0 0 8px; font-size: 14px; font-weight: 600; color: rgba(255, 255, 255, 0.9); text-transform: uppercase; letter-spacing: 1px;">
                                            Tu código de verificación
                                        </p>
                                        <p style="margin: 0; font-size: 48px; font-weight: 700; color: #ffffff; letter-spacing: 8px; font-family: \'Courier New\', monospace;">
                                            {{otp.code}}
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            <table width="100%" cellpadding="0" cellspacing="0" style="margin: 0 0 32px; background-color: #fef3c7; border-left: 4px solid #f59e0b; border-radius: 8px; padding: 16px;">
                                <tr>
                                    <td>
                                        <p style="margin: 0 0 8px; font-size: 14px; font-weight: 600; color: #92400e;">
                                            ⏱️ Tiempo de validez
                                        </p>
                                        <p style="margin: 0; font-size: 14px; color: #78350f;">
                                            Este código expirará en <strong>{{otp.expires_in}} minutos</strong>
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            <!-- Security Warning -->
                            <table width="100%" cellpadding="0" cellspacing="0" style="margin: 0 0 32px; background-color: #fee2e2; border-left: 4px solid #ef4444; border-radius: 8px; padding: 16px;">
                                <tr>
                                    <td>
                                        <p style="margin: 0 0 12px; font-size: 14px; font-weight: 600; color: #991b1b;">
                                            ⚠️ Por tu seguridad
                                        </p>
                                        <ul style="margin: 0; padding-left: 20px; font-size: 14px; color: #7f1d1d; line-height: 20px;">
                                            <li style="margin-bottom: 8px;">No compartas este código con nadie</li>
                                            <li style="margin-bottom: 8px;">Nuestro personal NUNCA te pedirá este código</li>
                                            <li>Si no solicitaste este código, ignora este mensaje</li>
                                        </ul>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin: 0; font-size: 14px; line-height: 20px; color: #6b7280;">
                                Saludos,<br>
                                <strong>{{tenant.name}}</strong>
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f9fafb; padding: 20px 30px; border-top: 1px solid #e5e7eb;">
                            <p style="margin: 0; font-size: 12px; line-height: 16px; color: #9ca3af; text-align: center;">
                                Este es un correo automático, por favor no respondas a este mensaje.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';
    }

    /**
     * Get Application Submitted email HTML template
     */
    public static function getApplicationSubmittedHtml(): string
    {
        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif; background-color: #f3f4f6;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f3f4f6; padding: 40px 0;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 16px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); overflow: hidden;">
                    <!-- Header -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); padding: 40px 30px; text-align: center;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 28px; font-weight: 700;">✅ Solicitud Recibida</h1>
                        </td>
                    </tr>

                    <!-- Body -->
                    <tr>
                        <td style="padding: 40px 30px;">
                            <p style="margin: 0 0 24px; font-size: 18px; line-height: 28px; color: #111827; font-weight: 600;">
                                ¡Hola {{user.first_name}}!
                            </p>

                            <p style="margin: 0 0 32px; font-size: 16px; line-height: 24px; color: #374151;">
                                Hemos recibido tu solicitud de crédito y está siendo procesada por nuestro equipo.
                            </p>

                            <!-- Application Details Box -->
                            <table width="100%" cellpadding="0" cellspacing="0" style="margin: 0 0 32px; background-color: #f9fafb; border-radius: 12px; padding: 24px; border: 1px solid #e5e7eb;">
                                <tr>
                                    <td>
                                        <p style="margin: 0 0 16px; font-size: 14px; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px;">
                                            Detalles de tu Solicitud
                                        </p>

                                        <table width="100%" cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td style="padding: 8px 0; font-size: 14px; color: #6b7280; border-bottom: 1px solid #e5e7eb;">
                                                    Folio
                                                </td>
                                                <td style="padding: 8px 0; font-size: 14px; font-weight: 600; color: #111827; text-align: right; border-bottom: 1px solid #e5e7eb;">
                                                    {{application.folio}}
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 8px 0; font-size: 14px; color: #6b7280; border-bottom: 1px solid #e5e7eb;">
                                                    Monto Solicitado
                                                </td>
                                                <td style="padding: 8px 0; font-size: 14px; font-weight: 600; color: #111827; text-align: right; border-bottom: 1px solid #e5e7eb;">
                                                    ${{currency application.amount}} MXN
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 8px 0; font-size: 14px; color: #6b7280; border-bottom: 1px solid #e5e7eb;">
                                                    Plazo
                                                </td>
                                                <td style="padding: 8px 0; font-size: 14px; font-weight: 600; color: #111827; text-align: right; border-bottom: 1px solid #e5e7eb;">
                                                    {{application.term_months}} meses
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 8px 0; font-size: 14px; color: #6b7280;">
                                                    Producto
                                                </td>
                                                <td style="padding: 8px 0; font-size: 14px; font-weight: 600; color: #111827; text-align: right;">
                                                    {{application.product_name}}
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <!-- Next Steps -->
                            <table width="100%" cellpadding="0" cellspacing="0" style="margin: 0 0 32px;">
                                <tr>
                                    <td>
                                        <p style="margin: 0 0 16px; font-size: 16px; font-weight: 600; color: #111827;">
                                            📋 Próximos Pasos
                                        </p>
                                        <ol style="margin: 0; padding-left: 20px; font-size: 14px; color: #374151; line-height: 24px;">
                                            <li style="margin-bottom: 8px;">Revisaremos tu solicitud en las próximas <strong>24-48 horas</strong></li>
                                            <li style="margin-bottom: 8px;">Te contactaremos si necesitamos documentos adicionales</li>
                                            <li style="margin-bottom: 8px;">Recibirás una notificación con la resolución</li>
                                        </ol>
                                    </td>
                                </tr>
                            </table>

                            <!-- Contact Info -->
                            <table width="100%" cellpadding="0" cellspacing="0" style="margin: 0 0 24px; background-color: #eff6ff; border-radius: 12px; padding: 20px; border-left: 4px solid #3b82f6;">
                                <tr>
                                    <td>
                                        <p style="margin: 0 0 8px; font-size: 14px; font-weight: 600; color: #1e40af;">
                                            💬 ¿Tienes dudas?
                                        </p>
                                        <p style="margin: 0; font-size: 14px; color: #1e3a8a; line-height: 20px;">
                                            Contáctanos en: <strong>{{tenant.phone}}</strong>
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin: 0; font-size: 14px; line-height: 20px; color: #6b7280;">
                                Saludos cordiales,<br>
                                <strong>Equipo {{tenant.name}}</strong>
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f9fafb; padding: 20px 30px; border-top: 1px solid #e5e7eb;">
                            <p style="margin: 0; font-size: 12px; line-height: 16px; color: #9ca3af; text-align: center;">
                                Este es un correo automático, por favor no respondas a este mensaje.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';
    }

    /**
     * Get Documents Pending email HTML template
     */
    public static function getDocumentsPendingHtml(): string
    {
        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif; background-color: #f3f4f6;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f3f4f6; padding: 40px 0;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 16px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); overflow: hidden;">
                    <!-- Header -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); padding: 40px 30px; text-align: center;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 28px; font-weight: 700;">📄 Documentos Pendientes</h1>
                        </td>
                    </tr>

                    <!-- Body -->
                    <tr>
                        <td style="padding: 40px 30px;">
                            <p style="margin: 0 0 24px; font-size: 18px; line-height: 28px; color: #111827; font-weight: 600;">
                                Hola {{user.first_name}},
                            </p>

                            <p style="margin: 0 0 32px; font-size: 16px; line-height: 24px; color: #374151;">
                                Estamos revisando tu solicitud <strong>{{application.folio}}</strong> y necesitamos algunos documentos adicionales para continuar con el proceso.
                            </p>

                            <!-- Action Required Box -->
                            <table width="100%" cellpadding="0" cellspacing="0" style="margin: 0 0 32px; background-color: #fef3c7; border-radius: 12px; padding: 24px; border-left: 4px solid #f59e0b;">
                                <tr>
                                    <td>
                                        <p style="margin: 0 0 12px; font-size: 14px; font-weight: 600; color: #92400e;">
                                            ⚡ Acción Requerida
                                        </p>
                                        <p style="margin: 0; font-size: 14px; color: #78350f; line-height: 20px;">
                                            Por favor sube los documentos lo antes posible para agilizar tu solicitud.
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            <!-- CTA Button -->
                            <table width="100%" cellpadding="0" cellspacing="0" style="margin: 0 0 32px;">
                                <tr>
                                    <td align="center">
                                        <a href="{{tenant.app_url}}/aplicante/documentos" style="display: inline-block; padding: 14px 32px; background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: #ffffff; text-decoration: none; font-size: 16px; font-weight: 600; border-radius: 8px; box-shadow: 0 2px 4px rgba(245, 158, 11, 0.3);">
                                            Subir Documentos
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin: 0 0 24px; font-size: 14px; line-height: 20px; color: #6b7280;">
                                Si tienes problemas para subir los documentos, contáctanos en: <strong>{{tenant.phone}}</strong>
                            </p>

                            <p style="margin: 0; font-size: 14px; line-height: 20px; color: #6b7280;">
                                Saludos cordiales,<br>
                                <strong>Equipo {{tenant.name}}</strong>
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f9fafb; padding: 20px 30px; border-top: 1px solid #e5e7eb;">
                            <p style="margin: 0; font-size: 12px; line-height: 16px; color: #9ca3af; text-align: center;">
                                Este es un correo automático, por favor no respondas a este mensaje.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';
    }

    /**
     * Get Application Approved email HTML template
     */
    public static function getApplicationApprovedHtml(): string
    {
        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif; background-color: #f3f4f6;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f3f4f6; padding: 40px 0;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 16px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); overflow: hidden;">
                    <!-- Header -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); padding: 50px 30px; text-align: center;">
                            <div style="font-size: 48px; margin-bottom: 16px;">🎉</div>
                            <h1 style="margin: 0; color: #ffffff; font-size: 32px; font-weight: 700;">¡FELICIDADES!</h1>
                            <p style="margin: 8px 0 0; color: rgba(255, 255, 255, 0.95); font-size: 18px;">
                                Tu solicitud ha sido APROBADA
                            </p>
                        </td>
                    </tr>

                    <!-- Body -->
                    <tr>
                        <td style="padding: 40px 30px;">
                            <p style="margin: 0 0 32px; font-size: 18px; line-height: 28px; color: #111827; font-weight: 600; text-align: center;">
                                Hola {{user.first_name}} {{user.last_name}},
                            </p>

                            <p style="margin: 0 0 32px; font-size: 16px; line-height: 24px; color: #374151; text-align: center;">
                                Nos complace informarte que tu solicitud de crédito ha sido <strong style="color: #10b981;">APROBADA</strong> ✅
                            </p>

                            <!-- Credit Details Box -->
                            <table width="100%" cellpadding="0" cellspacing="0" style="margin: 0 0 32px; background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%); border-radius: 16px; padding: 28px; border: 2px solid #10b981;">
                                <tr>
                                    <td>
                                        <p style="margin: 0 0 20px; font-size: 14px; font-weight: 600; color: #065f46; text-transform: uppercase; letter-spacing: 0.5px; text-align: center;">
                                            Detalles de tu Crédito
                                        </p>

                                        <table width="100%" cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td style="padding: 12px 0; font-size: 15px; color: #047857; border-bottom: 1px solid #a7f3d0;">
                                                    Folio
                                                </td>
                                                <td style="padding: 12px 0; font-size: 15px; font-weight: 700; color: #065f46; text-align: right; border-bottom: 1px solid #a7f3d0;">
                                                    {{application.folio}}
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 12px 0; font-size: 15px; color: #047857; border-bottom: 1px solid #a7f3d0;">
                                                    Monto Aprobado
                                                </td>
                                                <td style="padding: 12px 0; font-size: 18px; font-weight: 700; color: #10b981; text-align: right; border-bottom: 1px solid #a7f3d0;">
                                                    ${{currency application.amount}} MXN
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 12px 0; font-size: 15px; color: #047857; border-bottom: 1px solid #a7f3d0;">
                                                    Plazo
                                                </td>
                                                <td style="padding: 12px 0; font-size: 15px; font-weight: 700; color: #065f46; text-align: right; border-bottom: 1px solid #a7f3d0;">
                                                    {{application.term_months}} meses
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 12px 0; font-size: 15px; color: #047857;">
                                                    Tasa de Interés
                                                </td>
                                                <td style="padding: 12px 0; font-size: 15px; font-weight: 700; color: #065f46; text-align: right;">
                                                    {{application.interest_rate}}%
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <!-- Next Steps -->
                            <table width="100%" cellpadding="0" cellspacing="0" style="margin: 0 0 32px;">
                                <tr>
                                    <td>
                                        <p style="margin: 0 0 16px; font-size: 18px; font-weight: 700; color: #111827;">
                                            🚀 Próximos Pasos
                                        </p>
                                        <table width="100%" cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td style="padding: 12px 0; vertical-align: top; width: 40px;">
                                                    <div style="width: 32px; height: 32px; background: linear-gradient(135deg, #10b981 0%, #059669 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #ffffff; font-weight: 700; font-size: 16px;">
                                                        1
                                                    </div>
                                                </td>
                                                <td style="padding: 12px 0 12px 12px;">
                                                    <p style="margin: 0; font-size: 15px; color: #111827; font-weight: 600;">
                                                        Contacto (24 horas)
                                                    </p>
                                                    <p style="margin: 4px 0 0; font-size: 14px; color: #6b7280; line-height: 20px;">
                                                        Te contactaremos para coordinar los siguientes pasos
                                                    </p>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 12px 0; vertical-align: top; width: 40px;">
                                                    <div style="width: 32px; height: 32px; background: linear-gradient(135deg, #10b981 0%, #059669 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #ffffff; font-weight: 700; font-size: 16px;">
                                                        2
                                                    </div>
                                                </td>
                                                <td style="padding: 12px 0 12px 12px;">
                                                    <p style="margin: 0; font-size: 15px; color: #111827; font-weight: 600;">
                                                        Firma de Contrato
                                                    </p>
                                                    <p style="margin: 4px 0 0; font-size: 14px; color: #6b7280; line-height: 20px;">
                                                        Revisaremos y firmaremos el contrato de crédito
                                                    </p>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 12px 0; vertical-align: top; width: 40px;">
                                                    <div style="width: 32px; height: 32px; background: linear-gradient(135deg, #10b981 0%, #059669 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #ffffff; font-weight: 700; font-size: 16px;">
                                                        3
                                                    </div>
                                                </td>
                                                <td style="padding: 12px 0 12px 12px;">
                                                    <p style="margin: 0; font-size: 15px; color: #111827; font-weight: 600;">
                                                        Desembolso (24-48 horas)
                                                    </p>
                                                    <p style="margin: 4px 0 0; font-size: 14px; color: #6b7280; line-height: 20px;">
                                                        Recibirás el monto en tu cuenta bancaria
                                                    </p>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <!-- Contact Info -->
                            <table width="100%" cellpadding="0" cellspacing="0" style="margin: 0 0 32px; background-color: #eff6ff; border-radius: 12px; padding: 20px; border-left: 4px solid #3b82f6;">
                                <tr>
                                    <td>
                                        <p style="margin: 0 0 8px; font-size: 14px; font-weight: 600; color: #1e40af;">
                                            💬 ¿Tienes dudas?
                                        </p>
                                        <p style="margin: 0; font-size: 14px; color: #1e3a8a; line-height: 20px;">
                                            Estamos aquí para ayudarte: <strong>{{tenant.phone}}</strong>
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin: 0 0 24px; font-size: 16px; line-height: 24px; color: #374151; text-align: center;">
                                ¡Gracias por confiar en <strong>{{tenant.name}}</strong>!
                            </p>

                            <p style="margin: 0; font-size: 14px; line-height: 20px; color: #6b7280; text-align: center;">
                                Saludos cordiales,<br>
                                <strong>Equipo {{tenant.name}}</strong>
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f9fafb; padding: 20px 30px; border-top: 1px solid #e5e7eb;">
                            <p style="margin: 0; font-size: 12px; line-height: 16px; color: #9ca3af; text-align: center;">
                                Este es un correo automático, por favor no respondas a este mensaje.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';
    }

    /**
     * Get Application Rejected email HTML template
     */
    public static function getApplicationRejectedHtml(): string
    {
        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif; background-color: #f3f4f6;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f3f4f6; padding: 40px 0;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 16px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); overflow: hidden;">
                    <!-- Header -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); padding: 40px 30px; text-align: center;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 28px; font-weight: 700;">Actualización de Solicitud</h1>
                        </td>
                    </tr>

                    <!-- Body -->
                    <tr>
                        <td style="padding: 40px 30px;">
                            <p style="margin: 0 0 24px; font-size: 18px; line-height: 28px; color: #111827; font-weight: 600;">
                                Hola {{user.first_name}} {{user.last_name}},
                            </p>

                            <p style="margin: 0 0 24px; font-size: 16px; line-height: 24px; color: #374151;">
                                Lamentamos informarte que en esta ocasión tu solicitud de crédito <strong>{{application.folio}}</strong> no ha sido aprobada.
                            </p>

                            <p style="margin: 0 0 32px; font-size: 16px; line-height: 24px; color: #374151;">
                                Esta decisión se basa en nuestro análisis de crédito y políticas internas.
                            </p>

                            <!-- Info Box -->
                            <table width="100%" cellpadding="0" cellspacing="0" style="margin: 0 0 32px; background-color: #eff6ff; border-radius: 12px; padding: 24px; border-left: 4px solid #3b82f6;">
                                <tr>
                                    <td>
                                        <p style="margin: 0 0 12px; font-size: 16px; font-weight: 600; color: #1e40af;">
                                            💡 ¿Puedo volver a solicitar?
                                        </p>
                                        <p style="margin: 0; font-size: 14px; color: #1e3a8a; line-height: 22px;">
                                            ¡Por supuesto! Te invitamos a intentarlo después de <strong>90 días</strong>, cuando tu situación financiera pueda haber mejorado.
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            <!-- Contact Info -->
                            <table width="100%" cellpadding="0" cellspacing="0" style="margin: 0 0 32px; background-color: #f9fafb; border-radius: 12px; padding: 20px;">
                                <tr>
                                    <td>
                                        <p style="margin: 0 0 12px; font-size: 14px; font-weight: 600; color: #374151;">
                                            ¿Tienes dudas sobre esta decisión?
                                        </p>
                                        <p style="margin: 0; font-size: 14px; color: #6b7280; line-height: 20px;">
                                            Contáctanos:<br>
                                            📞 <strong>{{tenant.phone}}</strong><br>
                                            📧 <strong>{{tenant.email}}</strong>
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin: 0 0 8px; font-size: 15px; line-height: 22px; color: #374151;">
                                Agradecemos tu interés en <strong>{{tenant.name}}</strong>.
                            </p>

                            <p style="margin: 0; font-size: 14px; line-height: 20px; color: #6b7280;">
                                Saludos cordiales,<br>
                                <strong>Equipo {{tenant.name}}</strong>
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f9fafb; padding: 20px 30px; border-top: 1px solid #e5e7eb;">
                            <p style="margin: 0; font-size: 12px; line-height: 16px; color: #9ca3af; text-align: center;">
                                Este es un correo automático, por favor no respondas a este mensaje.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';
    }

    /**
     * Get Corrections Requested email HTML template
     */
    public static function getCorrectionsRequestedHtml(): string
    {
        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif; background-color: #f3f4f6;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f3f4f6; padding: 40px 0;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 16px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); overflow: hidden;">
                    <!-- Header -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%); padding: 40px 30px; text-align: center;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 28px; font-weight: 700;">✏️ Correcciones Requeridas</h1>
                        </td>
                    </tr>

                    <!-- Body -->
                    <tr>
                        <td style="padding: 40px 30px;">
                            <p style="margin: 0 0 24px; font-size: 18px; line-height: 28px; color: #111827; font-weight: 600;">
                                Hola {{user.first_name}},
                            </p>

                            <p style="margin: 0 0 32px; font-size: 16px; line-height: 24px; color: #374151;">
                                Estamos revisando tu solicitud <strong>{{application.folio}}</strong> y necesitamos que corrijas algunos datos para poder continuar con el proceso.
                            </p>

                            <!-- Action Required Box -->
                            <table width="100%" cellpadding="0" cellspacing="0" style="margin: 0 0 32px; background-color: #fef3c7; border-radius: 12px; padding: 24px; border-left: 4px solid #f59e0b;">
                                <tr>
                                    <td>
                                        <p style="margin: 0 0 12px; font-size: 14px; font-weight: 600; color: #92400e;">
                                            ⚡ Acción Requerida
                                        </p>
                                        <p style="margin: 0; font-size: 14px; color: #78350f; line-height: 20px;">
                                            Por favor ingresa a tu cuenta y revisa los campos marcados para corrección.
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            <!-- CTA Button -->
                            <table width="100%" cellpadding="0" cellspacing="0" style="margin: 0 0 32px;">
                                <tr>
                                    <td align="center">
                                        <a href="{{tenant.app_url}}/aplicante/perfil" style="display: inline-block; padding: 14px 32px; background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%); color: #ffffff; text-decoration: none; font-size: 16px; font-weight: 600; border-radius: 8px; box-shadow: 0 2px 4px rgba(139, 92, 246, 0.3);">
                                            Revisar mi Solicitud
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin: 0; font-size: 14px; line-height: 20px; color: #6b7280;">
                                Saludos cordiales,<br>
                                <strong>Equipo {{tenant.name}}</strong>
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f9fafb; padding: 20px 30px; border-top: 1px solid #e5e7eb;">
                            <p style="margin: 0; font-size: 12px; line-height: 16px; color: #9ca3af; text-align: center;">
                                Este es un correo automático, por favor no respondas a este mensaje.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';
    }

    /**
     * Get Analyst Assigned email HTML template
     */
    public static function getAnalystAssignedHtml(): string
    {
        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif; background-color: #f3f4f6;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f3f4f6; padding: 40px 0;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 16px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); overflow: hidden;">
                    <!-- Header -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); padding: 40px 30px; text-align: center;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 28px; font-weight: 700;">📋 Nueva Solicitud Asignada</h1>
                        </td>
                    </tr>

                    <!-- Body -->
                    <tr>
                        <td style="padding: 40px 30px;">
                            <p style="margin: 0 0 24px; font-size: 18px; line-height: 28px; color: #111827; font-weight: 600;">
                                Hola {{staff.first_name}},
                            </p>

                            <p style="margin: 0 0 32px; font-size: 16px; line-height: 24px; color: #374151;">
                                Se te ha asignado una nueva solicitud de crédito para revisión.
                            </p>

                            <!-- Application Details Box -->
                            <table width="100%" cellpadding="0" cellspacing="0" style="margin: 0 0 32px; background-color: #eff6ff; border-radius: 12px; padding: 24px; border: 1px solid #bfdbfe;">
                                <tr>
                                    <td>
                                        <p style="margin: 0 0 16px; font-size: 14px; font-weight: 600; color: #1e40af; text-transform: uppercase; letter-spacing: 0.5px;">
                                            Detalles de la Solicitud
                                        </p>

                                        <table width="100%" cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td style="padding: 8px 0; font-size: 14px; color: #1e3a8a; border-bottom: 1px solid #bfdbfe;">
                                                    Folio
                                                </td>
                                                <td style="padding: 8px 0; font-size: 14px; font-weight: 600; color: #1e40af; text-align: right; border-bottom: 1px solid #bfdbfe;">
                                                    {{application.folio}}
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 8px 0; font-size: 14px; color: #1e3a8a; border-bottom: 1px solid #bfdbfe;">
                                                    Solicitante
                                                </td>
                                                <td style="padding: 8px 0; font-size: 14px; font-weight: 600; color: #1e40af; text-align: right; border-bottom: 1px solid #bfdbfe;">
                                                    {{user.first_name}} {{user.last_name}}
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 8px 0; font-size: 14px; color: #1e3a8a; border-bottom: 1px solid #bfdbfe;">
                                                    Monto
                                                </td>
                                                <td style="padding: 8px 0; font-size: 14px; font-weight: 600; color: #1e40af; text-align: right; border-bottom: 1px solid #bfdbfe;">
                                                    ${{currency application.amount}} MXN
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 8px 0; font-size: 14px; color: #1e3a8a; border-bottom: 1px solid #bfdbfe;">
                                                    Plazo
                                                </td>
                                                <td style="padding: 8px 0; font-size: 14px; font-weight: 600; color: #1e40af; text-align: right; border-bottom: 1px solid #bfdbfe;">
                                                    {{application.term_months}} meses
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 8px 0; font-size: 14px; color: #1e3a8a;">
                                                    Producto
                                                </td>
                                                <td style="padding: 8px 0; font-size: 14px; font-weight: 600; color: #1e40af; text-align: right;">
                                                    {{application.product_name}}
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <!-- CTA Button -->
                            <table width="100%" cellpadding="0" cellspacing="0" style="margin: 0 0 32px;">
                                <tr>
                                    <td align="center">
                                        <a href="{{tenant.app_url}}/admin/solicitudes/{{application.id}}" style="display: inline-block; padding: 14px 32px; background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); color: #ffffff; text-decoration: none; font-size: 16px; font-weight: 600; border-radius: 8px; box-shadow: 0 2px 4px rgba(59, 130, 246, 0.3);">
                                            Ver Solicitud
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin: 0; font-size: 14px; line-height: 20px; color: #6b7280;">
                                Saludos,<br>
                                <strong>Sistema {{tenant.name}}</strong>
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f9fafb; padding: 20px 30px; border-top: 1px solid #e5e7eb;">
                            <p style="margin: 0; font-size: 12px; line-height: 16px; color: #9ca3af; text-align: center;">
                                Este es un correo automático, por favor no respondas a este mensaje.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';
    }

    /**
     * Get Profile Incomplete Reminder email HTML template
     */
    public static function getProfileIncompleteHtml(): string
    {
        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif; background-color: #f3f4f6;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f3f4f6; padding: 40px 0;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 16px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); overflow: hidden;">
                    <!-- Header -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #ec4899 0%, #db2777 100%); padding: 40px 30px; text-align: center;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 28px; font-weight: 700;">⏰ Completa tu Perfil</h1>
                        </td>
                    </tr>

                    <!-- Body -->
                    <tr>
                        <td style="padding: 40px 30px;">
                            <p style="margin: 0 0 24px; font-size: 18px; line-height: 28px; color: #111827; font-weight: 600;">
                                Hola {{user.first_name}},
                            </p>

                            <p style="margin: 0 0 32px; font-size: 16px; line-height: 24px; color: #374151;">
                                Notamos que tu perfil en <strong>{{tenant.name}}</strong> está incompleto.
                            </p>

                            <!-- Reminder Box -->
                            <table width="100%" cellpadding="0" cellspacing="0" style="margin: 0 0 32px; background-color: #fef2f2; border-radius: 12px; padding: 24px; border-left: 4px solid #ef4444;">
                                <tr>
                                    <td>
                                        <p style="margin: 0 0 12px; font-size: 14px; font-weight: 600; color: #991b1b;">
                                            📝 Acción Pendiente
                                        </p>
                                        <p style="margin: 0; font-size: 14px; color: #7f1d1d; line-height: 20px;">
                                            Para poder solicitar un crédito, necesitas completar tu información personal.
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            <!-- CTA Button -->
                            <table width="100%" cellpadding="0" cellspacing="0" style="margin: 0 0 32px;">
                                <tr>
                                    <td align="center">
                                        <a href="{{tenant.app_url}}/aplicante/perfil" style="display: inline-block; padding: 14px 32px; background: linear-gradient(135deg, #ec4899 0%, #db2777 100%); color: #ffffff; text-decoration: none; font-size: 16px; font-weight: 600; border-radius: 8px; box-shadow: 0 2px 4px rgba(236, 72, 153, 0.3);">
                                            Completar mi Perfil
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin: 0; font-size: 14px; line-height: 20px; color: #6b7280;">
                                Saludos,<br>
                                <strong>Equipo {{tenant.name}}</strong>
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f9fafb; padding: 20px 30px; border-top: 1px solid #e5e7eb;">
                            <p style="margin: 0; font-size: 12px; line-height: 16px; color: #9ca3af; text-align: center;">
                                Este es un correo automático, por favor no respondas a este mensaje.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';
    }
}
