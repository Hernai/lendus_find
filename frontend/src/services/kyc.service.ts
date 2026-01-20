/**
 * KYC Service - Handles KYC validation API calls.
 *
 * This service extracts the API interaction logic from the KYC store
 * to improve testability and reduce store complexity.
 *
 * Uses V2 API endpoints for applicant KYC operations.
 */

import { v2 } from '@/services/v2'
import { logger } from '@/utils/logger'

const log = logger.child('KycService')

// Re-export types from V2 service for backwards compatibility
export type {
  KycServicesData,
  IneOcrData,
  IneValidationData,
  RfcValidationData,
  BiometricTokenData,
  FaceMatchData,
  LivenessData,
  VerifiedField,
  VerificationRecord,
  VerificationsData,
  RecordVerificationPayload,
} from '@/services/v2/kyc.applicant.service'

// Aliased exports for backwards compatibility
export type { KycServicesData as KycServicesResponse } from '@/services/v2/kyc.applicant.service'
export type { IneValidationData as IneValidationResponse } from '@/services/v2/kyc.applicant.service'
export type { RfcValidationData as RfcValidationResponse } from '@/services/v2/kyc.applicant.service'
export type { BiometricTokenData as BiometricTokenResponse } from '@/services/v2/kyc.applicant.service'
export type { FaceMatchData as FaceMatchResponse } from '@/services/v2/kyc.applicant.service'
export type { LivenessData as LivenessResponse } from '@/services/v2/kyc.applicant.service'
export type { VerificationsData as VerificationsResponse } from '@/services/v2/kyc.applicant.service'

// Legacy types that map to V2
export interface CurpValidationResponse {
  valid: boolean
  data?: {
    nombres?: string
    apellido_paterno?: string
    apellido_materno?: string
    fecha_nacimiento?: string
    sexo?: string
  }
}

export interface ComplianceCheckResponse {
  message: string
  data: {
    ofac: {
      found: boolean
      matches: Array<{
        name: string
        score: number
        list: string
      }>
      score: number
    }
    pld?: {
      found: boolean
      matches: unknown[]
    }
  }
}

export interface LoadVerificationsResponse {
  verifications: Array<{
    field: string
    field_label: string
    value: string
    method: string
    method_label: string
    is_verified: boolean
    is_locked: boolean
    status: string
    verified_at: string
    metadata?: Record<string, unknown> | null
    notes?: string | null
  }>
  verified_fields: Record<string, {
    value: string
    method: string
    method_label: string
    verified_at: string
    metadata?: Record<string, unknown> | null
    is_locked?: boolean
  }>
  summary: {
    personal_data: Record<string, unknown>
    contact: Record<string, unknown>
    address: Record<string, unknown>
    kyc: Record<string, unknown>
  }
  kyc_verified: boolean
  kyc_verified_at: string | null
}

/**
 * Check available KYC services configuration.
 */
export async function checkServices(): Promise<{
  configured: boolean
  services: string[]
  birthStates: Record<string, string>
}> {
  try {
    // V2 service now returns unwrapped data: { services: { nubarium: {...} }, birth_states: {...} }
    const data = await v2.applicant.kyc.getServices()
    return {
      configured: data.services?.nubarium?.configured || false,
      services: data.services?.nubarium?.services || [],
      birthStates: data.birth_states || {}
    }
  } catch (err) {
    log.error('Failed to check KYC services', { error: err })
    return {
      configured: false,
      services: [],
      birthStates: {}
    }
  }
}

/**
 * Test connection to KYC service.
 */
export async function testConnection(): Promise<{ success: boolean; message: string }> {
  try {
    return await v2.applicant.kyc.testConnection()
  } catch (err: unknown) {
    log.error('Failed to test KYC connection', { error: err })
    const errorResponse = err as { response?: { data?: { message?: string } } }
    return {
      success: false,
      message: errorResponse.response?.data?.message || 'Error al probar conexión'
    }
  }
}

/**
 * Refresh KYC token.
 */
