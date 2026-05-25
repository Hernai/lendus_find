import { platform } from '@/platform'
import { detectTenantSlug } from '@/utils/tenant'
import { STORAGE_KEYS } from '@/utils/storage'
import { logger } from '@/utils/logger'
import { emitAuthEvent } from '@/services/auth-events'
import { apiClient } from './client'

const log = logger.child('HTTP')

/**
 * Resuelve el tenant slug a enviar como `X-Tenant-ID`.
 *
 * Prioridad:
 * 1. Si hay sesión y un tenant seleccionado en storage (super admin), usar ese.
 * 2. Si hay un override persistido (caso native), usar ese.
 * 3. Fallback a `detectTenantSlug()` (web: subdomain, path, o env).
 */
async function resolveTenantSlug(): Promise<string> {
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
    }

    const token = await platform.storage.get<string>(STORAGE_KEYS.AUTH_TOKEN)
    if (token && config.headers) {
      config.headers.Authorization = `Bearer ${token}`
    }

    if (config.data instanceof FormData && config.headers) {
      delete config.headers['Content-Type']
    }

    log.debug('Request', { url: config.url, tenant: tenantSlug })
    return config
  })

  apiClient.interceptors.response.use(
    (response) => response,
    async (error) => {
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
