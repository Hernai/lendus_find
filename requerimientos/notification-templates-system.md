# Sistema de Gestión de Plantillas de Notificaciones Multi-Canal

## 1. Resumen Ejecutivo

Este documento especifica la arquitectura e implementación de un sistema de gestión de plantillas de notificaciones multi-canal para LendusFind. El sistema permitirá a cada tenant configurar plantillas personalizadas para notificaciones enviadas por SMS, WhatsApp, Email, y notificaciones en aplicación (in-app), disparadas por eventos del ciclo de vida de solicitudes.

**Objetivos:**
- Permitir configuración de plantillas por tenant sin modificar código
- Soportar múltiples canales de notificación (SMS, WhatsApp, Email, In-App)
- Sistema de variables dinámicas para personalización
- Editor visual para plantillas HTML (Email e In-App)
- Gestión de eventos disparadores (triggers)
- Sistema de fallback entre canales
- Preferencias de usuario por canal
- Auditoría completa de envíos

## 2. Arquitectura del Sistema

### 2.1 Componentes Principales

```
┌─────────────────────────────────────────────────────────────────┐
│                     APPLICATION LAYER                            │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐         │
│  │   Events     │→ │   Listeners  │→ │ Notification │         │
│  │  (Triggers)  │  │  (Handlers)  │  │   Service    │         │
│  └──────────────┘  └──────────────┘  └──────────────┘         │
└─────────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────────┐
│                    NOTIFICATION LAYER                            │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐         │
│  │  Template    │  │  Variable    │  │   Channel    │         │
│  │  Renderer    │  │  Resolver    │  │   Router     │         │
│  └──────────────┘  └──────────────┘  └──────────────┘         │
└─────────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────────┐
│                      DELIVERY LAYER                              │
│  ┌─────────┐  ┌──────────┐  ┌────────┐  ┌──────────────┐      │
│  │   SMS   │  │ WhatsApp │  │  Email │  │   Database   │      │
│  │ Channel │  │  Channel │  │ Channel│  │    Channel   │      │
│  └─────────┘  └──────────┘  └────────┘  └──────────────┘      │
│       ↓             ↓            ↓              ↓               │
│  ┌─────────┐  ┌──────────┐  ┌────────┐  ┌──────────────┐      │
│  │ Twilio  │  │  Twilio  │  │SendGrid│  │  PostgreSQL  │      │
│  │   API   │  │   API    │  │   API  │  │   (in-app)   │      │
│  └─────────┘  └──────────┘  └────────┘  └──────────────┘      │
└─────────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────────┐
│                       LOGGING LAYER                              │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐         │
│  │notification_ │  │   sms_logs   │  │   api_logs   │         │
│  │    logs      │  │              │  │              │         │
│  └──────────────┘  └──────────────┘  └──────────────┘         │
└─────────────────────────────────────────────────────────────────┘
```

### 2.2 Flujo de Procesamiento

```
1. Event Triggered (ej: Application Submitted)
   ↓
2. Event Listener → NotificationService::send()
   ↓
3. Load Template for (tenant_id, event, channel)
   ↓
4. Resolve Variables (user data, application data, etc.)
   ↓
5. Render Template (Blade for Email, plain text for SMS)
   ↓
6. Check User Preferences (opt-out, channel priority)
   ↓
7. Queue Notification Job (priority-based queue)
   ↓
8. Channel-Specific Delivery (via providers)
   ↓
9. Log Result (success/failure) to notification_logs
   ↓
10. Retry if failed (with exponential backoff)
```

## 3. Modelo de Datos

### 3.1 Tabla: `notification_templates`

Almacena las plantillas de notificaciones configurables por tenant.

```php
Schema::create('notification_templates', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->uuid('tenant_id');
    $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');

    // Template identification
    $table->string('event', 50); // APPLICATION_SUBMITTED, APPLICATION_APPROVED, etc.
    $table->string('channel', 20); // SMS, WHATSAPP, EMAIL, IN_APP
    $table->string('name', 100); // Friendly name for admin UI
    $table->text('description')->nullable(); // What this notification does

    // Template content
    $table->text('subject')->nullable(); // For EMAIL only
    $table->text('body'); // Main content (supports variables)
    $table->text('html_body')->nullable(); // For EMAIL and IN_APP (rich HTML)

    // Configuration
    $table->json('variables')->nullable(); // Available variables for this template
    $table->json('settings')->nullable(); // Channel-specific settings
    /*
     * settings structure:
     * {
     *   "priority": "HIGH|NORMAL|LOW",
     *   "retry_attempts": 3,
     *   "retry_delay_minutes": [5, 15, 60],
     *   "fallback_channel": "SMS",
     *   "send_time_window": {"start": "08:00", "end": "22:00"},
     *   "require_user_consent": true
     * }
     */

    // Status
    $table->boolean('is_active')->default(true);
    $table->integer('version')->default(1); // For versioning

    // Metadata
    $table->timestamps();
    $table->softDeletes();
    $table->uuid('created_by')->nullable();
    $table->uuid('updated_by')->nullable();

    // Indexes
    $table->index('tenant_id');
    $table->index(['tenant_id', 'event', 'channel']);
    $table->unique(['tenant_id', 'event', 'channel', 'version'], 'template_unique');
});
```

### 3.2 Tabla: `notification_events`

Define eventos disponibles y sus variables.

```php
Schema::create('notification_events', function (Blueprint $table) {
    $table->uuid('id')->primary();

    // Event definition
    $table->string('code', 50)->unique(); // APPLICATION_SUBMITTED
    $table->string('name', 100); // "Solicitud Enviada"
    $table->text('description')->nullable();
    $table->string('category', 30); // APPLICATION, DOCUMENT, USER, SYSTEM

    // Available variables for this event
    $table->json('available_variables');
    /*
     * Structure:
     * [
     *   {"name": "applicant_name", "type": "string", "description": "Nombre del solicitante"},
     *   {"name": "application_folio", "type": "string", "description": "Folio de solicitud"},
     *   {"name": "amount", "type": "money", "description": "Monto solicitado"},
     *   {"name": "product_name", "type": "string", "description": "Producto"}
     * ]
     */

    // Recommended channels for this event
    $table->json('recommended_channels')->nullable(); // ["SMS", "EMAIL", "IN_APP"]

    // Event trigger configuration
    $table->string('trigger_type', 30); // MODEL_EVENT, MANUAL, SCHEDULED
    $table->string('model_class')->nullable(); // App\Models\Application
    $table->string('model_event')->nullable(); // created, updated, status_changed

    $table->boolean('is_system')->default(false); // System events can't be deleted
    $table->boolean('is_active')->default(true);

    $table->timestamps();

    $table->index('category');
    $table->index('is_active');
});
```

### 3.3 Tabla: `notification_logs`

Registro de todas las notificaciones enviadas.

