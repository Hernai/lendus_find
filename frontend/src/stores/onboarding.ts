import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { useProfileStore } from './profile'
import { useApplicationStore } from './application'
import { logger } from '@/utils/logger'
import { isValidRfc } from '@/utils/validators'

const onboardingLogger = logger.child('Onboarding')

// Step data interfaces
interface Step1Data {
  first_name: string
  last_name: string
  second_last_name: string
  birth_date: string
  birth_state: string
  gender: 'M' | 'F' | ''
  nationality: string
  marital_status: string
}

interface Step2Data {
  id_type: 'INE' | 'PASSPORT'
  curp: string
  rfc: string
  clave_elector: string
  numero_ocr: string
  folio_ine: string
  passport_number: string
  passport_issue_date: string
  passport_expiry_date: string
}

interface Step3Data {
  street: string
  ext_number: string
  int_number: string
  neighborhood: string
  postal_code: string
  city: string
  state: string
  municipality: string
  housing_type: string
  years_at_address: number
  months_at_address: number
  is_ine_address?: boolean
}

interface Step4Data {
  employment_type: string
  company_name: string
  job_title: string
  monthly_income: number
  seniority_years: number
  seniority_months: number
  company_phone: string
  company_address: string
}

interface Step5Data {
  purpose: string
  confirmed_simulation: boolean
}

interface Step6Data {
  documents_uploaded: string[]
}

interface Step7Data {
  references: Array<{
    first_name: string
    last_name_1: string
    last_name_2: string
    phone: string
    relationship: string
  }>
}

interface OnboardingData {
  step1: Step1Data
  step2: Step2Data
  step3: Step3Data
  step4: Step4Data
  step5: Step5Data
  step6: Step6Data
  step7: Step7Data
}

const STORAGE_KEY = 'onboarding_draft'

/**
 * Convert date from DD/MM/YYYY format (OCR) to YYYY-MM-DD format (API)
 */
const formatDateForApi = (dateStr: string): string => {
  if (!dateStr) return ''

  // Already in YYYY-MM-DD format
  if (/^\d{4}-\d{2}-\d{2}$/.test(dateStr)) {
    return dateStr
  }

  // DD/MM/YYYY or DD-MM-YYYY format (common from OCR)
  if (/^\d{2}[/-]\d{2}[/-]\d{4}$/.test(dateStr)) {
    const parts = dateStr.split(/[/-]/)
    return `${parts[2]}-${parts[1]}-${parts[0]}`
  }

  return dateStr
}

const getDefaultData = (): OnboardingData => ({
  step1: {
    first_name: '',
    last_name: '',
    second_last_name: '',
    birth_date: '',
    birth_state: '',
    gender: '',
    nationality: 'MX',
    marital_status: ''
  },
  step2: {
    id_type: 'INE',
    curp: '',
    rfc: '',
    clave_elector: '',
    numero_ocr: '',
    folio_ine: '',
    passport_number: '',
    passport_issue_date: '',
    passport_expiry_date: ''
  },
  step3: {
    street: '',
    ext_number: '',
    int_number: '',
    neighborhood: '',
    postal_code: '',
    city: '',
    state: '',
    municipality: '',
    housing_type: '',
    years_at_address: 0,
    months_at_address: 0
  },
  step4: {
    employment_type: '',
    company_name: '',
    job_title: '',
    monthly_income: 0,
    seniority_years: 0,
    seniority_months: 0,
    company_phone: '',
    company_address: ''
  },
  step5: {
    purpose: '',
    confirmed_simulation: false
  },
  step6: {
    documents_uploaded: []
  },
  step7: {
    references: []
  }
})

