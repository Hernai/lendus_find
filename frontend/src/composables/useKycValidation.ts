import { ref, computed } from 'vue'
import { useKycStore } from '@/stores/kyc'
import { storeToRefs } from 'pinia'

export type KycStep = 'ine-front' | 'ine-back' | 'selfie' | 'validating' | 'result'

export interface ValidationStep {
  key: string
  label: string
  status: 'pending' | 'in_progress' | 'success' | 'error' | 'warning'
  message?: string
}

export interface UseKycValidationReturn {
  /** Current step in the KYC process */
  currentStep: import('vue').Ref<KycStep>
  /** All validation steps with status */
  validationSteps: import('vue').ComputedRef<ValidationStep[]>
  /** Whether validation is complete */
  isComplete: import('vue').ComputedRef<boolean>
  /** Whether all validations passed */
  allPassed: import('vue').ComputedRef<boolean>
  /** Overall error message */
  error: import('vue').ComputedRef<string | null>
  /** Move to next step */
  nextStep: () => void
  /** Move to previous step */
  previousStep: () => void
  /** Go to a specific step */
  goToStep: (step: KycStep) => void
  /** Run all validations after images captured */
  runValidations: () => Promise<boolean>
  /** Retry failed validations */
  retryValidations: () => Promise<boolean>
  /** Reset the entire KYC process */
  resetKyc: () => void
}

/**
 * Composable for orchestrating the KYC validation process.
 * Coordinates between capturing images and running validations.
 */
