import { ref, onUnmounted } from 'vue'
import { logger } from '@/utils/logger'

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
 * Composable for handling image capture across mobile and desktop devices.
 *
 * On mobile: Uses native camera via input[type=file][capture]
 * On desktop: Uses getUserMedia() for webcam with fallback to file upload
 */
export function useDeviceCapture(options: CaptureOptions = {}): UseCaptureReturn {
  const {
    facingMode = 'environment',
    maxWidth = 1920,
    maxHeight = 1080,
    quality = 0.85
  } = options

  // State
  const stream = ref<MediaStream | null>(null)
  const isWebcamActive = ref(false)
  const error = ref<string | null>(null)
  const isLoading = ref(false)

  // Device detection - evaluated once at composable creation
  const isMobile = (() => {
    if (typeof window === 'undefined') return false
    return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(
      navigator.userAgent
    )
  })()

  // Check for webcam support - evaluated once at composable creation
  const hasWebcam = (() => {
    if (typeof navigator === 'undefined') return false
    return !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia)
  })()

  /**
   * Start webcam stream and attach to video element
   */
  const startWebcam = async (videoElement: HTMLVideoElement): Promise<boolean> => {
    if (!hasWebcam) {
      error.value = 'Tu navegador no soporta acceso a la cámara'
      return false
    }

    isLoading.value = true
    error.value = null

    try {
      const constraints: MediaStreamConstraints = {
        video: {
          facingMode,
          width: { ideal: maxWidth },
          height: { ideal: maxHeight }
        },
        audio: false
      }

      stream.value = await navigator.mediaDevices.getUserMedia(constraints)
      videoElement.srcObject = stream.value

      // Wait for video to be ready
      await new Promise<void>((resolve, reject) => {
        videoElement.onloadedmetadata = () => {
          videoElement.play()
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

  /**
   * Stop webcam stream
   */
  const stopWebcam = () => {
    if (stream.value) {
      stream.value.getTracks().forEach(track => track.stop())
      stream.value = null
    }
    isWebcamActive.value = false
  }

  /**
   * Capture photo from active webcam stream
   */
  const captureFromWebcam = async (videoElement: HTMLVideoElement): Promise<string | null> => {
    if (!isWebcamActive.value || !videoElement.videoWidth) {
      error.value = 'La cámara no está activa'
      return null
    }

    try {
      const canvas = document.createElement('canvas')
      const ctx = canvas.getContext('2d')

      if (!ctx) {
        error.value = 'Error al crear canvas'
        return null
      }

      // Calculate dimensions maintaining aspect ratio
      let width = videoElement.videoWidth
      let height = videoElement.videoHeight

      if (width > maxWidth) {
        height = (height * maxWidth) / width
        width = maxWidth
      }
      if (height > maxHeight) {
        width = (width * maxHeight) / height
        height = maxHeight
      }

      canvas.width = width
      canvas.height = height

      // For front camera (selfie), mirror the image horizontally
      // This matches what the user sees in the preview
      if (facingMode === 'user') {
        ctx.translate(width, 0)
        ctx.scale(-1, 1)
      }

      // Draw video frame to canvas
      ctx.drawImage(videoElement, 0, 0, width, height)

      // Convert to base64
      const base64 = canvas.toDataURL('image/jpeg', quality)

      // Return just the base64 data (without data:image/jpeg;base64, prefix)
      return base64.split(',')[1] || null
    } catch (err) {
      log.error('Failed to capture from webcam:', err)
      error.value = 'Error al capturar la imagen'
      return null
    }
  }

  /**
   * Convert File/Blob to base64 with optional resizing
   * For selfie mode (facingMode === 'user'), mirrors the image horizontally
   */
  const imageToBase64 = (file: File | Blob): Promise<string> => {
    return new Promise((resolve, reject) => {
      const reader = new FileReader()

      reader.onload = (e) => {
        const img = new Image()

        img.onload = () => {
          const canvas = document.createElement('canvas')
          const ctx = canvas.getContext('2d')

          if (!ctx) {
            reject(new Error('Failed to create canvas context'))
            return
          }

          // Calculate dimensions maintaining aspect ratio
          let width = img.width
          let height = img.height

          if (width > maxWidth) {
            height = (height * maxWidth) / width
            width = maxWidth
          }
          if (height > maxHeight) {
            width = (width * maxHeight) / height
            height = maxHeight
          }

          canvas.width = width
          canvas.height = height

          // For front camera (selfie), mirror the image horizontally
          if (facingMode === 'user') {
            ctx.translate(width, 0)
            ctx.scale(-1, 1)
          }

          // Draw image to canvas
          ctx.drawImage(img, 0, 0, width, height)

          // Convert to base64 (without prefix)
          const base64 = canvas.toDataURL('image/jpeg', quality)
          const base64Data = base64.split(',')[1]
          if (base64Data) {
            resolve(base64Data)
          } else {
            reject(new Error('Failed to extract base64 data'))
          }
        }

        img.onerror = () => reject(new Error('Failed to load image'))
        img.src = e.target?.result as string
      }

      reader.onerror = () => reject(new Error('Failed to read file'))
      reader.readAsDataURL(file)
    })
  }

  /**
   * Process file from input element
   */
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
      const base64 = await imageToBase64(file)
      return base64
    } catch (err) {
      log.error('Failed to process file:', err)
      error.value = 'Error al procesar la imagen'
      return null
    } finally {
      isLoading.value = false
    }
  }

  /**
   * Get native capture attributes for input element
   * On mobile, this will open the native camera
   */
  const getNativeCaptureAttributes = (): { accept: string; capture: string | undefined } => {
    return {
      accept: 'image/*',
      capture: isMobile ? (facingMode === 'user' ? 'user' : 'environment') : undefined
    }
  }

  // Cleanup on unmount
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
    getNativeCaptureAttributes
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