```php
Schema::create('notification_logs', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->uuid('tenant_id');
    $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');

    // Notification identification
    $table->uuid('template_id')->nullable();
    $table->foreign('template_id')->references('id')->on('notification_templates')->nullOnDelete();
    $table->string('event', 50);
    $table->string('channel', 20);

    // Recipient
    $table->string('recipient_type', 50); // email, phone, user_id
    $table->string('recipient_value', 255); // actual email/phone/id
    $table->uuid('user_id')->nullable(); // Link to applicant_accounts or staff_accounts

    // Related entity
    $table->uuid('entity_id')->nullable(); // Application ID, Document ID, etc.
    $table->string('entity_type', 100)->nullable(); // Application, Document, etc.

    // Content snapshot
    $table->text('subject')->nullable();
    $table->text('body');
    $table->json('variables_used')->nullable(); // Actual values used in rendering

    // Delivery tracking
    $table->string('status', 20); // QUEUED, SENT, DELIVERED, FAILED, BOUNCED
    $table->string('provider', 30)->nullable(); // TWILIO, SENDGRID, etc.
    $table->string('provider_message_id')->nullable(); // External tracking ID
    $table->text('error_message')->nullable();
    $table->integer('retry_count')->default(0);
    $table->timestamp('sent_at')->nullable();
    $table->timestamp('delivered_at')->nullable();
    $table->timestamp('failed_at')->nullable();
    $table->timestamp('next_retry_at')->nullable();

    // Cost tracking
    $table->decimal('cost', 10, 4)->nullable(); // In USD
    $table->integer('segments')->nullable(); // For SMS (how many parts)

    // Context
    $table->string('ip_address', 45)->nullable();
    $table->text('user_agent')->nullable();
    $table->json('metadata')->nullable(); // Additional context

    $table->timestamps();

    // Indexes
    $table->index('tenant_id');
    $table->index(['tenant_id', 'event']);
    $table->index(['tenant_id', 'channel']);
    $table->index(['entity_type', 'entity_id']);
    $table->index('status');
    $table->index('sent_at');
    $table->index('next_retry_at');
});
```

### 3.4 Tabla: `notification_preferences`

Preferencias de notificación por usuario.

```php
Schema::create('notification_preferences', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->uuid('tenant_id');
    $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');

    // User identification
    $table->uuid('user_id');
    $table->string('user_type', 50); // ApplicantAccount, StaffAccount

    // Event-specific preferences
    $table->string('event', 50); // Or '*' for all events
    $table->json('enabled_channels'); // ["SMS", "EMAIL", "IN_APP"]
    $table->json('disabled_channels')->nullable(); // ["WHATSAPP"]

    // Channel-specific settings
    $table->json('channel_settings')->nullable();
    /*
     * {
     *   "EMAIL": {
     *     "address": "alternate@email.com",
     *     "digest_mode": false
     *   },
     *   "SMS": {
     *     "phone": "+525512345678"
     *   }
     * }
     */

    // Quiet hours
    $table->time('quiet_start')->nullable(); // 22:00
    $table->time('quiet_end')->nullable(); // 08:00
    $table->string('timezone', 50)->default('America/Mexico_City');

    // Frequency limits
    $table->integer('max_per_hour')->nullable();
    $table->integer('max_per_day')->nullable();

    $table->timestamps();

    $table->index(['user_id', 'user_type']);
    $table->index(['tenant_id', 'event']);
});
```

## 4. Sistema de Variables

### 4.1 Categorías de Variables

#### A. Variables de Sistema (Siempre disponibles)
```php
[
    'system' => [
        'tenant_name' => 'Nombre del tenant',
        'tenant_logo_url' => 'URL del logo',
        'tenant_phone' => 'Teléfono de soporte',
        'tenant_email' => 'Email de contacto',
        'tenant_website' => 'Sitio web',
        'current_date' => 'Fecha actual',
        'current_time' => 'Hora actual',
        'app_url' => 'URL de la aplicación'
    ]
]
```

#### B. Variables de Usuario
```php
[
    'user' => [
        'first_name' => 'Nombre',
        'last_name_1' => 'Apellido paterno',
        'last_name_2' => 'Apellido materno',
        'full_name' => 'Nombre completo',
        'email' => 'Correo electrónico',
        'phone' => 'Teléfono'
    ]
]
```

#### C. Variables de Solicitud (Application)
```php
[
    'application' => [
        'folio' => 'Folio de solicitud',
        'status' => 'Estado actual',
        'status_label' => 'Estado legible',
        'product_name' => 'Nombre del producto',
        'product_type' => 'Tipo de producto',
        'amount' => 'Monto solicitado (con formato)',
        'amount_raw' => 'Monto sin formato',
        'term_months' => 'Plazo en meses',
        'monthly_payment' => 'Pago mensual',
        'annual_rate' => 'Tasa anual',
        'created_at' => 'Fecha de creación',
        'submitted_at' => 'Fecha de envío',
        'approved_at' => 'Fecha de aprobación',
        'rejection_reason' => 'Motivo de rechazo',
        'assigned_analyst' => 'Analista asignado',
        'view_url' => 'URL para ver solicitud'
    ]
]
```

#### D. Variables de Documento
```php
[
    'document' => [
        'type' => 'Tipo de documento',
        'type_label' => 'Tipo legible',
        'status' => 'Estado del documento',
        'file_name' => 'Nombre del archivo',
        'rejection_reason' => 'Motivo de rechazo',
        'upload_url' => 'URL para subir'
    ]
]
```

#### E. Variables de Acción
```php
[
    'action' => [
        'button_text' => 'Texto del botón CTA',
        'button_url' => 'URL del botón CTA',
        'deadline' => 'Fecha límite',
        'days_remaining' => 'Días restantes'
    ]
]
```

### 4.2 Sintaxis de Variables

Se utilizará **sintaxis Handlebars** (compatible con Mustache) para máxima compatibilidad:

#### Básica:
```
Hola {{ user.first_name }},

Tu solicitud {{ application.folio }} ha sido {{ application.status_label }}.
```

#### Condicionales:
```
{{#if application.approved_at}}
  ¡Felicidades! Tu crédito ha sido aprobado.
{{else}}
  Tu solicitud está en revisión.
{{/if}}
```

#### Iteraciones:
```
{{#each documents}}
  - {{ this.type_label }}: {{ this.status }}
{{/each}}
```

#### Helpers personalizados:
```
{{ formatMoney application.amount }}
{{ formatDate application.created_at }}
{{ upper user.full_name }}
```

## 5. Eventos del Sistema

### 5.1 Eventos de Aplicación

