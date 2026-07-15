# decision-policy

## ADDED Requirements

### Requirement: Políticas de decisión versionadas por tenant y producto
El sistema DEBE (MUST) persistir políticas de decisión en la tabla `decision_policies`
(UUID, tenant scoping) con alcance dual: `product_id NULL` = política de tenant (filtro
telefónico, cooldown); `product_id` presente = política de producto (scoring, bandas,
oferta, graduación). Cada edición DEBE crear una **versión nueva** en borrador — las
versiones existentes son inmutables. Solo puede existir **una versión activa** por
`(tenant_id, product_id)`.

#### Scenario: Editar crea versión nueva
- **WHEN** el staff guarda cambios sobre la política v2 activa de un producto
- **THEN** se crea la v3 en borrador sin alterar la v2, que sigue activa

#### Scenario: Unicidad de política activa
- **WHEN** se intenta activar una segunda política para el mismo `(tenant_id, product_id)`
- **THEN** la versión previamente activa se desactiva en la misma transacción

#### Scenario: Aislamiento entre tenants
- **WHEN** un staff de otro tenant consulta las políticas
- **THEN** solo ve las de su propio tenant (scoping `HasTenant`)

### Requirement: Activación, rollback y modo de la política
Activar una versión DEBE (MUST) registrar `activated_at`/`activated_by` y desactivar la
versión activa anterior. El rollback es activar una versión previa. Cada política tiene
modo `OFF | SHADOW | ACTIVE`: en `OFF` el motor no evalúa; en `SHADOW` evalúa y registra
sin actuar; en `ACTIVE` evalúa y ejecuta salidas.

#### Scenario: Rollback a versión anterior
- **WHEN** el staff activa la v1 estando la v3 activa
- **THEN** la v1 queda activa, la v3 inactiva, y las evaluaciones siguientes usan la v1

#### Scenario: Sin política activa
- **WHEN** una solicitud se envía y su producto no tiene política activa (o está en `OFF`)
- **THEN** el flujo permanece 100% manual como hoy (sin evaluación ni registro)

### Requirement: Seeder idempotente de la política MoneyCapital
El seeder de MoneyCapital DEBE (MUST) sembrar la política v1 del producto piloto
(valores de la Matriz Maestra: bandas $300-400/$500-600/$700-800/$900-1,000, primer
crédito a 7 días, vigencia 72h, cooldown 30 días, graduación por niveles) en modo
`SHADOW`, **solo si no existe ninguna política** para ese tenant/producto. Nunca DEBE
sobrescribir políticas existentes (prod re-siembra tenants en cada deploy).

#### Scenario: Primera siembra
- **WHEN** corre el seeder y el producto MoneyCapital no tiene políticas
- **THEN** se crea la v1 en modo `SHADOW` con los defaults de la matriz

#### Scenario: Re-siembra en deploy
- **WHEN** corre el seeder y ya existe cualquier política (editada por el admin)
- **THEN** no se crea ni modifica ninguna política

### Requirement: La política no se expone en la API pública
La configuración de decisión NO DEBE (MUST NOT) viajar en `/api/v2/config` ni en ningún
endpoint público o de applicant. La whitelist `formatRules` permanece sin llaves de
decisión; el applicant solo conoce el resultado (oferta/estado), nunca la política.

#### Scenario: Config pública sin política
- **WHEN** el frontend público consulta `/api/v2/config`
- **THEN** el payload no contiene umbrales, scoring, bandas ni graduación
