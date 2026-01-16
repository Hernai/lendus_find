/**
 * KYC composables.
 *
 * These composables are extracted from the kyc.ts store
 * to improve maintainability and enable code reuse.
 */

export { useKycValidations, type KycValidationResults } from './useKycValidations'
export {
  useKycBiometrics,
  type FaceMatchResult,
  type LivenessResult,
} from './useKycBiometrics'
export {
  useKycDocuments,
  type DocumentCapture,
  type DocumentUploadResult,
} from './useKycDocuments'
