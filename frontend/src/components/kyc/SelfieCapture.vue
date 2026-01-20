<script setup lang="ts">
import { ref, computed, onUnmounted } from 'vue'
import { useSelfieCapture } from '@/composables/useDeviceCapture'
import AppButton from '@/components/common/AppButton.vue'

interface Props {
  /** Current captured image (base64) */
  capturedImage?: string | null
  /** Whether this step is active */
  active?: boolean
  /** Whether face match has been validated (locks the image) */
  isValidated?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  capturedImage: null,
  active: true,
  isValidated: false
})

const emit = defineEmits<{
  captured: [image: string]
  retake: []
}>()

// Use the selfie capture composable (front camera)
const capture = useSelfieCapture({ maxWidth: 1280, maxHeight: 720, quality: 0.9 })

// Refs
const videoRef = ref<HTMLVideoElement | null>(null)
const fileInputRef = ref<HTMLInputElement | null>(null)
const showWebcam = ref(false)
const capturedPreview = ref<string | null>(null)

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
  // First show the webcam view so the video element is rendered
  showWebcam.value = true

  // Wait for DOM to update and video element to be available
  await new Promise(resolve => setTimeout(resolve, 150))

  if (videoRef.value) {
    const success = await capture.startWebcam(videoRef.value)
    if (!success) {
      // If webcam failed to start, go back to initial state
      showWebcam.value = false
    }
  } else {
    console.error('Video element not found after DOM update')
    showWebcam.value = false
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
      <h2 class="text-xl font-semibold text-gray-900">Toma una selfie</h2>
      <p class="text-gray-600 mt-1">Compararemos tu rostro con la foto de tu INE</p>
    </div>

    <!-- Preview of captured image -->
    <div v-if="hasCaptured && !showWebcam" class="space-y-4">
      <div class="relative aspect-square max-w-xs mx-auto rounded-full overflow-hidden bg-gray-100 border-4 border-green-500">
        <img
          :src="capturedImage ? `data:image/jpeg;base64,${capturedImage}` : (capturedPreview ?? undefined)"
          alt="Selfie capturado"
          class="w-full h-full object-cover"
        />
        <!-- Success badge -->
        <div class="absolute bottom-2 left-1/2 -translate-x-1/2 bg-green-500 text-white px-3 py-1 rounded-full text-sm font-medium flex items-center gap-1">
          <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
          </svg>
          Capturado
        </div>
      </div>

      <!-- Retake button (hidden if validated) -->
      <div v-if="!isValidated" class="flex justify-center">
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

      <!-- Validated badge -->
      <div v-else class="flex justify-center">
        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-green-100 text-green-700 rounded-full text-sm font-medium">
          <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
          </svg>
          Face Match verificado
        </span>
      </div>
    </div>

    <!-- Webcam view (desktop) -->
    <div v-else-if="showWebcam" class="space-y-4">
      <div class="relative aspect-square max-w-xs mx-auto rounded-full overflow-hidden bg-black">
        <!-- Video stream -->
        <video
          ref="videoRef"
          autoplay
          playsinline
          muted
          class="w-full h-full object-cover scale-x-[-1]"
        />

        <!-- Circular guide overlay -->
        <div class="absolute inset-0 pointer-events-none">
          <svg class="w-full h-full" viewBox="0 0 100 100">
            <defs>
              <mask id="selfie-hole">
                <rect width="100" height="100" fill="white" />
                <circle cx="50" cy="50" r="45" fill="black" />
              </mask>
            </defs>
            <rect width="100" height="100" fill="rgba(0,0,0,0.6)" mask="url(#selfie-hole)" />
            <circle cx="50" cy="50" r="45" fill="none" stroke="white" stroke-width="1" stroke-dasharray="4,4" />
          </svg>
        </div>

        <!-- Loading overlay -->
        <div v-if="capture.isLoading.value" class="absolute inset-0 bg-black/50 flex items-center justify-center">
          <div class="animate-spin rounded-full h-12 w-12 border-4 border-white border-t-transparent" />
        </div>
      </div>

      <!-- Hint -->
      <p class="text-center text-sm text-gray-500">Centra tu rostro en el marco</p>

      <!-- Controls -->
      <div class="flex gap-3 max-w-xs mx-auto">
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
      <!-- Simple selfie illustration -->
      <div class="w-40 h-40 mx-auto rounded-full bg-gray-100 border-2 border-dashed border-gray-300 flex items-center justify-center">
        <svg class="w-20 h-20 text-gray-400" viewBox="0 0 24 24" fill="currentColor">
          <circle cx="12" cy="8" r="4" />
          <path d="M12 14c-4 0-8 2-8 4v2h16v-2c0-2-4-4-8-4z" />
        </svg>
      </div>

      <!-- Instruction text -->
      <p class="text-center text-gray-600 font-medium">
        Mira directamente a la cámara
      </p>

      <!-- Hint -->
      <p class="text-center text-sm text-gray-500">
        Asegúrate de tener buena iluminación y que tu rostro sea visible
      </p>

      <!-- Hidden file input -->
      <input
        ref="fileInputRef"
        type="file"
        accept="image/*"
        :capture="capture.isMobile ? 'user' : undefined"
        class="hidden"
        @change="handleFileInput"
      />

      <!-- Capture buttons -->
      <div class="space-y-3 max-w-xs mx-auto">
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
          Tomar Selfie
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
            {{ capture.hasWebcam ? 'O sube una imagen' : 'Sube una imagen' }}
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
