/**
 * V2 Staff Application Service
 *
 * Handles application management operations for staff users.
 * All endpoints are under /api/v2/staff/applications
 */

import { api } from '../api'
import type {
  V2ApiResponse,
  V2Application,
  V2ApplicationDetail,
  V2ApplicationFilters,
  V2AssignApplicationPayload,
  V2ChangeStatusPayload,
  V2ApprovePayload,
  V2RejectPayload,
  V2CounterOfferCreatePayload,
  V2RiskAssessmentPayload,
  V2ApplicationStatistics,
  V2ApplicationNote,
  V2ApplicationNotePayload,
} from '@/types/v2'

const BASE_PATH = '/v2/staff/applications'

// =====================================================
// Read Operations
// =====================================================

/**
 * Response structure for paginated application list.
 */
export interface V2ApplicationListResponse {
  applications: V2Application[]
  meta: {
    current_page: number
    from: number | null
    last_page: number
    per_page: number
    to: number | null
    total: number
  }
}

/**
 * List all applications with filters.
 */
export async function list(filters?: V2ApplicationFilters): Promise<V2ApiResponse<V2ApplicationListResponse>> {
  const response = await api.get<V2ApiResponse<V2ApplicationListResponse>>(BASE_PATH, { params: filters })
  return response.data
}

/**
 * Get application statistics.
 */
export async function getStatistics(): Promise<V2ApiResponse<V2ApplicationStatistics>> {
  const response = await api.get<V2ApiResponse<V2ApplicationStatistics>>(`${BASE_PATH}/statistics`)
  return response.data
}

/**
 * Board column item (minimal application data for Kanban).
 */
export interface V2BoardItem {
  id: string
  folio: string
  status: string
  applicant_type: string
  applicant_name: string | null
  product: {
    id: string
    name: string
  } | null
  requested_amount: number
  assigned_to: {
    id: string
    name: string
  } | null
  created_at: string
  submitted_at: string | null
}

/**
 * Board column data.
 */
export interface V2BoardColumn {
  status: string
  status_label: string
  count: number
  items: V2BoardItem[]
  has_more: boolean
}

/**
 * Board response structure.
 */
export interface V2BoardData {
  columns: V2BoardColumn[]
  totals: {
    all: number
    by_status: Record<string, number>
  }
}

/**
 * Get Kanban board data with applications grouped by status.
 * More efficient than fetching all applications for dashboard views.
 */
export async function getBoard(params?: {
  columns?: string[]
  limit_per_column?: number
  assigned_to?: string
  sort_by?: 'created_at' | 'submitted_at' | 'requested_amount'
  sort_dir?: 'asc' | 'desc'
}): Promise<V2ApiResponse<V2BoardData>> {
  const response = await api.get<V2ApiResponse<V2BoardData>>(`${BASE_PATH}/board`, { params })
  return response.data
}

/**
 * Get unassigned applications.
 */
export async function getUnassigned(): Promise<V2ApiResponse<{ applications: V2Application[] }>> {
  const response = await api.get<V2ApiResponse<{ applications: V2Application[] }>>(`${BASE_PATH}/unassigned`)
  return response.data
}

/**
 * Get staff member's assigned queue.
 */
export async function getMyQueue(params?: {
  status?: string
}): Promise<V2ApiResponse<{ applications: V2Application[] }>> {
  const response = await api.get<V2ApiResponse<{ applications: V2Application[] }>>(`${BASE_PATH}/my-queue`, { params })
  return response.data
}

/**
 * Get application details by ID.
 * Returns the new structured format with loan, applicant, verification, workflow sections.
 */
export async function get(id: string): Promise<V2ApiResponse<V2ApplicationDetail>> {
  const response = await api.get<V2ApiResponse<V2ApplicationDetail>>(`${BASE_PATH}/${id}`)
  return response.data
}

// getHistory eliminado: la lectura del historial unificado vive en el feed
// `v2.staff.activity.getActivity(applicationId, { kind: 'event' })`.

// =====================================================
// Action Operations (with permissions)
// =====================================================

/**
 * Assign application to a staff member.
 * Requires: canAssignApplications permission.
 */
export async function assign(id: string, payload: V2AssignApplicationPayload): Promise<V2ApiResponse<{ application: V2Application }>> {
  const response = await api.post<V2ApiResponse<{ application: V2Application }>>(`${BASE_PATH}/${id}/assign`, payload)
  return response.data
}

/**
 * Change application status.
 * Requires: canChangeApplicationStatus permission.
 */
