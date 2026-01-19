import { ref, type Ref } from 'vue'

/**
 * Options for useModal composable.
 */
export interface UseModalOptions<T = unknown> {
  /** Callback when modal opens */
  onOpen?: (data?: T) => void
  /** Callback when modal closes */
  onClose?: () => void
  /** Callback on successful submit */
  onSuccess?: () => void
  /** Callback on error */
  onError?: (error: string) => void
  /** Initial data for editing */
  initialData?: T | null
}

/**
 * Return type for useModal composable.
 */
export interface UseModalReturn<T = unknown> {
  /** Whether modal is visible */
  isOpen: Ref<boolean>
  /** Whether form is submitting */
  isSubmitting: Ref<boolean>
  /** Current error message */
  error: Ref<string>
  /** Data being edited (null for create mode) */
  editingData: Ref<T | null>
  /** Whether in edit mode (has editingData) */
  isEditMode: Ref<boolean>
  /** Open modal for creating new item */
  openCreate: () => void
  /** Open modal for editing existing item */
  openEdit: (data: T) => void
  /** Close modal and reset state */
  close: () => void
  /** Set error message */
  setError: (message: string) => void
  /** Clear error message */
  clearError: () => void
  /** Start submitting */
  startSubmit: () => void
  /** End submitting */
  endSubmit: () => void
  /** Handle successful submission */
  handleSuccess: () => void
  /** Handle error during submission */
  handleError: (error: unknown, defaultMessage?: string) => void
  /** Reset all state */
  reset: () => void
}

/**
 * Composable for managing modal state with create/edit modes.
 *
 * Eliminates the repetitive pattern of:
 * - showModal + isSubmitting + formError
 * - openCreate/openEdit/close functions
 *
 * @example
 * ```typescript
 * const {
 *   isOpen,
 *   isSubmitting,
 *   error,
 *   editingData,
 *   isEditMode,
 *   openCreate,
 *   openEdit,
 *   close,
 *   startSubmit,
 *   handleSuccess,
 *   handleError
 * } = useModal<User>({
 *   onSuccess: () => fetchUsers(),
 *   onError: (err) => toast.error(err)
 * })
 *
 * // In template
 * <button @click="openCreate">Nuevo</button>
 * <button @click="openEdit(user)">Editar</button>
 *
 * // In submit handler
 * const onSubmit = async () => {
 *   startSubmit()
 *   try {
 *     if (isEditMode.value) {
 *       await api.put(`/users/${editingData.value.id}`, form)
 *     } else {
 *       await api.post('/users', form)
 *     }
 *     handleSuccess()
 *   } catch (e) {
 *     handleError(e, 'Error al guardar usuario')
 *   }
 * }
 * ```
 */
export function useModal<T = unknown>(options: UseModalOptions<T> = {}): UseModalReturn<T> {
  const isOpen = ref(false)
  const isSubmitting = ref(false)
  const error = ref('')
  const editingData = ref<T | null>(options.initialData ?? null) as Ref<T | null>

  const isEditMode = ref(false)

  const openCreate = (): void => {
    editingData.value = null
    isEditMode.value = false
    error.value = ''
    isOpen.value = true
    options.onOpen?.()
  }

  const openEdit = (data: T): void => {
    editingData.value = data
    isEditMode.value = true
    error.value = ''
    isOpen.value = true
    options.onOpen?.(data)
  }

  const close = (): void => {
    isOpen.value = false
    isSubmitting.value = false
    error.value = ''
    options.onClose?.()
  }

  const setError = (message: string): void => {
    error.value = message
  }

  const clearError = (): void => {
    error.value = ''
  }

  const startSubmit = (): void => {
    isSubmitting.value = true
    error.value = ''
  }

  const endSubmit = (): void => {
    isSubmitting.value = false
  }

  const handleSuccess = (): void => {
    isSubmitting.value = false
    isOpen.value = false
    editingData.value = null
    error.value = ''
    options.onSuccess?.()
  }

  const handleError = (err: unknown, defaultMessage = 'Error desconocido'): void => {
    isSubmitting.value = false

    // Extract error message
    let message = defaultMessage
    if (err && typeof err === 'object') {
      const axiosError = err as { response?: { data?: { message?: string } }; message?: string }
      message = axiosError.response?.data?.message || axiosError.message || defaultMessage
    } else if (typeof err === 'string') {
      message = err
    }

    error.value = message
    options.onError?.(message)
  }

  const reset = (): void => {
    isOpen.value = false
    isSubmitting.value = false
    error.value = ''
    editingData.value = null
    isEditMode.value = false
  }

  return {
    isOpen,
    isSubmitting,
    error,
    editingData,
    isEditMode,
    openCreate,
    openEdit,
    close,
    setError,
    clearError,
    startSubmit,
    endSubmit,
    handleSuccess,
    handleError,
    reset,
  }
}

/**
 * Simplified modal for confirm dialogs.
 */
export interface UseConfirmModalReturn {
  /** Whether modal is visible */
  isOpen: Ref<boolean>
  /** Whether action is in progress */
  isLoading: Ref<boolean>
  /** Item to confirm action on */
  item: Ref<unknown>
  /** Open confirm modal */
  open: (itemToConfirm: unknown) => void
  /** Close modal */
  close: () => void
  /** Confirm action */
  confirm: () => Promise<void>
}

/**
 * Composable for confirm modals (delete, approve, etc.)
 *
 * @example
 * ```typescript
 * const deleteModal = useConfirmModal({
 *   onConfirm: async (user) => {
 *     await api.delete(`/users/${user.id}`)
 *     toast.success('Usuario eliminado')
 *     fetchUsers()
 *   },
 *   onError: (err) => toast.error(err)
 * })
 *
 * // In template
 * <button @click="deleteModal.open(user)">Eliminar</button>
 *
 * <ConfirmModal
 *   :show="deleteModal.isOpen.value"
 *   :loading="deleteModal.isLoading.value"
 *   @confirm="deleteModal.confirm"
 *   @cancel="deleteModal.close"
 * />
 * ```
 */
export function useConfirmModal(options: {
  onConfirm: (item: unknown) => Promise<void>
  onError?: (error: string) => void
  onClose?: () => void
}): UseConfirmModalReturn {
  const isOpen = ref(false)
  const isLoading = ref(false)
  const item = ref<unknown>(null)

  const open = (itemToConfirm: unknown): void => {
    item.value = itemToConfirm
    isOpen.value = true
  }

  const close = (): void => {
    isOpen.value = false
    isLoading.value = false
    item.value = null
    options.onClose?.()
  }

  const confirm = async (): Promise<void> => {
    if (!item.value) return

    isLoading.value = true
    try {
      await options.onConfirm(item.value)
      close()
    } catch (err) {
      isLoading.value = false
      const message = err instanceof Error ? err.message : 'Error desconocido'
      options.onError?.(message)
    }
  }

  return {
    isOpen,
    isLoading,
    item,
    open,
    close,
    confirm,
  }
}
