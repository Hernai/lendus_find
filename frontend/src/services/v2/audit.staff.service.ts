/**
 * V2 Staff Audit Log Service.
 *
 * Lista de eventos de auditoría (HTTP_REQUEST + acciones de negocio)
 * para un applicant o aplicación específica.
 */

import { api } from '../api'
import type { V2ApiResponse } from '@/types/v2'

export interface V2AuditLog {
  id: string
  tenant_id: string
  user_id: string | null
  applicant_id: string | null
  application_id: string | null
  action: string
  entity_type: string | null
  entity_id: string | null
  old_values: Record<string, unknown> | null
  new_values: Record<string, unknown> | null
  metadata: Record<string, unknown> | null
  ip_address: string | null
  user_agent: string | null
  latitude: string | number | null
  longitude: string | number | null
  city: string | null
  region: string | null
  country: string | null
  device_type: string | null
  browser: string | null
  os: string | null
  created_at: string
}

export interface V2AuditLogPage {
  items: V2AuditLog[]
  pagination: {
    current_page: number
    per_page: number
    total: number
    last_page: number
  }
}

export interface V2AuditLogFilters {
  page?: number
  per_page?: number
  action?: string | string[]
  entity_type?: string
  from?: string
  to?: string
  exclude_http?: boolean
}

function buildQuery(filters: V2AuditLogFilters = {}): string {
  const params = new URLSearchParams()
  if (filters.page) params.set('page', String(filters.page))
  if (filters.per_page) params.set('per_page', String(filters.per_page))
  if (filters.entity_type) params.set('entity_type', filters.entity_type)
  if (filters.from) params.set('from', filters.from)
  if (filters.to) params.set('to', filters.to)
  if (filters.exclude_http) params.set('exclude_http', '1')
  if (filters.action) {
    const value = Array.isArray(filters.action) ? filters.action.join(',') : filters.action
    params.set('action', value)
  }
  const qs = params.toString()
  return qs ? `?${qs}` : ''
}

export async function listByApplicant(
  applicantId: string,
  filters: V2AuditLogFilters = {},
): Promise<V2ApiResponse<V2AuditLogPage>> {
  const res = await api.get<V2ApiResponse<V2AuditLogPage>>(
    `/v2/staff/applicants/${applicantId}/audit-logs${buildQuery(filters)}`,
  )
  return res.data
}

export async function listByApplication(
  applicationId: string,
  filters: V2AuditLogFilters = {},
): Promise<V2ApiResponse<V2AuditLogPage>> {
  const res = await api.get<V2ApiResponse<V2AuditLogPage>>(
    `/v2/staff/applications/${applicationId}/audit-logs${buildQuery(filters)}`,
  )
  return res.data
}

export default {
  listByApplicant,
  listByApplication,
}
