# aislar-onboarding-draft Specification

## Purpose
TBD - created by archiving change aislar-onboarding-draft. Update Purpose after archive.
## Requirements
### Requirement: El borrador del onboarding está sellado por tenant y usuario

El borrador del onboarding persistido en `localStorage` SHALL incluir un sello con el
`tenantId` y el `userId` de la sesión que lo creó.

#### Scenario: El borrador se guarda con sello
- **WHEN** se persiste el borrador del onboarding
- **THEN** el objeto guardado incluye el `tenantId` y el `userId` actuales

### Requirement: Un borrador de otra sesión no se restaura

Al cargar el borrador, el sistema SHALL restaurarlo únicamente si su sello coincide con el
tenant **y** el usuario de la sesión actual. Si el tenant o el usuario difieren, o si el
borrador no tiene sello (creado antes de este cambio), SHALL descartarse y limpiarse de
`localStorage`, sin prefilar ningún dato.

#### Scenario: Borrador de otro usuario/tenant se descarta
- **WHEN** existe un borrador cuyo `tenantId` o `userId` no coincide con la sesión actual
- **THEN** no se restaura ningún dato y el borrador se elimina de `localStorage`

#### Scenario: Borrador sin sello se descarta
- **WHEN** existe un borrador sin `tenantId`/`userId` (creado antes de este cambio)
- **THEN** se descarta (no se restaura) por seguridad

#### Scenario: Borrador propio se restaura
- **WHEN** el borrador tiene el mismo `tenantId` y `userId` que la sesión actual
- **THEN** se restaura normalmente

### Requirement: El borrador se limpia al cambiar de usuario

Cuando la autenticación detecta que el usuario cambió respecto al anterior, el sistema SHALL
limpiar el borrador del onboarding de `localStorage`.

#### Scenario: Cambio de usuario limpia el borrador
- **WHEN** inicia sesión un usuario distinto al que había en el dispositivo
- **THEN** el borrador del onboarding previo se elimina

