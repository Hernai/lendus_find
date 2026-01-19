/**
 * Utilities Index
 *
 * Central export point for all utility functions.
 */

// Formatters
export {
  // Currency
  formatMoney,
  formatCurrency,
  formatMoneyDecimals,
  formatMoneyShort,
  // Phone
  formatPhone,
  formatPhoneInput,
  // Date
  formatDate,
  formatDateShort,
  formatDateOnly,
  formatDateTime,
  formatTimeOnly,
  formatDateForApi,
  // Employment
  formatSeniority,
  formatEmploymentType,
  formatContractType,
  // Gender
  formatGender,
  // Payment
  formatFrequency,
  // Reference
  getTypeFromRelationship,
  // Percentage
  formatPercentage,
  // Text
  truncate,
} from './formatters'

// Storage
export { storage, STORAGE_KEYS } from './storage'
export type { StorageKey } from './storage'

// Logger
export { logger } from './logger'

// Tenant
export { detectTenantSlug } from './tenant'

// Validators
export {
  // Generic
  isValidId,
  isValidUuid,
  sanitizeId,
  // Phone
  isValidPhone,
  cleanPhone,
  // Email
  isValidEmail,
  // CURP
  isValidCurp,
  extractBirthDateFromCurp,
  extractGenderFromCurp,
  extractStateFromCurp,
  getStateNameFromCurp,
  CURP_STATE_CODES,
  // RFC
  isValidRfc,
  isPersonaFisica,
  isPersonaMoral,
  // Banking
  isValidClabe,
  looksLikeClabe,
  extractBankCodeFromClabe,
  isValidCardNumber,
  // Address
  isValidPostalCode,
  // INE
  isValidClaveElector,
  isValidIneOcr,
  isValidIneCic,
} from './validators'
