## Context

El canal móvil de LendusFind (rutas `/m/*`, entry-point Capacitor) es la experiencia de los
tenants solo-app como MoneyCapital (`canal-solo-app`). Su navegación es `MobileBottomNav`
con tres tabs: Inicio (`m-home`), Pagos (`m-loan-dashboard`, gated por `loan_portfolio`) y
un tercer tab "Mi cuenta" que hoy es un **placeholder**: solo hace `window.confirm` + logout
y no navega a ninguna vista. El propio código lo documenta como pendiente ("Cuando se
agreguen las vistas de Pagos/Perfil, registrar sus rutas y se activarán automáticamente").

La vista completa de perfil, `ProfileView.vue`, ya existe y ya está estilizada mobile-first
(safe-area insets, tarjetas redondeadas, foto 24×24) e incluye todas las secciones y la
gestión de foto y cuentas bancarias. Solo está cableada a rutas web (`/perfil`,
`/:tenant/perfil`). El objetivo es exponerla en el canal móvil con el mínimo de código.

## Goals / Non-Goals

**Goals:**
- Dar acceso a "Mi Perfil" desde la app móvil con paridad funcional total.
- Reutilizar `ProfileView` sin duplicar UI ni lógica.
- Mantener intacto el comportamiento del perfil web.
- Aplicar a todos los tenants del canal móvil, sin hardcode por tenant.

**Non-Goals:**
- Vista de perfil móvil dedicada o UX distinta a web.
- Cambios de backend/API/migración.
- Tocar el tab "Pagos" o la whitelist de `formatRules`.

## Decisions

### 1. Reusar `ProfileView` en `/m/perfil` (vs. vista nueva)
`ProfileView` ya es mobile-first y ya implementa foto + CRUD de cuentas. Crear una
`MobileProfileView` obligaría a replicar ~600 líneas y dos flujos con estado (foto, cuentas).
**Elegido:** registrar `/m/perfil` (`m-profile`) apuntando al mismo componente.
*Alternativa descartada:* vista nueva — más código, más superficie de bug, sin beneficio
de UX que lo justifique en v1.

### 2. Detección de contexto móvil dentro de `ProfileView`
`ProfileView` necesita cambiar solo su navegación según dónde se montó. Se detecta con el
router: `route.meta.mobileEntry === true` (o `route.path` bajo `/m`). Con ese flag:
- `goBack()` → `router.push('/m/home')` en móvil; `/dashboard` en web (actual).
- `handleLogout()` → redirige a `/m` en móvil; `/` en web (actual).
- Se renderiza `<MobileBottomNav>` y se agrega padding inferior para no tapar contenido.
*Alternativa descartada:* prop `isMobile` pasada por una vista contenedora — requeriría un
wrapper y contradice el reuso directo de la ruta.

### 3. Tercer tab: "Perfil" navegable (vs. mantener "Mi cuenta")
Como `ProfileView` ya trae botón "Salir", el logout no se pierde al reconvertir el tab.
**Elegido:** renombrar el tab a "Perfil", `@click="go('m-profile')"`, agregar `m-profile` a
`KNOWN_ROUTES`, y eliminar `openAccount()`/`window.confirm`.
*Alternativa descartada:* cuarto tab o action-sheet — más componentes y logout duplicado.

### 4. Disponibilidad para todos los tenants móviles (vs. gate MoneyCapital)
El `MobileBottomNav` es compartido; condicionar por slug/flag agrega lógica per-tenant y
arriesga inconsistencia. **Elegido:** sin gate; MoneyCapital lo obtiene por ser solo-app.

## Risks / Trade-offs

- **Input de foto en webview Capacitor** → El `<input type="file">` de `ProfileView` puede
  comportarse distinto en nativo. *Mitigación:* verificar en vivo con `ui-smoke`; si falla,
  fallback a plugin de cámara queda para una iteración posterior (no bloquea v1 de lectura y
  cuentas).
- **Redirección de logout a `/m`** → Depende de que el guard maneje `/m` en nativo.
  *Mitigación:* el guard ya desvía la landing pública a `/m` en nativo; verificar que el
  logout aterrice ahí y no en una web pública.
- **Regresión en perfil web** → Los cambios de navegación deben ser condicionales.
  *Mitigación:* escenario de spec "Perfil web intacto" + `npm run type-check` y smoke web.
- **`goBack` asume `/m/home` existe** → Es una ruta ya registrada (`m-home`); bajo riesgo.

## Migration Plan

Cambio puramente aditivo en frontend, sin datos ni backend. Deploy = build del frontend.
Rollback = revertir los 3 archivos; no hay estado persistido nuevo que limpiar.
