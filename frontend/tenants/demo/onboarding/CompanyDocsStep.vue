<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import documentService from '@/services/v2/document.applicant.service'
import { logger } from '@/utils/logger'

/**
 * Paso custom (tenant demo, arrendamiento) SOLO para persona moral
 * (condition: if_company). Sube los documentos LEGALES de la empresa (acta
 * constitutiva, poder del representante, constancia fiscal, ID del representante).
 * Antes era solo un checkbox de acuse; ahora sube los archivos de verdad —la
 * solicitud ya existe en este punto del flujo. modelValue = tipos ya subidos.
 */

const log = logger.child('CompanyDocsStep')

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

const uploaded = ref<Set<string>>(new Set(props.modelValue ?? []))
const uploading = ref<string | null>(null)
const errorMsg = ref('')

const isValid = computed(() => DOCS.filter((d) => d.required).every((d) => uploaded.value.has(d.type)))
watch(isValid, (v) => emit('update:valid', v), { immediate: true })

async function onFile(type: string, ev: Event) {
  const input = ev.target as HTMLInputElement
  const file = input.files?.[0]
  input.value = ''
  if (!file) return
  uploading.value = type
  errorMsg.value = ''
  try {
    await documentService.upload(file, type)
    uploaded.value = new Set([...uploaded.value, type])
    emit('update:modelValue', [...uploaded.value])
  } catch (e) {
    errorMsg.value = 'No se pudo subir el documento. Intenta de nuevo.'
    log.warn('upload company doc failed', { error: e, type })
  } finally {
    uploading.value = null
  }
}
</script>

<template>
  <div class="step-company-docs">
    <h2 v-if="step.label" class="step-section-title">{{ step.label }}</h2>
    <p class="intro">Sube los documentos legales de la empresa (foto o PDF).</p>

    <ul class="docs-list">
      <li v-for="doc in DOCS" :key="doc.type" class="doc-row" :class="{ 'doc-row--done': uploaded.has(doc.type) }">
        <span class="doc-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none">
            <path d="M7 3h7l4 4v14H7z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" />
            <path d="M14 3v4h4" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" />
          </svg>
        </span>
        <span class="doc-label">{{ doc.label }}</span>

        <span v-if="uploaded.has(doc.type)" class="doc-check" aria-label="Subido">
          <svg viewBox="0 0 24 24" fill="none">
            <circle cx="12" cy="12" r="10" fill="#10b981" />
            <path d="M8 12l2.5 2.5L16 9" stroke="white" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" />
          </svg>
        </span>
        <label v-else class="doc-btn" :class="{ 'doc-btn--loading': uploading === doc.type }">
          {{ uploading === doc.type ? 'Subiendo…' : 'Subir' }}
          <input
            type="file"
            accept="image/*,application/pdf"
            class="doc-input"
            :disabled="uploading === doc.type"
            @change="onFile(doc.type, $event)"
          />
        </label>
      </li>
    </ul>

    <p v-if="errorMsg" class="doc-error" role="alert">{{ errorMsg }}</p>
  </div>
</template>

<style scoped>
.step-company-docs { display: flex; flex-direction: column; gap: 14px; }
.step-section-title { font-size: 15px; font-weight: 700; color: #0f172a; margin: 0; }
.intro { font-size: 13.5px; color: #475569; margin: 0; }
.docs-list { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 10px; }
.doc-row {
  display: flex; align-items: center; gap: 12px; padding: 12px 14px;
  background: #fff; border: 1.5px solid #e5e7eb; border-radius: 12px; min-height: 56px;
}
.doc-row--done { border-color: var(--tenant-primary, #5B21B6); background: rgb(var(--surface-soft-rgb, 243 242 250) / 1); }
.doc-icon { width: 22px; height: 22px; color: var(--tenant-primary, #5B21B6); flex-shrink: 0; }
.doc-icon svg { width: 100%; height: 100%; }
.doc-label { flex: 1; font-size: 14px; color: #0f172a; line-height: 1.3; }
.doc-check { width: 24px; height: 24px; flex-shrink: 0; }
.doc-check svg { width: 100%; height: 100%; }
.doc-btn {
  flex-shrink: 0; cursor: pointer; font-size: 13.5px; font-weight: 700;
  color: #fff; background: var(--tenant-primary, #5B21B6);
  padding: 8px 16px; border-radius: 10px; position: relative; overflow: hidden;
}
.doc-btn--loading { opacity: 0.6; cursor: default; }
.doc-input { position: absolute; inset: 0; opacity: 0; cursor: pointer; }
.doc-error { font-size: 13px; color: #b91c1c; background: #fef2f2; border: 1px solid #fecaca; border-radius: 10px; padding: 10px 12px; margin: 0; }
</style>
