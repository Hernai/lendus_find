/**
 * Capa de plataforma — Factory.
 *
 * Expone `platform` con los adapters apropiados según el entorno:
 * - Web (Vite SPA, PWA): adapters de `./web/`.
 * - Native (Capacitor iOS/Android): adapters de `./native/` (Fase 3).
 *
 * La selección en runtime ocurrirá vía `Capacitor.isNativePlatform()` cuando
 * se instale `@capacitor/core` (Fase 3). Hasta entonces, este factory siempre
 * retorna la implementación web.
 *
 * Uso:
 *   import { platform } from '@/platform'
 *   const token = await platform.storage.get<string>(STORAGE_KEYS.AUTH_TOKEN)
 *   await platform.browser.openWhatsApp('+5215512345678', 'Hola')
 */

import type { Platforms } from './types'
import { storageWeb } from './web/storage.web'
import { navigatorWeb } from './web/navigator.web'
import { deviceWeb } from './web/device.web'
import { browserWeb } from './web/browser.web'
import { shareWeb } from './web/share.web'
import { clipboardWeb } from './web/clipboard.web'
import { cameraWeb } from './web/camera.web'
import { pushWeb } from './web/push.web'
import { realtimeWeb } from './web/realtime.web'

const webPlatform: Platforms = {
  storage: storageWeb,
  navigator: navigatorWeb,
  device: deviceWeb,
  browser: browserWeb,
  share: shareWeb,
  clipboard: clipboardWeb,
  camera: cameraWeb,
  push: pushWeb,
  realtime: realtimeWeb,
}

/**
 * Instancia global de adapters de plataforma.
 *
 * En Fase 3 (Capacitor) este export pasará a evaluar
 * `Capacitor.isNativePlatform()` y devolver `nativePlatform` o `webPlatform`.
 */
export const platform: Platforms = webPlatform

export * from './types'
export { bindRouter } from './web/navigator.web'
export {
  requestStream as requestCameraStream,
  stopStream as stopCameraStream,
  captureFromVideoElement,
  fileToCapturedImage,
} from './web/camera.web'
