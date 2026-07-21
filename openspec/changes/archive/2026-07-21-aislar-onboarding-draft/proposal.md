## Why

El borrador del onboarding se guarda en `localStorage['onboarding_draft']` con una **key
fija, sin sello de tenant/cuenta**, y `loadFromStorage()` lo restaura ciegamente en `init()`.
Si el mismo dispositivo/navegador se usa para dos cuentas (mismo tenant, o cruzando demo↔
MoneyCapital que comparten origen `lendus.app`), el borrador de una cuenta se prefilla en el
flujo de otra — incluyendo **PII** (nombre, RFC, dirección y ahora el correo). Es la misma
clase de fuga que motivó `pruneForeignTenantState` en `application.ts`.

## What Changes

- **Sellar** el borrador con `{ tenantId, userId }` al guardar (`saveToStorage`).
- Al cargar (`loadFromStorage`), **descartar** el borrador si el sello no coincide con la
  sesión actual (tenant o usuario distinto), o si **no tiene sello** (borradores viejos). Solo
  se restaura cuando tenant **y** usuario coinciden.
- **Limpiar** el borrador en `auth.ts`, en el punto donde ya se detecta el cambio de usuario
  (`previousUserId !== apiUser.id`).

## Non-goals

- No usar namespacing por key (se decidió sellar + descartar).
- No cambiar el contenido del borrador más allá de agregar el sello.
- No tocar el backend.

## Capabilities

### New Capabilities
- `aislar-onboarding-draft`: el borrador del onboarding queda aislado por tenant y usuario; un
  borrador de otra sesión no se restaura y se limpia al cambiar de usuario.

### Modified Capabilities
<!-- Ninguna. -->

## Impact

- **Frontend:** `stores/onboarding.ts` (`saveToStorage` + `loadFromStorage`), `stores/auth.ts`
  (limpiar el borrador en el cambio de usuario). Sin backend.
- Los borradores viejos **sin sello** se descartan una vez (el usuario pierde un borrador en
  progreso) — aceptable por ser un fix de aislamiento de datos.
