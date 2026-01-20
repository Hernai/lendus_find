/**
 * V2 Staff Integration Service
 *
 * Handles API integration management for staff users.
 * All endpoints are under /api/v2/staff/integrations
 */

import { api } from '../api'
import type { V2ApiResponse } from '@/types/v2'

const BASE_PATH = '/v2/staff/integrations'

// =====================================================
// Types
// =====================================================

export interface V2Integration {
  id: string
  provider: string
  provider_label: string
  service_type: string
  service_type_label: string
  from_number?: string
  from_email?: string
  domain?: string
  is_active: boolean
  is_sandbox: boolean
  has_credentials: boolean
  masked_credentials: {
    account_sid?: string
    auth_token?: string
    api_key?: string
    api_secret?: string
  }
  last_tested_at?: string
  last_test_success?: boolean
  last_test_error?: string
}

export interface V2IntegrationOptions {
  providers: Record<string, string>
  service_types: Record<string, string>
}

export interface V2IntegrationPayload {
  provider: string
  service_type: string
  account_sid?: string
  auth_token?: string
  api_key?: string
  api_secret?: string
  from_number?: string
  from_email?: string
  domain?: string
  webhook_url?: string
  webhook_secret?: string
  extra_config?: Record<string, unknown>
  is_active?: boolean
  is_sandbox?: boolean
}

export interface V2IntegrationTestPayload {
  test_phone?: string
  test_email?: string
}

/**
 * Response structure for successful test.
 * Note: This is the data inside success response, not the full V2ApiResponse.
 */
export interface V2IntegrationTestResult {
  details?: {
    sid?: string
    status?: string
    token_preview?: string
  }
}

// =====================================================
// API Functions
// =====================================================

/**
 * List all integrations for current tenant.
 */
export async function list(): Promise<V2ApiResponse<{ integrations: V2Integration[] }>> {
  const response = await api.get<V2ApiResponse<{ integrations: V2Integration[] }>>(BASE_PATH)
  return response.data
}

/**
 * Get available providers and service types.
 */
export async function getOptions(): Promise<V2ApiResponse<V2IntegrationOptions>> {
  const response = await api.get<V2ApiResponse<V2IntegrationOptions>>(`${BASE_PATH}/options`)
  return response.data
}

/**
 * Create or update an integration.
 */
export async function save(payload: V2IntegrationPayload): Promise<V2ApiResponse<{ integration: V2Integration }>> {
  const response = await api.post<V2ApiResponse<{ integration: V2Integration }>>(BASE_PATH, payload)
  return response.data
}

/**
 * Test an integration.
 */
export async function test(id: string, payload?: V2IntegrationTestPayload): Promise<V2ApiResponse<V2IntegrationTestResult>> {
  const response = await api.post<V2ApiResponse<V2IntegrationTestResult>>(`${BASE_PATH}/${id}/test`, payload)
  return response.data
}

/**
 * Toggle integration active status.
 */
export async function toggle(id: string): Promise<V2ApiResponse<{ integration: V2Integration }>> {
  const response = await api.patch<V2ApiResponse<{ integration: V2Integration }>>(`${BASE_PATH}/${id}/toggle`)
  return response.data
}

/**
 * Delete an integration.
 */
export async function destroy(id: string): Promise<V2ApiResponse<null>> {
  const response = await api.delete<V2ApiResponse<null>>(`${BASE_PATH}/${id}`)
  return response.data
}

export default {
  list,
  getOptions,
  save,
  test,
  toggle,
  destroy,
}
