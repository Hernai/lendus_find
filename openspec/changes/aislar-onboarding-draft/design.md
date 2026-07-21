## Context

`stores/onboarding.ts` persiste el borrador en `localStorage['onboarding_draft']` (key fija)
vía `saveToStorage()` (guarda `{ data, completedSteps, currentStep, savedAt }`, sin sello) y
lo restaura en `loadFromStorage()` dentro de `init()`. El onboarding es post-auth, así que hay
`userId` disponible (`STORAGE_KEYS.CURRENT_USER_ID`) además de `tenantStore.tenant.id`.
`stores/auth.ts` ya detecta el cambio de usuario (`previousUserId !== apiUser.id`, ~línea 283).
`application.ts` ya resuelve esta clase de problema con `pruneForeignTenantState` (descartar
estado residual de otro tenant).

## Goals / Non-Goals

**Goals:** que un borrador nunca prefile datos (PII) de una cuenta/tenant distinto.

**Non-Goals:** namespacing por key; cambiar el contenido del borrador; tocar el backend.

## Decisions

### 1. Sellar + descartar (no namespacing)
Se estampa `{ tenantId, userId }` en el objeto guardado; al cargar se descarta si no coincide.
Es consistente con `pruneForeignTenantState` y con la detección de cambio de usuario que ya
existe en `auth.ts`. *Alternativa descartada:* namespacing por key (`onboarding_draft_<t>_<u>`)
— preserva el borrador de cada cuenta, pero acumula keys y se aparta del patrón del repo.

### 2. Doble refuerzo
(a) Guardia en `loadFromStorage`: solo restaura si tenant+usuario coinciden; descarta y limpia
si no. (b) Limpieza en `auth.ts` en el punto de cambio de usuario ya existente. Cinturón y
tirantes: cubre tanto el arranque del store como el momento del switch de sesión.

### 3. Borradores sin sello se descartan
Los borradores creados antes de este cambio no tienen sello; se tratan como ajenos y se
descartan (default seguro). El costo es perder un borrador en progreso una sola vez.

### 4. Fuente de identidad
`tenantId` desde `tenantStore.tenant?.id` (con fallback a `STORAGE_KEYS.CURRENT_TENANT_ID`) y
`userId` desde `STORAGE_KEYS.CURRENT_USER_ID` — disponibles al cargar sin depender de que el
store de auth esté hidratado.

## Risks / Trade-offs

- **Pérdida de borradores viejos** → una vez, aceptable por ser fix de seguridad.
- **Timing de identidad al cargar** → usar los `STORAGE_KEYS` (persistidos en login) evita
  depender de refs reactivos aún no hidratados.
- **Evitar acoplar `auth.ts` al store de onboarding** → limpiar la key de `localStorage`
  directamente (sin import cruzado) para no crear dependencias circulares.

## Migration Plan

Frontend puro. Rollback = revertir `onboarding.ts` y `auth.ts`. No hay datos de servidor.
