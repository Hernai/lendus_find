import { ref, type Ref } from 'vue'
import { api } from '@/services/api'
import { logger } from '@/utils/logger'

const kycLogger = logger.child('KYC:Biometrics')

/**
 * Face match result.
 */
export interface FaceMatchResult {
  score: number
  match: boolean
}

/**
 * Liveness check result.
 */
export interface LivenessResult {
  passed: boolean
  score?: number
}

/**
 * Composable for biometric KYC operations.
 *
 * Handles face matching and liveness detection.
 */
export function useKycBiometrics() {
  const isProcessing: Ref<boolean> = ref(false)
  const biometricError: Ref<string | null> = ref(null)
  const faceMatchResult: Ref<FaceMatchResult | null> = ref(null)
  const livenessResult: Ref<LivenessResult | null> = ref(null)
  const biometricToken: Ref<string | null> = ref(null)

  /**
   * Get biometric session token.
   */
  const getBiometricToken = async (applicationId: string): Promise<string | null> => {
    isProcessing.value = true
    biometricError.value = null

    try {
      kycLogger.debug('Getting biometric token')

      const response = await api.post<{ token: string }>('/kyc/biometric/token', {
        application_id: applicationId,
      })

      biometricToken.value = response.data.token
      kycLogger.debug('Biometric token obtained')
      return response.data.token
    } catch (error) {
      const message = error instanceof Error ? error.message : 'Error getting biometric token'
      biometricError.value = message
      kycLogger.error('Failed to get biometric token', { error: message })
      return null
    } finally {
      isProcessing.value = false
    }
  }

  /**
   * Validate face match between selfie and INE photo.
   */
  const validateFaceMatch = async (
    selfieBase64: string,
    inePhotoBase64: string
  ): Promise<FaceMatchResult> => {
    isProcessing.value = true
    biometricError.value = null

    try {
      kycLogger.debug('Starting face match validation')

      const response = await api.post<{ score?: number; match?: boolean }>('/kyc/face-match/validate', {
        selfie: selfieBase64,
        ine_photo: inePhotoBase64,
      })

      const result: FaceMatchResult = {
        score: response.data.score || 0,
        match: response.data.match === true,
      }

      faceMatchResult.value = result
      kycLogger.debug('Face match completed', { match: result.match, score: result.score })
      return result
    } catch (error) {
      const message = error instanceof Error ? error.message : 'Error validating face match'
      biometricError.value = message
      kycLogger.error('Face match validation failed', { error: message })
      return { score: 0, match: false }
    } finally {
      isProcessing.value = false
    }
  }

  /**
   * Validate liveness from video or image sequence.
   */
  const validateLiveness = async (
    livenessData: string | string[]
  ): Promise<LivenessResult> => {
    isProcessing.value = true
    biometricError.value = null

    try {
      kycLogger.debug('Starting liveness validation')

      const response = await api.post<{ passed?: boolean; score?: number }>('/kyc/liveness/validate', {
        data: livenessData,
      })

      const result: LivenessResult = {
        passed: response.data.passed === true,
        score: response.data.score,
      }

      livenessResult.value = result
      kycLogger.debug('Liveness validation completed', { passed: result.passed })
      return result
    } catch (error) {
      const message = error instanceof Error ? error.message : 'Error validating liveness'
      biometricError.value = message
      kycLogger.error('Liveness validation failed', { error: message })
      return { passed: false }
    } finally {
      isProcessing.value = false
    }
  }

  /**
   * Reset biometric state.
   */
  const resetBiometrics = () => {
    faceMatchResult.value = null
    livenessResult.value = null
    biometricToken.value = null
    biometricError.value = null
  }

  return {
    isProcessing,
    biometricError,
    faceMatchResult,
    livenessResult,
    biometricToken,
    getBiometricToken,
    validateFaceMatch,
    validateLiveness,
    resetBiometrics,
  }
}
