import { platform } from '@/platform'
import { detectTenantSlug, hasTenantInUrl } from '@/utils/tenant'
import { STORAGE_KEYS } from '@/utils/storage'
import { logger } from '@/utils/logger'
import { emitAuthEvent } from '@/services/auth-events'
import { perf } from '@/utils/perf'
import { apiClient } from './client'

const log = logger.child('HTTP')

/**
 * Resuelve el tenant slug a enviar como `X-Tenant-ID`.
 *
 * Prioridad:
 * 1. Super admin global con tenant seleccionado en storage → siempre gana
 *    (cambia entre tenants vía el dropdown del backoffice).
 * 2. Slug en la URL (subdominio o path) — gana en web para evitar overrides
 *    persistidos cuando se navega entre /demo, /moneycapital, /finatea.
 * 3. Sesión con `selected_tenant_id` (legacy super admin) o override (native).
 * 4. Fallback a `detectTenantSlug()` (env var).
 */
async function resolveTenantSlug(): Promise<string> {
  const isSuperAdminGlobal = await platform.storage.get<boolean>(STORAGE_KEYS.IS_SUPER_ADMIN_GLOBAL)
  if (isSuperAdminGlobal) {
    const selected = await platform.storage.get<string>(STORAGE_KEYS.CURRENT_TENANT_SLUG)
    if (selected) return selected
  }

  if (hasTenantInUrl()) {
    return detectTenantSlug()
  }
  const authToken = await platform.storage.get<string>(STORAGE_KEYS.AUTH_TOKEN)
  if (authToken) {
    const selectedTenantId =
      (await platform.storage.get<string>(STORAGE_KEYS.CURRENT_TENANT_ID)) ||
      (await platform.storage.get<string>(STORAGE_KEYS.SELECTED_TENANT_ID))
    if (selectedTenantId) return selectedTenantId
  }
  const overrideSlug = await platform.storage.get<string>(STORAGE_KEYS.CURRENT_TENANT_SLUG)
  if (overrideSlug) return overrideSlug
  return detectTenantSlug()
}

let interceptorsRegistered = false

/**
 * Registra los interceptores de Axios. Idempotente.
 *
 * Headers que adjuntamos en cada request:
 * - `Authorization: Bearer <token>` (si hay sesión)
 * - `X-Tenant-ID`
 * - `X-Platform` (`web` | `ios` | `android`)
 * - `X-App-Version`
 * - `X-Device-Id` (UUID estable para telemetría/push)
 *
 * En 401 (no-auth endpoint): limpia token y emite `auth:unauthorized`.
 */
export function registerInterceptors(): void {
  if (interceptorsRegistered) return
  interceptorsRegistered = true

  apiClient.interceptors.request.use(async (config) => {
    const tenantSlug = await resolveTenantSlug()
    if (config.headers) {
      config.headers['X-Tenant-ID'] = tenantSlug
      config.headers['X-Platform'] = platform.device.platform()
      config.headers['X-App-Version'] = platform.device.appVersion()
      const deviceId = await platform.device.deviceId()
      if (deviceId) config.headers['X-Device-Id'] = deviceId

      // Geolocalización: cacheada 60s para no disparar GPS en cada request.
      // Si el usuario no dio permiso, getCurrent() devuelve null y no agregamos
      // los headers (el backend cae a IP-based geolocation).
      try {
        const geo = await platform.geolocation.getCurrent({ cacheMs: 60_000, timeoutMs: 2_500 })
        if (geo) {
          config.headers['X-Geo-Lat'] = geo.latitude.toFixed(7)
          config.headers['X-Geo-Lng'] = geo.longitude.toFixed(7)
          if (geo.accuracy != null) {
            config.headers['X-Geo-Accuracy'] = Math.round(geo.accuracy).toString()
          }
          config.headers['X-Geo-Timestamp'] = geo.timestamp.toString()
        }
      } catch {
        // ignorar — geo es best-effort
      }
    }

    const token = await platform.storage.get<string>(STORAGE_KEYS.AUTH_TOKEN)
    if (token && config.headers) {
      config.headers.Authorization = `Bearer ${token}`
    }

    if (config.data instanceof FormData && config.headers) {
      delete config.headers['Content-Type']
    }

    // Marca tiempo de inicio para que el interceptor de response calcule la
    // duración total. Se guarda en config.metadata (campo libre de Axios).
    ;(config as { metadata?: Record<string, unknown> }).metadata = {
      ...(config as { metadata?: Record<string, unknown> }).metadata,
      perfStartedAt: performance.now(),
    }

    log.debug('Request', { url: config.url, tenant: tenantSlug })
    return config
  })

  apiClient.interceptors.response.use(
    (response) => {
      const meta = (response.config as { metadata?: { perfStartedAt?: number } }).metadata
      if (meta?.perfStartedAt != null) {
        perf.recordApi({
          method: (response.config.method || 'GET').toUpperCase(),
          url: response.config.url || '',
          status: response.status,
          durationMs: performance.now() - meta.perfStartedAt,
          startedAt: meta.perfStartedAt,
        })
      }
      return response
    },
    async (error) => {
      // Registrar la métrica incluso si falló
      const config = error?.config ?? error?.response?.config
      const meta = (config as { metadata?: { perfStartedAt?: number } } | undefined)?.metadata
      if (meta?.perfStartedAt != null) {
        perf.recordApi({
          method: (config?.method || 'GET').toUpperCase(),
          url: config?.url || '',
          status: error?.response?.status,
          durationMs: performance.now() - meta.perfStartedAt,
          startedAt: meta.perfStartedAt,
          error: error?.message || 'Network error',
        })
      }

      if (error?.response) {
        const { status, config } = error.response
        const requestUrl = (config?.url as string) || ''
        const isAuthEndpoint = requestUrl.includes('/auth/') || requestUrl.includes('/login')

        if (status === 401 && !isAuthEndpoint) {
          await platform.storage.remove(STORAGE_KEYS.AUTH_TOKEN)
          emitAuthEvent('auth:unauthorized')
        }

        if (status === 403) {
          log.warn('Access denied', { url: requestUrl })
        }

        if (status === 422) {
          return Promise.reject(error.response.data)
        }
      }
      return Promise.reject(error)
    },
  )
}
