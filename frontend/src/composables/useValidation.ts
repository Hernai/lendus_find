/**
 * Composable for common validation utilities.
 *
 * Centralizes ID validation logic that was duplicated across stores.
 */
export function useValidation() {
  /**
   * Check if a value is a valid non-empty string ID.
   *
   * Handles common invalid cases: null, undefined, 'null', 'undefined', empty string.
   */
  const isValidId = (id: unknown): id is string => {
    return (
      typeof id === 'string' &&
      id !== 'null' &&
      id !== 'undefined' &&
      id !== '' &&
      id.length > 0
    )
  }

  /**
   * Check if a value is a valid UUID v4.
   */
  const isValidUuid = (id: unknown): id is string => {
    if (!isValidId(id)) return false
    const uuidRegex = /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i
    return uuidRegex.test(id)
  }

  /**
   * Check if a value is a valid Mexican phone number (10 digits).
   */
  const isValidPhone = (phone: unknown): phone is string => {
    if (typeof phone !== 'string') return false
    const cleaned = phone.replace(/\D/g, '')
    return cleaned.length === 10
  }

  /**
   * Check if a value is a valid email.
   */
  const isValidEmail = (email: unknown): email is string => {
    if (typeof email !== 'string') return false
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
    return emailRegex.test(email)
  }

  /**
   * Check if a value is a valid CURP (18 characters, specific format).
   */
  const isValidCurp = (curp: unknown): curp is string => {
    if (typeof curp !== 'string') return false
    if (curp.length !== 18) return false
    const curpRegex = /^[A-Z]{4}[0-9]{6}[HM][A-Z]{2}[A-Z]{3}[A-Z0-9][0-9]$/i
    return curpRegex.test(curp)
  }

  /**
   * Check if a value is a valid RFC (12 or 13 characters).
   */
  const isValidRfc = (rfc: unknown): rfc is string => {
    if (typeof rfc !== 'string') return false
    const length = rfc.length
    if (length !== 12 && length !== 13) return false

    const pattern = length === 13
      ? /^[A-Z]{4}[0-9]{6}[A-Z0-9]{3}$/i // Persona física
      : /^[A-Z]{3}[0-9]{6}[A-Z0-9]{3}$/i // Persona moral

    return pattern.test(rfc)
  }

  /**
   * Check if a value is a valid CLABE (18 digits).
   */
  const isValidClabe = (clabe: unknown): clabe is string => {
    if (typeof clabe !== 'string') return false
    const cleaned = clabe.replace(/\D/g, '')
    return cleaned.length === 18
  }

  /**
   * Check if a value is a valid postal code (5 digits).
   */
  const isValidPostalCode = (cp: unknown): cp is string => {
    if (typeof cp !== 'string') return false
    const cleaned = cp.replace(/\D/g, '')
    return cleaned.length === 5
  }

  /**
   * Sanitize an ID value, returning null if invalid.
   */
  const sanitizeId = (id: unknown): string | null => {
    return isValidId(id) ? id : null
  }

  return {
    isValidId,
    isValidUuid,
    isValidPhone,
    isValidEmail,
    isValidCurp,
    isValidRfc,
    isValidClabe,
    isValidPostalCode,
    sanitizeId,
  }
}

/**
 * Standalone validation functions for use outside Vue components.
 */
export const validation = {
  isValidId: (id: unknown): id is string => {
    return (
      typeof id === 'string' &&
      id !== 'null' &&
      id !== 'undefined' &&
      id !== '' &&
      id.length > 0
    )
  },

  isValidUuid: (id: unknown): id is string => {
    if (!validation.isValidId(id)) return false
    const uuidRegex = /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i
    return uuidRegex.test(id)
  },

  sanitizeId: (id: unknown): string | null => {
    return validation.isValidId(id) ? id : null
  },
}
