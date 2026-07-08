<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useApplicationStore } from '@/stores/application'
import documentService from '@/services/v2/document.applicant.service'
import { logger } from '@/utils/logger'

/**
 * Paso custom (tenant demo, arrendamiento): sube los documentos que pide el
 * producto (`required_documents`) que NO captura otro paso. La INE (INE_FRONT/
 * BACK) va en kyc_ine y la selfie en kyc_face, así que aquí quedan comprobante de
 * domicilio, de ingresos, etc. Sube cada archivo al seleccionarlo (la solicitud ya
 * existe en este punto del flujo). modelValue = tipos ya subidos (para validez/resume).
 */

const log = logger.child('DocumentsStep')

const props = defineProps<{
  step: { id: string; label?: string }
  modelValue: string[] | null
}>()

const emit = defineEmits<{
  'update:modelValue': [value: string[]]
  'update:valid': [valid: boolean]
}>()

const applicationStore = useApplicationStore()

// Documentos manejados por OTROS pasos (KYC) — no se piden aquí.
const HANDLED_ELSEWHERE = new Set([
  'INE_FRONT', 'INE_BACK', 'SELFIE', 'FACE_MATCH', 'LIVENESS', 'BIOMETRIC', 'PASSPORT', 'RESIDENCE_CARD',
])

interface DocItem { type: string; label: string; required: boolean }

function extractDocs(raw: unknown): DocItem[] {
  const arr: unknown[] = Array.isArray(raw)
    ? raw
    : (raw && typeof raw === 'object' ? ((raw as Record<string, unknown>).nationals as unknown[]) ?? [] : [])
  return arr
    .map((d): DocItem => {
      if (typeof d === 'string') return { type: d, label: d, required: true }
      const o = (d ?? {}) as { type?: string; description?: string; required?: boolean }
      return { type: o.type ?? '', label: o.description ?? o.type ?? '', required: o.required ?? true }
    })
    .filter((d) => d.type && !HANDLED_ELSEWHERE.has(d.type))
}

const docs = computed<DocItem[]>(() => {
  const prod = applicationStore.selectedProduct as unknown as {
    required_documents?: unknown
    required_docs?: unknown
    rules?: { lease?: { asset_documents?: Array<{ type?: string; label?: string; required?: boolean }> } }
  } | null
  const personal = extractDocs(prod?.required_documents ?? prod?.required_docs)
  // Documentos del BIEN (arrendamiento), configurables por el admin en
  // rules.lease.asset_documents (tipo + label + requerido/opcional).
  const asset = (prod?.rules?.lease?.asset_documents ?? [])
    .map((d): DocItem => ({ type: d.type ?? '', label: d.label ?? d.type ?? '', required: d.required ?? false }))
    .filter((d) => d.type && !HANDLED_ELSEWHERE.has(d.type))
  // Dedupe por tipo (por si un tipo apareciera en ambas listas).
  const seen = new Set<string>()
  return [...personal, ...asset].filter((d) => (seen.has(d.type) ? false : (seen.add(d.type), true)))
})

const uploaded = ref<Set<string>>(new Set(props.modelValue ?? []))
const uploading = ref<string | null>(null)
const errorMsg = ref('')

const requiredTypes = computed(() => docs.value.filter((d) => d.required).map((d) => d.type))
// Válido si no hay docs que pedir aquí, o si todos los requeridos ya se subieron.
const isValid = computed(() => requiredTypes.value.every((t) => uploaded.value.has(t)))
watch(isValid, (v) => emit('update:valid', v), { immediate: true })

async function onFile(type: string, ev: Event) {
  const input = ev.target as HTMLInputElement
  const file = input.files?.[0]
  input.value = '' // permite re-seleccionar el mismo archivo
  if (!file) return
  uploading.value = type
  errorMsg.value = ''
  try {
    await documentService.upload(file, type)
    uploaded.value = new Set([...uploaded.value, type])
    emit('update:modelValue', [...uploaded.value])
  } catch (e) {
    errorMsg.value = 'No se pudo subir el documento. Intenta de nuevo.'
    log.warn('upload doc failed', { error: e, type })
  } finally {
    uploading.value = null
  }
}
</script>

