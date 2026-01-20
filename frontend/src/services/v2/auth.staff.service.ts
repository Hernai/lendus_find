/**
 * V2 Staff Authentication Service
 *
 * Handles email/password authentication for staff users.
 * All endpoints are under /api/v2/staff/auth
 */

import { api } from '../api'
import type {
  V2ApiResponse,
  V2StaffLoginPayload,
  V2AuthResponse,
  V2StaffUser,
} from '@/types/v2'

const BASE_PATH = '/v2/staff/auth'

/**
 * Login with email and password.
 * Backend returns: { token, user: { id, email, role, is_staff, is_active, profile, permissions, last_login_at } }
 */
export async function login(payload: V2StaffLoginPayload): Promise<V2ApiResponse<{ token: string; user: V2StaffUser }>> {
  const response = await api.post<V2ApiResponse<{ token: string; user: V2StaffUser }>>(
    `${BASE_PATH}/login`,
    payload
  )
  return response.data
}

/**
 * Get current authenticated staff profile.
 * Requires authentication.
 */
export async function getMe(): Promise<V2ApiResponse<{ user: V2StaffUser }>> {
  const response = await api.get<V2ApiResponse<{ user: V2StaffUser }>>(`${BASE_PATH}/me`)
  return response.data
}

/**
 * Logout current staff session.
 * Requires authentication.
 * Note: Backend returns null data with success message.
 */
export async function logout(): Promise<V2ApiResponse<null>> {
  const response = await api.post<V2ApiResponse<null>>(`${BASE_PATH}/logout`)
  return response.data
}

/**
 * Refresh authentication token.
 * Requires authentication.
 * Note: Backend only returns token, not full user object.
 */
export async function refreshToken(): Promise<V2ApiResponse<{ token: string }>> {
  const response = await api.post<V2ApiResponse<{ token: string }>>(`${BASE_PATH}/refresh`)
  return response.data
}

// Export as default object for consistency
export default {
  login,
  getMe,
  logout,
  refreshToken,
}
