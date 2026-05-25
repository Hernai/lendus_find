import { ref } from 'vue'
import { platform } from '@/platform'
import { api } from '@/http'
import { logger } from '@/utils/logger'
import { useAuthStore } from '@/stores/auth'

const log = logger.child('PushPermission')

/**
 * Composable que centraliza:
 * - Solicitar permiso de notificaciones (nativo o web).
 * - Obtener el push token (APNs/FCM/WebPush) vía `platform.push`.
 * - Registrarlo contra el backend en POST /v2/{rol}/devices.
 * - Revocarlo en logout vía DELETE /v2/{rol}/devices/{token}.
 *
 * Se diseña como singleton implícito: cada vista que necesite el estado
 * llama `useNotificationPermission()`. El estado interno se mantiene en
 * el módulo y no se duplica.
 */

const status = ref<'idle' | 'requesting' | 'granted' | 'denied' | 'unsupported'>('idle')
const registeredToken = ref<string | null>(null)
const lastError = ref<string | null>(null)

export function useNotificationPermission() {
  const authStore = useAuthStore()

  async function ensureRegistered(): Promise<boolean> {
    if (!platform.push.isSupported()) {
      status.value = 'unsupported'
      return false
    }
    if (status.value === 'granted' && registeredToken.value) {
      return true
    }

    status.value = 'requesting'
    lastError.value = null

    try {
      const permission = await platform.push.requestPermission()
      if (permission !== 'granted') {
        status.value = 'denied'
        return false
      }

      const registration = await platform.push.register()
      if (!registration) {
        status.value = 'denied'
        return false
      }

      const rolePath = authStore.isStaff ? '/v2/staff/devices' : '/v2/applicant/devices'
      const payload = {
        token: registration.token,
        provider: registration.provider,
        platform: platform.device.platform(),
        app_version: platform.device.appVersion(),
        device_id: await platform.device.deviceId(),
      }
      await api.post(rolePath, payload)

      registeredToken.value = registration.token
      status.value = 'granted'
      log.debug('Push registrado', { provider: registration.provider })
      return true
    } catch (err) {
      const msg = err instanceof Error ? err.message : String(err)
      log.warn('Push registration failed', { error: msg })
      lastError.value = msg
      status.value = 'denied'
      return false
    }
  }

  async function unregister(): Promise<void> {
    if (!registeredToken.value) return
    try {
      const rolePath = authStore.isStaff
        ? `/v2/staff/devices/${encodeURIComponent(registeredToken.value)}`
        : `/v2/applicant/devices/${encodeURIComponent(registeredToken.value)}`
      await api.delete(rolePath)
    } catch (err) {
      log.warn('Push unregister API failed', { error: err })
    }
    try {
      await platform.push.unregister()
    } catch {
      /* noop */
    }
    registeredToken.value = null
    status.value = 'idle'
  }

  return {
    status,
    registeredToken,
    lastError,
    ensureRegistered,
    unregister,
  }
}
