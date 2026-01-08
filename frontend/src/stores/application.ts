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
  const isLoading = ref(false)
  const isSaving = ref(false)

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
    isLoading.value = true

    try {
      const response = await api.post<SimulatorResponse>('/simulator/calculate', {
        product_id: params.product_id,
        amount: params.amount,
        term_months: params.term_months,
        payment_frequency: params.payment_frequency
      })

      const data = response.data.simulation

      // Map API response to frontend SimulationResult format
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
        amortization_table: [] // Can be loaded separately if needed
      }

      return simulation.value
    } catch (error) {
      console.error('Error running simulation:', error)
      throw error
    } finally {
      isLoading.value = false
    }
  }

  const loadApplications = async (): Promise<Application[]> => {
    isLoading.value = true
    try {
      const response = await api.get<ApplicationListResponse>('/applications')
      return response.data.data
    } catch (error) {
      console.error('Error loading applications:', error)
      return []
    } finally {
      isLoading.value = false
    }
  }

  const loadApplication = async (id: string): Promise<Application | null> => {
    // Validate ID before making API call
    if (!id || id === 'null' || id === 'undefined') {
      console.error('❌ loadApplication: Invalid ID:', id)
      return null
    }

    isLoading.value = true
    try {
      const response = await api.get<ApplicationResponse>(`/applications/${id}`)
      const app = response.data.data

      // Validate the response has a valid ID
      if (!app?.id || app.id === 'null') {
        console.error('❌ loadApplication: API returned invalid application ID')
        return null
      }

      currentApplication.value = app
      return currentApplication.value
    } catch (error) {
      console.error('Error loading application:', error)
      return null
    } finally {
      isLoading.value = false
    }
  }

  const createApplication = async (params: CreateApplicationParams): Promise<Application> => {
    isLoading.value = true

    try {
      console.log('📤 POST /applications with:', params)
      const response = await api.post<ApplicationResponse>('/applications', {
        product_id: params.product_id,
        requested_amount: params.requested_amount,
        term_months: params.term_months,
        payment_frequency: params.payment_frequency,
        simulation_data: simulation.value
      })

      console.log('📥 Response status:', response.status)
      console.log('📥 Response data:', response.data)
      console.log('📥 Response data.data:', response.data.data)
      console.log('📥 Response data.data.id:', response.data.data?.id)

      currentApplication.value = response.data.data

      console.log('✅ currentApplication set to:', currentApplication.value?.id)

      // Save to localStorage for persistence
      if (currentApplication.value?.id) {
        localStorage.setItem('current_application_id', currentApplication.value.id)
        console.log('💾 Saved application ID to localStorage:', currentApplication.value.id)
      }

      // Reset step to 1
      currentStep.value = 1

      return currentApplication.value
    } catch (error) {
      console.error('❌ Error creating application:', error)
      throw error
    } finally {
      isLoading.value = false
    }
  }

  const updateApplication = async (data: Partial<Application>) => {
    console.log('📝 updateApplication called, currentApplication:', currentApplication.value?.id || 'NULL')

    if (!currentApplication.value) {
      console.warn('⚠️ updateApplication: No currentApplication')
      return
    }

    // Validate ID is not null, undefined, or the string "null"
    const appId = currentApplication.value.id
    if (!appId || appId === 'null' || appId === 'undefined') {
      console.error('❌ updateApplication: currentApplication has invalid ID:', appId)
      console.error('❌ currentApplication:', JSON.stringify(currentApplication.value))

      // Try to recover from localStorage
      const savedId = localStorage.getItem('current_application_id')
      if (savedId && savedId !== 'null' && savedId !== 'undefined') {
        console.log('🔄 Attempting to reload application from saved ID:', savedId)
        const loaded = await loadApplication(savedId)
        if (!loaded?.id) {
          localStorage.removeItem('current_application_id')
          throw new Error('Application ID is missing and recovery failed')
        }
        // Now currentApplication should have a valid ID, retry
      } else {
        throw new Error('Application ID is missing')
      }
    }

    isSaving.value = true

    try {
      console.log('📤 PUT /applications/' + currentApplication.value!.id, data)
      const response = await api.put<ApplicationResponse>(
        `/applications/${currentApplication.value!.id}`,
        data
      )

      currentApplication.value = response.data.data
      console.log('✅ Application updated:', currentApplication.value?.id)
    } catch (error) {
      console.error('❌ Error updating application:', error)
      throw error
    } finally {
      isSaving.value = false
    }
  }

  const saveStepData = async (stepData: Record<string, unknown>) => {
    // Create application if it doesn't exist
    if (!currentApplication.value) {
      await createApplication({
        product_id: selectedProduct.value?.id || 'default-product',
        requested_amount: simulation.value?.requested_amount || 10000,
        term_months: simulation.value?.term_months || 12,
        payment_frequency: simulation.value?.payment_frequency || 'MONTHLY'
      })
    }

    await updateApplication({
      dynamic_data: {
        ...currentApplication.value?.dynamic_data,
        ...stepData
      }
    })
  }

  const submitApplication = async (): Promise<Application | null> => {
    if (!currentApplication.value || !canSubmit.value) return null

    isLoading.value = true

    try {
      const response = await api.post<ApplicationResponse>(
        `/applications/${currentApplication.value.id}/submit`
      )

      currentApplication.value = response.data.data

      return currentApplication.value
    } catch (error) {
      console.error('Error submitting application:', error)
      throw error
    } finally {
      isLoading.value = false
    }
  }

  const cancelApplication = async (): Promise<Application | null> => {
    if (!currentApplication.value) return null

    isLoading.value = true

    try {
      const response = await api.post<ApplicationResponse>(
        `/applications/${currentApplication.value.id}/cancel`
      )

      currentApplication.value = response.data.data

      return currentApplication.value
    } catch (error) {
      console.error('Error canceling application:', error)
      throw error
    } finally {
      isLoading.value = false
    }
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
    isLoading.value = false
    isSaving.value = false
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
