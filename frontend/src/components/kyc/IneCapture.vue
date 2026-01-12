<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useDocumentCapture } from '@/composables/useDeviceCapture'
import AppButton from '@/components/common/AppButton.vue'

interface Props {
  /** Whether capturing front or back of INE */
  side: 'front' | 'back'
  /** Current captured image (base64) */
  capturedImage?: string | null
  /** Whether this step is active */
  active?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  capturedImage: null,
  active: true
})

const emit = defineEmits<{
  captured: [image: string]
  retake: []
}>()

// Use the document capture composable
const capture = useDocumentCapture({ maxWidth: 1920, maxHeight: 1080, quality: 0.9 })

// Refs
const videoRef = ref<HTMLVideoElement | null>(null)
const fileInputRef = ref<HTMLInputElement | null>(null)
const showWebcam = ref(false)
const capturedPreview = ref<string | null>(null)

// Labels based on side
const labels = computed(() => ({
  title: props.side === 'front' ? 'Frente de tu INE' : 'Reverso de tu INE',
  instruction: props.side === 'front'
    ? 'Posiciona la parte frontal de tu INE dentro del marco'
    : 'Ahora voltea tu INE y captura el reverso',
  hint: props.side === 'front'
    ? 'Asegúrate de que tu foto y datos sean visibles'
    : 'El código de barras y la firma deben ser legibles'
}))

// Check if already captured
const hasCaptured = computed(() => !!props.capturedImage || !!capturedPreview.value)

/**
 * Handle file input change (mobile native or fallback)
 */
const handleFileInput = async (event: Event) => {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]

  if (!file) return

  const base64 = await capture.processFileInput(file)
  if (base64) {
    capturedPreview.value = `data:image/jpeg;base64,${base64}`
    emit('captured', base64)
  }

  // Reset input for re-capture
  input.value = ''
}

/**
 * Start webcam for desktop capture
 */
const startCamera = async () => {
  if (!videoRef.value) return

  showWebcam.value = true

  // Wait for DOM to update
  await new Promise(resolve => setTimeout(resolve, 100))

  if (videoRef.value) {
    await capture.startWebcam(videoRef.value)
  }
}

/**
 * Take photo from webcam
 */
const takePhoto = async () => {
  if (!videoRef.value) return

  const base64 = await capture.captureFromWebcam(videoRef.value)
  if (base64) {
    capturedPreview.value = `data:image/jpeg;base64,${base64}`
    emit('captured', base64)
    capture.stopWebcam()
    showWebcam.value = false
  }
}

/**
 * Cancel webcam and go back
 */
const cancelWebcam = () => {
  capture.stopWebcam()
  showWebcam.value = false
}

/**
 * Retake photo
 */
const retake = () => {
  capturedPreview.value = null
  emit('retake')
}

/**
 * Trigger native file input (for mobile or fallback)
 */
const triggerFileInput = () => {
  fileInputRef.value?.click()
}

// Cleanup on unmount
onUnmounted(() => {
  capture.stopWebcam()
})
</script>

