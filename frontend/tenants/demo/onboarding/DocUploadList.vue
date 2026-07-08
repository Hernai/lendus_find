<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import documentService from '@/services/v2/document.applicant.service'
import { logger } from '@/utils/logger'

/**
 * Lista de documentos con subida vía BOTTOM SHEET (compartida por DocumentsStep y
 * CompanyDocsStep del tenant demo). Al tocar un documento se abre un panel desde
 * abajo —como en la carga del INE (KycIneStepRenderer)— con dos opciones: "Tomar
 * foto" (cámara) o "Subir archivo" (galería/PDF). Componente controlado:
 * modelValue = tipos ya subidos; emite el arreglo actualizado.
 */

const log = logger.child('DocUploadList')

interface DocItem {
  type: string
  label: string
  required: boolean
}

const props = defineProps<{
  docs: DocItem[]
  modelValue: string[] | null
  intro?: string
}>()

const emit = defineEmits<{
  'update:modelValue': [value: string[]]
}>()

const uploaded = ref<Set<string>>(new Set(props.modelValue ?? []))
watch(
  () => props.modelValue,
  (v) => { uploaded.value = new Set(v ?? []) },
)

const uploading = ref<string | null>(null)
const errorMsg = ref('')

// Tipo de documento cuyo panel de método está abierto (null = cerrado).
const sheetFor = ref<string | null>(null)
const sheetLabel = computed(
  () => props.docs.find((d) => d.type === sheetFor.value)?.label ?? 'Subir documento',
)

function openSheet(type: string) {
  if (uploading.value) return
  errorMsg.value = ''
  sheetFor.value = type
}