| Código | Nombre | Variables Clave | Canales Recomendados |
|--------|--------|-----------------|---------------------|
| `APPLICATION_SUBMITTED` | Solicitud Enviada | application.*, user.* | EMAIL, SMS, IN_APP |
| `APPLICATION_IN_REVIEW` | En Revisión | application.*, analyst | EMAIL, IN_APP |
| `APPLICATION_APPROVED` | Solicitud Aprobada | application.*, approved_amount | EMAIL, SMS, WHATSAPP, IN_APP |
| `APPLICATION_REJECTED` | Solicitud Rechazada | application.*, rejection_reason | EMAIL, SMS, IN_APP |
| `APPLICATION_DOCS_PENDING` | Documentos Pendientes | application.*, missing_docs | EMAIL, SMS, IN_APP |
| `APPLICATION_CORRECTIONS_NEEDED` | Correcciones Requeridas | application.*, rejected_fields | EMAIL, SMS, IN_APP |
| `APPLICATION_DISBURSED` | Crédito Dispersado | application.*, bank_info | EMAIL, SMS, WHATSAPP, IN_APP |
| `APPLICATION_ANALYST_ASSIGNED` | Analista Asignado | application.*, analyst | IN_APP |

### 5.2 Eventos de Documentos

| Código | Nombre | Variables Clave | Canales Recomendados |
|--------|--------|-----------------|---------------------|
| `DOCUMENT_APPROVED` | Documento Aprobado | document.*, application.* | IN_APP |
| `DOCUMENT_REJECTED` | Documento Rechazado | document.*, rejection_reason | EMAIL, SMS, IN_APP |
| `DOCUMENT_EXPIRED` | Documento Vencido | document.*, expiry_date | EMAIL, SMS |

### 5.3 Eventos de Usuario

| Código | Nombre | Variables Clave | Canales Recomendados |
|--------|--------|-----------------|---------------------|
| `OTP_CODE` | Código OTP | otp_code, expires_in | SMS, WHATSAPP, EMAIL |
| `PASSWORD_RESET` | Restablecer Contraseña | reset_token, reset_url | EMAIL |
| `ACCOUNT_CREATED` | Cuenta Creada | user.*, verification_url | EMAIL |
| `KYC_VERIFIED` | KYC Verificado | user.* | EMAIL, IN_APP |

### 5.4 Eventos de Pago

| Código | Nombre | Variables Clave | Canales Recomendados |
|--------|--------|-----------------|---------------------|
| `PAYMENT_DUE_REMINDER` | Recordatorio de Pago | payment.*, due_date | EMAIL, SMS, WHATSAPP |
| `PAYMENT_RECEIVED` | Pago Recibido | payment.*, receipt_url | EMAIL, SMS, IN_APP |
| `PAYMENT_OVERDUE` | Pago Atrasado | payment.*, late_fee | EMAIL, SMS, WHATSAPP |

## 6. Canales de Notificación

### 6.1 SMS (via Twilio)

**Características:**
- Máximo 160 caracteres por segmento
- Solo texto plano (sin formato)
- Alta tasa de apertura (98%)
- Costo: ~$0.01-0.05 USD por mensaje
- Entrega inmediata (<5 segundos)

**Variables soportadas:** Todas (convertidas a texto plano)

**Plantilla ejemplo:**
```
Hola {{ user.first_name }},

Tu solicitud {{ application.folio }} ha sido APROBADA por ${{ application.approved_amount }}.

Ingresa a {{ action.button_url }} para ver los detalles.

{{ tenant_name }}
```

**Límites y validaciones:**
- Máximo 480 caracteres (3 segmentos)
- Sin emojis (excepto básicos: ✓ ✗ ★)
- No incluir URLs largas (usar acortador automático)

### 6.2 WhatsApp (via Twilio)

**Características:**
- Hasta 4096 caracteres
- Formato básico (negrita, cursiva)
- Requiere plantillas pre-aprobadas por Meta
- Costo: ~$0.005-0.03 USD por mensaje
- Ventana de 24h para respuestas

**Variables soportadas:** Todas

**Plantilla ejemplo:**
```
¡Hola *{{ user.first_name }}*! 👋

Tu solicitud *{{ application.folio }}* ha sido *APROBADA* 🎉

💰 Monto aprobado: ${{ application.approved_amount }}
📅 Plazo: {{ application.term_months }} meses
💳 Pago mensual: ${{ application.monthly_payment }}

Toca aquí para continuar:
{{ action.button_url }}

_{{ tenant_name }}_
```

**Formato soportado:**
- `*negrita*`
- `_cursiva_`
- `~tachado~`
- ` ```código``` `
- Emojis completos

### 6.3 Email (via SendGrid/SMTP)

**Características:**
- Sin límite de tamaño práctico
- HTML completo con CSS inline
- Imágenes embebidas
- Costo: ~$0.0001-0.001 USD por email
- Tasa de apertura: 20-40%

**Variables soportadas:** Todas

**Estructura de plantilla HTML:**
```html
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        /* CSS inline generado dinámicamente con colores del tenant */
    </style>
</head>
<body style="margin: 0; padding: 0; background-color: #f4f4f4;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
        <!-- Header con logo del tenant -->
        <tr>
            <td align="center" style="padding: 40px 0;">
                <img src="{{ tenant_logo_url }}" alt="{{ tenant_name }}" height="50">
            </td>
        </tr>

        <!-- Contenido principal -->
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0"
                       style="background: white; border-radius: 8px;">
                    <tr>
                        <td style="padding: 40px;">
                            <!-- BODY CONTENT HERE -->
                            {{{ html_body }}}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

        <!-- Footer -->
        <tr>
            <td align="center" style="padding: 20px; color: #666; font-size: 12px;">
                <p>{{ tenant_name }} | {{ tenant_email }} | {{ tenant_phone }}</p>
                <p>Este es un correo automático, por favor no responder.</p>
            </td>
        </tr>
    </table>
</body>
</html>
```

**Editor WYSIWYG:**
- Integrar **TinyMCE** o **Quill** para edición visual
- Soporte para componentes reutilizables (botones, tablas)
- Vista previa en tiempo real
- Prueba con variables de ejemplo

### 6.4 In-App (Database Channel)

**Características:**
- Notificaciones dentro de la aplicación
- Persistentes (quedan en el historial)
- Soportan HTML y acciones (botones, links)
- Sin costo de envío
- Requiere que el usuario esté logueado

**Almacenamiento:**
- Tabla: `notifications` (Laravel built-in)
- Campos: type, data, read_at
- Polymorphic: notifiable_type, notifiable_id

**Estructura de datos:**
```json
{
  "type": "ApplicationApproved",
  "data": {
    "title": "¡Solicitud Aprobada!",
    "message": "Tu crédito {{ application.folio }} ha sido aprobado",
    "icon": "check-circle",
    "color": "green",
    "action": {
      "text": "Ver Detalles",
      "url": "/dashboard/applications/{{ application.id }}"
    }
  }
}
```

**Frontend (Vue):**
```vue
<template>
  <div class="notification-dropdown">
    <button @click="toggleDropdown">
      <BellIcon />
      <span v-if="unreadCount" class="badge">{{ unreadCount }}</span>
    </button>

    <div v-if="isOpen" class="dropdown">
      <div v-for="notification in notifications" :key="notification.id"
           class="notification-item" :class="{ unread: !notification.read_at }"
           @click="markAsRead(notification)">
        <div :class="`icon icon-${notification.data.color}`">
          <component :is="notification.data.icon" />
        </div>
        <div class="content">
          <h4>{{ notification.data.title }}</h4>
          <p>{{ notification.data.message }}</p>
          <span class="time">{{ formatTimeAgo(notification.created_at) }}</span>
        </div>
      </div>
    </div>
  </div>
</template>
```

