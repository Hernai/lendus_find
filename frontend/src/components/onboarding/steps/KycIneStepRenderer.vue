<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'
import type { KycIneStep } from '@/types/v2/onboardingStep'
import { platform } from '@/platform'
import { useTenantStore } from '@/stores/tenant'

/**
 * Renderiza step `kyc_ine`: captura frente y reverso del INE.
 *
 * Si el tenant NO tiene proveedor KYC activo (ej. Nubarium), la pantalla
 * también pide los datos personales que normalmente extrae el OCR del INE:
 * nombre, apellidos, fecha de nacimiento, género, CURP, RFC, nacionalidad
 * y estado de nacimiento.
 */

// Solo los IDENTIFICADORES del INE (los que Nubarium extrae por OCR del
// documento). El nombre, apellidos, fecha, género, etc. se piden en el step
// `personal_data`.
interface PersonalFromIne {
  curp?: string
  clave_elector?: string
  numero_ocr?: string
  folio_ine?: string
}

interface KycIneData {
  front_image: string | null
  back_image: string | null
  personal?: PersonalFromIne
}

const props = defineProps<{
  step: KycIneStep
  modelValue: KycIneData | null
}>()

const emit = defineEmits<{
  'update:modelValue': [value: KycIneData]
}>()

const tenantStore = useTenantStore()

const front = ref<string | null>(props.modelValue?.front_image ?? null)
const back = ref<string | null>(props.modelValue?.back_image ?? null)
const personal = ref<PersonalFromIne>({
  curp: props.modelValue?.personal?.curp ?? '',
  clave_elector: props.modelValue?.personal?.clave_elector ?? '',
  numero_ocr: props.modelValue?.personal?.numero_ocr ?? '',
  folio_ine: props.modelValue?.personal?.folio_ine ?? '',
})
const currentSide = ref<'front' | 'back'>('front')
const methodSheetOpen = ref(false)
const loading = ref(false)
const error = ref('')

const hasKycProvider = computed(() => tenantStore.hasKycProvider)

const showPersonalForm = computed(() => !hasKycProvider.value && !!front.value && !!back.value)

const CURP_REGEX = /^[A-Z]{4}\d{6}[HM][A-Z]{5}[A-Z0-9]\d$/
const CLAVE_REGEX = /^[A-Z0-9]{18}$/
const OCR_REGEX = /^\d{13}$/
const FOLIO_REGEX = /^\d{9,20}$/
const isCurpValid = computed(() => CURP_REGEX.test((personal.value.curp || '').toUpperCase()))
const isClaveValid = computed(() => CLAVE_REGEX.test((personal.value.clave_elector || '').toUpperCase()))
const isOcrValid = computed(() => OCR_REGEX.test(personal.value.numero_ocr || ''))
const isFolioValid = computed(() => FOLIO_REGEX.test(personal.value.folio_ine || ''))

function onCurpInput(event: Event) {
  personal.value.curp = (event.target as HTMLInputElement).value.toUpperCase().replace(/\s/g, '')
}
function onClaveInput(event: Event) {
  personal.value.clave_elector = (event.target as HTMLInputElement).value.toUpperCase().replace(/\s/g, '')
}
function onDigitsInput(field: 'numero_ocr' | 'folio_ine', event: Event) {
  personal.value[field] = (event.target as HTMLInputElement).value.replace(/\D/g, '')
}

onMounted(async () => {
  if (!tenantStore.isLoaded) {
    try { await tenantStore.loadConfig() } catch { /* noop */ }
  }
})

const stage = computed<'front' | 'back' | 'done'>(() => {
  if (!front.value) return 'front'
  if (!back.value) return 'back'
  return 'done'
})

const stageInstruction = computed(() => {
  if (stage.value === 'front') return 'Por favor, carga el frente de tu INE vigente'
  if (stage.value === 'back') return 'Ahora carga el reverso de tu INE'
  return '¡Listo! Tu INE se cargó correctamente.'
})

