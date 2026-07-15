# decision-engine

## Requirements

### Requirement: Evaluación asíncrona al enviar la solicitud
Cuando una solicitud pasa a `SUBMITTED` y el producto tiene política activa en modo
`SHADOW` o `ACTIVE`, el sistema DEBE (MUST) despachar `DecideApplicationJob` a la cola.
El submit NO DEBE bloquearse por la evaluación; el cliente ve "en proceso" y recibe el
resultado vía WebSocket/notificación.

#### Scenario: Submit con política activa
- **WHEN** un solicitante de MoneyCapital envía su solicitud con política en `ACTIVE`
- **THEN** el submit responde de inmediato y el job de decisión queda encolado

#### Scenario: Submit sin política
- **WHEN** el producto no tiene política activa o está en `OFF`
- **THEN** no se despacha ningún job y la solicitud espera revisión manual como hoy

### Requirement: Recolección de insumos con reintentos y timeout
El job DEBE (MUST) recolectar los insumos de la evaluación: riesgo telefónico
(`risk_assessments`), estado KYC de la persona, validación de CLABE
(`nubarium_async_validations`), datos declarativos, geolocalización y flags del gate
telefónico. Si un insumo requerido aún no está disponible, el job DEBE reintentar con
backoff hasta `review.input_timeout_minutes`; agotado el timeout, la salida DEBE ser
`REVIEW` con motivo `inputs_incomplete`. Un insumo faltante NUNCA DEBE producir rechazo.

#### Scenario: CLABE pendiente de webhook
- **WHEN** el job corre y la validación de CLABE sigue `pending`
- **THEN** el job se re-encola con backoff sin decidir

#### Scenario: Timeout de insumos
- **WHEN** pasan más de `input_timeout_minutes` sin completarse los insumos
- **THEN** la solicitud pasa a `IN_REVIEW` y la evaluación registra `inputs_incomplete`

### Requirement: Scoring de bandas para primer crédito
El motor DEBE (MUST) calcular un puntaje sumando los puntos configurados por variable
(`scoring.variables`) y mapearlo a una banda con `scoring.band_cutoffs`. La banda
determina el cupo: el rango de oferta es `[min_amount del producto → max_amount de la
banda]` y el plazo del primer crédito es fijo (`first_credit.term_days`, piloto: 7
días). Los porcentajes `target_share_pct` de las bandas son metas de monitoreo y NO
DEBEN (MUST NOT) usarse para asignar.

#### Scenario: Perfil con puntaje intermedio
- **WHEN** un perfil suma 35 puntos con cortes BASE≥0, INTERMEDIA≥30, CONTROLADA≥50
- **THEN** la banda es INTERMEDIA y el cupo autorizado es $600 con plazo de 7 días

#### Scenario: Variables declarativas no deciden solas
- **WHEN** un perfil tiene puntaje alto solo por variables declarativas pero una validación fuerte falló
- **THEN** aplican las reglas duras (rechazo/revisión) antes que la banda calculada

### Requirement: Salidas del motor en modo ACTIVE
Con insumos completos y política `ACTIVE`, el motor DEBE (MUST) ejecutar exactamente una
salida usando las acciones existentes del modelo:

- `OFFER`: transicionar `SUBMITTED → IN_REVIEW → COUNTER_OFFERED` (historial en ambas)
  creando la oferta de rango (`source: ENGINE`, vigencia `offer.validity_hours`). Si el
  monto solicitado cabe en el cupo queda pre-seleccionado; si lo excede, la
  pre-selección es el cupo máximo (contraoferta automática).
- `REVIEW`: transicionar a `IN_REVIEW` (bandeja de no-asignadas existente).
- `REJECT`: `reject()` con motivo del motor; inicia el cooldown.

#### Scenario: Solicitado dentro del cupo
- **WHEN** el cliente pidió $350 y el motor autoriza cupo $400
- **THEN** la solicitud queda en `COUNTER_OFFERED` con rango $300-$400 y $350 pre-seleccionado

#### Scenario: Contraoferta automática por exceso
- **WHEN** el cliente pidió $900 y el motor autoriza cupo $400
- **THEN** la oferta se crea con rango $300-$400 pre-seleccionando $400

#### Scenario: Alertas parciales van a revisión
- **WHEN** la evaluación dispara una regla de revisión (score telefónico FLAG, inconsistencia no crítica)
- **THEN** la solicitud pasa a `IN_REVIEW` sin oferta ni rechazo

#### Scenario: Regla dura de rechazo
- **WHEN** una validación fuerte falla (`kyc_failed`, identidad no válida, fuera de cobertura)
- **THEN** la solicitud pasa a `REJECTED` con el motivo del motor y se notifica al cliente

### Requirement: Modo SHADOW registra sin actuar
En modo `SHADOW` el motor DEBE (MUST) completar la evaluación y persistirla con
`executed=false`, sin cambiar el estado de la solicitud, sin crear ofertas y sin
notificar al cliente. El flujo manual del staff continúa intacto.

#### Scenario: Evaluación sombra
- **WHEN** una solicitud se envía con política en `SHADOW` y el motor concluye `OFFER $400`
- **THEN** la solicitud permanece en `SUBMITTED` para revisión manual y la evaluación queda registrada como "habría ofertado $300-$400"

### Requirement: Auditoría de cada evaluación
Toda corrida del motor (submit, renovación, gate telefónico, probador) DEBE (MUST)
persistir una fila en `application_decisions` con: política y versión aplicadas,
`trigger`, `mode`, `inputs` congelados, `rule_hits` (reglas disparadas con detalle),
`score`, `band`, `outcome`, `outcome_detail` (rango calculado, motivos) y `executed`.

#### Scenario: Trazabilidad de una decisión
- **WHEN** un auditor consulta por qué una solicitud recibió cupo $400
- **THEN** la fila de `application_decisions` muestra la versión de política, los inputs, el puntaje, la banda y las reglas disparadas
