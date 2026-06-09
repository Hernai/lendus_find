import type { TenantConfig } from './_types'

/**
 * Tenant MoneyCapital México.
 *
 * Branding morado (#5B21B6), bundle nativo `mx.moneycapital.app`.
 * Las features activas (loan_portfolio, unified_consent_screen,
 * phone_score_enabled, auto_disbursement) viven en el backend
 * (`Tenant.features`) y se exponen vía /api/v2/config.
 */
const config: TenantConfig = {
  slug: 'moneycapital',
  appId: 'mx.moneycapital.app',
  appName: 'MoneyCapital',
  // Backend (VITE_API_URL) y Reverb (VITE_REVERB_*) viven en `.env*` —
  // son compartidos en la arquitectura B, no per-tenant.
  assets: {
    icon: 'tenants/moneycapital/icon.png',
    splash: 'tenants/moneycapital/splash.png',
    splashBackgroundColor: '#5B21B6',
  },
  nativeTheme: {
    primary: '#5B21B6',
    statusBar: 'light',
  },
  // push se configurará cuando MoneyCapital provea credenciales reales.
  deepLinkHost: 'app.moneycapital.mx',
  custom: {
    landing: 'MoneyCapitalLanding',
    // Auth, simulador y dashboard usan el flujo estándar y respetan el
    // branding del admin.
  },
  auth: {
    methods: ['phone', 'email'],
    defaultMethod: 'phone',
  },
}

export default config
