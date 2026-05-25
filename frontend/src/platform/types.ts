/**
 * Capa de plataforma — Interfaces.
 *
 * Define los contratos que cumplen las implementaciones `web/` y `native/`.
 * Regla: ninguna capa superior (http, services, stores, composables, views)
 * debe importar APIs de `window`, `document`, `navigator`, `localStorage` o
 * `@capacitor/*`. Todo el acceso a APIs de plataforma pasa por estas interfaces.
 */

export type Platform = 'web' | 'ios' | 'android'

export type PushProvider = 'fcm' | 'apns' | 'webpush'

export interface PlatformStorage {
  get<T = unknown>(key: string, defaultValue?: T | null): Promise<T | null>
  set<T = unknown>(key: string, value: T, expiryMs?: number): Promise<void>
  remove(key: string): Promise<void>
  has(key: string): Promise<boolean>
  clear(): Promise<void>
  keys(): Promise<string[]>
}

export interface PlatformNavigator {
  /** Ruta actual (path + query). */
  currentPath(): string
  /** Navegar a una ruta interna. */
  navigate(path: string, options?: { replace?: boolean }): Promise<void>
  /** Volver atrás en el historial. */
  back(): void
  /** Recargar la app. En native emite un evento para re-bootstrap. */
  reload(): void
}

export interface CameraOptions {
  facing?: 'user' | 'environment'
  maxWidth?: number
  maxHeight?: number
  /** 0..1 calidad JPEG. */
  quality?: number
  /** Para selfies: espeja horizontalmente el resultado. */
  mirror?: boolean
}

export interface CapturedImage {
  /** Datos base64 SIN el prefijo `data:image/...;base64,`. */
  base64: string
  mimeType: string
  width: number
  height: number
}

export interface PlatformCamera {
  isAvailable(): Promise<boolean>
  /** Captura desde cámara (en web: webcam vía getUserMedia; en native: cámara nativa). */
  capture(opts?: CameraOptions): Promise<CapturedImage | null>
  /** Selecciona de galería/archivos. */
  pickFromGallery(opts?: CameraOptions): Promise<CapturedImage | null>
}

export interface InAppNotification {
  id: string
  title: string
  body: string
  data?: Record<string, unknown>
}

export interface PushRegistration {
  token: string
  provider: PushProvider
}

export interface PlatformPush {
  isSupported(): boolean
  requestPermission(): Promise<'granted' | 'denied' | 'default'>
  register(): Promise<PushRegistration | null>
  unregister(): Promise<void>
  onNotificationReceived(cb: (n: InAppNotification) => void): () => void
  onNotificationOpened(cb: (n: InAppNotification) => void): () => void
}

export interface PlatformDevice {
  platform(): Platform
  appVersion(): string
  osVersion(): string | null
  deviceId(): Promise<string>
  isNative(): boolean
  /** Indica si es un User-Agent móvil (útil en web para layouts). */
  isMobileUserAgent(): boolean
}

export interface PlatformBrowser {
  /** Abre URL — `external:true` fuerza app externa en native. */
  open(url: string, opts?: { external?: boolean }): Promise<void>
  openEmail(to: string, subject?: string, body?: string): Promise<void>
  openWhatsApp(phone: string, text?: string): Promise<void>
  openTel(phone: string): Promise<void>
}

export interface PlatformShare {
  isSupported(): boolean
  share(payload: { title?: string; text?: string; url?: string }): Promise<void>
}

export interface PlatformClipboard {
  copy(text: string): Promise<void>
  read(): Promise<string | null>
}

export interface RealtimeOptions {
  tenantSlug?: string
}

export interface RealtimeChannel {
  listen(event: string, cb: (data: unknown) => void): RealtimeChannel
  stopListening(event: string): RealtimeChannel
  leave(): void
}

export interface PlatformRealtime {
  connect(token: string, opts?: RealtimeOptions): void
  disconnect(): void
  isConnected(): boolean
  privateChannel(name: string): RealtimeChannel
  presenceChannel(name: string): RealtimeChannel
}

export interface Platforms {
  storage: PlatformStorage
  navigator: PlatformNavigator
  camera: PlatformCamera
  push: PlatformPush
  device: PlatformDevice
  browser: PlatformBrowser
  share: PlatformShare
  clipboard: PlatformClipboard
  realtime: PlatformRealtime
}
