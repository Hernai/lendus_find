import { ref, type Ref } from 'vue'
import { type AxiosErrorResponse, getErrorMessage } from '@/types/api'

/**
 * Validation errors keyed by field name.
 */
export type ValidationErrors = Record<string, string>

/**
 * Parsed error result.
 */
export interface ParsedError {
  /** Main error message */
  message: string
  /** Field-level validation errors */
  fieldErrors: ValidationErrors
  /** HTTP status code if available */
  status?: number
  /** Whether this is a validation error (422) */
  isValidation: boolean
  /** Whether this is an authentication error (401) */
  isAuth: boolean
  /** Whether this is a forbidden error (403) */
  isForbidden: boolean
  /** Whether this is a not found error (404) */
  isNotFound: boolean
  /** Whether this is a server error (5xx) */
  isServerError: boolean
}

/**
 * Common error message translations.
 */
const ERROR_TRANSLATIONS: Record<string, string> = {
  'Validation error': 'Error de validación',
  'User not found': 'Usuario no encontrado',
  'Invalid credentials': 'Credenciales inválidas',
  'Unauthorized': 'No autorizado',
  'Forbidden': 'Acceso denegado',
  'Not Found': 'No encontrado',
  'Internal Server Error': 'Error del servidor',
  'Network Error': 'Error de conexión',
  'The given data was invalid.': 'Los datos proporcionados no son válidos',
  'Unauthenticated.': 'Sesión expirada. Inicia sesión de nuevo.',
  'You cannot change your own admin role': 'No puedes cambiar tu propio rol de administrador',
  'You cannot delete your own account': 'No puedes eliminar tu propia cuenta',
  'Cannot delete user with assigned applications. Reassign or deactivate instead.':
    'No se puede eliminar un usuario con solicitudes asignadas. Reasigna o desactiva en su lugar.',
  'Cannot delete product with existing applications. Deactivate it instead.':
    'No se puede eliminar un producto con solicitudes existentes. Desactívalo en su lugar.',
  'Tenant not found': 'Tenant no encontrado',
  'Tenant inactive': 'Cuenta inactiva',
}

/**
 * Common field error translations.
 */
const FIELD_ERROR_TRANSLATIONS: Record<string, Record<string, string>> = {
  email: {
    'The email has already been taken.': 'Este correo electrónico ya está registrado',
    'The email field is required.': 'El correo electrónico es requerido',
    'The email must be a valid email address.': 'El correo electrónico no es válido',
  },
  phone: {
    'The phone has already been taken.': 'Este número de teléfono ya está registrado',
    'The phone field is required.': 'El teléfono es requerido',
    'The phone must be 10 characters.': 'El teléfono debe tener 10 dígitos',
  },
  password: {
    'The password field is required.': 'La contraseña es requerida',
    'The password must be at least 8 characters.': 'La contraseña debe tener al menos 8 caracteres',
  },
  name: {
    'The name field is required.': 'El nombre es requerido',
  },
  curp: {
    'The curp has already been taken.': 'Este CURP ya está registrado',
    'The curp field is required.': 'El CURP es requerido',
  },
  rfc: {
    'The rfc has already been taken.': 'Este RFC ya está registrado',
  },
}

/**
 * Translate an error message.
 */
export function translateError(message: string): string {
  return ERROR_TRANSLATIONS[message] || message
}

/**
 * Translate a field error message.
 */
export function translateFieldError(field: string, message: string): string {
  const fieldTranslations = FIELD_ERROR_TRANSLATIONS[field]
  if (fieldTranslations && fieldTranslations[message]) {
    return fieldTranslations[message]
  }
  return message
}

/**
 * Parse an error into a structured format.
 */