export async function changeStatus(id: string, payload: V2ChangeStatusPayload): Promise<V2ApiResponse<{ application: V2Application }>> {
  const response = await api.post<V2ApiResponse<{ application: V2Application }>>(`${BASE_PATH}/${id}/status`, payload)
  return response.data
}

/**
 * Approve application.
 * Requires: canApproveRejectApplications permission.
 * Rate limited: 30 requests per minute.
 */
export async function approve(id: string, payload: V2ApprovePayload): Promise<V2ApiResponse<{ application: V2Application }>> {
  const response = await api.post<V2ApiResponse<{ application: V2Application }>>(`${BASE_PATH}/${id}/approve`, payload)
  return response.data
}

/**
 * Reject application.
 * Requires: canApproveRejectApplications permission.
 * Rate limited: 30 requests per minute.
 */
export async function reject(id: string, payload: V2RejectPayload): Promise<V2ApiResponse<{ application: V2Application }>> {
  const response = await api.post<V2ApiResponse<{ application: V2Application }>>(`${BASE_PATH}/${id}/reject`, payload)
  return response.data
}

/**
 * Create counter-offer.
 * Requires: canApproveRejectApplications permission.
 * Rate limited: 30 requests per minute.
 */
export async function createCounterOffer(
  id: string,
  payload: V2CounterOfferCreatePayload
): Promise<V2ApiResponse<{ application: V2Application }>> {
  const response = await api.post<V2ApiResponse<{ application: V2Application }>>(`${BASE_PATH}/${id}/counter-offer`, payload)
  return response.data
}

/** Resultado de re-verificar el INE desde el panel de staff. */
export interface V2IneReverifyResult {
  fields: { nombres: string; apellido_paterno: string; apellido_materno: string; curp: string }
  ine_valid: boolean | null
  curp_valid: boolean | null
  renapo: { nombres: string; apellido_paterno: string; apellido_materno: string } | null
  diffs: Record<string, { ocr: string; renapo: string }>
}

/**
 * Re-ejecutar la verificación de INE de un solicitante usando las imágenes de
 * INE que ya subió (por si Nubarium estaba caído durante el onboarding).
 * Requires: canReviewDocuments permission. Rate limited: 20 requests per minute.
 */
export async function reverifyIne(id: string): Promise<V2ApiResponse<V2IneReverifyResult>> {
  const response = await api.post<V2ApiResponse<V2IneReverifyResult>>(`${BASE_PATH}/${id}/kyc/verify-ine`)
  return response.data
}

/**
 * Update verification checklist.
 * Requires: canVerifyReferences permission.
 *
 * @param checks - Object with check field names as keys and boolean values
 * @example updateVerification(id, { identity_verified: true, address_verified: false })
 */
export async function updateVerification(
  id: string,
  checks: Record<string, boolean>
): Promise<V2ApiResponse<{ verification_checklist: Record<string, unknown> }>> {
  const response = await api.patch<V2ApiResponse<{ verification_checklist: Record<string, unknown> }>>(`${BASE_PATH}/${id}/verification`, { checks })
  return response.data
}

/**
 * Set risk assessment.
 * Requires: canChangeApplicationStatus permission.
 */
export async function setRiskAssessment(
  id: string,
  payload: V2RiskAssessmentPayload
): Promise<V2ApiResponse<{ risk_level: string; risk_data: Record<string, unknown> }>> {
  const response = await api.post<V2ApiResponse<{ risk_level: string; risk_data: Record<string, unknown> }>>(`${BASE_PATH}/${id}/risk-assessment`, payload)
  return response.data
}

// =====================================================
// Notes Operations
// =====================================================

/**
 * Get application notes.
 */
export async function getNotes(id: string): Promise<V2ApiResponse<{ notes: V2ApplicationNote[] }>> {
  const response = await api.get<V2ApiResponse<{ notes: V2ApplicationNote[] }>>(`${BASE_PATH}/${id}/notes`)
  return response.data
}

/**
 * Add note to application.
 */
export async function addNote(id: string, payload: V2ApplicationNotePayload): Promise<V2ApiResponse<V2ApplicationNote>> {
  const response = await api.post<V2ApiResponse<V2ApplicationNote>>(`${BASE_PATH}/${id}/notes`, payload)
  return response.data
}

// =====================================================
// Document Operations (within application context)
// =====================================================

/**
 * Get download URL for application document.
 */
export async function getDocumentUrl(
  applicationId: string,
  documentId: string
): Promise<V2ApiResponse<{ url: string; mime_type: string; original_name: string }>> {
  const response = await api.get<V2ApiResponse<{ url: string; mime_type: string; original_name: string }>>(
    `${BASE_PATH}/${applicationId}/documents/${documentId}/url`
  )
  return response.data
}

