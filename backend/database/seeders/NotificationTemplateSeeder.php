<?php

namespace Database\Seeders;

use App\Enums\NotificationChannel;
use App\Enums\NotificationEvent;
use App\Models\NotificationTemplate;
use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Database\Seeders\ProfessionalEmailTemplates;

class NotificationTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get all tenants
        $tenants = Tenant::all();

        if ($tenants->isEmpty()) {
            $this->command->warn('No tenants found. Please seed tenants first.');

            return;
        }

        $this->command->info("Creating professional notification templates for {$tenants->count()} tenant(s)");

        foreach ($tenants as $tenant) {
            $this->command->info("  → Tenant: {$tenant->name}");
            $this->createTemplatesForTenant($tenant);
        }

        $this->command->info('All professional notification templates created successfully!');
    }

    /**
     * Create templates for a specific tenant
     */
    private function createTemplatesForTenant(Tenant $tenant): void
    {

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
                'html_body' => ProfessionalEmailTemplates::getOtpEmailHtml(),
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

            // ==========================================
            // SOLICITUD EN REVISIÓN
            // ==========================================
            [
                'name' => 'Solicitud en Revisión - In-App',
                'event' => NotificationEvent::APPLICATION_IN_REVIEW,
                'channel' => NotificationChannel::IN_APP,
                'priority' => 3,
                'subject' => 'Solicitud en revisión',
                'body' => 'Tu solicitud {{application.folio}} está siendo revisada por nuestro equipo de analistas. Te notificaremos cuando tengamos novedades.',
            ],
            [
                'name' => 'Solicitud en Revisión - WhatsApp',
                'event' => NotificationEvent::APPLICATION_IN_REVIEW,
                'channel' => NotificationChannel::WHATSAPP,
                'priority' => 3,
                'subject' => null,
                'body' => '🔍 *Actualización de Solicitud*

Hola *{{user.first_name}}*,

Tu solicitud *{{application.folio}}* está siendo revisada por nuestro equipo de analistas de crédito.

⏱️ Tiempo estimado: 24-48 horas

Te mantendremos informado sobre el progreso.

{{tenant.name}}',
            ],

            // ==========================================
            // DOCUMENTOS PENDIENTES
            // ==========================================
            [
                'name' => 'Documentos Pendientes - Email',
                'event' => NotificationEvent::APPLICATION_DOCS_PENDING,
                'channel' => NotificationChannel::EMAIL,
                'priority' => 2,
                'subject' => '📄 Documentos pendientes - Solicitud {{application.folio}}',
                'body' => 'Hola {{user.first_name}},

Tu solicitud {{application.folio}} está siendo procesada, pero necesitamos que subas algunos documentos adicionales para continuar.

Documentos faltantes:
{{#each missing_documents}}
- {{this}}
{{/each}}

Por favor, ingresa a tu cuenta y sube los documentos lo antes posible para agilizar tu solicitud.

Saludos,
{{tenant.name}}',
            ],
            [
                'name' => 'Documentos Pendientes - WhatsApp',
                'event' => NotificationEvent::APPLICATION_DOCS_PENDING,
                'channel' => NotificationChannel::WHATSAPP,
                'priority' => 2,
                'subject' => null,
                'body' => '📄 *Documentos Pendientes*

Hola *{{user.first_name}}*,

Para continuar con tu solicitud *{{application.folio}}* necesitamos algunos documentos adicionales.

Por favor ingresa a tu cuenta y sube los documentos faltantes.

⚡ *Acción requerida*

{{tenant.name}}
{{tenant.phone}}',
            ],
            [
                'name' => 'Documento Rechazado - In-App',
                'event' => NotificationEvent::DOCUMENT_REJECTED,
                'channel' => NotificationChannel::IN_APP,
                'priority' => 2,
                'subject' => 'Documento rechazado',
                'body' => 'Tu documento {{document.type}} ha sido rechazado. Motivo: {{document.rejection_reason}}. Por favor sube un nuevo documento.',
            ],

            // ==========================================
            // SOLICITUD APROBADA - Se requieren más plantillas
            // ==========================================
            [
                'name' => 'Solicitud Aprobada - SMS',
                'event' => NotificationEvent::APPLICATION_APPROVED,
                'channel' => NotificationChannel::SMS,
                'priority' => 1,
                'subject' => null,
                'body' => '🎉 {{tenant.name}}: ¡APROBADO! Tu solicitud {{application.folio}} por ${{currency application.amount}} fue aprobada. Te contactaremos en 24hrs.',
            ],
            [
                'name' => 'Solicitud Aprobada - WhatsApp',
                'event' => NotificationEvent::APPLICATION_APPROVED,
                'channel' => NotificationChannel::WHATSAPP,
                'priority' => 1,
                'subject' => null,
                'body' => '🎉🎊 *¡FELICIDADES {{user.first_name}}!* 🎊🎉

Tu solicitud de crédito ha sido *APROBADA* ✅

┏━━━━━━━━━━━━━━━━━━━┓
┃  *DETALLES DEL CRÉDITO*  ┃
┗━━━━━━━━━━━━━━━━━━━┛

📋 Folio: `{{application.folio}}`
💰 Monto: *${{currency application.amount}} MXN*
📅 Plazo: *{{application.term_months}} meses*
📊 Tasa: *{{application.interest_rate}}%*

━━━━━━━━━━━━━━━━━━━━

🚀 *PRÓXIMOS PASOS:*

1️⃣ Contacto (24 hrs)
2️⃣ Firma de Contrato
3️⃣ Desembolso (24-48 hrs)

━━━━━━━━━━━━━━━━━━━━

💬 *¿Dudas?*
📞 {{tenant.phone}}

_¡Gracias por confiar en {{tenant.name}}!_',
            ],

            // ==========================================
            // SOLICITUD RECHAZADA
            // ==========================================
            [
                'name' => 'Solicitud Rechazada - Email',
                'event' => NotificationEvent::APPLICATION_REJECTED,
                'channel' => NotificationChannel::EMAIL,
                'priority' => 2,
                'subject' => 'Actualización sobre tu solicitud {{application.folio}}',
                'body' => 'Hola {{user.first_name}} {{user.last_name}},

Lamentamos informarte que en esta ocasión tu solicitud de crédito {{application.folio}} no ha sido aprobada.

Esta decisión se basa en nuestro análisis de crédito y políticas internas.

Esto no significa que no puedas aplicar nuevamente. Te invitamos a intentarlo después de 90 días, cuando tu situación financiera pueda haber mejorado.

Si tienes dudas sobre esta decisión, puedes contactarnos:
📞 {{tenant.phone}}
📧 {{tenant.email}}

Agradecemos tu interés en {{tenant.name}}.

Saludos cordiales,
Equipo {{tenant.name}}',
            ],
            [
                'name' => 'Solicitud Rechazada - WhatsApp',
                'event' => NotificationEvent::APPLICATION_REJECTED,
                'channel' => NotificationChannel::WHATSAPP,
                'priority' => 2,
                'subject' => null,
                'body' => 'Hola *{{user.first_name}}*,

Lamentamos informarte que tu solicitud *{{application.folio}}* no ha sido aprobada en esta ocasión.

Puedes volver a aplicar después de 90 días.

Si tienes dudas, contáctanos:
📞 {{tenant.phone}}

Gracias por tu interés en {{tenant.name}}.',
            ],
            [
                'name' => 'Solicitud Rechazada - SMS',
                'event' => NotificationEvent::APPLICATION_REJECTED,
                'channel' => NotificationChannel::SMS,
                'priority' => 2,
                'subject' => null,
                'body' => '{{tenant.name}}: Tu solicitud {{application.folio}} no fue aprobada en esta ocasión. Puedes volver a aplicar en 90 días. Dudas: {{tenant.phone}}',
            ],

            // ==========================================
            // CORRECCIONES SOLICITADAS
            // ==========================================
            [
                'name' => 'Correcciones Solicitadas - Email',
                'event' => NotificationEvent::APPLICATION_CORRECTIONS_REQUESTED,
                'channel' => NotificationChannel::EMAIL,
                'priority' => 2,
                'subject' => '✏️ Correcciones requeridas - {{application.folio}}',
                'body' => 'Hola {{user.first_name}},

Necesitamos que corrijas algunos datos en tu solicitud {{application.folio}} para poder continuar con el proceso.

Por favor ingresa a tu cuenta y revisa los campos marcados para corrección.

Saludos,
{{tenant.name}}',
            ],
            [
                'name' => 'Correcciones Solicitadas - WhatsApp',
                'event' => NotificationEvent::APPLICATION_CORRECTIONS_REQUESTED,
                'channel' => NotificationChannel::WHATSAPP,
                'priority' => 2,
                'subject' => null,
                'body' => '✏️ *Correcciones Requeridas*

Hola *{{user.first_name}}*,

Necesitamos que corrijas algunos datos en tu solicitud *{{application.folio}}*.

Por favor ingresa a tu cuenta.

{{tenant.name}}',
            ],

            // ==========================================
            // ANALISTA ASIGNADO (STAFF)
            // ==========================================
            [
                'name' => 'Analista Asignado - Email Staff',
                'event' => NotificationEvent::ANALYST_ASSIGNED,
                'channel' => NotificationChannel::EMAIL,
                'priority' => 2,
                'subject' => '📋 Nueva solicitud asignada - {{application.folio}}',
                'body' => 'Hola {{staff.first_name}},

Se te ha asignado una nueva solicitud de crédito para revisión:

Solicitud: {{application.folio}}
Solicitante: {{user.first_name}} {{user.last_name}}
Monto: ${{currency application.amount}} MXN
Plazo: {{application.term_months}} meses
Producto: {{application.product_name}}

Por favor revisa la solicitud en el panel administrativo.

{{tenant.name}}',
            ],
            [
                'name' => 'Analista Asignado - In-App Staff',
                'event' => NotificationEvent::ANALYST_ASSIGNED,
                'channel' => NotificationChannel::IN_APP,
                'priority' => 2,
                'subject' => 'Nueva solicitud asignada',
                'body' => 'Se te ha asignado la solicitud {{application.folio}} de {{user.first_name}} {{user.last_name}} por ${{currency application.amount}}.',
            ],

            // ==========================================
            // DOCUMENTOS APROBADOS
            // ==========================================
            [
                'name' => 'Documentos Completos - WhatsApp',
                'event' => NotificationEvent::DOCUMENTS_COMPLETE,
                'channel' => NotificationChannel::WHATSAPP,
                'priority' => 3,
                'subject' => null,
                'body' => '✅ *Documentos Aprobados*

Hola *{{user.first_name}}*,

Todos tus documentos han sido aprobados.

Tu solicitud *{{application.folio}}* está siendo evaluada por nuestro equipo.

{{tenant.name}}',
            ],
            [
                'name' => 'Documentos Completos - In-App',
                'event' => NotificationEvent::DOCUMENTS_COMPLETE,
                'channel' => NotificationChannel::IN_APP,
                'priority' => 3,
                'subject' => 'Documentos aprobados',
                'body' => 'Todos tus documentos han sido aprobados. Tu solicitud {{application.folio}} está siendo evaluada.',
            ],

            // ==========================================
            // RECORDATORIOS
            // ==========================================
            [
                'name' => 'Recordatorio Documentos Pendientes - WhatsApp',
                'event' => NotificationEvent::REMINDER_PENDING_DOCS,
                'channel' => NotificationChannel::WHATSAPP,
                'priority' => 4,
                'subject' => null,
                'body' => '⏰ *Recordatorio*

Hola *{{user.first_name}}*,

Aún tienes documentos pendientes en tu solicitud *{{application.folio}}*.

Por favor súbelos lo antes posible para continuar con tu proceso.

{{tenant.name}}',
            ],
            [
                'name' => 'Recordatorio Perfil Incompleto - Email',
                'event' => NotificationEvent::REMINDER_INCOMPLETE_PROFILE,
                'channel' => NotificationChannel::EMAIL,
                'priority' => 5,
                'subject' => '⏰ Completa tu perfil - {{tenant.name}}',
                'body' => 'Hola {{user.first_name}},

Notamos que tu perfil está incompleto.

Por favor ingresa a tu cuenta y completa tu información para poder solicitar un crédito.

Saludos,
{{tenant.name}}',
            ],

            // ==========================================
            // CONTRAOFERTA
            // ==========================================
            [
                'name' => 'Contraoferta - WhatsApp',
                'event' => NotificationEvent::APPLICATION_COUNTER_OFFERED,
                'channel' => NotificationChannel::WHATSAPP,
                'priority' => 2,
                'subject' => null,
                'body' => '💬 *Nueva propuesta - {{tenant.name}}*

Hola *{{user.first_name}}*,

Tenemos una propuesta para tu solicitud *{{application.folio}}*:

💰 Monto: *{{counter_offer.amount}}*
📅 Plazo: *{{counter_offer.term_months}} meses*
💵 Pago mensual: *{{counter_offer.monthly_payment}}*

Ingresa a tu cuenta para aceptarla o rechazarla.

{{tenant.name}}',
            ],
            [
                'name' => 'Contraoferta - Email',
                'event' => NotificationEvent::APPLICATION_COUNTER_OFFERED,
                'channel' => NotificationChannel::EMAIL,
                'priority' => 2,
                'subject' => 'Tenemos una propuesta para ti - {{tenant.name}}',
                'body' => 'Hola {{user.first_name}},

Revisamos tu solicitud {{application.folio}} y tenemos una propuesta:

- Monto: {{counter_offer.amount}}
- Plazo: {{counter_offer.term_months}} meses
- Pago mensual: {{counter_offer.monthly_payment}}

Ingresa a tu cuenta para aceptar o rechazar la propuesta.

Saludos,
{{tenant.name}}',
            ],
            [
                'name' => 'Contraoferta - In-App',
                'event' => NotificationEvent::APPLICATION_COUNTER_OFFERED,
                'channel' => NotificationChannel::IN_APP,
                'priority' => 2,
                'subject' => 'Tienes una nueva propuesta',
                'body' => 'Tenemos una propuesta para tu solicitud {{application.folio}}: {{counter_offer.amount}} a {{counter_offer.term_months}} meses ({{counter_offer.monthly_payment}}/mes). Ingresa para responder.',
            ],

            // ==========================================
            // SOLICITUD CANCELADA
            // ==========================================
            [
                'name' => 'Solicitud Cancelada - WhatsApp',
                'event' => NotificationEvent::APPLICATION_CANCELLED,
                'channel' => NotificationChannel::WHATSAPP,
                'priority' => 3,
                'subject' => null,
                'body' => '⚠️ *Solicitud cancelada - {{tenant.name}}*

Hola *{{user.first_name}}*,

Tu solicitud *{{application.folio}}* fue cancelada.

Si tienes dudas, contáctanos.

{{tenant.name}}',
            ],
            [
                'name' => 'Solicitud Cancelada - Email',
                'event' => NotificationEvent::APPLICATION_CANCELLED,
                'channel' => NotificationChannel::EMAIL,
                'priority' => 3,
                'subject' => 'Tu solicitud {{application.folio}} fue cancelada - {{tenant.name}}',
                'body' => 'Hola {{user.first_name}},

Tu solicitud {{application.folio}} fue cancelada.

Si consideras que esto es un error o tienes dudas, contáctanos.

Saludos,
{{tenant.name}}',
            ],
            [
                'name' => 'Solicitud Cancelada - In-App',
                'event' => NotificationEvent::APPLICATION_CANCELLED,
                'channel' => NotificationChannel::IN_APP,
                'priority' => 3,
                'subject' => 'Solicitud cancelada',
                'body' => 'Tu solicitud {{application.folio}} fue cancelada. Si tienes dudas, contáctanos.',
            ],

            // ==========================================
            // DOCUMENTO APROBADO
            // ==========================================
            [
                'name' => 'Documento Aprobado - In-App',
                'event' => NotificationEvent::DOCUMENT_APPROVED,
                'channel' => NotificationChannel::IN_APP,
                'priority' => 4,
                'subject' => 'Documento aprobado',
                'body' => 'Tu documento "{{document.type_label}}" fue aprobado.',
            ],

            // ==========================================
            // KYC COMPLETADO (identidad verificada)
            // ==========================================
            [
                'name' => 'Identidad Verificada - In-App',
                'event' => NotificationEvent::KYC_COMPLETED,
                'channel' => NotificationChannel::IN_APP,
                'priority' => 3,
                'subject' => 'Identidad verificada',
                'body' => 'Tu identidad fue verificada correctamente. ¡Ya puedes continuar con tu solicitud!',
            ],
            [
                'name' => 'Identidad Verificada - WhatsApp',
                'event' => NotificationEvent::KYC_COMPLETED,
                'channel' => NotificationChannel::WHATSAPP,
                'priority' => 3,
                'subject' => null,
                'body' => '✅ *Identidad verificada - {{tenant.name}}*

Hola *{{user.first_name}}*,

Tu identidad fue verificada correctamente. Ya puedes continuar con tu solicitud.

{{tenant.name}}',
            ],
        ];

        foreach ($templates as $templateData) {
            $event = $templateData['event'];
            $channel = $templateData['channel'];

            // Get available variables for this event
            $availableVariables = $event->getAvailableVariables();

            // Idempotente y seguro en producción: firstOrCreate respeta
            // ediciones del cliente. Si el template ya existe (mismo
            // tenant + event + channel + name), no toca su body/subject.
            // Solo crea los faltantes.
            NotificationTemplate::firstOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'event' => $event->value,
                    'channel' => $channel->value,
                    'name' => $templateData['name'],
                ],
                [
                    'is_active' => $event->isEnabledByDefault(),
                    'priority' => $templateData['priority'],
                    'subject' => $templateData['subject'],
                    'body' => $templateData['body'],
                    'html_body' => $templateData['html_body'] ?? null,
                    'available_variables' => $availableVariables,
                ],
            );

            // Don't output each template, too verbose for multiple tenants
        }
    }
}
