# counter-offer-admin

## Requirements

### Requirement: Modal de contraoferta adaptivo por producto
El `CounterOfferModal` del detalle de solicitud DEBE (MUST) adaptar sus campos según el
producto de la solicitud: si `product.rules.term_in_days` es true, captura monto y
"Plazo (días)" validado contra `min_term_days`/`max_term_days`, sin campos de tasa ni
frecuencia (se toman fijos del producto); en caso contrario mantiene la captura actual
en meses con tasa editable. En ambos casos DEBE capturar la vigencia de la oferta con
default 30 minutos y un motivo.

#### Scenario: Producto en días (MoneyCapital)
- **WHEN** el staff abre el modal sobre una solicitud del producto "Préstamo Sin Buró"
- **THEN** el modal muestra monto y plazo en días (1–30), sin campo de tasa, con vigencia default de 30 minutos

#### Scenario: Producto en meses
- **WHEN** el staff abre el modal sobre una solicitud de un producto sin `term_in_days`
- **THEN** el modal muestra la captura actual en meses más el campo de vigencia

#### Scenario: Validación de rangos
- **WHEN** el staff captura un plazo de 45 días en un producto con `max_term_days: 30`
- **THEN** el formulario impide el envío y el backend, de recibirlo, responde error de validación

### Requirement: Endpoint staff valida días o meses según producto
`POST /v2/staff/applications/{id}/counter-offer` DEBE (MUST) aceptar `term_days` o
`term_months` (exactamente uno, coherente con `product.rules.term_in_days`), `amount`
dentro de los rangos del producto, `reason` y vigencia en minutos; y DEBE requerir el
permiso de aprobar/rechazar (`canApproveRejectApplications`).

#### Scenario: Días en producto de días
- **WHEN** el staff envía `{ amount: 700, term_days: 10, reason: "...", expires_in_minutes: 30 }` sobre una solicitud MoneyCapital
- **THEN** la contraoferta se crea y la solicitud pasa a `COUNTER_OFFERED`

#### Scenario: Unidad incorrecta
- **WHEN** el staff envía `term_months` para un producto con `term_in_days: true`
- **THEN** el backend responde error de validación

#### Scenario: Sin permiso
- **WHEN** un analista sin `canApproveRejectApplications` intenta enviar contraoferta
- **THEN** el backend responde 403

### Requirement: Botón de contraoferta visible en estados elegibles
El detalle de solicitud del admin DEBE (MUST) mostrar la acción "Contraoferta" en `IN_REVIEW`,
`DOCS_PENDING` y `COUNTER_OFFERED` (reenvío), y DEBE mostrar la contraoferta vigente
cuando exista, incluyendo: monto (o **rango** `min-max` cuando la oferta es ajustable),
plazo, vigencia restante, estado de respuesta y **origen** de la oferta ("Motor de
decisión" cuando `source: ENGINE`; el nombre del staff cuando es manual). El modal de
contraoferta manual permanece de monto fijo.

#### Scenario: Reenvío desde COUNTER_OFFERED
- **WHEN** el staff abre una solicitud en `COUNTER_OFFERED` sin respuesta
- **THEN** ve la oferta vigente y puede reenviar una nueva que la sobreescribe

#### Scenario: Estado no elegible
- **WHEN** el staff abre una solicitud en `APPROVED` o estado terminal
- **THEN** la acción "Contraoferta" no se muestra

#### Scenario: Oferta del motor identificada
- **WHEN** el staff abre una solicitud con oferta vigente creada por el motor con rango $300-$400
- **THEN** la tarjeta muestra "Rango $300-$400 × 7 días · Origen: Motor de decisión" y la vigencia restante
