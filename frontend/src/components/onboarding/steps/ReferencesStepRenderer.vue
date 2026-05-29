<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import type { ReferencesStep } from '@/types/v2/onboardingStep'
import { formatPhoneInput } from '@/utils/formatters'

/**
 * Renderiza step `references`: usuario agrega referencias familiar y personal.
 *
 * Layout: dos secciones (familiar + personal), cada una con badge contador
 * (ej. "1 de 2"), inputs de nombre y teléfono con icono morado a la izquierda
 * y check verde a la derecha cuando son válidos.
 *
 * Tenant-agnóstico. Valida que cada referencia tenga nombre (≥3) y teléfono
 * de 10 dígitos.
 */

interface Reference {
  type: 'FAMILY' | 'PERSONAL'
  name: string
  phone: string
}

const props = defineProps<{
  step: ReferencesStep
  modelValue: Reference[] | null
}>()

const emit = defineEmits<{
  'update:modelValue': [value: Reference[]]
}>()

// Estructura inicial: 1 familiar + 1 personal
const family = ref<Reference>(
  props.modelValue?.find((r) => r.type === 'FAMILY') ?? { type: 'FAMILY', name: '', phone: '' },
)
const personal = ref<Reference>(
  props.modelValue?.find((r) => r.type === 'PERSONAL') ?? { type: 'PERSONAL', name: '', phone: '' },
)

// Requiere nombre Y apellido (≥2 palabras), porque el backend exige
// first_name y last_name_1 por separado.
const validName = (n: string) => {
  const parts = n.trim().split(/\s+/).filter(Boolean)
  return parts.length >= 2 && parts.every((p) => p.length >= 2)
}
const validPhone = (p: string) => p.replace(/\D/g, '').length === 10

const familyValid = computed(() => validName(family.value.name) && validPhone(family.value.phone))
const personalValid = computed(() => validName(personal.value.name) && validPhone(personal.value.phone))

function handlePhoneInput(ref: Reference, ev: Event) {
  const input = ev.target as HTMLInputElement
  const raw = input.value
  const caretBefore = input.selectionStart ?? raw.length
  const digitsBeforeCaret = raw.slice(0, caretBefore).replace(/\D/g, '').length
  const formatted = formatPhoneInput(raw)
  ref.phone = formatted
  if (input.value !== formatted) {
    input.value = formatted
  }
  let pos = 0
  let counted = 0
  while (pos < formatted.length && counted < digitsBeforeCaret) {
    if (/\d/.test(formatted.charAt(pos))) counted++
    pos++
  }
  try { input.setSelectionRange(pos, pos) } catch { /* noop */ }
}

watch(
  [family, personal],
  () => {
    emit('update:modelValue', [family.value, personal.value])
  },
  { deep: true },
)
</script>

