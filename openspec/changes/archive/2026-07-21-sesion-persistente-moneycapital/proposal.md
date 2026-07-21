## Why

MoneyCapital no usa PIN, así que su re-login es por OTP (SMS, con costo). Si el usuario
cierra sesión voluntariamente, reingresar gasta un SMS. El único cierre voluntario en el
canal móvil es el botón "Salir" del perfil. Queremos que la sesión persista y no ofrecer
cerrarla en el canal móvil.

## What Changes

- Ocultar el botón "Salir" del perfil (`ProfileView`) cuando se está en contexto móvil
  (`isMobileContext`). En web se conserva.
- La sesión **ya persiste** hoy (token Sanctum sin expiración configurada, sin auto-logout
  por inactividad), así que no se requiere cambio adicional en auth. El único reingreso por
  OTP queda para el caso de "borrar datos" del dispositivo (inevitable).

## Non-goals

- No cambiar la expiración de tokens ni el flujo de autenticación.
- No agregar PIN a MoneyCapital.
- No implementar restauración de la ruta exacta "donde estabas" (la sesión persistente ya
  deja al usuario autenticado al reabrir la app).

## Capabilities

### New Capabilities
- `sesion-persistente-moneycapital`: el canal móvil no ofrece cerrar sesión; la sesión
  persiste para evitar el re-login por SMS.

### Modified Capabilities
<!-- Ninguna. -->

## Impact

- **Frontend:** `frontend/src/views/applicant/dashboard/ProfileView.vue` (un `v-if` en el
  botón "Salir"). Sin backend, sin cambios de auth.
