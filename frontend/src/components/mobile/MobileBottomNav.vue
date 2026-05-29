<script setup lang="ts">
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

/**
 * Bottom navigation reusable para vistas mobile.
 *
 * Tabs: Inicio (m-home), Préstamos (m-loan-dashboard), Notificaciones (placeholder), Perfil (placeholder).
 * Resalta el tab actual según `route.name`.
 */

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()

const currentName = computed(() => String(route.name ?? ''))

// "Mi cuenta" aún no tiene vista propia: por ahora ofrece cerrar sesión con
// confirmación (evita el logout accidental que tenía el home).
function openAccount() {
  if (window.confirm('¿Deseas cerrar sesión?')) {
    authStore.logout()
  }
}

// Solo navegamos a rutas que existen; las demás se ignoran de forma segura
// (evita promesas rechazadas y "tabs muertos"). Cuando se agreguen las vistas
// de Pagos/Perfil, registrar sus rutas y se activarán automáticamente.
const KNOWN_ROUTES = new Set(['m-home', 'm-loan-dashboard', 'm-loan-detail'])

function go(name: string) {
  if (currentName.value === name) return
  if (!router.hasRoute(name) && !KNOWN_ROUTES.has(name)) return
  router.push({ name }).catch(() => { /* navegación cancelada/duplicada */ })
}
</script>

<template>
  <nav class="bottom-nav" aria-label="Navegación principal">
    <button
      type="button"
      class="nav-item"
      :class="{ 'nav-item--active': currentName === 'm-home' }"
      :aria-current="currentName === 'm-home' ? 'page' : undefined"
      @click="go('m-home')"
    >
      <svg viewBox="0 0 24 24" fill="none">
        <path d="M3 11l9-7 9 7v9a1 1 0 01-1 1h-5v-6h-6v6H4a1 1 0 01-1-1v-9z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" />
      </svg>
      <span>Inicio</span>
    </button>
    <button
      type="button"
      class="nav-item"
      :class="{ 'nav-item--active': currentName === 'm-loan-dashboard' || currentName === 'm-loan-detail' }"
      @click="go('m-loan-dashboard')"
    >
      <svg viewBox="0 0 24 24" fill="none">
        <rect x="3" y="6" width="18" height="13" rx="2" stroke="currentColor" stroke-width="1.8" />
        <path d="M3 11h18M7 16h4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
      </svg>
      <span>Pagos</span>
    </button>
    <button
      type="button"
      class="nav-item"
      @click="openAccount"
    >
      <svg viewBox="0 0 24 24" fill="none">
        <circle cx="12" cy="8.5" r="3.5" stroke="currentColor" stroke-width="1.8" />
        <path d="M4 21c.5-4 4-6 8-6s7.5 2 8 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
      </svg>
      <span>Mi cuenta</span>
    </button>
  </nav>
</template>

<style scoped>
.bottom-nav {
  position: fixed;
  left: 0; right: 0; bottom: 0;
  background: #ffffff;
  border-top: 1px solid #eef0f4;
  display: flex;
  padding: 6px 6px calc(6px + env(safe-area-inset-bottom));
  z-index: 40;
}
.nav-item {
  flex: 1;
  background: transparent; border: none;
  display: flex; flex-direction: column; align-items: center; gap: 2px;
  padding: 8px 4px;
  color: #94a3b8;
  font-size: 11px; font-weight: 600;
  cursor: pointer;
  -webkit-tap-highlight-color: transparent;
}
.nav-item svg { width: 22px; height: 22px; }
.nav-item--active { color: var(--tenant-primary, #5B21B6); }
.nav-item:active { color: var(--tenant-primary, #5B21B6); }
</style>
