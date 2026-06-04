/**
 * Tipo compartido para configuraciones per-tenant.
 *
 * Cada SOFOM tiene un archivo `<slug>.tenant.ts` que exporta un objeto
 * de este tipo. El script `scripts/build-tenant.mjs` lee estos archivos
 * para generar builds nativos con bundle ID, nombre, iconos, splash y
 * credenciales push propios.
 */

/** Métodos de autenticación que puede habilitar un tenant. */
export type AuthMethod = 'phone' | 'whatsapp' | 'email' | 'pin' | 'biometric'

/**
 * Identificador del componente Vue que sirve como landing del tenant.
 * El router lo resuelve en `router/tenantLandings.ts`. Si se omite, se
 * usa la landing genérica (`LandingView`).
 */
export type TenantLandingComponent =
  | 'GenericLanding'
  | 'DemoLanding'
  | 'MoneyCapitalLanding'
  | 'FinateaLanding'

export interface TenantConfig {
  /** Slug del tenant (mismo valor que `X-Tenant-ID`). */
  slug: string

  /** Bundle ID iOS / applicationId Android. Ej: `mx.acme.lendus`. */
  appId: string

  /** Nombre comercial que aparece debajo del icono. */
  appName: string

  // Nota: la URL del backend (`VITE_API_URL`) y la config del WebSocket
  // (`VITE_REVERB_*`) NO se declaran aquí porque son infraestructura
  // compartida en la arquitectura B (un solo backend `apifind.lendus.app`
  // que distingue tenants por el header `X-Tenant-ID`). Esos valores se
  // toman directamente del `.env*` que Vite carga durante el build.

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

  /**
   * Componente de landing del tenant. Si se omite, el dispatcher usa la
   * landing genérica (`LandingView`).
   */
  landingComponent?: TenantLandingComponent

  /** Configuración del flujo de autenticación per-tenant. */
  auth?: {
    /** Métodos permitidos en `/:tenant/auth`. Si solo hay 1, se redirige directo. */
    methods: AuthMethod[]
    /** Método preseleccionado en el selector cuando hay varios. */
    defaultMethod?: AuthMethod
  }
}
