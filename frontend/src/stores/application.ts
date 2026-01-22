import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { v2 } from '@/services/v2'
import type {
  Application,
  SimulationResult,
  SimulationParams,
  CreateApplicationParams,
  Product,
  PaymentFrequency
} from '@/types'
import type { V2PaymentFrequency } from '@/types/v2'
import { logger } from '@/utils/logger'
import { storage, STORAGE_KEYS } from '@/utils/storage'
import { useAsyncAction } from '@/composables'

const log = logger.child('ApplicationStore')

// Simulator only supports these frequencies (not 'OTHER')
type SimulatorPaymentFrequency = 'WEEKLY' | 'BIWEEKLY' | 'MONTHLY'

// Convert V1 payment frequency format (Spanish) to V2 format (English)
const toV2PaymentFrequency = (freq: PaymentFrequency): V2PaymentFrequency => {
  const map: Record<PaymentFrequency, V2PaymentFrequency> = {
    'SEMANAL': 'WEEKLY',
    'WEEKLY': 'WEEKLY',
    'BIWEEKLY': 'BIWEEKLY',
    'QUINCENAL': 'BIWEEKLY',
    'MONTHLY': 'MONTHLY',
    'MENSUAL': 'MONTHLY'
  }
  return map[freq] || 'MONTHLY'
}

// Convert to simulator-specific frequency (excludes 'OTHER')
const toSimulatorFrequency = (freq: PaymentFrequency): SimulatorPaymentFrequency => {
  const result = toV2PaymentFrequency(freq)
  // Simulator doesn't support 'OTHER', default to MONTHLY
  if (result === 'OTHER') return 'MONTHLY'
  return result
}

// Type-safe Axios error interface
interface ApiError extends Error {
  response?: {
    status?: number
    data?: { message?: string }
  }
}

