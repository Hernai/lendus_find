/**
 * Composable para Google reCAPTCHA v3 (invisible, score-based).
 *
 * Como funciona:
 *   1. `ensureLoaded()` carga el script de Google (idempotente) si hay
 *      site_key configurado. Sin site_key (local/testing) hace no-op.
 *   2. `execute(action)` llama `grecaptcha.execute(siteKey, {action})` y
 *      devuelve el token a mandar al backend. Sin site_key devuelve null
 *      y el backend salta la validacion (default seguro).
 *
 * Uso tipico en un componente de login:
 *
 *   const { ensureLoaded, execute } = useRecaptcha()
 *   onMounted(() => ensureLoaded())
 *
 *   const handleSubmit = async () => {
 *     const token = await execute('login')
 *     await api.post('/login', { email, password, recaptcha_token: token })
 *   }
 *
 * El siteKey se lee del store de tenant (que lo recibe del backend en
 * `/v2/config`). Asi no hay nada hardcoded ni dependencia a env vars del
 * frontend — el tenant decide si activa o no captcha.
 */

import { useTenantStore } from '@/stores'

const SCRIPT_ID = 'recaptcha-v3-script'

declare global {
  interface Window {
    grecaptcha?: {
      ready: (cb: () => void) => void
      execute: (siteKey: string, options: { action: string }) => Promise<string>
    }
  }
}

interface UseRecaptchaReturn {
  /** Carga el script de Google si hay site_key y no esta ya cargado. Idempotente. */
  ensureLoaded: () => Promise<void>
  /** Ejecuta una accion y devuelve el token. null = backend no requiere captcha. */
  execute: (action: string) => Promise<string | null>
  /** Helper reactivo: true si el captcha esta activo en este tenant. */
  isEnabled: () => boolean
}

let loadingPromise: Promise<void> | null = null

export function useRecaptcha(): UseRecaptchaReturn {
  const tenantStore = useTenantStore()

  const getSiteKey = (): string | null => {
    // El tenant store expone `recaptcha.site_key` cuando /v2/config lo
    // incluye (lo cual ocurre solo cuando el backend tiene RECAPTCHA_SITE_KEY
    // seteado). Sin site_key, el composable se comporta como no-op.
    return tenantStore.recaptcha?.site_key || null
  }

  const isEnabled = (): boolean => !!getSiteKey()

  const ensureLoaded = async (): Promise<void> => {
    const siteKey = getSiteKey()
    if (!siteKey) return // No-op cuando no esta configurado
    if (window.grecaptcha) return // Ya cargado

    if (loadingPromise) {
      await loadingPromise
      return
    }

    loadingPromise = new Promise<void>((resolve, reject) => {
      // Evita doble injection si por alguna razon el composable se llama
      // antes de que la promesa esten en cache.
      if (document.getElementById(SCRIPT_ID)) {
        // Espera a que se cargue el script existente.
        const checkReady = () => {
          if (window.grecaptcha) {
            window.grecaptcha.ready(() => resolve())
          } else {
            setTimeout(checkReady, 50)
          }
        }
        checkReady()
        return
      }

      const script = document.createElement('script')
      script.id = SCRIPT_ID
      script.src = `https://www.google.com/recaptcha/api.js?render=${encodeURIComponent(siteKey)}`
      script.async = true
      script.defer = true
      script.onload = () => {
        if (window.grecaptcha) {
          window.grecaptcha.ready(() => resolve())
        } else {
          reject(new Error('reCAPTCHA cargado pero window.grecaptcha indefinido'))
        }
      }
      script.onerror = () => reject(new Error('No se pudo cargar el script de reCAPTCHA'))
      document.head.appendChild(script)
    })

    try {
      await loadingPromise
    } catch (e) {
      // Reseteamos el cache de la promesa para permitir retry en el proximo
      // ensureLoaded(). Si el script falla por bloqueador o red, la siguiente
      // llamada intentara de nuevo.
      loadingPromise = null
      throw e
    }
  }

  const execute = async (action: string): Promise<string | null> => {
    const siteKey = getSiteKey()
    if (!siteKey) return null // Backend no requiere captcha

    try {
      await ensureLoaded()
    } catch (e) {
      console.warn('[useRecaptcha] script load failed', e)
      // Devolvemos null para que el backend (si tiene secret) rechace
      // con CAPTCHA_FAILED. Mejor que romper el flujo silenciosamente.
      return null
    }

    if (!window.grecaptcha) return null

    try {
      return await window.grecaptcha.execute(siteKey, { action })
    } catch (e) {
      console.warn('[useRecaptcha] execute failed', e)
      return null
    }
  }

  return { ensureLoaded, execute, isEnabled }
}
