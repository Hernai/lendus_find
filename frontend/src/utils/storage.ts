/**
 * Centralized localStorage manager.
 *
 * Provides namespaced, type-safe storage with expiration support
 * and automatic JSON serialization.
 *
 * @example
 * ```typescript
 * import { storage } from '@/utils/storage'
 *
 * // Simple get/set
 * storage.set('user_prefs', { theme: 'dark' })
 * const prefs = storage.get<{ theme: string }>('user_prefs')
 *
 * // With expiration (30 minutes)
 * storage.set('session_cache', data, 30 * 60 * 1000)
 *
 * // Check existence
 * if (storage.has('auth_token')) { ... }
 *
 * // Remove
 * storage.remove('temp_data')
 * ```
 */

const NAMESPACE = 'lendus:'
const VERSION = 'v1:'

interface StoredItem<T> {
  value: T
  expiry: number | null
  version: string
  createdAt: number
}

/**
 * Check if localStorage is available.
 */
function isStorageAvailable(): boolean {
  try {
    const test = '__storage_test__'
    localStorage.setItem(test, test)
    localStorage.removeItem(test)
    return true
  } catch {
    return false
  }
}

const storageAvailable = isStorageAvailable()

/**
 * Get the full key with namespace and version.
 */
function getFullKey(key: string): string {
  return `${NAMESPACE}${VERSION}${key}`
}

/**
 * Parse stored item, handling legacy formats.
 */
function parseStoredItem<T>(raw: string): T | null {
  try {
    const parsed = JSON.parse(raw)

    // Handle new format with metadata
    if (parsed && typeof parsed === 'object' && 'value' in parsed && 'version' in parsed) {
      const item = parsed as StoredItem<T>

      // Check expiration
      if (item.expiry && item.expiry < Date.now()) {
        return null
      }

      return item.value
    }

    // Handle legacy format (raw value)
    return parsed as T
  } catch {
    // Return as string if not valid JSON
    return raw as unknown as T
  }
}

export const storage = {
  /**
   * Set a value in storage.
   * @param key Storage key
   * @param value Value to store
   * @param expiryMs Optional expiration in milliseconds
   */
  set<T>(key: string, value: T, expiryMs?: number): boolean {
    if (!storageAvailable) return false

    try {
      const item: StoredItem<T> = {
        value,
        expiry: expiryMs ? Date.now() + expiryMs : null,
        version: VERSION,
        createdAt: Date.now(),
      }

      localStorage.setItem(getFullKey(key), JSON.stringify(item))
      return true
    } catch (error) {
      // Storage might be full
      console.warn(`[Storage] Failed to set "${key}":`, error)
      return false
    }
  },

  /**
   * Get a value from storage.
   * @param key Storage key
   * @param defaultValue Default value if key doesn't exist or is expired
   */
  get<T>(key: string, defaultValue: T | null = null): T | null {
    if (!storageAvailable) return defaultValue

    try {
      const fullKey = getFullKey(key)
      const raw = localStorage.getItem(fullKey)

      if (raw === null) {
        // Try legacy key without namespace/version
        const legacyRaw = localStorage.getItem(key)
        if (legacyRaw !== null) {
          return parseStoredItem<T>(legacyRaw)
        }
        return defaultValue
      }

      const value = parseStoredItem<T>(raw)

      // If expired, remove and return default
      if (value === null) {
        localStorage.removeItem(fullKey)
        return defaultValue
      }

      return value
    } catch {
      return defaultValue
    }
  },

  /**
   * Remove a value from storage.
   */
  remove(key: string): void {
    if (!storageAvailable) return

    localStorage.removeItem(getFullKey(key))
    // Also try to remove legacy key
    localStorage.removeItem(key)
  },

  /**
   * Check if a key exists and is not expired.
   */
  has(key: string): boolean {
    return this.get(key) !== null
  },

  /**
   * Clear all namespaced storage.
   */
  clear(): void {
    if (!storageAvailable) return

    const keysToRemove: string[] = []

    for (let i = 0; i < localStorage.length; i++) {
      const key = localStorage.key(i)
      if (key && key.startsWith(NAMESPACE)) {
        keysToRemove.push(key)
      }
    }

    keysToRemove.forEach((key) => localStorage.removeItem(key))
  },

  /**
   * Get all keys in the namespace.
   */
  keys(): string[] {
    if (!storageAvailable) return []

    const keys: string[] = []
    const prefix = getFullKey('')

    for (let i = 0; i < localStorage.length; i++) {
      const key = localStorage.key(i)
      if (key && key.startsWith(prefix)) {
        keys.push(key.slice(prefix.length))
      }
    }

    return keys
  },

  /**
   * Get storage usage info.
   */
  getUsage(): { used: number; keys: number } {
    if (!storageAvailable) return { used: 0, keys: 0 }

    let used = 0
    let keys = 0
    const prefix = NAMESPACE

    for (let i = 0; i < localStorage.length; i++) {
      const key = localStorage.key(i)
      if (key && key.startsWith(prefix)) {
        const value = localStorage.getItem(key)
        used += (key.length + (value?.length || 0)) * 2 // UTF-16 = 2 bytes per char
        keys++
      }
    }

    return { used, keys }
  },
}

/**
 * Storage keys used throughout the application.
 * Centralized here to prevent typos and enable IDE autocomplete.
 */
export const STORAGE_KEYS = {
  AUTH_TOKEN: 'auth_token',
  REFRESH_TOKEN: 'refresh_token',
  CURRENT_USER_ID: 'current_user_id',
  CURRENT_USER_TYPE: 'current_user_type', // 'staff' | 'applicant'
  CURRENT_TENANT_ID: 'current_tenant_id',
  CURRENT_TENANT_SLUG: 'current_tenant_slug',
  CURRENT_APPLICATION_ID: 'current_application_id',
  PENDING_APPLICATION: 'pending_application',
  SELECTED_TENANT_ID: 'selected_tenant_id',
  ONBOARDING_STEP: 'onboarding_step',
  ONBOARDING_DATA: 'onboarding_data',
  KYC_DATA: 'kyc_data',
  THEME: 'theme',
  LOCALE: 'locale',
} as const

export type StorageKey = (typeof STORAGE_KEYS)[keyof typeof STORAGE_KEYS]
