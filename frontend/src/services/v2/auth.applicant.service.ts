/**
 * V2 Applicant Authentication Service
 *
 * Handles OTP-based and PIN-based authentication for applicants.
 * All endpoints are under /api/v2/applicant/auth
 */

import { api } from '../api'
import type {
  V2ApiResponse,
  V2OtpRequestPayload,
  V2OtpVerifyPayload,
  V2CheckUserPayload,
  V2CheckUserResponse,
  V2PinLoginPayload,
  V2PinSetupPayload,
  V2PinChangePayload,
  V2AuthResponse,
  V2ApplicantUser,
} from '@/types/v2'

const BASE_PATH = '/v2/applicant/auth'

/**
 * Request OTP code via SMS, WhatsApp, or Email.
 * Rate limited: 3 requests per minute.
 */
export async function requestOtp(payload: V2OtpRequestPayload): Promise<V2ApiResponse<{ message: string; expires_at: string }>> {
  const response = await api.post<V2ApiResponse<{ message: string; expires_at: string }>>(
    `${BASE_PATH}/otp/request`,
    payload
  )
  return response.data
}

/**
 * Verify OTP code and authenticate.
 * Rate limited: 5 attempts per minute.
 */
export async function verifyOtp(payload: V2OtpVerifyPayload): Promise<V2ApiResponse<V2AuthResponse>> {
  const response = await api.post<V2ApiResponse<V2AuthResponse>>(
    `${BASE_PATH}/otp/verify`,
    payload
  )
  return response.data
}

/**
 * Check if user exists and their authentication methods.
 */
export async function checkUser(payload: V2CheckUserPayload): Promise<V2ApiResponse<V2CheckUserResponse>> {
  const response = await api.post<V2ApiResponse<V2CheckUserResponse>>(
    `${BASE_PATH}/check-user`,
    payload
  )
  return response.data
}

/**
 * Login with phone and PIN.
 * Rate limited: 5 attempts per minute.
 */
export async function loginWithPin(payload: V2PinLoginPayload): Promise<V2ApiResponse<V2AuthResponse>> {
  const response = await api.post<V2ApiResponse<V2AuthResponse>>(
    `${BASE_PATH}/pin/login`,
    payload
  )
  return response.data
}

/**
 * Get current authenticated applicant profile.
 * Requires authentication.
 */
export async function getMe(): Promise<V2ApiResponse<V2ApplicantUser>> {
  const response = await api.get<V2ApiResponse<V2ApplicantUser>>(`${BASE_PATH}/me`)
  return response.data
}

/**
 * Logout current applicant session.
 * Requires authentication.
 */
export async function logout(): Promise<V2ApiResponse<{ message: string }>> {
  const response = await api.post<V2ApiResponse<{ message: string }>>(`${BASE_PATH}/logout`)
  return response.data
}

/**
 * Refresh authentication token.
 * Requires authentication.
 */
export async function refreshToken(): Promise<V2ApiResponse<V2AuthResponse>> {
  const response = await api.post<V2ApiResponse<V2AuthResponse>>(`${BASE_PATH}/refresh`)
  return response.data
}

/**
 * Setup PIN for the first time.
 * Requires authentication.
 */
export async function setupPin(payload: V2PinSetupPayload): Promise<V2ApiResponse<{ message: string }>> {
  const response = await api.post<V2ApiResponse<{ message: string }>>(
    `${BASE_PATH}/pin/setup`,
    payload
  )
  return response.data
}

/**
 * Change existing PIN.
 * Requires authentication.
 */
export async function changePin(payload: V2PinChangePayload): Promise<V2ApiResponse<{ message: string }>> {
  const response = await api.post<V2ApiResponse<{ message: string }>>(
    `${BASE_PATH}/pin/change`,
    payload
  )
  return response.data
}

// Export as default object for consistency with other services
export default {
  requestOtp,
  verifyOtp,
  checkUser,
  loginWithPin,
  getMe,
  logout,
  refreshToken,
  setupPin,
  changePin,
}
