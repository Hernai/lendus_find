## 1. Ruta móvil de perfil

- [x] 1.1 En `frontend/src/router/routes/mobile.ts`, agregar la ruta `/m/perfil` con `name: 'm-profile'`, `component: ProfileView` (importar la vista existente) y `meta: { requiresAuth: true, mobileEntry: true }`.

## 2. Nav inferior móvil

- [x] 2.1 En `frontend/src/components/mobile/MobileBottomNav.vue`, cambiar la etiqueta del tercer tab de "Mi cuenta" a "Perfil" y su `@click` para navegar a `go('m-profile')` (conservar el ícono de persona).
- [x] 2.2 Agregar `'m-profile'` a `KNOWN_ROUTES` y marcar el tab activo cuando `currentName === 'm-profile'`.
- [x] 2.3 Eliminar la función `openAccount()` y el `window.confirm` de logout (el logout ahora vive dentro de `ProfileView`); limpiar el import de `useAuthStore` si queda sin uso.

## 3. Adaptación de contexto móvil en ProfileView

- [x] 3.1 En `frontend/src/views/applicant/dashboard/ProfileView.vue`, derivar un flag `isMobileContext` desde el router (`route.meta.mobileEntry === true` o path bajo `/m`).
- [x] 3.2 Hacer `goBack()` condicional: `/m/home` en móvil, `/dashboard` en web.
- [x] 3.3 Hacer `handleLogout()` condicional: redirigir a `/m` en móvil, `/` en web.
- [x] 3.4 Renderizar `<MobileBottomNav>` solo en contexto móvil y agregar padding inferior para que el nav no tape el contenido.

## 4. Coherencia del ingreso con el onboarding (ProfileView)

- [x] 4A.1 Agregar computed `salaryRangeLabel` en `ProfileView.vue` que reconstruye la etiqueta del rango desde `monthly_income` (espeja `SalaryRange::fromIncome/label`); devuelve null si no hay ingreso.
- [x] 4A.2 Mostrar el rango debajo del monto en la fila "Ingreso mensual".
- [x] 4A.3 Ocultar la fila "Antigüedad" cuando `seniority_months` es falsy (el endpoint la colapsa a `0`, no `null`, cuando MC/Demo no la capturan — condición truthy, no `!= null`).

## 5. Verificación

- [x] 5.1 `npm run type-check` sin errores nuevos (re-corrido tras el ajuste de ingreso).
- [x] 5.2 Revisar el diff con el agente `revisor-lendus` (aislamiento de tenant, sin regresión web, buckets del rango salarial correctos, convenciones). Sin hallazgos bloqueantes; se alineó el padding móvil a `pb-32`.
- [x] 5.3 Smoke con `ui-smoke` (stack desechable, moneycapital): `/m/home` → tab "Perfil" → `/m/perfil` (nav visible), "Volver" → `/m/home`, "Salir" → `/m`. 6/6 ✅, 0 errores de consola relevantes.
- [x] 5.5 Verificación visual del ingreso (ui-smoke, solicitante MC con empleo sembrado, ingreso 7500): en pantalla se ve "Ingreso mensual: $7,500" + "Rango: $6,001 - $9,000" y NO se renderiza la fila "Antigüedad". 3/3 ✅.
- [ ] 5.6 PENDIENTE-EMULADOR (fuera de ui-smoke, requiere emulador nativo): el `<input type="file">` de la foto en el webview de Capacitor.
- [x] 5.4 Confirmado: perfil web (`/perfil` → `/moneycapital/perfil`) sigue igual — "Volver" → `/dashboard`, sin nav inferior móvil.
