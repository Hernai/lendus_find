<script setup lang="ts">
import { ref, computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore, useTenantStore } from '@/stores'
import { AppButton } from '@/components/common'

const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()
const tenantStore = useTenantStore()

const phone = computed(() => route.query.phone as string || '')
const pin = ref('')
const error = ref('')
const attemptsRemaining = ref<number | null>(null)

// Get tenant slug from route params or store
const getTenantSlug = (): string | undefined => {
  const routeTenant = route.params.tenant as string
  return routeTenant || tenantStore.slug || undefined
}

const maskedPhone = computed(() => {
  if (!phone.value) return ''
  return `+52 ${phone.value.slice(0, 2)} **** ${phone.value.slice(-4)}`
})

const handlePinInput = (digit: string) => {
  if (pin.value.length < 4) {
    pin.value += digit
    error.value = ''
    attemptsRemaining.value = null
  }

  if (pin.value.length === 4) {
    handleSubmit()
  }
}

const handleDelete = () => {
  pin.value = pin.value.slice(0, -1)
}

const handleSubmit = async () => {
  if (pin.value.length !== 4) return

  console.log('🔐 PIN Login - Phone from query:', phone.value)
  console.log('🔐 PIN Login - Route query:', route.query)
  console.log('🔐 PIN Login - Current user before login:', authStore.user)

  const result = await authStore.loginWithPin(phone.value, pin.value)

  console.log('🔐 PIN Login result:', result)
  console.log('🔐 Current user after login:', authStore.user)

  if (result.success) {
    // Check auth state to see if user has completed registration
    await authStore.checkAuth()

    const redirect = route.query.redirect as string
    const tenantSlug = getTenantSlug()

    if (redirect) {
      // Explicit redirect (e.g., from protected route)
      router.push(redirect)
    } else if (!authStore.hasApplicant) {
      // User is new, redirect to onboarding
      if (tenantSlug) {
        router.push(`/${tenantSlug}/solicitud`)
      } else {
        router.push('/solicitud')
      }
    } else {
      // User exists, redirect to dashboard
      if (tenantSlug) {
        router.push(`/${tenantSlug}/dashboard`)
      } else {
        router.push('/dashboard')
      }
    }
  } else {
    const tenantSlug = getTenantSlug()
    if (result.error === 'INVALID_PIN') {
      attemptsRemaining.value = result.attemptsRemaining ?? null
      error.value = attemptsRemaining.value !== null
        ? `NIP incorrecto. ${attemptsRemaining.value} intentos restantes.`
        : 'NIP incorrecto'
    } else if (result.error === 'ACCOUNT_LOCKED') {
      error.value = `Cuenta bloqueada. Intenta en ${authStore.pinLockoutMinutes} minutos.`
    } else if (result.error === 'NO_PIN_SET') {
      if (tenantSlug) {
        router.push({ name: 'tenant-auth-phone', params: { tenant: tenantSlug }, query: { phone: phone.value } })
      } else {
        router.push({ name: 'auth-phone', query: { phone: phone.value } })
      }
    } else {
      error.value = 'Error al iniciar sesión'
    }
    pin.value = ''
  }
}

const handleForgotPin = async () => {
  // Redirect to OTP flow to reset PIN
  const tenantSlug = getTenantSlug()
  if (tenantSlug) {
    router.push({
      name: 'tenant-auth-phone',
      params: { tenant: tenantSlug },
      query: {
        phone: phone.value,
        forgot_pin: 'true'
      }
    })
  } else {
    router.push({
      name: 'auth-phone',
      query: {
        phone: phone.value,
        forgot_pin: 'true'
      }
    })
  }
}

const handleUseOtp = () => {
  const tenantSlug = getTenantSlug()
  if (tenantSlug) {
    router.push({
      name: 'tenant-auth-phone',
      params: { tenant: tenantSlug },
      query: { phone: phone.value }
    })
  } else {
    router.push({
      name: 'auth-phone',
      query: { phone: phone.value }
    })
  }
}

const goBack = () => {
  const tenantSlug = getTenantSlug()
  if (tenantSlug) {
    router.push(`/${tenantSlug}/auth`)
  } else {
    router.push('/auth')
  }
}
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
        <div class="w-16 h-16 bg-primary-100 rounded-2xl flex items-center justify-center mb-6 mx-auto">
          <svg class="w-8 h-8 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
          </svg>
        </div>

        <!-- Title -->
        <h1 class="text-2xl font-bold text-gray-900 text-center mb-2">
          Ingresa tu NIP
        </h1>
        <p class="text-gray-500 text-center mb-1">Para</p>
        <p class="text-primary-600 font-medium text-center mb-8">
          {{ maskedPhone }}
        </p>

        <!-- PIN Display -->
        <div class="flex justify-center gap-3 mb-8">
          <div
            v-for="i in 4"
            :key="i"
            class="w-14 h-14 rounded-xl border-2 flex items-center justify-center transition-all"
            :class="{
              'border-primary-500 bg-primary-50': pin.length === i - 1,
              'border-gray-300': pin.length < i - 1,
              'border-gray-900 bg-gray-100': pin.length >= i,
              'border-red-500': error && pin.length === 0
            }"
          >
            <div
              v-if="pin.length >= i"
              class="w-3 h-3 rounded-full bg-gray-900"
            />
          </div>
        </div>

        <!-- Error -->
        <p v-if="error" class="text-red-500 text-sm text-center mb-4">
          {{ error }}
        </p>

        <!-- Numpad -->
        <div class="grid grid-cols-3 gap-3 max-w-xs mx-auto">
          <button
            v-for="digit in ['1', '2', '3', '4', '5', '6', '7', '8', '9']"
            :key="digit"
            class="h-16 text-2xl font-semibold text-gray-900 bg-white rounded-xl shadow-sm hover:bg-gray-50 active:bg-gray-100 transition-colors"
            :disabled="authStore.isLoading"
            @click="handlePinInput(digit)"
          >
            {{ digit }}
          </button>

          <button
            class="h-16 text-sm font-medium text-gray-500 hover:bg-gray-100 rounded-xl transition-colors"
            @click="handleForgotPin"
          >
            Olvidé NIP
          </button>

          <button
            class="h-16 text-2xl font-semibold text-gray-900 bg-white rounded-xl shadow-sm hover:bg-gray-50 active:bg-gray-100 transition-colors"
            :disabled="authStore.isLoading"
            @click="handlePinInput('0')"
          >
            0
          </button>

          <button
            class="h-16 flex items-center justify-center text-gray-600 hover:bg-gray-100 rounded-xl transition-colors"
            @click="handleDelete"
          >
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2M3 12l6.414 6.414a2 2 0 001.414.586H19a2 2 0 002-2V7a2 2 0 00-2-2h-8.172a2 2 0 00-1.414.586L3 12z" />
            </svg>
          </button>
        </div>

        <!-- Loading indicator -->
        <div v-if="authStore.isLoading" class="mt-8 flex justify-center">
          <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600" />
        </div>

        <!-- Alternative login -->
        <div class="mt-8 text-center">
          <p class="text-sm text-gray-500 mb-2">¿Prefieres usar código SMS?</p>
          <button
            class="text-sm font-medium text-primary-600 hover:text-primary-700"
            @click="handleUseOtp"
          >
            Iniciar con código OTP
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