/**
 * Download document as blob.
 */
export async function downloadDocument(
  applicationId: string,
  documentId: string
): Promise<Blob> {
  const response = await api.get(`${BASE_PATH}/${applicationId}/documents/${documentId}/download`, {
    responseType: 'blob',
  })
  return response.data as Blob
}

/**
 * Approve application document.
 * Requires: canReviewDocuments permission.
 */
export async function approveDocument(
  applicationId: string,
  documentId: string
): Promise<V2ApiResponse<void>> {
  const response = await api.put<V2ApiResponse<void>>(
    `${BASE_PATH}/${applicationId}/documents/${documentId}/approve`
  )
  return response.data
}

/**
 * Reject application document.
 * Requires: canReviewDocuments permission.
 */
export async function rejectDocument(
  applicationId: string,
  documentId: string,
  payload: { reason: string; comment?: string }
): Promise<V2ApiResponse<void>> {
  const response = await api.put<V2ApiResponse<void>>(
    `${BASE_PATH}/${applicationId}/documents/${documentId}/reject`,
    payload
  )
  return response.data
}

/**
 * Unapprove document (set back to pending).
 * Requires: canReviewDocuments permission.
 */
export async function unapproveDocument(
  applicationId: string,
  documentId: string
): Promise<V2ApiResponse<void>> {
  const response = await api.put<V2ApiResponse<void>>(
    `${BASE_PATH}/${applicationId}/documents/${documentId}/unapprove`
  )
  return response.data
}

// =====================================================
// Reference Operations (within application context)
// =====================================================

/**
 * Verify application reference.
 * Requires: canVerifyReferences permission.
 */
export async function verifyReference(
  applicationId: string,
  referenceId: string,
  payload: { result: 'VERIFIED' | 'NOT_VERIFIED' | 'NO_ANSWER'; notes?: string }
): Promise<V2ApiResponse<void>> {
  const response = await api.put<V2ApiResponse<void>>(
    `${BASE_PATH}/${applicationId}/references/${referenceId}/verify`,
    payload
  )
  return response.data
}

// =====================================================
// Bank Account Operations (within application context)
// =====================================================

/**
 * Verify bank account.
 * Requires: canVerifyReferences permission.
 */
export async function verifyBankAccount(
  applicationId: string,
  bankAccountId: string
): Promise<V2ApiResponse<void>> {
  const response = await api.put<V2ApiResponse<void>>(
    `${BASE_PATH}/${applicationId}/bank-accounts/${bankAccountId}/verify`
  )
  return response.data
}

/**
 * Unverify bank account.
 * Requires: canVerifyReferences permission.
 */
export async function unverifyBankAccount(
  applicationId: string,
  bankAccountId: string
): Promise<V2ApiResponse<void>> {
  const response = await api.put<V2ApiResponse<void>>(
    `${BASE_PATH}/${applicationId}/bank-accounts/${bankAccountId}/unverify`
  )
  return response.data
}

/**
 * Edita el titular de una cuenta bancaria (corregir nombre/RFC).
 * Requires: canConfigureTenant (SUPER_ADMIN).
 */
export async function updateBankAccount(
  applicationId: string,
  bankAccountId: string,
  payload: { holder_name: string; holder_rfc?: string | null }
): Promise<V2ApiResponse<{ holder_name: string; holder_rfc: string | null }>> {
  const response = await api.put<V2ApiResponse<{ holder_name: string; holder_rfc: string | null }>>(
    `${BASE_PATH}/${applicationId}/bank-accounts/${bankAccountId}`,
    payload
  )
  return response.data
}

/**
 * Edita el teléfono del solicitante (solo SUPER_ADMIN). Uso de PRUEBAS:
 * libera el número original para volver a registrarlo desde cero.
 */
export async function updateApplicantPhone(
  applicationId: string,
  phone: string
): Promise<V2ApiResponse<{ phone: string }>> {
  const response = await api.put<V2ApiResponse<{ phone: string }>>(
    `${BASE_PATH}/${applicationId}/applicant-phone`,
    { phone }
  )
  return response.data
}

/** Validación asíncrona de Nubarium (CLABE) iniciada. */
export interface NubariumValidationStart {
  validation_id: string
  validation_code: string | null
  status: string
}

/** Estado/resultado de una validación asíncrona de Nubarium. */
export interface NubariumValidationData {
  id: string
  type: string
  status: 'pending' | 'completed' | 'failed'
  validation_code: string | null
  result: Record<string, unknown> | null
  error: string | null
  received_at: string | null
  created_at: string | null
}

