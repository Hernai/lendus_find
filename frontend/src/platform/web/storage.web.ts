import { storage as syncStorage } from '@/utils/storage'
import type { PlatformStorage } from '../types'

/**
 * Implementación web de PlatformStorage.
 *
 * Envuelve `utils/storage.ts` (sync, localStorage) en una API async para
 * compatibilidad con Capacitor Preferences en native.
 */
export const storageWeb: PlatformStorage = {
  async get<T>(key: string, defaultValue: T | null = null): Promise<T | null> {
    return syncStorage.get<T>(key, defaultValue)
  },

  async set<T>(key: string, value: T, expiryMs?: number): Promise<void> {
    syncStorage.set<T>(key, value, expiryMs)
  },

  async remove(key: string): Promise<void> {
    syncStorage.remove(key)
  },

  async has(key: string): Promise<boolean> {
    return syncStorage.has(key)
  },

  async clear(): Promise<void> {
    syncStorage.clear()
  },

  async keys(): Promise<string[]> {
    return syncStorage.keys()
  },
}
