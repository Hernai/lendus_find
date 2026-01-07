import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import type {
  Application,
  SimulationResult,
  SimulationParams,
  CreateApplicationParams,
  PaymentFrequency,
  Product
} from '@/types'

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
      // TODO: Replace with actual API call
      // const response = await api.post<SimulationResult>('/api/simulator', params)
      // simulation.value = response.data

      // Mock calculation (French amortization)
      await new Promise(resolve => setTimeout(resolve, 500))

      const { amount, term_months, payment_frequency } = params
      // Use product rates if available, otherwise defaults
      const annualRate = (selectedProduct.value?.rules.annual_rate || 45) / 100
      const openingCommissionRate = (selectedProduct.value?.rules.opening_commission || 2.5) / 100

      const periodsPerYear = payment_frequency === 'WEEKLY' ? 52 : payment_frequency === 'BIWEEKLY' ? 26 : 12
      const totalPeriods = payment_frequency === 'WEEKLY'
        ? Math.round(term_months * 4.33)
        : payment_frequency === 'BIWEEKLY'
          ? term_months * 2
          : term_months

      const periodicRate = annualRate / periodsPerYear
      const openingCommission = amount * openingCommissionRate

      // French amortization formula
      const periodicPayment = amount * (periodicRate * Math.pow(1 + periodicRate, totalPeriods)) /
                             (Math.pow(1 + periodicRate, totalPeriods) - 1)

      // Generate amortization table
      const amortizationTable = []
      let balance = amount
      const startDate = new Date()
      startDate.setMonth(startDate.getMonth() + 1)

      for (let i = 1; i <= totalPeriods; i++) {
        const interest = balance * periodicRate
        const principal = periodicPayment - interest
        const iva = interest * 0.16
        const newBalance = Math.max(0, balance - principal)

        const paymentDate = new Date(startDate)
        if (payment_frequency === 'WEEKLY') {
          paymentDate.setDate(paymentDate.getDate() + (i - 1) * 7)
        } else if (payment_frequency === 'BIWEEKLY') {
          paymentDate.setDate(paymentDate.getDate() + (i - 1) * 14)
        } else {
          paymentDate.setMonth(paymentDate.getMonth() + (i - 1))
        }

        const dateStr = paymentDate.toISOString().split('T')[0] ?? ''

        amortizationTable.push({
          number: i,
          date: dateStr,
          opening_balance: Math.round(balance * 100) / 100,
          principal: Math.round(principal * 100) / 100,
          interest: Math.round(interest * 100) / 100,
          iva: Math.round(iva * 100) / 100,
          payment: Math.round((periodicPayment + iva) * 100) / 100,
          closing_balance: Math.round(newBalance * 100) / 100
        })

        balance = newBalance
      }

      const totalInterest = amortizationTable.reduce((sum, row) => sum + row.interest, 0)
      const totalAmount = amount + totalInterest + openingCommission

      // CAT calculation (simplified)
      const cat = (Math.pow(totalAmount / amount, 1 / (term_months / 12)) - 1) * 100

      simulation.value = {
        requested_amount: amount,
        term_months,
        payment_frequency,
        total_periods: totalPeriods,
        annual_rate: annualRate * 100,
        periodic_rate: Math.round(periodicRate * 10000) / 100,
        opening_commission: Math.round(openingCommission * 100) / 100,
        periodic_payment: Math.round(periodicPayment * 100) / 100,
        total_interest: Math.round(totalInterest * 100) / 100,
        total_amount: Math.round(totalAmount * 100) / 100,
        cat: Math.round(cat * 10) / 10,
        amortization_table: amortizationTable
      }

      return simulation.value
    } finally {
      isLoading.value = false
    }
  }

  const createApplication = async (params: CreateApplicationParams): Promise<Application> => {
    isLoading.value = true

    try {
      // TODO: Replace with actual API call
      // const response = await api.post<Application>('/api/applications', params)
      // currentApplication.value = response.data

      // Mock application
      await new Promise(resolve => setTimeout(resolve, 500))

      const now = new Date().toISOString()
      const folio = `LEN-${new Date().getFullYear()}-${String(Math.floor(Math.random() * 100000)).padStart(5, '0')}`

      currentApplication.value = {
        id: 'app-' + Date.now(),
        tenant_id: 'tenant-001',
        applicant_id: '',
        product_id: params.product_id,
        folio,
        status: 'DRAFT',
        requested_amount: params.requested_amount,
        approved_amount: null,
        term_months: params.term_months,
        payment_frequency: params.payment_frequency,
        dynamic_data: {},
        simulation_data: simulation.value,
        submitted_at: null,
        approved_at: null,
        webhook_sent_at: null,
        created_at: now,
        updated_at: now
      }

      // Reset step to 1
      currentStep.value = 1

      return currentApplication.value
    } finally {
      isLoading.value = false
    }
  }

  const updateApplication = async (data: Partial<Application>) => {
    if (!currentApplication.value) return

    isSaving.value = true

    try {
      // TODO: Replace with actual API call
      // await api.patch(`/api/applications/${currentApplication.value.id}`, data)

      await new Promise(resolve => setTimeout(resolve, 300))

      currentApplication.value = {
        ...currentApplication.value,
        ...data,
        updated_at: new Date().toISOString()
      }
    } finally {
      isSaving.value = false
    }
  }

  const saveStepData = async (stepData: Record<string, any>) => {
    // Create application if it doesn't exist
    if (!currentApplication.value) {
      await createApplication({
        product_id: 'default-product',
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
      // TODO: Replace with actual API call
      // const response = await api.post(`/api/applications/${currentApplication.value.id}/submit`)
      // currentApplication.value = response.data

      await new Promise(resolve => setTimeout(resolve, 1000))

      currentApplication.value = {
        ...currentApplication.value,
        status: 'SUBMITTED',
        submitted_at: new Date().toISOString(),
        updated_at: new Date().toISOString()
      }

      return currentApplication.value
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
    createApplication,
    updateApplication,
    saveStepData,
    submitApplication,
    nextStep,
    prevStep,
    goToStep,
    restoreProgress,
    reset
  }
})
