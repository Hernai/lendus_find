# Design — contraoferta-admin-aceptacion

## Context

La infraestructura de contraoferta existe pero está desconectada:

- `ApplicationStatus::COUNTER_OFFERED` existe (label, color, icon) pero el modelo
  `Application` usa constantes propias que no lo incluyen; `sendCounterOffer()`
  (`Application.php:630`) solo escribe `decision` + JSONB sin cambiar `status`.
- Endpoints ya montados: staff `POST /v2/staff/applications/{id}/counter-offer`
  (`api.php:449`) y applicant `POST /v2/applicant/applications/{id}/counter-offer/respond`
  (`api.php:217`).
- `CounterOfferModal.vue` captura `term_months`/tasa/frecuencia; MoneyCapital opera en
  días (`product.rules`: `term_in_days`, `min/max/default_term_days`, `annual_rate` 36,
  `opening_commission` 13, BULLET).
- `LoanOfferView.vue` (ruta `m-loan-offer`, `/m/solicitud/:id/oferta`) ya implementa el
  mockup: sliders, resumen, countdown de `expires_at`, contrato, aceptar/rechazar; manda
  `{ accept }` pero el backend valida `accepted` → roto.
- `respondToCounterOffer()` al rechazar no cambia el estado aunque el mensaje del
  controller dice "solicitud cancelada".
- `COUNTER_OFFER_ACCEPTED`/`COUNTER_OFFER_REJECTED` definidos en `NotificationEvent` con
  templates seedeados, nunca emitidos.
- Scheduler de Laravel ya operativo en prod (`routes/console.php`, comandos audit-logs).

Todas las decisiones de producto salen de la sesión de grilling de esta conversación.

## Goals / Non-Goals

**Goals:**

- Estado `COUNTER_OFFERED` real y navegable en la máquina de estados.
- Admin crea/reenvía contraofertas en días o meses según el producto, con vigencia.
- Solicitante ve la oferta (monto + días fijos), acepta → `APPROVED` o rechaza →
  `CANCELLED`, desde su "pantalla principal" cuando hay oferta activa.
- Auto-cancelación de ofertas expiradas con notificación.
- Reparar el payload `accept`/`accepted` para todos los tenants.

**Non-Goals:**

- Motor de pricing / auto-recálculo al expirar; rangos ajustables; cambios de pricing
  comercial; flujo post-APPROVED (desembolso, contrato); canales de notificación nuevos.

## Decisions

### D1. `COUNTER_OFFERED` como estado real (grilling #1)

Agregar `STATUS_COUNTER_OFFERED = 'COUNTER_OFFERED'` a las constantes del modelo y al
mapa `STATUS_TRANSITIONS`:

- `IN_REVIEW` y `DOCS_PENDING` ganan transición → `COUNTER_OFFERED` (mismos estados en
  que el admin ya ve el botón).
- `COUNTER_OFFERED` → `APPROVED` (acepta), `CANCELLED` (rechaza / expira / staff).
- Reenviar contraoferta estando en `COUNTER_OFFERED` NO transiciona: sobreescribe el
  JSONB y renueva `expires_at` (evita ensuciar el historial con self-transitions).

*Alternativa descartada*: flag JSONB sin estado (invisible en filtros/listas admin y el
routing del solicitante dependería de un campo extra).

### D2. Snapshot de pricing en `counter_offer` (derivada de hechos)

`sendCounterOffer()` guarda el snapshot completo:

```json
{
  "amount": 700, "term_days": 10, "term_months": null,
  "interest_rate": 36, "opening_commission": 13,
  "reason": "...", "offered_by": "<staff-uuid>", "offered_at": "...",
  "expires_at": "...", "responded_at": null, "accepted": null
}
```

`term_days` XOR `term_months` según `product.rules.term_in_days`. La tasa y comisión se
copian del producto al momento de ofertar — la pantalla del solicitante lee TODO del
JSONB vía el endpoint de su solicitud, sin depender de la whitelist `formatRules` de
`/api/v2/config` ni de cambios futuros del producto.

### D3. Aceptar → APPROVED con copia a `approved_*` (grilling #3)

`respondToCounterOffer(accepted: true)`: transición validada a `APPROVED`, copiando
`amount → approved_amount`, `term_months → approved_term_months`,
`term_days → approved_term_days` (columna nueva), `interest_rate →
approved_interest_rate`. Emite `COUNTER_OFFER_ACCEPTED`. El staff ya definió los
términos; no hay segundo clic de aprobación.

### D4. Rechazar → CANCELLED (grilling #4)

`respondToCounterOffer(accepted: false)`: transición a `CANCELLED` con razón "El cliente
rechazó la contraoferta". Emite `COUNTER_OFFER_REJECTED`. Semántica: `REJECTED` es
decisión de la financiera; `CANCELLED` es del cliente.

### D5. Modal admin adaptivo por producto (grilling #5)

