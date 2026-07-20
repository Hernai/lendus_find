# bancos-transferencia-inmediata

## Purpose

Catálogo de bancos con flag de acreditación inmediata (validForTransfer) y filtro en el paso de cuenta bancaria.

# bancos-transferencia-inmediata

## Requirements

### Requirement: Flag de acreditación inmediata en el catálogo de bancos
El catálogo de bancos (`frontend/src/utils/banks.ts`, `MEXICAN_BANKS` con la interfaz
`BankOption`, hoy solo `code` + `name`) DEBE (MUST) exponer por cada institución un flag
`valid_for_transfer` que indique si acredita transferencias de inmediato vía SPEI/CEP.
El flag es la fuente única de verdad consumida por el paso de cuenta bancaria y por
cualquier derivación de banco (p. ej. `bankFromClabe`). La lista concreta de
instituciones válidas se investiga contra fuentes Banxico/SPEI y **la valida el usuario**
antes de liberar; este requisito define únicamente que el flag EXISTE y es la base de la
decisión, no qué bancos lo tienen.

#### Scenario: Toda entrada del catálogo declara el flag
- **WHEN** se lee cualquier opción de `MEXICAN_BANKS`
- **THEN** la opción incluye el campo booleano `valid_for_transfer` (no puede quedar indefinido)

#### Scenario: El flag distingue válidos de no válidos
- **WHEN** el catálogo se puebla con la lista aprobada por el usuario
- **THEN** las instituciones con acreditación inmediata SPEI/CEP quedan con `valid_for_transfer: true` y el resto con `valid_for_transfer: false`

### Requirement: El paso de cuenta bancaria deshabilita o avisa los bancos no válidos
El paso `bank_account` (`BankAccountStepRenderer.vue`), que hoy lista todos los bancos
en el sheet de selección sin distinción, DEBE (MUST) tratar de forma diferenciada a las
instituciones con `valid_for_transfer === false`: deshabilitar su selección en el picker
o, si se permite seleccionarlas, mostrar un aviso visible al aplicante. En cualquiera de
los dos modos, el paso NO DEBE (MUST NOT) quedar en estado válido (`update:valid` en
`true`) con un banco no apto para acreditación inmediata seleccionado, para no capturar
una cuenta donde el préstamo no podría dispersarse de inmediato.

#### Scenario: Banco no válido queda deshabilitado o marcado en el picker
- **WHEN** el aplicante abre el sheet de selección de banco
- **THEN** las instituciones con `valid_for_transfer === false` aparecen deshabilitadas o marcadas con un distintivo de "no disponible para recibir tu préstamo"

#### Scenario: Seleccionar un banco no válido no permite avanzar
- **WHEN** el aplicante logra seleccionar un banco con `valid_for_transfer === false`
- **THEN** el paso muestra un aviso explicativo y emite `update:valid` en `false`, impidiendo continuar hasta elegir un banco válido

#### Scenario: Banco válido avanza normalmente
- **WHEN** el aplicante selecciona un banco con `valid_for_transfer === true` y captura una cuenta con la longitud correcta (CLABE 18 / tarjeta 16)
- **THEN** el paso queda válido y no muestra el aviso de banco no apto

### Requirement: Default conservador para bancos sin acreditación confirmada
Cualquier institución cuyo estatus de acreditación inmediata no esté confirmado, y en
particular la opción comodín "Otro" (`code: 'OTHR'`), DEBE (MUST) tratarse como NO válida
para transferencia inmediata (`valid_for_transfer: false`) por defecto. El sistema NO
DEBE (MUST NOT) asumir que un banco acredita de inmediato cuando el flag no lo afirma
explícitamente, para evitar prometer dispersión inmediata a una cuenta que no la soporta.

#### Scenario: La opción "Otro" nunca cuenta como válida
- **WHEN** el aplicante selecciona la opción "Otro" (`OTHR`)
- **THEN** se trata como banco no válido para transferencia inmediata y aplica el mismo aviso/bloqueo que a las instituciones no aptas

#### Scenario: Ausencia de confirmación se resuelve como no válido
- **WHEN** una institución del catálogo no está en la lista de válidos aprobada por el usuario
- **THEN** su `valid_for_transfer` es `false` y el paso la trata como no apta
