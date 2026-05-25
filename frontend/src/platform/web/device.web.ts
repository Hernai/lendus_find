import type { PlatformDevice } from '../types'
import { storageWeb } from './storage.web'

/**
 * Implementación web de PlatformDevice.
 *
 * - `platform()` siempre devuelve `'web'`.
 * - `deviceId()` genera y persiste un UUID en storage (estable entre sesiones).
 * - `appVersion()` se lee de `VITE_APP_VERSION` (definido en vite.config.ts).
 */

const DEVICE_ID_KEY = 'platform_device_id'

function generateUuid(): string {
  if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
    return crypto.randomUUID()
  }
  // Fallback (no debería ocurrir en navegadores modernos).
  return 'web-' + Math.random().toString(36).slice(2) + '-' + Date.now().toString(36)
}

let cachedDeviceId: string | null = null

export const deviceWeb: PlatformDevice = {
  platform() {
    return 'web'
  },

  appVersion(): string {
    return (import.meta.env.VITE_APP_VERSION as string | undefined) ?? '0.0.0'
  },

  osVersion(): string | null {
    if (typeof navigator === 'undefined') return null
    return navigator.userAgent || null
  },

  async deviceId(): Promise<string> {
    if (cachedDeviceId) return cachedDeviceId
    const stored = await storageWeb.get<string>(DEVICE_ID_KEY)
    if (stored) {
      cachedDeviceId = stored
      return stored
    }
    const fresh = generateUuid()
    await storageWeb.set(DEVICE_ID_KEY, fresh)
    cachedDeviceId = fresh
    return fresh
  },

  isNative(): boolean {
    return false
  },

  isMobileUserAgent(): boolean {
    if (typeof navigator === 'undefined') return false
    return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent)
  },
}
