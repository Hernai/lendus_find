<script setup lang="ts">
import { computed, watch } from 'vue'

/**
 * Paso custom (tenant demo, arrendamiento): elige si el solicitante es
 * Persona Física o Empresa (persona moral). El valor ('INDIVIDUAL' | 'COMPANY')
 * dirige la ramificación del flujo vía `condition: if_individual / if_company`
 * (ver useOnboardingSteps) y se persiste como applicant_type de la solicitud.
 */

const props = defineProps<{
  step: { id: string; label?: string }
  modelValue: string | null
}>()

const emit = defineEmits<{
  'update:modelValue': [value: string]
  'update:valid': [valid: boolean]
}>()

const options = [
  { value: 'INDIVIDUAL', label: 'Persona Física', hint: 'Arrendamiento a tu nombre' },
  { value: 'COMPANY', label: 'Empresa', hint: 'Persona moral (razón social, RFC)' },
]

const isValid = computed(() => props.modelValue === 'INDIVIDUAL' || props.modelValue === 'COMPANY')
watch(isValid, (v) => emit('update:valid', v), { immediate: true })

function pick(value: string) {
  emit('update:modelValue', value)
}
</script>

<template>
  <div class="step-applicant-type">
    <h2 v-if="step.label" class="step-section-title">{{ step.label }}</h2>
    <ul class="options-list">
      <li v-for="opt in options" :key="opt.value">
        <button
          type="button"
          class="option-row"
          :class="{ 'option-row--active': modelValue === opt.value }"
          @click="pick(opt.value)"
        >
          <span class="option-icon" aria-hidden="true">
            <svg v-if="opt.value === 'INDIVIDUAL'" viewBox="0 0 24 24" fill="none">
              <circle cx="12" cy="8" r="4" stroke="currentColor" stroke-width="1.8" />
              <path d="M4 21c0-4 4-6 8-6s8 2 8 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
            </svg>
            <svg v-else viewBox="0 0 24 24" fill="none">
              <path d="M4 21V8l8-4 8 4v13" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" />
              <path d="M3 21h18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
              <path d="M10 21v-6h4v6" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" />
              <path d="M8 10h.01M16 10h.01M8 13h.01M16 13h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
            </svg>
          </span>
          <span class="option-body">
            <span class="option-label">{{ opt.label }}</span>
            <span class="option-hint">{{ opt.hint }}</span>
          </span>
          <span class="option-radio" :class="{ 'option-radio--active': modelValue === opt.value }">
            <svg v-if="modelValue === opt.value" viewBox="0 0 24 24" fill="none">
              <path d="M5 12l5 5L20 7" stroke="white" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
          </span>
        </button>
      </li>
    </ul>
  </div>
</template>

<style scoped>
.step-applicant-type { display: flex; flex-direction: column; gap: 12px; }
.step-section-title { font-size: 15px; font-weight: 700; color: #0f172a; margin: 0; }
.options-list { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 10px; }
.option-row {
  width: 100%; display: flex; align-items: center; gap: 12px; padding: 14px 16px;
  background: #fff; border: 1.5px solid #e5e7eb; border-radius: 14px; cursor: pointer;
  transition: border-color 140ms ease, background 140ms ease; min-height: 64px;
}
.option-row--active { border-color: var(--tenant-primary, #5B21B6); background: rgb(var(--surface-soft-rgb, 243 242 250) / 1); }
.option-icon { width: 24px; height: 24px; color: var(--tenant-primary, #5B21B6); display: grid; place-items: center; flex-shrink: 0; }
.option-icon svg { width: 100%; height: 100%; }
.option-body { flex: 1; display: flex; flex-direction: column; text-align: left; }
.option-label { font-size: 14.5px; font-weight: 600; color: #0f172a; }
.option-row--active .option-label { color: var(--tenant-primary, #5B21B6); }
.option-hint { font-size: 12.5px; color: #64748b; }
.option-radio { width: 22px; height: 22px; border-radius: 999px; border: 2px solid #cbd5e1; display: grid; place-items: center; flex-shrink: 0; }
.option-radio--active { background: var(--tenant-primary, #5B21B6); border-color: var(--tenant-primary, #5B21B6); }
.option-radio svg { width: 12px; height: 12px; }
</style>
