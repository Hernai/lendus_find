import { ref, type Ref } from 'vue'
import { api } from '@/services/api'
import { logger } from '@/utils/logger'

const kycLogger = logger.child('KYC')

/**
 * KYC validation results interface.
 */
export interface KycValidationResults {
  ine_ocr: {
    success: boolean
    data?: Record<string, unknown>
    error?: string
  } | null
  ine_lista_nominal: {
    valid: boolean
    code?: string
    message?: string
  } | null
  curp_renapo: {
    valid: boolean
    data?: Record<string, unknown>
  } | null
  face_match: {
    score: number
    match: boolean
  } | null
  liveness: {
    passed: boolean
    score?: number
  } | null
  ofac: {
    found: boolean
    matches: unknown[]
    score: number
  } | null
}

/**
 * Composable for KYC validation operations.
 *
 * Handles INE OCR, CURP, RFC, and OFAC validations.
 */
export function useKycValidations() {
  const isValidating: Ref<boolean> = ref(false)
  const validationError: Ref<string | null> = ref(null)
  const validations: Ref<KycValidationResults> = ref({
    ine_ocr: null,
    ine_lista_nominal: null,
    curp_renapo: null,
    face_match: null,
    liveness: null,
    ofac: null,
  })

  /**
   * Validate INE document via OCR.
   */
  const validateIne = async (
    ineFrontBase64: string,
    ineBackBase64: string
  ): Promise<{
    success: boolean
    data?: Record<string, unknown>
    error?: string
  }> => {
    isValidating.value = true
    validationError.value = null

    try {
      kycLogger.debug('Starting INE validation')

      const response = await api.post('/kyc/ine/validate', {
        ine_front: ineFrontBase64,
        ine_back: ineBackBase64,
      })

      const result = {
        success: response.data.ocr_data != null,
        data: response.data.ocr_data,
        error: response.data.error,
      }

      validations.value.ine_ocr = result

      if (response.data.list_validation) {
        validations.value.ine_lista_nominal = response.data.list_validation
      }

      kycLogger.debug('INE validation completed', { success: result.success })
      return result
    } catch (error) {
      const message = error instanceof Error ? error.message : 'Error validating INE'
      validationError.value = message
      kycLogger.error('INE validation failed', { error: message })
      return { success: false, error: message }
    } finally {
      isValidating.value = false
    }
  }

  /**
   * Validate CURP against RENAPO.
   */
  const validateCurp = async (
    curp: string
  ): Promise<{
    valid: boolean
    data?: Record<string, unknown>
    error?: string
  }> => {
    isValidating.value = true
    validationError.value = null

    try {
      kycLogger.debug('Starting CURP validation')

      const response = await api.post('/kyc/curp/validate', { curp })

      const result = {
        valid: response.data.valid === true,
        data: response.data.data,
        error: response.data.error,
      }

      validations.value.curp_renapo = result

      kycLogger.debug('CURP validation completed', { valid: result.valid })
      return result
    } catch (error) {
      const message = error instanceof Error ? error.message : 'Error validating CURP'
      validationError.value = message
      kycLogger.error('CURP validation failed', { error: message })
      return { valid: false, error: message }
    } finally {
      isValidating.value = false
    }
  }

  /**
   * Validate RFC against SAT.
   */
  const validateRfc = async (
    rfc: string
  ): Promise<{
    valid: boolean
    razon_social?: string
    error?: string
  }> => {
    isValidating.value = true
    validationError.value = null

    try {
      kycLogger.debug('Starting RFC validation')

      const response = await api.post('/kyc/rfc/validate', { rfc })

      const result = {
        valid: response.data.valid === true,
        razon_social: response.data.razon_social,
        error: response.data.error,
      }

      kycLogger.debug('RFC validation completed', { valid: result.valid })
      return result
    } catch (error) {
      const message = error instanceof Error ? error.message : 'Error validating RFC'
      validationError.value = message
      kycLogger.error('RFC validation failed', { error: message })
      return { valid: false, error: message }
    } finally {
      isValidating.value = false
    }
  }

  /**
   * Check OFAC sanctions list.
   */
  const checkOfac = async (
    fullName: string
  ): Promise<{
    found: boolean
    matches: unknown[]
    score: number
  }> => {
    isValidating.value = true
    validationError.value = null

    try {
      kycLogger.debug('Starting OFAC check')

      const response = await api.post('/kyc/ofac/check', { name: fullName })

      const result = {
        found: response.data.found === true,
        matches: response.data.matches || [],
        score: response.data.score || 0,
      }

      validations.value.ofac = result

      kycLogger.debug('OFAC check completed', { found: result.found })
      return result
    } catch (error) {
      const message = error instanceof Error ? error.message : 'Error checking OFAC'
      validationError.value = message
      kycLogger.error('OFAC check failed', { error: message })
      return { found: false, matches: [], score: 0 }
    } finally {
      isValidating.value = false
    }
  }

  /**
   * Reset all validations.
   */
  const resetValidations = () => {
    validations.value = {
      ine_ocr: null,
      ine_lista_nominal: null,
      curp_renapo: null,
      face_match: null,
      liveness: null,
      ofac: null,
    }
    validationError.value = null
  }

  return {
    isValidating,
    validationError,
    validations,
    validateIne,
    validateCurp,
    validateRfc,
    checkOfac,
    resetValidations,
  }
}
