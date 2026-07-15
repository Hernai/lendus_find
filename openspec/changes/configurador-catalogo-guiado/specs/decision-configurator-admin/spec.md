# decision-configurator-admin

## MODIFIED Requirements

### Requirement: Editor tipado por secciones con validación
El editor DEBE (MUST) ser tipado por secciones — filtro telefónico, cooldown (política
de tenant); scoring y bandas, oferta/vigencia, graduación por niveles (política de
producto) — sin edición de JSON libre. DEBE validar coherencia antes de guardar: cortes
de banda crecientes, rangos de banda sin traslapes y dentro de los límites del producto,
niveles de graduación crecientes, umbrales del gate `flag_from < block_from`, y
vigencia/cooldown positivos. Guardar crea una versión nueva en borrador.

Las variables de scoring DEBEN elegirse de un **catálogo** servido por el backend
(`GET /v2/staff/decision-policies/catalog`, fuente de verdad: los enums del motor) con
etiquetas en español: al elegir una variable se listan TODOS sus valores válidos y el
admin solo captura puntos — nunca teclea llaves ni valores. Las variables de valor
libre (estado/ciudad) conservan captura manual del valor. No se permite la misma
variable dos veces. El editor DEBE incluir guía inline: introducción "¿Cómo decide el
motor?" y una explicación corta por sección.

#### Scenario: Bandas incoherentes
- **WHEN** el staff captura una banda $500-$400 o un corte menor al anterior
- **THEN** el formulario impide guardar señalando el error

#### Scenario: Guardado como borrador
- **WHEN** el staff guarda cambios válidos sobre la política activa
- **THEN** se crea una versión nueva en borrador y la activa no cambia hasta activarla explícitamente

#### Scenario: Variable desde catálogo sin typos
- **WHEN** el admin agrega una variable de scoring y elige "Rango salarial mensual"
- **THEN** se listan todos los rangos salariales con su etiqueta ("Menos de $3,000"…) en 0 puntos y solo captura los puntos de cada uno

#### Scenario: Variable duplicada no disponible
- **WHEN** el admin ya usa "Rango salarial mensual" en otra fila
- **THEN** esa variable no aparece disponible en el select de una fila nueva

#### Scenario: Política vieja con llave fuera del catálogo
- **WHEN** el editor abre una política cuya variable no existe en el catálogo
- **THEN** la muestra con captura libre sin perder los puntos configurados

### Requirement: Probador de perfiles (dry-run)
El módulo DEBE (MUST) incluir un probador: el staff captura un perfil hipotético
(variables declarativas, nivel de score telefónico, geolocalización, monto/plazo
solicitados, historial de renovación) y `POST /v2/staff/decision-policies/dry-run`
devuelve la decisión completa (outcome, puntaje, banda, rango, reglas disparadas) **sin
afectar ninguna solicitud real**. El endpoint DEBE aceptar `policy_id` (versión
guardada — activa o borrador) **o** `rules` + `product_id` (configuración en edición,
sin persistir), validando la coherencia de las reglas inline antes de evaluar. El
editor DEBE ofrecer "Probar esta configuración" que abre el probador pre-cargado con
las reglas en pantalla.

#### Scenario: Probar la matriz antes de activar
- **WHEN** el staff prueba un perfil "salario 8-15k, score tel. bajo, Culiacán, pide $900" contra el borrador v3
- **THEN** ve la decisión (ej. `OFFER` banda BASE, rango $300-$400) con el desglose de puntos y reglas, y ninguna solicitud se crea o modifica

#### Scenario: Probar borradores
- **WHEN** el staff selecciona una versión en borrador para el dry-run
- **THEN** la evaluación usa esa versión sin necesidad de activarla

#### Scenario: Probar sin guardar
- **WHEN** el admin edita puntos en el editor y pulsa "Probar esta configuración"
- **THEN** el probador evalúa las reglas en pantalla (sin crear versión) y muestra la decisión

#### Scenario: Reglas inline incoherentes
- **WHEN** el dry-run recibe reglas en edición con bandas traslapadas
- **THEN** responde error de validación con los mismos mensajes del guardado
