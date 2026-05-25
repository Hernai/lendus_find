import type { CameraOptions, CapturedImage, PlatformCamera } from '../types'

/**
 * Implementación web de PlatformCamera.
 *
 * Usa `getUserMedia` para webcam y `<input type="file">` programático como
 * fallback para galería. En mobile (Capacitor) este adapter se reemplaza por
 * uno basado en `@capacitor/camera` que da acceso a la cámara nativa.
 *
 * Nota: este adapter NO maneja el preview en `<video>` directamente — eso
 * sigue siendo responsabilidad del componente de UI que muestra la cámara.
 * Aquí solo se expone `capture(stream)` para tomar la foto desde un stream
 * activo, y `pickFromGallery` para selección de archivo.
 *
 * Para mantener compatibilidad con el composable actual `useDeviceCapture`,
 * exponemos también helpers de bajo nivel (`requestStream`, `stopStream`,
 * `captureFromVideoElement`) que vistas legacy pueden seguir usando hasta el
 * refactor de Fase 1.
 */

interface WebCameraOptions extends CameraOptions {
  /** Cuando se quiera capturar desde un <video> activo. */
  videoElement?: HTMLVideoElement
}

const DEFAULTS = {
  facing: 'environment' as const,
  maxWidth: 1920,
  maxHeight: 1080,
  quality: 0.85,
}

function applyDimensionConstraints(width: number, height: number, max: { w: number; h: number }) {
  if (width > max.w) {
    height = (height * max.w) / width
    width = max.w
  }
  if (height > max.h) {
    width = (width * max.h) / height
    height = max.h
  }
  return { width: Math.round(width), height: Math.round(height) }
}

function stripBase64Prefix(dataUrl: string): string {
  return dataUrl.split(',')[1] ?? ''
}

/**
 * Solicita un MediaStream con las restricciones indicadas. Helper de bajo nivel
 * que las vistas con preview personalizado pueden usar.
 */
export async function requestStream(opts: WebCameraOptions = {}): Promise<MediaStream> {
  const facingMode = opts.facing ?? DEFAULTS.facing
  const maxWidth = opts.maxWidth ?? DEFAULTS.maxWidth
  const maxHeight = opts.maxHeight ?? DEFAULTS.maxHeight
  const constraints: MediaStreamConstraints = {
    video: {
      facingMode,
      width: { ideal: maxWidth },
      height: { ideal: maxHeight },
    },
    audio: false,
  }
  return navigator.mediaDevices.getUserMedia(constraints)
}

export function stopStream(stream: MediaStream | null): void {
  if (!stream) return
  stream.getTracks().forEach((track) => track.stop())
}

/**
 * Captura el frame actual de un `<video>` con stream activo a base64 JPEG.
 */
export function captureFromVideoElement(
  videoElement: HTMLVideoElement,
  opts: CameraOptions = {},
): CapturedImage | null {
  if (!videoElement.videoWidth) return null
  const facing = opts.facing ?? DEFAULTS.facing
  const maxWidth = opts.maxWidth ?? DEFAULTS.maxWidth
  const maxHeight = opts.maxHeight ?? DEFAULTS.maxHeight
  const quality = opts.quality ?? DEFAULTS.quality
  const mirror = opts.mirror ?? facing === 'user'

  const canvas = document.createElement('canvas')
  const ctx = canvas.getContext('2d')
  if (!ctx) return null

  const { width, height } = applyDimensionConstraints(
    videoElement.videoWidth,
    videoElement.videoHeight,
    { w: maxWidth, h: maxHeight },
  )
  canvas.width = width
  canvas.height = height

  if (mirror) {
    ctx.translate(width, 0)
    ctx.scale(-1, 1)
  }
  ctx.drawImage(videoElement, 0, 0, width, height)

  const dataUrl = canvas.toDataURL('image/jpeg', quality)
  return {
    base64: stripBase64Prefix(dataUrl),
    mimeType: 'image/jpeg',
    width,
    height,
  }
}

/**
 * Convierte un File/Blob a CapturedImage redimensionando si excede los máximos.
 */
export function fileToCapturedImage(file: File | Blob, opts: CameraOptions = {}): Promise<CapturedImage> {
  const maxWidth = opts.maxWidth ?? DEFAULTS.maxWidth
  const maxHeight = opts.maxHeight ?? DEFAULTS.maxHeight
  const quality = opts.quality ?? DEFAULTS.quality
  const mirror = opts.mirror ?? false

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
        const { width, height } = applyDimensionConstraints(img.width, img.height, {
          w: maxWidth,
          h: maxHeight,
        })
        canvas.width = width
        canvas.height = height
        if (mirror) {
          ctx.translate(width, 0)
          ctx.scale(-1, 1)
        }
        ctx.drawImage(img, 0, 0, width, height)
        const dataUrl = canvas.toDataURL('image/jpeg', quality)
        resolve({
          base64: stripBase64Prefix(dataUrl),
          mimeType: 'image/jpeg',
          width,
          height,
        })
      }
      img.onerror = () => reject(new Error('Failed to load image'))
      img.src = e.target?.result as string
    }
    reader.onerror = () => reject(new Error('Failed to read file'))
    reader.readAsDataURL(file)
  })
}

/**
 * Selecciona un archivo de imagen vía `<input type=file>` programático.
 * En mobile (web móvil) usa `capture` para abrir cámara nativa.
 */
function pickFile(opts: CameraOptions, useCapture: boolean): Promise<CapturedImage | null> {
  return new Promise((resolve, reject) => {
    const input = document.createElement('input')
    input.type = 'file'
    input.accept = 'image/*'
    if (useCapture) {
      // capture='user' o 'environment' abre cámara nativa en móvil.
      input.setAttribute('capture', opts.facing === 'user' ? 'user' : 'environment')
    }
    input.style.display = 'none'
    input.onchange = async () => {
      const file = input.files?.[0]
      document.body.removeChild(input)
      if (!file) {
        resolve(null)
        return
      }
      try {
        const captured = await fileToCapturedImage(file, opts)
        resolve(captured)
      } catch (err) {
        reject(err)
      }
    }
    input.oncancel = () => {
      document.body.removeChild(input)
      resolve(null)
    }
    document.body.appendChild(input)
    input.click()
  })
}

export const cameraWeb: PlatformCamera = {
  async isAvailable(): Promise<boolean> {
    return typeof navigator !== 'undefined' && !!navigator.mediaDevices?.getUserMedia
  },

  /**
   * Captura sencilla via prompt nativo del navegador (input[capture]).
   * Para preview con `<video>` controlado por la vista, usar los helpers
   * `requestStream`, `captureFromVideoElement`, `stopStream`.
   */
  async capture(opts: CameraOptions = {}): Promise<CapturedImage | null> {
    return pickFile(opts, true)
  },

  async pickFromGallery(opts: CameraOptions = {}): Promise<CapturedImage | null> {
    return pickFile(opts, false)
  },
}