## 7. Servicio de Notificaciones (Backend)

### 7.1 NotificationService

```php
<?php

namespace App\Services;

use App\Models\NotificationTemplate;
use App\Models\NotificationLog;
use App\Models\NotificationPreference;
use App\Jobs\SendNotificationJob;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    /**
     * Send a notification based on an event.
     *
     * @param string $event Event code (APPLICATION_SUBMITTED, etc.)
     * @param array $variables Variables to replace in template
     * @param string $recipient Recipient identifier (email, phone, user_id)
     * @param string $recipientType Type of recipient (email, phone, user_id)
     * @param array $options Additional options
     * @return void
     */
    public function send(
        string $event,
        array $variables,
        string $recipient,
        string $recipientType = 'email',
        array $options = []
    ): void {
        $tenant = app('tenant');

        // Load active templates for this event
        $templates = NotificationTemplate::where('tenant_id', $tenant->id)
            ->where('event', $event)
            ->where('is_active', true)
            ->get();

        if ($templates->isEmpty()) {
            Log::warning("No templates found for event: {$event}");
            return;
        }

        // Get user preferences if user_id provided
        $preferences = null;
        if (isset($options['user_id'])) {
            $preferences = $this->getUserPreferences(
                $options['user_id'],
                $options['user_type'] ?? 'ApplicantAccount',
                $event
            );
        }

        // Determine which channels to use
        $channels = $this->resolveChannels($templates, $preferences, $recipientType);

        // Queue notification for each channel
        foreach ($channels as $channel => $template) {
            $this->queueNotification(
                $template,
                $variables,
                $recipient,
                $recipientType,
                $channel,
                $options
            );
        }
    }

    /**
     * Queue a notification job for delivery.
     */
    protected function queueNotification(
        NotificationTemplate $template,
        array $variables,
        string $recipient,
        string $recipientType,
        string $channel,
        array $options
    ): void {
        // Enrich variables with system data
        $variables = $this->enrichVariables($variables);

        // Render template
        $rendered = $this->renderTemplate($template, $variables);

        // Create log entry
        $log = NotificationLog::create([
            'tenant_id' => $template->tenant_id,
            'template_id' => $template->id,
            'event' => $template->event,
            'channel' => $channel,
            'recipient_type' => $recipientType,
            'recipient_value' => $recipient,
            'user_id' => $options['user_id'] ?? null,
            'entity_id' => $options['entity_id'] ?? null,
            'entity_type' => $options['entity_type'] ?? null,
            'subject' => $rendered['subject'] ?? null,
            'body' => $rendered['body'],
            'variables_used' => $variables,
            'status' => 'QUEUED',
            'metadata' => $options['metadata'] ?? null,
        ]);

        // Determine priority queue
        $priority = $template->settings['priority'] ?? 'NORMAL';
        $queueName = match($priority) {
            'HIGH' => "notifications-{$channel}-high",
            'LOW' => "notifications-{$channel}-low",
            default => "notifications-{$channel}",
        };

        // Dispatch job
        SendNotificationJob::dispatch($log->id)
            ->onQueue($queueName)
            ->delay(now()->addSeconds($options['delay'] ?? 0));
    }

    /**
     * Render template with variables.
     */
    protected function renderTemplate(
        NotificationTemplate $template,
        array $variables
    ): array {
        $renderer = new TemplateRenderer();

        return [
            'subject' => $template->subject
                ? $renderer->render($template->subject, $variables)
                : null,
            'body' => $renderer->render($template->body, $variables),
            'html_body' => $template->html_body
                ? $renderer->renderHtml($template->html_body, $variables)
                : null,
        ];
    }

    /**
     * Enrich variables with system data.
     */
    protected function enrichVariables(array $variables): array
    {
        $tenant = app('tenant');

        return array_merge($variables, [
            'system' => [
                'tenant_name' => $tenant->name,
                'tenant_logo_url' => $tenant->brandingConfig?->logo_url,
                'tenant_phone' => $tenant->phone,
                'tenant_email' => $tenant->email,
                'tenant_website' => $tenant->website,
                'current_date' => now()->format('d/m/Y'),
                'current_time' => now()->format('H:i'),
                'app_url' => config('app.url'),
            ],
        ]);
    }

    /**
     * Get user notification preferences.
     */
    protected function getUserPreferences(
        string $userId,
        string $userType,
        string $event
    ): ?NotificationPreference {
        return NotificationPreference::where('user_id', $userId)
            ->where('user_type', $userType)
            ->where(function ($query) use ($event) {
                $query->where('event', $event)
                    ->orWhere('event', '*');
            })
            ->first();
    }

    /**
     * Resolve which channels should be used.
     */
    protected function resolveChannels(
        $templates,
        ?NotificationPreference $preferences,
        string $recipientType
    ): array {
        $channels = [];

        foreach ($templates as $template) {
            $channel = $template->channel;

            // Check user preferences
            if ($preferences && !in_array($channel, $preferences->enabled_channels)) {
                continue;
            }

            // Check recipient type compatibility
            if (!$this->isChannelCompatible($channel, $recipientType)) {
                continue;
            }

            $channels[$channel] = $template;
        }

        return $channels;
    }

    /**
     * Check if channel is compatible with recipient type.
     */
    protected function isChannelCompatible(string $channel, string $recipientType): bool
    {
        return match($channel) {
            'SMS', 'WHATSAPP' => $recipientType === 'phone',
            'EMAIL' => $recipientType === 'email',
            'IN_APP' => $recipientType === 'user_id',
            default => false,
        };
    }
}
```

### 7.2 TemplateRenderer

```php
<?php

namespace App\Services;

use LightnCandy\LightnCandy;
use Illuminate\Support\Facades\Cache;

class TemplateRenderer
{
    /**
     * Render template with Handlebars syntax.
     */
    public function render(string $template, array $variables): string
    {
        $compiled = $this->compile($template);
        return $compiled($variables);
    }

    /**
     * Render HTML template with layout.
     */
    public function renderHtml(string $template, array $variables): string
    {
        // Wrap in email layout
        $layout = view('emails.layouts.base')->render();
        $content = $this->render($template, $variables);

        return str_replace('{{{ html_body }}}', $content, $layout);
    }

    /**
     * Compile Handlebars template (with caching).
     */
    protected function compile(string $template): callable
    {
        $hash = md5($template);

        return Cache::remember("template.{$hash}", 3600, function () use ($template) {
            $phpStr = LightnCandy::compile($template, [
                'flags' => LightnCandy::FLAG_HANDLEBARS |
                          LightnCandy::FLAG_ERROR_EXCEPTION |
                          LightnCandy::FLAG_RUNTIMEPARTIAL,
                'helpers' => $this->getHelpers(),
            ]);

            return LightnCandy::prepare(eval($phpStr));
        });
    }

    /**
     * Register custom Handlebars helpers.
     */
    protected function getHelpers(): array
    {
        return [
            'formatMoney' => function ($value) {
                return '$' . number_format((float) $value, 0, '.', ',');
            },
            'formatDate' => function ($value) {
                return \Carbon\Carbon::parse($value)->format('d/m/Y');
            },
            'formatDateTime' => function ($value) {
                return \Carbon\Carbon::parse($value)->format('d/m/Y H:i');
            },
            'upper' => function ($value) {
                return strtoupper($value);
            },
            'lower' => function ($value) {
                return strtolower($value);
            },
            'truncate' => function ($value, $length = 50) {
                return strlen($value) > $length
                    ? substr($value, 0, $length) . '...'
                    : $value;
            },
        ];
    }
}
```

