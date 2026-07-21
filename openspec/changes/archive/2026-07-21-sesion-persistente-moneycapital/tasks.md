## 1. Ocultar cierre de sesión en móvil

- [x] 1.1 En `ProfileView.vue`, condicionar el botón "Salir" con `v-if="!isMobileContext"` (se conserva en web).

## 2. Verificación

- [x] 2.1 Confirmar por investigación que la sesión ya persiste (token Sanctum sin expiración, sin auto-logout) — no requiere cambio de auth.
- [x] 2.2 `npm run type-check` sin errores (`handleLogout` sigue referenciado por el botón web).
- [ ] 2.3 PENDIENTE-SMOKE (batch): en `/m/perfil` no aparece "Salir"; en `/perfil` (web) sí.
