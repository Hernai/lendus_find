# Contraoferta desde admin + pantalla de aceptación del solicitante

## Why

La contraoferta está construida a medias en todo el stack: el estado `COUNTER_OFFERED`
existe en el enum pero la máquina de estados nunca lo asigna, el modal admin captura
plazos en meses aunque MoneyCapital opera en días, y la pantalla del solicitante
(`LoanOfferView`, ya diseñada según mockup) manda un payload que el backend no entiende
(`accept` vs `accepted`), por lo que aceptar/rechazar no funciona hoy. MoneyCapital
necesita este flujo completo para operar su producto "Préstamo Sin Buró" (montos
$300–$15,000, plazos 1–30 días, bullet).

## What Changes

- `COUNTER_OFFERED` se vuelve estado real de la máquina de estados del modelo
  `Application`: `IN_REVIEW`/`DOCS_PENDING` → `COUNTER_OFFERED`; de ahí → `APPROVED`
  (cliente acepta, copiando términos a `approved_*`) o → `CANCELLED` (cliente rechaza,
  staff cancela, o la oferta expira).
- El modal admin de contraoferta se vuelve adaptivo por producto: si
  `product.rules.term_in_days`, captura plazo en días (validado contra
  `min/max_term_days`) y la tasa se toma fija del producto; para productos en meses
  sigue como hoy. Captura vigencia de la oferta (default 30 minutos).
- El backend guarda en el JSONB `counter_offer` un snapshot completo del pricing
  (monto, plazo en días o meses, tasa, comisión de apertura, `expires_at`) para que la
  pantalla no dependa de la config pública ni cambie retroactivamente.
- Contraofertas expiradas se auto-cancelan vía comando programado (scheduler ya
  operativo en prod) con notificación al solicitante.
- La pantalla del solicitante MoneyCapital es la `LoanOfferView` existente, cableada al
  backend real: valores fijos (sliders bloqueados), línea de comisiones (13% + IVA),
  total a pagar, fecha límite, cuenta regresiva de vigencia, aceptar + rechazo discreto.
- Redirect automático: al entrar a la app con la solicitud activa en `COUNTER_OFFERED`,
  el solicitante aterriza en la pantalla de oferta (con salida libre y tarjeta en el
  dashboard para volver). Aplica a todos los tenants; para tenants en meses
  (demo/finatea) la oferta se muestra en `ApplicationStatusView` con su propia sección.
- **Fix**: se estandariza el payload de respuesta en `accepted` (lo que el backend
  valida); esto repara los botones de aceptar/rechazar que ya existen en el dashboard.
- Se emiten por fin los eventos `COUNTER_OFFER_ACCEPTED` / `COUNTER_OFFER_REJECTED`
  (los templates ya existen) y la expiración notifica con razón explícita.
- Migración: nueva columna `approved_term_days` (gemela de `requested_term_days`) para
  registrar el plazo aprobado de productos en días.

## Capabilities

### New Capabilities

- `counter-offer-lifecycle`: estado `COUNTER_OFFERED` en la máquina de estados,
  transiciones de aceptación/rechazo/cancelación, vigencia con auto-expiración
  programada, snapshot de pricing en `counter_offer`, eventos de notificación.
- `counter-offer-admin`: creación y reenvío de contraofertas desde el detalle de
  solicitud del admin, con modal adaptivo por producto (días/meses) y vigencia
  configurable.
- `counter-offer-applicant`: experiencia del solicitante — pantalla de oferta
  MoneyCapital (días, valores fijos, comisiones, countdown), sección de contraoferta
  en `ApplicationStatusView` para tenants en meses, redirect como pantalla principal,
  aceptar/rechazar.

### Modified Capabilities

(ninguna — no hay specs previas en `openspec/specs/`)

## Non-goals

- Motor de pricing / auto-recálculo de ofertas al expirar (el texto del mockup "volverá
  a autocalcularse" se ajusta; al expirar solo se auto-cancela).
- Rangos ajustables por el cliente (los sliders quedan bloqueados en valores fijos).
- Cambiar el pricing comercial de MoneyCapital: el cálculo usa las rules actuales
  (`annual_rate` 36%, `opening_commission` 13%); los números del mockup son placeholder.
- Desembolso, contrato/firma electrónica y todo lo post-`APPROVED`.
- Notificaciones nuevas por canal (se usan los templates y canales ya configurados).

## Impact

- **Backend**: `Application` (constantes de estado, `STATUS_TRANSITIONS`,
  `sendCounterOffer`, `respondToCounterOffer`), `ApplicationService`, controller
  Staff/Applicant `ApplicationController`, migración `approved_term_days`, comando
  programado nuevo + registro en `routes/console.php`, enum `NotificationEvent` (sin
  cambios de enum; se emiten eventos ya definidos).
- **Frontend**: `CounterOfferModal.vue`, `ApplicationDetailHeader.vue` (gating por
  estado), `LoanOfferView.vue`, `ApplicationStatusView.vue`, `DashboardView.vue`
  (payload + tarjeta + redirect), router/guard de entrada, types V2
  (`V2CounterOffer*`), services applicant/staff.
- **Tenants**: backend global; UX en días para MoneyCapital, en meses para demo/finatea.
  El redirect valida tenant actual (bug conocido de localStorage compartido).
- **Datos existentes**: no hay solicitudes vivas en `COUNTER_OFFERED` (el estado nunca
  se asignó), no se requiere backfill.
