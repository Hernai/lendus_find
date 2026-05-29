<script setup lang="ts">
import { computed } from 'vue'
import { useTenantStore } from '@/stores/tenant'
import type { SelectStep } from '@/types/v2/onboardingStep'

/**
 * Renderiza un step tipo `select` con iconos por opción.
 *
 * Layout pill-card: cada opción es una fila con icono morado lavanda
 * a la izquierda + label + radio circular a la derecha. Al seleccionar,
 * la fila toma borde y fondo color tenant.
 *
 * Los iconos se eligen por `step.enum` + valor (ej. EducationLevel +
 * PRIMARIA → libro abierto). Componente tenant-agnóstico: color via
 * `var(--tenant-primary)` y `rgb(var(--primary-N-rgb))`.
 */

const props = defineProps<{
  step: SelectStep
  modelValue: string | number | null
}>()

const emit = defineEmits<{
  'update:modelValue': [value: string]
}>()

const tenantStore = useTenantStore()

interface Option { value: string | number; label: string }

const options = computed<Option[]>(() => {
  const key = props.step.enum.charAt(0).toLowerCase() + props.step.enum.slice(1)
  const list = (tenantStore.options as Record<string, Option[]>)[key] ?? []
  return list
})

/**
 * Devuelve el path SVG del icono apropiado para una opción dada el
 * enum del step. Se basa en pattern matching de la value (case-insensitive).
 * Para enums no mapeados se usa un icono genérico.
 */
function iconFor(enumName: string, value: string | number): string {
  const v = String(value).toUpperCase()

  if (enumName === 'EducationLevel') {
    if (v.includes('NIN') || v === 'NONE' || v === 'NO_EDUCATION' || v === 'SIN_EDUCACION') return 'cap'
    if (v.includes('PRIMARI')) return 'book'
    if (v.includes('SECUNDARI')) return 'book-open'
    if (v.includes('LICEN') || v.includes('TECNIC')) return 'briefcase'
    if (v.includes('UNIVERS') || v.includes('POLITEC')) return 'building'
    if (v.includes('POSGRAD') || v.includes('MASTER') || v.includes('DOCTOR') || v.includes('PROFESIONAL')) return 'medal'
    return 'cap'
  }

  if (enumName === 'MaritalStatus') {
    if (v.includes('SOLTER')) return 'user'
    if (v.includes('CASAD')) return 'users'
    if (v.includes('DIVOR')) return 'user'
    if (v.includes('VIUD')) return 'user'
    if (v.includes('UNION')) return 'users'
    return 'user'
  }

  if (enumName === 'EmploymentType') {
    if (v.includes('EMPLEAD') || v.includes('EMPLOYEE')) return 'briefcase'
    if (v.includes('NEGOC') || v.includes('CUENTA') || v.includes('AUTONOM') || v.includes('SELF')) return 'user'
    if (v.includes('PENSION') || v.includes('JUBIL')) return 'medal'
    if (v.includes('HOGAR') || v.includes('AMA_DE_CASA') || v.includes('HOME')) return 'home'
    if (v.includes('ESTUDIANT') || v.includes('STUDENT')) return 'cap'
    if (v.includes('PROPIO') || v.includes('OWNER')) return 'building'
    if (v.includes('FREELANC') || v.includes('FREE')) return 'user'
    if (v.includes('DESEMPLE') || v.includes('UNEMPLOY')) return 'user'
    return 'briefcase'
  }

  if (enumName === 'SalaryRange' || enumName === 'IncomeRange') {
    return 'wallet'
  }

  return 'circle'
}

function pick(value: string | number) {
  emit('update:modelValue', String(value))
}
</script>

