import { ref, readonly } from 'vue'

/**
 * Toast notification types.
 */
export type ToastType = 'success' | 'error' | 'warning' | 'info'

/**
 * Toast notification configuration.
 */
export interface Toast {
  id: string
  type: ToastType
  message: string
  duration: number
}

/**
 * Options for showing a toast.
 */
export interface ToastOptions {
  type?: ToastType
  duration?: number
}

// Singleton state for toast notifications
const toasts = ref<Toast[]>([])
let toastCounter = 0

/**
 * Add a toast notification.
 */
const addToast = (message: string, options: ToastOptions = {}): string => {
  const id = `toast-${++toastCounter}`
  const type = options.type || 'info'
  const duration = options.duration ?? (type === 'error' ? 5000 : 3000)

  const toast: Toast = { id, type, message, duration }
  toasts.value.push(toast)

  // Auto-remove after duration
  if (duration > 0) {
    setTimeout(() => {
      removeToast(id)
    }, duration)
  }

  return id
}

/**
 * Remove a toast by ID.
 */
const removeToast = (id: string): void => {
  const index = toasts.value.findIndex(t => t.id === id)
  if (index !== -1) {
    toasts.value.splice(index, 1)
  }
}

/**
 * Clear all toasts.
 */
const clearToasts = (): void => {
  toasts.value = []
}

/**
 * Composable for toast notifications.
 *
 * Replaces native `alert()` calls with a proper notification system.
 *
 * @example
 * ```typescript
 * const { success, error, warning, info } = useToast()
 *
 * // Show success toast
 * success('Guardado correctamente')
 *
 * // Show error toast (longer duration)
 * error('Error al guardar los cambios')
 *
 * // Custom options
 * info('Procesando...', { duration: 0 }) // Won't auto-dismiss
 * ```
 */
export function useToast() {
  const success = (message: string, options: Omit<ToastOptions, 'type'> = {}): string => {
    return addToast(message, { ...options, type: 'success' })
  }

  const error = (message: string, options: Omit<ToastOptions, 'type'> = {}): string => {
    return addToast(message, { ...options, type: 'error' })
  }

  const warning = (message: string, options: Omit<ToastOptions, 'type'> = {}): string => {
    return addToast(message, { ...options, type: 'warning' })
  }

  const info = (message: string, options: Omit<ToastOptions, 'type'> = {}): string => {
    return addToast(message, { ...options, type: 'info' })
  }

  return {
    // State (readonly to prevent external mutation)
    toasts: readonly(toasts),

    // Actions
    success,
    error,
    warning,
    info,
    remove: removeToast,
    clear: clearToasts,
  }
}

/**
 * Get toast state for the ToastContainer component.
 */
export function useToastState() {
  return {
    toasts: readonly(toasts),
    remove: removeToast,
  }
}
