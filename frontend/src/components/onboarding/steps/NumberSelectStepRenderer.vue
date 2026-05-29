<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'
import type { NumberSelectStep } from '@/types/v2/onboardingStep'

/**
 * Renderiza step `number_select` (ej. crédito previos).
 *
 * Layout pantalla 10 mock:
 *  - Banner con icono chart + copy explicativo
 *  - Pregunta + dropdown trigger mostrando selección
 *  - Hint
 *  - Bottom sheet auto-abierto con lista de opciones
 *
 * Tap en opción → guarda + cierra sheet (parent auto-avanza).
 */

const props = defineProps<{
  step: NumberSelectStep
  modelValue: number | null
}>()

const emit = defineEmits<{
  'update:modelValue': [value: number]
}>()

const sheetOpen = ref(true)
const localValue = ref<number | null>(props.modelValue)

watch(() => props.modelValue, (v) => {
  localValue.value = v
})

onMounted(() => {
  // Auto-abrir sheet al montar
  sheetOpen.value = true
})

const triggerLabel = computed(() => {
  if (localValue.value === null) return 'Selecciona una opción'
  const last = props.step.options[props.step.options.length - 1]
  if (localValue.value === last && props.step.options.length > 1) return `${last} o más`
  return String(localValue.value)
})

function labelFor(n: number, idx: number, total: number): string {
  if (idx === total - 1 && total > 1) return `${n} o más`
  return String(n)
}

function pick(n: number) {
  localValue.value = n
  emit('update:modelValue', n)
}
</script>

<template>
  <div class="step-number">
    <!-- Banner info -->
    <div class="ns-banner">
      <span class="ns-banner-icon" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none">
          <path d="M7 17V11M12 17V7M17 17V14" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
          <path d="M4 21h16" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
        </svg>
      </span>
      <div>
        <h3 class="ns-banner-title">Cuéntanos tu experiencia con préstamos en línea.</h3>
        <p class="ns-banner-sub">Esta información nos ayuda a evaluar mejor tu perfil.</p>
      </div>
    </div>

    <!-- Pregunta + dropdown trigger -->
    <h2 class="ns-question">{{ step.label }}</h2>
    <button type="button" class="ns-trigger" :class="{ 'ns-trigger--placeholder': localValue === null }" @click="sheetOpen = true">
      <span>{{ triggerLabel }}</span>
      <svg viewBox="0 0 24 24" fill="none">
        <path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
      </svg>
    </button>
    <p class="ns-hint">Si tienes experiencia previa, selecciona la opción que más se acerque a tu historial.</p>

    <!-- Bottom sheet -->
    <Teleport to="body">
      <div v-if="sheetOpen" class="sheet-overlay" @click.self="sheetOpen = false">
        <div class="sheet">
          <div class="sheet-handle" />
          <header class="sheet-header">
            <h2>{{ step.label }}</h2>
            <button type="button" class="sheet-close" aria-label="Cerrar" @click="sheetOpen = false">
              <svg viewBox="0 0 24 24" fill="none">
                <path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
              </svg>
            </button>
          </header>
          <p class="sheet-subtitle">Selecciona la opción que más se acerque a tu historial.</p>
          <ul class="sheet-list">
            <li v-for="(n, idx) in step.options" :key="n">
              <button
                type="button"
                class="sheet-row"
                :class="{ 'sheet-row--active': localValue === n }"
                @click="pick(n)"
              >
                <span class="sheet-label">{{ labelFor(n, idx, step.options.length) }}</span>
                <span class="sheet-radio" :class="{ 'sheet-radio--active': localValue === n }">
                  <span v-if="localValue === n" class="sheet-radio-inner" />
                </span>
              </button>
            </li>
          </ul>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<style scoped>
.step-number {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.ns-banner {
  display: flex;
  gap: 12px;
  padding: 12px 14px;
  background: rgb(var(--surface-soft-rgb, 243 242 250) / 1);
  border-radius: 14px;
  align-items: flex-start;
}
.ns-banner-icon {
  width: 36px;
  height: 36px;
  flex-shrink: 0;
  border-radius: 10px;
  background: #ffffff;
  color: var(--tenant-primary, #5B21B6);
  display: grid;
  place-items: center;
}
.ns-banner-icon svg {
  width: 22px;
  height: 22px;
}
.ns-banner-title {
  margin: 0;
  font-size: 13.5px;
  font-weight: 700;
  color: #0f172a;
  line-height: 1.35;
}
.ns-banner-sub {
  margin: 4px 0 0;
  font-size: 12.5px;
  color: #64748b;
  line-height: 1.4;
}

.ns-question {
  font-size: 15px;
  font-weight: 700;
  color: #0f172a;
  margin: 6px 0 0;
}

.ns-trigger {
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 16px;
  background: #ffffff;
  border: 1.5px solid #e5e7eb;
  border-radius: 14px;
  cursor: pointer;
  -webkit-tap-highlight-color: transparent;
  font-size: 14.5px;
  font-weight: 600;
  color: #0f172a;
  min-height: 56px;
}
.ns-trigger:active {
  border-color: var(--tenant-primary, #5B21B6);
}
.ns-trigger--placeholder {
  color: #9ca3af;
  font-weight: 400;
}
.ns-trigger svg {
  width: 18px;
  height: 18px;
  color: #94a3b8;
}

.ns-hint {
  font-size: 12.5px;
  color: #64748b;
  margin: 0;
  line-height: 1.5;
}

.sheet-overlay {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.5);
  display: grid;
  place-items: end center;
  z-index: 60;
  animation: fadeIn 160ms ease;
}
.sheet {
  background: #ffffff;
  width: 100%;
  max-width: 520px;
  max-height: 75vh;
  border-radius: 24px 24px 0 0;
  display: flex;
  flex-direction: column;
  animation: slideUp 220ms ease;
  padding-bottom: env(safe-area-inset-bottom);
}
.sheet-handle {
  width: 40px;
  height: 4px;
  background: #e2e8f0;
  border-radius: 999px;
  margin: 10px auto 6px;
}
.sheet-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 4px 20px 0;
}
.sheet-header h2 {
  font-size: 16.5px;
  font-weight: 700;
  color: #0f172a;
  margin: 0;
  line-height: 1.3;
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
  flex-shrink: 0;
}
.sheet-close svg { width: 18px; height: 18px; }
.sheet-subtitle {
  margin: 6px 20px 8px;
  font-size: 12.5px;
  color: #64748b;
  line-height: 1.4;
}
.sheet-list {
  list-style: none;
  padding: 0;
  margin: 0;
  overflow-y: auto;
}
.sheet-row {
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 22px;
  background: transparent;
  border: none;
  cursor: pointer;
  font-size: 14.5px;
  color: #0f172a;
  border-bottom: 1px solid #f1f5f9;
}
.sheet-row:last-child {
  border-bottom: none;
}
.sheet-row--active {
  background: rgb(var(--surface-soft-rgb, 243 242 250) / 1);
  color: var(--tenant-primary, #5B21B6);
  font-weight: 600;
}
.sheet-label {
  flex: 1;
  text-align: left;
}
.sheet-radio {
  width: 22px;
  height: 22px;
  border-radius: 999px;
  border: 2px solid #cbd5e1;
  display: grid;
  place-items: center;
  flex-shrink: 0;
}
.sheet-radio--active {
  border-color: var(--tenant-primary, #5B21B6);
}
.sheet-radio-inner {
  width: 10px;
  height: 10px;
  border-radius: 999px;
  background: var(--tenant-primary, #5B21B6);
}
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
@keyframes slideUp { from { transform: translateY(100%); } to { transform: translateY(0); } }
</style>
