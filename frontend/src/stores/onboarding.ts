import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { useProfileStore } from './profile'
import * as profileService from '@/services/v2/profile.service'
import documentService from '@/services/v2/document.applicant.service'
import * as applicationService from '@/services/v2/application.applicant.service'
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
  work_phone: string
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
  /**
   * Datos del onboarding configurable por producto (MoneyCapital y similares).
   * Cada step persiste su valor bajo su `step.id` (ej. education, marital, location)
   * y opcionalmente campos individuales (state, city, education_level, salary_range, etc.).
   */
  dynamic: Record<string, unknown>
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
    work_phone: '',
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
  },
  dynamic: {}
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
  // Reemplaza recursivamente los data: URLs (imágenes base64 de la cámara) por
  // null antes de serializar a localStorage. Las imágenes pesan MB y reventarían
  // la cuota (~5MB) de localStorage; además ya se suben como documentos al
  // backend, así que no necesitan persistir en el draft local.
  const stripDataUrls = (value: unknown): unknown => {
    if (typeof value === 'string') {
      return value.startsWith('data:') ? null : value
    }
    if (Array.isArray(value)) {
      return value.map(stripDataUrls)
    }
    if (value && typeof value === 'object') {
      const out: Record<string, unknown> = {}
      for (const [k, v] of Object.entries(value as Record<string, unknown>)) {
        out[k] = stripDataUrls(v)
      }
      return out
    }
    return value
  }

  const saveToStorage = () => {
    try {
      const lightData = {
        ...data.value,
        dynamic: stripDataUrls(data.value.dynamic ?? {}),
      }
      localStorage.setItem(STORAGE_KEY, JSON.stringify({
        data: lightData,
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
            work_phone: emp.work_phone || '',
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

          // Always include INE fields if present
          if (s2.clave_elector || s2.numero_ocr || s2.folio_ine) {
            payload.ine_clave = s2.clave_elector || undefined
            payload.ine_ocr = s2.numero_ocr || undefined
            payload.ine_folio = s2.folio_ine || undefined
          }

          // Always include passport fields if present
          if (s2.passport_number || s2.passport_issue_date || s2.passport_expiry_date) {
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

          const employmentPayload = {
            employment_type: s4.employment_type,
            company_name: s4.company_name || undefined,
            position: s4.job_title || undefined,
            monthly_income: s4.monthly_income,
            seniority_years: Number(s4.seniority_years) || 0,
            seniority_months: Number(s4.seniority_months) || 0,
            work_phone: s4.work_phone || undefined
          }
          onboardingLogger.debug('saveStepToBackend step 4 - Employment payload', employmentPayload)
          await profileStore.updateEmployment(employmentPayload)
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

  // Cancel pending auto-save
  const cancelAutoSave = () => {
    if (autoSaveTimer) {
      clearTimeout(autoSaveTimer)
      autoSaveTimer = null
    }
  }

  // Mark step as completed and save
  const completeStep = async (step: number) => {
    // Cancel any pending auto-save to prevent duplicate requests
    cancelAutoSave()

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

  // ============================================================
  // Dynamic onboarding (configurable por producto, MoneyCapital y similares)
  // ============================================================
  const dynamicData = computed<Record<string, unknown>>(() => data.value.dynamic ?? {})

  const setDynamicField = (key: string, value: unknown): void => {
    if (!data.value.dynamic) data.value.dynamic = {}
    data.value.dynamic[key] = value
    saveToStorage()
  }

  // Salary range → monthly_income midpoint (alineado con backend SalaryRange::midpoint())
  const SALARY_MIDPOINT: Record<string, number> = {
    LT_3000: 2000,
    R_3001_6000: 4500,
    R_6001_9000: 7500,
    R_9001_12000: 10500,
    R_12001_15000: 13500,
    GT_15000: 20000,
  }

  // Mapeo housing_type frontend → backend enum
  const HOUSING_MAP: Record<string, 'OWNED' | 'RENTED' | 'FAMILY' | 'MORTGAGED' | 'EMPLOYER'> = {
    OWN: 'OWNED',
    RENT: 'RENTED',
    FAMILY: 'FAMILY',
    OTHER: 'EMPLOYER',
  }

  // Hash del último payload persistido por step, para no re-ejecutar (y no
  // duplicar POSTs de referencias/cuentas) al retroceder y avanzar sin cambios.
  const persistedHashes = new Map<string, string>()
  const inFlightSteps = new Set<string>()
  const hashOf = (payload: unknown): string => {
    try { return JSON.stringify(stripDataUrls(payload ?? null)) } catch { return Math.random().toString() }
  }
  // Algún sub-POST falló durante el step actual → no marcar como persistido.
  let stepHadError = false

  /**
   * Persistir el step recién completado contra el endpoint correcto del
   * profile API. Reutiliza los mismos endpoints que el flow legacy de
   * LendusFind (`/v2/applicant/profile/*`).
   *
   * `payload` es el valor del step (lo que se le pasa al renderer como
   * modelValue). `stepId` y `stepType` identifican qué dispatcher usar.
   *
   * Idempotente y serializado:
   *  - Si el payload no cambió desde la última persistencia EXITOSA, se omite.
   *  - Si ya hay una persistencia en curso para ese step, se ignora (evita
   *    duplicar POSTs de referencias/cuentas por llamadas concurrentes).
   *  - Solo marca el hash si NINGÚN sub-POST falló (evita marcar como guardado
   *    algo que en realidad falló).
   */
  const persistDynamic = async (stepId?: string, stepType?: string, payload?: unknown): Promise<void> => {
    saveToStorage()
    if (!stepId || !stepType) return // backward-compat: si se llama sin args, solo cache local

    const hash = hashOf(payload)
    if (persistedHashes.get(stepId) === hash) return // sin cambios → no re-persistir
    if (inFlightSteps.has(stepId)) return // ya hay una persistencia en curso para este step

    inFlightSteps.add(stepId)
    stepHadError = false
    try {
      isSaving.value = true
      switch (stepType) {
        case 'select': {
          // Education / Marital / Employment_type / SalaryRange comparten el patrón.
          if (stepId === 'education') {
            await profileStore.updatePersonalData({ education_level: String(payload || '') })
          } else if (stepId === 'marital') {
            await profileStore.updatePersonalData({ marital_status: String(payload || '') })
          } else if (stepId === 'employment') {
            const dyn = data.value.dynamic || {}
            const salaryCode = String(dyn.salary_range || '')
            await profileStore.updateEmployment({
              employment_type: String(payload || ''),
              monthly_income: SALARY_MIDPOINT[salaryCode] ?? 0,
            } as never)
          } else if (stepId === 'salary_range') {
            const dyn = data.value.dynamic || {}
            const empType = String(dyn.employment_type || '')
            if (empType) {
              await profileStore.updateEmployment({
                employment_type: empType,
                monthly_income: SALARY_MIDPOINT[String(payload || '')] ?? 0,
              } as never)
            }
            // Si todavía no hay employment_type, solo cache.
          }
          break
        }
        case 'state_city': {
          // Guardar en cache para mandarlo cuando el step `address` complete los demás campos.
          break
        }
        case 'number_select': {
          // online_loans_count → se guarda en Application.metadata (no tiene modelo propio).
          const appId = applicationStore.currentApplication?.id
          if (appId && payload !== null && payload !== undefined) {
            try {
              await applicationService.update(appId, {
                metadata: { [stepId]: payload },
              } as never)
            } catch (e) {
              stepHadError = true
              onboardingLogger.warn('update metadata failed', { error: e, stepId })
            }
          }
          break
        }
        case 'review':
          break
        case 'references': {
          const refs = Array.isArray(payload) ? (payload as Array<{ type: string; name: string; phone: string }>) : []
          // Borrar las referencias existentes para no duplicar al reeditar/retroceder.
          try {
            const existing = await profileService.listReferences()
            const list = existing.data ?? []
            for (const r of list) {
              try { await profileService.deleteReference(r.id) } catch { stepHadError = true }
            }
          } catch { stepHadError = true }
          for (const r of refs) {
            if (!r?.name || !r?.phone) continue
            // Split del nombre completo: 1ª palabra como nombre, resto como
            // apellido. Si solo hay una palabra, el apellido queda vacío (NO se
            // duplica el nombre como apellido).
            const parts = r.name.trim().split(/\s+/)
            const first = parts[0] ?? r.name
            const last = parts.length > 1 ? parts.slice(1).join(' ') : ''
            // El backend solo soporta type PERSONAL|WORK; el parentesco real va en relationship.
            const relationship = r.type === 'FAMILY' ? 'FAMILIAR' : 'AMIGO'
            try {
              await profileService.addReference({
                type: 'PERSONAL',
                first_name: first,
                last_name_1: last,
                phone: r.phone.replace(/\D/g, ''),
                relationship,
              } as never)
            } catch (e) {
              stepHadError = true
              onboardingLogger.warn('addReference failed', { error: e, ref: r })
            }
          }
          break
        }
        case 'bank_account': {
          const ba = payload as { type?: string; bank_code?: string; account_number?: string } | null
          if (ba?.bank_code && ba?.account_number) {
            const num = ba.account_number.replace(/\D/g, '')
            const pd = profileStore.profile?.personal_data
            const holder = [pd?.first_name, pd?.last_name_1, pd?.last_name_2]
              .filter(Boolean).join(' ').trim() || 'Titular'
            // Borrar cuentas existentes para no duplicar al reeditar/retroceder.
            try {
              const existing = await profileService.listBankAccounts()
              const list = existing.data?.bank_accounts ?? []
              for (const b of list) {
                try { await profileService.deleteBankAccount(b.id) } catch { stepHadError = true }
              }
            } catch { stepHadError = true }
            try {
              // POST /profile/bank-accounts (plural). Para CLABE manda `clabe`;
              // para tarjeta manda el número como `card_number`.
              const isCard = ba.type === 'CARD'
              await profileService.createBankAccount({
                clabe: isCard ? '' : num,
                card_number: isCard ? num : undefined,
                holder_name: holder,
                account_type: isCard ? 'TARJETA' : 'DEBITO',
              } as never)
            } catch (e) {
              stepHadError = true
              onboardingLogger.warn('createBankAccount failed', { error: e })
            }
          }
          break
        }
        case 'kyc_ine': {
          const k = payload as { front_image?: string; back_image?: string; personal?: Record<string, unknown> } | null
          const p = k?.personal ?? {}
          // Solo IDENTIFICADORES del INE (CURP + clave de elector + OCR + folio),
          // los mismos campos que Nubarium extrae por OCR. Nombre/fecha/etc. se
          // guardan en el step `personal_data`.
          const ineIds: Record<string, unknown> = {}
          if (p.curp) ineIds.curp = String(p.curp).toUpperCase()
          if (p.clave_elector) ineIds.ine_clave = String(p.clave_elector).toUpperCase()
          if (p.numero_ocr) ineIds.ine_ocr = String(p.numero_ocr)
          if (p.folio_ine) ineIds.ine_folio = String(p.folio_ine)
          if (Object.keys(ineIds).length > 0) {
            await profileStore.updateIdentifications(ineIds as never)
          }
          // Subir imágenes del INE (base64 desde la cámara).
          if (k?.front_image?.startsWith('data:')) {
            try { await documentService.uploadBase64(k.front_image, 'INE_FRONT', { file_name: 'ine_front.jpg' }) }
            catch (e) { stepHadError = true; onboardingLogger.warn('upload INE_FRONT failed', { error: e }) }
          }
          if (k?.back_image?.startsWith('data:')) {
            try { await documentService.uploadBase64(k.back_image, 'INE_BACK', { file_name: 'ine_back.jpg' }) }
            catch (e) { stepHadError = true; onboardingLogger.warn('upload INE_BACK failed', { error: e }) }
          }
          break
        }
        case 'personal_data': {
          const pd = payload as Record<string, unknown> | null
          if (!pd) break
          const personalPayload: Record<string, unknown> = {}
          if (pd.first_name) personalPayload.first_name = pd.first_name
          if (pd.last_name) personalPayload.last_name_1 = pd.last_name
          if (pd.second_last_name) personalPayload.last_name_2 = pd.second_last_name
          if (pd.birth_date) personalPayload.birth_date = pd.birth_date
          if (pd.gender) personalPayload.gender = pd.gender
          if (pd.birth_state) personalPayload.birth_state = pd.birth_state
          if (pd.nationality) personalPayload.nationality = pd.nationality
          if (Object.keys(personalPayload).length > 0) {
            await profileStore.updatePersonalData(personalPayload as never)
          }
          if (pd.rfc) {
            await profileStore.updateIdentifications({ rfc: String(pd.rfc).toUpperCase() } as never)
          }
          break
        }
        case 'address': {
          const ad = payload as Record<string, unknown> | null
          if (!ad) break
          const housing = HOUSING_MAP[String(ad.housing_type || '')] || 'FAMILY'
          await profileStore.updateAddress({
            street: String(ad.street || ''),
            ext_number: String(ad.ext_number || '') || undefined,
            int_number: String(ad.int_number || '') || undefined,
            neighborhood: String(ad.neighborhood || ''),
            municipality: String(ad.municipality || '') || undefined,
            city: String(ad.city || ad.municipality || ''),
            state: String(ad.state || ''),
            postal_code: String(ad.postal_code || ''),
            housing_type: housing,
            years_at_address: Number(ad.years_at_address || 0),
            months_at_address: Number(ad.months_at_address || 0),
          } as never)
          break
        }
        case 'kyc_selfie': {
          // El selfie llega como base64 (string) o { image } según el renderer.
          const img = typeof payload === 'string'
            ? payload
            : (payload as { image?: string } | null)?.image
          if (img?.startsWith('data:')) {
            try { await documentService.uploadBase64(img, 'SELFIE', { file_name: 'selfie.jpg' }) }
            catch (e) { stepHadError = true; onboardingLogger.warn('upload SELFIE failed', { error: e }) }
          }
          break
        }
        case 'review_full':
          break
      }
      lastSavedAt.value = new Date()
      // Marca como persistido SOLO si ningún sub-POST falló (así se reintenta
      // en el siguiente avance si algo falló silenciosamente).
      if (!stepHadError) persistedHashes.set(stepId, hash)
    } catch (e) {
      onboardingLogger.warn('persistDynamic dispatcher failed', { error: e, stepId, stepType })
    } finally {
      inFlightSteps.delete(stepId)
      isSaving.value = false
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
    dynamicData,
    // Actions
    init,
    loadFromBackend,
    saveStepToBackend,
    completeStep,
    goToStep,
    nextStep,
    prevStep,
    updateStepData,
    setDynamicField,
    persistDynamic,
    reset,
    handleApplicationNotFound
  }
})
