/**
 * V2 Applicant Application Service
 *
 * Handles application CRUD and lifecycle operations for applicants.
 * All endpoints are under /api/v2/applicant/applications
 */

import { api } from '../api'
import type {
  V2ApiResponse,
  V2PaginatedResponse,
  V2Application,
  V2ApplicationCreatePayload,
  V2ApplicationUpdatePayload,
  V2CounterOfferResponsePayload,
  V2StatusHistoryEntry,
} from '@/types/v2'

const BASE_PATH = '/v2/applicant/applications'

/**
 * List all applications for the current applicant.
 */
export async function list(params?: {
  status?: string
  page?: number
  per_page?: number
}): Promise<V2PaginatedResponse<V2Application>> {
  const response = await api.get<V2PaginatedResponse<V2Application>>(BASE_PATH, { params })
  return response.data
}

/**
 * Create a new application.
 */
export async function create(payload: V2ApplicationCreatePayload): Promise<V2ApiResponse<V2Application>> {
  const response = await api.post<V2ApiResponse<V2Application>>(BASE_PATH, payload)
  return response.data
}

/**
 * Get application details by ID.
 */
export async function get(id: string): Promise<V2ApiResponse<V2Application>> {
  const response = await api.get<V2ApiResponse<V2Application>>(`${BASE_PATH}/${id}`)
  return response.data
}

/**
 * Update a draft application.
 */
export async function update(id: string, payload: V2ApplicationUpdatePayload): Promise<V2ApiResponse<V2Application>> {
  const response = await api.patch<V2ApiResponse<V2Application>>(`${BASE_PATH}/${id}`, payload)
  return response.data
}

/**
 * Submit application for review.
 */
export async function submit(id: string): Promise<V2ApiResponse<V2Application>> {
  const response = await api.post<V2ApiResponse<V2Application>>(`${BASE_PATH}/${id}/submit`)
  return response.data
}

/**
 * Cancel an application.
 */
export async function cancel(id: string, reason?: string): Promise<V2ApiResponse<V2Application>> {
  const response = await api.post<V2ApiResponse<V2Application>>(
    `${BASE_PATH}/${id}/cancel`,
    reason ? { reason } : undefined
  )
  return response.data
}

/**
 * Respond to a counter-offer (accept or reject).
 */
export async function respondToCounterOffer(
  id: string,
  payload: V2CounterOfferResponsePayload
): Promise<V2ApiResponse<V2Application>> {
  const response = await api.post<V2ApiResponse<V2Application>>(
    `${BASE_PATH}/${id}/counter-offer/respond`,
    payload
  )
  return response.data
}

/**
 * Get application status history.
 */
export async function getHistory(id: string): Promise<V2ApiResponse<V2StatusHistoryEntry[]>> {
  const response = await api.get<V2ApiResponse<V2StatusHistoryEntry[]>>(`${BASE_PATH}/${id}/history`)
  return response.data
}

export default {
  list,
  create,
  get,
  update,
  submit,
  cancel,
  respondToCounterOffer,
  getHistory,
}
