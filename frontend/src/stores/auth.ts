import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { v2 } from '@/services/v2'
import type { User, OtpMethod, SendOtpResponse, VerifyOtpResponse } from '@/types'
import { initializeEcho, disconnectEcho } from '@/plugins/echo'
import { logger } from '@/utils/logger'
import { storage, STORAGE_KEYS } from '@/utils/storage'

const authLogger = logger.child('Auth')

/**
 * Legacy storage migration helper.
 * Reads from new storage utility first, falls back to old localStorage key.
 * TODO: Remove this after migration period (all users have migrated to new storage keys)
 */
function getWithLegacyFallback<T>(storageKey: string, legacyKey: string): T | null {
  const value = storage.get<T>(storageKey)
  if (value !== null) return value

  // Fallback to legacy localStorage key
  const legacyValue = localStorage.getItem(legacyKey)
  if (legacyValue) {
    // Migrate to new storage
    try {
      const parsed = JSON.parse(legacyValue) as T
      storage.set(storageKey, parsed)
      localStorage.removeItem(legacyKey)
      return parsed
    } catch {
      // Simple string value
      storage.set(storageKey, legacyValue as T)
      localStorage.removeItem(legacyKey)
      return legacyValue as T
    }
  }
  return null
}

interface CheckUserApiResponse {
  exists: boolean
  has_pin: boolean
  is_locked: boolean
  lockout_minutes: number
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
  // Use storage utility consistently - no fallback to raw localStorage
  const token = ref<string | null>(storage.get<string>(STORAGE_KEYS.AUTH_TOKEN))
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

  // Cache control for checkAuth
  const authChecked = ref(false)
  const authCheckPromise = ref<Promise<boolean> | null>(null)

