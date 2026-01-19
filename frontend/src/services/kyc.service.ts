/**
 * KYC Service - Handles KYC validation API calls.
 *
 * This service extracts the API interaction logic from the KYC store
 * to improve testability and reduce store complexity.
 */

import { api } from '@/services/api'
import { logger } from '@/utils/logger'

const log = logger.child('KycService')

// Types
export interface KycServicesResponse {
  data: {
    nubarium: {
      configured: boolean
      services: string[]
    }
  }
  birth_states: Record<string, string>
}

export interface IneOcrData {
  nombres: string
  apellido_paterno: string
  apellido_materno: string
  curp: string
  fecha_nacimiento: string
  sexo: string
  calle: string
  colonia: string
  cp?: string
  localidad?: string
  ciudad?: string
  municipio?: string
  estado?: string
  clave_elector: string
  vigencia: string
  ocr?: string
  cic?: string
  identificador_ciudadano?: string
  subtipo?: string
}

export interface IneValidationResponse {
  message: string
  ocr_data?: IneOcrData
  list_validation?: {
    valid: boolean
    code: string
    message: string
  }
  is_valid?: boolean
  validation_code?: string
}

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

export interface RfcValidationResponse {
  message: string
  valid: boolean
  data: {
    rfc: string
    mensaje?: string
    informacion_adicional?: string
    razon_social?: string
    tipo_persona: 'M' | 'F'
    tipo_persona_label: string
  }
}

export interface BiometricTokenResponse {
  message: string
  data: {
    token: string
    expires_in: number
    transaction_id: string
  }
}

export interface FaceMatchResponse {
  message: string
  data: {
    match: boolean
    score: number
    transaction_id: string
  }
}

export interface LivenessResponse {
  message: string
  data: {
    passed: boolean
    score: number
    confidence: number
    transaction_id: string
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

/**
 * Check available KYC services configuration.
 */
export async function checkServices(): Promise<{
  configured: boolean
  services: string[]
  birthStates: Record<string, string>
}> {
  try {
    const response = await api.get<KycServicesResponse>('/kyc/services')
    return {
      configured: response.data.data.nubarium?.configured || false,
      services: response.data.data.nubarium?.services || [],
      birthStates: response.data.birth_states || {}
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
    const response = await api.post<{ success: boolean; message: string }>('/kyc/test-connection')
    return {
      success: response.data.success,
      message: response.data.message
    }
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
    const response = await api.post<{ success: boolean; message: string }>('/kyc/refresh-token')
    return {
      success: response.data.success,
      message: response.data.message
    }
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
): Promise<IneValidationResponse> {
  const response = await api.post<IneValidationResponse>('/kyc/ine/validate', {
    front_image: frontImage,
    back_image: backImage,
    validate_list: true
  })
  return response.data
}

/**
 * Validate CURP with RENAPO.
 */
export async function validateCurp(curp: string): Promise<CurpValidationResponse> {
  const response = await api.post<CurpValidationResponse>('/kyc/curp/validate', { curp })
  return response.data
}

/**
 * Validate RFC with SAT.
 */
export async function validateRfc(
  rfc: string,
  applicantId?: string
): Promise<RfcValidationResponse> {
  const response = await api.post<RfcValidationResponse>('/kyc/rfc/validate', {
    rfc,
    applicant_id: applicantId
  })
  return response.data
}

/**
 * Get biometric token for face match/liveness.
 */
export async function getBiometricToken(): Promise<BiometricTokenResponse['data']> {
  const response = await api.post<BiometricTokenResponse>('/kyc/biometric/token')
  return response.data.data
}

/**
 * Perform face match between INE and selfie.
 */
export async function performFaceMatch(
  ineImage: string,
  selfieImage: string
): Promise<FaceMatchResponse['data']> {
  const response = await api.post<FaceMatchResponse>('/kyc/biometric/face-match', {
    ine_image: ineImage,
    selfie_image: selfieImage
  })
  return response.data.data
}

/**
 * Perform liveness check.
 */
export async function performLivenessCheck(
  selfieImage: string
): Promise<LivenessResponse['data']> {
  const response = await api.post<LivenessResponse>('/kyc/biometric/liveness', {
    selfie_image: selfieImage
  })
  return response.data.data
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
  const response = await api.post<ComplianceCheckResponse>('/kyc/compliance/check', data)
  return response.data.data
}

export interface VerifiedField {
  value: string
  method: string
  method_label: string
  verified_at: string
  metadata?: Record<string, unknown> | null
  is_locked?: boolean
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
  verified_fields: Record<string, VerifiedField>
  summary: {
    personal_data: Record<string, VerifiedField>
    contact: Record<string, VerifiedField>
    address: Record<string, VerifiedField>
    kyc: Record<string, VerifiedField>
  }
  kyc_verified: boolean
  kyc_verified_at: string | null
}

/**
 * Load applicant verifications from backend.
 */
export async function loadVerifications(applicantId: string): Promise<LoadVerificationsResponse> {
  const response = await api.get<{ data: LoadVerificationsResponse }>(`/applicants/${applicantId}/verifications`)
  return response.data.data
}

/**
 * Record verifications for an applicant.
 */
export async function recordVerifications(
  applicantId: string,
  verifications: Array<{
    field: string
    value: string
    method: string
    metadata?: Record<string, unknown>
  }>
): Promise<void> {
  await api.post(`/applicants/${applicantId}/verifications`, { verifications })
}

/**
 * Record a single verification.
 */
export async function recordSingleVerification(
  applicantId: string,
  field: string,
  value: string,
  method: string,
  metadata?: Record<string, unknown>
): Promise<void> {
  await api.post(`/applicants/${applicantId}/verifications`, {
    verifications: [{ field, value, method, metadata }]
  })
}