export const useApplicationStore = defineStore('application', () => {
  // State
  const currentApplication = ref<Application | null>(null)
  const simulation = ref<SimulationResult | null>(null)
  const selectedProduct = ref<Product | null>(null)
  const currentStep = ref(1)
  const totalSteps = ref(10) // Simulator + KYC + 8 data steps

  // Helper to convert unknown values to numbers (JSON may return strings)
  const toNum = (val: unknown): number => {
    if (typeof val === 'number') return val
    if (typeof val === 'string') return parseFloat(val) || 0
    return 0
  }

  // Helper to restore simulation from application data
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const restoreSimulationFromApp = (app: any) => {
    if (app?.requested_amount) {
      const termMonths = toNum(app.requested_term_months) || toNum(app.term_months) || 12
      const paymentFreq = app.payment_frequency || 'MONTHLY'
      const interestRate = toNum(app.interest_rate)
      simulation.value = {
        requested_amount: toNum(app.requested_amount),
        term_months: termMonths,
        payment_frequency: paymentFreq as 'WEEKLY' | 'BIWEEKLY' | 'MONTHLY',
        total_periods: termMonths,
        annual_rate: interestRate,
        periodic_rate: interestRate / 12,
        opening_commission: toNum(app.opening_commission),
        periodic_payment: toNum(app.monthly_payment),
        total_interest: toNum(app.total_interest),
        total_amount: toNum(app.total_amount) || toNum(app.total_to_pay),
        cat: toNum(app.cat),
        amortization_table: []
      }
      log.debug('Simulation restored from application data', { simulation: simulation.value })
    }
  }

  // Async actions with shared loading states
  const { execute: executeSimulation, isLoading: isSimulating } = useAsyncAction(
    async (params: SimulationParams) => {
      const response = await v2.simulator.calculate({
        product_id: params.product_id,
        amount: params.amount,
        term_months: params.term_months,
        payment_frequency: toSimulatorFrequency(params.payment_frequency)
      })

      if (!response.success || !response.data) {
        throw new Error(response.message || 'Error en simulación')
      }

      const data = response.data.simulation

      simulation.value = {
        requested_amount: data.requested_amount,
        term_months: data.term_months,
        payment_frequency: data.payment_frequency as 'WEEKLY' | 'BIWEEKLY' | 'MONTHLY',
        total_periods: data.total_periods,
        annual_rate: data.annual_rate,
        periodic_rate: data.periodic_rate,
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
      const response = await v2.applicant.application.list()
      if (response.success && response.data) {
        return (response.data.applications || []) as unknown as Application[]
      }
      return []
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

      const response = await v2.applicant.application.get(id)
      let app: Application | null = null

      if (response.success && response.data) {
        app = response.data as unknown as Application
      }

      if (!app?.id || app.id === 'null') {
        log.error('loadApplication: API returned invalid application ID')
        return null
      }

      currentApplication.value = app
      restoreSimulationFromApp(app)

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

      const response = await v2.applicant.application.create({
        product_id: params.product_id,
        requested_amount: params.requested_amount,
        term_months: params.term_months,
        payment_frequency: toV2PaymentFrequency(params.payment_frequency),
        simulation_data: simulation.value || undefined
      })

      let app: Application | null = null
      if (response.success && response.data) {
        app = response.data as unknown as Application
      }
      log.debug('V2 Response received', { appId: app?.id })

      currentApplication.value = app
      restoreSimulationFromApp(app)

      if (currentApplication.value?.id) {
        storage.set(STORAGE_KEYS.CURRENT_APPLICATION_ID, currentApplication.value.id)
        log.debug('Saved application ID to storage', { appId: currentApplication.value.id })
      }

      currentStep.value = 1
      return currentApplication.value
    },
    { rethrow: true }
  )

  const { execute: executeUpdateApplication } = useAsyncAction(
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

      log.debug('PATCH /applications/' + currentApplication.value!.id)

      // Convert payment_frequency to V2 format if present
      const v2Data = { ...data } as Record<string, unknown>
      if (data.payment_frequency) {
        v2Data.payment_frequency = toV2PaymentFrequency(data.payment_frequency as PaymentFrequency)
      }

      const response = await v2.applicant.application.update(currentApplication.value!.id, v2Data)
      let app: Application | null = null

      if (response.success && response.data) {
        app = response.data as unknown as Application
      }

      currentApplication.value = app
      restoreSimulationFromApp(app)

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
      if (!currentApplication.value) {
        throw new Error('Cannot submit application')
      }

      const response = await v2.applicant.application.submit(currentApplication.value.id)
      let app: Application | null = null

      if (response.success && response.data) {
        app = response.data as unknown as Application
      }

      currentApplication.value = app
      return currentApplication.value
    },
    { rethrow: true }
  )

  // Combined loading state
  const isLoading = computed(() =>
    isSimulating.value ||
    isLoadingList.value ||
    isLoadingOne.value ||
    isCreating.value ||
    isSubmitting.value
  )

  // Actions
  const setSelectedProduct = (product: Product | null) => {
    console.log('💾 [ApplicationStore] setSelectedProduct called', { product: product ? { id: product.id, name: product.name } : null })
    selectedProduct.value = product
    // Persist to storage so it survives page reloads (e.g., during auth)
    if (product) {
      storage.set(STORAGE_KEYS.SELECTED_PRODUCT, product)
      console.log('✅ [ApplicationStore] Product saved to storage:', product.name)
      // Verify it was saved
      const verify = storage.get<Product>(STORAGE_KEYS.SELECTED_PRODUCT)
      console.log('🔍 [ApplicationStore] Verification - product in storage:', verify ? verify.name : 'NOT FOUND')
    } else {
      storage.remove(STORAGE_KEYS.SELECTED_PRODUCT)
      console.log('🗑️ [ApplicationStore] Product removed from storage')
    }
  }

  const runSimulation = async (params: SimulationParams): Promise<SimulationResult> => {
    console.log('🎲 [ApplicationStore] runSimulation called', { params })
    const result = await executeSimulation(params)
    if (!result) throw new Error('Simulation failed')
    // Persist simulation to storage
    storage.set(STORAGE_KEYS.SIMULATION, result)
    console.log('✅ [ApplicationStore] Simulation saved to storage:', { amount: result.requested_amount })
    // Verify it was saved
    const verify = storage.get<SimulationResult>(STORAGE_KEYS.SIMULATION)
    console.log('🔍 [ApplicationStore] Verification - simulation in storage:', verify ? `${verify.requested_amount}` : 'NOT FOUND')
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

  const submitApplication = async (): Promise<Application | null> => {
    return executeSubmitApplication()
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
    selectedProduct.value = null
    currentStep.value = 1
    // Clear persisted data
    storage.remove(STORAGE_KEYS.SELECTED_PRODUCT)
    storage.remove(STORAGE_KEYS.SIMULATION)
  }

  // Initialize: restore state from storage on mount
  const init = () => {
    console.log('🔧 [ApplicationStore] init() called - restoring from storage...')
    const savedProduct = storage.get<Product>(STORAGE_KEYS.SELECTED_PRODUCT)
    const savedSimulation = storage.get<SimulationResult>(STORAGE_KEYS.SIMULATION)

    console.log('📦 [ApplicationStore] Retrieved from storage:', {
      savedProduct: savedProduct ? { id: savedProduct.id, name: savedProduct.name } : null,
      savedSimulation: savedSimulation ? { amount: savedSimulation.requested_amount } : null
    })

    if (savedProduct) {
      selectedProduct.value = savedProduct
      log.info('✅ Restored selected product from storage', { productId: savedProduct.id, name: savedProduct.name })
    } else {
      log.warn('⚠️ No saved product found in storage')
    }

    if (savedSimulation) {
      simulation.value = savedSimulation
      log.info('✅ Restored simulation from storage', { amount: savedSimulation.requested_amount })
    } else {
      log.warn('⚠️ No saved simulation found in storage')
    }
  }

  // Auto-init on store creation
  init()

  return {
    // State
    currentApplication,
    simulation,
    selectedProduct,
    currentStep,
    totalSteps,
    isLoading,
    // Actions
    setSelectedProduct,
    runSimulation,
    loadApplications,
    loadApplication,
    createApplication,
    updateApplication,
    submitApplication,
    restoreProgress,
    reset,
    init
  }
})
