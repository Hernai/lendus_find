# motor-housing-ubicacion

## ADDED Requirements

### Requirement: Recolección de `housing_type` como insumo del motor
El `DecisionInputCollector` DEBE (MUST) incluir `housing_type` dentro del arreglo
`inputs.variables` que recolecta para el motor de decisión, tomándolo del domicilio actual
del solicitante (`person.currentHomeAddress`, ya cargado para `state`/`city`). El valor DEBE
(MUST) normalizarse al enum canónico `HousingType` (`OWNED_PAID`, `OWNED_MORTGAGE`, `RENTED`,
`FAMILY`, `BORROWED`, `OTHER`) — soportando valores legacy vía `HousingType::normalize()` —
para que la llave del points map coincida. Cuando no exista domicilio o `housing_type` sea
nulo, la variable DEBE (MUST) quedar en `null` (el motor no le suma puntos). Hoy el
collector recolecta `state`/`city` pero NO `housing_type`.

#### Scenario: Domicilio con tipo de vivienda canónico
- **WHEN** una solicitud tiene domicilio actual con `housing_type = OWNED_PAID`
- **THEN** `inputs.variables.housing_type` recolectado es `OWNED_PAID`

#### Scenario: Valor legacy se normaliza al canónico
- **WHEN** el domicilio guarda un valor legacy en español (p. ej. `PROPIA_PAGADA`)
- **THEN** `inputs.variables.housing_type` recolectado es el canónico `OWNED_PAID`

#### Scenario: Sin domicilio o sin tipo de vivienda
- **WHEN** la solicitud no tiene domicilio o el `housing_type` es nulo
- **THEN** `inputs.variables.housing_type` es `null` y el motor no suma puntos por esa variable

### Requirement: Variables de scoring `housing_type` y `state` con points maps en 0
La política sembrada por `MoneyCapitalSeeder` DEBE (MUST) declarar en `rules.scoring.variables`
dos entradas nuevas —`key: 'housing_type'` y `key: 'state'`— además de las cinco ya
existentes (`salary_range`, `employment_type`, `education_level`, `phone_risk_level`,
`online_loans_count`). Sus `points` maps DEBEN (MUST) arrancar en 0 (matriz sin calibrar);
la matriz concreta NO se define aquí y queda editable desde el configurador del admin, sin
requerir cambio de código ni re-seed. La entrada `state` DEBE (MUST) mapear las claves de
estado que el motor ya recibe en `inputs.variables.state`.

#### Scenario: Siembra incluye las dos variables nuevas
- **WHEN** corre `MoneyCapitalSeeder` y crea la política del producto piloto
- **THEN** `rules.scoring.variables` contiene una entrada con `key = housing_type` y otra con `key = state`

#### Scenario: Puntos arrancan neutros
- **WHEN** la política recién sembrada evalúa una solicitud
- **THEN** `housing_type` y `state` aportan 0 puntos al score total (los maps están en 0) y no alteran la banda ni la decisión respecto al comportamiento previo

#### Scenario: El admin calibra los puntos sin tocar código
- **WHEN** el staff edita en el configurador los `points` de `housing_type`/`state` a valores distintos de 0 y activa la nueva versión
- **THEN** el motor suma esos puntos en las evaluaciones siguientes sin desplegar código

### Requirement: Scoring genérico de las variables nuevas (config-driven)
El `DecisionEngineService` DEBE (MUST) sumar los puntos de `housing_type` y `state` de forma
genérica recorriendo `rules.scoring.variables` (misma ruta que las variables existentes),
buscando el valor recolectado en `inputs.variables[key]` contra el points map, sin lógica
especial por variable. Un valor recolectado ausente del points map (o `null`/vacío) DEBE
(MUST) aportar 0 puntos. Cada variable evaluada DEBE (MUST) aparecer en el detalle del
scoring con `key`, `value` y `points` para trazabilidad.

#### Scenario: Suma según el points map configurado
- **WHEN** `scoring.variables` incluye `housing_type` con `points` `{ "OWNED_PAID": 10 }` y la solicitud aporta `housing_type = OWNED_PAID`
- **THEN** el score total incluye +10 y el detalle registra `{ key: housing_type, value: OWNED_PAID, points: 10 }`

#### Scenario: Valor fuera del points map aporta 0
- **WHEN** la solicitud aporta un `state` cuya clave no está en el points map de `state`
- **THEN** esa variable aporta 0 puntos y el detalle la registra con `points: 0`
