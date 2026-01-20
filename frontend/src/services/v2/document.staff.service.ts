/**
 * V2 Staff Document Service
 *
 * Handles document review and management operations for staff users.
 * All endpoints are under /api/v2/staff/documents
 */

import { api } from '../api'
import type {
  V2ApiResponse,
  V2Document,
  V2DocumentType,
} from '@/types/v2'

const BASE_PATH = '/v2/staff/documents'

// =====================================================
// Read Operations
// =====================================================

/**
 * Get available document types.
 */
export async function getTypes(): Promise<V2ApiResponse<{ types: V2DocumentType[]; categories: Record<string, string> }>> {
  const response = await api.get<V2ApiResponse<{ types: V2DocumentType[]; categories: Record<string, string> }>>(`${BASE_PATH}/types`)
  return response.data
}

/**
 * Get pending documents for review.
 */
export async function getPending(params?: {
  category?: string
  page?: number
  per_page?: number
}): Promise<V2ApiResponse<{ documents: V2Document[] }>> {
  const response = await api.get<V2ApiResponse<{ documents: V2Document[] }>>(`${BASE_PATH}/pending`, { params })
  return response.data
}

/**
 * Get documents expiring soon.
 */
export async function getExpiring(params?: {
  days?: number
  page?: number
  per_page?: number
}): Promise<V2ApiResponse<{ documents: V2Document[] }>> {
  const response = await api.get<V2ApiResponse<{ documents: V2Document[] }>>(`${BASE_PATH}/expiring`, { params })
  return response.data
}

/**
 * Get document details by ID.
 */
export async function get(id: string): Promise<V2ApiResponse<{ document: V2Document }>> {
  const response = await api.get<V2ApiResponse<{ document: V2Document }>>(`${BASE_PATH}/${id}`)
  return response.data
}

/**
 * Download document (returns signed URL).
 */
export async function download(id: string): Promise<V2ApiResponse<{ url: string; expires_at: string; filename: string }>> {
  const response = await api.get<V2ApiResponse<{ url: string; expires_at: string; filename: string }>>(
    `${BASE_PATH}/${id}/download`
  )
  return response.data
}

// =====================================================
// Action Operations (with permissions)
// =====================================================

/**
 * Approve document.
 * Requires: canReviewDocuments permission.
 * Rate limited: 60 requests per minute.
 */
export async function approve(id: string, notes?: string): Promise<V2ApiResponse<{ document: V2Document }>> {
  const response = await api.post<V2ApiResponse<{ document: V2Document }>>(
    `${BASE_PATH}/${id}/approve`,
    notes ? { notes } : undefined
  )
  return response.data
}

/**
 * Reject document.
 * Requires: canReviewDocuments permission.
 * Rate limited: 60 requests per minute.
 */
export async function reject(id: string, reason: string): Promise<V2ApiResponse<{ document: V2Document }>> {
  const response = await api.post<V2ApiResponse<{ document: V2Document }>>(`${BASE_PATH}/${id}/reject`, { reason })
  return response.data
}

/**
 * Set OCR data for document.
 * Requires: canReviewDocuments permission.
 */
export async function setOcrData(
  id: string,
  ocrData: Record<string, unknown>,
  confidence: number
): Promise<V2ApiResponse<{ document: { id: string; ocr_processed: boolean; ocr_data: Record<string, unknown>; ocr_confidence: number | null } }>> {
  const response = await api.post<V2ApiResponse<{ document: { id: string; ocr_processed: boolean; ocr_data: Record<string, unknown>; ocr_confidence: number | null } }>>(`${BASE_PATH}/${id}/ocr`, {
    ocr_data: ocrData,
    confidence,
  })
  return response.data
}

// =====================================================
// Person Document Operations
// =====================================================

/**
 * Get all documents for a person.
 */
export async function getPersonDocuments(
  personId: string,
  params?: {
    type?: string
    category?: string
    status?: string
  }
): Promise<V2ApiResponse<{ documents: V2Document[] }>> {
  const response = await api.get<V2ApiResponse<{ documents: V2Document[] }>>(
    `/v2/staff/persons/${personId}/documents`,
    { params }
  )
  return response.data
}

/**
 * Check required documents for a person.
 * Note: Backend expects required_types as array format (required_types[]=value1&required_types[]=value2)
 */
export async function checkPersonDocuments(
  personId: string,
  requiredTypes: string[]
): Promise<V2ApiResponse<{
  is_complete: boolean
  missing_types: string[]
  rejected_count: number
  rejected_documents: Array<{
    id: string
    type: string
    type_label: string
    rejection_reason: string | null
  }>
}>> {
  const response = await api.get<V2ApiResponse<{
    is_complete: boolean
    missing_types: string[]
    rejected_count: number
    rejected_documents: Array<{
      id: string
      type: string
      type_label: string
      rejection_reason: string | null
    }>
  }>>(`/v2/staff/persons/${personId}/documents/check`, {
    params: { required_types: requiredTypes },
  })
  return response.data
}

export default {
  getTypes,
  getPending,
  getExpiring,
  get,
  download,
  approve,
  reject,
  setOcrData,
  getPersonDocuments,
  checkPersonDocuments,
}
