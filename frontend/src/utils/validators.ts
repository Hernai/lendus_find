/**
 * Centralized validation utilities for Mexican identification documents and data.
 *
 * These are pure functions that can be used anywhere in the application,
 * including outside Vue components (services, stores, etc.).
 *
 * @example
 * ```typescript
 * import { isValidCurp, isValidRfc, isValidPhone } from '@/utils/validators'
 *
 * if (isValidCurp(value)) {
 *   // value is typed as string
 * }
 * ```
 */

// =====================================================
// Generic Validators
// =====================================================

/**
 * Check if a value is a valid non-empty string ID.
 * Handles common invalid cases: null, undefined, 'null', 'undefined', empty string.
 */
export function isValidId(id: unknown): id is string {
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
export function isValidUuid(id: unknown): id is string {
  if (!isValidId(id)) return false
  const uuidRegex = /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i
  return uuidRegex.test(id)
}

/**
 * Sanitize an ID value, returning null if invalid.
 */
export function sanitizeId(id: unknown): string | null {
  return isValidId(id) ? id : null
}

// =====================================================
// Mexican Phone Validators
// =====================================================

/**
 * Check if a value is a valid Mexican phone number (10 digits).
 */
export function isValidPhone(phone: unknown): phone is string {
  if (typeof phone !== 'string') return false
  const cleaned = phone.replace(/\D/g, '')
  return cleaned.length === 10
}

/**
 * Extract only digits from a phone string.
 */
export function cleanPhone(phone: string): string {
  return phone.replace(/\D/g, '').slice(0, 10)
}

// =====================================================
// Email Validators
// =====================================================

/**
 * Check if a value is a valid email address.
 */
export function isValidEmail(email: unknown): email is string {
  if (typeof email !== 'string') return false
  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
  return emailRegex.test(email)
}

// =====================================================
// CURP Validators (Mexican ID)
// =====================================================

/**
 * CURP regex pattern for validation.
 * Format: 4 letters + 6 digits (DOB) + H/M (gender) + 2 letters (state) + 3 consonants + 1 homoclave + 1 digit
 */
const CURP_REGEX = /^[A-Z]{4}[0-9]{6}[HM][A-Z]{2}[A-Z]{3}[A-Z0-9][0-9]$/i

/**
 * Check if a value is a valid CURP (18 characters, specific format).
 */
export function isValidCurp(curp: unknown): curp is string {
  if (typeof curp !== 'string') return false
  if (curp.length !== 18) return false
  return CURP_REGEX.test(curp)
}

/**
 * Extract date of birth from CURP.
 * @returns Date string in YYYY-MM-DD format or null if invalid
 */
export function extractBirthDateFromCurp(curp: string): string | null {
  if (!isValidCurp(curp)) return null

  const yearPart = curp.substring(4, 6)
  const month = curp.substring(6, 8)
  const day = curp.substring(8, 10)

  // Determine century: 00-30 = 2000s, 31-99 = 1900s
  const yearNum = parseInt(yearPart, 10)
  const century = yearNum <= 30 ? '20' : '19'
  const year = `${century}${yearPart}`

  return `${year}-${month}-${day}`
}

/**
 * Extract gender from CURP.
 * @returns 'H' for male, 'M' for female, or null if invalid
 */
export function extractGenderFromCurp(curp: string): 'H' | 'M' | null {
  if (!isValidCurp(curp)) return null
  const gender = curp.charAt(10).toUpperCase()
  return gender === 'H' || gender === 'M' ? gender : null
}

/**
 * Map of CURP state codes to state names.
 */
export const CURP_STATE_CODES: Record<string, string> = {
  AS: 'Aguascalientes',
  BC: 'Baja California',
  BS: 'Baja California Sur',
  CC: 'Campeche',
  CL: 'Coahuila',
  CM: 'Colima',
  CS: 'Chiapas',
  CH: 'Chihuahua',
  DF: 'Ciudad de México',
  DG: 'Durango',
  GT: 'Guanajuato',
  GR: 'Guerrero',
  HG: 'Hidalgo',
  JC: 'Jalisco',
  MC: 'Estado de México',
  MN: 'Michoacán',
  MS: 'Morelos',
  NT: 'Nayarit',
  NL: 'Nuevo León',
  OC: 'Oaxaca',
  PL: 'Puebla',
  QT: 'Querétaro',
  QR: 'Quintana Roo',
  SP: 'San Luis Potosí',
  SL: 'Sinaloa',
  SR: 'Sonora',
  TC: 'Tabasco',
  TS: 'Tamaulipas',
  TL: 'Tlaxcala',
  VZ: 'Veracruz',
  YN: 'Yucatán',
  ZS: 'Zacatecas',
  NE: 'Nacido en el Extranjero',
}

/**
 * Extract birth state code from CURP.
 */
export function extractStateFromCurp(curp: string): string | null {
  if (!isValidCurp(curp)) return null
  return curp.substring(11, 13).toUpperCase()
}

/**
 * Get state name from CURP.
 */
export function getStateNameFromCurp(curp: string): string | null {
  const code = extractStateFromCurp(curp)
  return code ? CURP_STATE_CODES[code] ?? null : null
}

// =====================================================
// RFC Validators (Mexican Tax ID)
// =====================================================

/**
 * RFC regex pattern for persona física (13 characters).
 */
const RFC_PERSONA_FISICA_REGEX = /^[A-ZÑ&]{4}[0-9]{6}[A-Z0-9]{3}$/i

/**
 * RFC regex pattern for persona moral (12 characters).
 */
const RFC_PERSONA_MORAL_REGEX = /^[A-ZÑ&]{3}[0-9]{6}[A-Z0-9]{3}$/i

/**
 * Check if a value is a valid RFC (12 or 13 characters).
 */
export function isValidRfc(rfc: unknown): rfc is string {
  if (typeof rfc !== 'string') return false
  const length = rfc.length

  if (length === 13) {
    return RFC_PERSONA_FISICA_REGEX.test(rfc)
  }
  if (length === 12) {
    return RFC_PERSONA_MORAL_REGEX.test(rfc)
  }
  return false
}

/**
 * Check if RFC belongs to a persona física (individual).
 */
export function isPersonaFisica(rfc: string): boolean {
  return rfc.length === 13 && RFC_PERSONA_FISICA_REGEX.test(rfc)
}

/**
 * Check if RFC belongs to a persona moral (company).
 */
export function isPersonaMoral(rfc: string): boolean {
  return rfc.length === 12 && RFC_PERSONA_MORAL_REGEX.test(rfc)
}

// =====================================================
// Banking Validators
// =====================================================

/**
 * CLABE control digit weights for validation.
 */
const CLABE_WEIGHTS = [3, 7, 1, 3, 7, 1, 3, 7, 1, 3, 7, 1, 3, 7, 1, 3, 7]

/**
 * Check if a value is a valid CLABE (18 digits with valid control digit).
 */
export function isValidClabe(clabe: unknown): clabe is string {
  if (typeof clabe !== 'string') return false
  const cleaned = clabe.replace(/\D/g, '')
  if (cleaned.length !== 18) return false

  // Validate control digit
  let sum = 0
  for (let i = 0; i < 17; i++) {
    const digit = parseInt(cleaned[i]!, 10)
    sum += (digit * CLABE_WEIGHTS[i]!) % 10
  }
  const controlDigit = (10 - (sum % 10)) % 10
  return parseInt(cleaned[17]!, 10) === controlDigit
}

/**
 * Check if a value looks like a CLABE (18 digits, without control digit validation).
 */
export function looksLikeClabe(value: unknown): value is string {
  if (typeof value !== 'string') return false
  const cleaned = value.replace(/\D/g, '')
  return cleaned.length === 18
}

/**
 * Extract bank code from CLABE (first 3 digits).
 */
export function extractBankCodeFromClabe(clabe: string): string | null {
  const cleaned = clabe.replace(/\D/g, '')
  if (cleaned.length < 3) return null
  return cleaned.substring(0, 3)
}

/**
 * Check if a value is a valid debit card number (16 digits).
 */
export function isValidCardNumber(card: unknown): card is string {
  if (typeof card !== 'string') return false
  const cleaned = card.replace(/\D/g, '')
  return cleaned.length === 16
}

// =====================================================
// Address Validators
// =====================================================

/**
 * Check if a value is a valid Mexican postal code (5 digits).
 */
export function isValidPostalCode(cp: unknown): cp is string {
  if (typeof cp !== 'string') return false
  const cleaned = cp.replace(/\D/g, '')
  return cleaned.length === 5
}

// =====================================================
// INE Validators
// =====================================================

/**
 * Check if a value is a valid INE Clave de Elector (18 alphanumeric characters).
 */
export function isValidClaveElector(clave: unknown): clave is string {
  if (typeof clave !== 'string') return false
  if (clave.length !== 18) return false
  return /^[A-Z0-9]{18}$/i.test(clave)
}

/**
 * Check if a value is a valid INE OCR number (13 digits).
 */
export function isValidIneOcr(ocr: unknown): ocr is string {
  if (typeof ocr !== 'string') return false
  const cleaned = ocr.replace(/\D/g, '')
  return cleaned.length === 13
}

/**
 * Check if a value is a valid INE CIC/Folio number (9 digits).
 */
export function isValidIneCic(cic: unknown): cic is string {
  if (typeof cic !== 'string') return false
  const cleaned = cic.replace(/\D/g, '')
  return cleaned.length === 9
}
