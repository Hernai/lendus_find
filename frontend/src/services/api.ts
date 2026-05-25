/**
 * @deprecated Importar desde `@/http` en código nuevo.
 *
 * Este archivo se mantiene como re-export para no romper imports legacy.
 * La implementación real vive en `src/http/`.
 */

export { api, apiClient, API_BASE } from '@/http'
export { apiClient as default } from '@/http'

/**
 * @deprecated `initCsrf` ya no es necesario — el auth es 100% Bearer.
 * Se mantiene como no-op para compatibilidad con llamadas existentes.
 */
export async function initCsrf(): Promise<void> {
  /* noop */
}
