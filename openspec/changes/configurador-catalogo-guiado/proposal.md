# Configurador entendible: catálogo de variables y guía inline

## Why

En el editor de políticas del motor de decisión, la variable de scoring y sus valores se
capturan a texto libre (`salary_range`, `GT_15000`): un typo hace que la regla nunca
dispare, en silencio. Además nada explica qué se está configurando ni cómo decide el
motor — el admin del tenant (que ahora configura su propia matriz) no puede operar el
módulo con confianza.

*Grilling en esta conversación: 2 decisiones — (1) catálogo + guía inline, (2) probar la
configuración sin guardar.*

## What Changes

- **Catálogo de variables desde el backend** (`GET /v2/staff/decision-policies/catalog`):
  llaves conocidas por el motor con valores válidos y etiquetas en español (fuente de
  verdad = enums: SalaryRange, EmploymentType, EducationLevel, MaritalStatus + niveles
  de riesgo telefónico + créditos declarados; estado/ciudad quedan como valor libre).
- **Editor con selects**: la variable se elige de la lista; al elegirla se listan TODOS
  sus valores válidos con etiqueta y el admin solo captura puntos (sin duplicados de
  variable; los valores libres conservan captura manual).
- **Guía inline**: intro "¿Cómo decide el motor?" (4 pasos) al abrir el editor de
  producto + explicación corta por sección (bandas = cupo, cortes = gana el más alto,
  graduación, gate/cooldown en la política de tenant).
- **Probar sin guardar**: el dry-run acepta las reglas en edición (sin persistir) y el
  editor gana el botón "Probar esta configuración" que abre el probador pre-cargado.

## Capabilities

### New Capabilities

*(ninguna — todo es evolución del configurador existente)*

### Modified Capabilities

- `decision-configurator-admin`: el editor deja de aceptar llaves/valores de scoring a
  texto libre (catálogo con etiquetas; valores completos pre-listados); se agrega la
  guía inline obligatoria; el probador acepta reglas en edición sin persistir.

## Non-goals

- Wizard/asistente paso a paso y vista previa en vivo embebida (descartados en el
  grilling por costo/beneficio).
- Catálogo duro de nombres de banda (siguen siendo libres; los cortes ya los
  referencian por select y la validación detecta bandas inexistentes).
- Cambios al motor de evaluación (solo configurador/UX + dry-run).

## Impact

- **Backend**: método `catalog()` y extensión de `dryRun()` (acepta `rules` inline
  además de `policy_id`) en `DecisionPolicyController` + 1 ruta nueva. Sin migraciones.
- **Frontend**: `AdminDecisionEngine.vue` (sección de scoring con selects, textos guía,
  botón probar-desde-editor) + `decision-policy.staff.service.ts` (getCatalog, dryRun
  con rules). Sin cambios de tipos públicos.
- **Specs**: delta sobre `decision-configurator-admin`.
- Borrador parcial ya existente en el working tree (pausado al iniciar el grilling); se
  retoma como base.
