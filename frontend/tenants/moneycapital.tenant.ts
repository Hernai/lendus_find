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
  appId: 'io.money.capital',
  appName: 'MoneyCapital',
  // Backend (VITE_API_URL) y Reverb (VITE_REVERB_*) viven en `.env*` —
  // son compartidos en la arquitectura B, no per-tenant.
  assets: {
    icon: 'tenants/moneycapital/icon.png',
    splash: 'tenants/moneycapital/splash.png',
    // El splash trae el wordmark morado de MoneyCapital: fondo blanco
    // para que sea legible (sobre morado desaparecería).
    splashBackgroundColor: '#FFFFFF',
    // El logo ya es un círculo morado: fondo blanco para que se distinga
    // en el launcher (los iconos transparentes no existen en Android/iOS).
    iconBackgroundColor: '#FFFFFF',
  },
  nativeTheme: {
    primary: '#5B21B6',
    statusBar: 'light',
  },
  // push se configurará cuando MoneyCapital provea credenciales reales.
  deepLinkHost: 'moneycapital.lendus.app',
  custom: {
    landing: 'MoneyCapitalLanding',
    // Pantalla unificada de auth (UnifiedAuthView): combina celular + OTP +
    // método de envío en una sola pantalla. Se activa en runtime cuando el
    // backend devuelve features.unified_auth_screen=true para este tenant.
    authFlow: 'UnifiedAuthView',
    // Simulador y dashboard usan el flujo estándar y respetan el branding
    // del admin.
  },
  auth: {
    methods: ['phone'],
    defaultMethod: 'phone',
  },
}

export default config
