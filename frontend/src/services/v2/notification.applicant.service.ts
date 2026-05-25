/**
 * V2 Applicant Notification Service
 *
 * Maneja notificaciones in-app del aplicante.
 * Todos los endpoints están bajo /api/v2/applicant/notifications
 */

import { api } from '../api'
import type { V2ApiResponse } from '@/types/v2'

// =====================================================
// Types
// =====================================================

export interface InAppNotification {
  id: string
  event: string
  subject: string | null
  body: string
  read_at: string | null
  is_read: boolean
  created_at: string
  metadata: Record<string, unknown> | null
}

export interface NotificationsResponse {
  notifications: InAppNotification[]
  pagination: {
    total: number
    per_page: number
    current_page: number
    last_page: number
  }
}

const BASE_PATH = '/v2/applicant/notifications'

// =====================================================
// API Functions
// =====================================================

/**
 * Listar notificaciones paginadas.
 */
export async function list(page = 1): Promise<V2ApiResponse<NotificationsResponse>> {
  const response = await api.get<V2ApiResponse<NotificationsResponse>>(BASE_PATH, {
    params: { page },
  })
  return response.data
}

/**
 * Obtener contador de notificaciones no leídas.
 */
export async function unreadCount(): Promise<V2ApiResponse<{ unread_count: number }>> {
  const response = await api.get<V2ApiResponse<{ unread_count: number }>>(`${BASE_PATH}/unread-count`)
  return response.data
}

/**
 * Marcar una notificación como leída.
 */
export async function markAsRead(id: string): Promise<V2ApiResponse<{ notification: InAppNotification }>> {
  const response = await api.patch<V2ApiResponse<{ notification: InAppNotification }>>(`${BASE_PATH}/${id}/read`)
  return response.data
}

/**
 * Marcar todas las notificaciones como leídas.
 */
export async function markAllAsRead(): Promise<V2ApiResponse<{ updated_count: number }>> {
  const response = await api.post<V2ApiResponse<{ updated_count: number }>>(`${BASE_PATH}/mark-all-read`)
  return response.data
}

// =====================================================
// Default Export
// =====================================================

export default {
  list,
  unreadCount,
  markAsRead,
  markAllAsRead,
}
