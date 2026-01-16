import { ref, type Ref } from 'vue'

/**
 * Options for useAsyncAction.
 */
export interface AsyncActionOptions<T> {
  /** Callback on successful execution */
  onSuccess?: (data: T) => void
  /** Callback on error */
  onError?: (error: Error) => void
  /** Initial loading state */
  initialLoading?: boolean
}

/**
 * Return type for useAsyncAction.
 */
export interface AsyncActionResult<T> {
  /** Execute the async action */
  execute: () => Promise<T | null>
  /** Current loading state */
  isLoading: Ref<boolean>
  /** Current error message */
  error: Ref<string | null>
  /** Clear current error */
  clearError: () => void
  /** Reset state to initial */
  reset: () => void
}

/**
 * Composable for wrapping async actions with standardized error handling.
 *
 * Eliminates the repetitive try-catch-finally pattern found throughout stores.
 *
 * @example
 * ```typescript
 * const { execute: loadUser, isLoading, error } = useAsyncAction(
 *   () => api.get('/user').then(r => r.data),
 *   {
 *     onSuccess: (user) => console.log('Loaded:', user),
 *     onError: (e) => console.error('Failed:', e)
 *   }
 * )
 *
 * // In component
 * await loadUser()
 * ```
 */
export function useAsyncAction<T>(
  action: () => Promise<T>,
  options?: AsyncActionOptions<T>
): AsyncActionResult<T> {
  const isLoading: Ref<boolean> = ref(options?.initialLoading ?? false)
  const error: Ref<string | null> = ref(null)

  /**
   * Clear the current error.
   */
  const clearError = (): void => {
    error.value = null
  }

  /**
   * Reset to initial state.
   */
  const reset = (): void => {
    isLoading.value = false
    error.value = null
  }

  /**
   * Execute the async action with error handling.
   */
  const execute = async (): Promise<T | null> => {
    isLoading.value = true
    error.value = null

    try {
      const result = await action()
      options?.onSuccess?.(result)
      return result
    } catch (e) {
      const errorObj = e instanceof Error ? e : new Error(String(e))
      error.value = errorObj.message
      options?.onError?.(errorObj)
      console.error('[useAsyncAction] Error:', errorObj.message)
      return null
    } finally {
      isLoading.value = false
    }
  }

  return {
    execute,
    isLoading,
    error,
    clearError,
    reset,
  }
}

/**
 * Composable for async actions that need to track multiple states.
 *
 * Useful for forms that have both loading (fetch) and saving (submit) states.
 *
 * @example
 * ```typescript
 * const { load, save, isLoading, isSaving, error } = useAsyncForm({
 *   loadAction: () => api.get('/data'),
 *   saveAction: (data) => api.post('/data', data),
 * })
 * ```
 */
export function useAsyncForm<TLoad, TSave>(options: {
  loadAction?: () => Promise<TLoad>
  saveAction?: (data: TSave) => Promise<unknown>
  onLoadSuccess?: (data: TLoad) => void
  onSaveSuccess?: () => void
  onError?: (error: Error) => void
}) {
  const isLoading: Ref<boolean> = ref(false)
  const isSaving: Ref<boolean> = ref(false)
  const error: Ref<string | null> = ref(null)

  const clearError = (): void => {
    error.value = null
  }

  const load = async (): Promise<TLoad | null> => {
    if (!options.loadAction) return null

    isLoading.value = true
    error.value = null

    try {
      const result = await options.loadAction()
      options.onLoadSuccess?.(result)
      return result
    } catch (e) {
      const errorObj = e instanceof Error ? e : new Error(String(e))
      error.value = errorObj.message
      options.onError?.(errorObj)
      return null
    } finally {
      isLoading.value = false
    }
  }

  const save = async (data: TSave): Promise<boolean> => {
    if (!options.saveAction) return false

    isSaving.value = true
    error.value = null

    try {
      await options.saveAction(data)
      options.onSaveSuccess?.()
      return true
    } catch (e) {
      const errorObj = e instanceof Error ? e : new Error(String(e))
      error.value = errorObj.message
      options.onError?.(errorObj)
      return false
    } finally {
      isSaving.value = false
    }
  }

  return {
    load,
    save,
    isLoading,
    isSaving,
    error,
    clearError,
  }
}
