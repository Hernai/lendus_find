# Plan de Notificaciones — LendusFind

> Estado a 2026-07-03. Base: el sistema de notificaciones ya existe y funciona;
> el problema es que está **infrautilizado** (muchos eventos definidos sin disparo)
> y le falta **gating por capacidad del tenant**.

## 1. Estado actual

**Infraestructura (completa y funcional):**
- `NotificationService::send()` → busca template activo (tenant+evento+canal) →
  `TemplateRenderer` (Handlebars) → `NotificationLog` → `SendNotificationJob` (cola).
- 4 canales: SMS (Twilio), WhatsApp (Twilio), Email (SMTP/SendGrid/Mailgun), In-App.
- **Preferencias por usuario**: `NotificationPreference` + UI, se respetan antes de enviar. ✓
- Templates sembrados para ~12 eventos (genéricos) + 6 de crédito (MoneyCapital).

**Eventos que SÍ notifican hoy (7):**
`OTP_SENT`*, `APPLICATION_SUBMITTED`, `APPLICATION_IN_REVIEW`, `APPLICATION_DOCS_PENDING`,
`APPLICATION_CORRECTIONS_REQUESTED`, `APPLICATION_APPROVED`, `APPLICATION_REJECTED`.
Todos desde `ApplicationService::sendNotification()` (privado, llamado en submit/changeStatus/approve/reject).

\* El OTP va por un **camino aparte** (`ApplicantAuthService::dispatchOtp` → Nubarium/Twilio/SMTP
directo, sin cola ni templates). Ya lleva el **origen** (nombre de la SOFOM) tras el último cambio.

**Gaps principales:**
1. **32 eventos huérfanos**: definidos en el enum + varios con template sembrado, pero **ningún
   código los dispara** (DOCUMENT_*, KYC_*, ANALYST_ASSIGNED, COUNTER_OFFER_*, CANCELLED,
   REFERENCE_VERIFIED, BANK_ACCOUNT_VERIFIED, WEBHOOK_FAILED, REMINDER_*, LOAN_*, PAYMENT_*).
2. **Sin gating por capacidad del tenant**: `NotificationService` no verifica si el tenant tiene
   ese canal configurado (`TenantApiConfig`). Intenta canales no configurados → el job falla y
   reintenta 3× antes de marcar FAILED. Ruido y latencia.
3. Cambios de estado sin aviso: `CANCELLED`, `COUNTER_OFFERED`, `SYNCED`.

## 2. Objetivos

- Cerrar los gaps de eventos que el **solicitante** y el **staff** esperan.
- Que un tenant nunca intente un canal que no tiene configurado.
- Mantener el OTP consistente (con origen) sin romper su camino rápido.

## 3. Fases (priorizadas por valor/esfuerzo)

### Fase 0 — Robustez del envío (rápido, alto impacto)
- **Gating por capacidad del tenant** en `NotificationService::send()`: filtrar los canales por
  `TenantApiConfig` activo (`sms`/`email`/`whatsapp`) + In-App siempre disponible. Evita fallos
  preventivos y reintentos inútiles.
- **Fallback de canal**: si el canal recomendado no está disponible, degradar
  (WhatsApp → SMS → Email → In-App) en vez de descartar la notificación.
- Log claro cuando se omite un canal por falta de integración (no como error).

### Fase 1 — Eventos del solicitante (mayor valor percibido)
Patrón: llamar `notificationService->send(evento, cuenta, variables, null, tenant)` en el punto de negocio.
- `DOCUMENT_APPROVED` / `DOCUMENT_REJECTED` — al revisar cada documento (staff). El cliente sabe el resultado.
- `DOCUMENTS_COMPLETE` — cuando se completan todos los requeridos (template ya sembrado).
- `APPLICATION_COUNTER_OFFERED` + `COUNTER_OFFER_ACCEPTED` / `COUNTER_OFFER_REJECTED`.
- `APPLICATION_CANCELLED`.
- `KYC_COMPLETED` / `KYC_FAILED` (enganchar en `VerificationService::updateKycStatus` /
  `IneVerificationService`).

### Fase 2 — Eventos de staff / operativos
- `ANALYST_ASSIGNED` — notificar al analista asignado (template ya sembrado). En `ApplicationService::assign`.
- `REFERENCE_VERIFIED`, `BANK_ACCOUNT_VERIFIED`.
- `COMMENT_ADDED` — al agregar nota (si aplica notificar al solicitante).
- `APPLICATION_SYNCED` — confirmación de webhook a sistema externo OK.
- `WEBHOOK_FAILED` — alerta al SUPER_ADMIN del tenant (canal In-App/Email).

### Fase 3 — Recordatorios (requiere scheduler)
- `REMINDER_PENDING_DOCS`, `REMINDER_INCOMPLETE_PROFILE` (templates ya sembrados).
- Comando artisan `notifications:reminders` + cron (diario): barre solicitudes con docs pendientes
  o perfiles incompletos > N días y dispara el evento. Idempotente (no repetir el mismo día).

### Fase 4 — Crédito (solo módulo MoneyCapital, fuera del core LOS)
- `LOAN_*`, `PAYMENT_*` — templates ya sembrados para MoneyCapital. LendusFind es **agnóstico**
  (no gestiona cartera/pagos), así que esto solo aplica si existe el módulo de crédito con la data
  de préstamos/pagos. Requiere disparadores en ese módulo.

## 4. Patrón de implementación

- Un solo punto por evento, en el **service/observer** correspondiente (no en el controller).
- Reusar el helper `ApplicationService::sendNotification()` como referencia; extraer variables del
  contexto (`user.*`, `application.*`, `document.*`, `analyst.*`, `tenant.*`).
- Todo pasa por `SendNotificationJob` (cola), excepto el OTP (camino directo por latencia).

## 5. Decisiones a confirmar

- **OTP**: se recomienda dejarlo en su camino directo (rápido, sin cola, ya con origen). Los
  templates OTP sembrados quedarían solo de referencia. ¿De acuerdo o unificar a templates?
- **Recordatorios**: ¿umbral de días (p.ej. 3) y frecuencia (diaria)?
- **Fase 4 (crédito)**: ¿entra en este alcance o es del roadmap del módulo MoneyCapital?
- **Canales por defecto**: ¿respetamos `getRecommendedChannels()` del evento o defines una matriz
  evento×canal por tenant en el admin?
