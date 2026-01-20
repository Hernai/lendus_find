<script setup lang="ts">
import { ref, computed, nextTick } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore, useTenantStore } from '@/stores'
import { AppButton, AppInput } from '@/components/common'
import { logger } from '@/utils/logger'
import { formatPhoneInput } from '@/utils/formatters'
import type { OtpMethod } from '@/types'

const log = logger.child('AuthPhoneView')

const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()
const tenantStore = useTenantStore()

const tenantName = computed(() => tenantStore.name || 'LendusFind')
const method = computed<OtpMethod>(() => (route.query.method as OtpMethod) || 'sms')

// Get tenant slug from route params or store
const getTenantSlug = (): string | undefined => {
  const routeTenant = route.params.tenant as string
  return routeTenant || tenantStore.slug || undefined
}

// Computed path for back navigation
const backPath = computed(() => {
  const tenantSlug = getTenantSlug()
  return tenantSlug ? `/${tenantSlug}/auth` : '/auth'
})

const phoneInput = ref<HTMLInputElement | null>(null)
const phone = ref('')
const error = ref('')

const digitCount = computed(() => phone.value.replace(/\D/g, '').length)

const isValidPhone = computed(() => {
  // Mexican phone: 10 digits
  return digitCount.value === 10
})

const handlePhoneInput = (event: Event) => {
  const input = event.target as HTMLInputElement
  const formatted = formatPhoneInput(input.value)
  phone.value = formatted

  // Force the input value to match the formatted value
  nextTick(() => {
    if (phoneInput.value) {
      phoneInput.value.value = formatted
    }
  })
}

const forgotPin = computed(() => route.query.forgot_pin === 'true')

const handleSubmit = async () => {
  if (!isValidPhone.value) {
    error.value = 'Ingresa un número de celular válido (10 dígitos)'
    return
  }

  error.value = ''
  const cleanPhone = phone.value.replace(/\D/g, '')
  const tenantSlug = getTenantSlug()

  try {
    // Check if user has PIN and should use PIN login (unless they forgot PIN)
    if (!forgotPin.value) {
      const userCheck = await authStore.checkUser(cleanPhone)

      if (userCheck.exists && userCheck.has_pin) {
        if (userCheck.is_locked) {
          error.value = `Cuenta bloqueada. Intenta en ${userCheck.lockout_minutes} minutos o usa código OTP.`
        } else {
          // Redirect to PIN login
          log.debug('Redirecting to PIN login', { rawPhone: phone.value, cleanPhone, userCheck })

          if (tenantSlug) {
            router.push({
              name: 'tenant-auth-pin-login',
              params: { tenant: tenantSlug },
              query: { phone: cleanPhone, redirect: route.query.redirect as string }
            })
          } else {
            router.push({
              name: 'auth-pin-login',
              query: { phone: cleanPhone, redirect: route.query.redirect as string }
            })
          }
          return
        }
      }
    }

    // No PIN or new user - send OTP
    await authStore.sendOtp(cleanPhone, method.value)
    if (tenantSlug) {
      router.push(`/${tenantSlug}/auth/verify`)
    } else {
      router.push('/auth/verify')
    }
  } catch (e) {
    error.value = 'Error al enviar el código. Intenta de nuevo.'
  }
}
</script>

<template>
  <div class="min-h-screen bg-gray-50 flex flex-col">
    <div class="flex-1 flex flex-col px-6 py-8">
      <div class="mx-auto w-full max-w-md">
        <!-- Back button -->
        <router-link
          :to="backPath"
          class="inline-flex items-center text-gray-500 hover:text-gray-700 mb-6"
        >
          <svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
          </svg>
          Atrás
        </router-link>

        <!-- Icon -->
        <div class="w-16 h-16 bg-primary-100 rounded-2xl flex items-center justify-center mb-6 mx-auto">
          <svg class="w-8 h-8 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" />
          </svg>
        </div>

        <!-- Title -->
        <h1 class="text-2xl font-bold text-gray-900 text-center mb-2">
          ¿Cuál es tu número?
        </h1>
        <p class="text-gray-500 text-center mb-8">
          Ingresa tu número de celular para continuar
        </p>

        <!-- Phone Input -->
        <form @submit.prevent="handleSubmit">
          <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">
              Número de celular
            </label>
            <div class="flex border-2 rounded-xl overflow-hidden transition-colors"
                 :class="error ? 'border-red-300' : 'border-gray-200 focus-within:border-primary-500'">
              <div class="bg-gray-50 px-4 flex items-center border-r gap-2">
                <span class="text-lg">🇲🇽</span>
                <span class="text-gray-600 font-medium">+52</span>
              </div>
              <input
                ref="phoneInput"
                type="tel"
                :value="phone"
                placeholder="(55) 1234-5678"
                class="flex-1 px-4 py-4 text-lg focus:outline-none"
                @input="handlePhoneInput"
              />
            </div>
            <p v-if="error" class="mt-2 text-sm text-red-600">{{ error }}</p>
            <p v-else-if="digitCount > 0 && digitCount < 10" class="mt-2 text-sm text-gray-500">
              {{ digitCount }}/10 dígitos
            </p>
            <p v-else-if="digitCount === 10" class="mt-2 text-sm text-green-600">
              ✓ Número válido
            </p>
          </div>

          <!-- Submit Button -->
          <AppButton
            type="submit"
            variant="primary"
            size="lg"
            full-width
            :loading="authStore.isLoading"
            :disabled="!isValidPhone"
          >
            Continuar
          </AppButton>
        </form>

        <!-- Terms -->
        <p class="text-xs text-gray-400 text-center mt-6">
          Al continuar aceptas nuestros
          <a href="#" class="text-primary-600 hover:underline">Términos</a> y
          <a href="#" class="text-primary-600 hover:underline">Aviso de Privacidad</a>
        </p>
      </div>
    </div>
  </div>
</template>
