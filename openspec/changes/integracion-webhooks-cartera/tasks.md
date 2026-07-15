# Tasks — integracion-webhooks-cartera

## 1. Datos y modelos

- [ ] 1.1 Migración `create_webhook_endpoints_table` (url, secret encriptado, events JSONB, is_active, is_sandbox, name, last_success/failure_at; índice tenant+active)
- [ ] 1.2 Migración aditiva sobre `webhooks`: `webhook_endpoint_id` (FK), `event_id`, `idempotency_key` (único por endpoint+event_id), `signature`
- [ ] 1.3 Migración `create_inbound_events_table` (external_event_id único por tenant, type, payload, status, result, received_at)
- [ ] 1.4 Modelos `WebhookEndpoint` (Crypt del secreto, cast events, scopes), `WebhookDelivery` (sobre la tabla webhooks), `InboundEvent` — HasTenant/HasUuid/HasAuditFields
- [ ] 1.5 Reuso de `WebhookStatus` y `NotificationEvent`; catálogo de eventos v1 como constante

## 2. Emisión y firma (saliente)

- [ ] 2.1 `WebhookPayloadBuilder`: sobre versionado + recurso estandarizado `application`/`loan` (folio, persona/KYC, montos, condiciones aprobadas, CLABE, producto) — mismo builder que la API de lectura
- [ ] 2.2 `WebhookSigner`: HMAC-SHA256 de `timestamp.body`, cabeceras X-LendusFind-*
- [ ] 2.3 `WebhookService::emit($event, $model)`: event_id, resuelve endpoints activos suscritos, crea entregas PENDING, despacha job; best-effort (no revierte negocio)
- [ ] 2.4 Hooks de emisión: `Application::approve/reject`, `recordPayment` (payment.received + loan.completed al liquidar). `loan.disbursed` se emite desde la confirmación entrante (cartera externa) o desde `LoanService` (dispersión interna)
- [ ] 2.5 Estado `PENDING_DISBURSEMENT` en `LoanStatus`; para tenants con cartera externa `createFromApplication` crea el Loan pendiente (sin STP interno) — el `auto_disbursement` interno se gatea por tenant

## 3. Entrega y reintentos

- [ ] 3.1 `DeliverWebhookJob`: POST firmado con timeout; 2xx→SENT; fallo→RETRYING con backoff (1m/5m/30m/2h/6h); agota→FAILED + `WEBHOOK_FAILED`; persiste response en el log
- [ ] 3.2 Comando `webhooks:dispatch-retries` (scheduler) re-encola RETRYING vencidas
- [ ] 3.3 Tests: emisión por evento, sin endpoints=no-op, firma válida, backoff, agotar reintentos, aislamiento por tenant

## 4. API entrante (cartera → LendusFind)

- [ ] 4.1 Middleware/verificación HMAC de endpoint entrante + ventana de timestamp + dedupe por `external_event_id` (InboundEvent)
- [ ] 4.2 `POST /api/webhooks/inbound/{endpoint}/disbursement` → Loan PENDING_DISBURSEMENT→ACTIVE + Application→SYNCED + emite loan.disbursed; idempotente
- [ ] 4.3 `POST /api/webhooks/inbound/{endpoint}/payments` → recordPayment; idempotente; devuelve saldo
- [ ] 4.4 `POST /api/webhooks/inbound/{endpoint}/ingest-ack` → markSynced(external_id); idempotente
- [ ] 4.5 Tests: dispersión→ACTIVE+SYNCED+emite, pago aplicado, reintento duplicado, firma inválida (401), estado inválido

## 5. API de re-consulta

- [ ] 5.1 `GET /api/v2/integration/applications/{id}` y `/loans/{id}` con el mismo builder; auth token integración (ability `integration`) o HMAC; scoped por tenant
- [ ] 5.2 Tests: esquema idéntico al webhook, aislamiento por tenant, 403 sin ability

## 6. API staff + panel admin

- [ ] 6.1 `Staff\WebhookEndpointController` (CRUD, rotar secreto, activar/sandbox) + `WebhookDeliveryController` (index/retry) + test-event — permiso canManageProducts; rutas
- [ ] 6.2 Módulo admin "Webhooks": lista de endpoints, alta/edición (URL HTTPS, checklist de eventos, ver/rotar secreto, sandbox), log de entregas con filtros, reenviar, enviar prueba
- [ ] 6.3 Service + tipos V2 frontend; registro en el sistema de módulos (visibilidad por rol)
- [ ] 6.4 Tests de permisos (ADMIN gestiona, ANALYST 403, aislamiento por tenant)

## 7. Documentación del integrador

- [ ] 7.1 `docs/integracion/webhooks.md`: overview, catálogo de eventos, esquema del sobre + ejemplos JSON reales por evento, verificación de firma (PHP/Node/Python), API entrante y de re-consulta, reintentos/idempotencia, checklist y guía de pruebas (sandbox) — *(borrador entregado con el spec; se ajusta a los campos reales al implementar)*

## 8. Verificación integral

- [ ] 8.1 `php artisan test` de los grupos nuevos + `vue-tsc` + lint
- [ ] 8.2 Smoke: alta de endpoint sandbox → aprobar una solicitud → ver entrega firmada en el log → validar firma con el snippet de la doc → reenviar; POST de pago entrante idempotente
- [ ] 8.3 Verificar aislamiento multi-tenant y que sin endpoints el flujo actual queda intacto
