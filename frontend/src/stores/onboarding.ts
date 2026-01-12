import { defineStore } from 'pinia'
import { ref, computed, watch } from 'vue'
import { useApplicantStore } from './applicant'
import { useApplicationStore } from './application'
import type { Address, EmploymentRecord, BankAccount, Reference } from '@/types'

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
}

interface Step4Data {
  employment_type: string
  company_name: string
  job_title: string
  monthly_income: number
  seniority_years: number
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
 * Handles multiple input formats: DD/MM/YYYY, DD-MM-YYYY, YYYY-MM-DD
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

  // Return as-is if no pattern matches
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
  const applicantStore = useApplicantStore()
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
      console.error('Failed to load onboarding data from storage:', e)
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
      console.error('Failed to save onboarding data to storage:', e)
    }
  }

  // Load existing data from backend
  const loadFromBackend = async () => {
    isLoading.value = true
    try {
      await applicantStore.loadApplicant()

      // Try to restore current application from localStorage if not already loaded
      const currentAppId = applicationStore.currentApplication?.id
      const savedAppId = localStorage.getItem('current_application_id')

      if (!currentAppId || currentAppId === 'null' || currentAppId === 'undefined') {
        // No application in store, try to restore from localStorage
        if (savedAppId && savedAppId !== 'null' && savedAppId !== 'undefined') {
          console.log('🔄 Restoring application from localStorage:', savedAppId)
          try {
            await applicationStore.loadApplication(savedAppId)
            console.log('✅ Application restored:', applicationStore.currentApplication?.id)
          } catch (e) {
            console.warn('⚠️ Could not restore application:', e)
            localStorage.removeItem('current_application_id')
          }
        } else {
          console.log('⚠️ No application ID in localStorage to restore')
        }
      } else {
        console.log('✅ Application already loaded:', currentAppId)
      }

      if (applicantStore.applicant) {
        const a = applicantStore.applicant

        // Populate step 1
        if (a.first_name) {
          data.value.step1 = {
            first_name: a.first_name || '',
            last_name: a.last_name_1 || '',
            second_last_name: a.last_name_2 || '',
            birth_date: a.birth_date || '',
            birth_state: '',
            gender: a.gender || '',
            nationality: a.nationality || 'MX',
            marital_status: a.marital_status || ''
          }
          if (!completedSteps.value.includes(1)) {
            completedSteps.value.push(1)
          }
        }

        // Populate step 2
        if (a.curp || a.rfc) {
          data.value.step2.curp = a.curp || ''
          data.value.step2.rfc = a.rfc || ''
          data.value.step2.clave_elector = a.ine_clave || ''
          data.value.step2.numero_ocr = a.ine_ocr || ''
          data.value.step2.folio_ine = a.ine_folio || ''
          data.value.step2.passport_number = a.passport_number || ''
          data.value.step2.passport_issue_date = a.passport_issue_date || ''
          data.value.step2.passport_expiry_date = a.passport_expiry_date || ''
          // Determine ID type based on which fields are populated
          if (a.passport_number) {
            data.value.step2.id_type = 'PASSPORT'
          }
          if (!completedSteps.value.includes(2)) {
            completedSteps.value.push(2)
          }
        }

        // Populate step 3 from primary address
        if (a.primary_address) {
          const addr = a.primary_address
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

        // Populate step 4 from current employment
        if (a.current_employment) {
          const emp = a.current_employment
          data.value.step4 = {
            employment_type: emp.employment_type || '',
            company_name: emp.company_name || '',
            job_title: emp.position || '', // Backend sends 'position'
            monthly_income: emp.monthly_income || 0,
            seniority_years: Math.floor((emp.seniority_months || 0) / 12), // Convert months to years
            company_phone: emp.work_phone || '', // Backend sends 'work_phone'
            company_address: ''
          }
          if (!completedSteps.value.includes(4)) {
            completedSteps.value.push(4)
          }
        }

        // Step 5 is loan details - nothing to load from applicant data
        // Purpose and confirmation are set during the onboarding flow

        saveToStorage()
      }
    } catch (e) {
      console.error('Failed to load from backend:', e)
    } finally {
      isLoading.value = false
    }
  }

  // Save specific step to backend
  const saveStepToBackend = async (step: number) => {
    isSaving.value = true
    try {
      switch (step) {
        case 1: {
          // Validate required fields for step 1
          const s1 = data.value.step1
          if (!s1.first_name || !s1.last_name || !s1.birth_date || !s1.gender) {
            throw new Error('Faltan campos requeridos en datos personales')
          }
          await applicantStore.updatePersonalData({
            first_name: s1.first_name,
            last_name_1: s1.last_name,
            last_name_2: s1.second_last_name || undefined,
            birth_date: formatDateForApi(s1.birth_date),
            birth_state: s1.birth_state || undefined,
            gender: s1.gender as 'M' | 'F',
            nationality: s1.nationality || 'MX',
            marital_status: s1.marital_status || ''
          })
          break
        }

        case 2:
          await applicantStore.updateIdentification(
            data.value.step2.rfc,
            data.value.step2.curp,
            data.value.step2.id_type === 'INE'
              ? {
                  ine_clave: data.value.step2.clave_elector,
                  ine_ocr: data.value.step2.numero_ocr,
                  ine_folio: data.value.step2.folio_ine
                }
              : {
                  passport_number: data.value.step2.passport_number,
                  passport_issue_date: data.value.step2.passport_issue_date,
                  passport_expiry_date: data.value.step2.passport_expiry_date
                }
          )
          break

        case 3: {
          // Validate required fields for step 3
          const s3 = data.value.step3
          if (!s3.street || !s3.ext_number || !s3.neighborhood || !s3.postal_code || !s3.state || !s3.housing_type) {
            throw new Error('Faltan campos requeridos en dirección')
          }
          await applicantStore.updateAddress({
            street: s3.street,
            ext_number: s3.ext_number,
            int_number: s3.int_number || undefined,
            neighborhood: s3.neighborhood,
            postal_code: s3.postal_code,
            city: s3.city || s3.municipality,
            state: s3.state,
            municipality: s3.municipality || undefined,
            housing_type: s3.housing_type as any,
            years_at_address: Number(s3.years_at_address) || 0,
            months_at_address: Number(s3.months_at_address) || 0
          })
          break
        }

        case 4: {
          // Validate required fields for step 4
          const s4 = data.value.step4
          if (!s4.employment_type || s4.monthly_income < 1000) {
            throw new Error('Faltan campos requeridos en información laboral')
          }
          await applicantStore.updateEmployment({
            employment_type: s4.employment_type as any,
            company_name: s4.company_name || undefined,
            position: s4.job_title || undefined, // Backend expects 'position', not 'job_title'
            monthly_income: s4.monthly_income,
            seniority_months: (Number(s4.seniority_years) || 0) * 12, // Convert years to months for backend
            work_phone: s4.company_phone || undefined // Backend expects 'work_phone', not 'company_phone'
          })
          break
        }

        case 5: {
          // Loan details - save purpose directly to application (not to dynamic_data)
          const currentId = applicationStore.currentApplication?.id
          console.log('🔍 Step 5 save - currentApplication:', currentId || 'NULL')

          // Check if current application has valid ID
          const hasValidId = currentId && currentId !== 'null' && currentId !== 'undefined'

          // If no current application or invalid ID, try to load or create one
          if (!applicationStore.currentApplication || !hasValidId) {
            console.warn('⚠️ No valid currentApplication - attempting recovery...')

            // Try to load from localStorage
            let savedAppId = localStorage.getItem('current_application_id')

            // Clean up invalid saved IDs
            if (savedAppId === 'null' || savedAppId === 'undefined' || savedAppId === '') {
              console.log('🧹 Cleaning up invalid savedAppId:', savedAppId)
              localStorage.removeItem('current_application_id')
              savedAppId = null
            }

            if (savedAppId) {
              console.log('🔄 Trying to load application from saved ID:', savedAppId)
              try {
                await applicationStore.loadApplication(savedAppId)
              } catch (e) {
                console.warn('❌ Could not load application:', e)
                localStorage.removeItem('current_application_id')
              }
            }
          }

          // Check again if we have a valid application now
          const finalId = applicationStore.currentApplication?.id
          const hasFinalValidId = finalId && finalId !== 'null' && finalId !== 'undefined'

          if (applicationStore.currentApplication && hasFinalValidId) {
            console.log('📤 Updating application with purpose:', data.value.step5.purpose)
            await applicationStore.updateApplication({
              purpose: data.value.step5.purpose
            })
          } else {
            // Last resort: throw an error so the user sees feedback
            throw new Error('No se encontró la solicitud. Por favor, regresa al inicio y vuelve a empezar.')
          }
          break
        }

        // Steps 6 and 7 handled separately (documents and references)
      }

      lastSavedAt.value = new Date()
      saveToStorage()
    } catch (e) {
      console.error(`Failed to save step ${step} to backend:`, e)
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

    // Trigger auto-save with debounce (extract step number from stepKey)
    const stepNumber = parseInt(stepKey.replace('step', ''))
    if (!isNaN(stepNumber)) {
      triggerAutoSave(stepNumber)
    }
  }

  // Auto-save with debounce (2 seconds after last change)
  const triggerAutoSave = (step: number) => {
    // Clear existing timer
    if (autoSaveTimer) {
      clearTimeout(autoSaveTimer)
    }

    // Set new timer
    autoSaveTimer = setTimeout(async () => {
      // Check if minimum required fields are filled before attempting save
      if (!canAutoSaveStep(step)) {
        console.log(`⏭️ Skipping auto-save for step ${step} - required fields not filled`)
        return
      }

      try {
        console.log(`💾 Auto-saving step ${step}...`)
        await saveStepToBackend(step)
        console.log(`✅ Auto-save complete for step ${step}`)
      } catch (error) {
        console.error(`❌ Auto-save failed for step ${step}:`, error)
        // Don't throw - auto-save failures shouldn't block the user
      }
    }, 2000) // 2 seconds debounce
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
        // At least CURP or RFC should be filled
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
        // Step 5 needs application ID to save
        const appId = applicationStore.currentApplication?.id
        return !!(appId && appId !== 'null' && appId !== 'undefined' && data.value.step5.purpose)
      }
      case 6:
        // Documents don't auto-save, they upload individually
        return false
      case 7:
        // References can always be saved
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
  }

  // Initialize
  const init = async () => {
    loadFromStorage()
    await loadFromBackend()

    // Validate: If currentStep >= 5, we need a valid application
    // (Steps 5+ save to application, not just applicant)
    if (currentStep.value >= 5) {
      const appId = applicationStore.currentApplication?.id
      if (!appId || appId === 'null' || appId === 'undefined') {
        console.warn('⚠️ Onboarding init: No valid application for step >= 5, resetting to step 1')
        currentStep.value = 1
        // Keep completedSteps for steps 1-4 if they were completed
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
    reset
  }
})
