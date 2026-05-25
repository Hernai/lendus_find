import type { InAppNotification, PlatformPush, PushRegistration } from '../types'

/**
 * Implementación web de PlatformPush.
 *
 * Para LendusFind web, el canal principal de tiempo real es el WebSocket
 * (Reverb), por lo que `register()` está deshabilitado por defecto. Si en el
 * futuro se requiere Web Push real (VAPID + Service Worker), se implementará
 * aquí registrando el endpoint vía `pushManager.subscribe(...)` y devolviendo
 * el provider `webpush`.
 *
 * Los callbacks `onNotificationReceived` / `onNotificationOpened` se mantienen
 * para paridad de API con el adapter nativo; en web los stores conectados a
 * Echo siguen siendo la fuente de eventos in-app.
 */

const noopUnsubscribe = () => undefined

export const pushWeb: PlatformPush = {
  isSupported(): boolean {
    if (typeof window === 'undefined') return false
    return 'Notification' in window && 'serviceWorker' in navigator && 'PushManager' in window
  },

  async requestPermission(): Promise<'granted' | 'denied' | 'default'> {
    if (typeof window === 'undefined' || !('Notification' in window)) return 'denied'
    const result = await Notification.requestPermission()
    return result
  },

  async register(): Promise<PushRegistration | null> {
    // Stub — implementar VAPID + Service Worker en una fase futura si se necesita
    // push web nativo. Por ahora retornamos null y delegamos in-app al WebSocket.
    return null
  },

  async unregister(): Promise<void> {
    /* noop */
  },

  onNotificationReceived(_cb: (n: InAppNotification) => void): () => void {
    return noopUnsubscribe
  },

  onNotificationOpened(_cb: (n: InAppNotification) => void): () => void {
    return noopUnsubscribe
  },
}
