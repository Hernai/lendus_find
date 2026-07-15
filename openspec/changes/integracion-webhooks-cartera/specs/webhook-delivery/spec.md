# webhook-delivery

## ADDED Requirements

### Requirement: Emisión de eventos del ciclo de vida
Cuando ocurre un evento del catálogo v1 (`application.approved`, `application.rejected`,
`loan.disbursed`, `loan.completed`, `payment.received`), el sistema DEBE (MUST) generar un
`event_id` (UUID) único y crear una entrega por cada endpoint activo suscrito, con el
payload del sobre versionado. La emisión DEBE ser best-effort respecto al negocio: un
fallo al encolar se registra pero NUNCA revierte la aprobación, dispersión o pago.

#### Scenario: Crédito autorizado emite a los suscritos
- **WHEN** una solicitud pasa a `APPROVED` y dos endpoints activos suscriben `application.approved`
- **THEN** se crean dos entregas `PENDING` con el mismo `event_id` y el payload completo

#### Scenario: Sin endpoints no rompe el flujo
- **WHEN** ocurre un evento y no hay endpoints suscritos
- **THEN** la operación de negocio se completa normalmente sin crear entregas

#### Scenario: Fallo de emisión no revierte el negocio
- **WHEN** falla la creación de la entrega al aprobar
- **THEN** la solicitud queda `APPROVED` y el fallo se registra en log

### Requirement: Sobre versionado con payload completo
Cada entrega DEBE (MUST) enviar un sobre `{ id, event, version, occurred_at, tenant, data }`
donde `data` es el recurso estandarizado completo (application o loan) según un esquema
estable documentado. El sobre DEBE incluir `version` para permitir evolución; dentro de
una versión los cambios solo agregan campos.

#### Scenario: Payload de crédito autorizado
- **WHEN** se entrega `application.approved`
- **THEN** el sobre incluye `event="application.approved"`, `version`, `occurred_at`, `tenant` y `data` con folio, persona/KYC, montos y condiciones aprobadas y la **cuenta de dispersión con la CLABE completa** (la cartera externa la necesita para dispersar)

### Requirement: Firma HMAC-SHA256 con anti-replay
Cada request DEBE (MUST) incluir `X-LendusFind-Signature: sha256=<hex>` (HMAC-SHA256 de
`"{timestamp}.{body}"` con el secreto del endpoint), `X-LendusFind-Timestamp`,
`X-LendusFind-Event` y `X-LendusFind-Delivery`. La firma DEBE calcularse sobre el cuerpo
exacto enviado.

#### Scenario: Firma verificable
- **WHEN** el receptor recomputa el HMAC con su secreto sobre `timestamp.body`
- **THEN** coincide con `X-LendusFind-Signature` y el timestamp está dentro de la ventana

### Requirement: Reintentos con backoff y log de entregas
Una respuesta 2xx marca la entrega `SENT`. Un fallo (no-2xx o timeout) DEBE (MUST)
incrementar `attempts`, fijar `next_retry_at` con backoff exponencial y estado `RETRYING`;
agotados los reintentos (default 5) pasa a `FAILED` y emite la notificación interna
`WEBHOOK_FAILED`. Un comando programado re-encola las `RETRYING` vencidas. Cada intento
registra `response_code`, `response_body`, `error_message` en el log.

#### Scenario: Reintento tras fallo
- **WHEN** el endpoint responde 500 en el primer intento
- **THEN** la entrega queda `RETRYING` con `next_retry_at` futuro y se reintenta al vencer

#### Scenario: Agotar reintentos
- **WHEN** se agotan los 5 intentos sin éxito
- **THEN** la entrega queda `FAILED` y se notifica al staff con `WEBHOOK_FAILED`

#### Scenario: Reenvío manual
- **WHEN** el staff reenvía una entrega `FAILED` desde el panel
- **THEN** vuelve a `PENDING` y se intenta de nuevo con la firma vigente
