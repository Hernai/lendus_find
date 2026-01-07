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
    has_pin?: boolean
  }
}

interface CheckUserApiResponse {
  exists: boolean
  has_pin: boolean
  is_locked: boolean
  lockout_minutes: number
}

interface PinLoginApiResponse {
  success: boolean
  token: string
  user: {
    id: string
    phone: string | null
    email: string | null
    type: string
    is_admin: boolean
    has_pin: boolean
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

  // PIN-related state
  const hasPin = ref(false)
  const needsPinSetup = ref(false)
  const pinLockoutMinutes = ref(0)

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

        // Set PIN state
        hasPin.value = apiUser.has_pin ?? false
        needsPinSetup.value = !apiUser.has_pin

        // Clear OTP state
        otpDestination.value = null
        otpMethod.value = null
        otpExpiresAt.value = null

        return {
          success: true,
          token: response.data.token,
          user: mappedUser,
          needsPinSetup: !apiUser.has_pin
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

  // PIN-related actions

  const checkUser = async (phone: string): Promise<CheckUserApiResponse> => {
    isLoading.value = true
    try {
      const cleanPhone = phone.replace(/\D/g, '')
      const response = await api.post<CheckUserApiResponse>('/auth/check-user', { phone: cleanPhone })
      hasPin.value = response.data.has_pin
      pinLockoutMinutes.value = response.data.lockout_minutes
      return response.data
    } finally {
      isLoading.value = false
    }
  }

  const loginWithPin = async (phone: string, pin: string): Promise<{ success: boolean; error?: string; attemptsRemaining?: number }> => {
    isLoading.value = true
    try {
      const cleanPhone = phone.replace(/\D/g, '')
      const response = await api.post<PinLoginApiResponse>('/auth/pin/login', { phone: cleanPhone, pin })

      if (response.data.success) {
        const apiUser = response.data.user

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

        token.value = response.data.token
        localStorage.setItem('auth_token', response.data.token)
        hasPin.value = true
        needsPinSetup.value = false

        return { success: true }
      }

      return { success: false, error: 'LOGIN_FAILED' }
    } catch (error: unknown) {
      const axiosError = error as { response?: { status?: number; data?: { message?: string; attempts_remaining?: number; lockout_minutes?: number } } }

      if (axiosError.response?.status === 401) {
        return {
          success: false,
          error: 'INVALID_PIN',
          attemptsRemaining: axiosError.response.data?.attempts_remaining
        }
      }

      if (axiosError.response?.status === 423) {
        pinLockoutMinutes.value = axiosError.response.data?.lockout_minutes || 30
        return { success: false, error: 'ACCOUNT_LOCKED' }
      }

      if (axiosError.response?.status === 400) {
        return { success: false, error: 'NO_PIN_SET' }
      }

      throw error
    } finally {
      isLoading.value = false
    }
  }

  const setupPin = async (pin: string): Promise<{ success: boolean; error?: string }> => {
    isLoading.value = true
    try {
      const response = await api.post<{ success: boolean; message: string }>('/auth/pin/setup', {
        pin,
        pin_confirmation: pin
      })

      if (response.data.success) {
        hasPin.value = true
        needsPinSetup.value = false
        return { success: true }
      }

      return { success: false, error: 'SETUP_FAILED' }
    } catch (error: unknown) {
      const axiosError = error as { response?: { data?: { message?: string } } }
      return { success: false, error: axiosError.response?.data?.message || 'SETUP_FAILED' }
    } finally {
      isLoading.value = false
    }
  }

  const changePin = async (currentPin: string, newPin: string): Promise<{ success: boolean; error?: string }> => {
    isLoading.value = true
    try {
      const response = await api.post<{ success: boolean; message: string }>('/auth/pin/change', {
        current_pin: currentPin,
        new_pin: newPin,
        new_pin_confirmation: newPin
      })

      return { success: response.data.success }
    } catch (error: unknown) {
      const axiosError = error as { response?: { status?: number; data?: { message?: string } } }

      if (axiosError.response?.status === 401) {
        return { success: false, error: 'INVALID_CURRENT_PIN' }
      }

      return { success: false, error: axiosError.response?.data?.message || 'CHANGE_FAILED' }
    } finally {
      isLoading.value = false
    }
  }

  const resetPinWithOtp = async (phone: string, code: string, newPin: string): Promise<{ success: boolean; error?: string }> => {
    isLoading.value = true
    try {
      const cleanPhone = phone.replace(/\D/g, '')
      const response = await api.post<PinLoginApiResponse>('/auth/pin/reset', {
        phone: cleanPhone,
        code,
        new_pin: newPin,
        new_pin_confirmation: newPin
      })

      if (response.data.success) {
        const apiUser = response.data.user

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

        token.value = response.data.token
        localStorage.setItem('auth_token', response.data.token)
        hasPin.value = true
        needsPinSetup.value = false

        return { success: true }
      }

      return { success: false, error: 'RESET_FAILED' }
    } catch (error: unknown) {
      const axiosError = error as { response?: { status?: number; data?: { message?: string } } }

      if (axiosError.response?.status === 401) {
        return { success: false, error: 'INVALID_CODE' }
      }

      return { success: false, error: axiosError.response?.data?.message || 'RESET_FAILED' }
    } finally {
      isLoading.value = false
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
    // PIN State
    hasPin,
    needsPinSetup,
    pinLockoutMinutes,
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
    checkAuth,
    // PIN Actions
    checkUser,
    loginWithPin,
    setupPin,
    changePin,
    resetPinWithOtp
  }
})
