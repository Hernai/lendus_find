<script setup lang="ts">
import { ref, watch } from 'vue'
import type { KycSelfieStep } from '@/types/v2/onboardingStep'
import { platform } from '@/platform'

/**
 * Renderiza step `kyc_selfie`: captura selfie del aplicante para validación
 * facial. Muestra un overlay de marco circular tipo "coloca tu rostro aquí".
 *
 * Tenant-agnóstico.
 */

const props = defineProps<{
  step: KycSelfieStep
  modelValue: string | null
}>()

const emit = defineEmits<{
  'update:modelValue': [value: string]
}>()

const selfie = ref<string | null>(props.modelValue ?? null)
const loading = ref(false)
const error = ref('')

async function captureSelfie() {
  loading.value = true
  error.value = ''
  try {
    const result = await platform.camera.capture({ facing: 'user', mirror: true })
    if (result) {
      selfie.value = `data:${result.mimeType};base64,${result.base64}`
    }
  } catch (e) {
    error.value = 'No se pudo abrir la cámara frontal.'
  } finally {
    loading.value = false
  }
}

function retake() {
  selfie.value = null
}

watch(selfie, (v) => {
  if (v) emit('update:modelValue', v)
})
</script>

<template>
  <div class="step-selfie">
    <div class="selfie-frame">
      <div v-if="!selfie" class="selfie-placeholder">
        <!-- Frame circular guía -->
        <div class="selfie-circle">
          <svg class="selfie-guide" viewBox="0 0 200 200" fill="none" aria-hidden="true">
            <circle cx="100" cy="100" r="92" stroke="currentColor" stroke-width="2.5" stroke-dasharray="8 6" />
            <!-- corners para el "frame" -->
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

        <button type="button" class="capture-btn" :disabled="loading" @click="captureSelfie">
          <svg viewBox="0 0 24 24" fill="none">
            <rect x="3" y="7" width="18" height="13" rx="2" stroke="white" stroke-width="2" />
            <circle cx="12" cy="13.5" r="3" stroke="white" stroke-width="2" />
            <path d="M9 7l1.5-2h3L15 7" stroke="white" stroke-width="2" stroke-linejoin="round" />
          </svg>
          <span>{{ loading ? 'Abriendo cámara…' : 'Tomar foto' }}</span>
        </button>
      </div>

      <div v-else class="selfie-done">
        <img :src="selfie" alt="Selfie capturada" />
        <button type="button" class="retake-btn" @click="retake">
          <svg viewBox="0 0 24 24" fill="none">
            <path d="M4 14v6h6M20 10V4h-6M4 20l16-16" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
          </svg>
          <span>Tomar otra</span>
        </button>
      </div>
    </div>

    <p v-if="error" class="selfie-error">{{ error }}</p>
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
.selfie-placeholder {
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
}
.selfie-guide {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
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
