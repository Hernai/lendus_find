# counter-offer-lifecycle

## MODIFIED Requirements

### Requirement: Snapshot de pricing en el JSONB counter_offer
Al enviar una contraoferta, el backend DEBE (MUST) guardar en `counter_offer` el snapshot
completo: `amount`, `term_days` o `term_months` (exclusivos, según
`product.rules.term_in_days`), `interest_rate` y `opening_commission` copiados del
producto, `reason`, `offered_by`, `offered_at`, `expires_at`, y campos de respuesta
(`responded_at`, `accepted`) en null. El snapshot DEBE además soportar **modo rango**
(retrocompatible): cuando incluye `min_amount`/`max_amount` (y
`min_term_days`/`max_term_days`), la oferta es ajustable dentro de ese rango y `amount`
representa el valor pre-seleccionado; sin esos campos, la oferta es de monto fijo como
hasta ahora. El snapshot DEBE registrar `source` (`ENGINE` | `STAFF`) y un campo
`acceptance` (null hasta responder) para la evidencia de aceptación.

#### Scenario: Producto en días
- **WHEN** se envía contraoferta de $700 a 10 días sobre el producto de MoneyCapital
- **THEN** `counter_offer` contiene `term_days: 10`, `term_months: null`, `interest_rate: 36` y `opening_commission: 13` tomados de las rules del producto

#### Scenario: Producto en meses
- **WHEN** se envía contraoferta sobre un producto sin `term_in_days`
- **THEN** `counter_offer` contiene `term_months` y `term_days: null`

#### Scenario: Oferta de rango generada por el motor
- **WHEN** el motor crea una oferta con cupo $400 para una solicitud de $900
- **THEN** el snapshot contiene `min_amount: 300`, `max_amount: 400`, `amount: 400` (pre-selección), `min_term_days: 7`, `max_term_days: 7` y `source: "ENGINE"`

#### Scenario: Oferta fija sigue funcionando
- **WHEN** el staff crea una contraoferta manual sin rango
- **THEN** el snapshot no incluye `max_amount` y la oferta se comporta como monto fijo

### Requirement: Aceptación transiciona a APPROVED con copia de términos
Cuando el solicitante acepta, el sistema DEBE (MUST) transicionar a `APPROVED`, copiar los
términos aceptados a `approved_amount`, `approved_term_months` o `approved_term_days`, y
`approved_interest_rate`, marcar `counter_offer_accepted=true` con
`counter_offer_responded_at`, y emitir el evento `COUNTER_OFFER_ACCEPTED`. En ofertas de
**rango**, los términos aceptados son el monto/plazo elegidos por el solicitante dentro
del rango (con el pricing recalculado para esa selección); en ofertas fijas, los del
snapshot. La aceptación DEBE registrar evidencia en `counter_offer.acceptance`:
`chosen_amount`, `chosen_term_days`/`chosen_term_months`, `ip`, `user_agent`,
`accepted_at`.

#### Scenario: Aceptación exitosa
- **WHEN** el solicitante acepta una contraoferta vigente de $700 a 10 días
- **THEN** la solicitud queda en `APPROVED` con `approved_amount=700` y `approved_term_days=10`, y se emite `COUNTER_OFFER_ACCEPTED`

#### Scenario: Aceptación de oferta expirada
- **WHEN** el solicitante intenta aceptar después de `expires_at`
- **THEN** el backend responde 422 y la solicitud no cambia de estado

#### Scenario: Aceptación dentro del rango
- **WHEN** el solicitante elige $350 en una oferta de rango $300-$400 y acepta
- **THEN** la solicitud queda en `APPROVED` con `approved_amount=350` y la evidencia (`ip`, `user_agent`, `accepted_at`) queda en el snapshot

#### Scenario: Monto fuera del rango
- **WHEN** el solicitante envía $450 sobre una oferta de rango $300-$400
- **THEN** el backend responde error de validación sin efectos

### Requirement: Contrato de respuesta con campo accepted
El endpoint `POST /v2/applicant/applications/{id}/counter-offer/respond` DEBE (MUST) aceptar el
payload `{ accepted: boolean, amount?: number, term_days?: number }` y DEBE rechazar la
petición si la solicitud no está en `COUNTER_OFFERED`, si no pertenece al solicitante
autenticado o su tenant, o si la oferta ya fue respondida. En ofertas de rango,
`amount`/`term_days` se validan contra el rango del snapshot; si se omiten, se usa el
valor pre-seleccionado (`amount` del snapshot). En ofertas fijas, `amount`/`term_days`
se ignoran.

#### Scenario: Payload correcto
- **WHEN** el solicitante envía `{ accepted: true }` sobre su solicitud en `COUNTER_OFFERED`
- **THEN** la respuesta se procesa (aceptación) y responde `{ success: true }`

#### Scenario: Doble respuesta
- **WHEN** el solicitante intenta responder una contraoferta ya respondida
- **THEN** el backend responde error de validación sin efectos

#### Scenario: Cliente viejo sin monto en oferta de rango
- **WHEN** un cliente envía solo `{ accepted: true }` sobre una oferta de rango
- **THEN** la aceptación usa el monto pre-seleccionado del snapshot

## ADDED Requirements

### Requirement: Ofertas generadas por el sistema con vigencia en horas
El motor de decisión DEBE (MUST) poder crear ofertas sin intervención de staff
(`source: ENGINE`, `offered_by: "system"`) con vigencia expresada en horas
(`offer.validity_hours` de la política, default 72), conviviendo con las ofertas
manuales de staff cuya vigencia sigue en minutos. La expiración automática y el reenvío
staff operan igual para ambas.

#### Scenario: Oferta del motor con 72 horas
- **WHEN** el motor crea una oferta a las 10:00 del lunes con vigencia 72h
- **THEN** `expires_at` es las 10:00 del jueves y el comando de expiración la cancela si no hay respuesta

#### Scenario: Staff reenvía sobre oferta del motor
- **WHEN** un supervisor reenvía una contraoferta manual sobre una solicitud con oferta del motor vigente
- **THEN** el snapshot se sobreescribe con la oferta manual (`source: STAFF`) sin transición duplicada

### Requirement: Recordatorio antes del vencimiento
El barrido programado DEBE (MUST) notificar al solicitante una sola vez cuando su oferta
vigente cruce el umbral `offer.reminder_hours_before` (default 24h antes de
`expires_at`), emitiendo el evento `COUNTER_OFFER_EXPIRING` y marcando el snapshot para
no repetir el aviso.

#### Scenario: Recordatorio único
- **WHEN** una oferta de 72h llega a faltarle 24h y el barrido corre varias veces
- **THEN** el solicitante recibe exactamente una notificación de vencimiento próximo
