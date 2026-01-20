/**
 * V2 Applicant Document Service
 *
 * Handles document upload and management for applicants.
 * All endpoints are under /api/v2/applicant/documents
 */

import { api } from '../api'
import type {
  V2ApiResponse,
  V2Document,
} from '@/types/v2'

const BASE_PATH = '/v2/applicant/documents'

/**
 * List all documents for the current applicant.
 */
export async function list(params?: {
  type?: string
  category?: string
  status?: string
  page?: number
  per_page?: number
}): Promise<V2ApiResponse<{ documents: V2Document[] }>> {
  const response = await api.get<V2ApiResponse<{ documents: V2Document[] }>>(BASE_PATH, { params })
  return response.data
}

/**
 * Upload a new document.
 *
 * @param file - The file to upload
 * @param type - Document type (e.g., 'INE_FRONT', 'INE_BACK', 'PROOF_OF_ADDRESS')
 * @param options.identification_id - Optional: ID of PersonIdentification to attach document to
 * @param options.metadata - Optional: Additional metadata for the document
 */
export async function upload(
  file: File,
  type: string,
  options?: {
    identification_id?: string
    metadata?: Record<string, unknown>
  }
): Promise<V2ApiResponse<{ document: V2Document }>> {
  const formData = new FormData()
  formData.append('file', file)
  formData.append('type', type)

  if (options?.identification_id) {
    formData.append('identification_id', options.identification_id)
  }
  if (options?.metadata) {
    formData.append('metadata', JSON.stringify(options.metadata))
  }

  const response = await api.post<V2ApiResponse<{ document: V2Document }>>(BASE_PATH, formData)
  return response.data
}

/**
 * Get available document types.
 */
export async function getTypes(): Promise<V2ApiResponse<{ types: Record<string, string>; categories: Record<string, string> }>> {
  const response = await api.get<V2ApiResponse<{ types: Record<string, string>; categories: Record<string, string> }>>(`${BASE_PATH}/types`)
  return response.data
}

/**
 * Get rejected documents that need re-upload.
 */
export async function getRejected(): Promise<V2ApiResponse<{ documents: Array<{ id: string; type: string; type_label: string; rejection_reason: string | null; rejected_at: string | null }> }>> {
  const response = await api.get<V2ApiResponse<{ documents: Array<{ id: string; type: string; type_label: string; rejection_reason: string | null; rejected_at: string | null }> }>>(`${BASE_PATH}/rejected`)
  return response.data
}

/**
 * Get missing required documents.
 */
export async function getMissing(applicationId?: string): Promise<V2ApiResponse<{
  missing_types: string[]
  missing_labels: Array<{ type: string; label: string }>
  is_complete: boolean
}>> {
  const response = await api.get<V2ApiResponse<{
    missing_types: string[]
    missing_labels: Array<{ type: string; label: string }>
    is_complete: boolean
  }>>(`${BASE_PATH}/missing`, {
    params: applicationId ? { application_id: applicationId } : undefined,
  })
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
 * Backend returns: { url, expires_at, filename }
 */
export async function download(id: string): Promise<V2ApiResponse<{ url: string; expires_at: string; filename: string }>> {
  const response = await api.get<V2ApiResponse<{ url: string; expires_at: string; filename: string }>>(
    `${BASE_PATH}/${id}/download`
  )
  return response.data
}

/**
 * Stream document content directly through API (no external URL).
 * Returns blob that can be used with URL.createObjectURL().
 */
export async function stream(id: string): Promise<Blob> {
  const response = await api.get<Blob>(`${BASE_PATH}/${id}/stream`, {
    responseType: 'blob',
  })
  return response.data
}

/**
 * Delete a document.
 */
export async function remove(id: string): Promise<V2ApiResponse<{ message: string }>> {
  const response = await api.delete<V2ApiResponse<{ message: string }>>(`${BASE_PATH}/${id}`)
  return response.data
}

export default {
  list,
  upload,
  getTypes,
  getRejected,
  getMissing,
  get,
  download,
  stream,
  remove,
}