### 7.3 SendNotificationJob

```php
<?php

namespace App\Jobs;

use App\Models\NotificationLog;
use App\Services\ExternalApi\TwilioService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

class SendNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 60;

    public function __construct(
        public string $notificationLogId
    ) {}

    public function handle(): void
    {
        $log = NotificationLog::find($this->notificationLogId);

        if (!$log) {
            Log::error("NotificationLog not found: {$this->notificationLogId}");
            return;
        }

        try {
            $result = match($log->channel) {
                'SMS' => $this->sendSms($log),
                'WHATSAPP' => $this->sendWhatsApp($log),
                'EMAIL' => $this->sendEmail($log),
                'IN_APP' => $this->sendInApp($log),
                default => throw new \Exception("Unknown channel: {$log->channel}"),
            };

            $log->update([
                'status' => 'SENT',
                'sent_at' => now(),
                'provider' => $result['provider'] ?? null,
                'provider_message_id' => $result['message_id'] ?? null,
                'cost' => $result['cost'] ?? null,
                'segments' => $result['segments'] ?? null,
            ]);

        } catch (\Exception $e) {
            $log->increment('retry_count');

            $log->update([
                'status' => 'FAILED',
                'error_message' => $e->getMessage(),
                'failed_at' => now(),
                'next_retry_at' => $this->shouldRetry($log)
                    ? $this->getNextRetryTime($log)
                    : null,
            ]);

            if ($this->shouldRetry($log)) {
                $this->release($this->getRetryDelay($log));
            } else {
                Log::error("Notification failed after {$log->retry_count} attempts", [
                    'log_id' => $log->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    protected function sendSms(NotificationLog $log): array
    {
        $twilio = app(TwilioService::class);

        $result = $twilio->sendSms(
            to: $log->recipient_value,
            message: $log->body,
            tenantId: $log->tenant_id,
            applicantId: $log->user_id,
            metadata: ['notification_log_id' => $log->id]
        );

        return [
            'provider' => 'TWILIO',
            'message_id' => $result['message_sid'] ?? null,
            'cost' => $result['price'] ?? null,
            'segments' => $result['num_segments'] ?? null,
        ];
    }

    protected function sendWhatsApp(NotificationLog $log): array
    {
        $twilio = app(TwilioService::class);

        $result = $twilio->sendWhatsApp(
            to: $log->recipient_value,
            message: $log->body,
            tenantId: $log->tenant_id,
            applicantId: $log->user_id,
            metadata: ['notification_log_id' => $log->id]
        );

        return [
            'provider' => 'TWILIO',
            'message_id' => $result['message_sid'] ?? null,
            'cost' => $result['price'] ?? null,
        ];
    }

    protected function sendEmail(NotificationLog $log): array
    {
        Mail::send([], [], function ($message) use ($log) {
            $message->to($log->recipient_value)
                ->subject($log->subject)
                ->html($log->body);
        });

        return [
            'provider' => 'SENDGRID',
            'message_id' => null, // SendGrid provides this via webhook
        ];
    }

    protected function sendInApp(NotificationLog $log): array
    {
        $userModel = $log->user_type === 'StaffAccount'
            ? \App\Models\StaffAccount::class
            : \App\Models\ApplicantAccount::class;

        $user = $userModel::find($log->user_id);

        if (!$user) {
            throw new \Exception("User not found: {$log->user_id}");
        }

        $user->notify(new \App\Notifications\GenericNotification([
            'title' => $log->subject,
            'message' => $log->body,
            'data' => $log->variables_used,
        ]));

        return [
            'provider' => 'DATABASE',
        ];
    }

    protected function shouldRetry(NotificationLog $log): bool
    {
        $maxRetries = $log->template->settings['retry_attempts'] ?? 3;
        return $log->retry_count < $maxRetries;
    }

    protected function getRetryDelay(NotificationLog $log): int
    {
        $delays = $log->template->settings['retry_delay_minutes'] ?? [5, 15, 60];
        $index = min($log->retry_count - 1, count($delays) - 1);
        return $delays[$index] * 60; // Convert to seconds
    }

    protected function getNextRetryTime(NotificationLog $log): \Carbon\Carbon
    {
        return now()->addSeconds($this->getRetryDelay($log));
    }
}
```

## 8. Panel de Administración (Frontend)

### 8.1 Rutas

```typescript
// frontend/src/router/admin.ts
{
  path: 'notifications',
  name: 'AdminNotifications',
  component: () => import('@/views/admin/notifications/NotificationTemplatesIndex.vue'),
  meta: { requiresAdmin: true }
},
{
  path: 'notifications/events',
  name: 'AdminNotificationEvents',
  component: () => import('@/views/admin/notifications/NotificationEventsIndex.vue'),
  meta: { requiresAdmin: true }
},
{
  path: 'notifications/templates/:id/edit',
  name: 'AdminNotificationTemplateEdit',
  component: () => import('@/views/admin/notifications/NotificationTemplateEdit.vue'),
  meta: { requiresAdmin: true }
},
{
  path: 'notifications/logs',
  name: 'AdminNotificationLogs',
  component: () => import('@/views/admin/notifications/NotificationLogsIndex.vue'),
  meta: { requiresAdmin: true }
}
```

### 8.2 Vista Principal: NotificationTemplatesIndex.vue

