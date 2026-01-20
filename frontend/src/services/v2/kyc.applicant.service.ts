/**
 * V2 Applicant KYC Service
 *
 * Handles KYC (Know Your Customer) validation operations for applicants.
 * All endpoints are under /api/v2/applicant/kyc
 */

import { api } from '../api'

const BASE_PATH = '/v2/applicant/kyc'

// =====================================================
// Types
// =====================================================

/**
 * V2 API wrapper response format.
 * All V2 endpoints return: { success: true, data: T, message?: string }
 */
interface V2Response<T> {
  success: boolean
  data: T
  message?: string
}

/**
 * Response data for getServices endpoint.
 * Backend returns: { success: true, data: { services: {...}, birth_states: {...} } }
 */
export interface KycServicesData {
  services: {
    nubarium: {
      configured: boolean
      services: string[]
    }
  }
  birth_states: Record<string, string>
}

export type KycServicesResponse = V2Response<KycServicesData>

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

/**
 * INE validation data structure (inside data wrapper).
 */
export interface IneValidationData {
  ocr_data?: IneOcrData
  list_validation?: {
    valid: boolean
    code: string
    message: string
  }
  is_valid?: boolean
  validation_code?: string
}

export type IneValidationResponse = V2Response<IneValidationData>

/**
 * CURP validation data structure (inside data wrapper).
 * Backend returns: { success: true, data: { curp_data: {...}, valid: true }, message: "..." }
 */
export interface CurpValidationData {
  curp_data?: {
    nombres?: string
    apellido_paterno?: string
    apellido_materno?: string
    fecha_nacimiento?: string
    sexo?: string
  }
  valid: boolean
  curp?: string
}

export type CurpValidationResponse = V2Response<CurpValidationData>

/**
 * RFC validation data structure (inside data wrapper).
 * Backend returns: { success: true, data: { rfc_data: {...}, valid: true }, message: "..." }
 */
export interface RfcValidationData {
  rfc_data: {
    rfc: string
    mensaje?: string
    informacion_adicional?: string
    razon_social?: string
    tipo_persona: 'M' | 'F'
    tipo_persona_label: string
  }
  valid: boolean
}

export type RfcValidationResponse = V2Response<RfcValidationData>

/**
 * Biometric token data structure.
 * Backend returns: { success: true, data: { token, expires_in, transaction_id }, message: "..." }
 */
export interface BiometricTokenData {
  token: string
  expires_in: number
  transaction_id: string
}

export type BiometricTokenResponse = V2Response<BiometricTokenData>

/**
 * Face match data structure.
 * Backend returns: { success: true, data: { match, score, threshold, validation_code }, message: "..." }
 */
export interface FaceMatchData {
  match: boolean
  score: number
  threshold?: number
  validation_code?: string
}

export type FaceMatchResponse = V2Response<FaceMatchData>

/**
 * Liveness check data structure.
 * Backend returns: { success: true, data: { passed, score, validation_code }, message: "..." }
 */
export interface LivenessData {
  passed: boolean
  score: number
  validation_code?: string
}

export type LivenessResponse = V2Response<LivenessData>

/**
 * OFAC check data structure.
 * Backend returns: { success: true, data: { found, matches, count, ... }, message: "..." }
 */
export interface OfacCheckData {
  found: boolean
  matches: Array<{
    name: string
    score: number
    list: string
  }>
  count: number
  validation_code?: string
  checked_at: string
  warning?: string
}

export type OfacCheckResponse = V2Response<OfacCheckData>

/**
 * PLD blacklist check data structure.
 * Backend returns: { success: true, data: { found, matches, count, ... }, message: "..." }
 */
export interface PldCheckData {
  found: boolean
  matches: unknown[]
  count: number
  validation_code?: string
  checked_at: string
  warning?: string
}

export type PldCheckResponse = V2Response<PldCheckData>

/**
 * CEP validation data structure.
 * Backend returns: { success: true, data: { cep_data, valid }, message: "..." }
 */
export interface CepValidationData {
  cep_data: unknown
  valid: boolean
}

export type CepValidationResponse = V2Response<CepValidationData>