<template>
  <div class="step-documents">
    <h2 v-if="step.label" class="step-section-title">{{ step.label }}</h2>
    <p v-if="docs.length" class="intro">Sube los documentos requeridos (foto o PDF).</p>
    <p v-else class="intro">No hay documentos adicionales por subir en este paso.</p>

    <ul class="docs-list">
      <li v-for="doc in docs" :key="doc.type" class="doc-row" :class="{ 'doc-row--done': uploaded.has(doc.type) }">
        <div class="doc-head">
          <span class="doc-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none">
              <path d="M7 3h7l4 4v14H7z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" />
              <path d="M14 3v4h4" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" />
            </svg>
          </span>
          <span class="doc-label">
            {{ doc.label }}
            <span v-if="!doc.required" class="doc-optional">(opcional)</span>
          </span>
          <span v-if="uploaded.has(doc.type)" class="doc-check" aria-label="Subido">
            <svg viewBox="0 0 24 24" fill="none">
              <circle cx="12" cy="12" r="10" fill="#10b981" />
              <path d="M8 12l2.5 2.5L16 9" stroke="white" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
          </span>
        </div>

        <!-- Dos formas de aportar el documento: cámara (capture en móvil) o archivo. -->
        <div v-if="!uploaded.has(doc.type)" class="doc-actions">
          <label class="doc-btn" :class="{ 'doc-btn--loading': uploading === doc.type }">
            <svg class="doc-btn-icon" viewBox="0 0 24 24" fill="none">
              <path d="M4 8h3l1.5-2h7L17 8h3v11H4z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round" />
              <circle cx="12" cy="13" r="3.2" stroke="currentColor" stroke-width="1.7" />
            </svg>
            {{ uploading === doc.type ? 'Subiendo…' : 'Tomar foto' }}
            <input
              type="file"
              accept="image/*"
              capture="environment"
              class="doc-input"
              :disabled="uploading === doc.type"
              @change="onFile(doc.type, $event)"
            />
          </label>
          <label class="doc-btn doc-btn--ghost" :class="{ 'doc-btn--loading': uploading === doc.type }">
            <svg class="doc-btn-icon" viewBox="0 0 24 24" fill="none">
              <path d="M12 15V4M8 8l4-4 4 4M5 19h14" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
            Subir archivo
            <input
              type="file"
              accept="image/*,application/pdf"
              class="doc-input"
              :disabled="uploading === doc.type"
              @change="onFile(doc.type, $event)"
            />
          </label>
        </div>
      </li>
    </ul>

    <p v-if="errorMsg" class="doc-error" role="alert">{{ errorMsg }}</p>
  </div>
</template>

<style scoped>
.step-documents { display: flex; flex-direction: column; gap: 14px; }
.step-section-title { font-size: 15px; font-weight: 700; color: #0f172a; margin: 0; }
.intro { font-size: 13.5px; color: #475569; margin: 0; }
.docs-list { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 10px; }
.doc-row {
  display: flex; flex-direction: column; gap: 10px; padding: 12px 14px;
  background: #fff; border: 1.5px solid #e5e7eb; border-radius: 12px;
}
.doc-row--done { border-color: var(--tenant-primary, #5B21B6); background: rgb(var(--surface-soft-rgb, 243 242 250) / 1); }
.doc-head { display: flex; align-items: center; gap: 12px; }
.doc-icon { width: 22px; height: 22px; color: var(--tenant-primary, #5B21B6); flex-shrink: 0; }
.doc-icon svg { width: 100%; height: 100%; }
.doc-label { flex: 1; font-size: 14px; color: #0f172a; line-height: 1.3; }
.doc-optional { font-size: 12px; color: #94a3b8; }
.doc-check { width: 24px; height: 24px; flex-shrink: 0; }
.doc-check svg { width: 100%; height: 100%; }
/* Dos botones (Tomar foto / Subir archivo), lado a lado; se acomodan en móvil. */
.doc-actions { display: flex; gap: 8px; }
.doc-btn {
  flex: 1; display: flex; align-items: center; justify-content: center; gap: 6px;
  cursor: pointer; font-size: 13.5px; font-weight: 700; min-height: 44px;
  color: #fff; background: var(--tenant-primary, #5B21B6);
  padding: 10px 12px; border-radius: 10px; position: relative; overflow: hidden;
  border: 1.5px solid var(--tenant-primary, #5B21B6);
}
.doc-btn--ghost { background: #fff; color: var(--tenant-primary, #5B21B6); }
.doc-btn-icon { width: 16px; height: 16px; flex-shrink: 0; }
.doc-btn--loading { opacity: 0.6; cursor: default; }
.doc-input { position: absolute; inset: 0; opacity: 0; cursor: pointer; }
.doc-error { font-size: 13px; color: #b91c1c; background: #fef2f2; border: 1px solid #fecaca; border-radius: 10px; padding: 10px 12px; margin: 0; }
</style>
