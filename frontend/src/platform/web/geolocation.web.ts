import type { GeoPosition, PlatformGeolocation } from '../types'

/**
 * Implementación web de PlatformGeolocation usando navigator.geolocation.
 *
 * Cache simple en memoria con TTL configurable por llamada para evitar
 * disparar GPS en cada request HTTP.
 */

let cached: GeoPosition | null = null

function toGeoPosition(pos: GeolocationPosition): GeoPosition {
  return {
    latitude: pos.coords.latitude,
    longitude: pos.coords.longitude,
    accuracy: pos.coords.accuracy ?? null,
    timestamp: pos.timestamp,
  }
}

export const geolocationWeb: PlatformGeolocation = {
  isSupported(): boolean {
    return typeof navigator !== 'undefined' && 'geolocation' in navigator
  },

  async requestPermission(): Promise<'granted' | 'denied' | 'prompt' | 'unsupported'> {
    if (!this.isSupported()) return 'unsupported'
    // Permissions API no siempre está disponible; en ese caso prompt cuando
    // realmente se pida la posición.
    if (typeof navigator.permissions?.query === 'function') {
      try {
        const status = await navigator.permissions.query({ name: 'geolocation' as PermissionName })
        return status.state as 'granted' | 'denied' | 'prompt'
      } catch {
        return 'prompt'
      }
    }
    return 'prompt'
  },

  async getCurrent(opts: { cacheMs?: number; timeoutMs?: number } = {}): Promise<GeoPosition | null> {
    if (!this.isSupported()) return null

    const cacheMs = opts.cacheMs ?? 60_000
    const timeoutMs = opts.timeoutMs ?? 8_000

    if (cached && Date.now() - cached.timestamp < cacheMs) {
      return cached
    }

    return new Promise<GeoPosition | null>((resolve) => {
      navigator.geolocation.getCurrentPosition(
        (pos) => {
          cached = toGeoPosition(pos)
          resolve(cached)
        },
        () => resolve(null),
        {
          enableHighAccuracy: false,
          maximumAge: cacheMs,
          timeout: timeoutMs,
        },
      )
    })
  },
}