export async function refreshToken(): Promise<{ success: boolean; message: string }> {
  try {
    return await v2.applicant.kyc.refreshToken()
  } catch (err: unknown) {
    log.error('Failed to refresh KYC token', { error: err })
    const errorResponse = err as { response?: { data?: { message?: string } } }
    return {
      success: false,
      message: errorResponse.response?.data?.message || 'Error al renovar token'
    }
  }
}

/**
 * Validate INE document with OCR and list validation.
 */
export async function validateIne(
  frontImage: string,
  backImage?: string | null
) {
  return await v2.applicant.kyc.validateIne(frontImage, backImage, true)
}

/**
 * Validate CURP with RENAPO.
 */
export async function validateCurp(curp: string): Promise<CurpValidationResponse> {
  // V2 service returns unwrapped data: { curp_data: {...}, valid: boolean }
  const data = await v2.applicant.kyc.validateCurp(curp)
  return {
    valid: data.valid,
    data: data.curp_data
  }
}

/**
 * Validate RFC with SAT.
 */
export async function validateRfc(
  rfc: string,
  _applicantId?: string // Kept for backwards compatibility but V2 doesn't need it
) {
  return await v2.applicant.kyc.validateRfc(rfc)
}

/**
 * Get biometric token for face match/liveness.
 */
export async function getBiometricToken(applicationId?: string) {
  return await v2.applicant.kyc.getBiometricToken(applicationId)
}

/**
 * Perform face match between INE and selfie.
 */
export async function performFaceMatch(
  ineImage: string,
  selfieImage: string
): Promise<{ match: boolean; score: number }> {
  const response = await v2.applicant.kyc.validateFaceMatch(selfieImage, ineImage)
  return {
    match: response.match,
    score: response.score
  }
}

/**
 * Perform liveness check.
 */
export async function performLivenessCheck(
  selfieImage: string
): Promise<{ passed: boolean; score: number }> {
  const response = await v2.applicant.kyc.validateLiveness(selfieImage)
  return {
    passed: response.passed,
    score: response.score
  }
}

/**
 * Perform compliance checks (OFAC, PLD).
 */
export async function performComplianceCheck(data: {
  first_name: string
  last_name_1: string
  last_name_2?: string
  curp?: string
  birth_date?: string
}): Promise<ComplianceCheckResponse['data']> {
  // Build full name for OFAC check
  const fullName = [data.first_name, data.last_name_1, data.last_name_2]
    .filter(Boolean)
    .join(' ')

  // Call both OFAC and PLD checks
  // V2 services now return unwrapped data directly
  const [ofacData, pldData] = await Promise.all([
    v2.applicant.kyc.checkOfac(fullName),
    v2.applicant.kyc.checkPldBlacklists(fullName, data.curp)
  ])

  return {
    ofac: {
      found: ofacData.found,
      matches: ofacData.matches,
      score: ofacData.matches.length > 0
        ? Math.max(...ofacData.matches.map(m => m.score))
        : 0
    },
    pld: {
      found: pldData.found,
      matches: pldData.matches
    }
  }
}

/**
 * Load applicant verifications from backend.
 * V2 doesn't need applicantId - it uses the authenticated user's applicant.
 */
export async function loadVerifications(_applicantId?: string): Promise<LoadVerificationsResponse> {
  // V2 getVerifications() returns the data directly (already unwrapped from response.data.data)
  return await v2.applicant.kyc.getVerifications()
}

/**
 * Record verifications for an applicant.
 * V2 doesn't need applicantId - it uses the authenticated user's applicant.
 */
export async function recordVerifications(
  _applicantId: string,
  verifications: Array<{
    field: string
    value: string
    method: string
    metadata?: Record<string, unknown>
  }>
): Promise<void> {
  await v2.applicant.kyc.recordVerifications(verifications)
}

/**
 * Record a single verification.
 * V2 doesn't need applicantId - it uses the authenticated user's applicant.
 */
export async function recordSingleVerification(
  _applicantId: string,
  field: string,
  value: string,
  method: string,
  metadata?: Record<string, unknown>
): Promise<void> {
  await v2.applicant.kyc.recordVerifications([{ field, value, method, metadata }])
}
