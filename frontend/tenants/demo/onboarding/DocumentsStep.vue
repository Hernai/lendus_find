<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useApplicationStore } from '@/stores/application'
import DocUploadList from './DocUploadList.vue'

/**
 * Paso custom (tenant demo, arrendamiento): sube los documentos que pide el
 * producto (`required_documents`) que NO captura otro paso. La INE (INE_FRONT/
 * BACK) va en kyc_ine y la selfie en kyc_face, así que aquí quedan comprobante de
 * domicilio, de ingresos, etc. La subida (panel desde abajo) vive en DocUploadList.
 * modelValue = tipos ya subidos (para validez/resume).
 */

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

const uploadedTypes = ref<string[]>([...(props.modelValue ?? [])])

const requiredTypes = computed(() => docs.value.filter((d) => d.required).map((d) => d.type))
// Válido si no hay docs requeridos, o si todos los requeridos ya se subieron.
const isValid = computed(() => requiredTypes.value.every((t) => uploadedTypes.value.includes(t)))
watch(isValid, (v) => emit('update:valid', v), { immediate: true })

function onUpdate(types: string[]) {
  uploadedTypes.value = types
  emit('update:modelValue', types)
}
</script>

<template>
  <div class="step-documents">
    <h2 v-if="step.label" class="step-section-title">{{ step.label }}</h2>
    <DocUploadList :docs="docs" :model-value="uploadedTypes" @update:model-value="onUpdate" />
  </div>
</template>

<style scoped>
.step-documents { display: flex; flex-direction: column; gap: 14px; }
.step-section-title { font-size: 15px; font-weight: 700; color: #0f172a; margin: 0; }
</style>
