import { Contacts } from '@capacitor-community/contacts'
import type { PickedContact, PlatformContacts } from '../types'

/**
 * Implementación native de PlatformContacts usando el plugin de contactos de Capacitor.
 *
 * Abre el selector de contactos del sistema (picker nativo) y devuelve el
 * nombre y el primer teléfono del contacto elegido. Requiere permisos:
 * iOS `NSContactsUsageDescription`, Android `READ_CONTACTS`.
 *
 * Si el usuario cancela o niega el permiso, devuelve `null` sin lanzar para
 * que el paso pueda continuar con captura manual (fallback).
 */
export const contactsNative: PlatformContacts = {
  isSupported(): boolean {
    return true
  },

  async pickContact(): Promise<PickedContact | null> {
    try {
      // Solo pedimos nombre y teléfonos: es lo único que prellena la referencia.
      const result = await Contacts.pickContact({
        projection: { name: true, phones: true },
      })

      const contact = result?.contact
      if (!contact) return null

      const name = contact.name?.display ?? ''
      const phone =
        contact.phones?.find((p) => !!p.number)?.number ?? contact.phones?.[0]?.number ?? ''

      // Contacto sin nombre ni teléfono útil: se trata como cancelación.
      if (!name && !phone) return null

      return { name, phone }
    } catch {
      // Permiso denegado o error del picker: no romper el flujo.
      return null
    }
  },
}