  // Super admin tenant switching
  // Use storage utility consistently - no fallback to raw localStorage
  const selectedTenantId = ref<string | null>(storage.get<string>(STORAGE_KEYS.CURRENT_TENANT_ID))

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
      storage.set(STORAGE_KEYS.CURRENT_TENANT_ID, tenantId)
    } else {
      storage.remove(STORAGE_KEYS.CURRENT_TENANT_ID)
    }
  }

  const clearSelectedTenant = () => {
    selectedTenantId.value = null
    storage.remove(STORAGE_KEYS.CURRENT_TENANT_ID)
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
      const identifierType = method === 'email' ? 'email' : 'phone'
      const identifierValue = method === 'email' ? destination : cleanPhone
      const channel = method === 'whatsapp' ? 'whatsapp' : method === 'email' ? 'email' : 'sms'

      const response = await v2.applicant.auth.requestOtp({
        type: identifierType,
        identifier: identifierValue,
        channel: channel,
      })

      if (response.success || response.data) {
        otpDestination.value = identifierValue
        otpMethod.value = method
        otpExpiresAt.value = response.data?.expires_at
          ? new Date(response.data.expires_at)
          : new Date(Date.now() + 10 * 60 * 1000)

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
      const identifierType = otpMethod.value === 'email' ? 'email' : 'phone'

      const response = await v2.applicant.auth.verifyOtp({
        type: identifierType,
        identifier: otpDestination.value,
        code,
      })

      // Handle both response formats:
      // - Standard V2: { success: true, data: { token, user } }
      // - Legacy/Direct: { success: true, token, user }
      const rawResponse = response as unknown as {
        success: boolean
        data?: { token: string; user: import('@/types/v2').V2ApplicantUser }
        token?: string
        user?: import('@/types/v2').V2ApplicantUser
      }

      const authData = rawResponse.data || (rawResponse.token && rawResponse.user ? { token: rawResponse.token, user: rawResponse.user } : null)

      if (response.success && authData) {
        // Cast to V2ApplicantUser since we're using applicant auth
        const apiUser = authData.user as import('@/types/v2').V2ApplicantUser

        // Check if user changed (different user_id)
        const previousUserId = getWithLegacyFallback<string>(STORAGE_KEYS.CURRENT_USER_ID, 'current_user_id')
        if (previousUserId && previousUserId !== apiUser.id) {
          authLogger.info('User changed, clearing onboarding cache')
          clearOnboardingCache()
        }

        // Map V2 user to frontend User type
        const mappedUser: User = {
          id: apiUser.id,
          tenant_id: apiUser.tenant_id,
          phone: apiUser.phone || '',
          email: apiUser.email || null,
          role: 'APPLICANT',
          phone_verified_at: apiUser.phone ? new Date().toISOString() : null,
          created_at: apiUser.created_at,
          updated_at: new Date().toISOString()
        }

        user.value = mappedUser
        token.value = authData.token
        storage.set(STORAGE_KEYS.AUTH_TOKEN, authData.token)
        storage.set(STORAGE_KEYS.CURRENT_USER_ID, apiUser.id)
        storage.set(STORAGE_KEYS.CURRENT_USER_TYPE, 'applicant')

        // Set PIN state (only for phone-based authentication, not email)
        const isPhoneAuth = otpMethod.value === 'sms' || otpMethod.value === 'whatsapp'
        // V2 doesn't return has_pin directly, check person existence
        hasPin.value = false // Will be updated on checkUser
        needsPinSetup.value = isPhoneAuth

        // Clear OTP state
        otpDestination.value = null
        otpMethod.value = null
        otpExpiresAt.value = null

        // Initialize WebSocket
        initializeEcho(authData.token)

        return {
          success: true,
          token: authData.token,
          user: mappedUser,
          needsPinSetup: isPhoneAuth
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
    // Clear all onboarding-related storage when user changes
    storage.remove(STORAGE_KEYS.ONBOARDING_DATA)
    storage.remove(STORAGE_KEYS.CURRENT_APPLICATION_ID)

    // Also clear legacy localStorage keys for backwards compatibility
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
        await v2.applicant.auth.logout()
      }
    } catch (error) {
      authLogger.error('Logout API error', error)
    } finally {
      user.value = null
      token.value = null
      permissions.value = null
      authChecked.value = false
      authCheckPromise.value = null

      // Clear storage (new manager + legacy)
      storage.remove(STORAGE_KEYS.AUTH_TOKEN)
      storage.remove(STORAGE_KEYS.CURRENT_USER_ID)
      storage.remove(STORAGE_KEYS.CURRENT_USER_TYPE)
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

  const checkAuth = async (targetPath?: string, force = false) => {
    if (!token.value) return false

    // If already checked and user exists, skip (unless forced)
    if (!force && authChecked.value && user.value) {
      // Just check route access
      const pathToCheck = targetPath || window.location.pathname
      const isAdminRoute = pathToCheck.startsWith('/admin')
      if (isAdminRoute && !isStaff.value) {
        return false
      }
      return true
    }

    // If already checking, wait for existing promise
    if (authCheckPromise.value) {
      authLogger.debug('checkAuth already in progress, waiting...')
      return authCheckPromise.value
    }

    const checkPromise = (async () => {
      try {
        // Determine user type from storage or try both endpoints
        const userType = storage.get<string>(STORAGE_KEYS.CURRENT_USER_TYPE)
      let apiUser: {
        id: string
        tenant_id?: string
        phone?: string | null
        email?: string | null
        type?: string
        role?: string
        is_admin?: boolean
        is_staff: boolean
        has_pin?: boolean
        applicant?: unknown
        permissions?: UserPermissions
      }

      if (userType === 'staff') {
        // Try staff endpoint
        const response = await v2.staff.auth.getMe()
        if (!response.success || !response.data) {
          throw new Error('Failed to get staff user')
        }
        const staffUser = response.data.user
        apiUser = {
          id: staffUser.id,
          email: staffUser.email,
          type: staffUser.role,
          role: staffUser.role,
          is_staff: true,
          permissions: staffUser.permissions ? (
            // Handle both array format (legacy) and object format (V2)
            Array.isArray(staffUser.permissions) ? {
              canViewAllApplications: staffUser.permissions.includes('canViewAllApplications'),
              canReviewDocuments: staffUser.permissions.includes('canReviewDocuments'),
              canVerifyReferences: staffUser.permissions.includes('canVerifyReferences'),
              canChangeApplicationStatus: staffUser.permissions.includes('canChangeApplicationStatus'),
              canApproveRejectApplications: staffUser.permissions.includes('canApproveRejectApplications'),
              canAssignApplications: staffUser.permissions.includes('canAssignApplications'),
              canManageProducts: staffUser.permissions.includes('canManageProducts'),
              canManageUsers: staffUser.permissions.includes('canManageUsers'),
              canViewReports: staffUser.permissions.includes('canViewReports'),
              canConfigureTenant: staffUser.permissions.includes('canConfigureTenant'),
            } : staffUser.permissions as unknown as UserPermissions
          ) : undefined
        }
      } else {
        // Try applicant endpoint (default)
        const response = await v2.applicant.auth.getMe()
        if (!response.success || !response.data) {
          throw new Error('Failed to get applicant user')
        }
        const appUser = response.data.user
        apiUser = {
          id: appUser.id,
          tenant_id: appUser.tenant_id,
          phone: appUser.phone,
          email: appUser.email,
          type: 'APPLICANT',
          is_admin: false,
          is_staff: false,
          has_pin: appUser.has_pin,
          applicant: appUser.person_id ? { id: appUser.person_id } : null
        }
      }

      // Map backend user to frontend User type
      // For staff users, use the backend type directly
      const userRole = apiUser.is_staff
        ? (apiUser.type || apiUser.role) as User['role']
        : mapUserType(apiUser.type || 'APPLICANT', apiUser.is_admin || false)

      // Check if user changed (different user_id)
      const previousUserId = getWithLegacyFallback<string>(STORAGE_KEYS.CURRENT_USER_ID, 'current_user_id')
      if (previousUserId && previousUserId !== apiUser.id) {
        authLogger.info('User changed on checkAuth, clearing onboarding cache')
        clearOnboardingCache()
      }

      user.value = {
        id: apiUser.id,
        tenant_id: apiUser.tenant_id || '',
        phone: apiUser.phone || '',
        email: apiUser.email || null,
        role: userRole,
        phone_verified_at: apiUser.phone ? new Date().toISOString() : null,
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString()
      }

      // Save current user ID
      storage.set(STORAGE_KEYS.CURRENT_USER_ID, apiUser.id)

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

      // Mark as checked
      authChecked.value = true

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
      authChecked.value = false
      storage.remove(STORAGE_KEYS.AUTH_TOKEN)
      storage.remove(STORAGE_KEYS.CURRENT_USER_TYPE)
      localStorage.removeItem('auth_token')
      return false
    } finally {
      authCheckPromise.value = null
    }
    })()

    authCheckPromise.value = checkPromise
    return checkPromise
  }

  // PIN-related actions

  const checkUser = async (phone: string): Promise<CheckUserApiResponse> => {
    isLoading.value = true
    try {
      const cleanPhone = phone.replace(/\D/g, '')
      authLogger.debug('checkUser called', { rawPhone: phone.slice(0, 4) + '***', cleanPhone: cleanPhone.slice(0, 4) + '***' })

      const response = await v2.applicant.auth.checkUser({ type: 'phone', identifier: cleanPhone })

      if (response.success && response.data) {
        const checkData = response.data
        authLogger.debug('checkUser V2 response', { exists: checkData.exists, hasPin: checkData.has_pin })

        hasPin.value = checkData.has_pin
        // V2 doesn't return lockout_minutes in checkUser, only in login errors
        pinLockoutMinutes.value = 0

        return {
          exists: checkData.exists,
          has_pin: checkData.has_pin,
          is_locked: false,
          lockout_minutes: 0
        }
      }

      return { exists: false, has_pin: false, is_locked: false, lockout_minutes: 0 }
    } finally {
      isLoading.value = false
    }
  }

  const loginWithPin = async (phone: string, pin: string): Promise<{ success: boolean; error?: string; attemptsRemaining?: number }> => {
    isLoading.value = true
    try {
      const cleanPhone = phone.replace(/\D/g, '')
      authLogger.debug('loginWithPin called', { phonePrefix: cleanPhone.slice(0, 4) + '***' })

      const response = await v2.applicant.auth.loginWithPin({ type: 'phone', identifier: cleanPhone, pin })

      if (response.success && response.data) {
        const authData = response.data
        // Cast to V2ApplicantUser since we're using applicant auth
        const apiUser = authData.user as import('@/types/v2').V2ApplicantUser

        authLogger.debug('loginWithPin V2 user', { userId: apiUser.id })

        // Check if user changed (different user_id)
        const previousUserId = getWithLegacyFallback<string>(STORAGE_KEYS.CURRENT_USER_ID, 'current_user_id')
        if (previousUserId && previousUserId !== apiUser.id) {
          authLogger.info('User changed, clearing onboarding cache')
          clearOnboardingCache()
        }

        user.value = {
          id: apiUser.id,
          tenant_id: apiUser.tenant_id,
          phone: apiUser.phone || '',
          email: apiUser.email || null,
          role: 'APPLICANT',
          phone_verified_at: apiUser.phone ? new Date().toISOString() : null,
          created_at: apiUser.created_at,
          updated_at: new Date().toISOString()
        }

        authLogger.debug('loginWithPin user set in store', { userId: user.value?.id })

        token.value = authData.token
        storage.set(STORAGE_KEYS.AUTH_TOKEN, authData.token)
        storage.set(STORAGE_KEYS.CURRENT_USER_ID, apiUser.id)
        storage.set(STORAGE_KEYS.CURRENT_USER_TYPE, 'applicant')
        hasPin.value = true
        needsPinSetup.value = false

        // Initialize WebSocket
        initializeEcho(authData.token)

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
      const response = await v2.applicant.auth.setupPin({
        pin,
        pin_confirmation: pin
      })

      if (response.success) {
        hasPin.value = true
        needsPinSetup.value = false
        return { success: true }
      }

      return { success: false, error: response.error || 'SETUP_FAILED' }
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
      const response = await v2.applicant.auth.changePin({
        current_pin: currentPin,
        new_pin: newPin,
        new_pin_confirmation: newPin
      })

      return { success: response.success }
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
      const response = await v2.staff.auth.login({ email, password })

      // V2 response wraps data in { success, data: { token, user } }
      if (response.success && response.data) {
        const apiUser = response.data.user as {
          id: string
          email: string
          role: string
          tenant_id?: string
          is_active: boolean
          profile?: { full_name: string; phone?: string }
          permissions: Record<string, boolean>
          created_at?: string
        }
        const apiToken = response.data.token

        // Check if user changed (different user_id)
        const previousUserId = getWithLegacyFallback<string>(STORAGE_KEYS.CURRENT_USER_ID, 'current_user_id')
        if (previousUserId && previousUserId !== apiUser.id) {
          authLogger.info('User changed, clearing onboarding cache')
          clearOnboardingCache()
        }

        user.value = {
          id: apiUser.id,
          tenant_id: apiUser.tenant_id || '',
          phone: apiUser.profile?.phone || '',
          email: apiUser.email,
          role: apiUser.role as User['role'],
          phone_verified_at: null,
          created_at: apiUser.created_at || new Date().toISOString(),
          updated_at: new Date().toISOString()
        }

        // Store permissions for staff users (V2 returns object, map to our format)
        if (apiUser.permissions) {
          permissions.value = apiUser.permissions as unknown as UserPermissions
        }

        token.value = apiToken
        storage.set(STORAGE_KEYS.AUTH_TOKEN, apiToken)
        storage.set(STORAGE_KEYS.CURRENT_USER_ID, apiUser.id)
        storage.set(STORAGE_KEYS.CURRENT_USER_TYPE, 'staff')

        // Initialize WebSocket
        initializeEcho(apiToken)

        return { success: true }
      }

      return { success: false, error: response.message || 'LOGIN_FAILED' }
    } catch (error: unknown) {
      const axiosError = error as { response?: { status?: number; data?: { message?: string; error?: string } } }

      if (axiosError.response?.status === 401) {
        return { success: false, error: 'INVALID_CREDENTIALS' }
      }

      if (axiosError.response?.status === 403) {
        return { success: false, error: axiosError.response.data?.error || 'UNAUTHORIZED_METHOD' }
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
      const response = await v2.applicant.auth.resetPinWithOtp({
        type: 'phone',
        identifier: cleanPhone,
        code,
        new_pin: newPin,
        new_pin_confirmation: newPin
      })

      if (response.success && response.data) {
        const apiUser = response.data.user

        // Check if user changed (different user_id)
        const previousUserId = getWithLegacyFallback<string>(STORAGE_KEYS.CURRENT_USER_ID, 'current_user_id')
        if (previousUserId && previousUserId !== apiUser.id) {
          authLogger.info('User changed, clearing onboarding cache')
          clearOnboardingCache()
        }

        user.value = {
          id: apiUser.id,
          tenant_id: apiUser.tenant_id || '',
          phone: apiUser.phone || '',
          email: apiUser.email || null,
          role: 'APPLICANT',
          phone_verified_at: apiUser.phone ? new Date().toISOString() : null,
          created_at: apiUser.created_at,
          updated_at: new Date().toISOString()
        }

        token.value = response.data.token
        storage.set(STORAGE_KEYS.AUTH_TOKEN, response.data.token)
        storage.set(STORAGE_KEYS.CURRENT_USER_ID, apiUser.id)
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
