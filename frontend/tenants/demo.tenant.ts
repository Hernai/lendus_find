import type { TenantConfig } from './_types'

/**
 * Tenant DEMO — usado para validar el pipeline white-label end-to-end.
 *
 * Los assets bajo `frontend/tenants/demo/` son placeholders. Reemplázalos
 * con los reales del tenant cuando se publique.
 */
const config: TenantConfig = {
  slug: 'demo',
  appId: 'mx.lendus.demo',
  appName: 'Lendus Demo',
  // Backend (VITE_API_URL) y Reverb (VITE_REVERB_*) viven en `.env*` —
  // son compartidos en la arquitectura B, no per-tenant.
  assets: {
    icon: 'tenants/demo/icon.png',
    splash: 'tenants/demo/splash.png',
    splashBackgroundColor: '#1E40AF',
  },
  theme: {
    primary: '#1E40AF',
    statusBar: 'light',
  },
  // push se configurará en Fase 6 cuando haya credenciales reales.
  deepLinkHost: 'demo.lendus.mx',
  landingComponent: 'DemoLanding',
  auth: {
    methods: ['phone', 'whatsapp', 'email'],
    defaultMethod: 'phone',
  },
}

export default config
