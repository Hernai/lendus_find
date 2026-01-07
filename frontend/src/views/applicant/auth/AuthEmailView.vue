<script setup lang="ts">
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores'
import { AppButton, AppInput } from '@/components/common'

const router = useRouter()
const authStore = useAuthStore()

const email = ref('')
const error = ref('')

const isValidEmail = computed(() => {
  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
  return emailRegex.test(email.value)
})

const handleSubmit = async () => {
  if (!isValidEmail.value) {
    error.value = 'Ingresa un correo electrónico válido'
    return
  }

  error.value = ''

  try {
    await authStore.sendOtp(email.value, 'email')
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
        <div class="w-16 h-16 bg-blue-100 rounded-2xl flex items-center justify-center mb-6 mx-auto">
          <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
          </svg>
        </div>

        <!-- Title -->
        <h1 class="text-2xl font-bold text-gray-900 text-center mb-2">
          ¿Cuál es tu correo?
        </h1>
        <p class="text-gray-500 text-center mb-8">
          Te enviaremos un código de verificación
        </p>

        <!-- Email Input -->
        <form @submit.prevent="handleSubmit">
          <div class="mb-4">
            <AppInput
              v-model="email"
              type="email"
              label="Correo electrónico"
              placeholder="tu@email.com"
              :error="error"
            />
          </div>

          <!-- Info box -->
          <div class="bg-blue-50 rounded-xl p-4 mb-6">
            <div class="flex gap-3">
              <svg class="w-5 h-5 text-blue-500 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
              </svg>
              <p class="text-sm text-blue-700">
                Revisa tu bandeja de entrada y carpeta de spam
              </p>
            </div>
          </div>

          <!-- Submit Button -->
          <AppButton
            type="submit"
            variant="primary"
            size="lg"
            full-width
            :loading="authStore.isLoading"
            :disabled="!isValidEmail"
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
