<script setup lang="ts">
import { onMounted, onBeforeUnmount } from 'vue'

/**
 * Bottom sheet reusable para los pasos de onboarding white-label.
 *
 * Renderiza un drawer pegado al fondo con esquinas superiores
 * redondeadas, header con título + X close, body scrollable y un slot
 * opcional `actions` para el botón continuar. El backdrop oscurece la
 * pantalla base que queda visible detrás.
 *
 * Tenant-agnóstico: usa `var(--tenant-primary)` y `rgb(var(--primary-N-rgb))`.
 */

defineProps<{
  title: string
  subtitle?: string
}>()

const emit = defineEmits<{
  close: []
}>()

function handleEsc(e: KeyboardEvent) {
  if (e.key === 'Escape') emit('close')
}

onMounted(() => {
  document.addEventListener('keydown', handleEsc)
  document.body.style.overflow = 'hidden'
})

onBeforeUnmount(() => {
  document.removeEventListener('keydown', handleEsc)
  document.body.style.overflow = ''
})
</script>

<template>
  <div class="sheet-overlay" @click.self="emit('close')">
    <div class="sheet" role="dialog" aria-modal="true">
      <div class="sheet-handle" aria-hidden="true" />
      <header class="sheet-header">
        <h2 class="sheet-title">{{ title }}</h2>
        <button type="button" class="sheet-close" aria-label="Cerrar" @click="emit('close')">
          <svg viewBox="0 0 24 24" fill="none">
            <path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
          </svg>
        </button>
      </header>
      <p v-if="subtitle" class="sheet-subtitle">{{ subtitle }}</p>
      <div class="sheet-body">
        <slot />
      </div>
      <div v-if="$slots.actions" class="sheet-actions">
        <slot name="actions" />
      </div>
    </div>
  </div>
</template>

<style scoped>
.sheet-overlay {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.5);
  display: grid;
  place-items: end center;
  z-index: 60;
  animation: fadeIn 160ms ease;
  padding-bottom: env(safe-area-inset-bottom);
}
.sheet {
  background: #ffffff;
  width: 100%;
  max-width: 520px;
  max-height: 88vh;
  border-radius: 24px 24px 0 0;
  display: flex;
  flex-direction: column;
  animation: slideUp 240ms cubic-bezier(0.2, 0.8, 0.2, 1);
  padding-bottom: env(safe-area-inset-bottom);
}
.sheet-handle {
  width: 40px;
  height: 4px;
  background: #e2e8f0;
  border-radius: 999px;
  margin: 10px auto 0;
}
.sheet-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 14px 20px 8px;
}
.sheet-title {
  font-size: 17px;
  font-weight: 700;
  color: #0f172a;
  margin: 0;
  letter-spacing: -0.2px;
}
.sheet-close {
  background: transparent;
  border: none;
  cursor: pointer;
  color: #64748b;
  width: 30px;
  height: 30px;
  display: grid;
  place-items: center;
  border-radius: 999px;
  -webkit-tap-highlight-color: transparent;
  transition: background 120ms ease;
}
.sheet-close:hover,
.sheet-close:active {
  background: #f1f5f9;
}
.sheet-close svg {
  width: 18px;
  height: 18px;
}
.sheet-subtitle {
  margin: 0 20px 8px;
  font-size: 13.5px;
  color: #64748b;
  line-height: 1.45;
}
.sheet-body {
  flex: 1;
  overflow-y: auto;
  padding: 8px 20px 16px;
}
.sheet-actions {
  padding: 12px 20px 16px;
  border-top: 1px solid #f1f5f9;
}
@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}
@keyframes slideUp {
  from { transform: translateY(100%); }
  to { transform: translateY(0); }
}
</style>