async function onSheetFile(ev: Event) {
  const type = sheetFor.value
  const input = ev.target as HTMLInputElement
  const file = input.files?.[0] ?? null
  input.value = '' // permite re-seleccionar el mismo archivo
  sheetFor.value = null // cerramos el panel; el estado "subiendo" se ve en la fila
  if (!type || !file) return
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
  <div class="doc-upload">
    <p v-if="docs.length" class="doc-intro">{{ intro ?? 'Sube los documentos requeridos (foto o PDF).' }}</p>
    <p v-else class="doc-intro">No hay documentos por subir en este paso.</p>

    <ul v-if="docs.length" class="docs-list">
      <li v-for="doc in docs" :key="doc.type" class="doc-row" :class="{ 'doc-row--done': uploaded.has(doc.type) }">
        <button type="button" class="doc-trigger" :disabled="uploading === doc.type" @click="openSheet(doc.type)">
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
          <!-- Estado a la derecha: palomita si subido, spinner si subiendo, chevron si pendiente. -->
          <span v-if="uploaded.has(doc.type)" class="doc-check" aria-label="Subido">
            <svg viewBox="0 0 24 24" fill="none">
              <circle cx="12" cy="12" r="10" fill="#10b981" />
              <path d="M8 12l2.5 2.5L16 9" stroke="white" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
          </span>
          <span v-else-if="uploading === doc.type" class="doc-spin" aria-label="Subiendo" />
          <svg v-else class="doc-chevron" viewBox="0 0 24 24" fill="none">
            <path d="M9 6l6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
          </svg>
        </button>
      </li>
    </ul>

    <p v-if="errorMsg" class="doc-error" role="alert">{{ errorMsg }}</p>

    <!-- Panel de método (aparece DESDE ABAJO), igual que la carga del INE. -->
    <Teleport to="body">
      <div v-if="sheetFor" class="sheet-overlay" @click.self="sheetFor = null">
        <div class="sheet">
          <div class="sheet-handle" />
          <header class="sheet-header">
            <h2>{{ sheetLabel }}</h2>
            <button type="button" class="sheet-close" aria-label="Cerrar" @click="sheetFor = null">
              <svg viewBox="0 0 24 24" fill="none"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round" /></svg>
            </button>
          </header>
          <div class="sheet-actions">
            <label class="method-card">
              <span class="method-icon">
                <svg viewBox="0 0 24 24" fill="none">
                  <path d="M4 8h3l1.5-2h7L17 8h3v11H4z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round" />
                  <circle cx="12" cy="13" r="3.2" stroke="currentColor" stroke-width="1.7" />
                </svg>
              </span>
              <span>Tomar foto</span>
              <input type="file" accept="image/*" capture="environment" class="sheet-input" @change="onSheetFile($event)" />
            </label>
            <label class="method-card">
              <span class="method-icon">
                <svg viewBox="0 0 24 24" fill="none">
                  <path d="M12 15V4M8 8l4-4 4 4M5 19h14" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
              </span>
              <span>Subir archivo</span>
              <input type="file" accept="image/*,application/pdf" class="sheet-input" @change="onSheetFile($event)" />
            </label>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<style scoped>
.doc-upload { display: flex; flex-direction: column; gap: 14px; }
.doc-intro { font-size: 13.5px; color: #475569; margin: 0; }
.docs-list { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 10px; }
.doc-row {
  background: #fff; border: 1.5px solid #e5e7eb; border-radius: 12px; overflow: hidden;
}
.doc-row--done { border-color: var(--tenant-primary, #5B21B6); background: rgb(var(--surface-soft-rgb, 243 242 250) / 1); }
.doc-trigger {
  width: 100%; display: flex; align-items: center; gap: 12px; padding: 14px;
  background: transparent; border: none; cursor: pointer; text-align: left;
  min-height: 56px; -webkit-tap-highlight-color: transparent; font: inherit; color: inherit;
}
.doc-trigger:disabled { cursor: default; opacity: 0.7; }
.doc-icon { width: 22px; height: 22px; color: var(--tenant-primary, #5B21B6); flex-shrink: 0; }
.doc-icon svg { width: 100%; height: 100%; }
.doc-label { flex: 1; font-size: 14px; color: #0f172a; line-height: 1.3; }
.doc-optional { font-size: 12px; color: #94a3b8; }
.doc-check { width: 24px; height: 24px; flex-shrink: 0; }
.doc-check svg { width: 100%; height: 100%; }
.doc-chevron { width: 20px; height: 20px; color: #cbd5e1; flex-shrink: 0; }
.doc-spin {
  width: 20px; height: 20px; flex-shrink: 0; border-radius: 999px;
  border: 2.5px solid #e2e8f0; border-top-color: var(--tenant-primary, #5B21B6);
  animation: spin 720ms linear infinite;
}
.doc-error { font-size: 13px; color: #b91c1c; background: #fef2f2; border: 1px solid #fecaca; border-radius: 10px; padding: 10px 12px; margin: 0; }

/* Bottom sheet (mismo patrón que KycIneStepRenderer) */
.sheet-overlay {
  position: fixed; inset: 0; background: rgba(15, 23, 42, 0.5);
  display: grid; place-items: end center; z-index: 1000;
}
.sheet {
  background: #fff; width: 100%; max-width: 520px; border-radius: 24px 24px 0 0;
  display: flex; flex-direction: column; padding-bottom: env(safe-area-inset-bottom);
  animation: slideUp 220ms ease;
}
.sheet-handle { width: 40px; height: 4px; background: #e2e8f0; border-radius: 999px; margin: 10px auto 0; }
.sheet-header { display: flex; justify-content: space-between; align-items: center; padding: 14px 20px; }
.sheet-header h2 { font-size: 15px; font-weight: 700; color: #0f172a; margin: 0; }
.sheet-close { background: transparent; border: none; cursor: pointer; color: #64748b; width: 28px; height: 28px; display: grid; place-items: center; }
.sheet-close svg { width: 18px; height: 18px; }
.sheet-actions { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; padding: 4px 20px 24px; }
.method-card {
  display: flex; flex-direction: column; align-items: center; gap: 10px; padding: 18px 12px;
  background: rgb(var(--surface-soft-rgb, 243 242 250) / 1); border: 1.5px solid transparent;
  border-radius: 14px; cursor: pointer; color: #0f172a; font-size: 14px; font-weight: 600;
  -webkit-tap-highlight-color: transparent; transition: border-color 140ms ease; position: relative; overflow: hidden;
}
.method-card:active { border-color: var(--tenant-primary, #5B21B6); }
.method-icon { width: 44px; height: 44px; border-radius: 999px; background: #fff; color: var(--tenant-primary, #5B21B6); display: grid; place-items: center; }
.method-icon svg { width: 24px; height: 24px; }
.sheet-input { position: absolute; inset: 0; opacity: 0; cursor: pointer; }
@keyframes slideUp { from { transform: translateY(100%); } to { transform: translateY(0); } }
@keyframes spin { to { transform: rotate(360deg); } }
</style>
