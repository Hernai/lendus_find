<script setup lang="ts">
import { ref, computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore, useTenantStore } from '@/stores'
import { AppButton, AppInput } from '@/components/common'
import type { OtpMethod } from '@/types'

const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()
const tenantStore = useTenantStore()

const tenantName = computed(() => tenantStore.name || 'LendusFind')
const method = computed<OtpMethod>(() => (route.query.method as OtpMethod) || 'sms')

const phone = ref('')
const error = ref('')

const isValidPhone = computed(() => {
  // Mexican phone: 10 digits
  const cleaned = phone.value.replace(/\D/g, '')
  return cleaned.length === 10
})

const formatPhone = (value: string) => {
  // Format as (55) 1234-5678
  const cleaned = value.replace(/\D/g, '').slice(0, 10)
  if (cleaned.length <= 2) return cleaned
  if (cleaned.length <= 6) return `(${cleaned.slice(0, 2)}) ${cleaned.slice(2)}`
  return `(${cleaned.slice(0, 2)}) ${cleaned.slice(2, 6)}-${cleaned.slice(6)}`
}

const handlePhoneInput = (value: string | number) => {
  phone.value = formatPhone(String(value))
}

const handleSubmit = async () => {
  if (!isValidPhone.value) {
    error.value = 'Ingresa un número de celular válido (10 dígitos)'
    return
  }

  error.value = ''
  const cleanPhone = phone.value.replace(/\D/g, '')

  try {
    await authStore.sendOtp(cleanPhone, method.value)
    router.push('/auth/verify')
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
          to="/auth"
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
          Te enviaremos un código de 6 dígitos por
          {{ method === 'whatsapp' ? 'WhatsApp' : 'SMS' }}
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
                type="tel"
                :value="phone"
                placeholder="(55) 1234-5678"
                class="flex-1 px-4 py-4 text-lg focus:outline-none"
                @input="handlePhoneInput(($event.target as HTMLInputElement).value)"
              />
            </div>
            <p v-if="error" class="mt-2 text-sm text-red-600">{{ error }}</p>
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
            Enviar código
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
