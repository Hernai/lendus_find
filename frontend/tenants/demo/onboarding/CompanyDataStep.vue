<script setup lang="ts">
import { computed, watch } from 'vue'
import { isPersonaMoral } from '@/utils/validators'

/**
 * Paso custom (tenant demo, arrendamiento) SOLO para persona moral
 * (condition: if_company). Captura los datos de la empresa; el resultado va a
 * `applications.metadata.company`. La Person del onboarding representa al
 * representante legal.
 */

interface CompanyValue {
  legal_name: string
  rfc: string
  legal_entity_type: string
  incorporation_date: string
}

const props = defineProps<{
  step: { id: string; label?: string }
  modelValue: CompanyValue | null
}>()

const emit = defineEmits<{
  'update:modelValue': [value: CompanyValue]
  'update:valid': [valid: boolean]
}>()

const ENTITY_TYPES = [
  'S.A. de C.V.',
  'S. de R.L. de C.V.',
  'S.A.P.I. de C.V.',
  'S.C.',
  'A.C.',
  'SOFOM E.N.R.',
  'Otra',
]

const current = computed<CompanyValue>(() => ({
  legal_name: props.modelValue?.legal_name ?? '',
  rfc: props.modelValue?.rfc ?? '',
  legal_entity_type: props.modelValue?.legal_entity_type ?? '',
  incorporation_date: props.modelValue?.incorporation_date ?? '',
}))

const rfcValid = computed(() => isPersonaMoral(current.value.rfc.trim().toUpperCase()))
const rfcTouched = computed(() => current.value.rfc.trim().length > 0)

const isValid = computed(() => current.value.legal_name.trim().length >= 3 && rfcValid.value)
watch(isValid, (v) => emit('update:valid', v), { immediate: true })

function update(patch: Partial<CompanyValue>) {
  emit('update:modelValue', { ...current.value, ...patch })
}
</script>

<template>
  <div class="step-company">
    <h2 v-if="step.label" class="step-section-title">{{ step.label }}</h2>

    <label class="field">
      <span class="field-label">Razón social *</span>
      <input
        class="field-input"
        type="text"
        :value="current.legal_name"
        placeholder="Ej. Energía Solar del Norte S.A. de C.V."
        @input="update({ legal_name: ($event.target as HTMLInputElement).value })"
      />
    </label>

    <label class="field">
      <span class="field-label">RFC de la empresa *</span>
      <input
        class="field-input"
        :class="{ 'field-input--error': rfcTouched && !rfcValid }"
        type="text"
        maxlength="12"
        :value="current.rfc"
        placeholder="12 caracteres (persona moral)"
        @input="update({ rfc: ($event.target as HTMLInputElement).value.toUpperCase() })"
      />
      <span v-if="rfcTouched && !rfcValid" class="field-error">RFC de persona moral inválido (12 caracteres).</span>
    </label>

    <label class="field">
      <span class="field-label">Tipo de sociedad</span>
      <select
        class="field-input"
        :value="current.legal_entity_type"
        @change="update({ legal_entity_type: ($event.target as HTMLSelectElement).value })"
      >
        <option value="">Selecciona…</option>
        <option v-for="t in ENTITY_TYPES" :key="t" :value="t">{{ t }}</option>
      </select>
    </label>

    <label class="field">
      <span class="field-label">Fecha de constitución</span>
      <input
        class="field-input"
        type="date"
        :value="current.incorporation_date"
        @input="update({ incorporation_date: ($event.target as HTMLInputElement).value })"
      />
    </label>
  </div>
</template>

<style scoped>
.step-company { display: flex; flex-direction: column; gap: 14px; }
.step-section-title { font-size: 15px; font-weight: 700; color: #0f172a; margin: 0; }
.field { display: flex; flex-direction: column; gap: 6px; }
.field-label { font-size: 13px; font-weight: 600; color: #334155; }
.field-input {
  border: 1.5px solid #e5e7eb; border-radius: 12px; padding: 12px 14px; font-size: 14.5px;
  color: #0f172a; background: #fff;
}
.field-input:focus { outline: none; border-color: var(--tenant-primary, #5B21B6); }
.field-input--error { border-color: #dc2626; }
.field-error { font-size: 12px; color: #dc2626; }
</style>
