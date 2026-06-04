import type { TenantConfig } from './_types'

/**
 * Tenant Finatea.
 *
 * Branding teal (#0D9488), bundle nativo `mx.finatea.app`.
 * Assets actuales (icon.png, splash.png) son placeholders copiados de demo
 * — reemplázalos cuando Finatea provea su branding oficial.
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
    splashBackgroundColor: '#0D9488',
  },
  theme: {
    primary: '#0D9488',
    statusBar: 'light',
  },
  deepLinkHost: 'app.finatea.mx',
  landingComponent: 'FinateaLanding',
  auth: {
    methods: ['phone', 'whatsapp'],
    defaultMethod: 'whatsapp',
  },
}

export default config