<template>
  <div class="w-full">
    <!-- Header -->
    <div class="text-center mb-6">
      <h2 class="text-xl font-semibold text-gray-900">{{ labels.title }}</h2>
      <p class="text-gray-600 mt-1">{{ labels.instruction }}</p>
    </div>

    <!-- Preview of captured image -->
    <div v-if="hasCaptured && !showWebcam" class="space-y-4">
      <div class="relative aspect-[1.58] rounded-2xl overflow-hidden bg-gray-100 border-2 border-green-500">
        <img
          :src="capturedImage ? `data:image/jpeg;base64,${capturedImage}` : (capturedPreview ?? undefined)"
          alt="INE capturado"
          class="w-full h-full object-contain"
        />
        <!-- Success badge -->
        <div class="absolute top-3 right-3 bg-green-500 text-white px-3 py-1 rounded-full text-sm font-medium flex items-center gap-1">
          <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
          </svg>
          Capturado
        </div>
      </div>

      <!-- Retake button -->
      <div class="flex justify-center">
        <button
          type="button"
          class="text-primary-600 font-medium flex items-center gap-2 hover:text-primary-700"
          @click="retake"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
          </svg>
          Tomar otra foto
        </button>
      </div>
    </div>

    <!-- Webcam view (desktop) -->
    <div v-else-if="showWebcam" class="space-y-4">
      <div class="relative aspect-[1.58] rounded-2xl overflow-hidden bg-black">
        <!-- Video stream -->
        <video
          ref="videoRef"
          autoplay
          playsinline
          muted
          class="w-full h-full object-cover"
        />

        <!-- Guide overlay -->
        <div class="absolute inset-0 pointer-events-none">
          <!-- Semi-transparent overlay with hole -->
          <svg class="w-full h-full" viewBox="0 0 100 63.29">
            <defs>
              <mask id="hole">
                <rect width="100" height="63.29" fill="white" />
                <rect x="5" y="5" width="90" height="53.29" rx="3" fill="black" />
              </mask>
            </defs>
            <rect width="100" height="63.29" fill="rgba(0,0,0,0.5)" mask="url(#hole)" />
            <rect x="5" y="5" width="90" height="53.29" rx="3" fill="none" stroke="white" stroke-width="0.5" stroke-dasharray="2,2" />
          </svg>

          <!-- Corner guides -->
          <div class="absolute top-4 left-4 w-8 h-8 border-t-2 border-l-2 border-white rounded-tl-lg" />
          <div class="absolute top-4 right-4 w-8 h-8 border-t-2 border-r-2 border-white rounded-tr-lg" />
          <div class="absolute bottom-4 left-4 w-8 h-8 border-b-2 border-l-2 border-white rounded-bl-lg" />
          <div class="absolute bottom-4 right-4 w-8 h-8 border-b-2 border-r-2 border-white rounded-br-lg" />
        </div>

        <!-- Loading overlay -->
        <div v-if="capture.isLoading.value" class="absolute inset-0 bg-black/50 flex items-center justify-center">
          <div class="animate-spin rounded-full h-12 w-12 border-4 border-white border-t-transparent" />
        </div>
      </div>

      <!-- Hint -->
      <p class="text-center text-sm text-gray-500">{{ labels.hint }}</p>

      <!-- Controls -->
      <div class="flex gap-3">
        <AppButton
          variant="secondary"
          class="flex-1"
          @click="cancelWebcam"
        >
          Cancelar
        </AppButton>
        <AppButton
          variant="primary"
          class="flex-1"
          :disabled="capture.isLoading.value"
          @click="takePhoto"
        >
          <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M4 5a2 2 0 00-2 2v8a2 2 0 002 2h12a2 2 0 002-2V7a2 2 0 00-2-2h-1.586a1 1 0 01-.707-.293l-1.121-1.121A2 2 0 0011.172 3H8.828a2 2 0 00-1.414.586L6.293 4.707A1 1 0 015.586 5H4zm6 9a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd" />
          </svg>
          Capturar
        </AppButton>
      </div>
    </div>

    <!-- Capture options (initial state) -->
    <div v-else class="space-y-4">
      <!-- INE illustration/guide -->
      <div class="relative aspect-[1.58] rounded-2xl bg-gray-100 border-2 border-dashed border-gray-300 flex flex-col items-center justify-center p-6">
        <!-- INE placeholder icon -->
        <svg class="w-24 h-16 text-gray-400 mb-4" fill="none" viewBox="0 0 96 64">
          <rect x="1" y="1" width="94" height="62" rx="4" stroke="currentColor" stroke-width="2" />
          <circle cx="28" cy="28" r="12" stroke="currentColor" stroke-width="2" />
          <rect x="48" y="16" width="36" height="4" rx="2" fill="currentColor" opacity="0.5" />
          <rect x="48" y="24" width="28" height="4" rx="2" fill="currentColor" opacity="0.5" />
          <rect x="48" y="32" width="32" height="4" rx="2" fill="currentColor" opacity="0.5" />
          <rect x="12" y="48" width="72" height="6" rx="2" fill="currentColor" opacity="0.3" />
        </svg>

        <p class="text-gray-500 text-center">
          {{ side === 'front' ? 'Toma una foto clara del frente de tu INE' : 'Toma una foto clara del reverso de tu INE' }}
        </p>
      </div>

      <!-- Hint -->
      <p class="text-center text-sm text-gray-500">{{ labels.hint }}</p>

      <!-- Hidden file input -->
      <input
        ref="fileInputRef"
        type="file"
        accept="image/*"
        :capture="capture.isMobile ? 'environment' : undefined"
        class="hidden"
        @change="handleFileInput"
      />

      <!-- Capture buttons -->
      <div class="space-y-3">
        <!-- Primary: Camera button -->
        <AppButton
          v-if="capture.isMobile"
          variant="primary"
          size="lg"
          class="w-full"
          @click="triggerFileInput"
        >
          <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M4 5a2 2 0 00-2 2v8a2 2 0 002 2h12a2 2 0 002-2V7a2 2 0 00-2-2h-1.586a1 1 0 01-.707-.293l-1.121-1.121A2 2 0 0011.172 3H8.828a2 2 0 00-1.414.586L6.293 4.707A1 1 0 015.586 5H4zm6 9a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd" />
          </svg>
          Abrir Cámara
        </AppButton>

        <!-- Desktop: Webcam or upload -->
        <template v-else>
          <AppButton
            v-if="capture.hasWebcam"
            variant="primary"
            size="lg"
            class="w-full"
            @click="startCamera"
          >
            <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M4 5a2 2 0 00-2 2v8a2 2 0 002 2h12a2 2 0 002-2V7a2 2 0 00-2-2h-1.586a1 1 0 01-.707-.293l-1.121-1.121A2 2 0 0011.172 3H8.828a2 2 0 00-1.414.586L6.293 4.707A1 1 0 015.586 5H4zm6 9a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd" />
            </svg>
            Usar Webcam
          </AppButton>

          <button
            type="button"
            class="w-full text-center text-primary-600 font-medium hover:text-primary-700"
            @click="triggerFileInput"
          >
            {{ capture.hasWebcam ? 'O sube una imagen de tu computadora' : 'Sube una imagen de tu INE' }}
          </button>
        </template>
      </div>

      <!-- Error message -->
      <p v-if="capture.error.value" class="text-center text-sm text-red-600">
        {{ capture.error.value }}
      </p>
    </div>
  </div>
</template>
