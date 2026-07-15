/**
 * V2 Staff Webhook Service
 *
 * Gestión de endpoints de webhook, log de entregas, reenvío y evento de prueba.
 * All endpoints are under /api/v2/staff/webhooks
 */

import { api } from '@/services/api'
import type { V2ApiResponse } from '@/types/v2'

const BASE = '/v2/staff/webhooks'

// =====================================================
// Types
// =====================================================

export interface WebhookEventOption {
  value: string
  label: string
}

export interface V2WebhookEndpoint {
  id: string
  name: string
  url: string
  events: string[]
  is_active: boolean
  is_sandbox: boolean
  description: string | null
  has_secret: boolean
  last_success_at: string | null
  last_failure_at: string | null
  created_at: string | null
  /** Solo presente al crear o rotar el secreto (una sola vez). */
  secret?: string
}

export interface V2WebhookDelivery {
  id: string
  webhook_endpoint_id: string
  event: string
  event_id: string
  status: 'PENDING' | 'SENT' | 'FAILED' | 'RETRYING'
  attempts: number
  max_attempts: number
  response_code: number | null
  response_body: string | null
  error_message: string | null
  last_attempt_at: string | null
  next_retry_at: string | null
  created_at: string | null
}

export interface V2WebhookEndpointPayload {
  name: string
  url: string
  events: string[]
  is_active?: boolean
  is_sandbox?: boolean
  description?: string
}

// =====================================================
// API Functions
// =====================================================

export async function getEvents(): Promise<V2ApiResponse<{ events: WebhookEventOption[] }>> {
  const res = await api.get<V2ApiResponse<{ events: WebhookEventOption[] }>>(`${BASE}/events`)
  return res.data
}

export async function listEndpoints(): Promise<V2ApiResponse<{ endpoints: V2WebhookEndpoint[] }>> {
  const res = await api.get<V2ApiResponse<{ endpoints: V2WebhookEndpoint[] }>>(`${BASE}/endpoints`)
  return res.data
}

export async function createEndpoint(payload: V2WebhookEndpointPayload): Promise<V2ApiResponse<V2WebhookEndpoint>> {
  const res = await api.post<V2ApiResponse<V2WebhookEndpoint>>(`${BASE}/endpoints`, payload)
  return res.data
}

export async function updateEndpoint(id: string, payload: V2WebhookEndpointPayload): Promise<V2ApiResponse<V2WebhookEndpoint>> {
  const res = await api.put<V2ApiResponse<V2WebhookEndpoint>>(`${BASE}/endpoints/${id}`, payload)
  return res.data
}

export async function deleteEndpoint(id: string): Promise<V2ApiResponse<null>> {
  const res = await api.delete<V2ApiResponse<null>>(`${BASE}/endpoints/${id}`)
  return res.data
}

export async function rotateSecret(id: string): Promise<V2ApiResponse<{ secret: string }>> {
  const res = await api.post<V2ApiResponse<{ secret: string }>>(`${BASE}/endpoints/${id}/rotate-secret`)
  return res.data
}

export async function testEndpoint(id: string): Promise<V2ApiResponse<{ delivery_id: string }>> {
  const res = await api.post<V2ApiResponse<{ delivery_id: string }>>(`${BASE}/endpoints/${id}/test`)
  return res.data
}

export async function listDeliveries(params?: {
  endpoint_id?: string
  status?: string
  event?: string
}): Promise<V2ApiResponse<{ deliveries: V2WebhookDelivery[] }>> {
  const res = await api.get<V2ApiResponse<{ deliveries: V2WebhookDelivery[] }>>(`${BASE}/deliveries`, { params })
  return res.data
}

export async function retryDelivery(id: string): Promise<V2ApiResponse<null>> {
  const res = await api.post<V2ApiResponse<null>>(`${BASE}/deliveries/${id}/retry`)
  return res.data
}

export default {
  getEvents,
  listEndpoints,
  createEndpoint,
  updateEndpoint,
  deleteEndpoint,
  rotateSecret,
  testEndpoint,
  listDeliveries,
  retryDelivery,
}
