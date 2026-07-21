## 1. Feature flag

- [x] 1.1 En `MoneyCapitalSeeder`, agregar `features.app_only_channel => false` al tenant.

## 2. Guard de router

- [x] 2.1 En `frontend/src/router/index.ts` (`beforeEach`), si `!platform.device.isNative()` y `features.app_only_channel === true` y la ruta destino es de solicitud (`tenant-onboarding-dynamic`, `m-onboarding-*`, `/auth`, `/solicitud/*`), redirigir a la landing informativa del tenant.
- [x] 2.2 Con el flag OFF, el guard es no-op (no altera nada).

## 3. Landing informativa

- [x] 3.1 En `MoneyCapitalLanding.vue`, agregar CTA de descarga (App Store / Google Play) reutilizando los href de `SinBuroLanding.vue`.
- [x] 3.2 Mantener el simulador (gancho); no eliminar `goToSimulator`.

## 4. Verificación

- [x] 4.1 `type-check` + `lint` frontend; `php -l` seeder.
- [x] 4.2 Verificar manualmente: con flag OFF el onboarding web sigue accesible (pruebas); con flag ON redirige a la landing.
