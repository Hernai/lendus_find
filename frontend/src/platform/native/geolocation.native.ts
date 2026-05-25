import { Geolocation } from '@capacitor/geolocation'
import type { GeoPosition, PlatformGeolocation } from '../types'

/**
 * Implementación native de PlatformGeolocation usando @capacitor/geolocation.
 *
 * Requiere permisos en Android (ACCESS_FINE_LOCATION) e iOS (NSLocationWhenInUseUsageDescription).
 */

let cached: GeoPosition | null = null

export const geolocationNative: PlatformGeolocation = {
  isSupported(): boolean {
    return true
  },

  async requestPermission(): Promise<'granted' | 'denied' | 'prompt' | 'unsupported'> {
    try {
      const status = await Geolocation.requestPermissions({ permissions: ['location'] })
      const loc = status.location
      if (loc === 'granted') return 'granted'
      if (loc === 'denied') return 'denied'
      return 'prompt'
    } catch {
      return 'unsupported'
    }
  },

  async getCurrent(opts: { cacheMs?: number; timeoutMs?: number } = {}): Promise<GeoPosition | null> {
    const cacheMs = opts.cacheMs ?? 60_000
    const timeoutMs = opts.timeoutMs ?? 8_000

    if (cached && Date.now() - cached.timestamp < cacheMs) {
      return cached
    }

    try {
      const pos = await Geolocation.getCurrentPosition({
        enableHighAccuracy: false,
        maximumAge: cacheMs,
        timeout: timeoutMs,
      })
      cached = {
        latitude: pos.coords.latitude,
        longitude: pos.coords.longitude,
        accuracy: pos.coords.accuracy ?? null,
        timestamp: pos.timestamp,
      }
      return cached
    } catch {
      return null
    }
  },
}
