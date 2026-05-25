/**
 * @deprecated Shim de compatibilidad. Código nuevo debe usar `platform.realtime`.
 *
 * Este archivo se mantiene únicamente para consumidores legacy
 * (`useWebSocket.ts`, `DataCorrectionsView.vue`) que aún acceden a la API
 * cruda de Laravel Echo. Detrás de bambalinas delegamos al singleton de
 * `platform/web/realtime.web.ts` para que solo haya UNA conexión.
 */

import { platform } from '@/platform'
import { getRawEchoInstance, type EchoInstance } from '@/platform/web/realtime.web'
import { detectTenantSlug } from '@/utils/tenant'

export type { EchoInstance }

export function initializeEcho(token: string): EchoInstance {
  platform.realtime.connect(token, { tenantSlug: detectTenantSlug() })
  const instance = getRawEchoInstance()
  if (!instance) {
    throw new Error('Echo instance was not initialized')
  }
  return instance
}

export function disconnectEcho(): void {
  platform.realtime.disconnect()
}

export function getEcho(): EchoInstance | null {
  return getRawEchoInstance()
}