/**
 * IMSS history response type.
 * Backend returns: { success: true, data: {...}, message: "..." }
 */
export type ImssHistoryResponse = V2Response<unknown>

/**
 * Cédula validation data structure.
 * Backend returns: { success: true, data: { cedula_data, valid }, message: "..." }
 */
export interface CedulaValidationData {
  cedula_data: unknown
  valid: boolean
}

export type CedulaValidationResponse = V2Response<CedulaValidationData>

export interface VerifiedField {
  value: string
  method: string
  method_label: string
  verified_at: string
  metadata?: Record<string, unknown> | null
  is_locked?: boolean
}

export interface VerificationRecord {
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
}

export interface VerificationsData {
  verifications: VerificationRecord[]
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

export type VerificationsResponse = V2Response<VerificationsData>

export interface RecordVerificationPayload {
  field: string
  value: string
  method: string
  metadata?: Record<string, unknown>
  notes?: string
}

/**
 * Record verifications response data.
 */
export interface RecordVerificationsData {
  recorded: Array<{
    field: string
    verified: boolean
    locked: boolean
    method: string
  }>
  total: number
}

export type RecordVerificationsResponse = V2Response<RecordVerificationsData>

/**
 * Check fields verified response data.
 */
export interface CheckFieldsVerifiedData {
  fields: Record<string, boolean>
  all_verified: boolean
}

export type CheckFieldsVerifiedResponse = V2Response<CheckFieldsVerifiedData>

// =====================================================
// Service Functions
// =====================================================

/**
 * Get available KYC services for the current tenant.
 * Returns the unwrapped data from the V2 response.
 */
export async function getServices(): Promise<KycServicesData> {
  const response = await api.get<KycServicesResponse>(`${BASE_PATH}/services`)
  return response.data.data
}

/**
 * Test KYC service connection.
 */
export async function testConnection(): Promise<{ success: boolean; message: string }> {
  const response = await api.post<{ success: boolean; message: string }>(
    `${BASE_PATH}/test-connection`
  )
  return response.data
}

/**
 * Refresh KYC token.
 */
export async function refreshToken(): Promise<{ success: boolean; message: string }> {
  const response = await api.post<{ success: boolean; message: string }>(
    `${BASE_PATH}/refresh-token`
  )
  return response.data
}

/**
 * Validate CURP with RENAPO.
 * Returns the unwrapped data from the V2 response.
 */
export async function validateCurp(curp: string): Promise<CurpValidationData> {
  const response = await api.post<CurpValidationResponse>(`${BASE_PATH}/curp/validate`, { curp })
  return response.data.data
}

/**
 * Get CURP by personal data.
 * Returns the unwrapped data from the V2 response.
 */
export async function getCurp(data: {
  nombres: string
  apellido_paterno: string
  apellido_materno?: string
  fecha_nacimiento: string
  sexo: 'H' | 'M'
  entidad_nacimiento: string
}): Promise<CurpValidationData> {
  const response = await api.post<CurpValidationResponse>(`${BASE_PATH}/curp/get`, data)
  return response.data.data
}

/**
 * Validate RFC with SAT.
 * Returns the unwrapped data from the V2 response.
 */
export async function validateRfc(rfc: string): Promise<RfcValidationData> {
  const response = await api.post<RfcValidationResponse>(`${BASE_PATH}/rfc/validate`, { rfc })
  return response.data.data
}

/**
 * Validate INE document with OCR and list validation.
 * Returns the unwrapped data from the V2 response.
 */
export async function validateIne(
  frontImage: string,
  backImage?: string | null,
  validateList: boolean = true
): Promise<IneValidationData> {
  const response = await api.post<IneValidationResponse>(`${BASE_PATH}/ine/validate`, {
    front_image: frontImage,
    back_image: backImage,
    validate_list: validateList,
  })
  return response.data.data
}

/**
 * Get biometric token for face match/liveness.
 * Returns the unwrapped data from the V2 response.
 */
export async function getBiometricToken(
  applicationId?: string
): Promise<BiometricTokenData> {
  const response = await api.post<BiometricTokenResponse>(`${BASE_PATH}/biometric/token`, {
    application_id: applicationId,
  })
  return response.data.data
}

/**
 * Perform face match between INE and selfie.
 * Returns the unwrapped data from the V2 response.
 */
export async function validateFaceMatch(
  selfieImage: string,
  ineImage: string,
  threshold?: number
): Promise<FaceMatchData> {
  const response = await api.post<FaceMatchResponse>(`${BASE_PATH}/biometric/face-match`, {
    selfie_image: selfieImage,
    ine_image: ineImage,
    threshold,
  })
  return response.data.data
}

/**
 * Perform liveness check.
 * Returns the unwrapped data from the V2 response.
 */
export async function validateLiveness(faceImage: string): Promise<LivenessData> {
  const response = await api.post<LivenessResponse>(`${BASE_PATH}/biometric/liveness`, {
    face_image: faceImage,
  })
  return response.data.data
}

/**
 * Validate SPEI CEP (payment proof).
 * Returns the unwrapped data from the V2 response.
 */
export async function validateCep(data: {
  clave_rastreo: string
  fecha_operacion: string
  monto: number
  cuenta_beneficiario: string
  cuenta_ordenante?: string
}): Promise<CepValidationData> {
  const response = await api.post<CepValidationResponse>(`${BASE_PATH}/cep/validate`, data)
  return response.data.data
}

/**
 * Check OFAC & UN sanctions block lists.
 * Returns the unwrapped data from the V2 response.
 */
export async function checkOfac(name: string, similarity?: number): Promise<OfacCheckData> {
  const response = await api.post<OfacCheckResponse>(`${BASE_PATH}/ofac/check`, {
    name,
    similarity,
  })
  return response.data.data
}

/**
 * Check Mexican PLD (Anti-Money Laundering) blacklists.
 * Returns the unwrapped data from the V2 response.
 */
export async function checkPldBlacklists(
  name: string,
  curp?: string,
  similarity?: number
): Promise<PldCheckData> {
  const response = await api.post<PldCheckResponse>(`${BASE_PATH}/pld/check`, {
    name,
    curp,
    similarity,
  })
  return response.data.data
}

/**
 * Get IMSS employment history.
 * Returns the unwrapped data from the V2 response.
 */
export async function getImssHistory(curp: string, nss?: string): Promise<unknown> {
  const response = await api.post<ImssHistoryResponse>(`${BASE_PATH}/imss/history`, {
    curp,
    nss,
  })
  return response.data.data
}

/**
 * Validate professional license (Cédula Profesional).
 * Returns the unwrapped data from the V2 response.
 */
export async function validateCedula(cedula: string): Promise<CedulaValidationData> {
  const response = await api.post<CedulaValidationResponse>(`${BASE_PATH}/cedula/validate`, {
    cedula,
  })
  return response.data.data
}

/**
 * Record KYC verifications for the current applicant.
 * Returns the unwrapped data from the V2 response.
 */
export async function recordVerifications(
  verifications: RecordVerificationPayload[]
): Promise<RecordVerificationsData> {
  const response = await api.post<RecordVerificationsResponse>(`${BASE_PATH}/verifications`, {
    verifications,
  })
  return response.data.data
}

/**
 * Get all verifications for the current applicant.
 */
export async function getVerifications(): Promise<VerificationsData> {
  const response = await api.get<VerificationsResponse>(`${BASE_PATH}/verifications`)
  return response.data.data
}

/**
 * Check if specific fields are verified for the current applicant.
 * Returns the unwrapped data from the V2 response.
 */
export async function checkFieldsVerified(fields: string[]): Promise<CheckFieldsVerifiedData> {
  const response = await api.post<CheckFieldsVerifiedResponse>(`${BASE_PATH}/verifications/check`, {
    fields,
  })
  return response.data.data
}

// =====================================================
// Default Export
// =====================================================

export default {
  getServices,
  testConnection,
  refreshToken,
  validateCurp,
  getCurp,
  validateRfc,
  validateIne,
  getBiometricToken,
  validateFaceMatch,
  validateLiveness,
  validateCep,
  checkOfac,
  checkPldBlacklists,
  getImssHistory,
  validateCedula,
  recordVerifications,
  getVerifications,
  checkFieldsVerified,
}
