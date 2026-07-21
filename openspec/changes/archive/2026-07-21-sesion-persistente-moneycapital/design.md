## Context

MoneyCapital autentica solo por teléfono (feature `unified_auth_screen`) y, según lo
aclarado, no usa PIN. El re-login dispara OTP por SMS (costo). Los tokens Sanctum del
solicitante se crean sin expiración y no hay auto-logout por inactividad ni temporizador de
sesión en el frontend (verificado en la investigación). El único cierre voluntario en el
canal móvil es el botón "Salir" del perfil (`ProfileView.handleLogout` → revoca el token). El
nav inferior móvil ya no tiene logout (se quitó en `perfil-movil`).

## Goals / Non-Goals

**Goals:** que el canal móvil no ofrezca cerrar sesión; que la sesión persista para evitar
SMS de re-login.

**Non-Goals:** cambiar expiración de tokens, flujo de auth, agregar PIN, o restaurar la ruta
exacta previa.

## Decisions

### 1. Ocultar "Salir" en móvil (no eliminarlo)
Se condiciona el botón con `v-if="!isMobileContext"` (el mismo flag que ya usa `ProfileView`
para adaptar navegación). En web se conserva el logout. *Alternativa descartada:* eliminarlo
del todo — rompería el logout del perfil web, que es compartido.

### 2. No tocar auth/token
La sesión ya persiste por diseño (token sin expiración, sin auto-logout). El caso de
"borrar datos" → reingreso por OTP se acepta como inevitable. No se agrega PIN.

## Risks / Trade-offs

- **Sin logout en móvil** → un usuario no puede cerrar sesión en la app (aceptado; es el
  objetivo). Un revisor de tienda puede "borrar datos" para reingresar.
- **Compartir `ProfileView` web/móvil** → el `v-if` por contexto evita afectar el logout web.

## Migration Plan

Cambio de frontend (un `v-if`). Rollback = quitar la condición.
