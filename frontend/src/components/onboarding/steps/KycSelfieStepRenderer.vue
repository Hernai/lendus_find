<script setup lang="ts">
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue'
import type { KycSelfieStep } from '@/types/v2/onboardingStep'
import { platform } from '@/platform'
import { requestStream, captureFromVideoElement, stopStream } from '@/platform/web/camera.web'

/**
 * Renderiza step `kyc_selfie`: captura la selfie del aplicante para validación
 * facial. La captura es SIEMPRE en vivo — nunca subida de archivo/galería —
 * para impedir que se pase la foto de la INE como selfie (el facematch compararía
 * INE vs INE y aprobaría sin cara real).
 *
 * - Web: preview `<video>` con getUserMedia; se captura el frame en vivo.
 * - Nativo (Capacitor): platform.camera.capture, que abre la cámara (source Camera).
 *
 * Si no hay cámara o se niega el permiso, el paso NO avanza (no hay fallback a
 * galería): sin selfie, isValid=false y el runner bloquea el "Continuar".
 *
 * Tenant-agnóstico.
 */

const props = defineProps<{
  step: KycSelfieStep
  modelValue: string | null
}>()

const emit = defineEmits<{
  'update:modelValue': [value: string]
  'update:valid': [valid: boolean]
}>()

// Validez del paso (contrato): un selfie capturado (string no vacío). Sin selfie
// no se puede continuar → esto bloquea el avance cuando no hay cámara/permiso.
const isValid = computed(() =>
  typeof props.modelValue === 'string' && props.modelValue.length > 0,
)
watch(isValid, (v) => emit('update:valid', v), { immediate: true })

const selfie = ref<string | null>(props.modelValue ?? null)
const isNative = platform.device.isNative()

const loading = ref(false)
const error = ref('')
const permissionDenied = ref(false)

// --- Web: cámara en vivo ---
const videoEl = ref<HTMLVideoElement | null>(null)
const stream = ref<MediaStream | null>(null)
const streaming = ref(false)

async function startCamera() {
  if (isNative || selfie.value) return
  loading.value = true
  error.value = ''
  permissionDenied.value = false
  try {
    stream.value = await requestStream({ facing: 'user' })
    if (videoEl.value) {
      videoEl.value.srcObject = stream.value
      await videoEl.value.play()
    }
    streaming.value = true
  } catch {
    // Sin cámara o permiso denegado. NO hay fallback a galería: el paso queda
    // bloqueado (sin selfie no se puede continuar) hasta que se otorgue.
    permissionDenied.value = true
    error.value = 'Necesitamos acceso a tu cámara para la validación facial. Habilita el permiso y reintenta.'
  } finally {
    loading.value = false
  }
}

function stopCamera() {
  stopStream(stream.value)
  stream.value = null
  streaming.value = false
  if (videoEl.value) videoEl.value.srcObject = null
}

// Web: captura el frame actual del preview en vivo (espejado, cámara frontal).
function captureWeb() {
  if (!videoEl.value) return
  const img = captureFromVideoElement(videoEl.value, { facing: 'user', mirror: true })
  if (img) {
    selfie.value = `data:${img.mimeType};base64,${img.base64}`
    stopCamera()
  }
}

// Nativo: abre la cámara del dispositivo (source Camera, sin galería).
async function captureNative() {
  loading.value = true
  error.value = ''
  try {
    const result = await platform.camera.capture({ facing: 'user', mirror: true })
    if (result) selfie.value = `data:${result.mimeType};base64,${result.base64}`
  } catch {
    error.value = 'No se pudo abrir la cámara frontal.'
  } finally {
    loading.value = false
  }
}

function onCapture() {
  if (isNative) captureNative()
  else captureWeb()
}

function retake() {
  selfie.value = null
  if (!isNative) startCamera()
}

onMounted(() => {
  if (!isNative && !selfie.value) startCamera()
})
onBeforeUnmount(() => stopCamera())

watch(selfie, (v) => {
  if (v) emit('update:modelValue', v)
})
</script>