function openMethodSheet() {
  currentSide.value = stage.value === 'back' ? 'back' : 'front'
  methodSheetOpen.value = true
  error.value = ''
}

async function captureFromCamera() {
  methodSheetOpen.value = false
  loading.value = true
  try {
    const result = await platform.camera.capture({ facing: 'environment' })
    if (result) saveImage(`data:${result.mimeType};base64,${result.base64}`)
  } catch (e) {
    error.value = 'No se pudo abrir la cámara.'
  } finally {
    loading.value = false
  }
}

async function captureFromAlbum() {
  methodSheetOpen.value = false
  loading.value = true
  try {
    const result = await platform.camera.pickFromGallery()
    if (result) saveImage(`data:${result.mimeType};base64,${result.base64}`)
  } catch (e) {
    error.value = 'No se pudo abrir la galería.'
  } finally {
    loading.value = false
  }
}

function saveImage(dataUrl: string) {
  if (currentSide.value === 'front') front.value = dataUrl
  else back.value = dataUrl
}

function retake(side: 'front' | 'back') {
  if (side === 'front') front.value = null
  else back.value = null
}

watch([front, back, personal], () => {
  emit('update:modelValue', {
    front_image: front.value,
    back_image: back.value,
    personal: { ...personal.value },
  })
}, { deep: true })
</script>

