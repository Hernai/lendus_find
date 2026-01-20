/**
 * V2 Staff API Log Service
 *
 * Handles API log viewing for staff users.
 * All endpoints are under /api/v2/staff/api-logs
 */

import { api } from '../api'
import type { V2ApiResponse } from '@/types/v2'

const BASE_PATH = '/v2/staff/api-logs'

// =====================================================
// Types
// =====================================================

export interface V2ApiLog {
  id: string
  provider: string
  service: string
  endpoint: string
  method: string
  request_payload: Record<string, unknown>
  response_status: number | null
  response_body: Record<string, unknown>
  success: boolean
  error_code: string | null
  error_message: string | null
  duration_ms: number | null
  cost: number | null
  created_at: string
  applicant_id: string | null
}

export interface V2ApiLogStats {
  today: {
    total: number
    successful: number
    failed: number
  }
  by_provider: Array<{
    provider: string
    total: number
    successful: number
    failed: number
  }>
  avg_duration_ms: number
  total_cost_this_month: number
}

export interface V2ApiLogFilters {
  provider?: string
  service?: string
  success?: 'true' | 'false' | 'all'
  from_date?: string
  to_date?: string
  per_page?: number
  page?: number
}

export interface V2ApiLogListResponse {
  logs: V2ApiLog[]
  meta: {
    current_page: number
    from: number | null
    last_page: number
    per_page: number
    to: number | null
    total: number
  }
}

// =====================================================
// API Functions
// =====================================================

/**
 * List API logs with filters.
 */
export async function list(filters?: V2ApiLogFilters): Promise<V2ApiResponse<V2ApiLogListResponse>> {
  const response = await api.get<V2ApiResponse<V2ApiLogListResponse>>(BASE_PATH, { params: filters })
  return response.data
}

/**
 * Get a single API log by ID.
 */
export async function get(id: string): Promise<V2ApiResponse<{ log: V2ApiLog }>> {
  const response = await api.get<V2ApiResponse<{ log: V2ApiLog }>>(`${BASE_PATH}/${id}`)
  return response.data
}

/**
 * Get API log statistics.
 */
export async function getStats(): Promise<V2ApiResponse<V2ApiLogStats>> {
  const response = await api.get<V2ApiResponse<V2ApiLogStats>>(`${BASE_PATH}/stats`)
  return response.data
}

/**
 * Get available providers for filter dropdown.
 */
export async function getProviders(): Promise<V2ApiResponse<{ providers: string[] }>> {
  const response = await api.get<V2ApiResponse<{ providers: string[] }>>(`${BASE_PATH}/providers`)
  return response.data
}

export default {
  list,
  get,
  getStats,
  getProviders,
}
