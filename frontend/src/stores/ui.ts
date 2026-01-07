import { defineStore } from 'pinia'
import { ref, computed } from 'vue'

export interface Toast {
  id: string
  type: 'success' | 'error' | 'warning' | 'info'
  title: string
  message?: string
  duration?: number
}

export const useUiStore = defineStore('ui', () => {
  // State
  const isMobile = ref(window.innerWidth < 768)
  const isTablet = ref(window.innerWidth >= 768 && window.innerWidth < 1024)
  const isDesktop = ref(window.innerWidth >= 1024)
  const isSidebarOpen = ref(false)
  const isModalOpen = ref(false)
  const modalComponent = ref<string | null>(null)
  const modalProps = ref<Record<string, any>>({})
  const toasts = ref<Toast[]>([])
  const isPageLoading = ref(false)

  // Actions
  const updateBreakpoints = () => {
    const width = window.innerWidth
    isMobile.value = width < 768
    isTablet.value = width >= 768 && width < 1024
    isDesktop.value = width >= 1024
  }

  const toggleSidebar = () => {
    isSidebarOpen.value = !isSidebarOpen.value
  }

  const openSidebar = () => {
    isSidebarOpen.value = true
  }

  const closeSidebar = () => {
    isSidebarOpen.value = false
  }

  const openModal = (component: string, props: Record<string, any> = {}) => {
    modalComponent.value = component
    modalProps.value = props
    isModalOpen.value = true
  }

  const closeModal = () => {
    isModalOpen.value = false
    modalComponent.value = null
    modalProps.value = {}
  }

  const showToast = (toast: Omit<Toast, 'id'>) => {
    const id = 'toast-' + Date.now()
    const newToast: Toast = { id, ...toast }
    toasts.value.push(newToast)

    // Auto remove after duration
    const duration = toast.duration ?? 5000
    setTimeout(() => {
      removeToast(id)
    }, duration)

    return id
  }

  const removeToast = (id: string) => {
    toasts.value = toasts.value.filter(t => t.id !== id)
  }

  const showSuccess = (title: string, message?: string) => {
    return showToast({ type: 'success', title, message })
  }

  const showError = (title: string, message?: string) => {
    return showToast({ type: 'error', title, message, duration: 8000 })
  }

  const showWarning = (title: string, message?: string) => {
    return showToast({ type: 'warning', title, message })
  }

  const showInfo = (title: string, message?: string) => {
    return showToast({ type: 'info', title, message })
  }

  const setPageLoading = (loading: boolean) => {
    isPageLoading.value = loading
  }

  // Initialize resize listener
  const initResizeListener = () => {
    window.addEventListener('resize', updateBreakpoints)
  }

  const destroyResizeListener = () => {
    window.removeEventListener('resize', updateBreakpoints)
  }

  return {
    // State
    isMobile,
    isTablet,
    isDesktop,
    isSidebarOpen,
    isModalOpen,
    modalComponent,
    modalProps,
    toasts,
    isPageLoading,
    // Actions
    updateBreakpoints,
    toggleSidebar,
    openSidebar,
    closeSidebar,
    openModal,
    closeModal,
    showToast,
    removeToast,
    showSuccess,
    showError,
    showWarning,
    showInfo,
    setPageLoading,
    initResizeListener,
    destroyResizeListener
  }
})
