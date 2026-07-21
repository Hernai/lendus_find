## Why

MoneyCapital quiere que la **solicitud de crédito sea exclusiva de la app móvil** (#8) y que la **web sea meramente informativa** (#9). Hoy el onboarding corre igual en web. Como el equipo aún necesita **probar en web**, el bloqueo se implementa detrás de un **feature flag apagado**: queda listo y se enciende cuando la app esté publicada, sin re-trabajo ni romper las pruebas actuales.

## What Changes

- **Feature flag `app_only_channel`** en el tenant (MoneyCapital, **OFF** por defecto).
- **Guard de router**: cuando el flag está ON y el acceso NO es nativo (`!platform.device.isNative()`), las rutas de **solicitud** (auth/onboarding) redirigen a la **landing informativa** (que lleva la descarga de la app). Con el flag OFF, todo sigue igual (acceso web para pruebas).
- **CTA de descarga de la app** en `MoneyCapitalLanding` (App Store / Google Play), reutilizando los links ya presentes en las otras landings de MoneyCapital.
- El **simulador web se mantiene** como gancho (calcula en web; para solicitar, descarga la app).

## Capabilities

### New Capabilities
- **`canal-solo-app`**: la solicitud de crédito puede restringirse a la app móvil mediante un flag por tenant, con la web como canal informativo + descarga.

## Impact

- **Backend**: `MoneyCapitalSeeder` (agregar `features.app_only_channel = false`).
- **Frontend**: `router/index.ts` (guard tras el flag), `MoneyCapitalLanding.vue` (CTA de descarga). Sin backend adicional.
- **Sin migraciones**. El flag OFF preserva el comportamiento actual (pruebas web).

## Non-goals

- **Eliminar el simulador web**: se mantiene (decisión del grilling).
- **Activar el flag ahora**: queda OFF; el equipo lo enciende cuando publique la app.