<template>
  <div class="step-references">
    <p class="step-hint">
      Agrega 2 referencias para completar tu solicitud. Solo se usarán para contactarte si es necesario.
    </p>

    <!-- Referencia familiar -->
    <section class="ref-section">
      <header class="ref-header">
        <span class="ref-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none">
            <circle cx="9" cy="9" r="3.5" stroke="currentColor" stroke-width="1.6" />
            <circle cx="17" cy="11" r="2.5" stroke="currentColor" stroke-width="1.6" />
            <path d="M2 20c0-3 3-5 7-5s7 2 7 5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
            <path d="M16 16c2 0 6 1 6 4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
          </svg>
        </span>
        <h3>Referencia familiar</h3>
        <span class="ref-count">1 de 1</span>
      </header>

      <label class="field-label">Nombre y apellido</label>
      <div class="field" :class="{ 'field--valid': validName(family.name) }">
        <span class="field-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none">
            <circle cx="12" cy="8" r="4" stroke="currentColor" stroke-width="1.6" />
            <path d="M4 21c0-4 4-6 8-6s8 2 8 6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
          </svg>
        </span>
        <input v-model="family.name" type="text" placeholder="Ej. María López" class="field-input" />
        <svg v-if="validName(family.name)" class="field-check" viewBox="0 0 24 24" fill="none">
          <circle cx="12" cy="12" r="10" fill="#10b981" />
          <path d="M8 12l2.5 2.5L16 9" stroke="white" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
      </div>

      <label class="field-label">Teléfono</label>
      <div class="field" :class="{ 'field--valid': validPhone(family.phone) }">
        <span class="field-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none">
            <rect x="6" y="3" width="12" height="18" rx="3" stroke="currentColor" stroke-width="1.6" />
            <circle cx="12" cy="17.5" r="1" fill="currentColor" />
          </svg>
        </span>
        <span class="field-prefix">+52</span>
        <input
          :value="family.phone"
          type="tel"
          inputmode="numeric"
          placeholder="10 dígitos"
          maxlength="12"
          class="field-input"
          @input="handlePhoneInput(family, $event)"
        />
        <svg v-if="validPhone(family.phone)" class="field-check" viewBox="0 0 24 24" fill="none">
          <circle cx="12" cy="12" r="10" fill="#10b981" />
          <path d="M8 12l2.5 2.5L16 9" stroke="white" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
      </div>
    </section>

    <!-- Referencia personal -->
    <section class="ref-section">
      <header class="ref-header">
        <span class="ref-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none">
            <circle cx="12" cy="8" r="4" stroke="currentColor" stroke-width="1.6" />
            <path d="M4 21c0-4 4-6 8-6s8 2 8 6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
          </svg>
        </span>
        <h3>Referencia personal</h3>
        <span class="ref-count">1 de 1</span>
      </header>

      <label class="field-label">Nombre y apellido</label>
      <div class="field" :class="{ 'field--valid': validName(personal.name) }">
        <span class="field-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none">
            <circle cx="12" cy="8" r="4" stroke="currentColor" stroke-width="1.6" />
            <path d="M4 21c0-4 4-6 8-6s8 2 8 6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
          </svg>
        </span>
        <input v-model="personal.name" type="text" placeholder="Ej. Carlos Ramírez" class="field-input" />
        <svg v-if="validName(personal.name)" class="field-check" viewBox="0 0 24 24" fill="none">
          <circle cx="12" cy="12" r="10" fill="#10b981" />
          <path d="M8 12l2.5 2.5L16 9" stroke="white" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
      </div>

      <label class="field-label">Teléfono</label>
      <div class="field" :class="{ 'field--valid': validPhone(personal.phone) }">
        <span class="field-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none">
            <rect x="6" y="3" width="12" height="18" rx="3" stroke="currentColor" stroke-width="1.6" />
            <circle cx="12" cy="17.5" r="1" fill="currentColor" />
          </svg>
        </span>
        <span class="field-prefix">+52</span>
        <input
          :value="personal.phone"
          type="tel"
          inputmode="numeric"
          placeholder="10 dígitos"
          maxlength="12"
          class="field-input"
          @input="handlePhoneInput(personal, $event)"
        />
        <svg v-if="validPhone(personal.phone)" class="field-check" viewBox="0 0 24 24" fill="none">
          <circle cx="12" cy="12" r="10" fill="#10b981" />
          <path d="M8 12l2.5 2.5L16 9" stroke="white" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
      </div>
    </section>
  </div>
</template>

<style scoped>
.step-references {
  display: flex;
  flex-direction: column;
  gap: 16px;
}
.step-hint {
  font-size: 13px;
  color: #64748b;
  margin: 0;
  line-height: 1.5;
}
.ref-section {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.ref-header {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 4px;
}
.ref-icon {
  width: 32px;
  height: 32px;
  background: rgb(var(--surface-soft-rgb, 243 242 250) / 1);
  color: var(--tenant-primary, #5B21B6);
  border-radius: 999px;
  display: grid;
  place-items: center;
}
.ref-icon svg {
  width: 18px;
  height: 18px;
}
.ref-header h3 {
  flex: 1;
  font-size: 14px;
  font-weight: 700;
  color: var(--tenant-primary, #5B21B6);
  margin: 0;
}
.ref-count {
  font-size: 12px;
  color: #64748b;
  background: #f3f4f6;
  padding: 2px 8px;
  border-radius: 999px;
}

.field-label {
  font-size: 12.5px;
  color: #64748b;
  font-weight: 500;
  margin-top: 6px;
}

.field {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 0 12px 0 14px;
  background: #ffffff;
  border: 1.5px solid #e5e7eb;
  border-radius: 14px;
  min-height: 52px;
  transition: border-color 140ms ease;
}
.field:focus-within {
  border-color: var(--tenant-primary, #5B21B6);
}
.field--valid .field-icon {
  color: var(--tenant-primary, #5B21B6);
}
.field-icon {
  width: 20px;
  height: 20px;
  color: var(--tenant-primary, #5B21B6);
  flex-shrink: 0;
}
.field-icon svg {
  width: 100%;
  height: 100%;
}
.field-prefix {
  font-size: 14.5px;
  color: var(--tenant-primary, #5B21B6);
  font-weight: 600;
  border-right: 1px solid #e5e7eb;
  padding-right: 8px;
}
.field-input {
  flex: 1;
  border: none;
  background: transparent;
  font-size: 14.5px;
  color: #0f172a;
  outline: none;
  padding: 14px 0;
  min-width: 0;
}
.field-input::placeholder {
  color: #9ca3af;
}
.field-check {
  width: 20px;
  height: 20px;
  flex-shrink: 0;
}
</style>
