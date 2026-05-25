import { Preferences } from '@capacitor/preferences'
import type { PlatformStorage } from '../types'

/**
 * Implementación native de PlatformStorage usando @capacitor/preferences.
 *
 * Replica el contrato de la implementación web. Soporta TTL opcional
 * almacenando metadata junto al valor (mismo esquema que `utils/storage.ts`).
 */

interface StoredItem<T> {
  value: T
  expiry: number | null
}

const NAMESPACE = 'lendus:v1:'

function k(key: string): string {
  return NAMESPACE + key
}

export const storageNative: PlatformStorage = {
  async get<T>(key: string, defaultValue: T | null = null): Promise<T | null> {
    const { value } = await Preferences.get({ key: k(key) })
    if (value == null) return defaultValue
    try {
      const parsed = JSON.parse(value) as StoredItem<T>
      if (parsed.expiry && parsed.expiry < Date.now()) {
        await Preferences.remove({ key: k(key) })
        return defaultValue
      }
      return parsed.value
    } catch {
      // Cadena cruda (legacy) — devolver tal cual.
      return value as unknown as T
    }
  },

  async set<T>(key: string, value: T, expiryMs?: number): Promise<void> {
    const item: StoredItem<T> = {
      value,
      expiry: expiryMs ? Date.now() + expiryMs : null,
    }
    await Preferences.set({ key: k(key), value: JSON.stringify(item) })
  },

  async remove(key: string): Promise<void> {
    await Preferences.remove({ key: k(key) })
  },

  async has(key: string): Promise<boolean> {
    const result = await this.get(key)
    return result !== null
  },

  async clear(): Promise<void> {
    const { keys } = await Preferences.keys()
    await Promise.all(
      keys.filter((k) => k.startsWith(NAMESPACE)).map((k) => Preferences.remove({ key: k })),
    )
  },

  async keys(): Promise<string[]> {
    const { keys } = await Preferences.keys()
    return keys.filter((k) => k.startsWith(NAMESPACE)).map((k) => k.slice(NAMESPACE.length))
  },
}