`CounterOfferModal.vue` detecta `product.rules.term_in_days`:

- **Días**: campo "Plazo (días)" validado contra `min/max_term_days`; sin campo de tasa
  ni frecuencia (fijas del producto); payload `{ amount, term_days, reason, expires_in_minutes }`.
- **Meses**: comportamiento actual (`term_months`, tasa editable) + vigencia.

Backend valida `term_days` XOR `term_months` (`required_without`), rangos contra las
rules del producto. Campo de vigencia con default 30 minutos (grilling #8).

*Alternativa descartada*: ramificar por slug del tenant (rompe white-label).

### D6. Expiración: auto-cancelar vía comando programado (grilling #9)

Comando `counter-offers:expire` registrado en `routes/console.php` con
`everyMinute()->withoutOverlapping()->onOneServer()`. Barre solicitudes en
`COUNTER_OFFERED` con `counter_offer->expires_at < now()` y las transiciona a
`CANCELLED` (razón "La contraoferta expiró sin respuesta"), notificando
`APPLICATION_CANCELLED`. Sin job diferido por-oferta: el reenvío renueva `expires_at`
sin necesidad de invalidar jobs encolados.

La pantalla además deshabilita aceptar client-side cuando el countdown llega a 0 (el
comando puede tardar hasta 1 min en pasar).

### D7. Pantalla del solicitante (grilling #6, #7, #12)

- **MoneyCapital (días)**: `LoanOfferView` cableada — carga el `counter_offer` real,
  valores fijos (min=max → sliders bloqueados, ya soportado), agrega línea "Comisiones
  (IVA incluido)" (`amount × opening_commission% × 1.16`), interés desde
  `interest_rate` del snapshot, total y fecha límite. Texto del hero ajustado a
  contraoferta y sin promesa de auto-recálculo. Payload `{ accepted }`.
- **Tenants en meses**: sección de contraoferta en `ApplicationStatusView` (monto,
  plazo en meses, tasa, aceptar/rechazar) reutilizando el mismo service.

### D8. Redirect como pantalla principal (grilling #11)

Al resolver la entrada del applicant autenticado, si su solicitud activa está en
`COUNTER_OFFERED`, `router.replace` hacia la pantalla de oferta (`m-loan-offer` si el
producto es en días; `/solicitud/:id/estado` si es en meses). Hay DOS puntos de entrada
y ambos llevan el redirect con la misma llave de sessionStorage (una vez por pestaña):
`DashboardView` (web) y `MobileHomeView` (`/m/home`, el home real del flujo móvil
MoneyCapital — descubierto en el smoke: el login móvil aterriza ahí, no en el
dashboard). Salida libre: ambos muestran tarjeta "Ver oferta"/"Tu oferta está lista"
para volver.
La verificación se hace contra la solicitud **del tenant actual** (guard existente de
separación de tenants — bug conocido de localStorage compartido entre subdominios).

### D9. Fix de contrato de API: `accepted` (derivada de hechos)

El backend ya valida `accepted: required|boolean` — se corrige el frontend:
`V2CounterOfferResponsePayload` pasa de `accept` a `accepted`; se eliminan
`amount`/`term_days` del payload (valores fijos: el server usa el snapshot). Call sites:
`LoanOfferView.respond()`, `DashboardView.acceptCounterOffer/rejectCounterOffer`.

## Risks / Trade-offs

- [Solicitudes que ya tienen `decision = COUNTER_OFFER` sin estado] → No existen en
  prod (el estado nunca se asignó y el flujo estaba roto); no hay backfill. Verificar
  con un query antes de deploy por higiene.
- [El comando corre cada minuto: oferta "expirada" aceptable hasta 60s] → El client-side
  bloquea aceptar al llegar a 0; el backend valida `expires_at` en
  `respondToCounterOffer` como cinturón (rechaza aceptaciones tardías con 422).
- [Push/afterware de notificaciones depende de config per-tenant] → Se emiten los
  eventos; si un tenant no tiene template/canal activo simplemente no envía (patrón
  actual). Verificar templates de MoneyCapital ya seedeados.
- [Modal admin en meses cambia (campo vigencia nuevo)] → Default 30 min pre-cargado; no
  rompe el flujo actual de demo/finatea.
- [Re-seed de tenants en cada deploy] → No se tocan rules del producto (pricing queda
  como está por grilling #10); sin riesgo de pisar config.

## Migration Plan

1. Migración `approved_term_days` (nullable, sin backfill) — deploy backend primero.
2. Backend + comando programado (no requiere cron nuevo; scheduler ya corre).
3. Frontend (modal, vistas, redirect, types) — compatible con backend nuevo desde el
   primer deploy porque el contrato `accepted` ya era el del backend.
4. Rollback: revertir deploy; la columna nueva es nullable e inocua.

## Open Questions

(ninguna — todas las decisiones quedaron resueltas en la sesión de grilling)
