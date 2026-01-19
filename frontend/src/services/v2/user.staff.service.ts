/**
 * V2 Staff User Management Service
 *
 * Handles staff user CRUD operations.
 * All endpoints are under /api/v2/staff/users
 */

import { api } from '../api'
import type { V2ApiResponse, V2PaginatedResponse } from '@/types/v2'

// =====================================================
// Types
// =====================================================

export interface V2StaffUser {
  id: string
  email: string
  role: 'ANALYST' | 'SUPERVISOR' | 'ADMIN' | 'SUPER_ADMIN'
  is_active: boolean
  last_login_at: string | null
  created_at: string
  updated_at: string
  // Profile data (flattened)
  name: string
  first_name: string | null
  last_name: string | null
  last_name_2: string | null
  phone: string | null
  title: string | null
  initials: string
  // Extended data (when fetching single user)
  stats?: {
    total_assigned: number
    pending_review: number
  }
  permissions?: Record<string, boolean>
}

export interface V2StaffUserCreatePayload {
  email: string
  first_name: string
  last_name: string
  last_name_2?: string
  phone?: string
  role: V2StaffUser['role']
  password?: string
  title?: string
}

export interface V2StaffUserUpdatePayload {
  email?: string
  first_name?: string
  last_name?: string
  last_name_2?: string
  phone?: string
  role?: V2StaffUser['role']
  password?: string
  is_active?: boolean
  title?: string
}

export interface V2StaffUserFilters {
  role?: string
  active?: boolean
  search?: string
  per_page?: number
  page?: number
}

export interface V2StaffUserCreateResponse extends V2StaffUser {
  temporary_password?: string | null
}

const BASE_PATH = '/v2/staff/users'

// =====================================================
// CRUD Operations
// =====================================================

/**
 * List all staff users with optional filters.
 */
export async function list(filters?: V2StaffUserFilters): Promise<V2PaginatedResponse<V2StaffUser>> {
  const response = await api.get<V2PaginatedResponse<V2StaffUser>>(BASE_PATH, { params: filters })
  return response.data
}

/**
 * Create a new staff user.
 */
export async function create(
  payload: V2StaffUserCreatePayload
): Promise<V2ApiResponse<V2StaffUserCreateResponse> & { temporary_password?: string | null }> {
  const response = await api.post<V2ApiResponse<V2StaffUserCreateResponse> & { temporary_password?: string | null }>(
    BASE_PATH,
    payload
  )
  return response.data
}

/**
 * Get a specific staff user by ID.
 */
export async function get(id: string): Promise<V2ApiResponse<V2StaffUser>> {
  const response = await api.get<V2ApiResponse<V2StaffUser>>(`${BASE_PATH}/${id}`)
  return response.data
}

/**
 * Update a staff user.
 */
export async function update(id: string, payload: V2StaffUserUpdatePayload): Promise<V2ApiResponse<V2StaffUser>> {
  const response = await api.put<V2ApiResponse<V2StaffUser>>(`${BASE_PATH}/${id}`, payload)
  return response.data
}

/**
 * Delete a staff user.
 */
export async function remove(id: string): Promise<V2ApiResponse<{ message: string }>> {
  const response = await api.delete<V2ApiResponse<{ message: string }>>(`${BASE_PATH}/${id}`)
  return response.data
}

// =====================================================
// Export
// =====================================================

export default {
  list,
  create,
  get,
  update,
  remove,
}
