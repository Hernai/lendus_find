<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import DocUploadList from './DocUploadList.vue'

/**
 * Paso custom (tenant demo, arrendamiento) SOLO para persona moral
 * (condition: if_company). Sube los documentos LEGALES de la empresa (acta
 * constitutiva, poder del representante, constancia fiscal, ID del representante).
 * La subida (panel desde abajo) vive en DocUploadList. modelValue = tipos ya subidos.
 */

const props = defineProps<{
  step: { id: string; label?: string }
  modelValue: string[] | null
}>()

const emit = defineEmits<{
  'update:modelValue': [value: string[]]
  'update:valid': [valid: boolean]
}>()

const DOCS = [
  { type: 'CONSTITUTIVE_ACT', label: 'Acta constitutiva', required: true },
  { type: 'POWER_OF_ATTORNEY', label: 'Poder del representante legal', required: true },
  { type: 'FISCAL_SITUATION', label: 'Constancia de situación fiscal', required: true },
  { type: 'LEGAL_REP_ID', label: 'Identificación del representante legal', required: true },
]

const uploadedTypes = ref<string[]>([...(props.modelValue ?? [])])

const isValid = computed(() => DOCS.filter((d) => d.required).every((d) => uploadedTypes.value.includes(d.type)))
watch(isValid, (v) => emit('update:valid', v), { immediate: true })

function onUpdate(types: string[]) {
  uploadedTypes.value = types
  emit('update:modelValue', types)
}
</script>

<template>
  <div class="step-company-docs">
    <h2 v-if="step.label" class="step-section-title">{{ step.label }}</h2>
    <DocUploadList
      :docs="DOCS"
      :model-value="uploadedTypes"
      intro="Sube los documentos legales de la empresa (foto o PDF)."
      @update:model-value="onUpdate"
    />
  </div>
</template>

<style scoped>
.step-company-docs { display: flex; flex-direction: column; gap: 14px; }
.step-section-title { font-size: 15px; font-weight: 700; color: #0f172a; margin: 0; }
</style>
