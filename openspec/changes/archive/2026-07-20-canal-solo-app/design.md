## Context

El onboarding dinámico corre en web y nativo. `router/index.ts` (`beforeEach`) ya gatea la landing por nativo (`platform.device.isNative()`) y lee `features.*` del tenant. Los links de tiendas ya existen en `SinBuroLanding.vue`/`LiquidezUrgenteLanding.vue`. `MoneyCapitalLanding.vue` tiene CTAs `goToAuth` ("Solicitar ahora"/"Comenzar mi solicitud") y `goToSimulator`.

## Goals / Non-Goals

**Goals:** restringir la solicitud a la app vía flag por tenant, sin romper el acceso web de pruebas; web informativa + descarga; conservar el simulador.

**Non-Goals:** quitar el simulador; activar el flag; tocar el flujo nativo.

## Decisions

### 1. Flag `app_only_channel` por tenant (OFF)
Se agrega a `tenant.features`. OFF = comportamiento actual (web abierta). El seeder de MoneyCapital lo siembra en `false`. Se enciende manualmente (o desde el admin de tenant) cuando la app esté publicada.

### 2. Guard en `router.beforeEach`
Cuando `!platform.device.isNative()` **y** `features.app_only_channel === true` **y** la ruta destino es de **solicitud** (auth/onboarding: `tenant-onboarding-dynamic`, `m-onboarding-*`, `/auth`, `/solicitud/*`), redirigir a la landing informativa del tenant. Con el flag OFF, el guard es no-op.
- **Alternativa (pantalla dedicada "descarga la app")**: se prefiere redirigir a la landing existente (ya tendrá la descarga), menos código nuevo.

### 3. CTA de descarga en `MoneyCapitalLanding`
Agregar botones App Store / Google Play (reusando los href de `SinBuroLanding`). El botón "Solicitar" se mantiene: con el flag OFF lleva al onboarding (pruebas); con el flag ON, el guard lo intercepta. No se elimina el simulador.

## Risks / Trade-offs

- **Encender el flag sin app publicada dejaría a los usuarios web sin poder solicitar** → el flag arranca OFF y su encendido es una decisión operativa consciente (cuando la app esté en tiendas).
- **Deep-links web a onboarding con el flag ON** → el guard cubre `beforeEach`, así que cualquier navegación (incluye deep-link) se intercepta.

## Migration Plan

Sin datos. El flag OFF preserva todo. Rollback: quitar el flag/guard.

## Open Questions

Ninguna: las decisiones salieron del grilling (mantener web para pruebas, simulador como gancho, retomar en app).