/**
 * Inicia la validación de la CLABE de una cuenta bancaria con Nubarium (async).
 * Requires: canVerifyReferences.
 */
export async function validateBankAccountClabe(
  applicationId: string,
  bankAccountId: string
): Promise<V2ApiResponse<NubariumValidationStart>> {
  const response = await api.post<V2ApiResponse<NubariumValidationStart>>(
    `${BASE_PATH}/${applicationId}/bank-accounts/${bankAccountId}/validate-clabe`
  )
  return response.data
}

/** Consulta el estado/resultado de una validación asíncrona de Nubarium. */
export async function getNubariumValidation(
  id: string
): Promise<V2ApiResponse<NubariumValidationData>> {
  const response = await api.get<V2ApiResponse<NubariumValidationData>>(
    `/v2/staff/nubarium-validations/${id}`
  )
  return response.data
}

/** Una evaluación de riesgo de contacto (phone/email risk). */
export interface RiskContactItem {
  id: string
  type: string
  provider: string
  identifier: string | null
  status: 'completed' | 'failed'
  score: number | null
  level: string | null
  recommendation: string | null
  result: Record<string, unknown> | null
  error: string | null
  created_at: string | null
}

/** Verificación de campo (KYC/biometría) tal como la devuelve el detalle. */
export interface RiskFieldVerification {
  status?: string
  verified?: boolean
  method?: string | null
  method_label?: string | null
  verified_at?: string | null
  metadata?: Record<string, unknown> | null
}

/** Vista consolidada de riesgos/validaciones del solicitante. */
export interface ApplicationRisks {
  kyc_status: string | null
  kyc_verified_at: string | null
  contact_risk: {
    phone: RiskContactItem | null
    email: RiskContactItem | null
    phone_enabled?: boolean
    email_enabled?: boolean
    has_phone?: boolean
    has_email?: boolean
  }
  identity: Record<string, RiskFieldVerification>
  biometrics: Record<string, RiskFieldVerification>
  bank: Array<{
    bank_name: string
    clabe: string
    is_verified: boolean
    clabe_validation: Record<string, unknown> | null
  }>
  pld: unknown
  credit_bureau: unknown
  circulo: unknown
}

/** Consolidado de riesgos/validaciones Nubarium del solicitante (tab "Riesgos"). */
export async function getRisks(
  applicationId: string
): Promise<V2ApiResponse<ApplicationRisks>> {
  const response = await api.get<V2ApiResponse<ApplicationRisks>>(
    `${BASE_PATH}/${applicationId}/risks`
  )
  return response.data
}

/**
 * Ejecuta (o reintenta) el riesgo de contacto a demanda desde el panel.
 * Requiere que el servicio (phone_risk/email_risk) esté activo en el tenant.
 */
export async function runContactRisk(
  applicationId: string,
  type: 'phone' | 'email'
): Promise<V2ApiResponse<unknown>> {
  const response = await api.post<V2ApiResponse<unknown>>(
    `${BASE_PATH}/${applicationId}/risks/run`,
    { type }
  )
  return response.data
}

// =====================================================
// Data Verification Operations
// =====================================================

/**
 * Verify application data field.
 * Requires: canVerifyReferences permission.
 */
export async function verifyData(
  applicationId: string,
  payload: {
    field: string
    action: 'verify' | 'reject' | 'unverify'
    method?: string
    rejection_reason?: string
    notes?: string
  }
): Promise<V2ApiResponse<void>> {
  const response = await api.put<V2ApiResponse<void>>(
    `${BASE_PATH}/${applicationId}/verify-data`,
    payload
  )
  return response.data
}

// API Logs por aplicacion: eliminado. Ahora se consume via el feed unificado
// `v2.staff.activity.getActivity(applicationId, { kind: 'api' })`.

export default {
  // List operations
  list,
  getBoard,
  getStatistics,
  getUnassigned,
  getMyQueue,
  get,
  // Status operations
  assign,
  changeStatus,
  approve,
  reject,
  createCounterOffer,
  reverifyIne,
  updateVerification,
  setRiskAssessment,
  // Notes
  getNotes,
  addNote,
  // Documents
  getDocumentUrl,
  downloadDocument,
  approveDocument,
  rejectDocument,
  unapproveDocument,
  // References
  verifyReference,
  // Bank accounts
  verifyBankAccount,
  unverifyBankAccount,
  updateBankAccount,
  updateApplicantPhone,
  validateBankAccountClabe,
  getNubariumValidation,
  getRisks,
  runContactRisk,
  // Data verification
  verifyData,
}
