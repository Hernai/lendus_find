import { Capacitor } from '@capacitor/core'
import { Device } from '@capacitor/device'
import { App } from '@capacitor/app'
import type { Platform, PlatformDevice } from '../types'

let cachedDeviceId: string | null = null
let cachedAppVersion: string | null = null

async function fetchAppVersion(): Promise<string> {
  if (cachedAppVersion) return cachedAppVersion
  try {
    const info = await App.getInfo()
    cachedAppVersion = info.version
  } catch {
    cachedAppVersion = (import.meta.env.VITE_APP_VERSION as string | undefined) ?? '0.0.0'
  }
  return cachedAppVersion
}

// Pre-warm el cache para que appVersion() sync devuelva el valor real luego.
void fetchAppVersion()

export const deviceNative: PlatformDevice = {
  platform(): Platform {
    const p = Capacitor.getPlatform()
    return p === 'ios' || p === 'android' ? p : 'web'
  },

  appVersion(): string {
    return cachedAppVersion ?? ((import.meta.env.VITE_APP_VERSION as string | undefined) ?? '0.0.0')
  },

  osVersion(): string | null {
    return null
  },

  async deviceId(): Promise<string> {
    if (cachedDeviceId) return cachedDeviceId
    const info = await Device.getId()
    cachedDeviceId = info.identifier
    return cachedDeviceId
  },

  isNative(): boolean {
    return Capacitor.isNativePlatform()
  },

  isMobileUserAgent(): boolean {
    return true
  },
}
