// Auth Types - Authentication & OTP

export interface User {
  id: string
  tenant_id: string
  phone: string
  email: string | null
  role: UserRole
  phone_verified_at: string | null
  created_at: string
  updated_at: string
}

export type UserRole = 'APPLICANT' | 'SUPERVISOR' | 'ANALYST' | 'ADMIN' | 'SUPER_ADMIN'

export type OtpMethod = 'sms' | 'whatsapp' | 'email'

export interface SendOtpParams {
  destination: string
  method: OtpMethod
}

export interface SendOtpResponse {
  success: boolean
  expires_at: string
  method: OtpMethod
}

export interface VerifyOtpParams {
  destination: string
  code: string
}

export interface VerifyOtpResponse {
  success: boolean
  error?: 'OTP_EXPIRED' | 'MAX_ATTEMPTS_EXCEEDED' | 'INVALID_CODE'
  attempts_remaining?: number
  token?: string
  user?: User
  needsPinSetup?: boolean
}

export interface AuthState {
  user: User | null
  token: string | null
  isAuthenticated: boolean
  isLoading: boolean
}

export interface LoginCredentials {
  phone?: string
  email?: string
  password: string
}
