# integration-inbound-api

## ADDED Requirements

### Requirement: Confirmación de dispersión desde la cartera externa
Para tenants cuya cartera externa dispersa (no el STP interno),
`POST /api/webhooks/inbound/{endpoint}/disbursement` DEBE (MUST) verificar la firma HMAC y,
tras confirmarse la dispersión, transicionar el `Loan` de `PENDING_DISBURSEMENT` a
`ACTIVE` (fijando `disbursed_at`, `due_date` y `disbursement_reference`), marcar la
`Application` `SYNCED` con el `external_id` de la cartera, y emitir el evento saliente
`loan.disbursed` a los demás endpoints suscritos. DEBE ser idempotente por
`external_event_id`.

#### Scenario: Cartera confirma la dispersión
- **WHEN** la cartera confirma que dispersó un crédito en `PENDING_DISBURSEMENT` con un `external_event_id` nuevo
- **THEN** el `Loan` pasa a `ACTIVE`, la `Application` a `SYNCED` con su `external_id`, y se emite `loan.disbursed`

#### Scenario: Confirmación duplicada
- **WHEN** llega el mismo `external_event_id` de dispersión otra vez
- **THEN** se responde `duplicate` sin re-activar ni re-emitir

#### Scenario: Crédito en estado no válido
- **WHEN** llega una confirmación para un crédito que no está en `PENDING_DISBURSEMENT`
- **THEN** el backend responde error de validación sin efectos

### Requirement: Confirmación de pago desde la cartera externa
`POST /api/webhooks/inbound/{endpoint}/payments` DEBE (MUST) verificar la firma HMAC del
endpoint y su ventana de timestamp, y registrar el pago aplicado vía
`LoanService::recordPayment` (canal del proveedor y `provider_reference`), devolviendo el
saldo resultante. La operación DEBE ser idempotente por `external_event_id`: una segunda
llamada con el mismo id NO DEBE (MUST NOT) volver a cargar el pago.

#### Scenario: Pago aplicado en cartera
- **WHEN** la cartera confirma un pago de $400 sobre un crédito con un `external_event_id` nuevo
- **THEN** LendusFind registra el pago, recalcula el saldo y responde el saldo actualizado

#### Scenario: Reintento idempotente
- **WHEN** llega el mismo `external_event_id` otra vez
- **THEN** se responde el resultado previo marcado como `duplicate` sin doble cargo

#### Scenario: Firma inválida
- **WHEN** la firma HMAC no valida o el timestamp está fuera de ventana
- **THEN** el backend responde 401 sin efectos

### Requirement: Acuse de ingesta a cartera (cierra el handoff)
`POST /api/webhooks/inbound/{endpoint}/ingest-ack` DEBE (MUST) marcar la `Application`
como `SYNCED` vía `markSynced($external_id, $external_system, $sync_data)` tras verificar
firma e idempotencia. Solo aplica a solicitudes en estado que permita la transición a
`SYNCED` (desde `APPROVED`).

#### Scenario: Cartera acusa ingesta
- **WHEN** la cartera confirma que ingirió el crédito aprobado con su `external_id`
- **THEN** la solicitud pasa a `SYNCED` guardando `external_id` y `external_system`

#### Scenario: Acuse duplicado
- **WHEN** llega el mismo acuse dos veces
- **THEN** el segundo se responde como `duplicate` sin re-transicionar

### Requirement: Registro e idempotencia de eventos entrantes
Todo evento entrante DEBE (MUST) registrarse en `inbound_events`
(`external_event_id` único por tenant, `type`, `payload`, `status`, `result`) para dedupe
y auditoría. Un `external_event_id` repetido resuelve como `duplicate`.

#### Scenario: Trazabilidad entrante
- **WHEN** un auditor revisa qué confirmó la cartera
- **THEN** cada evento entrante figura con su id externo, tipo, payload y resultado