```vue
<template>
  <div class="notification-templates-page">
    <PageHeader
      title="Plantillas de Notificaciones"
      subtitle="Configura las notificaciones que se envían a tus clientes"
    >
      <template #actions>
        <AppButton @click="showCreateModal = true" icon="plus">
          Nueva Plantilla
        </AppButton>
      </template>
    </PageHeader>

    <!-- Filtros -->
    <div class="filters">
      <Select v-model="filters.event" label="Evento" :options="eventOptions" />
      <Select v-model="filters.channel" label="Canal" :options="channelOptions" />
      <Select v-model="filters.status" label="Estado" :options="statusOptions" />
    </div>

    <!-- Grid de plantillas agrupadas por evento -->
    <div class="templates-grid">
      <div v-for="group in groupedTemplates" :key="group.event" class="event-group">
        <div class="event-header">
          <h3>{{ group.event_name }}</h3>
          <span class="badge">{{ group.templates.length }} plantillas</span>
        </div>

        <div class="templates-row">
          <TemplateCard
            v-for="template in group.templates"
            :key="template.id"
            :template="template"
            @edit="editTemplate(template)"
            @duplicate="duplicateTemplate(template)"
            @toggle="toggleTemplate(template)"
            @delete="deleteTemplate(template)"
          />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { v2 } from '@/services/v2'
import { useToast } from '@/composables'

const toast = useToast()

const templates = ref<NotificationTemplate[]>([])
const events = ref<NotificationEvent[]>([])
const filters = ref({ event: '', channel: '', status: 'all' })

const groupedTemplates = computed(() => {
  let filtered = templates.value

  if (filters.value.event) {
    filtered = filtered.filter(t => t.event === filters.value.event)
  }
  if (filters.value.channel) {
    filtered = filtered.filter(t => t.channel === filters.value.channel)
  }
  if (filters.value.status === 'active') {
    filtered = filtered.filter(t => t.is_active)
  }

  const grouped = new Map()

  for (const template of filtered) {
    if (!grouped.has(template.event)) {
      const event = events.value.find(e => e.code === template.event)
      grouped.set(template.event, {
        event: template.event,
        event_name: event?.name || template.event,
        templates: []
      })
    }
    grouped.get(template.event).templates.push(template)
  }

  return Array.from(grouped.values())
})

onMounted(async () => {
  await Promise.all([
    loadTemplates(),
    loadEvents()
  ])
})

const loadTemplates = async () => {
  const response = await v2.admin.notifications.templates.index()
  if (response.success) {
    templates.value = response.data
  }
}

const loadEvents = async () => {
  const response = await v2.admin.notifications.events.index()
  if (response.success) {
    events.value = response.data
  }
}
</script>
```

### 8.3 Editor de Plantillas: NotificationTemplateEdit.vue

**Componentes clave:**

1. **Selector de Evento y Canal**
2. **Editor de Contenido** (diferente por canal):
   - SMS/WhatsApp: Textarea simple con contador de caracteres
   - Email: TinyMCE con vista previa
   - In-App: Editor JSON estructurado
3. **Selector de Variables** (inserción automática)
4. **Vista Previa en Tiempo Real**
5. **Envío de Prueba**

```vue
<template>
  <div class="template-editor">
    <PageHeader title="Editar Plantilla">
      <template #actions>
        <AppButton @click="sendTest" variant="outline">
          Enviar Prueba
        </AppButton>
        <AppButton @click="save" :loading="isSaving">
          Guardar Cambios
        </AppButton>
      </template>
    </PageHeader>

    <!-- Basic Info -->
    <Card>
      <h3>Información Básica</h3>
      <FormRow>
        <Select v-model="form.event" label="Evento" :options="eventOptions" required />
        <Select v-model="form.channel" label="Canal" :options="channelOptions" required />
      </FormRow>
      <Input v-model="form.name" label="Nombre" required />
      <Textarea v-model="form.description" label="Descripción" rows="2" />
    </Card>

    <!-- Content Editor -->
    <Card>
      <h3>Contenido</h3>

      <!-- EMAIL / IN_APP: Subject field -->
      <Input v-if="showSubject" v-model="form.subject" label="Asunto" />

      <!-- Variable Picker -->
      <div class="variable-picker">
        <h4>Variables Disponibles</h4>
        <div class="variables-grid">
          <button
            v-for="variable in availableVariables"
            :key="variable.name"
            @click="insertVariable(variable.name)"
            class="variable-chip"
            :title="variable.description"
          >
            {{ `{{ ${variable.name} }}` }}
          </button>
        </div>
      </div>

      <!-- SMS / WhatsApp: Plain text editor -->
      <Textarea
        v-if="form.channel === 'SMS' || form.channel === 'WHATSAPP'"
        v-model="form.body"
        label="Mensaje"
        rows="6"
        :maxlength="form.channel === 'SMS' ? 480 : 4096"
        show-counter
      />

      <!-- EMAIL: Rich HTML editor -->
      <div v-if="form.channel === 'EMAIL'" class="email-editor">
        <TinyMCEEditor v-model="form.html_body" />
      </div>

      <!-- IN_APP: Structured editor -->
      <div v-if="form.channel === 'IN_APP'" class="inapp-editor">
        <Input v-model="form.inapp_data.title" label="Título" />
        <Textarea v-model="form.inapp_data.message" label="Mensaje" rows="3" />
        <Select v-model="form.inapp_data.icon" label="Icono" :options="iconOptions" />
        <Select v-model="form.inapp_data.color" label="Color" :options="colorOptions" />
        <Input v-model="form.inapp_data.action_text" label="Texto del Botón" />
        <Input v-model="form.inapp_data.action_url" label="URL del Botón" />
      </div>
    </Card>

    <!-- Settings -->
    <Card>
      <h3>Configuración Avanzada</h3>
      <FormRow>
        <Select v-model="form.settings.priority" label="Prioridad" :options="priorityOptions" />
        <Input v-model.number="form.settings.retry_attempts" label="Reintentos" type="number" />
      </FormRow>
      <FormRow>
        <Select v-model="form.settings.fallback_channel" label="Canal de respaldo" :options="channelOptions" />
      </FormRow>
      <Toggle v-model="form.is_active" label="Plantilla activa" />
    </Card>

    <!-- Preview Panel -->
    <Card v-if="form.body || form.html_body">
      <h3>Vista Previa</h3>
      <div class="preview-container">
        <div v-if="form.channel === 'SMS'" class="phone-preview">
          <div class="phone-screen">
            <div class="sms-bubble">
              {{ renderedPreview }}
            </div>
          </div>
        </div>
        <div v-else-if="form.channel === 'EMAIL'" class="email-preview">
          <iframe :srcdoc="renderedPreview" />
        </div>
      </div>
    </Card>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { v2 } from '@/services/v2'
import { useToast } from '@/composables'
import TinyMCEEditor from '@/components/common/TinyMCEEditor.vue'

const route = useRoute()
const router = useRouter()
const toast = useToast()

const form = ref({
  event: '',
  channel: '',
  name: '',
  description: '',
  subject: '',
  body: '',
  html_body: '',
  variables: [],
  settings: {
    priority: 'NORMAL',
    retry_attempts: 3,
    retry_delay_minutes: [5, 15, 60],
    fallback_channel: null,
  },
  is_active: true,
  inapp_data: {
    title: '',
    message: '',
    icon: 'info',
    color: 'blue',
    action_text: '',
    action_url: '',
  }
})

const availableVariables = computed(() => {
  // Load from selected event
  const event = events.value.find(e => e.code === form.value.event)
  return event?.available_variables || []
})

const showSubject = computed(() => {
  return form.value.channel === 'EMAIL' || form.value.channel === 'IN_APP'
})

const renderedPreview = computed(() => {
  // Render template with example data
  const exampleData = {
    'user.first_name': 'Juan',
    'user.full_name': 'Juan Pérez García',
    'application.folio': 'APP-2026-001234',
    'application.amount': '$50,000',
    'application.product_name': 'Crédito Personal',
    // ... more example values
  }

  let content = form.value.channel === 'EMAIL'
    ? form.value.html_body
    : form.value.body

  // Simple variable replacement for preview
  for (const [key, value] of Object.entries(exampleData)) {
    content = content.replaceAll(`{{ ${key} }}`, value)
  }

  return content
})

const insertVariable = (variableName: string) => {
  const textarea = document.querySelector('textarea')
  if (!textarea) return

  const start = textarea.selectionStart
  const end = textarea.selectionEnd
  const text = form.value.body

  form.value.body = text.substring(0, start) +
    `{{ ${variableName} }}` +
    text.substring(end)
}

const save = async () => {
  isSaving.value = true
  try {
    const response = await v2.admin.notifications.templates.update(
      route.params.id,
      form.value
    )
    if (response.success) {
      toast.success('Plantilla guardada correctamente')
      router.push('/admin/notifications')
    }
  } finally {
    isSaving.value = false
  }
}

const sendTest = async () => {
  // Open modal to input test recipient
  // Then send via API
}
</script>
```