<template>
  <div class="step-select">
    <h2 v-if="step.label" class="step-section-title">{{ step.label }}</h2>
    <ul class="options-list">
      <li v-for="opt in options" :key="opt.value">
        <button
          type="button"
          class="option-row"
          :class="{ 'option-row--active': String(modelValue) === String(opt.value) }"
          @click="pick(opt.value)"
        >
          <span class="option-icon" aria-hidden="true">
            <!-- cap (graduación) -->
            <svg v-if="iconFor(step.enum, opt.value) === 'cap'" viewBox="0 0 24 24" fill="none">
              <path d="M3 9l9-4 9 4-9 4-9-4z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" />
              <path d="M6 11v4c0 1 2.5 2.5 6 2.5s6-1.5 6-2.5v-4" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" />
              <path d="M21 9v5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
            </svg>
            <!-- book (cerrado) -->
            <svg v-else-if="iconFor(step.enum, opt.value) === 'book'" viewBox="0 0 24 24" fill="none">
              <path d="M5 4h11a3 3 0 013 3v13H8a3 3 0 01-3-3V4z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" />
              <path d="M5 17a3 3 0 013-3h11" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" />
            </svg>
            <!-- book-open -->
            <svg v-else-if="iconFor(step.enum, opt.value) === 'book-open'" viewBox="0 0 24 24" fill="none">
              <path d="M3 5h7a2 2 0 012 2v13a2 2 0 00-2-2H3V5z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" />
              <path d="M21 5h-7a2 2 0 00-2 2v13a2 2 0 012-2h7V5z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" />
            </svg>
            <!-- briefcase -->
            <svg v-else-if="iconFor(step.enum, opt.value) === 'briefcase'" viewBox="0 0 24 24" fill="none">
              <rect x="3" y="7" width="18" height="13" rx="2" stroke="currentColor" stroke-width="1.8" />
              <path d="M9 7V5a2 2 0 012-2h2a2 2 0 012 2v2" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" />
              <path d="M3 13h18" stroke="currentColor" stroke-width="1.8" />
            </svg>
            <!-- building -->
            <svg v-else-if="iconFor(step.enum, opt.value) === 'building'" viewBox="0 0 24 24" fill="none">
              <path d="M4 21V8l8-4 8 4v13" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" />
              <path d="M3 21h18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
              <path d="M10 21v-6h4v6" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" />
              <path d="M8 10h.01M16 10h.01M8 13h.01M16 13h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
            </svg>
            <!-- medal -->
            <svg v-else-if="iconFor(step.enum, opt.value) === 'medal'" viewBox="0 0 24 24" fill="none">
              <circle cx="12" cy="15" r="5" stroke="currentColor" stroke-width="1.8" />
              <path d="M8 11l-2-7h4l2 4M16 11l2-7h-4l-2 4" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" />
            </svg>
            <!-- user -->
            <svg v-else-if="iconFor(step.enum, opt.value) === 'user'" viewBox="0 0 24 24" fill="none">
              <circle cx="12" cy="8" r="4" stroke="currentColor" stroke-width="1.8" />
              <path d="M4 21c0-4 4-6 8-6s8 2 8 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
            </svg>
            <!-- users (pareja) -->
            <svg v-else-if="iconFor(step.enum, opt.value) === 'users'" viewBox="0 0 24 24" fill="none">
              <circle cx="9" cy="9" r="3.5" stroke="currentColor" stroke-width="1.8" />
              <circle cx="17" cy="11" r="2.5" stroke="currentColor" stroke-width="1.8" />
              <path d="M2 20c0-3 3-5 7-5s7 2 7 5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
              <path d="M16 16c2 0 6 1 6 4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
            </svg>
            <!-- home -->
            <svg v-else-if="iconFor(step.enum, opt.value) === 'home'" viewBox="0 0 24 24" fill="none">
              <path d="M3 11l9-7 9 7v9a2 2 0 01-2 2H5a2 2 0 01-2-2v-9z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" />
              <path d="M9 22V14h6v8" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" />
            </svg>
            <!-- wallet -->
            <svg v-else-if="iconFor(step.enum, opt.value) === 'wallet'" viewBox="0 0 24 24" fill="none">
              <rect x="3" y="6" width="18" height="13" rx="2" stroke="currentColor" stroke-width="1.8" />
              <path d="M3 10h18" stroke="currentColor" stroke-width="1.8" />
              <circle cx="17" cy="14.5" r="1.2" fill="currentColor" />
            </svg>
            <!-- fallback circle -->
            <svg v-else viewBox="0 0 24 24" fill="none">
              <circle cx="12" cy="12" r="8" stroke="currentColor" stroke-width="1.8" />
            </svg>
          </span>
          <span class="option-label">{{ opt.label }}</span>
          <span class="option-radio" :class="{ 'option-radio--active': String(modelValue) === String(opt.value) }">
            <svg v-if="String(modelValue) === String(opt.value)" viewBox="0 0 24 24" fill="none">
              <path d="M5 12l5 5L20 7" stroke="white" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
          </span>
        </button>
      </li>
    </ul>
  </div>
</template>

<style scoped>
.step-select {
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.step-section-title {
  font-size: 15px;
  font-weight: 700;
  color: #0f172a;
  margin: 0;
}
.options-list {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.option-row {
  width: 100%;
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px 16px;
  background: #ffffff;
  border: 1.5px solid #e5e7eb;
  border-radius: 14px;
  cursor: pointer;
  transition: border-color 140ms ease, background 140ms ease;
  -webkit-tap-highlight-color: transparent;
  min-height: 56px;
}
.option-row:active {
  transform: translateY(0.5px);
}
.option-row--active {
  border-color: var(--tenant-primary, #5B21B6);
  background: rgb(var(--surface-soft-rgb, 243 242 250) / 1);
}
.option-icon {
  width: 24px;
  height: 24px;
  color: var(--tenant-primary, #5B21B6);
  display: grid;
  place-items: center;
  flex-shrink: 0;
}
.option-icon svg {
  width: 100%;
  height: 100%;
}
.option-label {
  flex: 1;
  text-align: left;
  font-size: 14.5px;
  font-weight: 600;
  color: #0f172a;
}
.option-row--active .option-label {
  color: var(--tenant-primary, #5B21B6);
}
.option-radio {
  width: 22px;
  height: 22px;
  border-radius: 999px;
  border: 2px solid #cbd5e1;
  display: grid;
  place-items: center;
  flex-shrink: 0;
  transition: background 140ms ease, border-color 140ms ease;
}
.option-radio--active {
  background: var(--tenant-primary, #5B21B6);
  border-color: var(--tenant-primary, #5B21B6);
}
.option-radio svg {
  width: 12px;
  height: 12px;
}
</style>
