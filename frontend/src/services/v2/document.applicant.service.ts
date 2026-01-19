/**
 * V2 Applicant Document Service
 *
 * Handles document upload and management for applicants.
 * All endpoints are under /api/v2/applicant/documents
 */

import { api } from '../api'
import type {
  V2ApiResponse,
  V2PaginatedResponse,
  V2Document,
  V2DocumentType,
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
}): Promise<V2PaginatedResponse<V2Document>> {
  const response = await api.get<V2PaginatedResponse<V2Document>>(BASE_PATH, { params })
  return response.data
}

/**
 * Upload a new document.
 */
export async function upload(
  file: File,
  type: string,
  options?: {
    documentable_type?: string
    documentable_id?: string
    metadata?: Record<string, unknown>
  }
): Promise<V2ApiResponse<V2Document>> {
  const formData = new FormData()
  formData.append('file', file)
  formData.append('type', type)

  if (options?.documentable_type) {
    formData.append('documentable_type', options.documentable_type)
  }
  if (options?.documentable_id) {
    formData.append('documentable_id', options.documentable_id)
  }
  if (options?.metadata) {
    formData.append('metadata', JSON.stringify(options.metadata))
  }

  const response = await api.post<V2ApiResponse<V2Document>>(BASE_PATH, formData)
  return response.data
}

/**
 * Get available document types.
 */
export async function getTypes(): Promise<V2ApiResponse<V2DocumentType[]>> {
  const response = await api.get<V2ApiResponse<V2DocumentType[]>>(`${BASE_PATH}/types`)
  return response.data
}

/**
 * Get rejected documents that need re-upload.
 */
export async function getRejected(): Promise<V2ApiResponse<V2Document[]>> {
  const response = await api.get<V2ApiResponse<V2Document[]>>(`${BASE_PATH}/rejected`)
  return response.data
}

/**
 * Get missing required documents.
 */
export async function getMissing(applicationId?: string): Promise<V2ApiResponse<string[]>> {
  const response = await api.get<V2ApiResponse<string[]>>(`${BASE_PATH}/missing`, {
    params: applicationId ? { application_id: applicationId } : undefined,
  })
  return response.data
}

/**
 * Get document details by ID.
 */
export async function get(id: string): Promise<V2ApiResponse<V2Document>> {
  const response = await api.get<V2ApiResponse<V2Document>>(`${BASE_PATH}/${id}`)
  return response.data
}

/**
 * Download document (returns signed URL).
 */
export async function download(id: string): Promise<V2ApiResponse<{ url: string; expires_at: string }>> {
  const response = await api.get<V2ApiResponse<{ url: string; expires_at: string }>>(
    `${BASE_PATH}/${id}/download`
  )
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
  remove,
}
