# Integración saliente: webhooks y handoff a cartera externa

## Why

CLAUDE.md define LendusFind como **"Integration First": envía JSON estandarizado vía
webhooks a sistemas externos (SAP, core bancario)**. Hoy esa capa no existe: la tabla
`webhooks` está dormida (schema completo, cero código), el estado `SYNCED` con
`external_id/external_system/sync_data` y `markSynced()` nunca se cablearon, y no hay
forma de que un sistema de cartera reciba "crédito autorizado", confirme pagos aplicados
ni acuse la ingesta de datos. Sin esto, LendusFind origina créditos que quedan aislados
del core que los administra.

*Grilling en esta conversación: 8 decisiones (ver `design.md § Decisiones`).*

## What Changes

- **Webhooks salientes firmados**: al ocurrir un evento del ciclo, LendusFind entrega un
  POST HTTP con el **payload completo estandarizado** (sobre versionado) a los endpoints
  suscritos, firmado con **HMAC-SHA256** (`X-LendusFind-Signature` + `X-LendusFind-Timestamp`).
  Cola con reintentos (backoff exponencial), log de entregas y reenvío manual.
- **Registro de endpoints** (`webhook_endpoints`): per-tenant, con URL, secreto
  encriptado, eventos suscritos, `is_active`, `is_sandbox`. La tabla `webhooks` existente
  pasa a ser el **log de entregas** (FK al endpoint). Varios endpoints por tenant.
- **Catálogo de eventos v1** (originación + cartera): `application.approved`,
  `application.rejected`, `loan.disbursed`, `loan.completed`, `payment.received`.
- **Dispersión externa**: la cartera externa dispersa (no el STP interno; `auto_disbursement`
  queda apagado para esos tenants). Al aceptar la oferta, LendusFind crea el `Loan` en
  `PENDING_DISBURSEMENT` y emite `application.approved` con la **CLABE completa** (señal de
  "dispersa"). La cartera confirma de vuelta y LendusFind activa el crédito.
- **API entrante** (cartera → LendusFind), HMAC verificado e idempotente por id externo:
  (1) **confirmación de dispersión** → `Loan` a `ACTIVE` + `Application` a `SYNCED` +
  reemite `loan.disbursed`; (2) **confirmación de pago** → `LoanService::recordPayment`
  (actualiza saldo, dispara liquidación/renovación); (3) **acuse de ingesta** (opcional,
  si ingesta y dispersión son pasos separados) → marca `SYNCED`.
- **API de re-consulta** (lectura): `GET` del expediente/crédito estandarizado para que
  el externo reconcilie o recupere un evento perdido, con el mismo esquema del payload.
- **Panel admin "Integraciones / Webhooks"**: alta/edición de endpoints (ver/rotar
  secreto, activar/sandbox), log de entregas con estado/código/respuesta, reenviar una
  entrega fallida y enviar evento de prueba.
- **Guía del integrador** (`docs/integracion/webhooks.md`): catálogo de eventos, esquema
  del sobre + ejemplos JSON reales por evento, verificación de firma con código
  (PHP/Node/Python), API entrante y de re-consulta, reintentos e idempotencia, checklist
  y guía de pruebas.

## Capabilities

### New Capabilities

- `webhook-endpoints`: modelo y ciclo de vida de los endpoints suscriptores (URL,
  secreto, eventos, activo/sandbox) per-tenant.
- `webhook-delivery`: emisión desde el ciclo de vida, firma HMAC, cola de entregas con
  reintentos/backoff, log e idempotencia de emisión.
- `integration-inbound-api`: API entrante de confirmación de pago y acuse de ingesta,
  con verificación de firma e idempotencia.
- `integration-read-api`: API de lectura del expediente/crédito estandarizado.
- `webhook-admin-ui`: panel de gestión de endpoints y log de entregas (reenviar, probar).

### Modified Capabilities

*(ninguna — todo es capa nueva; los eventos reutilizan `NotificationEvent` pero no
cambian su comportamiento)*

## Non-goals

- Eventos fuera del set de originación+cartera (submitted, in_review, counter_offered,
  overdue, default…): el catálogo es configurable, se agregan después sin re-diseño.
- API entrante genérica de cambios de estado arbitrarios (solo pago + acuse; LendusFind
  conserva el control de sus transiciones).
- mTLS, OAuth2 client-credentials, colas externas (Kafka/SNS): HMAC + reintentos propios
  cubren el piloto.
- SDKs generados / OpenAPI: la guía Markdown es la fuente de verdad de v1.
- Reemplazar el portafolio interno (`Loan`): LendusFind sigue originando y sirviendo el
  Loan interno; la cartera externa es un consumidor/espejo, no un reemplazo.

## Impact

- **Backend**: tabla nueva `webhook_endpoints` + reactivación de `webhooks` (migración
  aditiva: `webhook_endpoint_id`, `idempotency_key`); modelos `WebhookEndpoint`,
  `WebhookDelivery`; `WebhookService` (firma, emisión) + `DeliverWebhookJob` (entrega +
  backoff) + comando de barrido de reintentos; hooks de emisión en `Application::approve/
  reject`, `LoanService` (disburse/complete/recordPayment); controllers entrantes
  (`Api/Webhooks/`) y de re-consulta (`Api/V2/Integration/`); endpoints staff de gestión;
  reuso de `WebhookStatus` y `NotificationEvent`.
- **Frontend**: módulo admin "Webhooks" (endpoints + log + reenviar + probar); tipos V2.
- **Docs**: `docs/integracion/webhooks.md` (guía del integrador con ejemplos).
- **Seguridad**: secretos de endpoint encriptados (patrón `Crypt` de `TenantApiConfig`);
  firma HMAC saliente y verificación entrante; ventana de timestamp anti-replay.
- **Multitenancy**: endpoints y entregas scoped por `HasTenant`; el payload nunca cruza
  tenants.
- **Datos existentes**: sin backfill; solo eventos nuevos emiten. La tabla `webhooks`
  está vacía hoy, así que reactivarla no rompe nada.
