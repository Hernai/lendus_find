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

### Fase 0 — Robustez del envío ✅ HECHO
- **Gating por capacidad del tenant** en `NotificationService::send()`: filtra los canales por lo
  que el tenant realmente puede enviar (Twilio en settings, SMS/email por integración, In-App/Push
  siempre). Evita fallos preventivos y los 3 reintentos del job. Fallback a In-App.
- **SMS vía Nubarium** en `SendNotificationJob::sendSms` (misma credencial del OTP), para que las
  notificaciones SMS lleguen en tenants que usan Nubarium y no Twilio.
- Bonus: fix del bug `{{user.*}}` vs `applicant.*` (el nombre salía vacío en TODAS las
  notificaciones de solicitud).

### Fase 1 — Eventos del solicitante ✅ HECHO (falta KYC_FAILED)
- ✅ `DOCUMENT_APPROVED` / `DOCUMENT_REJECTED` — al revisar cada documento (staff).
- ✅ `DOCUMENTS_COMPLETE` — al aprobar el último documento pendiente (único punto que lo notifica,
  porque el auto-avance de status no pasa por changeStatus).
- ✅ `APPLICATION_COUNTER_OFFERED` — con datos de la oferta (monto/plazo/pago).
- ✅ `APPLICATION_CANCELLED` — solo si no la canceló el propio solicitante.
- ✅ `KYC_COMPLETED` — en `VerificationService::updateKycStatus` (best-effort).
- ⏳ `KYC_FAILED` — pendiente: **no hay un punto claro de "KYC falló definitivo"** en el código
  (el KYC se reintenta, no tiene estado FAILED terminal). Requiere definir el disparador.
- ⏳ `COUNTER_OFFER_ACCEPTED` / `COUNTER_OFFER_REJECTED` — van a **staff**, se mueven a Fase 2.

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
