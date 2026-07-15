# Design — Configurador entendible

## Context

El editor de `AdminDecisionEngine.vue` captura variables de scoring y sus valores como
texto libre. El motor busca esas llaves en los insumos (`DecisionInputCollector`):
`salary_range`, `employment_type`, `education_level`, `marital_status`,
`online_loans_count`, `phone_risk_level`, `state`, `city`. Los 4 enums declarativos ya
tienen `toOptions()` con etiquetas en español.

## Goals / Non-Goals

**Goals:** eliminar el typo silencioso (catálogo), explicar el funcionamiento en el
lugar (guía inline), ciclo de ajuste inmediato (probar sin guardar).

**Non-Goals:** wizard completo, preview en vivo, catálogo de bandas, cambios al motor.

## Decisiones (grilling)

| # | Decisión | Elección | Descartado |
|---|----------|----------|------------|
| 1 | Profundidad | Catálogo + guía inline | Wizard completo; solo tooltips |
| 2 | Validación previa | Dry-run acepta reglas en edición + botón en el editor | Solo versiones guardadas; preview en vivo (duplicaría el motor en el front) |
| — | Bandas | Nombres libres (cortes ya los referencian por select; validación detecta huérfanos) | Catálogo duro |
| — | Fuente del catálogo | Backend (enums = fuente de verdad) | Hardcode en frontend (deriva) |

## Diseño

- **`GET /decision-policies/catalog`** (permiso `canManageProducts`): lista de
  `{key, label, description, values: [{value,label}] | null}`. `values: null` = captura
  libre (state/city).
- **Editor**: select de variable (excluye ya usadas); al elegir, `points` se pre-llena
  con todos los valores del catálogo en 0 (preservando puntos existentes al editar);
  filas de valores con etiqueta fija + input numérico; variables libres conservan filas
  valor+puntos editables. Intro "¿Cómo decide el motor?" + hint por sección.
- **Dry-run inline**: `POST /decision-policies/dry-run` acepta `policy_id` XOR
  (`rules` + `product_id`); con `rules` inline se valida coherencia primero y la
  corrida se registra igual (`trigger=DRY_RUN`, `policy_version=0`,
  `decision_policy_id` de la versión activa o null→ columna es NOT NULL: usar la
  activa del producto si existe; si no hay ninguna política previa, no se persiste la
  corrida — solo se devuelve el resultado). Botón "Probar esta configuración" en el
  editor abre el probador con las reglas en pantalla.

## Risks / Trade-offs

- [Catálogo desincronizado si aparece una variable nueva en el motor] → el catálogo
  vive junto al controller del configurador; agregar insumo al collector implica
  agregarlo al catálogo (nota en ambos archivos).
- [Políticas viejas con llaves fuera del catálogo] → el editor las muestra como
  variable "desconocida" con captura libre (no se pierden datos).
