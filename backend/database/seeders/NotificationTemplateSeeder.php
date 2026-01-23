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

        $this->command->info("Creating default notification templates for tenant: {$tenant->name}");

        $templates = [
            // OTP Templates
            [
                'name' => 'Código OTP - SMS',
                'event' => NotificationEvent::OTP_SENT,
                'channel' => NotificationChannel::SMS,
                'priority' => 1,
                'subject' => null,
                'body' => 'Tu código de verificación para {{tenant.name}} es: {{otp.code}}. Válido por {{otp.expires_in}} minutos.',
            ],
            [
                'name' => 'Código OTP - WhatsApp',
                'event' => NotificationEvent::OTP_SENT,
                'channel' => NotificationChannel::WHATSAPP,
                'priority' => 1,
                'subject' => null,
                'body' => '🔒 *{{tenant.name}}*

Tu código de verificación es:

*{{otp.code}}*

Válido por {{otp.expires_in}} minutos.

No compartas este código con nadie.',
            ],

            // Application Submitted
            [
                'name' => 'Solicitud Enviada - Email',
                'event' => NotificationEvent::APPLICATION_SUBMITTED,
                'channel' => NotificationChannel::EMAIL,
                'priority' => 3,
                'subject' => 'Solicitud recibida - {{application.folio}}',
                'body' => 'Hola {{user.first_name}},

Hemos recibido tu solicitud de crédito {{application.folio}} por ${{currency application.amount}} a {{application.term_months}} meses.

Nuestro equipo la está revisando y te contactaremos pronto.

Saludos,
{{tenant.name}}
{{tenant.phone}}',
                'html_body' => '<h2>Solicitud Recibida</h2>
<p>Hola <strong>{{user.first_name}}</strong>,</p>
<p>Hemos recibido tu solicitud de crédito <strong>{{application.folio}}</strong>:</p>
<ul>
<li>Producto: {{application.product_name}}</li>
<li>Monto: ${{currency application.amount}}</li>
<li>Plazo: {{application.term_months}} meses</li>
</ul>
<p>Nuestro equipo la está revisando y te contactaremos pronto.</p>
<hr>
<p style="color: #666; font-size: 12px;">
{{tenant.name}}<br>
{{tenant.phone}}<br>
<a href="{{tenant.website}}">{{tenant.website}}</a>
</p>',
            ],
            [
                'name' => 'Solicitud Enviada - In-App',
                'event' => NotificationEvent::APPLICATION_SUBMITTED,
                'channel' => NotificationChannel::IN_APP,
                'priority' => 3,
                'subject' => 'Solicitud enviada',
                'body' => 'Tu solicitud {{application.folio}} ha sido recibida y está siendo revisada por nuestro equipo.',
            ],

            // Application Approved
            [
                'name' => 'Solicitud Aprobada - SMS',
                'event' => NotificationEvent::APPLICATION_APPROVED,
                'channel' => NotificationChannel::SMS,
                'priority' => 1,
                'subject' => null,
                'body' => '¡Felicidades {{user.first_name}}! Tu solicitud {{application.folio}} ha sido APROBADA por ${{currency application.amount}}. Pronto nos contactaremos contigo. - {{tenant.name}}',
            ],
            [
                'name' => 'Solicitud Aprobada - WhatsApp',
                'event' => NotificationEvent::APPLICATION_APPROVED,
                'channel' => NotificationChannel::WHATSAPP,
                'priority' => 1,
                'subject' => null,
                'body' => '🎉 *¡Felicidades {{user.first_name}}!*

Tu solicitud de crédito ha sido *APROBADA*

📋 Folio: {{application.folio}}
💰 Monto: ${{currency application.amount}}
📅 Plazo: {{application.term_months}} meses

Pronto nos pondremos en contacto contigo para continuar con el proceso.

{{tenant.name}}
{{tenant.phone}}',
            ],
            [
                'name' => 'Solicitud Aprobada - Email',
                'event' => NotificationEvent::APPLICATION_APPROVED,
                'channel' => NotificationChannel::EMAIL,
                'priority' => 1,
                'subject' => '¡Felicidades! Tu solicitud ha sido aprobada',
                'body' => 'Hola {{user.first_name}},

¡Tenemos excelentes noticias!

Tu solicitud de crédito {{application.folio}} ha sido APROBADA por ${{currency application.amount}} a {{application.term_months}} meses.

Pronto nos pondremos en contacto contigo para continuar con el proceso de formalización.

¡Felicidades!

{{tenant.name}}
{{tenant.phone}}',
                'html_body' => '<div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 10px; text-align: center;">
<h1 style="margin: 0;">🎉 ¡Felicidades!</h1>
<p style="font-size: 18px; margin: 10px 0 0 0;">Tu solicitud ha sido aprobada</p>
</div>

<p>Hola <strong>{{user.first_name}}</strong>,</p>

<p>¡Tenemos excelentes noticias!</p>

<div style="background: #f0fdf4; border-left: 4px solid #22c55e; padding: 15px; margin: 20px 0;">
<p style="margin: 0;"><strong>Solicitud:</strong> {{application.folio}}</p>
<p style="margin: 5px 0 0 0;"><strong>Monto aprobado:</strong> ${{currency application.amount}}</p>
<p style="margin: 5px 0 0 0;"><strong>Plazo:</strong> {{application.term_months}} meses</p>
</div>

<p>Pronto nos pondremos en contacto contigo para continuar con el proceso de formalización.</p>

<hr>
<p style="color: #666; font-size: 12px;">
{{tenant.name}}<br>
{{tenant.phone}}<br>
<a href="{{tenant.website}}">{{tenant.website}}</a>
</p>',
            ],

            // Application Rejected
            [
                'name' => 'Solicitud Rechazada - Email',
                'event' => NotificationEvent::APPLICATION_REJECTED,
                'channel' => NotificationChannel::EMAIL,
                'priority' => 2,
                'subject' => 'Actualización sobre tu solicitud {{application.folio}}',
                'body' => 'Hola {{user.first_name}},

Lamentamos informarte que en esta ocasión tu solicitud {{application.folio}} no ha sido aprobada.

Esto no significa que no puedas aplicar nuevamente en el futuro. Te invitamos a intentarlo más adelante.

Si tienes dudas, contáctanos en {{tenant.phone}}.

Saludos,
{{tenant.name}}',
                'html_body' => '<h2>Actualización sobre tu Solicitud</h2>
<p>Hola <strong>{{user.first_name}}</strong>,</p>
<p>Lamentamos informarte que en esta ocasión tu solicitud <strong>{{application.folio}}</strong> no ha sido aprobada.</p>
<p>Esto no significa que no puedas aplicar nuevamente en el futuro. Te invitamos a intentarlo más adelante.</p>
<p>Si tienes dudas, contáctanos en <strong>{{tenant.phone}}</strong>.</p>
<hr>
<p style="color: #666; font-size: 12px;">
{{tenant.name}}<br>
{{tenant.phone}}<br>
<a href="{{tenant.website}}">{{tenant.website}}</a>
</p>',
            ],

            // Documents Pending
            [
                'name' => 'Documentos Pendientes - WhatsApp',
                'event' => NotificationEvent::APPLICATION_DOCS_PENDING,
                'channel' => NotificationChannel::WHATSAPP,
                'priority' => 3,
                'subject' => null,
                'body' => '📄 Hola {{user.first_name}},

Tu solicitud {{application.folio}} está en revisión pero faltan algunos documentos.

Por favor ingresa a tu cuenta y sube los documentos faltantes para continuar con el proceso.

{{tenant.name}}
{{tenant.phone}}',
            ],

            // Document Rejected
            [
                'name' => 'Documento Rechazado - In-App',
                'event' => NotificationEvent::DOCUMENT_REJECTED,
                'channel' => NotificationChannel::IN_APP,
                'priority' => 2,
                'subject' => 'Documento rechazado',
                'body' => 'Tu documento {{document.type}} ha sido rechazado. Motivo: {{document.rejection_reason}}. Por favor sube un nuevo documento.',
            ],
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

        $this->command->info('Default notification templates created successfully!');
    }
}
