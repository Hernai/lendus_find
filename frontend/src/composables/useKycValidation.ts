import { ref, computed } from 'vue'
import { useKycStore } from '@/stores/kyc'
import { useApplicantStore } from '@/stores/applicant'
import { useApplicationStore } from '@/stores/application'
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
  /** Whether selfie/face match is required for current product */
  requiresSelfie: import('vue').ComputedRef<boolean>
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
  const applicantStore = useApplicantStore()
  const applicationStore = useApplicationStore()
  const {
    validations,
    lockedData,
    error: storeError,
    ineFrontImage,
    selfieImage
  } = storeToRefs(kycStore)

  // Current step in the process
  const currentStep = ref<KycStep>('ine-front')

  // Check if selfie/face match is required for the current product
  // Products can specify 'SELFIE' in their required_documents array
  const requiresSelfie = computed(() => {
    const product = applicationStore.selectedProduct
    if (!product) return false

    // Check if product requires selfie in required_documents or required_docs
    // Backend may send either field name depending on the endpoint
    const requiredDocs = product.required_documents || product.required_docs || []
    console.log('[KYC] Checking requiresSelfie - requiredDocs:', requiredDocs)

    return requiredDocs.some((doc) => {
      // Handle both string and object formats
      const docType = typeof doc === 'string' ? doc : doc.type
      const upperType = docType?.toUpperCase() || ''
      return upperType === 'SELFIE' ||
             upperType === 'FACE_MATCH' ||
             upperType === 'BIOMETRIC'
    })
  })

  // Track individual validation statuses
  const validationProgress = ref<Record<string, 'pending' | 'in_progress' | 'success' | 'error' | 'warning'>>({
    ine_ocr: 'pending',
    ine_lista_nominal: 'pending',
    curp_renapo: 'pending',
    pld: 'pending',
    ofac: 'pending',
    face_match: 'pending'
  })

  const validationMessages = ref<Record<string, string>>({})

  // Step order for navigation
  const stepOrder: KycStep[] = ['ine-front', 'ine-back', 'selfie', 'validating', 'result']

  // Computed validation steps for UI display
  const validationSteps = computed<ValidationStep[]>(() => {
    const steps: ValidationStep[] = [
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
      }
    ]

    // Add face match step only if selfie is required
    if (requiresSelfie.value) {
      steps.push({
        key: 'face_match',
        label: 'Comparando rostro con INE',
        status: validationProgress.value.face_match || 'pending',
        message: validationMessages.value.face_match || undefined
      })
    }

    // Always add PLD and OFAC steps
    steps.push(
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
    )

    return steps
  })

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
      face_match: 'pending',
      pld: 'pending',
      ofac: 'pending'
    }
    validationMessages.value = {}

    try {
      // Get applicant_id for auto-recording verifications
      const applicantId = applicantStore.applicant?.id
      console.log('[KYC] Applicant ID for auto-recording:', applicantId)

      // Step 1: Validate INE (OCR + Lista Nominal)
      console.log('[KYC] Step 1: Validating INE...')
      validationProgress.value.ine_ocr = 'in_progress'

      const ineValid = await kycStore.validateIne(applicantId)
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

        const curpValid = await kycStore.validateCurp(undefined, applicantId)
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

      // Step 3: Face Match (if selfie is required)
      // Compares selfie with INE photo to verify identity
      if (requiresSelfie.value && selfieImage.value) {
        console.log('[KYC] Step 3: Validating face match...')
        validationProgress.value.face_match = 'in_progress'

        const faceMatchValid = await kycStore.validateFaceMatch(applicantId)
        console.log('[KYC] Face match result:', faceMatchValid)

        if (faceMatchValid) {
          validationProgress.value.face_match = 'success'
          validationMessages.value.face_match = 'Rostro verificado correctamente'
        } else {
          validationProgress.value.face_match = 'error'
          validationMessages.value.face_match = 'El rostro no coincide con la foto del INE'
        }
      } else if (requiresSelfie.value) {
        // Selfie required but not captured
        validationProgress.value.face_match = 'error'
        validationMessages.value.face_match = 'Falta la imagen de selfie'
      } else {
        // Selfie not required - mark as success (skipped)
        validationProgress.value.face_match = 'success'
      }

      // Step 4: Check PLD (Mexican blacklists - PGR, PGJ, PEPs, SAT, etc.)
      // PLD is NON-BLOCKING - shows warning for review but doesn't fail validation
      console.log('[KYC] Step 4: Checking PLD blacklists...')
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

      // Step 5: Check OFAC (International sanctions - US OFAC, UN)
      // OFAC is NON-BLOCKING - shows warning for review but doesn't fail validation
      console.log('[KYC] Step 5: Checking OFAC...')
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
      // Face match is only critical if selfie is required
      const faceMatchPassed = !requiresSelfie.value || validationProgress.value.face_match === 'success'
      const criticalPassed =
        validationProgress.value.ine_ocr === 'success' &&
        validationProgress.value.curp_renapo === 'success' &&
        faceMatchPassed

      console.log('[KYC] Critical passed:', criticalPassed, 'ine_ocr:', validationProgress.value.ine_ocr, 'curp:', validationProgress.value.curp_renapo, 'face_match:', validationProgress.value.face_match)

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

    // Get applicant_id for auto-recording verifications
    const applicantId = applicantStore.applicant?.id

    for (const step of failedSteps) {
      validationProgress.value[step] = 'in_progress'
      validationMessages.value[step] = ''

      try {
        switch (step) {
          case 'ine_ocr':
          case 'ine_lista_nominal':
            await kycStore.validateIne(applicantId)
            validationProgress.value.ine_ocr = validations.value.ine_ocr?.success
              ? 'success'
              : 'error'
            validationProgress.value.ine_lista_nominal = validations.value.ine_lista_nominal?.valid
              ? 'success'
              : 'error'
            break

          case 'curp_renapo':
            const curpValid = await kycStore.validateCurp(undefined, applicantId)
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

          case 'face_match':
            if (requiresSelfie.value && selfieImage.value) {
              const faceMatchValid = await kycStore.validateFaceMatch(applicantId)
              validationProgress.value.face_match = faceMatchValid ? 'success' : 'error'
            }
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
      face_match: 'pending',
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
    requiresSelfie,
    nextStep,
    previousStep,
    goToStep,
    runValidations,
    retryValidations,
    resetKyc
  }
}
