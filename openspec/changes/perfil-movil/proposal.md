## Why

Los tenants que operan en canal móvil/solo-app (p. ej. MoneyCapital, vía `canal-solo-app`)
no tienen forma de ver "Mi Perfil": el nav inferior móvil expone un tercer tab "Mi cuenta"
que solo dispara logout, y no existe ninguna ruta `/m/perfil`. La vista de perfil completa
(`ProfileView`) hoy solo está cableada a rutas web (`/perfil`, `/:tenant/perfil`), así que
el usuario de la app nunca puede consultar sus datos ni gestionar su cuenta bancaria —
justo lo que el canal app necesita para recibir el crédito.

## What Changes

- Nueva ruta móvil `/m/perfil` (`m-profile`) que **reutiliza** la vista `ProfileView`
  existente; no se crea una vista nueva.
- `ProfileView` gana comportamiento sensible al contexto móvil: cuando se abre bajo `/m`,
  el botón "Volver" va a `/m/home`, el `MobileBottomNav` queda visible (con padding
  inferior) y el logout regresa a `/m`.
- El tercer tab del `MobileBottomNav` pasa de "Mi cuenta" (placeholder de logout) a
  "Perfil", que navega a `/m/perfil`. El logout se conserva vía el botón "Salir" que ya
  vive dentro de `ProfileView`. Se agrega `m-profile` a `KNOWN_ROUTES` y se elimina el
  `window.confirm` de logout del nav.
- Aplica a **todos** los tenants del canal móvil (sin hardcode por tenant/slug).
- Coherencia del perfil con lo capturado: en los flujos que capturan un **rango salarial**
  (`salary_range`, persistido como midpoint en `monthly_income` — MoneyCapital, Demo) el
  perfil muestra el rango reconstruido junto al monto; y la fila "Antigüedad" se oculta
  cuando el onboarding no la capturó (evita mostrar un guion vacío).

## Non-goals

- No se construye una vista de perfil móvil dedicada ni una UX distinta a la web (se reusa).
- No se toca el segundo tab "Pagos" (`m-loan-dashboard`, gated por `loan_portfolio`).
- No hay cambios de backend, API, ni migración de datos.
- No se modifica la whitelist de `formatRules` en `/api/v2/config`.
- No se rediseñan las secciones de `ProfileView` más allá del ajuste de coherencia del
  ingreso/antigüedad (foto y CRUD de cuentas se reusan tal cual).
- No se persiste `salary_range` en el backend (se sigue derivando de `monthly_income`); el
  perfil solo lo reconstruye para mostrarlo.

## Capabilities

### New Capabilities
- `perfil-movil`: Acceso a "Mi Perfil" desde el canal móvil/app — ruta `/m/perfil` que
  reutiliza `ProfileView` con navegación adaptada, y tab "Perfil" en el nav inferior con
  paridad funcional total (datos, foto, cuentas bancarias).

### Modified Capabilities
<!-- Ninguna: el cambio no altera requisitos de specs existentes (canal-solo-app sigue igual). -->

## Impact

- **Frontend, 3 archivos:**
  - `frontend/src/router/routes/mobile.ts` — nueva ruta `/m/perfil`.
  - `frontend/src/components/mobile/MobileBottomNav.vue` — tab "Perfil" + `KNOWN_ROUTES` +
    quitar confirm de logout.
  - `frontend/src/views/applicant/dashboard/ProfileView.vue` — adaptación de navegación
    según contexto móvil.
- **Riesgo a verificar (no bloqueante):** el `<input type="file">` de la foto en el webview
  nativo de Capacitor — se valida en vivo con `ui-smoke`.
- Sin impacto en backend, base de datos ni integraciones.
