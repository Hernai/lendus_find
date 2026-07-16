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

/**
 * Personalizaciones HARD-CODED del tenant. Cuando un campo tiene valor,
 * ese pedazo del frontend NO respeta el branding configurable del admin
 * (es un componente .vue con look/copy propio del SOFOM, mantenido en
 * el repo y desplegado por release). El admin debe ver estos flags como
 * read-only para no engañar a quien configura.
 *
 * Si un campo es `undefined`/ausente, el sistema usa el flujo genérico
 * y respeta el branding del admin (TenantBranding en BD).
 */
export interface TenantCustomizations {
  /** Componente Vue dedicado para la página marketing (landing). */
  landing?: TenantLandingComponent
  /**
   * Identificador del flujo de auth custom. `undefined` = flujo estándar
   * (selector de método + OTP + PIN). Cuando un SOFOM pide pantallas con
   * branding extremo o métodos exóticos, se mete aquí.
   */
  authFlow?: string
  /** Componente del simulador custom (gráficos, CAT desglosado, etc.). */
  simulator?: string
  /** Dashboard post-login custom (promos, productos cruzados). */
  dashboard?: string
}

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
    /**
     * Color de fondo del icono adaptativo (HEX). Android/iOS no permiten
     * iconos con transparencia: el launcher siempre rellena el fondo con
     * un color sólido. Si se omite, usa `splashBackgroundColor`.
     */
    iconBackgroundColor?: string
    /**
     * PNG opcional con el wordmark para el splash de Android 12+
     * (windowSplashScreenBrandingImage, franja inferior de ~200×80dp).
     * Ideal ~560px de ancho sobre fondo transparente. Si se omite se
     * genera un placeholder transparente para que el recurso exista.
     */
    branding?: string
  }

  /**
   * Theme NATIVO. Afecta SOLO al splash screen + status bar de iOS/Android
   * (donde el OS no puede leer el branding del backend al vuelo).
   *
   * Para colores runtime de la SPA web/PWA (botones, navbar, etc.) la
   * fuente de verdad es `TenantBranding` en BD, configurable desde el
   * admin sin redeploy.
   */
  nativeTheme: {
    /** Color primario del tenant (HEX). Solo splash + status bar. */
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
   * Personalizaciones HARD-CODED del tenant. Cada campo poblado significa
   * que esa parte del frontend tiene un componente custom propio que NO
   * respeta el branding del admin (requiere release para cambiar). Ver
   * `TenantCustomizations` para detalle.
   *
   * Si necesitas que algo nuevo sea customizable hard, agrega el campo
   * al type y declara aqui qué componente lo implementa.
   */
  custom?: TenantCustomizations

  /** Configuración del flujo de autenticación per-tenant (métodos visibles). */
  auth?: {
    /** Métodos permitidos en `/:tenant/auth`. Si solo hay 1, se redirige directo. */
    methods: AuthMethod[]
    /** Método preseleccionado en el selector cuando hay varios. */
    defaultMethod?: AuthMethod
  }
}
