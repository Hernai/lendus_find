# Design — Integración saliente y handoff a cartera

## Context

CLAUDE.md posiciona LendusFind como "Integration First" pero la capa nunca se construyó.
Piezas dormidas que este diseño reactiva:

- **Tabla `webhooks`** (migración `2026_01_06_000010`): schema completo de cola de
  entrega — `event`, `model_type/model_id`, `payload` JSON, `url`, `status`
  (PENDING/SENT/FAILED/RETRYING), `attempts`, `max_attempts`, `next_retry_at`,
  `response_code`, `response_body`, `error_message`; índices `(tenant_id,status)` y
  `(status,next_retry_at)`. Sin modelo ni servicio.
- **`WebhookStatus`** enum (PENDING/SENT/FAILED/RETRYING) y `NotificationEvent::WEBHOOK_FAILED`.
- **Ciclo `SYNCED`**: `Application` tiene `external_id`, `external_system`, `sync_data`,
  `markSynced()`, transición `APPROVED → SYNCED` (terminal). Nunca cableado.
- **`NotificationEvent`** ya nombra los eventos: `application.approved/rejected`,
  `loan.disbursed/completed`, `payment.received` — se reutilizan como nombres de webhook.
- **Pagos**: `LoanPayment` (canales CONEKTA/OPENPAY/STP/MANUAL; estados
  PENDING/COMPLETED/FAILED/REFUNDED) y `LoanService::recordPayment()` (recalcula saldo,
  dispara liquidación/recompensa/renovación).
- **Patrón de encriptación**: `TenantApiConfig` guarda credenciales con `Crypt` — mismo
  patrón para el secreto del endpoint.
- **Webhook entrante existente**: token-en-URL (`NubariumWebhookController`).

## Goals / Non-Goals

**Goals:** capa de integración bidireccional, firmada, con reintentos y auto-gestionable;
handoff LOS→cartera completo (autorización, dispersión, pago, liquidación, acuse de
ingesta); documentación que desbloquee al desarrollador externo.

**Non-Goals:** ver `proposal.md § Non-goals`.

## Decisiones (grilling)

| # | Decisión | Elección | Descartado |
|---|----------|----------|------------|
| 1 | Dirección | Bidireccional (saliente + entrante) | Solo saliente; solo entrante |
| 2 | Payload | Completo + API de re-consulta | Aviso ligero + pull; completo sin API |
| 3 | Seguridad | HMAC-SHA256 + timestamp anti-replay | Bearer token; mTLS |
| 4 | Suscripciones | Tabla `webhook_endpoints`; `webhooks`=log | `tenant.webhook_config`; TenantApiConfig |
| 5 | Eventos v1 | Originación + cartera (5 eventos) | Todo el ciclo; mínimo |
| 6 | Entrante | Pago + acuse de ingesta, idempotente | Solo pago; genérico de estado |
| 7 | Admin UI | Gestión + log + reenviar + probar | Solo alta; sin UI |
| 8 | Docs | Guía Markdown en repo | OpenAPI; Artifact |
| — | Reintentos | Backoff exponencial, 5 intentos, luego FAILED + `WEBHOOK_FAILED` + reenvío manual | — |
| — | Idempotencia | `event_id` UUID por evento (saliente) y `Idempotency-Key`/id externo (entrante) | — |

## Arquitectura

### Datos

**`webhook_endpoints`** (UUID, `HasTenant`, `HasUuid`, `HasAuditFields`):

- `tenant_id`, `name`, `url`, `secret` (encriptado con `Crypt`), `events` (JSONB — lista
  de nombres suscritos o `["*"]`), `is_active`, `is_sandbox`, `description`,
  `last_success_at`, `last_failure_at`, timestamps, softDeletes.
- Índice `(tenant_id, is_active)`.

**`webhooks`** (reactivada como **log de entregas**; migración aditiva):

- Columnas nuevas: `webhook_endpoint_id` (FK), `event_id` (UUID del evento — mismo para
  todas las entregas del evento, permite dedupe del lado receptor), `idempotency_key`
  (único por `(endpoint, event_id)`), `signature` (la firma enviada, para auditoría).
- Se conservan `event`, `payload`, `url`, `status`, `attempts`, `max_attempts`,
  `next_retry_at`, `response_code`, `response_body`, `error_message`.

**`inbound_events`** (idempotencia entrante; UUID, `HasTenant`):

- `tenant_id`, `endpoint_id` nullable, `external_event_id` (id que manda la cartera),
  `type` (`payment` | `ingest_ack`), `payload` JSONB, `status`
  (`processed` | `duplicate` | `failed`), `result` JSONB, `received_at`.
- Único `(tenant_id, external_event_id)` → dedupe.

### Sobre del payload (saliente)

Todo webhook comparte un **sobre versionado**; `data` cambia por `type` de recurso.

```jsonc
{
  "id": "evt_9f8c…",                 // event_id (UUID) — idempotencia del receptor
  "event": "application.approved",   // nombre del evento (NotificationEvent)
  "version": "1",                    // versión del esquema del sobre
  "occurred_at": "2026-07-15T18:04:22Z",
  "tenant": { "id": "…", "slug": "moneycapital" },
  "data": { /* recurso estandarizado, ver abajo */ }
}
```

Recurso `application` (en `application.approved/rejected`) y `loan`
(en `loan.disbursed/completed`, `payment.received`) siguen un **esquema estable
documentado** (folio, persona/KYC, montos y condiciones aprobadas, cuenta CLABE de
dispersión, producto). El esquema exacto y ejemplos por evento viven en
`docs/integracion/webhooks.md` (fuente de verdad) y en `WebhookPayloadBuilder`.

