# decision-review-panel

## ADDED Requirements

### Requirement: Panel de evaluación del motor en el detalle staff
`GET /v2/staff/applications/{id}/decision` DEBE (MUST) devolver la última evaluación del
motor para la solicitud (outcome, score, banda, rango sugerido, reglas disparadas con
detalle legible, insumos faltantes, modo y versión de política). El detalle admin DEBE
mostrar esta tarjeta a partir de ANALYST; en modo `SHADOW` la tarjeta indica claramente
"el motor habría decidido…".

#### Scenario: Analista revisa una solicitud ruteada por el motor
- **WHEN** un analista abre una solicitud que el motor mandó a `IN_REVIEW`
- **THEN** ve el puntaje, la banda sugerida, las reglas disparadas y qué insumo faltó o qué alerta la detuvo

#### Scenario: Solicitud sin evaluación
- **WHEN** el staff abre una solicitud creada sin política activa
- **THEN** la tarjeta del motor no se muestra

#### Scenario: Evaluación en sombra
- **WHEN** el staff abre una solicitud evaluada en modo `SHADOW`
- **THEN** la tarjeta muestra la decisión hipotética marcada como no ejecutada

### Requirement: Atajo "aplicar oferta sugerida"
`POST /v2/staff/applications/{id}/apply-suggested-offer` DEBE (MUST) crear en un clic la
oferta de rango que el motor calculó (misma vigencia y rango de la evaluación), con
permiso `canApproveRejectApplications`, solo en estados elegibles para contraoferta
(`IN_REVIEW`, `DOCS_PENDING`). La oferta resultante registra `source: ENGINE` y al staff
como quien la aplicó.

#### Scenario: Aplicar la sugerencia
- **WHEN** un supervisor pulsa "Aplicar oferta sugerida" sobre una solicitud en `IN_REVIEW` con sugerencia $300-$400 × 7 días
- **THEN** la solicitud pasa a `COUNTER_OFFERED` con ese rango sin capturar campos manualmente

#### Scenario: Sin evaluación no hay atajo
- **WHEN** la solicitud no tiene evaluación del motor con salida `OFFER` o `REVIEW` con banda sugerida
- **THEN** el atajo no se muestra y el endpoint responde error de validación

#### Scenario: Sin permiso
- **WHEN** un analista sin `canApproveRejectApplications` invoca el atajo
- **THEN** el backend responde 403
