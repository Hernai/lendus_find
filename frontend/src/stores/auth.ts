import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { api } from '@/services/api'
import type { User, OtpMethod, SendOtpResponse, VerifyOtpResponse } from '@/types'
import { initializeEcho, disconnectEcho } from '@/plugins/echo'
import { logger } from '@/utils/logger'

const authLogger = logger.child('Auth')

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

interface UserPermissions {
  canViewAllApplications: boolean
  canReviewDocuments: boolean
  canVerifyReferences: boolean
  canChangeApplicationStatus: boolean
  canApproveRejectApplications: boolean
  canAssignApplications: boolean
  canManageProducts: boolean
  canManageUsers: boolean
  canViewReports: boolean
  canConfigureTenant: boolean
}

interface PasswordLoginApiResponse {
  success: boolean
  token: string
  user: {
    id: string
    name: string
    email: string
    role: string  // SUPERVISOR, ANALYST, ADMIN, SUPER_ADMIN
    is_staff: boolean
    permissions: UserPermissions
  }
}

interface MeApiResponse {
  user: {
    id: string
    phone: string | null
    email: string | null
    type: string
    is_admin: boolean
    is_staff: boolean
    has_pin?: boolean
    applicant?: unknown
    permissions?: UserPermissions
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

  // Applicant state
  const hasApplicant = ref(false)

  // Permissions state (for staff users)
  const permissions = ref<UserPermissions | null>(null)

  // Super admin tenant switching
  const selectedTenantId = ref<string | null>(localStorage.getItem('selected_tenant_id'))

  // Getters
  const isAuthenticated = computed(() => !!token.value && !!user.value)
  const isApplicant = computed(() => user.value?.role === 'APPLICANT')
  const isSupervisor = computed(() => user.value?.role === 'SUPERVISOR')
  const isAnalyst = computed(() => user.value?.role === 'ANALYST')
  const isAdmin = computed(() => ['ADMIN', 'SUPER_ADMIN'].includes(user.value?.role || ''))
  const isSuperAdmin = computed(() => user.value?.role === 'SUPER_ADMIN')
  const isStaff = computed(() => ['SUPERVISOR', 'ANALYST', 'ADMIN', 'SUPER_ADMIN'].includes(user.value?.role || ''))

  // Actions for tenant switching (super admin only)
  const setSelectedTenant = (tenantId: string | null) => {
    selectedTenantId.value = tenantId
    if (tenantId) {
      localStorage.setItem('selected_tenant_id', tenantId)
    } else {
      localStorage.removeItem('selected_tenant_id')
    }
  }

  const clearSelectedTenant = () => {
    selectedTenantId.value = null
    localStorage.removeItem('selected_tenant_id')
  }

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
          authLogger.debug('Dev OTP Code', { code: response.data.code })
        }

