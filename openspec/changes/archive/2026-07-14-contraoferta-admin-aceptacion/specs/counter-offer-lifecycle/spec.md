# counter-offer-lifecycle

## ADDED Requirements

### Requirement: Estado COUNTER_OFFERED en la máquina de estados
El modelo `Application` DEBE (MUST) incluir `COUNTER_OFFERED` como estado válido con
transiciones: entrada desde `IN_REVIEW` y `DOCS_PENDING`; salida hacia `APPROVED` y
`CANCELLED`. Enviar una contraoferta DEBE transicionar la solicitud a `COUNTER_OFFERED`
y registrar la transición en `application_status_histories`.

#### Scenario: Staff envía contraoferta desde IN_REVIEW
- **WHEN** el staff envía una contraoferta sobre una solicitud en `IN_REVIEW`
- **THEN** la solicitud pasa a `COUNTER_OFFERED` y se registra la transición con el staff como `changed_by`

#### Scenario: Estado no elegible
- **WHEN** el staff intenta enviar una contraoferta sobre una solicitud en `DRAFT`, `APPROVED` o un estado terminal
- **THEN** el backend responde error de validación y la solicitud no cambia de estado

#### Scenario: Reenvío sobreescribe sin transicionar
- **WHEN** el staff envía una nueva contraoferta sobre una solicitud ya en `COUNTER_OFFERED`
- **THEN** el JSONB `counter_offer` se sobreescribe con la nueva oferta (incluyendo nuevo `expires_at`) y el estado permanece `COUNTER_OFFERED` sin transición duplicada en el historial

### Requirement: Snapshot de pricing en el JSONB counter_offer
Al enviar una contraoferta, el backend DEBE (MUST) guardar en `counter_offer` el snapshot
completo: `amount`, `term_days` o `term_months` (exclusivos, según
`product.rules.term_in_days`), `interest_rate` y `opening_commission` copiados del
producto, `reason`, `offered_by`, `offered_at`, `expires_at`, y campos de respuesta
(`responded_at`, `accepted`) en null.

#### Scenario: Producto en días
- **WHEN** se envía contraoferta de $700 a 10 días sobre el producto de MoneyCapital
- **THEN** `counter_offer` contiene `term_days: 10`, `term_months: null`, `interest_rate: 36` y `opening_commission: 13` tomados de las rules del producto

#### Scenario: Producto en meses
- **WHEN** se envía contraoferta sobre un producto sin `term_in_days`
- **THEN** `counter_offer` contiene `term_months` y `term_days: null`

### Requirement: Aceptación transiciona a APPROVED con copia de términos
Cuando el solicitante acepta, el sistema DEBE (MUST) transicionar a `APPROVED`, copiar los
términos del snapshot a `approved_amount`, `approved_term_months` o
`approved_term_days`, y `approved_interest_rate`, marcar `counter_offer_accepted=true`
con `counter_offer_responded_at`, y emitir el evento `COUNTER_OFFER_ACCEPTED`.

#### Scenario: Aceptación exitosa
- **WHEN** el solicitante acepta una contraoferta vigente de $700 a 10 días
- **THEN** la solicitud queda en `APPROVED` con `approved_amount=700` y `approved_term_days=10`, y se emite `COUNTER_OFFER_ACCEPTED`

#### Scenario: Aceptación de oferta expirada
- **WHEN** el solicitante intenta aceptar después de `expires_at`
- **THEN** el backend responde 422 y la solicitud no cambia de estado

### Requirement: Rechazo transiciona a CANCELLED
Cuando el solicitante rechaza, el sistema DEBE (MUST) transicionar a `CANCELLED` con razón
explícita de rechazo del cliente, marcar `counter_offer_accepted=false` con
`counter_offer_responded_at`, y emitir el evento `COUNTER_OFFER_REJECTED`.

#### Scenario: Rechazo del cliente
- **WHEN** el solicitante rechaza la contraoferta
- **THEN** la solicitud queda en `CANCELLED` (terminal) y se emite `COUNTER_OFFER_REJECTED`

### Requirement: Auto-cancelación de ofertas expiradas
Un comando programado (`counter-offers:expire`, cada minuto, `withoutOverlapping` +
`onOneServer`) DEBE (MUST) transicionar a `CANCELLED` toda solicitud en `COUNTER_OFFERED` cuyo
`counter_offer.expires_at` ya pasó y que no tenga respuesta, notificando al solicitante
con razón "contraoferta expirada".

#### Scenario: Oferta expira sin respuesta
- **WHEN** corre el comando y existe una solicitud en `COUNTER_OFFERED` con `expires_at` en el pasado y `responded_at` null
- **THEN** la solicitud pasa a `CANCELLED` con la razón de expiración y se notifica al solicitante

#### Scenario: Oferta vigente no se toca
- **WHEN** corre el comando y la oferta aún no expira o ya fue respondida
- **THEN** la solicitud no se modifica

### Requirement: Contrato de respuesta con campo accepted
El endpoint `POST /v2/applicant/applications/{id}/counter-offer/respond` DEBE (MUST) aceptar el
payload `{ accepted: boolean }` y DEBE rechazar la petición si la solicitud no está en
`COUNTER_OFFERED`, si no pertenece al solicitante autenticado o su tenant, o si la
oferta ya fue respondida.

#### Scenario: Payload correcto
- **WHEN** el solicitante envía `{ accepted: true }` sobre su solicitud en `COUNTER_OFFERED`
- **THEN** la respuesta se procesa (aceptación) y responde `{ success: true }`

#### Scenario: Doble respuesta
- **WHEN** el solicitante intenta responder una contraoferta ya respondida
- **THEN** el backend responde error de validación sin efectos
