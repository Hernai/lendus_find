<?php

namespace Database\Seeders;

use App\Enums\NotificationChannel;
use App\Enums\NotificationEvent;
use App\Models\NotificationTemplate;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class NotificationTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get first tenant (or create if none exists)
        $tenant = Tenant::first();

        if (! $tenant) {
            $this->command->warn('No tenants found. Please seed tenants first.');

            return;
        }

        $this->command->info("Creating professional notification templates for tenant: {$tenant->name}");

        $templates = [
            // ==========================================
            // OTP TEMPLATES - SEGURIDAD
            // ==========================================
            [
                'name' => 'Código de Verificación - SMS',
                'event' => NotificationEvent::OTP_SENT,
                'channel' => NotificationChannel::SMS,
                'priority' => 1,
                'subject' => null,
                'body' => '{{tenant.name}}: Tu código de verificación es {{otp.code}}. Válido por {{otp.expires_in}} min. No lo compartas. Si no solicitaste este código, ignora este mensaje.',
            ],
            [
                'name' => 'Código de Verificación - WhatsApp',
                'event' => NotificationEvent::OTP_SENT,
                'channel' => NotificationChannel::WHATSAPP,
                'priority' => 1,
                'subject' => null,
                'body' => '🔐 *Código de Seguridad - {{tenant.name}}*

Tu código de verificación es:

*{{otp.code}}*

⏱️ Válido por *{{otp.expires_in}} minutos*

⚠️ *IMPORTANTE:*
• No compartas este código con nadie
• Nuestro personal NUNCA te pedirá este código
• Si no solicitaste este código, ignora este mensaje

{{tenant.name}}',
            ],
            [
                'name' => 'Código de Verificación - Email',
                'event' => NotificationEvent::OTP_SENT,
                'channel' => NotificationChannel::EMAIL,
                'priority' => 1,
                'subject' => 'Tu código de verificación - {{tenant.name}}',
                'body' => 'Tu código de verificación para {{tenant.name}} es: {{otp.code}}

Este código es válido por {{otp.expires_in}} minutos.

Por tu seguridad:
- No compartas este código con nadie
- Nuestro personal nunca te pedirá este código
- Si no solicitaste este código, ignora este mensaje

{{tenant.name}}',
                'html_body' => '<!DOCTYPE html>
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

                            <!-- Security Notice -->
                            <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #fee2e2; border-left: 4px solid #ef4444; border-radius: 8px; padding: 16px;">
                                <tr>
                                    <td>
                                        <p style="margin: 0 0 12px; font-size: 14px; font-weight: 600; color: #991b1b;">
                                            🛡️ Por tu seguridad
                                        </p>
                                        <ul style="margin: 0; padding-left: 20px; font-size: 14px; color: #7f1d1d; line-height: 20px;">
                                            <li style="margin-bottom: 4px;">Nunca compartas este código con nadie</li>
                                            <li style="margin-bottom: 4px;">Nuestro personal NUNCA te pedirá este código por teléfono o email</li>
                                            <li>Si no solicitaste este código, ignora este mensaje o contacta a soporte</li>
                                        </ul>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f9fafb; padding: 30px; text-align: center; border-top: 1px solid #e5e7eb;">
                            <p style="margin: 0 0 8px; font-size: 14px; color: #6b7280;">
                                Este es un correo automático, por favor no respondas.
                            </p>
                            <p style="margin: 0; font-size: 14px; font-weight: 600; color: #374151;">
                                {{tenant.name}}
                            </p>
                            <p style="margin: 4px 0 0; font-size: 14px; color: #6b7280;">
                                {{tenant.phone}} • {{tenant.email}}
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>',
            ],

            // ==========================================
            // SOLICITUD ENVIADA
            // ==========================================
            [
                'name' => 'Confirmación de Solicitud - Email Profesional',
                'event' => NotificationEvent::APPLICATION_SUBMITTED,
                'channel' => NotificationChannel::EMAIL,
                'priority' => 2,
                'subject' => '✓ Solicitud #{{application.folio}} recibida - {{tenant.name}}',
                'body' => 'Hola {{user.first_name}} {{user.last_name}},

¡Gracias por confiar en {{tenant.name}}!

Hemos recibido tu solicitud de crédito con los siguientes detalles:

━━━━━━━━━━━━━━━━━━━━━━
📋 Folio: {{application.folio}}
💰 Monto: ${{currency application.amount}} MXN
📅 Plazo: {{application.term_months}} meses
🏦 Producto: {{application.product_name}}
━━━━━━━━━━━━━━━━━━━━━━

¿Qué sigue?

1️⃣ Revisión inicial (24-48 hrs)
2️⃣ Verificación de documentos
3️⃣ Análisis de crédito
4️⃣ Decisión final

Te mantendremos informado sobre cada paso del proceso.

Si tienes alguna pregunta, no dudes en contactarnos:

📞 {{tenant.phone}}
📧 {{tenant.email}}
🌐 {{tenant.website}}

Saludos cordiales,
Equipo {{tenant.name}}',
                'html_body' => '<!DOCTYPE html>
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
                            <div style="width: 80px; height: 80px; background-color: rgba(255, 255, 255, 0.2); border-radius: 50%; margin: 0 auto 20px; display: flex; align-items: center; justify-content: center;">
                                <span style="font-size: 48px;">✓</span>
                            </div>
                            <h1 style="margin: 0 0 8px; color: #ffffff; font-size: 28px; font-weight: 700;">¡Solicitud Recibida!</h1>
                            <p style="margin: 0; color: rgba(255, 255, 255, 0.9); font-size: 16px;">Gracias por confiar en nosotros</p>
                        </td>
                    </tr>

                    <!-- Body -->
                    <tr>
                        <td style="padding: 40px 30px;">
                            <p style="margin: 0 0 24px; font-size: 16px; line-height: 24px; color: #374151;">
                                Hola <strong>{{user.first_name}} {{user.last_name}}</strong>,
                            </p>

                            <p style="margin: 0 0 32px; font-size: 16px; line-height: 24px; color: #374151;">
                                Hemos recibido exitosamente tu solicitud de crédito. A continuación los detalles:
                            </p>

                            <!-- Application Details -->
                            <table width="100%" cellpadding="0" cellspacing="0" style="margin: 0 0 32px; background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%); border-radius: 12px; overflow: hidden;">
                                <tr>
                                    <td style="padding: 24px;">
                                        <table width="100%" cellpadding="8" cellspacing="0">
                                            <tr>
                                                <td style="font-size: 14px; color: #0369a1; font-weight: 600; width: 40%;">📋 Número de Folio</td>
                                                <td style="font-size: 16px; color: #075985; font-weight: 700;">{{application.folio}}</td>
                                            </tr>
                                            <tr>
                                                <td style="font-size: 14px; color: #0369a1; font-weight: 600; padding-top: 8px;">💰 Monto Solicitado</td>
                                                <td style="font-size: 20px; color: #075985; font-weight: 700; padding-top: 8px;">${{currency application.amount}} MXN</td>
                                            </tr>
                                            <tr>
                                                <td style="font-size: 14px; color: #0369a1; font-weight: 600; padding-top: 8px;">📅 Plazo</td>
                                                <td style="font-size: 16px; color: #075985; font-weight: 600; padding-top: 8px;">{{application.term_months}} meses</td>
                                            </tr>
                                            <tr>
                                                <td style="font-size: 14px; color: #0369a1; font-weight: 600; padding-top: 8px;">🏦 Producto</td>
                                                <td style="font-size: 16px; color: #075985; font-weight: 600; padding-top: 8px;">{{application.product_name}}</td>
                                            </tr>
                                            <tr>
                                                <td style="font-size: 14px; color: #0369a1; font-weight: 600; padding-top: 8px;">📆 Fecha de Solicitud</td>
                                                <td style="font-size: 16px; color: #075985; font-weight: 600; padding-top: 8px;">{{date application.created_at}}</td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <!-- Process Steps -->
                            <h2 style="margin: 0 0 20px; font-size: 20px; font-weight: 700; color: #111827;">
                                ¿Qué sigue ahora?
                            </h2>

                            <table width="100%" cellpadding="0" cellspacing="0" style="margin: 0 0 32px;">
                                <tr>
                                    <td style="padding: 16px; background-color: #f9fafb; border-left: 4px solid #10b981; margin-bottom: 12px; border-radius: 8px;">
                                        <p style="margin: 0 0 4px; font-size: 16px; font-weight: 600; color: #111827;">
                                            1️⃣ Revisión Inicial
                                        </p>
                                        <p style="margin: 0; font-size: 14px; color: #6b7280;">
                                            Nuestro equipo revisará tu solicitud en las próximas 24-48 horas
                                        </p>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 16px; background-color: #f9fafb; border-left: 4px solid #3b82f6; margin-bottom: 12px; border-radius: 8px;">
                                        <p style="margin: 0 0 4px; font-size: 16px; font-weight: 600; color: #111827;">
                                            2️⃣ Verificación de Documentos
                                        </p>
                                        <p style="margin: 0; font-size: 14px; color: #6b7280;">
                                            Validaremos la documentación proporcionada
                                        </p>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 16px; background-color: #f9fafb; border-left: 4px solid #f59e0b; margin-bottom: 12px; border-radius: 8px;">
                                        <p style="margin: 0 0 4px; font-size: 16px; font-weight: 600; color: #111827;">
                                            3️⃣ Análisis de Crédito
                                        </p>
                                        <p style="margin: 0; font-size: 14px; color: #6b7280;">
                                            Evaluaremos tu perfil crediticio
                                        </p>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 16px; background-color: #f9fafb; border-left: 4px solid #8b5cf6; border-radius: 8px;">
                                        <p style="margin: 0 0 4px; font-size: 16px; font-weight: 600; color: #111827;">
                                            4️⃣ Decisión Final
                                        </p>
                                        <p style="margin: 0; font-size: 14px; color: #6b7280;">
                                            Te notificaremos el resultado de tu solicitud
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #fef3c7; border-radius: 8px; padding: 16px;">
                                <tr>
                                    <td>
                                        <p style="margin: 0 0 8px; font-size: 14px; font-weight: 600; color: #92400e;">
                                            💡 Consejo importante
                                        </p>
                                        <p style="margin: 0; font-size: 14px; color: #78350f; line-height: 20px;">
                                            Mantén tu teléfono disponible. Es posible que nuestro equipo necesite contactarte para aclarar algún detalle de tu solicitud.
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Contact Section -->
                    <tr>
                        <td style="background-color: #f9fafb; padding: 30px; border-top: 1px solid #e5e7eb;">
                            <p style="margin: 0 0 16px; font-size: 16px; font-weight: 600; color: #111827; text-align: center;">
                                ¿Tienes preguntas?
                            </p>
                            <table width="100%" cellpadding="8" cellspacing="0">
                                <tr>
                                    <td align="center" style="font-size: 14px; color: #6b7280;">
                                        📞 <strong style="color: #111827;">{{tenant.phone}}</strong>
                                    </td>
                                </tr>
                                <tr>
                                    <td align="center" style="font-size: 14px; color: #6b7280;">
                                        📧 <strong style="color: #111827;">{{tenant.email}}</strong>
                                    </td>
                                </tr>
                                <tr>
                                    <td align="center" style="font-size: 14px; color: #6b7280;">
                                        🌐 <a href="{{tenant.website}}" style="color: #2563eb; text-decoration: none; font-weight: 600;">{{tenant.website}}</a>
                                    </td>
                                </tr>
                            </table>
                            <p style="margin: 20px 0 0; font-size: 12px; color: #9ca3af; text-align: center; line-height: 18px;">
                                Este correo fue enviado automáticamente. Por favor no respondas a este mensaje.<br>
                                © {{date "now"}} {{tenant.name}}. Todos los derechos reservados.
                            </p>
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
                'name' => 'Confirmación de Solicitud - WhatsApp',
                'event' => NotificationEvent::APPLICATION_SUBMITTED,
                'channel' => NotificationChannel::WHATSAPP,
                'priority' => 2,
                'subject' => null,
                'body' => '✅ *¡Solicitud Recibida Exitosamente!*

Hola *{{user.first_name}}*, gracias por confiar en *{{tenant.name}}*.

📋 *Detalles de tu Solicitud:*

• Folio: `{{application.folio}}`
• Monto: *${{currency application.amount}} MXN*
• Plazo: *{{application.term_months}} meses*
• Producto: {{application.product_name}}
• Fecha: {{date application.created_at}}

━━━━━━━━━━━━━━━━━━━━

🔄 *Proceso de Evaluación:*

1️⃣ Revisión inicial (24-48 hrs)
2️⃣ Verificación de documentos
3️⃣ Análisis de crédito
4️⃣ Decisión final

Te mantendremos informado en cada etapa del proceso.

💡 *Importante:* Mantén tu teléfono disponible, es posible que necesitemos contactarte.

━━━━━━━━━━━━━━━━━━━━

¿Dudas o preguntas?
📞 {{tenant.phone}}
🌐 {{tenant.website}}

_Mensaje automático - {{tenant.name}}_',
            ],
            [
                'name' => 'Confirmación de Solicitud - SMS',
                'event' => NotificationEvent::APPLICATION_SUBMITTED,
                'channel' => NotificationChannel::SMS,
                'priority' => 3,
                'subject' => null,
                'body' => '{{tenant.name}}: Solicitud {{application.folio}} recibida por ${{currency application.amount}} a {{application.term_months}} meses. Te contactaremos pronto. Dudas: {{tenant.phone}}',
            ],

            // Continúa en el siguiente mensaje debido al límite de caracteres...
        ];

        foreach ($templates as $templateData) {
            $event = $templateData['event'];
            $channel = $templateData['channel'];

            // Get available variables for this event
            $availableVariables = $event->getAvailableVariables();

            NotificationTemplate::create([
                'tenant_id' => $tenant->id,
                'name' => $templateData['name'],
                'event' => $event->value,
                'channel' => $channel->value,
                'is_active' => $event->isEnabledByDefault(),
                'priority' => $templateData['priority'],
                'subject' => $templateData['subject'],
                'body' => $templateData['body'],
                'html_body' => $templateData['html_body'] ?? null,
                'available_variables' => $availableVariables,
            ]);

            $this->command->info("  ✓ {$templateData['name']}");
        }

        $this->command->info('Professional notification templates created successfully!');
    }
}
