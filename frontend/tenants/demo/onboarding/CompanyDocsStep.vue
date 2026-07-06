<script setup lang="ts">
import { computed, watch } from 'vue'

/**
 * Paso custom (tenant demo, arrendamiento) SOLO para persona moral
 * (condition: if_company). Lista los documentos que la empresa deberá presentar
 * y captura la confirmación del solicitante. La carga real de archivos se hace
 * en el módulo de documentos posterior; aquí solo se reconoce el requisito.
 * modelValue = boolean (confirmado).
 */

const props = defineProps<{
  step: { id: string; label?: string }
  modelValue: boolean | null
}>()

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
  'update:valid': [valid: boolean]
}>()

const DOCS = [
  'Acta constitutiva',
  'Poder notarial del representante legal',
  'RFC / Constancia de situación fiscal de la empresa',
  'Identificación del representante legal',
]

const acknowledged = computed(() => props.modelValue === true)

const isValid = computed(() => acknowledged.value)
watch(isValid, (v) => emit('update:valid', v), { immediate: true })

function toggle() {
  emit('update:modelValue', !acknowledged.value)
}
</script>

<template>
  <div class="step-company-docs">
    <h2 v-if="step.label" class="step-section-title">{{ step.label }}</h2>
    <p class="intro">Para el arrendamiento a nombre de una empresa necesitarás presentar:</p>

    <ul class="docs-list">
      <li v-for="doc in DOCS" :key="doc" class="doc-row">
        <span class="doc-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none">
            <path d="M7 3h7l4 4v14H7z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" />
            <path d="M14 3v4h4" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" />
          </svg>
        </span>
        <span class="doc-label">{{ doc }}</span>
      </li>
    </ul>

    <button
      type="button"
      class="ack-row"
      :class="{ 'ack-row--active': acknowledged }"
      @click="toggle"
    >
      <span class="ack-box" :class="{ 'ack-box--active': acknowledged }">
        <svg v-if="acknowledged" viewBox="0 0 24 24" fill="none">
          <path d="M5 12l5 5L20 7" stroke="white" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
      </span>
      <span class="ack-label">Entiendo que deberé presentar estos documentos.</span>
    </button>
  </div>
</template>

<style scoped>
.step-company-docs { display: flex; flex-direction: column; gap: 14px; }
.step-section-title { font-size: 15px; font-weight: 700; color: #0f172a; margin: 0; }
.intro { font-size: 13.5px; color: #475569; margin: 0; }
.docs-list { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 8px; }
.doc-row {
  display: flex; align-items: center; gap: 12px; padding: 12px 14px;
  background: #fff; border: 1.5px solid #e5e7eb; border-radius: 12px;
}
.doc-icon { width: 22px; height: 22px; color: var(--tenant-primary, #5B21B6); flex-shrink: 0; }
.doc-icon svg { width: 100%; height: 100%; }
.doc-label { font-size: 14px; color: #0f172a; }
.ack-row {
  width: 100%; display: flex; align-items: center; gap: 12px; padding: 14px 16px;
  background: #fff; border: 1.5px solid #e5e7eb; border-radius: 14px; cursor: pointer; text-align: left;
}
.ack-row--active { border-color: var(--tenant-primary, #5B21B6); background: rgb(var(--surface-soft-rgb, 243 242 250) / 1); }
.ack-box { width: 22px; height: 22px; border-radius: 6px; border: 2px solid #cbd5e1; display: grid; place-items: center; flex-shrink: 0; }
.ack-box--active { background: var(--tenant-primary, #5B21B6); border-color: var(--tenant-primary, #5B21B6); }
.ack-box svg { width: 13px; height: 13px; }
.ack-label { font-size: 14px; font-weight: 600; color: #0f172a; }
</style>