        return {
          success: true,
          expires_at: otpExpiresAt.value.toISOString(),
          method
        }
      }

      throw new Error('Failed to send OTP')
    } catch (error) {
      authLogger.error('Failed to send OTP', error)
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

        // Check if user changed (different user_id)
        const previousUserId = localStorage.getItem('current_user_id')
        if (previousUserId && previousUserId !== apiUser.id) {
          authLogger.info('User changed, clearing onboarding cache')
          clearOnboardingCache()
        }

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
        localStorage.setItem('current_user_id', apiUser.id)

        // Set PIN state (only for phone-based authentication, not email)
        const isPhoneAuth = otpMethod.value === 'sms' || otpMethod.value === 'whatsapp'
        hasPin.value = apiUser.has_pin ?? false
        needsPinSetup.value = isPhoneAuth && !apiUser.has_pin

        // Clear OTP state
        otpDestination.value = null
        otpMethod.value = null
        otpExpiresAt.value = null

        return {
          success: true,
          token: response.data.token,
          user: mappedUser,
          needsPinSetup: isPhoneAuth && !apiUser.has_pin
        }
      }

      return { success: false, error: 'INVALID_CODE' }
    } catch (error: unknown) {
      authLogger.error('Failed to verify OTP', error)

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

  const clearOnboardingCache = () => {
    // Clear all onboarding-related localStorage when user changes
    localStorage.removeItem('onboarding_draft')
    localStorage.removeItem('current_application_id')
    localStorage.removeItem('pending_application')

    // Clear old application progress data (app_progress_*)
    const keysToRemove: string[] = []
    for (let i = 0; i < localStorage.length; i++) {
      const key = localStorage.key(i)
      if (key?.startsWith('app_progress_')) {
        keysToRemove.push(key)
      }
    }
    keysToRemove.forEach(key => localStorage.removeItem(key))

    authLogger.debug('Cleared onboarding cache')
  }

  const logout = async () => {
    try {
      if (token.value) {
        await api.post('/auth/logout')
      }
    } catch (error) {
      authLogger.error('Logout API error', error)
    } finally {
      user.value = null
      token.value = null
      permissions.value = null
      localStorage.removeItem('auth_token')
      localStorage.removeItem('current_user_id')
      clearOnboardingCache()
      clearSelectedTenant()
      otpDestination.value = null
      otpMethod.value = null
      otpExpiresAt.value = null

      // Desconectar WebSocket
      disconnectEcho()
    }
  }

  const checkAuth = async (targetPath?: string) => {
    if (!token.value) return false

    try {
      const response = await api.get<MeApiResponse>('/me')
      const apiUser = response.data.user

      // Map backend user to frontend User type
      // For staff users, use the backend type directly
      const userRole = apiUser.is_staff
        ? apiUser.type as User['role']
        : mapUserType(apiUser.type, apiUser.is_admin)

      // Check if user changed (different user_id)
      const previousUserId = localStorage.getItem('current_user_id')
      if (previousUserId && previousUserId !== apiUser.id) {
        authLogger.info('User changed on checkAuth, clearing onboarding cache')
        clearOnboardingCache()
      }

      user.value = {
        id: apiUser.id,
        tenant_id: '',
        phone: apiUser.phone || '',
        email: apiUser.email,
        role: userRole,
        phone_verified_at: apiUser.phone ? new Date().toISOString() : null,
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString()
      }

      // Save current user ID
      localStorage.setItem('current_user_id', apiUser.id)

      // Store permissions for staff users
      if (apiUser.permissions) {
        permissions.value = apiUser.permissions
      } else {
        permissions.value = null
      }

      // Set applicant state (only for applicants)
      if (!apiUser.is_staff) {
        hasApplicant.value = !!apiUser.applicant
      }

      // Set PIN state (only for applicants with phone, not email-only users)
      if (!apiUser.is_staff) {
        const hasPhone = !!apiUser.phone
        hasPin.value = apiUser.has_pin ?? false
        needsPinSetup.value = hasPhone && !apiUser.has_pin
      }

      // Check if user has access to target route
      const pathToCheck = targetPath || window.location.pathname
      const isAdminRoute = pathToCheck.startsWith('/admin')

      if (isAdminRoute && !apiUser.is_staff) {
        // User doesn't have staff access
        return false
      }

      return true
    } catch {
      // Token is invalid, clear auth state
      user.value = null
      token.value = null
      permissions.value = null
      localStorage.removeItem('auth_token')
      return false
    }
  }

  // PIN-related actions

  const checkUser = async (phone: string): Promise<CheckUserApiResponse> => {
    isLoading.value = true
    try {
      const cleanPhone = phone.replace(/\D/g, '')
      authLogger.debug('checkUser called', { rawPhone: phone.slice(0, 4) + '***', cleanPhone: cleanPhone.slice(0, 4) + '***' })

      const response = await api.post<CheckUserApiResponse>('/auth/check-user', { phone: cleanPhone })

      authLogger.debug('checkUser response', { exists: response.data.exists, hasPin: response.data.has_pin })

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
      authLogger.debug('loginWithPin called', { phonePrefix: cleanPhone.slice(0, 4) + '***' })

      const response = await api.post<PinLoginApiResponse>('/auth/pin/login', { phone: cleanPhone, pin })

      authLogger.debug('loginWithPin response', { success: response.data.success })

      if (response.data.success) {
        const apiUser = response.data.user

        authLogger.debug('loginWithPin user', { userId: apiUser.id, type: apiUser.type })

        // Check if user changed (different user_id)
        const previousUserId = localStorage.getItem('current_user_id')
        if (previousUserId && previousUserId !== apiUser.id) {
          authLogger.info('User changed, clearing onboarding cache')
          clearOnboardingCache()
        }

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

        authLogger.debug('loginWithPin user set in store', { userId: user.value?.id })

        token.value = response.data.token
        localStorage.setItem('auth_token', response.data.token)
        localStorage.setItem('current_user_id', apiUser.id)
        hasPin.value = true
        needsPinSetup.value = false

        // Inicializar WebSocket
        initializeEcho(response.data.token)

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

  const loginWithPassword = async (email: string, password: string): Promise<{ success: boolean; error?: string }> => {
    isLoading.value = true
    try {
      const response = await api.post<PasswordLoginApiResponse>('/admin/auth/login', { email, password })

      if (response.data.success) {
        const apiUser = response.data.user

        // Check if user changed (different user_id)
        const previousUserId = localStorage.getItem('current_user_id')
        if (previousUserId && previousUserId !== apiUser.id) {
          authLogger.info('User changed, clearing onboarding cache')
          clearOnboardingCache()
        }

        user.value = {
          id: apiUser.id,
          tenant_id: '',
          phone: '',  // Staff users may not have phone
          email: apiUser.email,
          role: apiUser.role as User['role'],
          phone_verified_at: null,
          created_at: new Date().toISOString(),
          updated_at: new Date().toISOString()
        }

        // Store permissions for staff users
        if (apiUser.permissions) {
          permissions.value = apiUser.permissions
        }

        token.value = response.data.token
        localStorage.setItem('auth_token', response.data.token)
        localStorage.setItem('current_user_id', apiUser.id)

        // Inicializar WebSocket
        initializeEcho(response.data.token)

        return { success: true }
      }

      return { success: false, error: 'LOGIN_FAILED' }
    } catch (error: unknown) {
      const axiosError = error as { response?: { status?: number; data?: { message?: string } } }

      if (axiosError.response?.status === 401) {
        return { success: false, error: 'INVALID_CREDENTIALS' }
      }

      if (axiosError.response?.status === 403) {
        return { success: false, error: 'UNAUTHORIZED_METHOD' }
      }

      if (axiosError.response?.status === 404) {
        return { success: false, error: 'USER_NOT_FOUND' }
      }

      return { success: false, error: axiosError.response?.data?.message || 'LOGIN_FAILED' }
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

        // Check if user changed (different user_id)
        const previousUserId = localStorage.getItem('current_user_id')
        if (previousUserId && previousUserId !== apiUser.id) {
          authLogger.info('User changed, clearing onboarding cache')
          clearOnboardingCache()
        }

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
        localStorage.setItem('current_user_id', apiUser.id)
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

  // Inicializar WebSocket si ya hay un token al cargar el store
  if (token.value) {
    authLogger.debug('Initializing Echo with existing token')
    initializeEcho(token.value)
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
    // Applicant State
    hasApplicant,
    // Super Admin Tenant Switching
    selectedTenantId,
    // Getters
    isAuthenticated,
    isApplicant,
    isAnalyst,
    isAdmin,
    isSuperAdmin,
    isSupervisor,
    isStaff,
    // Permissions
    permissions,
    // Actions
    sendOtp,
    verifyOtp,
    resendOtp,
    logout,
    checkAuth,
    clearOnboardingCache,
    // Tenant Switching Actions
    setSelectedTenant,
    clearSelectedTenant,
    // PIN Actions
    checkUser,
    loginWithPin,
    setupPin,
    changePin,
    resetPinWithOtp,
    // Password Actions
    loginWithPassword
  }
})
