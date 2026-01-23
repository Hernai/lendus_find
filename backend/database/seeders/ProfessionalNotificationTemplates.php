<?php

namespace Database\Seeders;

use App\Enums\NotificationChannel;
use App\Enums\NotificationEvent;

/**
 * Professional notification templates with modern HTML design
 *
 * This class contains additional professional templates for the notification system.
 * These templates use responsive HTML email design with gradients, icons, and
 * proper formatting for a professional user experience.
 */
class ProfessionalNotificationTemplates
{
    /**
     * Get approval templates
     */
    public static function getApprovalTemplates(): array
    {
        return [
            // ==========================================
            // SOLICITUD APROBADA - PROFESSIONAL
            // ==========================================
            [
                'name' => 'Solicitud Aprobada - Email Premium',
                'event' => NotificationEvent::APPLICATION_APPROVED,
                'channel' => NotificationChannel::EMAIL,
                'priority' => 1,
                'subject' => '🎉 ¡Felicidades {{user.first_name}}! Tu crédito ha sido aprobado',
                'body' => '¡FELICIDADES {{user.first_name}} {{user.last_name}}!

Tenemos excelentes noticias: Tu solicitud de crédito {{application.folio}} ha sido APROBADA.

DETALLES DE TU CRÉDITO:
━━━━━━━━━━━━━━━━━━━━━━
💰 Monto aprobado: ${{currency application.amount}} MXN
📅 Plazo: {{application.term_months}} meses
📊 Tasa de interés: {{application.interest_rate}}%
💳 Pago mensual estimado: ${{currency application.monthly_payment}} MXN
━━━━━━━━━━━━━━━━━━━━━━

PRÓXIMOS PASOS:

1. Nuestro equipo te contactará en las próximas 24 horas
2. Firmarás el contrato de crédito
3. Recibirás el desembolso en tu cuenta

¡Gracias por confiar en {{tenant.name}}!

Atentamente,
Equipo de {{tenant.name}}
📞 {{tenant.phone}}
📧 {{tenant.email}}',
                'html_body' => '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif; background-color: #0f172a;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background: linear-gradient(180deg, #0f172a 0%, #1e293b 100%); padding: 40px 0;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 20px; box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3); overflow: hidden;">
                    <!-- Celebratory Header -->
                    <tr>
                        <td style="position: relative; background: linear-gradient(135deg, #10b981 0%, #059669 50%, #047857 100%); padding: 60px 30px; text-align: center;">
                            <!-- Confetti Effect (using emoji) -->
                            <div style="font-size: 32px; margin-bottom: 20px; letter-spacing: 10px;">
                                🎊 🎉 ✨ 🎊 🎉
                            </div>
                            <h1 style="margin: 0 0 16px; color: #ffffff; font-size: 36px; font-weight: 800; text-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);">
                                ¡FELICIDADES!
                            </h1>
                            <p style="margin: 0; color: #ffffff; font-size: 20px; font-weight: 500;">
                                Tu crédito ha sido aprobado
                            </p>
                            <div style="margin-top: 20px; font-size: 32px; letter-spacing: 10px;">
                                ✨ 🎉 🎊 ✨ 🎉
                            </div>
                        </td>
                    </tr>

                    <!-- Personalized Greeting -->
                    <tr>
                        <td style="padding: 40px 30px 20px;">
                            <table width="100%" cellpadding="0" cellspacing="0" style="background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); border-radius: 12px; padding: 20px; border-left: 6px solid #f59e0b;">
                                <tr>
                                    <td>
                                        <p style="margin: 0; font-size: 18px; font-weight: 700; color: #78350f;">
                                            Hola {{user.first_name}} {{user.last_name}},
                                        </p>
                                        <p style="margin: 8px 0 0; font-size: 16px; color: #92400e; line-height: 24px;">
                                            Tenemos excelentes noticias que compartir contigo. Después de revisar cuidadosamente tu solicitud, nos complace informarte que...
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Approval Notice -->
                    <tr>
                        <td style="padding: 0 30px 30px;">
                            <table width="100%" cellpadding="0" cellspacing="0" style="background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%); border-radius: 16px; padding: 30px; border: 3px solid #22c55e;">
                                <tr>
                                    <td align="center">
                                        <div style="width: 80px; height: 80px; background: linear-gradient(135deg, #10b981 0%, #059669 100%); border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 16px; box-shadow: 0 10px 30px rgba(16, 185, 129, 0.3);">
                                            <span style="font-size: 48px; line-height: 1;">✓</span>
                                        </div>
                                        <h2 style="margin: 0 0 8px; font-size: 28px; font-weight: 800; color: #065f46;">
                                            ¡TU SOLICITUD HA SIDO APROBADA!
                                        </h2>
                                        <p style="margin: 0; font-size: 18px; font-weight: 600; color: #047857;">
                                            Folio: {{application.folio}}
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Credit Details Card -->
                    <tr>
                        <td style="padding: 0 30px 30px;">
                            <h2 style="margin: 0 0 20px; font-size: 22px; font-weight: 700; color: #111827;">
                                📊 Detalles de tu Crédito
                            </h2>
                            <table width="100%" cellpadding="0" cellspacing="0" style="background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%); border-radius: 16px; overflow: hidden; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);">
                                <tr>
                                    <td style="padding: 24px;">
                                        <table width="100%" cellpadding="12" cellspacing="0">
                                            <tr>
                                                <td style="border-bottom: 1px solid #bae6fd; padding: 12px 0;">
                                                    <div style="display: flex; justify-content: space-between; align-items: center;">
                                                        <span style="font-size: 15px; color: #0369a1; font-weight: 600;">💰 Monto Aprobado</span>
                                                        <span style="font-size: 26px; color: #075985; font-weight: 800;">${{currency application.amount}}</span>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="border-bottom: 1px solid #bae6fd; padding: 12px 0;">
                                                    <div style="display: flex; justify-content: space-between;">
                                                        <span style="font-size: 15px; color: #0369a1; font-weight: 600;">📅 Plazo</span>
                                                        <span style="font-size: 18px; color: #075985; font-weight: 700;">{{application.term_months}} meses</span>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="border-bottom: 1px solid #bae6fd; padding: 12px 0;">
                                                    <div style="display: flex; justify-content: space-between;">
                                                        <span style="font-size: 15px; color: #0369a1; font-weight: 600;">📊 Tasa de Interés</span>
                                                        <span style="font-size: 18px; color: #075985; font-weight: 700;">{{application.interest_rate}}% anual</span>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 12px 0;">
                                                    <div style="display: flex; justify-content: space-between;">
                                                        <span style="font-size: 15px; color: #0369a1; font-weight: 600;">💳 Pago Mensual Estimado</span>
                                                        <span style="font-size: 20px; color: #075985; font-weight: 800;">${{currency application.monthly_payment}}</span>
                                                    </div>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Next Steps -->
                    <tr>
                        <td style="padding: 0 30px 30px;">
                            <h2 style="margin: 0 0 20px; font-size: 22px; font-weight: 700; color: #111827;">
                                🚀 Próximos Pasos
                            </h2>

                            <table width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="padding-bottom: 16px;">
                                        <table width="100%" cellpadding="0" cellspacing="0" style="background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%); border-radius: 12px; padding: 20px; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05); border-left: 4px solid #10b981;">
                                            <tr>
                                                <td style="width: 50px; vertical-align: top;">
                                                    <div style="width: 40px; height: 40px; background: linear-gradient(135deg, #10b981 0%, #059669 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; font-size: 18px;">1</div>
                                                </td>
                                                <td>
                                                    <p style="margin: 0 0 6px; font-size: 17px; font-weight: 700; color: #111827;">
                                                        Contacto de Nuestro Equipo
                                                    </p>
                                                    <p style="margin: 0; font-size: 14px; color: #6b7280; line-height: 20px;">
                                                        Uno de nuestros ejecutivos te contactará en las próximas <strong style="color: #10b981;">24 horas</strong> para coordinar los siguientes pasos.
                                                    </p>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>

                                <tr>
                                    <td style="padding-bottom: 16px;">
                                        <table width="100%" cellpadding="0" cellspacing="0" style="background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%); border-radius: 12px; padding: 20px; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05); border-left: 4px solid #3b82f6;">
                                            <tr>
                                                <td style="width: 50px; vertical-align: top;">
                                                    <div style="width: 40px; height: 40px; background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; font-size: 18px;">2</div>
                                                </td>
                                                <td>
                                                    <p style="margin: 0 0 6px; font-size: 17px; font-weight: 700; color: #111827;">
                                                        Firma del Contrato
                                                    </p>
                                                    <p style="margin: 0; font-size: 14px; color: #6b7280; line-height: 20px;">
                                                        Revisaremos juntos el contrato y responderemos todas tus dudas antes de la firma electrónica.
                                                    </p>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>

                                <tr>
                                    <td>
                                        <table width="100%" cellpadding="0" cellspacing="0" style="background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%); border-radius: 12px; padding: 20px; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05); border-left: 4px solid #f59e0b;">
                                            <tr>
                                                <td style="width: 50px; vertical-align: top;">
                                                    <div style="width: 40px; height: 40px; background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; font-size: 18px;">3</div>
                                                </td>
                                                <td>
                                                    <p style="margin: 0 0 6px; font-size: 17px; font-weight: 700; color: #111827;">
                                                        Desembolso del Crédito
                                                    </p>
                                                    <p style="margin: 0; font-size: 14px; color: #6b7280; line-height: 20px;">
                                                        Una vez firmado el contrato, el dinero será transferido a tu cuenta bancaria en <strong style="color: #f59e0b;">24-48 horas hábiles</strong>.
                                                    </p>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Important Notice -->
                    <tr>
                        <td style="padding: 0 30px 40px;">
                            <table width="100%" cellpadding="0" cellspacing="0" style="background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); border-radius: 12px; padding: 20px; border-left: 4px solid #f59e0b;">
                                <tr>
                                    <td>
                                        <p style="margin: 0 0 12px; font-size: 16px; font-weight: 700; color: #78350f;">
                                            📌 Importante
                                        </p>
                                        <p style="margin: 0; font-size: 14px; color: #92400e; line-height: 22px;">
                                            • Mantén tu teléfono disponible<br>
                                            • Ten a la mano tu identificación oficial<br>
                                            • Verifica que tu cuenta bancaria esté activa<br>
                                            • Cualquier duda, contáctanos inmediatamente
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #f9fafb 0%, #f3f4f6 100%); padding: 40px 30px; border-top: 1px solid #e5e7eb;">
                            <table width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td align="center" style="padding-bottom: 20px;">
                                        <h3 style="margin: 0 0 12px; font-size: 20px; font-weight: 700; color: #111827;">
                                            ¡Gracias por confiar en nosotros!
                                        </h3>
                                        <p style="margin: 0; font-size: 14px; color: #6b7280; line-height: 22px;">
                                            Si tienes alguna pregunta, nuestro equipo está aquí para ayudarte
                                        </p>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <table width="100%" cellpadding="10" cellspacing="0">
                                            <tr>
                                                <td align="center" style="font-size: 15px; color: #374151; font-weight: 600;">
                                                    📞 {{tenant.phone}}
                                                </td>
                                            </tr>
                                            <tr>
                                                <td align="center" style="font-size: 15px; color: #374151; font-weight: 600;">
                                                    📧 {{tenant.email}}
                                                </td>
                                            </tr>
                                            <tr>
                                                <td align="center" style="font-size: 15px;">
                                                    🌐 <a href="{{tenant.website}}" style="color: #2563eb; text-decoration: none; font-weight: 700;">{{tenant.website}}</a>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td align="center" style="padding-top: 30px;">
                                        <p style="margin: 0; font-size: 12px; color: #9ca3af; line-height: 18px;">
                                            © {{date "now"}} {{tenant.name}}. Todos los derechos reservados.<br>
                                            Este correo fue enviado automáticamente. Por favor no respondas.
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>',
            ],
            [
                'name' => 'Solicitud Aprobada - WhatsApp Premium',
                'event' => NotificationEvent::APPLICATION_APPROVED,
                'channel' => NotificationChannel::WHATSAPP,
                'priority' => 1,
                'subject' => null,
                'body' => '🎉✨ *¡FELICIDADES {{user.first_name}}!* ✨🎉

¡Tenemos EXCELENTES NOTICIAS! 🎊

Tu solicitud de crédito ha sido *APROBADA* ✅

┏━━━━━━━━━━━━━━━━━━━┓
┃  *DETALLES DEL CRÉDITO*  ┃
┗━━━━━━━━━━━━━━━━━━━┛

📋 Folio: `{{application.folio}}`
💰 Monto: *${{currency application.amount}} MXN*
📅 Plazo: *{{application.term_months}} meses*
📊 Tasa: *{{application.interest_rate}}%*
💳 Pago mensual: *${{currency application.monthly_payment}}*

━━━━━━━━━━━━━━━━━━━━

🚀 *PRÓXIMOS PASOS:*

1️⃣ *Contacto* (24 hrs)
   Nuestro equipo te llamará pronto

2️⃣ *Firma de Contrato*
   Revisión y firma electrónica

3️⃣ *Desembolso* (24-48 hrs)
   Transferencia a tu cuenta

━━━━━━━━━━━━━━━━━━━━

📌 *IMPORTANTE:*
✓ Mantén tu teléfono disponible
✓ Ten tu identificación a la mano
✓ Verifica tu cuenta bancaria

━━━━━━━━━━━━━━━━━━━━

💬 *¿Dudas?*
📞 {{tenant.phone}}
🌐 {{tenant.website}}

_¡Gracias por confiar en {{tenant.name}}!_

🎊 *¡Que disfrutes tu crédito!* 🎊',
            ],
            [
                'name' => 'Solicitud Aprobada - SMS Corto',
                'event' => NotificationEvent::APPLICATION_APPROVED,
                'channel' => NotificationChannel::SMS,
                'priority' => 1,
                'subject' => null,
                'body' => '🎉FELICIDADES! Tu credito {{application.folio}} por ${{currency application.amount}} fue APROBADO. Te contactaremos en 24hrs. {{tenant.name}}',
            ],
        ];
    }
}
