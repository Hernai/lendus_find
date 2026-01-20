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
  V2StatusHistoryEntry,
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

/**
 * Get application status history.
 */
export async function getHistory(id: string): Promise<V2ApiResponse<{ history: V2StatusHistoryEntry[] }>> {
  const response = await api.get<V2ApiResponse<{ history: V2StatusHistoryEntry[] }>>(`${BASE_PATH}/${id}/history`)
  return response.data
}

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

// =====================================================
// API Logs (within application context)
// =====================================================

export interface V2ApiLogEntry {
  id: string
  provider: string
  service: string
  endpoint: string
  method: string
  request_method: string
  request_url: string
  response_status: number
  success: boolean
  error_message?: string
  duration_ms: number
  request_payload?: Record<string, unknown>
  response_payload?: Record<string, unknown>
  response_body?: Record<string, unknown>
  created_at: string
}

/**
 * Get API logs for application.
 */
export async function getApiLogs(applicationId: string): Promise<V2ApiResponse<{ logs: V2ApiLogEntry[] }>> {
  const response = await api.get<V2ApiResponse<{ logs: V2ApiLogEntry[] }>>(
    `${BASE_PATH}/${applicationId}/api-logs`
  )
  return response.data
}

export default {
  // List operations
  list,
  getBoard,
  getStatistics,
  getUnassigned,
  getMyQueue,
  get,
  getHistory,
  // Status operations
  assign,
  changeStatus,
  approve,
  reject,
  createCounterOffer,
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
  // Data verification
  verifyData,
  // API logs
  getApiLogs,
}
