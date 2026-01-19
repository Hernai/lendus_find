<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore, useTenantStore } from '@/stores'
import { AppButton, AppOtpInput } from '@/components/common'
import { logger } from '@/utils/logger'

const log = logger.child('AuthOtpView')

const router = useRouter()
const authStore = useAuthStore()
const tenantStore = useTenantStore()

const otpCode = ref('')
const error = ref('')
const countdown = ref(0)
const isNavigating = ref(false) // Flag to prevent redirect during navigation
let countdownInterval: ReturnType<typeof setInterval> | null = null

// Get tenant slug from route params or store
const getTenantSlug = (): string | undefined => {
  const routeTenant = router.currentRoute.value.params.tenant as string
  return routeTenant || tenantStore.slug || undefined
}

// Computed
const destination = computed(() => authStore.otpDestination || '')
const method = computed(() => authStore.otpMethod || 'sms')

const maskedDestination = computed(() => {
  if (method.value === 'email') {
    const parts = destination.value.split('@')
    const local = parts[0] || ''
    const domain = parts[1] || ''
    return `${local.slice(0, 2)}***@${domain}`
  }
  // Phone
  return `+52 ${destination.value.slice(0, 2)} **** ${destination.value.slice(-4)}`
})

const canResend = computed(() => countdown.value === 0)

// Methods
const startCountdown = () => {
  countdown.value = 60
  countdownInterval = setInterval(() => {
    countdown.value--
    if (countdown.value <= 0 && countdownInterval) {
      clearInterval(countdownInterval)
    }
  }, 1000)
}

const handleOtpComplete = async (code: string) => {
  error.value = ''

  try {
    const result = await authStore.verifyOtp(code)

    if (result.success) {
      // Set flag to prevent any redirect from onMounted/watchers
      isNavigating.value = true

      const tenantSlug = getTenantSlug()
      const redirect = router.currentRoute.value.query.redirect as string

      // Check if user needs to setup PIN
      if (result.needsPinSetup) {
        // Redirect to PIN setup, preserving the original redirect
        if (tenantSlug) {
          await router.push({
            name: 'tenant-auth-pin-setup',
            params: { tenant: tenantSlug },
            query: redirect ? { redirect } : undefined
          })
        } else {
          await router.push({
            name: 'auth-pin-setup',
            query: redirect ? { redirect } : undefined
          })
        }
        return
      }

      // Check if user has completed registration (has applicant)
      await authStore.checkAuth()

      // Redirect based on context
      if (redirect) {
        // Explicit redirect (e.g., from protected route)
        await router.push(redirect)
      } else if (!authStore.hasApplicant) {
        // User is new, redirect to onboarding
        if (tenantSlug) {
          await router.push(`/${tenantSlug}/solicitud`)
        } else {
          await router.push('/solicitud')
        }
      } else {
        // User exists, redirect to dashboard
        if (tenantSlug) {
          await router.push(`/${tenantSlug}/dashboard`)
        } else {
          await router.push('/dashboard')
        }
      }
    } else {
      if (result.error === 'OTP_EXPIRED') {
        error.value = 'El código ha expirado. Solicita uno nuevo.'
      } else if (result.error === 'MAX_ATTEMPTS_EXCEEDED') {
        error.value = 'Demasiados intentos. Solicita un nuevo código.'
      } else {
        error.value = `Código incorrecto. ${result.attempts_remaining} intentos restantes.`
      }
      otpCode.value = ''
    }
  } catch (e) {
    log.error('OTP verification error', { error: e })
    error.value = 'Error al verificar el código. Intenta de nuevo.'
    otpCode.value = ''
  }
}

const handleResend = async (resendMethod: 'sms' | 'whatsapp' | 'email') => {
  if (!canResend.value) return

  error.value = ''

  try {
    await authStore.resendOtp(resendMethod)
    startCountdown()
  } catch (e) {
    error.value = 'Error al reenviar el código.'
  }
}

const goBack = () => {
  router.back()
}

// Lifecycle
onMounted(() => {
  // Skip redirect if we're already navigating after successful verification
  if (isNavigating.value) {
    return
  }
  if (!destination.value) {
    // Redirect to the correct auth route based on tenant
    const tenantSlug = getTenantSlug()
    if (tenantSlug) {
      router.push(`/${tenantSlug}/auth`)
    } else {
      router.push('/auth')
    }
    return
  }
  startCountdown()
})

onUnmounted(() => {
  if (countdownInterval) {
    clearInterval(countdownInterval)
  }
})
</script>

<template>
  <div class="min-h-screen bg-gray-50 flex flex-col">
    <div class="flex-1 flex flex-col px-6 py-8">
      <div class="mx-auto w-full max-w-md">
        <!-- Back button -->
        <button
          class="inline-flex items-center text-gray-500 hover:text-gray-700 mb-6"
          @click="goBack"
        >
          <svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
          </svg>
          Atrás
        </button>

        <!-- Icon -->
        <div class="w-16 h-16 bg-green-100 rounded-2xl flex items-center justify-center mb-6 mx-auto">
          <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
          </svg>
        </div>

        <!-- Title -->
        <h1 class="text-2xl font-bold text-gray-900 text-center mb-2">
          Verifica tu código
        </h1>
        <p class="text-gray-500 text-center mb-1">Enviado a</p>
        <p class="text-primary-600 font-medium text-center mb-8">
          {{ maskedDestination }}
        </p>

        <!-- OTP Input -->
        <AppOtpInput
          v-model="otpCode"
          :error="error"
          :disabled="authStore.isLoading"
          @complete="handleOtpComplete"
        />

        <!-- Timer / Resend -->
        <div class="mt-6 text-center">
          <p v-if="countdown > 0" class="text-sm text-gray-500">
            El código expira en
            <span class="font-semibold text-primary-600">
              {{ Math.floor(countdown / 60) }}:{{ String(countdown % 60).padStart(2, '0') }}
            </span>
          </p>

          <div v-else class="space-y-3">
            <p class="text-sm text-gray-500">¿No recibiste el código?</p>
            <div class="flex justify-center gap-2">
              <button
                class="px-4 py-2 text-sm font-medium text-primary-600 hover:bg-primary-50 rounded-lg transition-colors"
                @click="handleResend('sms')"
              >
                Reenviar SMS
              </button>
              <button
                class="px-4 py-2 text-sm font-medium text-green-600 hover:bg-green-50 rounded-lg transition-colors"
                @click="handleResend('whatsapp')"
              >
                WhatsApp
              </button>
              <button
                class="px-4 py-2 text-sm font-medium text-blue-600 hover:bg-blue-50 rounded-lg transition-colors"
                @click="handleResend('email')"
              >
                Email
              </button>
            </div>
          </div>
        </div>

        <!-- Loading indicator -->
        <div v-if="authStore.isLoading" class="mt-8 flex justify-center">
          <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600" />
        </div>
      </div>
    </div>
  </div>
</template>
