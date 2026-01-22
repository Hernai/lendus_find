/**
 * Centralized formatting utilities for the application.
 *
 * Use these functions instead of defining local formatters in components
 * to ensure consistency and reduce code duplication.
 *
 * @example
 * ```typescript
 * import { formatMoney, formatPhone, formatDate } from '@/utils/formatters'
 *
 * const price = formatMoney(50000) // "$50,000"
 * const phone = formatPhone('5512345678') // "55 1234 5678"
 * const date = formatDate('2024-01-15') // "15 de enero de 2024"
 * ```
 */

// =====================================================
// Currency Formatters
// =====================================================

/**
 * Format a number as Mexican Peso currency.
 * @param amount - The amount to format
 * @param decimals - Whether to show decimal places (default: false)
 * @returns Formatted currency string (e.g., "$50,000" or "$50,000.00")
 */
export function formatMoney(amount: number | null | undefined, decimals = false): string {
  if (amount === null || amount === undefined) return '-'
  return new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
    minimumFractionDigits: decimals ? 2 : 0,
    maximumFractionDigits: decimals ? 2 : 0,
  }).format(amount)
}

/**
 * Alias for formatMoney (for backwards compatibility).
 */
export const formatCurrency = formatMoney

/**
 * Format currency with decimals.
 * @param amount - The amount to format
 * @returns Formatted currency string with 2 decimal places
 */
export function formatMoneyDecimals(amount: number | null | undefined): string {
  return formatMoney(amount, true)
}

/**
 * Format money in short form (e.g., "$50K", "$1.2M").
 * @param amount - The amount to format
 * @returns Short formatted string
 */
export function formatMoneyShort(amount: number | null | undefined): string {
  if (amount === null || amount === undefined) return '-'
  if (amount >= 1000000) {
    return `$${(amount / 1000000).toFixed(1)}M`
  }
  if (amount >= 1000) {
    return `$${(amount / 1000).toFixed(0)}K`
  }
  return formatMoney(amount)
}

// =====================================================
// Phone Formatters
// =====================================================

/** Maximum digits for Mexican phone numbers */
export const PHONE_MAX_DIGITS = 10

/** Maximum length including formatting spaces (XX XXXX XXXX = 12 chars) */
export const PHONE_MAX_LENGTH_FORMATTED = 12

/** Centralized configuration for phone inputs */
export const PHONE_INPUT_CONFIG = {
  /** Max digits (10 for Mexican phones) */
  maxDigits: PHONE_MAX_DIGITS,
  /** Max length for input field (formatted with spaces) */
  maxLength: PHONE_MAX_LENGTH_FORMATTED,
  /** Placeholder text */
  placeholder: '55 1234 5678',
  /** Input mode for mobile keyboards */
  inputMode: 'numeric' as const,
  /** Input type */
  type: 'tel' as const,
} as const

/**
 * Format a Mexican phone number (10 digits) with spaces.
 * @param phone - The phone number to format
 * @returns Formatted phone string (e.g., "55 1234 5678")
 */
export function formatPhone(phone: string | null | undefined): string {
  if (!phone) return '-'
  const digits = phone.replace(/\D/g, '').slice(0, PHONE_MAX_DIGITS)
  if (digits.length === 10) {
    return `${digits.slice(0, 2)} ${digits.slice(2, 6)} ${digits.slice(6)}`
  }
  if (digits.length >= 6) {
    return `${digits.slice(0, 2)} ${digits.slice(2, 6)} ${digits.slice(6)}`
  }
  return digits
}

/**
 * Format phone input as user types (for input handlers).
 * @param value - The raw input value
 * @returns Formatted phone string
 */
export function formatPhoneInput(value: string): string {
  const digits = value.replace(/\D/g, '').slice(0, PHONE_MAX_DIGITS)
  if (digits.length >= 6) {
    return `${digits.slice(0, 2)} ${digits.slice(2, 6)} ${digits.slice(6)}`
  }
  if (digits.length >= 2) {
    return `${digits.slice(0, 2)} ${digits.slice(2)}`
  }
  return digits
}

/**
 * Extract only digits from a phone value.
 * @param value - The formatted or unformatted value
 * @returns Digits only (max 10)
 */
export function stripPhoneFormatting(value: string | null | undefined): string {
  return (value || '').replace(/\D/g, '').slice(0, PHONE_MAX_DIGITS)
}

// =====================================================
// Date Formatters
// =====================================================

