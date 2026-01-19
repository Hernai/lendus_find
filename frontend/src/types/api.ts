// API Types - Common API Response Types

export interface ApiResponse<T = unknown> {
  success: boolean
  data?: T
  error?: string
  message?: string
}

// Axios error response type for catch blocks
export interface AxiosErrorResponse {
  response?: {
    data?: {
      message?: string
      error?: string
      errors?: Record<string, string[]>
    }
    status?: number
    statusText?: string
  }
  message?: string
  code?: string
  isAxiosError?: boolean
}

/**
 * Type guard to check if error is an Axios error.
 */
export function isAxiosError(error: unknown): error is AxiosErrorResponse {
  return (
    typeof error === 'object' &&
    error !== null &&
    ('response' in error || 'isAxiosError' in error)
  )
}

/**
 * Extract error message from unknown error.
 * Handles Axios errors, standard Error objects, and unknown errors.
 */
export function getErrorMessage(error: unknown, defaultMessage = 'Error desconocido'): string {
  if (isAxiosError(error)) {
    return error.response?.data?.message ||
           error.response?.data?.error ||
           error.message ||
           defaultMessage
  }

  if (error instanceof Error) {
    return error.message || defaultMessage
  }

  if (typeof error === 'string') {
    return error
  }

  return defaultMessage
}

/**
 * Extract validation errors from Axios error response.
 * Returns a flattened object with field names as keys and error messages as values.
 */
export function getValidationErrors(error: unknown): Record<string, string> {
  if (!isAxiosError(error)) return {}

  const errors = error.response?.data?.errors
  if (!errors) return {}

  const flattened: Record<string, string> = {}
  for (const [field, messages] of Object.entries(errors)) {
    flattened[field] = messages[0] ?? ''
  }
  return flattened
}

/**
 * Check if error is a validation error (HTTP 422).
 */
export function isValidationError(error: unknown): boolean {
  return isAxiosError(error) && error.response?.status === 422
}

/**
 * Check if error is an authentication error (HTTP 401).
 */
export function isAuthError(error: unknown): boolean {
  return isAxiosError(error) && error.response?.status === 401
}

/**
 * Check if error is a forbidden error (HTTP 403).
 */
export function isForbiddenError(error: unknown): boolean {
  return isAxiosError(error) && error.response?.status === 403
}

/**
 * Check if error is a not found error (HTTP 404).
 */
export function isNotFoundError(error: unknown): boolean {
  return isAxiosError(error) && error.response?.status === 404
}

export interface PaginatedResponse<T> {
  data: T[]
  meta: {
    current_page: number
    from: number
    last_page: number
    per_page: number
    to: number
    total: number
  }
}

export interface ApiError {
  success: false
  error: string
  message: string
  errors?: Record<string, string[]>
}

export interface PostalCodeResult {
  postal_code: string
  neighborhoods: string[]
  municipality: string
  city: string
  state: string
}
