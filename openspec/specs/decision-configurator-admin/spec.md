# decision-configurator-admin

## Requirements

### Requirement: Módulo admin "Motor de decisión" con permisos por rol
El admin DEBE (MUST) incluir un módulo dedicado "Motor de decisión" (integrado al
sistema de módulos con visibilidad por rol / `TenantRoleModuleOverride`) que muestre la
política de tenant y la lista de políticas por producto con su versión activa y modo.
Editar, activar y cambiar de modo requiere `canConfigureTenant` (SUPER_ADMIN); ADMIN
consulta en solo lectura; ANALYST/SUPERVISOR no ven el módulo (su vista es el panel de
evaluación en el detalle).

#### Scenario: Super admin edita
- **WHEN** un SUPER_ADMIN entra al módulo
- **THEN** puede editar borradores, activar versiones y cambiar el modo

#### Scenario: Admin consulta
- **WHEN** un ADMIN entra al módulo
- **THEN** ve las políticas y su historial sin acciones de escritura

#### Scenario: Analista no ve el módulo
- **WHEN** un ANALYST navega el admin
- **THEN** el módulo "Motor de decisión" no aparece en su menú

### Requirement: Editor tipado por secciones con validación
El editor DEBE (MUST) ser tipado por secciones — filtro telefónico, cooldown (política
de tenant); scoring y bandas, oferta/vigencia, graduación por niveles (política de
producto) — sin edición de JSON libre. DEBE validar coherencia antes de guardar: cortes
de banda crecientes, rangos de banda sin traslapes y dentro de los límites del producto,
niveles de graduación crecientes, umbrales del gate `flag_from < block_from`, y
vigencia/cooldown positivos. Guardar crea una versión nueva en borrador.

#### Scenario: Bandas incoherentes
- **WHEN** el staff captura una banda $500-$400 o un corte menor al anterior
- **THEN** el formulario impide guardar señalando el error

#### Scenario: Guardado como borrador
- **WHEN** el staff guarda cambios válidos sobre la política activa
- **THEN** se crea una versión nueva en borrador y la activa no cambia hasta activarla explícitamente

### Requirement: Historial de versiones con activación y rollback
El módulo DEBE (MUST) mostrar el historial de versiones (número, notas, quién/cuándo
activó) con acciones de activar (incluye rollback a versiones previas) y cambio de modo
`off/shadow/active` con confirmación explícita al pasar a `ACTIVE`.

#### Scenario: Activar con confirmación
- **WHEN** el SUPER_ADMIN cambia el modo de `SHADOW` a `ACTIVE`
- **THEN** se le pide confirmación explicando que el motor comenzará a decidir solicitudes reales

### Requirement: Probador de perfiles (dry-run)
El módulo DEBE (MUST) incluir un probador: el staff captura un perfil hipotético
(variables declarativas, nivel de score telefónico, geolocalización, monto/plazo
solicitados, historial de renovación) contra una versión de política — activa o borrador
— y `POST /v2/staff/decision-policies/dry-run` devuelve la decisión completa (outcome,
puntaje, banda, rango, reglas disparadas) **sin afectar ninguna solicitud real**. Las
corridas se registran con `trigger=DRY_RUN` y `executed=false`.

#### Scenario: Probar la matriz antes de activar
- **WHEN** el staff prueba un perfil "salario 8-15k, score tel. bajo, Culiacán, pide $900" contra el borrador v3
- **THEN** ve la decisión (ej. `OFFER` banda BASE, rango $300-$400) con el desglose de puntos y reglas, y ninguna solicitud se crea o modifica

#### Scenario: Probar borradores
- **WHEN** el staff selecciona una versión en borrador para el dry-run
- **THEN** la evaluación usa esa versión sin necesidad de activarla
