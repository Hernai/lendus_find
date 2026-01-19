import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { api } from '@/services/api'
import type {
  Application,
  SimulationResult,
  SimulationParams,
  CreateApplicationParams,
  Product
} from '@/types'
import { logger } from '@/utils/logger'
import { storage, STORAGE_KEYS } from '@/utils/storage'
import { useAsyncAction } from '@/composables'

const log = logger.child('ApplicationStore')

// Type-safe Axios error interface
interface ApiError extends Error {
  response?: {
    status?: number
    data?: { message?: string }
  }
}

interface SimulatorResponse {
  simulation: {
    product_id: string
    product_name: string
    requested_amount: number
    term_months: number
    payment_frequency: string
    annual_rate: number
    opening_commission_rate: number
    payment_amount: number
    total_periods: number
    total_to_pay: number
    total_interest: number
    opening_commission: number
    net_amount: number
    cat: number
  }
}

interface ApplicationResponse {
  data: Application
}

interface ApplicationListResponse {
  data: Application[]
}

export const useApplicationStore = defineStore('application', () => {
  // State
  const currentApplication = ref<Application | null>(null)
  const simulation = ref<SimulationResult | null>(null)
  const selectedProduct = ref<Product | null>(null)
  const currentStep = ref(1)
  const totalSteps = ref(8)

  // Async actions with shared loading states
  const { execute: executeSimulation, isLoading: isSimulating } = useAsyncAction(
    async (params: SimulationParams) => {
      const response = await api.post<SimulatorResponse>('/simulator/calculate', {
        product_id: params.product_id,
        amount: params.amount,
        term_months: params.term_months,
        payment_frequency: params.payment_frequency
      })

      const data = response.data.simulation

      simulation.value = {
        requested_amount: data.requested_amount,
        term_months: data.term_months,
        payment_frequency: data.payment_frequency as 'WEEKLY' | 'BIWEEKLY' | 'MONTHLY',
        total_periods: data.total_periods,
        annual_rate: data.annual_rate,
        periodic_rate: data.annual_rate / (data.payment_frequency === 'WEEKLY' ? 52 : data.payment_frequency === 'BIWEEKLY' ? 26 : 12),
        opening_commission: data.opening_commission,
        periodic_payment: data.payment_amount,
        total_interest: data.total_interest,
        total_amount: data.total_to_pay,
        cat: data.cat,
        amortization_table: []
      }

      return simulation.value
    },
    { rethrow: true }
  )

  const { execute: executeLoadApplications, isLoading: isLoadingList } = useAsyncAction(
    async () => {
      const response = await api.get<ApplicationListResponse>('/applications')
      return response.data.data
    },
    {
      onError: (e) => log.error('Error loading applications', { error: e.message })
    }
  )

  const { execute: executeLoadApplication, isLoading: isLoadingOne } = useAsyncAction(
    async (id: string) => {
      if (!id || id === 'null' || id === 'undefined') {
        log.error('loadApplication: Invalid ID', { id })
        return null
      }

      const response = await api.get<ApplicationResponse>(`/applications/${id}`)
      const app = response.data.data

      if (!app?.id || app.id === 'null') {
        log.error('loadApplication: API returned invalid application ID')
        return null
      }

      currentApplication.value = app
      return app
    },
    {
      onError: (e) => {
        log.error('Error loading application', { error: e.message })
        const apiError = e as ApiError
        if (apiError.response?.status === 404) {
          log.warn('Application not found (404). Clearing localStorage reference.')
          localStorage.removeItem('current_application_id')
        }
      }
    }
  )

  const { execute: executeCreateApplication, isLoading: isCreating } = useAsyncAction(
    async (params: CreateApplicationParams) => {
      log.debug('POST /applications', { productId: params.product_id })
      const response = await api.post<ApplicationResponse>('/applications', {
        product_id: params.product_id,
        requested_amount: params.requested_amount,
        term_months: params.term_months,
        payment_frequency: params.payment_frequency,
        simulation_data: simulation.value
      })

      log.debug('Response received', { status: response.status, appId: response.data.data?.id })

      currentApplication.value = response.data.data

      if (currentApplication.value?.id) {
        storage.set(STORAGE_KEYS.CURRENT_APPLICATION_ID, currentApplication.value.id)
        log.debug('Saved application ID to storage', { appId: currentApplication.value.id })
      }

      currentStep.value = 1
      return currentApplication.value
    },
    { rethrow: true }
  )

  const { execute: executeUpdateApplication, isLoading: isSaving } = useAsyncAction(
    async (data: Partial<Application>) => {
      log.debug('updateApplication called', { appId: currentApplication.value?.id || 'NULL' })

      if (!currentApplication.value) {
        log.warn('updateApplication: No currentApplication')
        throw new Error('No current application')
      }

      const appId = currentApplication.value.id
      if (!appId || appId === 'null' || appId === 'undefined') {
        log.error('updateApplication: currentApplication has invalid ID', { appId })

        const savedId = storage.get<string>(STORAGE_KEYS.CURRENT_APPLICATION_ID) || localStorage.getItem('current_application_id')
        if (savedId && savedId !== 'null' && savedId !== 'undefined') {
          log.debug('Attempting to reload application from saved ID', { savedId })
          const loaded = await executeLoadApplication(savedId)
          if (!loaded?.id) {
            storage.remove(STORAGE_KEYS.CURRENT_APPLICATION_ID)
            localStorage.removeItem('current_application_id')
            throw new Error('Application ID is missing and recovery failed')
          }
        } else {
          throw new Error('Application ID is missing')
        }
      }

      log.debug('PUT /applications/' + currentApplication.value!.id)
      const response = await api.put<ApplicationResponse>(
        `/applications/${currentApplication.value!.id}`,
        data
      )

      currentApplication.value = response.data.data
      log.debug('Application updated', { appId: currentApplication.value?.id })
      return currentApplication.value
    },
    {
      onError: (e) => {
        log.error('Error updating application', { error: e.message })
        const apiError = e as ApiError

        if (apiError.response?.status === 404) {
          log.warn('Application not found (404). Clearing stale state...')
          currentApplication.value = null
          storage.remove(STORAGE_KEYS.CURRENT_APPLICATION_ID)
          localStorage.removeItem('current_application_id')
        }

        if (apiError.response?.status === 400) {
          const message = apiError.response?.data?.message || ''
          if (message.includes('cannot be modified') || message.includes('current status')) {
            log.warn('Application cannot be modified (400). Clearing stale state...')
            currentApplication.value = null
            storage.remove(STORAGE_KEYS.CURRENT_APPLICATION_ID)
            localStorage.removeItem('current_application_id')
          }
        }
      },
      rethrow: true
    }
  )

  const { execute: executeSubmitApplication, isLoading: isSubmitting } = useAsyncAction(
    async () => {
      if (!currentApplication.value || !canSubmit.value) {
        throw new Error('Cannot submit application')
      }

      const response = await api.post<ApplicationResponse>(
        `/applications/${currentApplication.value.id}/submit`
      )

      currentApplication.value = response.data.data
      return currentApplication.value
    },
    { rethrow: true }
  )

  const { execute: executeCancelApplication, isLoading: isCanceling } = useAsyncAction(
    async () => {
      if (!currentApplication.value) {
        throw new Error('No application to cancel')
      }

      const response = await api.post<ApplicationResponse>(
        `/applications/${currentApplication.value.id}/cancel`
      )

      currentApplication.value = response.data.data
      return currentApplication.value
    },
    { rethrow: true }
  )

  // Combined loading state for backwards compatibility
  const isLoading = computed(() =>
    isSimulating.value ||
    isLoadingList.value ||
    isLoadingOne.value ||
    isCreating.value ||
    isSubmitting.value ||
    isCanceling.value
  )

  // Getters
  const progress = computed(() => Math.round((currentStep.value / totalSteps.value) * 100))

  const canSubmit = computed(() => {
    if (!currentApplication.value) return false
    return currentApplication.value.status === 'DRAFT'
  })

  const statusLabel = computed(() => {
    const labels: Record<string, string> = {
      DRAFT: 'Borrador',
      SUBMITTED: 'Enviada',
      IN_REVIEW: 'En revisión',
      DOCS_PENDING: 'Documentos pendientes',
      CORRECTIONS_PENDING: 'Correcciones pendientes',
      APPROVED: 'Aprobada',
      REJECTED: 'Rechazada',
      SYNCED: 'Sincronizada'
    }
    return labels[currentApplication.value?.status ?? ''] ?? ''
  })

  // Actions
  const setSelectedProduct = (product: Product | null) => {
    selectedProduct.value = product
  }

  const runSimulation = async (params: SimulationParams): Promise<SimulationResult> => {
    const result = await executeSimulation(params)
    if (!result) throw new Error('Simulation failed')
    return result
  }

  const loadApplications = async (): Promise<Application[]> => {
    const result = await executeLoadApplications()
    return result ?? []
  }

  const loadApplication = async (id: string): Promise<Application | null> => {
    return executeLoadApplication(id)
  }

  const createApplication = async (params: CreateApplicationParams): Promise<Application> => {
    const result = await executeCreateApplication(params)
    if (!result) throw new Error('Failed to create application')
    return result
  }

  const updateApplication = async (data: Partial<Application>) => {
    await executeUpdateApplication(data)
  }

  const saveStepData = async (stepData: Record<string, unknown>) => {
    if (!currentApplication.value) {
      await createApplication({
        product_id: selectedProduct.value?.id || 'default-product',
        requested_amount: simulation.value?.requested_amount || 10000,
        term_months: simulation.value?.term_months || 12,
        payment_frequency: simulation.value?.payment_frequency || 'MONTHLY'
      })
    }

    try {
      await updateApplication({
        dynamic_data: {
          ...currentApplication.value?.dynamic_data,
          ...stepData
        }
      })
    } catch (error: unknown) {
      const err = error as { message?: string }
      if (err.message?.includes('ya fue enviada') || err.message?.includes('no fue encontrada')) {
        log.info('Creating new application after previous one was closed...')
        await createApplication({
          product_id: selectedProduct.value?.id || 'default-product',
          requested_amount: simulation.value?.requested_amount || 10000,
          term_months: simulation.value?.term_months || 12,
          payment_frequency: simulation.value?.payment_frequency || 'MONTHLY'
        })
        await updateApplication({
          dynamic_data: {
            ...currentApplication.value?.dynamic_data,
            ...stepData
          }
        })
      } else {
        throw error
      }
    }
  }

  const submitApplication = async (): Promise<Application | null> => {
    return executeSubmitApplication()
  }

  const cancelApplication = async (): Promise<Application | null> => {
    return executeCancelApplication()
  }

  const nextStep = () => {
    if (currentStep.value < totalSteps.value) {
      currentStep.value++
      saveProgress()
    }
  }

  const prevStep = () => {
    if (currentStep.value > 1) {
      currentStep.value--
    }
  }

  const goToStep = (step: number) => {
    if (step >= 1 && step <= totalSteps.value) {
      currentStep.value = step
    }
  }

  const saveProgress = () => {
    if (currentApplication.value) {
      localStorage.setItem(`app_progress_${currentApplication.value.id}`, JSON.stringify({
        step: currentStep.value,
        timestamp: Date.now()
      }))
    }
  }

  const restoreProgress = () => {
    if (currentApplication.value) {
      const saved = localStorage.getItem(`app_progress_${currentApplication.value.id}`)
      if (saved) {
        const { step } = JSON.parse(saved)
        currentStep.value = step
      }
    }
  }

  const reset = () => {
    currentApplication.value = null
    simulation.value = null
    currentStep.value = 1
  }

  return {
    // State
    currentApplication,
    simulation,
    selectedProduct,
    currentStep,
    totalSteps,
    isLoading,
    isSaving,
    // Getters
    progress,
    canSubmit,
    statusLabel,
    // Actions
    setSelectedProduct,
    runSimulation,
    loadApplications,
    loadApplication,
    createApplication,
    updateApplication,
    saveStepData,
    submitApplication,
    cancelApplication,
    nextStep,
    prevStep,
    goToStep,
    restoreProgress,
    reset
  }
})
