import { Camera, CameraResultType, CameraSource, CameraDirection } from '@capacitor/camera'
import type { CameraOptions, CapturedImage, PlatformCamera } from '../types'

/**
 * Implementación native de PlatformCamera usando @capacitor/camera.
 *
 * El plugin nativo entrega la imagen ya redimensionada y rotada según EXIF,
 * por lo que no necesitamos canvas. Devuelve base64 directamente.
 */
export const cameraNative: PlatformCamera = {
  async isAvailable(): Promise<boolean> {
    return true
  },

  async capture(opts: CameraOptions = {}): Promise<CapturedImage | null> {
    try {
      const photo = await Camera.getPhoto({
        source: CameraSource.Camera,
        resultType: CameraResultType.Base64,
        quality: Math.round((opts.quality ?? 0.85) * 100),
        width: opts.maxWidth ?? 1920,
        height: opts.maxHeight ?? 1080,
        direction: opts.facing === 'user' ? CameraDirection.Front : CameraDirection.Rear,
        allowEditing: false,
        correctOrientation: true,
      })
      if (!photo.base64String) return null
      return {
        base64: photo.base64String,
        mimeType: photo.format ? `image/${photo.format}` : 'image/jpeg',
        width: opts.maxWidth ?? 0,
        height: opts.maxHeight ?? 0,
      }
    } catch (err) {
      // El usuario canceló o denegó permisos.
      if (err && typeof err === 'object' && 'message' in err) {
        const msg = String((err as { message: unknown }).message).toLowerCase()
        if (msg.includes('cancel') || msg.includes('denied')) return null
      }
      throw err
    }
  },

  async pickFromGallery(opts: CameraOptions = {}): Promise<CapturedImage | null> {
    try {
      const photo = await Camera.getPhoto({
        source: CameraSource.Photos,
        resultType: CameraResultType.Base64,
        quality: Math.round((opts.quality ?? 0.85) * 100),
        width: opts.maxWidth ?? 1920,
        height: opts.maxHeight ?? 1080,
        allowEditing: false,
        correctOrientation: true,
      })
      if (!photo.base64String) return null
      return {
        base64: photo.base64String,
        mimeType: photo.format ? `image/${photo.format}` : 'image/jpeg',
        width: opts.maxWidth ?? 0,
        height: opts.maxHeight ?? 0,
      }
    } catch (err) {
      if (err && typeof err === 'object' && 'message' in err) {
        const msg = String((err as { message: unknown }).message).toLowerCase()
        if (msg.includes('cancel') || msg.includes('denied')) return null
      }
      throw err
    }
  },
}
