import type { TenantConfig } from './_types'

/**
 * Tenant Finatea.
 *
 * Branding rojo (#B91C1C), bundle nativo `mx.finatea.app`.
 *
 * Para activar push notifications, agregar config.push con FCM/APNs.
 */
const config: TenantConfig = {
  slug: 'finatea',
  appId: 'mx.finatea.app',
  appName: 'Finatea',
  // Backend (VITE_API_URL) y Reverb (VITE_REVERB_*) viven en `.env*` —
  // son compartidos en la arquitectura B, no per-tenant.
  assets: {
    icon: 'tenants/finatea/icon.png',
    splash: 'tenants/finatea/splash.png',
    splashBackgroundColor: '#FFFFFF',
    // El logo trae figuras a color sobre transparente: fondo blanco para
    // que se distinga en el launcher (los iconos transparentes no existen
    // en Android/iOS).
    iconBackgroundColor: '#FFFFFF',
    // Wordmark "finatea" para la franja inferior del splash Android 12+.
    branding: 'tenants/finatea/branding.png',
  },
  nativeTheme: {
    primary: '#E02B20',
    statusBar: 'light',
  },
  deepLinkHost: 'finatea.lendus.app',
  custom: {
    landing: 'FinateaLanding',
  },
  auth: {
    methods: ['phone', 'email'],
    defaultMethod: 'phone',
  },
}

export default config