### 8.4 Logs de Notificaciones: NotificationLogsIndex.vue

```vue
<template>
  <div class="notification-logs-page">
    <PageHeader title="Registro de Notificaciones" />

    <!-- Filters -->
    <div class="filters">
      <DateRangePicker v-model="filters.dateRange" />
      <Select v-model="filters.event" label="Evento" :options="eventOptions" />
      <Select v-model="filters.channel" label="Canal" :options="channelOptions" />
      <Select v-model="filters.status" label="Estado" :options="statusOptions" />
      <Input v-model="filters.search" placeholder="Buscar por destinatario..." />
    </div>

    <!-- Stats Cards -->
    <div class="stats-grid">
      <StatCard title="Total Enviadas" :value="stats.total" icon="send" />
      <StatCard title="Entregadas" :value="stats.delivered" icon="check" color="green" />
      <StatCard title="Fallidas" :value="stats.failed" icon="x" color="red" />
      <StatCard title="Costo Total" :value="formatMoney(stats.totalCost)" icon="dollar" />
    </div>

    <!-- Logs Table -->
    <DataTable
      :data="logs"
      :columns="columns"
      :loading="isLoading"
      @row-click="showLogDetail"
    >
      <template #channel="{ row }">
        <span class="channel-badge" :class="row.channel.toLowerCase()">
          {{ row.channel }}
        </span>
      </template>

      <template #status="{ row }">
        <span class="status-badge" :class="row.status.toLowerCase()">
          {{ row.status }}
        </span>
      </template>

      <template #actions="{ row }">
        <button @click="retryNotification(row)" v-if="row.status === 'FAILED'">
          Reintentar
        </button>
      </template>
    </DataTable>
  </div>
</template>
```

## 9. Integración con Eventos del Sistema

### 9.1 Event Listeners

```php
<?php

namespace App\Listeners;

use App\Events\ApplicationSubmitted;
use App\Services\NotificationService;

class SendApplicationSubmittedNotification
{
    public function __construct(
        protected NotificationService $notificationService
    ) {}

    public function handle(ApplicationSubmitted $event): void
    {
        $application = $event->application;
        $person = $application->person;
        $account = $person->account;

        if (!$account) {
            return;
        }

        $variables = [
            'user' => [
                'first_name' => $person->first_name,
                'last_name_1' => $person->last_name_1,
                'last_name_2' => $person->last_name_2,
                'full_name' => $person->full_name,
                'email' => $account->email,
                'phone' => $account->phone,
            ],
            'application' => [
                'folio' => $application->folio,
                'status' => $application->status,
                'status_label' => $application->status_label,
                'product_name' => $application->product->name,
                'amount' => formatMoney($application->requested_amount),
                'amount_raw' => $application->requested_amount,
                'term_months' => $application->requested_term_months,
                'created_at' => $application->created_at->format('d/m/Y'),
                'view_url' => route('applicant.applications.show', $application->id),
            ],
        ];

        // Send to email
        if ($account->email) {
            $this->notificationService->send(
                event: 'APPLICATION_SUBMITTED',
                variables: $variables,
                recipient: $account->email,
                recipientType: 'email',
                options: [
                    'user_id' => $account->id,
                    'user_type' => 'ApplicantAccount',
                    'entity_id' => $application->id,
                    'entity_type' => 'Application',
                ]
            );
        }

        // Send to SMS
        if ($account->phone) {
            $this->notificationService->send(
                event: 'APPLICATION_SUBMITTED',
                variables: $variables,
                recipient: $account->phone,
                recipientType: 'phone',
                options: [
                    'user_id' => $account->id,
                    'user_type' => 'ApplicantAccount',
                    'entity_id' => $application->id,
                    'entity_type' => 'Application',
                ]
            );
        }

        // Send in-app
        $this->notificationService->send(
            event: 'APPLICATION_SUBMITTED',
            variables: $variables,
            recipient: $account->id,
            recipientType: 'user_id',
            options: [
                'user_id' => $account->id,
                'user_type' => 'ApplicantAccount',
                'entity_id' => $application->id,
                'entity_type' => 'Application',
            ]
        );
    }
}
```

### 9.2 Event Registration

```php
<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        \App\Events\ApplicationSubmitted::class => [
            \App\Listeners\SendApplicationSubmittedNotification::class,
        ],
        \App\Events\ApplicationApproved::class => [
            \App\Listeners\SendApplicationApprovedNotification::class,
        ],
        \App\Events\ApplicationRejected::class => [
            \App\Listeners\SendApplicationRejectedNotification::class,
        ],
        \App\Events\DocumentRejected::class => [
            \App\Listeners\SendDocumentRejectedNotification::class,
        ],
        // ... more event-listener mappings
    ];
}
```

## 10. Migraciones

### 10.1 Orden de Ejecución

```bash
1. 2026_01_24_000001_create_notification_events_table.php
2. 2026_01_24_000002_create_notification_templates_table.php
3. 2026_01_24_000003_create_notification_logs_table.php
4. 2026_01_24_000004_create_notification_preferences_table.php
5. 2026_01_24_000005_seed_default_notification_events.php (seeder)
6. 2026_01_24_000006_seed_default_notification_templates.php (seeder)
```

## 11. Rutas de API

### Backend Routes (Laravel)