export const useOnboardingStore = defineStore('onboarding', () => {
  const profileStore = useProfileStore()
  const applicationStore = useApplicationStore()

  // State
  const data = ref<OnboardingData>(getDefaultData())
  const currentStep = ref(1)
  const completedSteps = ref<number[]>([])
  const isSaving = ref(false)
  const isLoading = ref(false)
  const lastSavedAt = ref<Date | null>(null)

  // Auto-save timer
  let autoSaveTimer: ReturnType<typeof setTimeout> | null = null

  // Getters
  const progress = computed(() => Math.round((completedSteps.value.length / 7) * 100))

  const isStepCompleted = (step: number) => completedSteps.value.includes(step)

  const canNavigateToStep = (step: number) => {
    if (step === 1) return true
    return completedSteps.value.includes(step - 1)
  }

  // Load from localStorage on init
  const loadFromStorage = () => {
    try {
      const saved = localStorage.getItem(STORAGE_KEY)
      if (saved) {
        const parsed = JSON.parse(saved)
        data.value = { ...getDefaultData(), ...parsed.data }
        completedSteps.value = parsed.completedSteps || []
        currentStep.value = parsed.currentStep || 1
      }
    } catch (e) {
      onboardingLogger.error('Failed to load onboarding data from storage', e)
    }
  }

  // Save to localStorage
  const saveToStorage = () => {
    try {
      localStorage.setItem(STORAGE_KEY, JSON.stringify({
        data: data.value,
        completedSteps: completedSteps.value,
        currentStep: currentStep.value,
        savedAt: new Date().toISOString()
      }))
    } catch (e) {
      onboardingLogger.error('Failed to save onboarding data to storage', e)
    }
  }

  // Load existing data from backend using V2 API
  const loadFromBackend = async () => {
    isLoading.value = true
    try {
      // Load profile using V2 API
      await profileStore.loadProfile()

      // Try to restore current application
      const currentAppId = applicationStore.currentApplication?.id
      const savedAppId = localStorage.getItem('current_application_id')

      if (!currentAppId || currentAppId === 'null' || currentAppId === 'undefined') {
        if (savedAppId && savedAppId !== 'null' && savedAppId !== 'undefined') {
          onboardingLogger.debug('Restoring application from localStorage', { savedAppId })
          try {
            await applicationStore.loadApplication(savedAppId)
          } catch (e) {
            onboardingLogger.warn('Could not restore application', e)
            localStorage.removeItem('current_application_id')
          }
        }
      }

      // Populate form data from V2 profile
      if (profileStore.profile) {
        const profile = profileStore.profile

        // Populate step 1 from personal data
        if (profile.personal_data.first_name) {
          data.value.step1 = {
            first_name: profile.personal_data.first_name || '',
            last_name: profile.personal_data.last_name_1 || '',
            second_last_name: profile.personal_data.last_name_2 || '',
            birth_date: profile.personal_data.birth_date || '',
            birth_state: profile.personal_data.birth_state || '',
            gender: profile.personal_data.gender || '',
            nationality: profile.personal_data.nationality || 'MX',
            marital_status: profile.personal_data.marital_status || ''
          }
          if (!completedSteps.value.includes(1)) {
            completedSteps.value.push(1)
          }
        }

        // Populate step 2 from identifications
        if (profile.identifications.curp || profile.identifications.rfc) {
          data.value.step2.curp = profile.identifications.curp || ''
          data.value.step2.rfc = profile.identifications.rfc || ''
          if (profile.identifications.ine) {
            data.value.step2.clave_elector = profile.identifications.ine.clave_elector || ''
            data.value.step2.numero_ocr = profile.identifications.ine.ocr || ''
            data.value.step2.folio_ine = profile.identifications.ine.folio || ''
          }
          if (!completedSteps.value.includes(2)) {
            completedSteps.value.push(2)
          }
        }

        // Populate step 3 from address
        if (profile.address) {
          const addr = profile.address
          data.value.step3 = {
            street: addr.street || '',
            ext_number: addr.ext_number || '',
            int_number: addr.int_number || '',
            neighborhood: addr.neighborhood || '',
            postal_code: addr.postal_code || '',
            city: addr.city || '',
            state: addr.state || '',
            municipality: addr.municipality || '',
            housing_type: addr.housing_type || '',
            years_at_address: addr.years_at_address || 0,
            months_at_address: addr.months_at_address || 0
          }
          if (!completedSteps.value.includes(3)) {
            completedSteps.value.push(3)
          }
        }

        // Populate step 4 from employment
        if (profile.employment) {
          const emp = profile.employment
          // Use explicit years/months if available, otherwise calculate from seniority_months
          let seniorityYears = emp.years_employed ?? 0
          let seniorityMonths = emp.months_employed ?? 0
          // Fallback to calculated values if explicit values are 0 but seniority_months is set
          if (seniorityYears === 0 && seniorityMonths === 0 && emp.seniority_months) {
            seniorityYears = Math.floor(emp.seniority_months / 12)
            seniorityMonths = emp.seniority_months % 12
          }
          data.value.step4 = {
            employment_type: emp.employment_type || '',
            company_name: emp.company_name || '',
            job_title: emp.position || '',
            monthly_income: emp.monthly_income || 0,
            seniority_years: seniorityYears,
            seniority_months: seniorityMonths,
            company_phone: emp.work_phone || '',
            company_address: ''
          }
          if (!completedSteps.value.includes(4)) {
            completedSteps.value.push(4)
          }
        }

        // Populate step 7 from references
        if (profile.references && profile.references.length > 0) {
          data.value.step7.references = profile.references.map(ref => ({
            first_name: ref.full_name.split(' ')[0] || '',
            last_name_1: ref.full_name.split(' ')[1] || '',
            last_name_2: ref.full_name.split(' ')[2] || '',
            phone: ref.phone || '',
            relationship: ref.relationship || ''
          }))
        }

        saveToStorage()
      }
    } catch (e) {
      onboardingLogger.error('Failed to load from backend', e)
    } finally {
      isLoading.value = false
    }
  }

  // Save specific step to backend using V2 API
  const saveStepToBackend = async (step: number) => {
    isSaving.value = true
    try {
      switch (step) {
        case 1: {
          const s1 = data.value.step1
          if (!s1.first_name || !s1.last_name || !s1.birth_date || !s1.gender) {
            throw new Error('Missing required fields in personal data')
          }
          await profileStore.updatePersonalData({
            first_name: s1.first_name,
            last_name_1: s1.last_name,
            last_name_2: s1.second_last_name || undefined,
            birth_date: formatDateForApi(s1.birth_date),
            birth_state: s1.birth_state || undefined,
            gender: s1.gender as 'M' | 'F',
            nationality: s1.nationality || 'MX',
            marital_status: s1.marital_status || undefined
          })
          break
        }

        case 2: {
          const s2 = data.value.step2
          const rfc = s2.rfc?.trim().toUpperCase()

          // Validate RFC format before sending (must be valid 12-13 character RFC)
          if (rfc && !isValidRfc(rfc)) {
            throw new Error('El RFC debe tener 12-13 caracteres con formato válido (incluyendo homoclave)')
          }

          const payload: Record<string, string | undefined> = {
            curp: s2.curp?.toUpperCase() || undefined,
            rfc: rfc || undefined
          }

          if (s2.id_type === 'INE') {
            payload.ine_clave = s2.clave_elector || undefined
            payload.ine_ocr = s2.numero_ocr || undefined
            payload.ine_folio = s2.folio_ine || undefined
          } else {
            payload.passport_number = s2.passport_number || undefined
            payload.passport_issue_date = s2.passport_issue_date || undefined
            payload.passport_expiry_date = s2.passport_expiry_date || undefined
          }

          await profileStore.updateIdentifications(payload)
          break
        }

        case 3: {
          const s3 = data.value.step3
          const isIneAddress = s3.is_ine_address === true

          const missingFields: string[] = []
          if (!s3.street) missingFields.push('street')
          if (!s3.neighborhood) missingFields.push('neighborhood')
          if (!s3.postal_code) missingFields.push('postal_code')
          if (!s3.state) missingFields.push('state')
          if (!s3.housing_type) missingFields.push('housing_type')

          if (missingFields.length > 0) {
            throw new Error(`Missing required address fields: ${missingFields.join(', ')}`)
          }
          if (!isIneAddress && !s3.ext_number) {
            throw new Error('Missing exterior number')
          }

          await profileStore.updateAddress({
            street: s3.street,
            ext_number: s3.ext_number || undefined,
            int_number: s3.int_number || undefined,
            neighborhood: s3.neighborhood,
            postal_code: s3.postal_code,
            city: s3.city || s3.municipality,
            state: s3.state,
            municipality: s3.municipality || undefined,
            housing_type: s3.housing_type as 'OWNED' | 'RENTED' | 'FAMILY' | 'MORTGAGED' | 'EMPLOYER',
            years_at_address: Number(s3.years_at_address) || 0,
            months_at_address: Number(s3.months_at_address) || 0
          })
          break
        }

        case 4: {
          const s4 = data.value.step4
          if (!s4.employment_type || s4.monthly_income < 1000) {
            throw new Error('Missing required fields in employment information')
          }

          await profileStore.updateEmployment({
            employment_type: s4.employment_type,
            company_name: s4.company_name || undefined,
            position: s4.job_title || undefined,
            monthly_income: s4.monthly_income,
            seniority_years: Number(s4.seniority_years) || 0,
            seniority_months: Number(s4.seniority_months) || 0,
            work_phone: s4.company_phone || undefined
          })
          break
        }

        case 5: {
          const currentId = applicationStore.currentApplication?.id
          const hasValidId = currentId && currentId !== 'null' && currentId !== 'undefined'

          if (!applicationStore.currentApplication || !hasValidId) {
            const savedAppId = localStorage.getItem('current_application_id')
            if (savedAppId && savedAppId !== 'null' && savedAppId !== 'undefined') {
              try {
                await applicationStore.loadApplication(savedAppId)
              } catch (e) {
                localStorage.removeItem('current_application_id')
              }
            }
          }

          const finalId = applicationStore.currentApplication?.id
          const hasFinalValidId = finalId && finalId !== 'null' && finalId !== 'undefined'

          if (applicationStore.currentApplication && hasFinalValidId) {
            try {
              await applicationStore.updateApplication({
                purpose: data.value.step5.purpose
              })
            } catch (err: unknown) {
              const error = err as { message?: string }
              if (error.message?.includes('already submitted') || error.message?.includes('not found')) {
                throw new Error('Your previous application has already been submitted. To create a new application, go back to the start.')
              }
              throw err
            }
          } else {
            throw new Error('Application not found. Please go back to the start and try again.')
          }
          break
        }

        case 7: {
          // References are saved individually through profileStore
          // This step just marks completion
          break
        }
      }

      lastSavedAt.value = new Date()
      saveToStorage()
    } catch (e) {
      onboardingLogger.error(`Failed to save step ${step} to backend`, e)
      throw e
    } finally {
      isSaving.value = false
    }
  }

  // Mark step as completed and save
  const completeStep = async (step: number) => {
    await saveStepToBackend(step)

    if (!completedSteps.value.includes(step)) {
      completedSteps.value.push(step)
      completedSteps.value.sort((a, b) => a - b)
    }

    saveToStorage()
  }

  // Navigate to step
  const goToStep = (step: number) => {
    if (canNavigateToStep(step)) {
      currentStep.value = step
      saveToStorage()
    }
  }

  // Next step
  const nextStep = async () => {
    await completeStep(currentStep.value)
    if (currentStep.value < 8) {
      currentStep.value++
      saveToStorage()
    }
  }

  // Previous step
  const prevStep = () => {
    if (currentStep.value > 1) {
      currentStep.value--
      saveToStorage()
    }
  }

  // Update step data
  const updateStepData = <K extends keyof OnboardingData>(
    stepKey: K,
    stepData: Partial<OnboardingData[K]>
  ) => {
    data.value[stepKey] = { ...data.value[stepKey], ...stepData }
    saveToStorage()

    const stepNumber = parseInt(stepKey.replace('step', ''))
    if (!isNaN(stepNumber)) {
      triggerAutoSave(stepNumber)
    }
  }

  // Auto-save with debounce
  const triggerAutoSave = (step: number) => {
    if (autoSaveTimer) {
      clearTimeout(autoSaveTimer)
    }

    autoSaveTimer = setTimeout(async () => {
      if (!canAutoSaveStep(step)) {
        return
      }

      try {
        await saveStepToBackend(step)
      } catch (error) {
        onboardingLogger.error(`Auto-save failed for step ${step}`, error)
      }
    }, 2000)
  }

  // Check if step has minimum data to attempt auto-save
  const canAutoSaveStep = (step: number): boolean => {
    switch (step) {
      case 1: {
        const s1 = data.value.step1
        return !!(s1.first_name && s1.last_name && s1.birth_date && s1.gender)
      }
      case 2: {
        const s2 = data.value.step2
        return !!(s2.curp || s2.rfc)
      }
      case 3: {
        const s3 = data.value.step3
        return !!(s3.street && s3.ext_number && s3.neighborhood && s3.postal_code && s3.state)
      }
      case 4: {
        const s4 = data.value.step4
        return !!(s4.employment_type && s4.monthly_income >= 1000)
      }
      case 5: {
        const appId = applicationStore.currentApplication?.id
        return !!(appId && appId !== 'null' && appId !== 'undefined' && data.value.step5.purpose)
      }
      case 6:
        return false
      case 7:
        return true
      default:
        return false
    }
  }

  // Reset all data
  const reset = () => {
    data.value = getDefaultData()
    completedSteps.value = []
    currentStep.value = 1
    lastSavedAt.value = null
    localStorage.removeItem(STORAGE_KEY)
    localStorage.removeItem('current_application_id')
    profileStore.reset()
  }

  // Handle application not found
  const handleApplicationNotFound = () => {
    onboardingLogger.warn('Application not found. Resetting onboarding to step 1')
    localStorage.removeItem('current_application_id')
    currentStep.value = 1
    completedSteps.value = completedSteps.value.filter(s => s <= 4)
    saveToStorage()
  }

  // Initialize
  const init = async () => {
    loadFromStorage()
    await loadFromBackend()

    if (currentStep.value >= 5) {
      const appId = applicationStore.currentApplication?.id
      if (!appId || appId === 'null' || appId === 'undefined') {
        onboardingLogger.warn('Onboarding init: No valid application for step >= 5, resetting to step 1')
        currentStep.value = 1
        completedSteps.value = completedSteps.value.filter(s => s <= 4)
        saveToStorage()
      }
    }
  }

  return {
    // State
    data,
    currentStep,
    completedSteps,
    isSaving,
    isLoading,
    lastSavedAt,
    // Getters
    progress,
    isStepCompleted,
    canNavigateToStep,
    // Actions
    init,
    loadFromBackend,
    saveStepToBackend,
    completeStep,
    goToStep,
    nextStep,
    prevStep,
    updateStepData,
    reset,
    handleApplicationNotFound
  }
})
