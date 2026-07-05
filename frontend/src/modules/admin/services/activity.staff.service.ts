/**
 * V2 Staff Activity Feed Service.
 *
 * Feed unificado de actividad de una solicitud: combina eventos de negocio
 * (application_status_history), auditoria (audit_logs) y llamadas a APIs
 * externas (api_logs) en una sola linea de tiempo cronologica.
 *
 * Reemplaza a los servicios viejos audit.staff.service y la pagina de
 * api-logs por application del application.staff.service.
 */

import { api } from '@/services/api'
import type { V2ApiResponse } from '@/types/v2'

export type ActivityKind = 'event' | 'audit' | 'api'
export type ActivitySeverity = 'info' | 'success' | 'warning' | 'error'
export type ActivityActorType = 'staff' | 'applicant' | 'system'

export interface ActivityActor {
  type: ActivityActorType
  id: string | null
  name: string
}

export interface ActivityItem {
  id: string
  kind: ActivityKind
  timestamp: string
  actor: ActivityActor
  title: string
  summary: string
  severity: ActivitySeverity
  icon: string
  metadata: Record<string, unknown>
}

export interface ActivityFeedResponse {
  items: ActivityItem[]
  next_cursor: string | null
  has_more: boolean
}

export interface ActivityFeedFilters {
  cursor?: string | null
  kind?: ActivityKind | null
  q?: string | null
  per_page?: number
  include_http?: boolean
}

function buildQuery(filters: ActivityFeedFilters): string {
  const params = new URLSearchParams()
  if (filters.cursor) params.set('cursor', filters.cursor)
  if (filters.kind) params.set('kind', filters.kind)
  if (filters.q) params.set('q', filters.q)
  if (filters.per_page) params.set('per_page', String(filters.per_page))
  if (filters.include_http) params.set('include_http', '1')
  const qs = params.toString()
  return qs ? `?${qs}` : ''
}

export async function getActivity(
  applicationId: string,
  filters: ActivityFeedFilters = {},
): Promise<V2ApiResponse<ActivityFeedResponse>> {
  const response = await api.get<V2ApiResponse<ActivityFeedResponse>>(
    `/v2/staff/applications/${applicationId}/activity${buildQuery(filters)}`,
  )
  return response.data
}

export default {
  getActivity,
}