```php
// backend/routes/api.php - Admin routes
Route::prefix('v2/admin/notifications')->group(function () {
    // Events
    Route::get('/events', [NotificationEventController::class, 'index']);
    Route::post('/events', [NotificationEventController::class, 'store']);
    Route::put('/events/{id}', [NotificationEventController::class, 'update']);
    Route::delete('/events/{id}', [NotificationEventController::class, 'destroy']);

    // Templates
    Route::get('/templates', [NotificationTemplateController::class, 'index']);
    Route::post('/templates', [NotificationTemplateController::class, 'store']);
    Route::get('/templates/{id}', [NotificationTemplateController::class, 'show']);
    Route::put('/templates/{id}', [NotificationTemplateController::class, 'update']);
    Route::delete('/templates/{id}', [NotificationTemplateController::class, 'destroy']);
    Route::post('/templates/{id}/duplicate', [NotificationTemplateController::class, 'duplicate']);
    Route::post('/templates/{id}/test', [NotificationTemplateController::class, 'sendTest']);

    // Logs
    Route::get('/logs', [NotificationLogController::class, 'index']);
    Route::get('/logs/{id}', [NotificationLogController::class, 'show']);
    Route::post('/logs/{id}/retry', [NotificationLogController::class, 'retry']);
    Route::get('/logs/stats', [NotificationLogController::class, 'stats']);

    // Preferences (per tenant)
    Route::get('/preferences', [NotificationPreferenceController::class, 'index']);
});

// Applicant routes
Route::prefix('v2/applicant/notifications')->group(function () {
    // User preferences
    Route::get('/preferences', [ApplicantNotificationPreferenceController::class, 'show']);
    Route::put('/preferences', [ApplicantNotificationPreferenceController::class, 'update']);

    // In-app notifications
    Route::get('/inbox', [ApplicantNotificationController::class, 'index']);
    Route::put('/inbox/{id}/read', [ApplicantNotificationController::class, 'markAsRead']);
    Route::put('/inbox/read-all', [ApplicantNotificationController::class, 'markAllAsRead']);
    Route::delete('/inbox/{id}', [ApplicantNotificationController::class, 'destroy']);
});
```

## 12. Consideraciones de Seguridad

1. **Rate Limiting**: Implementar límites por usuario/tenant para prevenir abuso
2. **Validación de Destinatarios**: Verificar que números de teléfono y emails sean válidos
3. **Opt-out**: Respetar preferencias de usuario (GDPR/LFPDPPP compliance)
4. **Encriptación**: Datos sensibles en logs deben estar enmascarados
5. **Permisos**: Solo ADMIN y SUPER_ADMIN pueden modificar plantillas
6. **Audit Trail**: Registrar quién modifica qué plantilla y cuándo
7. **Content Sanitization**: Sanitizar HTML en plantillas de email
8. **Variable Validation**: Validar que variables existan antes de renderizar

## 13. Testing

### 13.1 Unit Tests

```php
// tests/Unit/Services/NotificationServiceTest.php
class NotificationServiceTest extends TestCase
{
    public function test_it_sends_notification_to_all_enabled_channels()
    {
        // Arrange
        $service = app(NotificationService::class);

        // Act
        $service->send('APPLICATION_SUBMITTED', [...], 'email@test.com');

        // Assert
        $this->assertDatabaseHas('notification_logs', [
            'event' => 'APPLICATION_SUBMITTED',
            'channel' => 'EMAIL',
        ]);
    }

    public function test_it_respects_user_preferences()
    {
        // Test opt-out functionality
    }

    public function test_it_renders_variables_correctly()
    {
        // Test template rendering
    }
}
```

### 13.2 Feature Tests

```php
// tests/Feature/Notifications/SendNotificationTest.php
class SendNotificationTest extends TestCase
{
    public function test_admin_can_create_notification_template()
    {
        $this->actingAs($this->admin)
            ->postJson('/api/v2/admin/notifications/templates', [...])
            ->assertSuccessful();
    }

    public function test_notification_is_sent_when_application_submitted()
    {
        // Submit application
        // Assert notification was queued
        // Assert notification was sent
    }
}
```

## 14. Monitoreo y Métricas

### Dashboards a Implementar

1. **Delivery Rates**: % entregadas por canal y por evento
2. **Failure Analysis**: Razones de fallo más comunes
3. **Cost Tracking**: Costo por canal, por tenant, por evento
4. **Performance**: Tiempo promedio de envío por canal
5. **User Engagement**: Tasa de apertura (email), clicks (in-app)

### Alertas

- Tasa de fallo > 5% en últimas 100 notificaciones
- Costo diario excede presupuesto del tenant
- Cola de notificaciones > 1000 pendientes
- Proveedor externo no responde (timeout)

## 15. Roadmap Futuro

### Fase 1 (MVP) - 2 semanas
- ✅ Tablas y migraciones
- ✅ NotificationService básico
- ✅ Canales: SMS, Email, In-App
- ✅ Panel admin: CRUD de plantillas
- ✅ Vista de logs

### Fase 2 - 1 semana
- WhatsApp channel
- Editor visual de emails (TinyMCE)
- Sistema de variables con selector
- Envío de pruebas

### Fase 3 - 1 semana
- Preferencias de usuario
- Sistema de fallback
- Retry logic avanzado
- Analytics dashboard

### Fase 4 (Futuro)
- Push notifications (móvil)
- Slack/Teams integration
- A/B testing de plantillas
- Segmentación de audiencias
- Digest mode (resumen diario)
- Smart send time (ML-based)

## 16. Referencias y Fuentes

Este diseño está basado en las mejores prácticas de la industria documentadas en:

- [Designing a Scalable Notification System](https://medium.com/@anshulkahar2211/designing-a-scalable-notification-system-email-sms-push-from-hld-to-lld-reliability-to-d5b883d936d8)
- [Multi-Channel Notification System Guide | NotiGrid](https://notigrid.com/blog/how-to-build-multi-channel-notification-system)
- [The 5 best notification infrastructure platforms | Knock](https://knock.app/blog/the-top-notification-infrastructure-platforms-for-developers)
- [Designing a Notification System at Scale | DEV Community](https://dev.to/sgchris/designing-a-notification-system-push-email-and-sms-at-scale-kio)
- [Laravel Notification Channels](https://laravel-notification-channels.com/)
- [Twilio SendGrid Dynamic Templates](https://www.twilio.com/docs/sendgrid/ui/sending-email/how-to-send-an-email-with-dynamic-templates)
- [Using Handlebars | SendGrid Docs](https://www.twilio.com/docs/sendgrid/for-developers/sending-email/using-handlebars)
- [Multi-tenancy and Azure Event Hubs | Microsoft Learn](https://learn.microsoft.com/en-us/azure/architecture/guide/multitenant/service/event-hubs)
- [Laravel Orchid - Notifications](https://orchid.software/en/docs/alert/)

## 17. Glosario

- **Template**: Plantilla de notificación con contenido y variables
- **Event**: Evento del sistema que dispara notificaciones
- **Channel**: Medio de entrega (SMS, Email, WhatsApp, In-App)
- **Variable**: Marcador de posición reemplazado con datos reales
- **Handlebars**: Lenguaje de plantillas para insertar variables
- **Fallback**: Canal alternativo si el principal falla
- **Retry**: Reintento automático de envío tras fallo
- **Opt-out**: Usuario desactiva canal específico
- **Digest**: Resumen agrupado de notificaciones
- **Segment**: Fragmento de SMS (máx 160 chars)

---

**Documento preparado por:** Claude Code (Anthropic)
**Fecha:** 23 de enero de 2026
**Versión:** 1.0