export function useKycValidation(): UseKycValidationReturn {
  const kycStore = useKycStore()
  const {
    validations,
    lockedData,
    isValidating,
    error: storeError,
    ineFrontImage,
    ineBackImage,
    verified
  } = storeToRefs(kycStore)

  // Current step in the process
  const currentStep = ref<KycStep>('ine-front')

  // Track individual validation statuses
  const validationProgress = ref<Record<string, 'pending' | 'in_progress' | 'success' | 'error' | 'warning'>>({
    ine_ocr: 'pending',
    ine_lista_nominal: 'pending',
    curp_renapo: 'pending',
    pld: 'pending',
    ofac: 'pending'
  })

  const validationMessages = ref<Record<string, string>>({})

  // Step order for navigation
  const stepOrder: KycStep[] = ['ine-front', 'ine-back', 'selfie', 'validating', 'result']

  // Computed validation steps for UI display
  const validationSteps = computed<ValidationStep[]>(() => [
    {
      key: 'ine_ocr',
      label: 'Extrayendo datos del INE (OCR)',
      status: validationProgress.value.ine_ocr || 'pending',
      message: validationMessages.value.ine_ocr || undefined
    },
    {
      key: 'ine_lista_nominal',
      label: 'Verificando en lista nominal del INE',
      status: validationProgress.value.ine_lista_nominal || 'pending',
      message: validationMessages.value.ine_lista_nominal || undefined
    },
    {
      key: 'curp_renapo',
      label: 'Validando CURP con RENAPO',
      status: validationProgress.value.curp_renapo || 'pending',
      message: validationMessages.value.curp_renapo || undefined
    },
    {
      key: 'pld',
      label: 'Verificando listas PLD (México)',
      status: validationProgress.value.pld || 'pending',
      message: validationMessages.value.pld || undefined
    },
    {
      key: 'ofac',
      label: 'Verificando listas OFAC (Internacional)',
      status: validationProgress.value.ofac || 'pending',
      message: validationMessages.value.ofac || undefined
    }
  ])

  // Check if all validations are complete
  const isComplete = computed(() => {
    return Object.values(validationProgress.value).every(
      status => status === 'success' || status === 'error' || status === 'warning'
    )
  })

  // Check if all validations passed (warning counts as passed - just needs review)
  const allPassed = computed(() => {
    return Object.values(validationProgress.value).every(
      status => status === 'success' || status === 'warning'
    )
  })

  // Get overall error
  const error = computed(() => {
    if (storeError.value) return storeError.value

    const failed = Object.entries(validationProgress.value).find(
      ([, status]) => status === 'error'
    )
    if (failed) {
      return validationMessages.value[failed[0]] || 'Error en la verificación'
    }

    return null
  })

  // Navigation functions
  const nextStep = () => {
    const currentIndex = stepOrder.indexOf(currentStep.value)
    if (currentIndex < stepOrder.length - 1) {
      const next = stepOrder[currentIndex + 1]
      if (next) {
        currentStep.value = next
      }
    }
  }

  const previousStep = () => {
    const currentIndex = stepOrder.indexOf(currentStep.value)
    if (currentIndex > 0) {
      const prev = stepOrder[currentIndex - 1]
      if (prev) {
        currentStep.value = prev
      }
    }
  }

  const goToStep = (step: KycStep) => {
    if (stepOrder.includes(step)) {
      currentStep.value = step
    }
  }

  /**
   * Run all KYC validations sequentially
   */
  const runValidations = async (): Promise<boolean> => {
    console.log('[KYC] Starting runValidations...')

    if (!ineFrontImage.value) {
      validationMessages.value.ine_ocr = 'Falta la imagen frontal del INE'
      validationProgress.value.ine_ocr = 'error'
      return false
    }

    // Reset progress
    validationProgress.value = {
      ine_ocr: 'pending',
      ine_lista_nominal: 'pending',
      curp_renapo: 'pending',
      pld: 'pending',
      ofac: 'pending'
    }
    validationMessages.value = {}

    try {
      // Step 1: Validate INE (OCR + Lista Nominal)
      console.log('[KYC] Step 1: Validating INE...')
      validationProgress.value.ine_ocr = 'in_progress'

      const ineValid = await kycStore.validateIne()
      console.log('[KYC] INE validation result:', ineValid, validations.value.ine_ocr)

      if (validations.value.ine_ocr?.success) {
        validationProgress.value.ine_ocr = 'success'
        validationMessages.value.ine_ocr = 'Datos extraídos correctamente'
      } else {
        validationProgress.value.ine_ocr = 'error'
        validationMessages.value.ine_ocr = validations.value.ine_ocr?.error || 'Error al leer el INE'
        return false
      }

      // Check lista nominal result
      console.log('[KYC] Step 1b: Checking lista nominal...')
      validationProgress.value.ine_lista_nominal = 'in_progress'
      await new Promise(resolve => setTimeout(resolve, 500)) // Small delay for UX

      if (validations.value.ine_lista_nominal?.valid) {
        validationProgress.value.ine_lista_nominal = 'success'
        validationMessages.value.ine_lista_nominal = 'INE vigente y válido'
      } else {
        validationProgress.value.ine_lista_nominal = 'error'
        validationMessages.value.ine_lista_nominal =
          validations.value.ine_lista_nominal?.message || 'INE no encontrado en lista nominal'
        // Continue with warnings instead of failing
      }

      // Step 2: Validate CURP with RENAPO
      console.log('[KYC] Step 2: Validating CURP...', lockedData.value.curp)
      if (lockedData.value.curp) {
        validationProgress.value.curp_renapo = 'in_progress'

        const curpValid = await kycStore.validateCurp()
        console.log('[KYC] CURP validation result:', curpValid)

        if (curpValid) {
          validationProgress.value.curp_renapo = 'success'
          validationMessages.value.curp_renapo = 'CURP validado con RENAPO'
        } else {
          validationProgress.value.curp_renapo = 'error'
          validationMessages.value.curp_renapo = 'CURP no coincide con RENAPO'
        }
      } else {
        validationProgress.value.curp_renapo = 'error'
        validationMessages.value.curp_renapo = 'No se pudo extraer el CURP del INE'
      }

      // Step 3: Check PLD (Mexican blacklists - PGR, PGJ, PEPs, SAT, etc.)
      // PLD is NON-BLOCKING - shows warning for review but doesn't fail validation
      console.log('[KYC] Step 3: Checking PLD blacklists...')
      validationProgress.value.pld = 'in_progress'

      const pldClear = await kycStore.checkPldBlacklists()
      console.log('[KYC] PLD check result:', pldClear)

      if (pldClear) {
        validationProgress.value.pld = 'success'
        validationMessages.value.pld = 'Sin alertas en listas PLD'
      } else {
        // Mark as warning (requires review) but don't block
        validationProgress.value.pld = 'warning'
        validationMessages.value.pld = 'Requiere revisión - posibles coincidencias en listas PLD'
      }

      // Step 4: Check OFAC (International sanctions - US OFAC, UN)
      // OFAC is NON-BLOCKING - shows warning for review but doesn't fail validation
      console.log('[KYC] Step 4: Checking OFAC...')
      validationProgress.value.ofac = 'in_progress'

      const ofacClear = await kycStore.checkOfac()
      console.log('[KYC] OFAC check result:', ofacClear)

      if (ofacClear) {
        validationProgress.value.ofac = 'success'
        validationMessages.value.ofac = 'Sin alertas en listas OFAC'
      } else {
        // Mark as warning (requires review) but don't block
        validationProgress.value.ofac = 'warning'
        validationMessages.value.ofac = 'Requiere revisión - posibles coincidencias en OFAC'
      }

      // Mark as verified if critical validations passed
      // PLD and OFAC warnings don't block - they just flag for review
      const criticalPassed =
        validationProgress.value.ine_ocr === 'success' &&
        validationProgress.value.curp_renapo === 'success'

      console.log('[KYC] Critical passed:', criticalPassed, 'ine_ocr:', validationProgress.value.ine_ocr, 'curp:', validationProgress.value.curp_renapo)

      if (criticalPassed) {
        kycStore.markVerified()
      }

      console.log('[KYC] Final validation progress:', JSON.stringify(validationProgress.value))
      return criticalPassed
    } catch (err) {
      console.error('[KYC] Validation error:', err)
      return false
    }
  }

  /**
   * Retry failed validations
   */
  const retryValidations = async (): Promise<boolean> => {
    // Only retry failed ones
    const failedSteps = Object.entries(validationProgress.value)
      .filter(([, status]) => status === 'error')
      .map(([key]) => key)

    if (failedSteps.length === 0) {
      return true
    }

    for (const step of failedSteps) {
      validationProgress.value[step] = 'in_progress'
      validationMessages.value[step] = ''

      try {
        switch (step) {
          case 'ine_ocr':
          case 'ine_lista_nominal':
            await kycStore.validateIne()
            validationProgress.value.ine_ocr = validations.value.ine_ocr?.success
              ? 'success'
              : 'error'
            validationProgress.value.ine_lista_nominal = validations.value.ine_lista_nominal?.valid
              ? 'success'
              : 'error'
            break

          case 'curp_renapo':
            const curpValid = await kycStore.validateCurp()
            validationProgress.value.curp_renapo = curpValid ? 'success' : 'error'
            break

          case 'pld':
            const pldClear = await kycStore.checkPldBlacklists()
            validationProgress.value.pld = pldClear ? 'success' : 'warning'
            break

          case 'ofac':
            const ofacClear = await kycStore.checkOfac()
            validationProgress.value.ofac = ofacClear ? 'success' : 'warning'
            break
        }
      } catch {
        validationProgress.value[step] = 'error'
      }
    }

    return allPassed.value
  }

  /**
   * Reset the entire KYC process
   */
  const resetKyc = () => {
    currentStep.value = 'ine-front'
    validationProgress.value = {
      ine_ocr: 'pending',
      ine_lista_nominal: 'pending',
      curp_renapo: 'pending',
      pld: 'pending',
      ofac: 'pending'
    }
    validationMessages.value = {}
    kycStore.reset()
  }

  return {
    currentStep,
    validationSteps,
    isComplete,
    allPassed,
    error,
    nextStep,
    previousStep,
    goToStep,
    runValidations,
    retryValidations,
    resetKyc
  }
}
