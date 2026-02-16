---
name: notification-system
description: Sistema de notificaciones multi-canal de LendusFind. Usar al trabajar con templates, envío de notificaciones, o configuración de canales.
---

# Notification System

## Cuándo aplica
Seguir esta guía al trabajar con templates de notificación, envío de mensajes (SMS, WhatsApp, Email, In-App), configuración de canales, o preferencias de usuario.

## Architecture Overview

```
NotificationService::send()
  → Busca template activo (tenant + event + channel)
  → TemplateRenderer::render() (Handlebars/LightnCandy)
  → Crea NotificationLog (status: PENDING)
  → Despacha SendNotificationJob (queued)
      → SMS: Twilio
      → WhatsApp: Twilio (whatsapp: prefix)
      → Email: SMTP integration > SendGrid > Mailgun > Laravel Mail
      → In-App: Solo marca como enviado
```

## Channels (NotificationChannel enum)

```php
enum NotificationChannel: string
{
    case SMS = 'SMS';           // Twilio, límite 160 chars
    case WHATSAPP = 'WHATSAPP'; // Twilio, límite 4096 chars
    case EMAIL = 'EMAIL';       // SMTP/SendGrid/Mailgun, soporta HTML
    case IN_APP = 'IN_APP';     // Solo log, soporta HTML
}
```

Métodos útiles: `supportsHtml()`, `requiresSubject()`, `characterLimit()`

## Events (NotificationEvent enum)

Eventos que disparan notificaciones:

```php
// Auth & Onboarding
OTP_SENT, USER_REGISTERED, PROFILE_COMPLETED

// Application Lifecycle
APPLICATION_CREATED, APPLICATION_SUBMITTED, APPLICATION_IN_REVIEW,
APPLICATION_APPROVED, APPLICATION_REJECTED, APPLICATION_DOCS_PENDING,
APPLICATION_CORRECTIONS_REQUESTED

// Documents
DOCUMENT_UPLOADED, DOCUMENT_APPROVED, DOCUMENT_REJECTED, DOCUMENTS_COMPLETE

// KYC
KYC_STARTED, KYC_COMPLETED, KYC_FAILED, REFERENCE_VERIFIED

// Staff
ANALYST_ASSIGNED, STATUS_CHANGED, COMMENT_ADDED

// System
WEBHOOK_FAILED, REMINDER_PENDING_DOCS, REMINDER_INCOMPLETE_PROFILE
```

Cada evento define: `label()`, `getAvailableVariables()`, `isEnabledByDefault()`, `getRecommendedChannels()`

## Template System

### NotificationTemplate model
- Scoped por tenant (`HasTenant`)
- Campos: `event`, `channel`, `subject`, `body`, `html_body`, `available_variables`, `priority`, `is_active`
- Scopes: `scopeActive()`, `scopeForEvent()`, `scopeForChannel()`

### Template Variables (Handlebars)
```handlebars
Hola {{user.first_name}},

Tu solicitud {{application.folio}} por ${{application.amount}} ha sido {{application.status_label}}.

Gracias,
{{tenant.name}}
```

Variables comunes: `user.*`, `tenant.*`, `application.*`, `document.*`, `otp.*`, `analyst.*`

### TemplateRenderer service
```php
$renderer = new TemplateRenderer();
$rendered = $renderer->render($template->body, [
    'user' => ['first_name' => 'Juan', 'last_name' => 'Pérez'],
    'application' => ['folio' => 'SOL-001', 'amount' => '50,000'],
    'tenant' => ['name' => 'FinanMex'],
]);
```

Usa LightnCandy (Handlebars PHP). Frontend usa Monaco Editor para edición de templates.

## NotificationService Usage

```php
$notificationService->send(
    event: NotificationEvent::APPLICATION_APPROVED,
    recipient: $applicantAccount,
    variables: [
        'user' => ['first_name' => $person->first_name],
        'application' => ['folio' => $app->folio, 'amount' => number_format($app->requested_amount)],
        'tenant' => ['name' => $tenant->name],
    ],
    channels: null,  // null = usa canales recomendados del evento
    tenant: $tenant,
);
```

## SendNotificationJob

Queued job con retry logic:
- `$tries = 3`, `$backoff = 60` (exponencial: 60, 120, 180 segundos)
- Email priority chain: TenantApiConfig SMTP > tenant settings provider > Laravel default
- Marca `NotificationLog` como SENT/FAILED con external_id

## NotificationLog statuses

```
PENDING → SENT → DELIVERED → READ
                → FAILED (con retry si retry_count < 3)
```

Métodos: `markAsSent()`, `markAsDelivered()`, `markAsRead()`, `markAsFailed()`, `incrementRetryCount()`

## Email Provider Configuration

Via `TenantApiConfig`:

| Provider | Credentials |
|----------|-------------|
| `smtp` | `api_key` (user) + `api_secret` (pass) + `extra_config` (host, port, encryption, from_name) |
| `sendgrid` | `api_key` + `from_email` |
| `mailgun` | `api_key` + `domain` + `from_email` |

`SmtpService::createFromConfig()` crea transport SMTP dinámico per-tenant.

## Frontend Components

- **NotificationTemplates.vue** — Lista de templates con filtros por event/channel
- **NotificationTemplateForm.vue** — Editor con Monaco (body) + TipTap (HTML) + preview
- **NotificationPreferences.vue** — Toggle por canal/evento para cada usuario
- **MonacoEditor.vue** — Editor de código para templates Handlebars
- **HtmlEditor.vue** — Editor TipTap para email HTML
- **NotificationPreview.vue** — Vista previa renderizada del template
