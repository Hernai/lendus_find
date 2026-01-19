import { ref, type Ref } from 'vue'
import { logger } from '@/utils/logger'

const log = logger.child('AsyncAction')

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
  /** Whether to rethrow errors after handling */
  rethrow?: boolean
}

/**
 * Return type for useAsyncAction with parameters.
 */
export interface AsyncActionResult<TArgs extends unknown[], TResult> {
  /** Execute the async action with arguments */
  execute: (...args: TArgs) => Promise<TResult | null>
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
 * Supports functions with parameters.
 *
 * @example
 * ```typescript
 * // Without parameters
 * const { execute: loadUsers, isLoading, error } = useAsyncAction(
 *   () => api.get('/users').then(r => r.data)
 * )
 * await loadUsers()
 *
 * // With parameters
 * const { execute: loadUser, isLoading, error } = useAsyncAction(
 *   (id: string) => api.get(`/users/${id}`).then(r => r.data),
 *   { onSuccess: (user) => log.info('Loaded:', user) }
 * )
 * await loadUser('123')
 *
 * // With rethrow for custom error handling
 * const { execute: submitForm, error } = useAsyncAction(
 *   (data: FormData) => api.post('/submit', data),
 *   { rethrow: true }
 * )
 * try {
 *   await submitForm(formData)
 * } catch (e) {
 *   // Handle specific error cases
 * }
 * ```
 */
export function useAsyncAction<TArgs extends unknown[], TResult>(
  action: (...args: TArgs) => Promise<TResult>,
  options?: AsyncActionOptions<TResult>
): AsyncActionResult<TArgs, TResult> {
  const isLoading: Ref<boolean> = ref(options?.initialLoading ?? false)
  const error: Ref<string | null> = ref(null)

  const clearError = (): void => {
    error.value = null
  }

  const reset = (): void => {
    isLoading.value = false
    error.value = null
  }

  const execute = async (...args: TArgs): Promise<TResult | null> => {
    isLoading.value = true
    error.value = null

    try {
      const result = await action(...args)
      options?.onSuccess?.(result)
      return result
    } catch (e) {
      const errorObj = e instanceof Error ? e : new Error(String(e))
      error.value = errorObj.message
      options?.onError?.(errorObj)
      log.error('Action failed', { error: errorObj.message })

      if (options?.rethrow) {
        throw errorObj
      }
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
