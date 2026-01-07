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
      has_pin?: boolean
    }
  }
}

export interface CheckUserResponse {
  exists: boolean
  has_pin: boolean
  is_locked: boolean
  lockout_minutes: number
}

export interface PinLoginPayload {
  phone: string
  pin: string
}

export interface PinSetupPayload {
  pin: string
  pin_confirmation: string
}

export interface PinChangePayload {
  current_pin: string
  new_pin: string
  new_pin_confirmation: string
}

export interface PinResetPayload {
  phone: string
  code: string
  new_pin: string
  new_pin_confirmation: string
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

  /**
   * Check if user exists and has PIN
   */
  checkUser: async (phone: string) => {
    const response = await api.post<CheckUserResponse>('/auth/check-user', { phone })
    return response.data
  },

  /**
   * Login with phone + PIN
   */
  loginWithPin: async (payload: PinLoginPayload) => {
    const response = await api.post<AuthResponse>('/auth/pin/login', payload)
    return response.data
  },

  /**
   * Setup PIN (requires authentication)
   */
  setupPin: async (payload: PinSetupPayload) => {
    const response = await api.post<{ success: boolean; message: string }>('/auth/pin/setup', payload)
    return response.data
  },

  /**
   * Change PIN (requires authentication)
   */
  changePin: async (payload: PinChangePayload) => {
    const response = await api.post<{ success: boolean; message: string }>('/auth/pin/change', payload)
    return response.data
  },

  /**
   * Reset PIN via OTP
   */
  resetPinWithOtp: async (payload: PinResetPayload) => {
    const response = await api.post<AuthResponse>('/auth/pin/reset', payload)
    return response.data
  },
}

export default authService
