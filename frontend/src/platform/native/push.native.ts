import { Capacitor } from '@capacitor/core'
import {
  PushNotifications,
  type Token,
  type PushNotificationSchema,
  type ActionPerformed,
} from '@capacitor/push-notifications'
import type { InAppNotification, PlatformPush, PushRegistration } from '../types'

/**
 * Implementación native de PlatformPush.
 *
 * Maneja el ciclo APNs (iOS) / FCM (Android) vía @capacitor/push-notifications.
 * `register()` devuelve el token cuando `registration` se emite. El
 * controlador `/v2/{rol}/devices` consume ese token (Fase 5).
 */
export const pushNative: PlatformPush = {
  isSupported(): boolean {
    return Capacitor.isPluginAvailable('PushNotifications')
  },

  async requestPermission(): Promise<'granted' | 'denied' | 'default'> {
    const result = await PushNotifications.requestPermissions()
    if (result.receive === 'granted') return 'granted'
    if (result.receive === 'denied') return 'denied'
    return 'default'
  },

  async register(): Promise<PushRegistration | null> {
    return new Promise<PushRegistration | null>((resolve, reject) => {
      let resolved = false
      const successListener = PushNotifications.addListener('registration', async (token: Token) => {
        if (resolved) return
        resolved = true
        await successListener.then((h) => h.remove())
        await errorListener.then((h) => h.remove())
        const platform = Capacitor.getPlatform()
        resolve({ token: token.value, provider: platform === 'ios' ? 'apns' : 'fcm' })
      })
      const errorListener = PushNotifications.addListener('registrationError', async (err) => {
        if (resolved) return
        resolved = true
        await successListener.then((h) => h.remove())
        await errorListener.then((h) => h.remove())
        reject(err)
      })
      PushNotifications.register().catch(reject)
    })
  },

  async unregister(): Promise<void> {
    await PushNotifications.removeAllListeners()
  },

  onNotificationReceived(cb: (n: InAppNotification) => void): () => void {
    const handle = PushNotifications.addListener(
      'pushNotificationReceived',
      (payload: PushNotificationSchema) => {
        cb({
          id: payload.id || '',
          title: payload.title || '',
          body: payload.body || '',
          data: payload.data,
        })
      },
    )
    return () => {
      handle.then((h) => h.remove())
    }
  },

  onNotificationOpened(cb: (n: InAppNotification) => void): () => void {
    const handle = PushNotifications.addListener(
      'pushNotificationActionPerformed',
      (action: ActionPerformed) => {
        const payload = action.notification
        cb({
          id: payload.id || '',
          title: payload.title || '',
          body: payload.body || '',
          data: payload.data,
        })
      },
    )
    return () => {
      handle.then((h) => h.remove())
    }
  },
}
