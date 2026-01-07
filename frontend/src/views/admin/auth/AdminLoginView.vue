<script setup lang="ts">
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore, useTenantStore } from '@/stores'
import { AppButton, AppInput } from '@/components/common'

const router = useRouter()
const authStore = useAuthStore()
const tenantStore = useTenantStore()

const email = ref('')
const password = ref('')
const error = ref('')
const showPassword = ref(false)

const isValidEmail = computed(() => {
  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
  return emailRegex.test(email.value)
})

const isValidForm = computed(() => {
  return isValidEmail.value && password.value.length >= 6
})

const handleSubmit = async () => {
  if (!isValidForm.value) {
    error.value = 'Ingresa un correo y contraseña válidos'
    return
  }

  error.value = ''

  const result = await authStore.loginWithPassword(email.value, password.value)

  if (result.success) {
    router.push('/admin')
  } else {
    switch (result.error) {
      case 'INVALID_CREDENTIALS':
        error.value = 'Correo o contraseña incorrectos'
        break
      case 'USER_NOT_FOUND':
        error.value = 'No existe una cuenta con este correo'
        break
      case 'UNAUTHORIZED_METHOD':
        error.value = 'Tu cuenta no tiene acceso a esta área'
        break
      default:
        error.value = result.error || 'Error al iniciar sesión'
    }
  }
}
</script>

<template>
  <div class="min-h-screen bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900 flex flex-col">
    <div class="flex-1 flex flex-col items-center justify-center px-6 py-8">
      <div class="w-full max-w-md">
        <!-- Logo -->
        <div class="text-center mb-8">
          <div class="inline-flex items-center justify-center w-16 h-16 bg-primary-600 rounded-2xl mb-4">
            <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
            </svg>
          </div>
          <h1 class="text-2xl font-bold text-white">
            {{ tenantStore.name || 'Portal Administrativo' }}
          </h1>
          <p class="text-slate-400 mt-1">
            Acceso para administradores y analistas
          </p>
        </div>

        <!-- Login Card -->
        <div class="bg-white rounded-2xl shadow-xl p-8">
          <h2 class="text-xl font-semibold text-gray-900 mb-6">
            Iniciar sesión
          </h2>

          <form @submit.prevent="handleSubmit">
            <!-- Email -->
            <div class="mb-4">
              <AppInput
                v-model="email"
                type="email"
                label="Correo electrónico"
                placeholder="admin@empresa.com"
                autocomplete="email"
              />
            </div>

            <!-- Password -->
            <div class="mb-4">
              <label class="block text-sm font-medium text-gray-700 mb-1.5">
                Contraseña
              </label>
              <div class="relative">
                <input
                  v-model="password"
                  :type="showPassword ? 'text' : 'password'"
                  placeholder="Tu contraseña"
                  autocomplete="current-password"
                  class="w-full px-4 py-3 border border-gray-300 rounded-xl text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all"
                />
                <button
                  type="button"
                  @click="showPassword = !showPassword"
                  class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                >
                  <svg v-if="showPassword" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                  </svg>
                  <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                  </svg>
                </button>
              </div>
            </div>

            <!-- Error message -->
            <div v-if="error" class="mb-4 p-3 bg-red-50 border border-red-200 rounded-xl">
              <p class="text-sm text-red-600 flex items-center gap-2">
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                </svg>
                {{ error }}
              </p>
            </div>

            <!-- Submit Button -->
            <AppButton
              type="submit"
              variant="primary"
              size="lg"
              full-width
              :loading="authStore.isLoading"
              :disabled="!isValidForm"
            >
              Entrar
            </AppButton>
          </form>

          <!-- Help link -->
          <div class="mt-6 text-center">
            <a href="#" class="text-sm text-primary-600 hover:text-primary-700">
              ¿Olvidaste tu contraseña?
            </a>
          </div>
        </div>

        <!-- Footer -->
        <p class="text-center text-slate-500 text-sm mt-8">
          ¿Necesitas ayuda? Contacta a soporte técnico
        </p>
      </div>
    </div>
  </div>
</template>
