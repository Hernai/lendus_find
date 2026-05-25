/**
 * Capa de plataforma — Factory.
 *
 * Detecta en runtime si la app corre en native (Capacitor iOS/Android) o web.
 * El bundle web no instancia los adapters native (los imports están aislados
 * por la rama del condicional y Capacitor.isNativePlatform() es síncrono).
 *
 * Uso:
 *   import { platform } from '@/platform'
 *   const token = await platform.storage.get<string>(STORAGE_KEYS.AUTH_TOKEN)
 */

import { Capacitor } from '@capacitor/core'
import type { Platforms } from './types'

// --- Web adapters ---
import { storageWeb } from './web/storage.web'
import { navigatorWeb, bindRouter as bindRouterWeb } from './web/navigator.web'
import { deviceWeb } from './web/device.web'
import { browserWeb } from './web/browser.web'
import { shareWeb } from './web/share.web'
import { clipboardWeb } from './web/clipboard.web'
import { cameraWeb } from './web/camera.web'
import { pushWeb } from './web/push.web'
import { realtimeWeb } from './web/realtime.web'
import { geolocationWeb } from './web/geolocation.web'

// --- Native adapters ---
// Estos imports añaden ~50 KB al bundle web pero los plugins reales solo se
// inicializan en native. Tree-shaking se encarga del resto.
import { storageNative } from './native/storage.native'
import { navigatorNative, bindRouter as bindRouterNative } from './native/navigator.native'
import { deviceNative } from './native/device.native'
import { browserNative } from './native/browser.native'
import { shareNative } from './native/share.native'
import { clipboardNative } from './native/clipboard.native'
import { cameraNative } from './native/camera.native'
import { pushNative } from './native/push.native'
import { realtimeNative } from './native/realtime.native'
import { geolocationNative } from './native/geolocation.native'

import type { Router } from 'vue-router'

const isNative = Capacitor.isNativePlatform()

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
  geolocation: geolocationWeb,
}

const nativePlatform: Platforms = {
  storage: storageNative,
  navigator: navigatorNative,
  device: deviceNative,
  browser: browserNative,
  share: shareNative,
  clipboard: clipboardNative,
  camera: cameraNative,
  push: pushNative,
  realtime: realtimeNative,
  geolocation: geolocationNative,
}

export const platform: Platforms = isNative ? nativePlatform : webPlatform

/**
 * Vincula el router de Vue. Llamar una sola vez desde `main.ts` antes del mount.
 * En native habilita el botón hardware Android. En web, permite que
 * `platform.navigator.navigate()` use vue-router en lugar de window.location.
 */
export function bindRouter(router: Router): void {
  if (isNative) {
    bindRouterNative(router)
  } else {
    bindRouterWeb(router)
  }
}

export * from './types'
export {
  requestStream as requestCameraStream,
  stopStream as stopCameraStream,
  captureFromVideoElement,
  fileToCapturedImage,
} from './web/camera.web'