<template>
  <div class="step-ine">
    <!-- Mini-stepper Frente / Reverso con preview -->
    <div class="side-tracker">
      <div class="side" :class="{ 'side--done': !!front, 'side--current': stage === 'front' }">
        <div class="side-thumb">
          <img v-if="front" :src="front" alt="Frente" />
          <svg v-else viewBox="0 0 24 24" fill="none" class="side-thumb-icon">
            <rect x="3" y="5" width="18" height="14" rx="2" stroke="currentColor" stroke-width="1.6" />
            <circle cx="9" cy="11" r="2.2" stroke="currentColor" stroke-width="1.6" />
            <path d="M6 16c.6-1.4 1.8-2.2 3-2.2s2.4.8 3 2.2M14 10h4M14 13h4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
          </svg>
          <span v-if="front" class="side-check" aria-label="Frente cargado">
            <svg viewBox="0 0 24 24" fill="none">
              <path d="M5 12l5 5L20 7" stroke="white" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
          </span>
        </div>
        <span class="side-label">Frente</span>
        <span class="side-status">{{ front ? 'Cargado' : (stage === 'front' ? 'En curso' : 'Pendiente') }}</span>
      </div>

      <div class="side-connector" :class="{ 'side-connector--done': !!front }" />

      <div class="side" :class="{ 'side--done': !!back, 'side--current': stage === 'back' }">
        <div class="side-thumb">
          <img v-if="back" :src="back" alt="Reverso" />
          <svg v-else viewBox="0 0 24 24" fill="none" class="side-thumb-icon">
            <rect x="3" y="5" width="18" height="14" rx="2" stroke="currentColor" stroke-width="1.6" />
            <path d="M3 9h18M6 14h6M6 17h4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
          </svg>
          <span v-if="back" class="side-check" aria-label="Reverso cargado">
            <svg viewBox="0 0 24 24" fill="none">
              <path d="M5 12l5 5L20 7" stroke="white" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
          </span>
        </div>
        <span class="side-label">Reverso</span>
        <span class="side-status">{{ back ? 'Cargado' : (stage === 'back' ? 'Tu turno' : 'Pendiente') }}</span>
      </div>
    </div>

    <p class="ine-instruction">{{ stageInstruction }}</p>

    <!-- Frame con ilustración INE -->
    <div class="ine-frame">
      <template v-if="stage !== 'done'">
        <button type="button" class="ine-card" :disabled="loading" @click="openMethodSheet">
          <!-- Ilustración FRENTE: foto + textos -->
          <svg v-if="stage === 'front'" class="ine-illustration" viewBox="0 0 280 170" fill="none" aria-hidden="true">
            <rect x="4" y="4" width="272" height="162" rx="14" stroke="currentColor" stroke-width="1.6" stroke-dasharray="6 5" />
            <rect x="20" y="16" width="140" height="6" rx="3" fill="currentColor" opacity="0.25" />
            <rect x="20" y="26" width="100" height="5" rx="2.5" fill="currentColor" opacity="0.15" />
            <rect x="20" y="42" width="60" height="80" rx="4" stroke="currentColor" stroke-width="1.4" fill="white" />
            <circle cx="50" cy="68" r="14" stroke="currentColor" stroke-width="1.5" fill="white" />
            <path d="M30 110c4-12 14-18 20-18s16 6 20 18" stroke="currentColor" stroke-width="1.5" fill="white" stroke-linecap="round" />
            <rect x="90" y="48" width="120" height="6" rx="3" fill="currentColor" opacity="0.2" />
            <rect x="90" y="62" width="100" height="6" rx="3" fill="currentColor" opacity="0.18" />
            <rect x="90" y="76" width="110" height="6" rx="3" fill="currentColor" opacity="0.16" />
            <rect x="90" y="90" width="80" height="6" rx="3" fill="currentColor" opacity="0.14" />
            <g transform="translate(220, 105)" stroke="currentColor" stroke-width="1.2" fill="none" opacity="0.4">
              <path d="M0 18c0-10 4-18 12-18s12 8 12 18" />
              <path d="M4 18c0-7 3-14 8-14s8 7 8 14" />
              <path d="M8 18c0-5 2-10 4-10s4 5 4 10" />
            </g>
          </svg>

          <!-- Ilustración REVERSO: código de barras + MRZ + sin foto -->
          <svg v-else class="ine-illustration" viewBox="0 0 280 170" fill="none" aria-hidden="true">
            <rect x="4" y="4" width="272" height="162" rx="14" stroke="currentColor" stroke-width="1.6" stroke-dasharray="6 5" />
            <!-- Banda superior con sello -->
            <rect x="20" y="18" width="100" height="7" rx="3.5" fill="currentColor" opacity="0.22" />
            <rect x="20" y="30" width="70" height="5" rx="2.5" fill="currentColor" opacity="0.14" />
            <!-- Sello circular tipo INE arriba derecha -->
            <circle cx="240" cy="32" r="16" stroke="currentColor" stroke-width="1.4" opacity="0.55" />
            <path d="M232 32l5 5 11-10" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" opacity="0.7" />
            <!-- Líneas tipo dirección/datos -->
            <rect x="20" y="50" width="180" height="5" rx="2.5" fill="currentColor" opacity="0.18" />
            <rect x="20" y="60" width="150" height="5" rx="2.5" fill="currentColor" opacity="0.15" />
            <rect x="20" y="70" width="170" height="5" rx="2.5" fill="currentColor" opacity="0.13" />
            <!-- Código de barras -->
            <g transform="translate(20, 90)">
              <rect x="0" y="0" width="2" height="34" fill="currentColor" />
              <rect x="4" y="0" width="3" height="34" fill="currentColor" />
              <rect x="10" y="0" width="2" height="34" fill="currentColor" />
              <rect x="14" y="0" width="4" height="34" fill="currentColor" />
              <rect x="20" y="0" width="2" height="34" fill="currentColor" />
              <rect x="24" y="0" width="3" height="34" fill="currentColor" />
              <rect x="29" y="0" width="2" height="34" fill="currentColor" />
              <rect x="33" y="0" width="4" height="34" fill="currentColor" />
              <rect x="39" y="0" width="3" height="34" fill="currentColor" />
              <rect x="44" y="0" width="2" height="34" fill="currentColor" />
              <rect x="48" y="0" width="3" height="34" fill="currentColor" />
              <rect x="53" y="0" width="4" height="34" fill="currentColor" />
              <rect x="59" y="0" width="2" height="34" fill="currentColor" />
              <rect x="63" y="0" width="3" height="34" fill="currentColor" />
              <rect x="68" y="0" width="2" height="34" fill="currentColor" />
              <rect x="72" y="0" width="4" height="34" fill="currentColor" />
              <rect x="78" y="0" width="2" height="34" fill="currentColor" />
              <rect x="82" y="0" width="3" height="34" fill="currentColor" />
              <rect x="87" y="0" width="4" height="34" fill="currentColor" />
              <rect x="93" y="0" width="2" height="34" fill="currentColor" />
            </g>
            <!-- MRZ líneas (los <<<<< del reverso de INE) -->
            <g font-family="monospace" font-size="9" fill="currentColor" opacity="0.55">
              <text x="20" y="140">IDMEX&lt;&lt;&lt;&lt;&lt;&lt;&lt;&lt;&lt;&lt;&lt;&lt;&lt;&lt;&lt;&lt;&lt;&lt;&lt;&lt;&lt;&lt;&lt;&lt;</text>
              <text x="20" y="152">0000000&lt;0000000MEX0000000000000&lt;</text>
            </g>
          </svg>

          <span class="ine-camera-btn" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none">
              <rect x="3" y="7" width="18" height="13" rx="2" stroke="white" stroke-width="2" />
              <circle cx="12" cy="13.5" r="3" stroke="white" stroke-width="2" />
              <path d="M9 7l1.5-2h3L15 7" stroke="white" stroke-width="2" stroke-linejoin="round" />
            </svg>
          </span>
        </button>
        <p class="ine-tip">*Por favor, asegúrate de que la foto sea clara, válida y correcta.</p>
      </template>

      <template v-else>
        <div class="ine-done">
          <div class="done-card">
            <img :src="front!" alt="Frente" />
            <button type="button" class="done-edit" aria-label="Cambiar frente" @click="retake('front')">
              <svg viewBox="0 0 24 24" fill="none"><path d="M4 14v6h6M20 10V4h-6M4 20l16-16" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" /></svg>
            </button>
            <span class="done-label">Frente</span>
          </div>
          <div class="done-card">
            <img :src="back!" alt="Reverso" />
            <button type="button" class="done-edit" aria-label="Cambiar reverso" @click="retake('back')">
              <svg viewBox="0 0 24 24" fill="none"><path d="M4 14v6h6M20 10V4h-6M4 20l16-16" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" /></svg>
            </button>
            <span class="done-label">Reverso</span>
          </div>
        </div>
      </template>
    </div>

    <p v-if="error" class="ine-error">{{ error }}</p>

    <!-- Form embebido: solo los datos que aparecen en el INE (cuando no hay OCR). -->
    <section v-if="showPersonalForm" class="ine-personal">
      <div class="personal-banner">
        <span class="banner-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none">
            <rect x="3" y="5" width="18" height="14" rx="2" stroke="currentColor" stroke-width="1.6" />
            <circle cx="9" cy="11" r="2.2" stroke="currentColor" stroke-width="1.6" />
            <path d="M6 16c.6-1.4 1.8-2.2 3-2.2s2.4.8 3 2.2M14 10h4M14 13h4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
          </svg>
        </span>
        <p>Captura los identificadores tal como aparecen en tu INE.</p>
      </div>

      <div class="field" :class="{ 'field--ok': isCurpValid, 'field--err': (personal.curp || '').length >= 18 && !isCurpValid }">
        <label>CURP</label>
        <input :value="personal.curp" type="text" maxlength="18" placeholder="18 caracteres" @input="onCurpInput($event)" />
        <p v-if="(personal.curp || '').length >= 18 && !isCurpValid" class="err">CURP inválida</p>
      </div>

      <div class="field" :class="{ 'field--ok': isClaveValid, 'field--err': (personal.clave_elector || '').length >= 18 && !isClaveValid }">
        <label>Clave de elector</label>
        <input :value="personal.clave_elector" type="text" maxlength="18" placeholder="18 caracteres (frente del INE)" @input="onClaveInput($event)" />
        <p v-if="(personal.clave_elector || '').length >= 18 && !isClaveValid" class="err">Clave de elector inválida</p>
      </div>

      <div class="grid-2">
        <div class="field" :class="{ 'field--ok': isOcrValid, 'field--err': (personal.numero_ocr || '').length >= 13 && !isOcrValid }">
          <label>Número OCR</label>
          <input :value="personal.numero_ocr" type="text" inputmode="numeric" maxlength="13" placeholder="13 dígitos (reverso)" @input="onDigitsInput('numero_ocr', $event)" />
          <p v-if="(personal.numero_ocr || '').length >= 13 && !isOcrValid" class="err">OCR inválido</p>
        </div>
        <div class="field" :class="{ 'field--ok': isFolioValid, 'field--err': (personal.folio_ine || '').length >= 9 && !isFolioValid }">
          <label>Folio</label>
          <input :value="personal.folio_ine" type="text" inputmode="numeric" maxlength="20" placeholder="Folio del INE" @input="onDigitsInput('folio_ine', $event)" />
          <p v-if="(personal.folio_ine || '').length >= 9 && !isFolioValid" class="err">Folio inválido</p>
        </div>
      </div>
    </section>

    <!-- Bottom sheet método de carga -->
    <Teleport to="body">
      <div v-if="methodSheetOpen" class="sheet-overlay" @click.self="methodSheetOpen = false">
        <div class="sheet">
          <div class="sheet-handle" />
          <header class="sheet-header">
            <h2>Selecciona el método de carga</h2>
            <button type="button" class="sheet-close" @click="methodSheetOpen = false">
              <svg viewBox="0 0 24 24" fill="none"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round" /></svg>
            </button>
          </header>
          <div class="sheet-actions">
            <button type="button" class="method-card" @click="captureFromCamera">
              <span class="method-icon">
                <svg viewBox="0 0 24 24" fill="none">
                  <rect x="3" y="7" width="18" height="13" rx="2" stroke="currentColor" stroke-width="1.6" />
                  <circle cx="12" cy="13.5" r="3" stroke="currentColor" stroke-width="1.6" />
                  <path d="M9 7l1.5-2h3L15 7" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round" />
                </svg>
              </span>
              <span>Tomar foto</span>
            </button>
            <button type="button" class="method-card" @click="captureFromAlbum">
              <span class="method-icon">
                <svg viewBox="0 0 24 24" fill="none">
                  <rect x="3" y="5" width="18" height="14" rx="2" stroke="currentColor" stroke-width="1.6" />
                  <circle cx="9" cy="10" r="2" stroke="currentColor" stroke-width="1.6" />
                  <path d="M21 17l-5-5-7 7" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round" />
                </svg>
              </span>
              <span>Álbum</span>
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<style scoped>
.step-ine {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

/* Mini-stepper Frente / Reverso */
.side-tracker {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 6px 0;
}
.side {
  flex: 0 0 auto;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
  width: 96px;
  text-align: center;
}
.side-thumb {
  position: relative;
  width: 72px;
  height: 56px;
  border-radius: 10px;
  background: rgb(var(--surface-soft-rgb, 243 242 250) / 1);
  display: grid;
  place-items: center;
  overflow: hidden;
  border: 1.5px dashed #cbd5e1;
  color: #94a3b8;
  transition: border-color 160ms ease, color 160ms ease;
}
.side-thumb img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.side-thumb-icon {
  width: 28px;
  height: 28px;
}
.side--current .side-thumb {
  border-color: var(--tenant-primary, #5B21B6);
  border-style: solid;
  color: var(--tenant-primary, #5B21B6);
  box-shadow: 0 0 0 3px rgba(91, 33, 182, 0.12);
}
.side--done .side-thumb {
  border-color: var(--tenant-primary, #5B21B6);
  border-style: solid;
  color: #ffffff;
}
.side-check {
  position: absolute;
  top: 4px;
  right: 4px;
  width: 20px;
  height: 20px;
  border-radius: 999px;
  background: #16a34a;
  display: grid;
  place-items: center;
  box-shadow: 0 0 0 2px #ffffff;
}
.side-check svg {
  width: 12px;
  height: 12px;
}
.side-label {
  font-size: 12.5px;
  font-weight: 700;
  color: #0f172a;
}
.side-status {
  font-size: 11px;
  font-weight: 500;
  color: #64748b;
}
.side--current .side-status { color: var(--tenant-primary, #5B21B6); }
.side--done .side-status { color: #16a34a; }
.side-connector {
  flex: 1;
  max-width: 48px;
  height: 2px;
  background: #e2e8f0;
  border-radius: 2px;
  align-self: center;
  margin-bottom: 28px;
  transition: background 160ms ease;
}
.side-connector--done { background: var(--tenant-primary, #5B21B6); }

.ine-instruction {
  font-size: 14px;
  color: #0f172a;
  margin: 0;
  text-align: center;
  line-height: 1.4;
  font-weight: 500;
}

.ine-frame {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

/* Form embebido — Datos del INE (cuando no hay OCR) */
.ine-personal { display: flex; flex-direction: column; gap: 14px; margin-top: 18px; box-sizing: border-box; }
.ine-personal * { box-sizing: border-box; }
.personal-banner {
  display: flex; align-items: center; gap: 10px;
  padding: 12px 14px;
  background: rgb(var(--surface-soft-rgb, 243 242 250) / 1);
  border-radius: 14px;
}
.personal-banner .banner-icon {
  width: 32px; height: 32px;
  flex-shrink: 0;
  border-radius: 999px;
  background: var(--tenant-primary, #5B21B6);
  color: #ffffff;
  display: grid; place-items: center;
}
.personal-banner .banner-icon svg { width: 18px; height: 18px; }
.personal-banner p { margin: 0; font-size: 13px; color: #0f172a; font-weight: 600; line-height: 1.3; }

.grid-2 {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
  gap: 12px;
}
.field { display: flex; flex-direction: column; gap: 4px; min-width: 0; }
.field label { font-size: 12.5px; color: #475569; font-weight: 600; }
.field input,
.field select {
  width: 100%; min-width: 0;
  padding: 12px 14px;
  border: 1.5px solid #e5e7eb;
  border-radius: 12px;
  font-size: 14px; color: #0f172a;
  background: #ffffff;
  font-family: inherit;
  outline: none;
}
.field input:focus, .field select:focus { border-color: var(--tenant-primary, #5B21B6); }
.field--ok input { border-color: #16a34a; }
.field--err input { border-color: #ef4444; }
.err { margin: 0; font-size: 11.5px; color: #ef4444; }

.date-row {
  display: grid;
  grid-template-columns: 0.8fr 1.4fr 1fr;
  gap: 8px;
}
.date-row select {
  padding: 12px 8px;
  border: 1.5px solid #e5e7eb;
  border-radius: 12px;
  font-size: 14px; color: #0f172a;
  background: #ffffff;
}

.seg-row {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
  gap: 6px;
}
.seg-btn {
  padding: 11px 4px;
  background: #ffffff;
  border: 1.5px solid #e5e7eb;
  border-radius: 12px;
  font-size: 13px; font-weight: 600;
  color: #475569;
  cursor: pointer;
  -webkit-tap-highlight-color: transparent;
  min-width: 0;
  white-space: nowrap;
}
.seg-btn--active {
  background: var(--tenant-primary, #5B21B6);
  border-color: var(--tenant-primary, #5B21B6);
  color: #ffffff;
}

.ine-card {
  width: 100%;
  position: relative;
  background: rgb(var(--surface-soft-rgb, 243 242 250) / 1);
  border-radius: 18px;
  padding: 24px 18px;
  color: var(--tenant-primary, #5B21B6);
  border: none;
  cursor: pointer;
  -webkit-tap-highlight-color: transparent;
  display: grid;
  place-items: center;
  min-height: 200px;
}
.ine-illustration {
  width: 100%;
  max-width: 320px;
  height: auto;
}
.ine-camera-btn {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  width: 64px;
  height: 64px;
  border-radius: 999px;
  background: var(--tenant-primary, #5B21B6);
  display: grid;
  place-items: center;
  box-shadow: 0 8px 24px -8px rgba(0, 0, 0, 0.25);
}
.ine-camera-btn svg {
  width: 28px;
  height: 28px;
}

.ine-tip {
  font-size: 12.5px;
  color: var(--tenant-primary, #5B21B6);
  text-align: center;
  margin: 0;
  font-style: italic;
}

.ine-done {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}
.done-card {
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
}
.done-card img {
  width: 100%;
  aspect-ratio: 4 / 3;
  object-fit: cover;
  border-radius: 12px;
  border: 2px solid var(--tenant-primary, #5B21B6);
}
.done-edit {
  position: absolute;
  top: 6px;
  right: 6px;
  width: 28px;
  height: 28px;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.92);
  border: none;
  display: grid;
  place-items: center;
  cursor: pointer;
  color: var(--tenant-primary, #5B21B6);
}
.done-edit svg {
  width: 14px;
  height: 14px;
}
.done-label {
  font-size: 12px;
  font-weight: 600;
  color: #0f172a;
}

.ine-error {
  color: #ef4444;
  font-size: 13px;
  text-align: center;
  margin: 0;
}

/* Sheet */
.sheet-overlay {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.5);
  display: grid;
  place-items: end center;
  z-index: 60;
}
.sheet {
  background: #ffffff;
  width: 100%;
  max-width: 520px;
  border-radius: 24px 24px 0 0;
  display: flex;
  flex-direction: column;
  padding-bottom: env(safe-area-inset-bottom);
  animation: slideUp 220ms ease;
}
.sheet-handle {
  width: 40px;
  height: 4px;
  background: #e2e8f0;
  border-radius: 999px;
  margin: 10px auto 0;
}
.sheet-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 14px 20px;
}
.sheet-header h2 {
  font-size: 15px;
  font-weight: 700;
  color: #0f172a;
  margin: 0;
}
.sheet-close {
  background: transparent;
  border: none;
  cursor: pointer;
  color: #64748b;
  width: 28px;
  height: 28px;
  display: grid;
  place-items: center;
}
.sheet-close svg {
  width: 18px;
  height: 18px;
}
.sheet-actions {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
  padding: 4px 20px 24px;
}
.method-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 10px;
  padding: 18px 12px;
  background: rgb(var(--surface-soft-rgb, 243 242 250) / 1);
  border: 1.5px solid transparent;
  border-radius: 14px;
  cursor: pointer;
  color: #0f172a;
  font-size: 14px;
  font-weight: 600;
  -webkit-tap-highlight-color: transparent;
  transition: border-color 140ms ease;
}
.method-card:active {
  border-color: var(--tenant-primary, #5B21B6);
}
.method-icon {
  width: 44px;
  height: 44px;
  border-radius: 999px;
  background: #ffffff;
  color: var(--tenant-primary, #5B21B6);
  display: grid;
  place-items: center;
}
.method-icon svg {
  width: 24px;
  height: 24px;
}
@keyframes slideUp { from { transform: translateY(100%); } to { transform: translateY(0); } }
</style>
