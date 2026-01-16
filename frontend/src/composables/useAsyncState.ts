import { ref, type Ref } from 'vue'

/**
 * Composable for managing async operation states.
 *
 * Provides standardized state management for loading, saving, and error states.
 * Reduces code duplication across stores and components.
 *
 * @example
 * ```typescript
 * const { isLoading, isSaving, error, clearError, setError } = useAsyncState()
 *
 * const loadData = async () => {
 *   isLoading.value = true
 *   try {
 *     // ... fetch data
 *   } catch (e) {
 *     setError(e)
 *   } finally {
 *     isLoading.value = false
 *   }
 * }
 * ```
 */
export function useAsyncState() {
  const isLoading: Ref<boolean> = ref(false)
  const isSaving: Ref<boolean> = ref(false)
  const error: Ref<string | null> = ref(null)

  /**
   * Clear the current error state.
   */
  const clearError = (): void => {
    error.value = null
  }

  /**
   * Set an error from various error types.
   */
  const setError = (e: unknown): void => {
    if (e instanceof Error) {
      error.value = e.message
    } else if (typeof e === 'string') {
      error.value = e
    } else {
      error.value = 'Error desconocido'
    }
  }

  /**
   * Start loading state.
   */
  const startLoading = (): void => {
    isLoading.value = true
    clearError()
  }

  /**
   * Stop loading state.
   */
  const stopLoading = (): void => {
    isLoading.value = false
  }

  /**
   * Start saving state.
   */
  const startSaving = (): void => {
    isSaving.value = true
    clearError()
  }

  /**
   * Stop saving state.
   */
  const stopSaving = (): void => {
    isSaving.value = false
  }

  return {
    isLoading,
    isSaving,
    error,
    clearError,
    setError,
    startLoading,
    stopLoading,
    startSaving,
    stopSaving,
  }
}

export type AsyncState = ReturnType<typeof useAsyncState>