<template>
  <div class="step-selfie">
    <div class="selfie-frame">
      <!-- 1. Foto ya capturada: confirmar (Continuar) o retomar -->
      <div v-if="selfie" class="selfie-done">
        <img :src="selfie" alt="Selfie capturada" />
        <button type="button" class="retake-btn" @click="retake">
          <svg viewBox="0 0 24 24" fill="none">
            <path d="M4 14v6h6M20 10V4h-6M4 20l16-16" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
          </svg>
          <span>Tomar otra</span>
        </button>
      </div>

      <!-- 2. Sin cámara / permiso denegado (web): bloqueado hasta habilitar -->
      <div v-else-if="permissionDenied" class="selfie-blocked">
        <div class="selfie-circle selfie-circle--blocked">
          <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <rect x="3" y="7" width="18" height="13" rx="2" stroke="currentColor" stroke-width="1.6" />
            <path d="M4 4l16 16" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
          </svg>
        </div>
        <p class="selfie-instruction"><strong>Habilita la cámara para continuar</strong></p>
        <p class="selfie-sub">La validación facial se toma en vivo; no se puede subir una foto.</p>
        <button type="button" class="capture-btn" @click="startCamera">
          <span>Reintentar</span>
        </button>
      </div>

      <!-- 3. Placeholder mientras se abre la cámara / nativo -->
      <div v-else-if="isNative || !streaming" class="selfie-placeholder">
        <div class="selfie-circle">
          <svg class="selfie-guide" viewBox="0 0 200 200" fill="none" aria-hidden="true">
            <circle cx="100" cy="100" r="92" stroke="currentColor" stroke-width="2.5" stroke-dasharray="8 6" />
          </svg>
          <span class="selfie-face" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none">
              <circle cx="12" cy="9" r="4" stroke="currentColor" stroke-width="1.5" />
              <path d="M4 21c1.5-4 5-6 8-6s6.5 2 8 6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
            </svg>
          </span>
        </div>
        <p class="selfie-instruction">
          <strong>Coloca tu rostro dentro del marco</strong><br />
          <span>y toma la foto.</span>
        </p>
        <p class="selfie-sub">Procura que tu rostro se vea completo y con buena iluminación.</p>
        <button type="button" class="capture-btn" :disabled="loading" @click="isNative ? onCapture() : startCamera()">
          <svg viewBox="0 0 24 24" fill="none">
            <rect x="3" y="7" width="18" height="13" rx="2" stroke="white" stroke-width="2" />
            <circle cx="12" cy="13.5" r="3" stroke="white" stroke-width="2" />
            <path d="M9 7l1.5-2h3L15 7" stroke="white" stroke-width="2" stroke-linejoin="round" />
          </svg>
          <span>{{ loading ? 'Abriendo cámara…' : 'Tomar foto' }}</span>
        </button>
      </div>

      <!-- 4. Preview en vivo (web): marco de encuadre sobre el video -->
      <div v-else class="selfie-placeholder">
        <div class="selfie-circle">
          <video ref="videoEl" class="selfie-video" autoplay playsinline muted />
          <svg class="selfie-guide" viewBox="0 0 200 200" fill="none" aria-hidden="true">
            <circle cx="100" cy="100" r="92" stroke="currentColor" stroke-width="2.5" stroke-dasharray="8 6" />
          </svg>
        </div>
        <p class="selfie-instruction">
          <strong>Coloca tu rostro dentro del marco</strong><br />
          <span>y toma la foto.</span>
        </p>
        <p class="selfie-sub">Procura que tu rostro se vea completo y con buena iluminación.</p>
        <button type="button" class="capture-btn" @click="onCapture">
          <svg viewBox="0 0 24 24" fill="none">
            <rect x="3" y="7" width="18" height="13" rx="2" stroke="white" stroke-width="2" />
            <circle cx="12" cy="13.5" r="3" stroke="white" stroke-width="2" />
            <path d="M9 7l1.5-2h3L15 7" stroke="white" stroke-width="2" stroke-linejoin="round" />
          </svg>
          <span>Tomar foto</span>
        </button>
      </div>
    </div>

    <p v-if="error && !permissionDenied" class="selfie-error">{{ error }}</p>
  </div>
</template>

<style scoped>
.step-selfie {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.selfie-frame {
  background: rgb(var(--surface-soft-rgb, 243 242 250) / 1);
  border-radius: 18px;
  padding: 24px 20px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 16px;
}
.selfie-placeholder,
.selfie-blocked {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 16px;
  width: 100%;
}
.selfie-circle {
  position: relative;
  width: 200px;
  height: 200px;
  display: grid;
  place-items: center;
  color: var(--tenant-primary, #5B21B6);
  border-radius: 999px;
  overflow: hidden;
}
.selfie-circle--blocked {
  color: #94a3b8;
  overflow: visible;
}
.selfie-circle--blocked svg {
  width: 72px;
  height: 72px;
}
.selfie-video {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
  transform: scaleX(-1); /* espejo, cámara frontal */
}
.selfie-guide {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  pointer-events: none;
}
.selfie-face {
  width: 80px;
  height: 80px;
  color: var(--tenant-primary, #5B21B6);
  opacity: 0.4;
}
.selfie-face svg {
  width: 100%;
  height: 100%;
}
.selfie-instruction {
  text-align: center;
  font-size: 14px;
  color: #0f172a;
  margin: 0;
  line-height: 1.5;
}
.selfie-instruction strong {
  color: var(--tenant-primary, #5B21B6);
  font-weight: 700;
}
.selfie-sub {
  text-align: center;
  font-size: 12.5px;
  color: #64748b;
  margin: 0;
  line-height: 1.4;
}
.capture-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: var(--tenant-primary, #5B21B6);
  color: #ffffff;
  border: none;
  border-radius: 999px;
  padding: 14px 26px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  -webkit-tap-highlight-color: transparent;
}
.capture-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
.capture-btn svg {
  width: 18px;
  height: 18px;
}

.selfie-done {
  width: 100%;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
}
.selfie-done img {
  width: 200px;
  height: 200px;
  border-radius: 999px;
  object-fit: cover;
  border: 3px solid var(--tenant-primary, #5B21B6);
}
.retake-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: #ffffff;
  color: var(--tenant-primary, #5B21B6);
  border: 1.5px solid var(--tenant-primary, #5B21B6);
  border-radius: 999px;
  padding: 10px 18px;
  font-size: 13.5px;
  font-weight: 600;
  cursor: pointer;
}
.retake-btn svg {
  width: 16px;
  height: 16px;
}

.selfie-error {
  color: #ef4444;
  font-size: 13px;
  text-align: center;
  margin: 0;
}
</style>
