/**
 * Tipo compartido para configuraciones per-tenant.
 *
 * Cada SOFOM tiene un archivo `<slug>.tenant.ts` que exporta un objeto
 * de este tipo. El script `scripts/build-tenant.mjs` lee estos archivos
 * para generar builds nativos con bundle ID, nombre, iconos, splash y
 * credenciales push propios.
 */

export interface TenantConfig {
  /** Slug del tenant (mismo valor que `X-Tenant-ID`). */
  slug: string

  /** Bundle ID iOS / applicationId Android. Ej: `mx.acme.lendus`. */
  appId: string

  /** Nombre comercial que aparece debajo del icono. */
  appName: string

  /** URL del backend público (sin trailing slash, sin /api). */
  apiBaseUrl: string

  /** Host del WebSocket Reverb público. */
  reverbHost: string

  /** Puerto Reverb (típicamente 443). */
  reverbPort: number

  /** Esquema Reverb (`https` o `http`). */
  reverbScheme: 'https' | 'http'

  /** Reverb app key (mismo valor que VITE_REVERB_APP_KEY del .env del tenant). */
  reverbAppKey: string

  /** Rutas a los assets para `@capacitor/assets` (relativas a `frontend/`). */
  assets: {
    /** PNG cuadrado, 1024×1024. */
    icon: string
    /** PNG cuadrado, 2732×2732. */
    splash: string
    /** PNG cuadrado opcional para dark mode. */
    splashDark?: string
    /** Color de fondo del splash (HEX). */
    splashBackgroundColor: string
  }

  theme: {
    /** Color primario del tenant (HEX). */
    primary: string
    /** Estilo de la status bar nativa. */
    statusBar: 'light' | 'dark'
  }

  /** Configuración push opcional (Fase 6). */
  push?: {
    /** Ruta relativa al `google-services.json` (Android FCM). */
    fcmGoogleServicesPath?: string
    /** Ruta relativa al `GoogleService-Info.plist` (iOS, si usa Firebase). */
    fcmInfoPlistPath?: string
    /** Apple Developer Team ID para APNs. */
    apnsTeamId?: string
    /** Bundle ID en Apple (típicamente igual a appId). */
    apnsBundleId?: string
  }

  /** Dominio público para deep links (Universal Links / App Links). */
  deepLinkHost?: string
}
