import type { PickedContact, PlatformContacts } from '../types'

/**
 * Implementación web de PlatformContacts — no-op.
 *
 * El navegador/PWA no expone la agenda del dispositivo, así que el picker
 * no está soportado y `pickContact()` devuelve `null` sin lanzar. En web el
 * paso de referencias usa la captura manual (fallback).
 */
export const contactsWeb: PlatformContacts = {
  isSupported(): boolean {
    return false
  },

  async pickContact(): Promise<PickedContact | null> {
    return null
  },
}