export function parseError(error: unknown): ParsedError {
  const axiosError = error as AxiosErrorResponse & {
    errors?: Record<string, string[]>
    message?: string
  }

  const status = axiosError.response?.status
  const responseData = axiosError.response?.data

  // For 422 errors, axios interceptor may reject with response.data directly
  const errorData = axiosError.errors ? axiosError : responseData

  // Extract field errors
  const fieldErrors: ValidationErrors = {}
  if (errorData?.errors) {
    Object.entries(errorData.errors).forEach(([field, messages]) => {
      const messageArray = Array.isArray(messages) ? messages : [messages]
      fieldErrors[field] = translateFieldError(field, messageArray[0] || '')
    })
  }

  // Extract main message
  let message = errorData?.message || axiosError.message || 'Error desconocido'
  message = translateError(message)

  return {
    message,
    fieldErrors,
    status,
    isValidation: status === 422 || Object.keys(fieldErrors).length > 0,
    isAuth: status === 401,
    isForbidden: status === 403,
    isNotFound: status === 404,
    isServerError: status !== undefined && status >= 500,
  }
}

/**
 * Options for useErrorHandler composable.
 */
export interface ErrorHandlerOptions {
  /** Default error message */
  defaultMessage?: string
  /** Auto-translate error messages */
  autoTranslate?: boolean
  /** Callback when error occurs */
  onError?: (error: ParsedError) => void
}

/**
 * Composable for standardized error handling.
 *
 * Eliminates repeated error parsing and message extraction across components.
 *
 * @example
 * ```typescript
 * const { error, fieldErrors, handleError, clearError, hasFieldError } = useErrorHandler()
 *
 * try {
 *   await api.post('/users', data)
 * } catch (e) {
 *   handleError(e)
 *   // error.value now has the message
 *   // fieldErrors.value has per-field errors
 * }
 *
 * // In template
 * <p v-if="error">{{ error }}</p>
 * <input :class="{ error: hasFieldError('email') }" />
 * <span v-if="fieldErrors.email">{{ fieldErrors.email }}</span>
 * ```
 */
export function useErrorHandler(options: ErrorHandlerOptions = {}) {
  const { defaultMessage = 'Ha ocurrido un error', autoTranslate = true, onError } = options

  const error: Ref<string> = ref('')
  const fieldErrors: Ref<ValidationErrors> = ref({})
  const lastParsedError: Ref<ParsedError | null> = ref(null)

  /**
   * Handle an error and extract message/field errors.
   */
  const handleError = (e: unknown): ParsedError => {
    const parsed = parseError(e)

    if (autoTranslate) {
      parsed.message = translateError(parsed.message)
    }

    error.value = parsed.message || defaultMessage
    fieldErrors.value = parsed.fieldErrors
    lastParsedError.value = parsed

    onError?.(parsed)

    return parsed
  }

  /**
   * Clear error state.
   */
  const clearError = () => {
    error.value = ''
    fieldErrors.value = {}
    lastParsedError.value = null
  }

  /**
   * Clear a specific field error.
   */
  const clearFieldError = (field: string) => {
    delete fieldErrors.value[field]
  }

  /**
   * Check if a field has an error.
   */
  const hasFieldError = (field: string): boolean => {
    return !!fieldErrors.value[field]
  }

  /**
   * Get error for a specific field.
   */
  const getFieldError = (field: string): string => {
    return fieldErrors.value[field] || ''
  }

  /**
   * Set a field error manually.
   */
  const setFieldError = (field: string, message: string) => {
    fieldErrors.value[field] = message
  }

  /**
   * Set main error message manually.
   */
  const setError = (message: string) => {
    error.value = message
  }

  /**
   * Check if there are any field errors.
   */
  const hasAnyFieldError = (): boolean => {
    return Object.keys(fieldErrors.value).length > 0
  }

  return {
    // State
    error,
    fieldErrors,
    lastParsedError,

    // Actions
    handleError,
    clearError,
    clearFieldError,
    setError,
    setFieldError,

    // Helpers
    hasFieldError,
    getFieldError,
    hasAnyFieldError,
  }
}

/**
 * Re-export getErrorMessage for backwards compatibility.
 */
export { getErrorMessage }
