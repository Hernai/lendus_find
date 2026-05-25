import { ref, onUnmounted } from 'vue'
import { logger } from '@/utils/logger'
import { platform } from '@/platform'
import {
  requestCameraStream,
  stopCameraStream,
  captureFromVideoElement,
  fileToCapturedImage,
} from '@/platform'

const log = logger.child('DeviceCapture')

export interface CaptureOptions {
  /** Camera facing mode: 'user' for selfie, 'environment' for documents */
  facingMode?: 'user' | 'environment'
  /** Maximum width for the captured image */
  maxWidth?: number
  /** Maximum height for the captured image */
  maxHeight?: number
  /** JPEG quality (0-1) */
  quality?: number
}

export interface UseCaptureReturn {
  /** Whether the device is mobile */
  isMobile: boolean
  /** Whether webcam is supported */
  hasWebcam: boolean
  /** Whether webcam is currently active */
  isWebcamActive: Readonly<import('vue').Ref<boolean>>
  /** Current webcam stream */
  stream: Readonly<import('vue').Ref<MediaStream | null>>
  /** Any error that occurred */
  error: Readonly<import('vue').Ref<string | null>>
  /** Whether the device is loading/initializing */
  isLoading: Readonly<import('vue').Ref<boolean>>
  /** Start the webcam stream */
  startWebcam: (videoElement: HTMLVideoElement) => Promise<boolean>
  /** Stop the webcam stream */
  stopWebcam: () => void
  /** Capture photo from webcam */
  captureFromWebcam: (videoElement: HTMLVideoElement) => Promise<string | null>
  /** Process file input (for mobile native capture or fallback upload) */
  processFileInput: (file: File) => Promise<string | null>
  /** Convert image to base64 with resizing */
  imageToBase64: (file: File | Blob) => Promise<string>
  /** Get native capture attributes for input element */
  getNativeCaptureAttributes: () => { accept: string; capture?: string }
}

/**
 * Composable para captura de imágenes (cámara/galería) en web y móvil.
 *
 * Toda la lógica de bajo nivel (getUserMedia, canvas, resize) vive en
 * `src/platform/web/camera.web.ts`. Este composable solo expone helpers
 * convenientes para vistas con preview en `<video>`. En Capacitor, la
 * implementación nativa de `platform.camera` reemplazará a estos helpers
 * web-only sin que las vistas que usen el composable se enteren.
 */
export function useDeviceCapture(options: CaptureOptions = {}): UseCaptureReturn {
  const {
    facingMode = 'environment',
    maxWidth = 1920,
    maxHeight = 1080,
    quality = 0.85,
  } = options

  // State
  const stream = ref<MediaStream | null>(null)
  const isWebcamActive = ref(false)
  const error = ref<string | null>(null)
  const isLoading = ref(false)

  // Device detection — delegamos a la capa de plataforma.
  const isMobile = platform.device.isMobileUserAgent()
  // Disponibilidad de cámara — evaluación síncrona razonable para web; en
  // native el adapter siempre será true.
  const hasWebcam =
    typeof navigator !== 'undefined' && !!navigator.mediaDevices?.getUserMedia

  const startWebcam = async (videoElement: HTMLVideoElement): Promise<boolean> => {
    if (!hasWebcam) {
      error.value = 'Tu navegador no soporta acceso a la cámara'
      return false
    }

    isLoading.value = true
    error.value = null

    try {
      stream.value = await requestCameraStream({ facing: facingMode, maxWidth, maxHeight })
      videoElement.srcObject = stream.value

      await new Promise<void>((resolve, reject) => {
        videoElement.onloadedmetadata = () => {
          videoElement
            .play()
            .then(() => resolve())
            .catch(reject)
        }
        videoElement.onerror = () => reject(new Error('Error loading video'))
      })

      isWebcamActive.value = true
      return true
    } catch (err) {
      log.error('Failed to start webcam:', err)

      if (err instanceof Error) {
        if (err.name === 'NotAllowedError' || err.name === 'PermissionDeniedError') {
          error.value = 'Permiso de cámara denegado. Activa el permiso en tu navegador.'
        } else if (err.name === 'NotFoundError' || err.name === 'DevicesNotFoundError') {
          error.value = 'No se encontró una cámara en tu dispositivo.'
        } else if (err.name === 'NotReadableError' || err.name === 'TrackStartError') {
          error.value = 'La cámara está siendo usada por otra aplicación.'
        } else {
          error.value = `Error al acceder a la cámara: ${err.message}`
        }
      } else {
        error.value = 'Error desconocido al acceder a la cámara'
      }

      return false
    } finally {
      isLoading.value = false
    }
  }

  const stopWebcam = () => {
    stopCameraStream(stream.value)
    stream.value = null
    isWebcamActive.value = false
  }

  const captureFromWebcam = async (videoElement: HTMLVideoElement): Promise<string | null> => {
    if (!isWebcamActive.value || !videoElement.videoWidth) {
      error.value = 'La cámara no está activa'
      return null
    }
    try {
      const captured = captureFromVideoElement(videoElement, {
        facing: facingMode,
        maxWidth,
        maxHeight,
        quality,
        mirror: facingMode === 'user',
      })
      return captured?.base64 ?? null
    } catch (err) {
      log.error('Failed to capture from webcam:', err)
      error.value = 'Error al capturar la imagen'
      return null
    }
  }

  const imageToBase64 = async (file: File | Blob): Promise<string> => {
    const captured = await fileToCapturedImage(file, {
      facing: facingMode,
      maxWidth,
      maxHeight,
      quality,
      mirror: facingMode === 'user',
    })
    return captured.base64
  }

  const processFileInput = async (file: File): Promise<string | null> => {
    if (!file) {
      error.value = 'No se seleccionó ningún archivo'
      return null
    }
    if (!file.type.startsWith('image/')) {
      error.value = 'El archivo debe ser una imagen'
      return null
    }

    isLoading.value = true
    error.value = null

    try {
      return await imageToBase64(file)
    } catch (err) {
      log.error('Failed to process file:', err)
      error.value = 'Error al procesar la imagen'
      return null
    } finally {
      isLoading.value = false
    }
  }

  const getNativeCaptureAttributes = (): { accept: string; capture: string | undefined } => ({
    accept: 'image/*',
    capture: isMobile ? (facingMode === 'user' ? 'user' : 'environment') : undefined,
  })

  onUnmounted(() => {
    stopWebcam()
  })

  return {
    isMobile,
    hasWebcam,
    isWebcamActive,
    stream,
    error,
    isLoading,
    startWebcam,
    stopWebcam,
    captureFromWebcam,
    processFileInput,
    imageToBase64,
    getNativeCaptureAttributes,
  }
}

/**
 * Create a capture composable for INE/ID documents (back camera)
 */
export function useDocumentCapture(options: Omit<CaptureOptions, 'facingMode'> = {}) {
  return useDeviceCapture({ ...options, facingMode: 'environment' })
}

/**
 * Create a capture composable for selfies (front camera)
 */
export function useSelfieCapture(options: Omit<CaptureOptions, 'facingMode'> = {}) {
  return useDeviceCapture({ ...options, facingMode: 'user' })
}
