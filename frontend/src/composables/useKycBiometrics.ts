import { ref } from 'vue'
import { api } from '@/services/api'
import { logger } from '@/utils/logger'

const kycLogger = logger.child('KYC:Biometrics')

export interface BiometricTokenData {
  token: string
  expires_in: number
  transaction_id: string
}

export interface FaceMatchResult {
  match: boolean
  score: number
}

export interface LivenessResult {
  passed: boolean
  score?: number
}

export interface UseKycBiometricsReturn {
  /** Whether biometric validation is in progress */
  isValidating: import('vue').Ref<boolean>
  /** Error message from biometric operations */
  error: import('vue').Ref<string | null>
  /** Face match result */
  faceMatchResult: import('vue').Ref<FaceMatchResult | null>
  /** Liveness result */
  livenessResult: import('vue').Ref<LivenessResult | null>
  /** Get biometric token for SDK integration */
  getBiometricToken: (applicationId?: string) => Promise<BiometricTokenData | null>
  /** Validate face match between selfie and INE */
  validateFaceMatch: (selfieImage: string, ineImage: string) => Promise<boolean>
  /** Validate liveness from selfie */
  validateLiveness: (faceImage: string) => Promise<boolean>
  /** Set face match result manually (e.g., from SDK callback) */
  setFaceMatchResult: (score: number, match: boolean) => void
  /** Set liveness result manually (e.g., from SDK callback) */
  setLivenessResult: (passed: boolean, score?: number) => void
  /** Reset biometric state */
  reset: () => void
}

/**
 * Composable for KYC biometric validations (face match and liveness).
 * Handles face comparison between selfie and ID photo, and liveness detection.
 */
export function useKycBiometrics(): UseKycBiometricsReturn {
  const isValidating = ref(false)
  const error = ref<string | null>(null)
  const faceMatchResult = ref<FaceMatchResult | null>(null)
  const livenessResult = ref<LivenessResult | null>(null)

  /**
   * Get biometric token for SDK integration.
   * Used when integrating with Nubarium's biometric SDK.
   */
  const getBiometricToken = async (applicationId?: string): Promise<BiometricTokenData | null> => {
    isValidating.value = true
    error.value = null

    try {
      const response = await api.post<{
        message: string
        data: BiometricTokenData
      }>('/kyc/biometric/token', {
        application_id: applicationId
      })

      return response.data.data
    } catch (err: unknown) {
      kycLogger.error('Failed to get biometric token', err)
      const errorResponse = err as { response?: { data?: { message?: string } } }
      error.value = errorResponse.response?.data?.message || 'Error al obtener token biométrico'
      return null
    } finally {
      isValidating.value = false
    }
  }

  /**
   * Validate face match between selfie and INE photo.
   * Compares the captured selfie with the face on the INE to verify identity.
   */
  const validateFaceMatch = async (selfieImage: string, ineImage: string): Promise<boolean> => {
    kycLogger.debug('validateFaceMatch called')

    if (!selfieImage) {
      error.value = 'Se requiere la imagen de selfie'
      return false
    }

    if (!ineImage) {
      error.value = 'Se requiere la imagen frontal del INE'
      return false
    }

    isValidating.value = true
    error.value = null

    try {
      kycLogger.debug('Calling /kyc/biometric/face-match API...')
      const response = await api.post<{
        message: string
        match: boolean
        score: number
        threshold: number
        validation_code?: string
      }>('/kyc/biometric/face-match', {
        selfie_image: selfieImage,
        ine_image: ineImage,
        threshold: 80 // 80% similarity threshold
      })

      kycLogger.debug('Face match response:', response.data)

      const match = response.data.match
      const score = response.data.score

      faceMatchResult.value = { score, match }

      return match
    } catch (err: unknown) {
      kycLogger.error('Failed to validate face match', err)
      const errorResponse = err as { response?: { data?: { message?: string } } }
      error.value = errorResponse.response?.data?.message || 'Error en comparación facial'
      faceMatchResult.value = { score: 0, match: false }
      return false
    } finally {
      isValidating.value = false
    }
  }

  /**
   * Validate liveness detection from selfie image.
   * Verifies that the captured face belongs to a real, present person (anti-spoofing).
   */
  const validateLiveness = async (faceImage: string): Promise<boolean> => {
    kycLogger.debug('validateLiveness called')

    if (!faceImage) {
      error.value = 'Se requiere la imagen de selfie'
      return false
    }

    isValidating.value = true
    error.value = null

    try {
      kycLogger.debug('Calling /kyc/biometric/liveness API...')
      const response = await api.post<{
        message: string
        passed: boolean
        score: number
        validation_code?: string
      }>('/kyc/biometric/liveness', {
        face_image: faceImage
      })

      kycLogger.debug('Liveness response:', response.data)

      const passed = response.data.passed
      const score = response.data.score

      livenessResult.value = { passed, score }

      return passed
    } catch (err: unknown) {
      kycLogger.error('Failed to validate liveness', err)
      const errorResponse = err as { response?: { data?: { message?: string } } }
      error.value = errorResponse.response?.data?.message || 'Error en prueba de vida'
      livenessResult.value = { passed: false, score: 0 }
      return false
    } finally {
      isValidating.value = false
    }
  }

  /**
   * Set face match result manually (e.g., from SDK callback).
   */
  const setFaceMatchResult = (score: number, match: boolean): void => {
    faceMatchResult.value = { score, match }
  }

  /**
   * Set liveness result manually (e.g., from SDK callback).
   */
  const setLivenessResult = (passed: boolean, score?: number): void => {
    livenessResult.value = { passed, score }
  }

  /**
   * Reset biometric state.
   */
  const reset = (): void => {
    isValidating.value = false
    error.value = null
    faceMatchResult.value = null
    livenessResult.value = null
  }

  return {
    isValidating,
    error,
    faceMatchResult,
    livenessResult,
    getBiometricToken,
    validateFaceMatch,
    validateLiveness,
    setFaceMatchResult,
    setLivenessResult,
    reset
  }
}