### Firma (HMAC-SHA256)

- Cabeceras: `X-LendusFind-Signature: sha256=<hex>`,
  `X-LendusFind-Timestamp: <unix>`, `X-LendusFind-Event: <nombre>`,
  `X-LendusFind-Delivery: <delivery_id>`.
- Base firmada: `"{timestamp}.{raw_body}"`; clave = `secret` del endpoint.
- El receptor recomputa y compara en tiempo constante; rechaza si el timestamp está fuera
  de una ventana (±5 min) → anti-replay. Doc con ejemplo en 3 lenguajes.

### Emisión

- Hooks: `Application::approve/reject`, `LoanService::createFromApplication` (dispersión),
  `recordPayment` (pago + posible liquidación → `loan.completed`). Cada hook llama
  `WebhookService::emit($event, $model)`.
- `emit` genera `event_id`, construye el payload con `WebhookPayloadBuilder`, y crea **una
  fila `webhooks` por endpoint activo suscrito** (estado PENDING); despacha
  `DeliverWebhookJob` por cada una. Sin endpoints suscritos → no-op (no rompe el flujo).
- La emisión es best-effort respecto al flujo de negocio: un fallo al encolar se loguea,
  nunca revierte la aprobación/pago.

### Entrega y reintentos (`DeliverWebhookJob`)

- POST con timeout corto; 2xx → `SENT` (`last_success_at`). No-2xx/timeout → incrementa
  `attempts`, calcula `next_retry_at` con backoff exponencial (p.ej. 1m, 5m, 30m, 2h, 6h),
  estado `RETRYING`; agotados los 5 intentos → `FAILED` + notificación interna
  `WEBHOOK_FAILED` al staff. Comando `webhooks:dispatch-retries` (scheduler) re-encola las
  `RETRYING` con `next_retry_at` vencido. Reenvío manual desde el panel resetea a PENDING.

### API entrante (cartera → LendusFind)

Prefijo `POST /api/webhooks/inbound/{endpoint}/…` (público, autenticado por HMAC del
endpoint; el `{endpoint}` en la URL localiza el secreto). Verificación de firma +
ventana de timestamp + idempotencia por `external_event_id` (tabla `inbound_events`).

- `POST …/payments` → registra el pago (`LoanService::recordPayment` con canal del
  proveedor y `provider_reference`); devuelve el saldo resultante. Reejecución con el
  mismo `external_event_id` → `duplicate` sin doble cargo.
- `POST …/ingest-ack` → marca la `Application` `SYNCED` (`markSynced($external_id,
  $external_system, $sync_data)`); idempotente.

### API de re-consulta (lectura)

- `GET /api/v2/integration/applications/{id}` y `/loans/{id}` — mismo esquema del payload
  saliente. Auth: token de integración del tenant (Sanctum con ability `integration`) o
  HMAC; scoped por tenant. Para reconciliar o recuperar un evento perdido.

### Admin (staff, prefijo `/api/v2/staff/webhooks`)

| Endpoint | Permiso |
|----------|---------|
| CRUD `/endpoints`, rotar secreto, activar/sandbox | `canManageProducts` |
| `GET /deliveries` (log, filtros por endpoint/estado/evento) | `canManageProducts` |
| `POST /deliveries/{id}/retry` (reenviar) | `canManageProducts` |
| `POST /endpoints/{id}/test` (evento de prueba) | `canManageProducts` |

*(alineado con el permiso del configurador del motor: el ADMIN del tenant gestiona sus
integraciones; SUPER_ADMIN cruza tenants con TenantSwitcher.)*

### Frontend

Módulo admin "Webhooks" (sistema de módulos + `TenantRoleModuleOverride`): lista de
endpoints con estado/último éxito; alta/edición (URL, checklist de eventos, ver/rotar
secreto, activar, sandbox); log de entregas con estado/código/respuesta y botón
"Reenviar"; botón "Enviar evento de prueba".

## Risks / Trade-offs

- [Un endpoint lento/caído frena la cola] → entrega async por job con timeout; el backoff
  aísla a los sanos; el circuito no bloquea el flujo de negocio.
- [Secreto filtrado] → rotable por endpoint desde el panel sin afectar otros; sandbox
  separa pruebas de producción.
- [Reintentos duplican efectos en el receptor] → `event_id` estable en el sobre + doc que
  exige idempotencia del lado receptor; entrante idempotente por `external_event_id`.
- [Payload expone datos sensibles] → HTTPS obligatorio + HMAC; el esquema estandarizado
  excluye secretos (no manda tokens/credenciales); scoping por tenant estricto.
- [Cambios de esquema rompen integradores] → `version` en el sobre; cambios incompatibles
  suben versión y se documentan; los campos solo se agregan (aditivo) dentro de una versión.

## Migration Plan

1. Migraciones aditivas (2 tablas nuevas + columnas en `webhooks`) — sin backfill.
2. Deploy sin endpoints configurados = sin emisión (no-op). Comportamiento actual intacto.
3. El cliente registra su endpoint en sandbox, valida firma y payloads con "enviar
   prueba", luego lo pasa a producción/activo.
4. Rollback: desactivar el endpoint (deja de emitir) — sin deploy.

## Open Questions

- Ninguna bloqueante. El **esquema exacto de cada recurso** (qué campos del expediente
  viajan) se fija en `docs/integracion/webhooks.md` y `WebhookPayloadBuilder` durante la
  implementación, partiendo de los campos ya existentes en `Application`/`Loan`/`Person`.