/**
 * Format a date string to long Spanish format.
 * @param dateStr - ISO date string or Date
 * @returns Formatted date (e.g., "15 de enero de 2024")
 */
export function formatDate(dateStr: string | Date | null | undefined): string {
  if (!dateStr) return '-'
  const date = typeof dateStr === 'string' ? new Date(dateStr) : dateStr
  if (isNaN(date.getTime())) return '-'
  return date.toLocaleDateString('es-MX', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  })
}

/**
 * Format a date string to short format (day and month only).
 * @param dateStr - ISO date string or Date
 * @returns Formatted date (e.g., "15 ene")
 */
export function formatDateShort(dateStr: string | Date | null | undefined): string {
  if (!dateStr) return '-'
  const date = typeof dateStr === 'string' ? new Date(dateStr) : dateStr
  if (isNaN(date.getTime())) return '-'
  return date.toLocaleDateString('es-MX', {
    day: 'numeric',
    month: 'short',
  })
}

/**
 * Format a date string to date only (no time).
 * @param dateStr - ISO date string or Date
 * @returns Formatted date (e.g., "15/01/2024")
 */
export function formatDateOnly(dateStr: string | Date | null | undefined): string {
  if (!dateStr) return 'Nunca'
  const date = typeof dateStr === 'string' ? new Date(dateStr) : dateStr
  if (isNaN(date.getTime())) return 'Nunca'
  return date.toLocaleDateString('es-MX', {
    day: 'numeric',
    month: 'short',
  })
}

/**
 * Format a date string with date and time.
 * @param dateStr - ISO date string or Date
 * @returns Formatted datetime (e.g., "15 ene 2024, 14:30")
 */
