# counter-offer-admin

## MODIFIED Requirements

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
