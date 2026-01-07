import { api } from './api'

export interface RequestOtpPayload {
  method: 'sms' | 'whatsapp' | 'email'
  phone?: string
  email?: string
}

export interface VerifyOtpPayload {
  code: string
  phone?: string
  email?: string
  device_info?: {
    user_agent: string
    platform: string
  }
}

export interface AuthResponse {
  message: string
  data: {
    token: string
    user: {
      id: string
      name: string
      email: string | null
      phone: string
      role: string
      has_applicant_profile: boolean
    }
  }
}

export interface UserProfile {
  id: string
  name: string
  email: string | null
  phone: string
  role: string
  tenant_id: string
  first_name: string | null
  last_name: string | null
  avatar_url: string | null
  is_active: boolean
  applicant?: {
    id: string
    full_name: string
    kyc_status: string
    has_signed: boolean
  }
}

const authService = {
  /**
   * Request OTP code
   */
  requestOtp: async (payload: RequestOtpPayload) => {
    const response = await api.post<{ message: string; data: { expires_in: number } }>(
      '/auth/otp/request',
      payload
    )
    return response.data
  },

  /**
   * Verify OTP code and login
   */
  verifyOtp: async (payload: VerifyOtpPayload) => {
    const response = await api.post<AuthResponse>('/auth/otp/verify', payload)
    return response.data
  },

  /**
   * Logout
   */
  logout: async () => {
    const response = await api.post<{ message: string }>('/auth/logout')
    localStorage.removeItem('auth_token')
    return response.data
  },

  /**
   * Get current user profile
   */
  me: async () => {
    const response = await api.get<{ data: UserProfile }>('/me')
    return response.data.data
  },
}

export default authService
