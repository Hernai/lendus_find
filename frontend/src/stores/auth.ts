import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import type { User, OtpMethod, SendOtpResponse, VerifyOtpResponse } from '@/types'

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

  // Actions
  const sendOtp = async (destination: string, method: OtpMethod): Promise<SendOtpResponse> => {
    isLoading.value = true

    try {
      // TODO: Replace with actual API call
      // const response = await api.post<SendOtpResponse>('/api/auth/otp/send', { destination, method })

      // Mock response
      await new Promise(resolve => setTimeout(resolve, 1000))

      otpDestination.value = destination
      otpMethod.value = method
      otpExpiresAt.value = new Date(Date.now() + 5 * 60 * 1000) // 5 minutes

      return {
        success: true,
        expires_at: otpExpiresAt.value.toISOString(),
        method
      }
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
      // TODO: Replace with actual API call
      // const response = await api.post<VerifyOtpResponse>('/api/auth/otp/verify', {
      //   destination: otpDestination.value,
      //   code
      // })

      // Mock response - accept any 6-digit code for development
      await new Promise(resolve => setTimeout(resolve, 1000))

      if (code.length !== 6) {
        return { success: false, error: 'INVALID_CODE', attempts_remaining: 2 }
      }

      // Mock successful authentication
      const mockUser: User = {
        id: 'user-001',
        tenant_id: 'tenant-001',
        phone: otpDestination.value,
        email: null,
        role: 'APPLICANT',
        phone_verified_at: new Date().toISOString(),
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString()
      }

      const mockToken = 'mock-jwt-token-' + Date.now()

      user.value = mockUser
      token.value = mockToken
      localStorage.setItem('auth_token', mockToken)

      // Clear OTP state
      otpDestination.value = null
      otpMethod.value = null
      otpExpiresAt.value = null

      return {
        success: true,
        token: mockToken,
        user: mockUser
      }
    } catch (error) {
      console.error('Failed to verify OTP:', error)
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

  const logout = () => {
    user.value = null
    token.value = null
    localStorage.removeItem('auth_token')
    otpDestination.value = null
    otpMethod.value = null
    otpExpiresAt.value = null
  }

  const checkAuth = async () => {
    if (!token.value) return false

    try {
      // TODO: Replace with actual API call to validate token
      // const response = await api.get<User>('/api/auth/me')
      // user.value = response.data

      // Mock - for development, assume token is valid
      if (!user.value) {
        user.value = {
          id: 'user-001',
          tenant_id: 'tenant-001',
          phone: '5512345678',
          email: null,
          role: 'APPLICANT',
          phone_verified_at: new Date().toISOString(),
          created_at: new Date().toISOString(),
          updated_at: new Date().toISOString()
        }
      }

      return true
    } catch {
      logout()
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
