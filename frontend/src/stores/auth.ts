import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { api } from '@/services/api'
import type { User, OtpMethod, SendOtpResponse, VerifyOtpResponse } from '@/types'

interface RequestOtpApiResponse {
  success: boolean
  message: string
  channel: string
  code?: string // Only in dev mode
}

interface VerifyOtpApiResponse {
  success: boolean
  token: string
  user: {
    id: string
    phone: string | null
    email: string | null
    type: string
    is_admin: boolean
  }
}

interface MeApiResponse {
  user: {
    id: string
    phone: string | null
    email: string | null
    type: string
    is_admin: boolean
    applicant?: unknown
  }
}

export const useAuthStore = defineStore('auth', () => {
  // State
  const user = ref<User | null>(null)
  const token = ref<string | null>(localStorage.getItem('auth_token'))
  const isLoading = ref(false)
  const otpDestination = ref<string | null>(null)
  const otpMethod = ref<OtpMethod | null>(null)
  const otpExpiresAt = ref<Date | null>(null)

  // Getters
  const isAuthenticated = computed(() => !!token.value && !!user.value)
  const isApplicant = computed(() => user.value?.role === 'APPLICANT')
  const isAnalyst = computed(() => user.value?.role === 'ANALYST')
  const isAdmin = computed(() => user.value?.role === 'ADMIN')

  // Helper to map backend user type to frontend role
  const mapUserType = (type: string, isAdmin: boolean): User['role'] => {
    if (isAdmin) return 'ADMIN'
    if (type === 'ANALYST') return 'ANALYST'
    return 'APPLICANT'
  }

  // Actions
  const sendOtp = async (destination: string, method: OtpMethod): Promise<SendOtpResponse> => {
    isLoading.value = true

    try {
      // Clean phone number (remove formatting)
      const cleanPhone = destination.replace(/\D/g, '')

      // Map method to backend channel
      const channelMap: Record<OtpMethod, string> = {
        sms: 'SMS',
        whatsapp: 'WHATSAPP',
        email: 'EMAIL'
      }

      const payload = method === 'email'
        ? { email: destination, channel: channelMap[method] }
        : { phone: cleanPhone, channel: channelMap[method] }

      const response = await api.post<RequestOtpApiResponse>('/auth/otp/request', payload)

      if (response.data.success) {
        otpDestination.value = method === 'email' ? destination : cleanPhone
        otpMethod.value = method
        otpExpiresAt.value = new Date(Date.now() + 10 * 60 * 1000) // 10 minutes

        // Log dev code if available
        if (response.data.code) {
          console.log('🔐 Dev OTP Code:', response.data.code)
        }

        return {
          success: true,
          expires_at: otpExpiresAt.value.toISOString(),
          method
        }
      }

      throw new Error('Failed to send OTP')
    } catch (error) {
      console.error('Failed to send OTP:', error)
      throw error
    } finally {
      isLoading.value = false
    }
  }

  const verifyOtp = async (code: string): Promise<VerifyOtpResponse> => {
    if (!otpDestination.value) {
      return { success: false, error: 'OTP_EXPIRED' }
    }

    isLoading.value = true

    try {
      const payload = otpMethod.value === 'email'
        ? { email: otpDestination.value, code }
        : { phone: otpDestination.value, code }

      const response = await api.post<VerifyOtpApiResponse>('/auth/otp/verify', payload)

      if (response.data.success) {
        const apiUser = response.data.user

        // Map backend user to frontend User type
        const mappedUser: User = {
          id: apiUser.id,
          tenant_id: '', // Will be set by backend context
          phone: apiUser.phone || '',
          email: apiUser.email,
          role: mapUserType(apiUser.type, apiUser.is_admin),
          phone_verified_at: apiUser.phone ? new Date().toISOString() : null,
          created_at: new Date().toISOString(),
          updated_at: new Date().toISOString()
        }

        user.value = mappedUser
        token.value = response.data.token
        localStorage.setItem('auth_token', response.data.token)

        // Clear OTP state
        otpDestination.value = null
        otpMethod.value = null
        otpExpiresAt.value = null

        return {
          success: true,
          token: response.data.token,
          user: mappedUser
        }
      }

      return { success: false, error: 'INVALID_CODE' }
    } catch (error: unknown) {
      console.error('Failed to verify OTP:', error)

      // Handle 401 - invalid code
      if ((error as { response?: { status?: number } })?.response?.status === 401) {
        return { success: false, error: 'INVALID_CODE', attempts_remaining: 4 }
      }

      throw error
    } finally {
      isLoading.value = false
    }
  }

  const resendOtp = async (method?: OtpMethod): Promise<SendOtpResponse> => {
    if (!otpDestination.value) {
      throw new Error('No OTP destination set')
    }

    return sendOtp(otpDestination.value, method ?? otpMethod.value ?? 'sms')
  }

  const logout = async () => {
    try {
      if (token.value) {
        await api.post('/auth/logout')
      }
    } catch (error) {
      console.error('Logout API error:', error)
    } finally {
      user.value = null
      token.value = null
      localStorage.removeItem('auth_token')
      otpDestination.value = null
      otpMethod.value = null
      otpExpiresAt.value = null
    }
  }

  const checkAuth = async (targetPath?: string) => {
    if (!token.value) return false

    try {
      const response = await api.get<MeApiResponse>('/me')
      const apiUser = response.data.user

      // Map backend user to frontend User type
      user.value = {
        id: apiUser.id,
        tenant_id: '',
        phone: apiUser.phone || '',
        email: apiUser.email,
        role: mapUserType(apiUser.type, apiUser.is_admin),
        phone_verified_at: apiUser.phone ? new Date().toISOString() : null,
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString()
      }

      // Check if user has access to target route
      const pathToCheck = targetPath || window.location.pathname
      const isAdminRoute = pathToCheck.startsWith('/admin')

      if (isAdminRoute && !apiUser.is_admin) {
        // User doesn't have admin access
        return false
      }

      return true
    } catch {
      // Token is invalid, clear auth state
      user.value = null
      token.value = null
      localStorage.removeItem('auth_token')
      return false
    }
  }

  return {
    // State
    user,
    token,
    isLoading,
    otpDestination,
    otpMethod,
    otpExpiresAt,
    // Getters
    isAuthenticated,
    isApplicant,
    isAnalyst,
    isAdmin,
    // Actions
    sendOtp,
    verifyOtp,
    resendOtp,
    logout,
    checkAuth
  }
})