export function formatDateTime(dateStr: string | Date | null | undefined): string {
  if (!dateStr) return '-'
  const date = typeof dateStr === 'string' ? new Date(dateStr) : dateStr
  if (isNaN(date.getTime())) return '-'
  return date.toLocaleString('es-MX', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

/**
 * Format time only from a date string.
 * @param dateStr - ISO date string or Date
 * @returns Formatted time (e.g., "14:30")
 */
export function formatTimeOnly(dateStr: string | Date | null | undefined): string {
  if (!dateStr) return ''
  const date = typeof dateStr === 'string' ? new Date(dateStr) : dateStr
  if (isNaN(date.getTime())) return ''
  return date.toLocaleTimeString('es-MX', {
    hour: '2-digit',
    minute: '2-digit',
  })
}

/**
 * Format a date as relative time (e.g., "Hace 5 min", "Hace 2h", "Hace 3d").
 * @param dateStr - ISO date string or Date
 * @returns Relative time string
 */
export function formatTimeAgo(dateStr: string | Date | null | undefined): string {
  if (!dateStr) return ''
  const date = typeof dateStr === 'string' ? new Date(dateStr) : dateStr
  if (isNaN(date.getTime())) return ''

  const now = new Date()
  const diffMs = now.getTime() - date.getTime()
  const diffMins = Math.floor(diffMs / 60000)
  const diffHours = Math.floor(diffMs / 3600000)
  const diffDays = Math.floor(diffMs / 86400000)

  if (diffMins < 1) return 'Ahora'
  if (diffMins < 60) return `Hace ${diffMins} min`
  if (diffHours < 24) return `Hace ${diffHours}h`
  if (diffDays < 30) return `Hace ${diffDays}d`
  return formatDateShort(date)
}

/**
 * Format a date for API submission (YYYY-MM-DD).
 * @param dateStr - Date string in any format
 * @returns ISO date string (YYYY-MM-DD) or empty string
 */
export function formatDateForApi(dateStr: string | null | undefined): string {
  if (!dateStr) return ''
  // If already in ISO format, extract date part
  if (dateStr.includes('T')) {
    return dateStr.split('T')[0] ?? ''
  }
  // Handle DD/MM/YYYY format
  const parts = dateStr.split('/')
  if (parts.length === 3) {
    const day = parts[0] ?? ''
    const month = parts[1] ?? ''
    const year = parts[2] ?? ''
    return `${year}-${month.padStart(2, '0')}-${day.padStart(2, '0')}`
  }
  return dateStr
}

// =====================================================
// Employment/Seniority Formatters
// =====================================================

/**
 * Format seniority in months to human-readable string.
 * @param months - Number of months
 * @returns Formatted string (e.g., "2 años, 3 meses" or "8 meses")
 */
export function formatSeniority(months: number | null | undefined): string {
  if (months === null || months === undefined) return '-'
  const years = Math.floor(months / 12)
  const remainingMonths = months % 12

  if (years === 0) {
    return `${remainingMonths} ${remainingMonths === 1 ? 'mes' : 'meses'}`
  }
  if (remainingMonths === 0) {
    return `${years} ${years === 1 ? 'año' : 'años'}`
  }
  return `${years} ${years === 1 ? 'año' : 'años'}, ${remainingMonths} ${remainingMonths === 1 ? 'mes' : 'meses'}`
}

// =====================================================
// Gender Formatters
// =====================================================

// Cached options from backend
let cachedGenderOptions: Array<{ value: string; label: string }> = []

/**
 * Set gender options from tenant config.
 * @param options - Array of { value, label } from backend Gender enum
 */
export function setGenderOptions(options: Array<{ value: string; label: string }>): void {
  cachedGenderOptions = options
}

/**
 * Format gender code to Spanish display text.
 * @param gender - Gender code (M/F/H)
 * @returns Spanish gender string
 */
export function formatGender(gender: string | null | undefined): string {
  if (!gender) return '-'
  const found = cachedGenderOptions.find(opt => opt.value === gender || opt.value === gender.toUpperCase())
  return found?.label || gender
}

// =====================================================
// Employment Type Formatters
// =====================================================

// Cached options from backend
let cachedEmploymentTypeOptions: Array<{ value: string; label: string }> = []

/**
 * Set employment type options from tenant config.
 * @param options - Array of { value, label } from backend EmploymentType enum
 */
export function setEmploymentTypeOptions(options: Array<{ value: string; label: string }>): void {
  cachedEmploymentTypeOptions = options
}

/**
 * Format employment type code to Spanish display text.
 * @param type - Employment type code
 * @returns Spanish employment type string
 */
export function formatEmploymentType(type: string | null | undefined): string {
  if (!type) return '-'
  const found = cachedEmploymentTypeOptions.find(opt => opt.value === type)
  return found?.label || type
}

// =====================================================
// Contract Type Formatters
// =====================================================

const contractTypeLabels: Record<string, string> = {
  PERMANENT: 'Indefinido',
  TEMPORARY: 'Temporal',
  FREELANCE: 'Freelance',
  INTERNSHIP: 'Prácticas',
}

/**
 * Format contract type code to Spanish display text.
 * @param type - Contract type code
 * @returns Spanish contract type string
 */
export function formatContractType(type: string | null | undefined): string {
  if (!type) return '-'
  return contractTypeLabels[type] || type
}

// =====================================================
// Payment Frequency Formatters
// =====================================================

// Cached options from backend
let cachedFrequencyOptions: Array<{ value: string; label: string }> = []

/**
 * Set payment frequency options from tenant config.
 * @param options - Array of { value, label } from backend PaymentFrequency enum
 */
export function setFrequencyOptions(options: Array<{ value: string; label: string }>): void {
  cachedFrequencyOptions = options
}

/**
 * Format payment frequency code to Spanish display text.
 * @param frequency - Frequency code
 * @returns Spanish frequency string (lowercase)
 */
export function formatFrequency(frequency: string | null | undefined): string {
  if (!frequency) return '-'
  const found = cachedFrequencyOptions.find(opt => opt.value === frequency)
  return found?.label.toLowerCase() || frequency.toLowerCase()
}

// =====================================================
// Reference Type Formatters
// =====================================================

/**
 * Determine reference type from relationship.
 * @param relationship - Relationship string
 * @returns 'PERSONAL' or 'WORK'
 */
export function getTypeFromRelationship(relationship: string): 'PERSONAL' | 'WORK' {
  const workRelationships = ['JEFE', 'COMPAÑERO', 'SUPERVISOR', 'EMPLEADOR', 'COLEGA', 'JEFE_DIRECTO', 'COMPAÑERO_TRABAJO']
  return workRelationships.includes(relationship.toUpperCase()) ? 'WORK' : 'PERSONAL'
}

// =====================================================
// Percentage Formatters
// =====================================================

/**
 * Format a decimal or number as percentage.
 * @param value - The value to format (0.36 or 36)
 * @param isDecimal - Whether the value is already decimal (default: false)
 * @returns Formatted percentage string (e.g., "36%")
 */
export function formatPercentage(value: number | null | undefined, isDecimal = false): string {
  if (value === null || value === undefined) return '-'
  const percent = isDecimal ? value * 100 : value
  return `${percent.toFixed(percent % 1 === 0 ? 0 : 1)}%`
}

// =====================================================
// Truncation Helpers
// =====================================================

/**
 * Truncate text to a maximum length with ellipsis.
 * @param text - The text to truncate
 * @param maxLength - Maximum length (default: 50)
 * @returns Truncated text with ellipsis if needed
 */
export function truncate(text: string | null | undefined, maxLength = 50): string {
  if (!text) return ''
  if (text.length <= maxLength) return text
  return `${text.slice(0, maxLength)}...`
}

// =====================================================
// Housing Type Formatters
// =====================================================

// Cached options from backend
let cachedHousingTypeOptions: Array<{ value: string; label: string }> = []

/**
 * Set housing type options from tenant config.
 * @param options - Array of { value, label } from backend HousingType enum
 */
export function setHousingTypeOptions(options: Array<{ value: string; label: string }>): void {
  cachedHousingTypeOptions = options
}

/**
 * Format housing type code to Spanish display text.
 * @param type - Housing type code
 * @returns Spanish housing type string
 */
export function formatHousingType(type: string | null | undefined): string {
  if (!type) return '-'
  const found = cachedHousingTypeOptions.find(opt => opt.value === type)
  return found?.label || type
}

// =====================================================
// Marital Status Formatters
// =====================================================

// Cached options from backend
let cachedMaritalStatusOptions: Array<{ value: string; label: string }> = []

/**
 * Set marital status options from tenant config.
 * @param options - Array of { value, label } from backend MaritalStatus enum
 */
export function setMaritalStatusOptions(options: Array<{ value: string; label: string }>): void {
  cachedMaritalStatusOptions = options
}

/**
 * Format marital status code to Spanish display text.
 * @param status - Marital status code
 * @returns Spanish marital status string
 */
export function formatMaritalStatus(status: string | null | undefined): string {
  if (!status) return '-'
  const found = cachedMaritalStatusOptions.find(opt => opt.value === status)
  return found?.label || status
}

// =====================================================
// Bank Account Type Formatters
// =====================================================

// Cache for options loaded from backend
let cachedAccountTypeOptions: Array<{ value: string; label: string }> = []

/**
 * Set account type options from tenant config.
 * Call this after loading tenant config to enable proper formatting.
 * @param options - Array of { value, label } from backend BankAccountType enum
 */
export function setAccountTypeOptions(options: Array<{ value: string; label: string }>): void {
  cachedAccountTypeOptions = options
}

/**
 * Format bank account type code to Spanish display text.
 * Uses options from backend BankAccountType enum when available.
 * @param type - Account type code
 * @param options - Optional override for account type options
 * @returns Spanish account type string
 */
export function formatAccountType(
  type: string | null | undefined,
  options?: Array<{ value: string; label: string }>
): string {
  if (!type) return '-'

  // Use provided options, cached options, or fall back to raw value
  const optionsToUse = options || cachedAccountTypeOptions
  const found = optionsToUse.find(opt => opt.value === type)
  return found?.label || type
}

// =====================================================
// Formatter Options Initializer
// =====================================================

interface FormatterOptions {
  gender?: Array<{ value: string; label: string }>
  employmentType?: Array<{ value: string; label: string }>
  housingType?: Array<{ value: string; label: string }>
  maritalStatus?: Array<{ value: string; label: string }>
  paymentFrequency?: Array<{ value: string; label: string }>
  bankAccountType?: Array<{ value: string; label: string }>
}

/**
 * Initialize all formatter caches with options from tenant config.
 * Call this once after loading tenant config to enable proper enum formatting.
 *
 * @example
 * ```typescript
 * // In tenant store after loading config:
 * initializeFormatters(response.options)
 * ```
 *
 * @param options - Options object from tenant config (camelCase keys)
 */
export function initializeFormatters(options: FormatterOptions): void {
  if (options.gender) setGenderOptions(options.gender)
  if (options.employmentType) setEmploymentTypeOptions(options.employmentType)
  if (options.housingType) setHousingTypeOptions(options.housingType)
  if (options.maritalStatus) setMaritalStatusOptions(options.maritalStatus)
  if (options.paymentFrequency) setFrequencyOptions(options.paymentFrequency)
  if (options.bankAccountType) setAccountTypeOptions(options.bankAccountType)
}
