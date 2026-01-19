<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useApplicantStore, useProfileStore } from '@/stores'

const emit = defineEmits<{
  close: []
  saved: []
}>()

// Use profileStore for CLABE validation (V2 API)
const profileStore = useProfileStore()
// Keep applicantStore for creating bank accounts (V1 API handles multiple accounts)
const applicantStore = useApplicantStore()

const isSubmitting = ref(false)
const error = ref<string | null>(null)

// Form state
const clabe = ref('')
const bankName = ref('')
const accountType = ref('DEBITO')
const holderName = ref('')
const holderRfc = ref('')
const isOwnAccount = ref(true)
const isPrimary = ref(false)

// CLABE validation state
const isValidatingClabe = ref(false)
const clabeError = ref<string | null>(null)
const clabeValid = ref(false)
const bankNameAutoDetected = ref(false)

const accountTypeOptions = [
  { value: 'DEBITO', label: 'Débito' },
  { value: 'NOMINA', label: 'Nómina' },
  { value: 'AHORRO', label: 'Ahorro' },
  { value: 'CHEQUES', label: 'Cheques' },
  { value: 'INVERSION', label: 'Inversión' },
  { value: 'OTRO', label: 'Otro' }
]

const canSubmit = computed(() => {
  return (
    clabe.value.length === 18 &&
    clabeValid.value &&
    bankName.value.trim().length > 0 &&
    holderName.value.trim().length > 0 &&
    !isSubmitting.value
  )
})

// Format CLABE input (only digits, max 18)
const formatClabe = (value: string) => {
  return value.replace(/\D/g, '').slice(0, 18)
}

const handleClabeInput = (event: Event) => {
  const input = event.target as HTMLInputElement
  clabe.value = formatClabe(input.value)
}

// Validate CLABE when it's 18 digits
watch(clabe, async (newValue) => {
  if (newValue.length === 18) {
    isValidatingClabe.value = true
    clabeError.value = null
    clabeValid.value = false
    bankName.value = ''
    bankNameAutoDetected.value = false

    try {
      const result = await profileStore.validateClabe(newValue)
      if (result?.is_valid) {
        clabeValid.value = true
        if (result.bank_name) {
          bankName.value = result.bank_name
          bankNameAutoDetected.value = true
        }
      } else {
        clabeError.value = 'CLABE inválida'
      }
    } catch (e) {
      clabeError.value = 'Error al validar CLABE'
    } finally {
      isValidatingClabe.value = false
    }
  } else {
    clabeValid.value = false
    bankName.value = ''
    bankNameAutoDetected.value = false
    clabeError.value = null
  }
})

const handleSubmit = async () => {
  if (!canSubmit.value) return

  isSubmitting.value = true
  error.value = null

  try {
    await applicantStore.createBankAccount({
      clabe: clabe.value,
      bank_name: bankName.value,
      account_type: accountType.value as 'DEBITO' | 'NOMINA' | 'AHORRO' | 'CHEQUES' | 'INVERSION' | 'OTRO',
      holder_name: holderName.value.toUpperCase(),
      holder_rfc: holderRfc.value.toUpperCase() || undefined,
      is_own_account: isOwnAccount.value,
      is_primary: isPrimary.value
    })
    emit('saved')
  } catch (e: unknown) {
    console.error('Failed to create bank account:', e)
    error.value = (e as Error)?.message || 'Error al agregar la cuenta'
  } finally {
    isSubmitting.value = false
  }
}
</script>

<template>
  <Teleport to="body">
    <Transition name="modal">
      <div class="fixed inset-0 z-50">
        <!-- Backdrop (only visible on desktop) -->
        <div
          class="absolute inset-0 bg-black/50 hidden sm:block"
          @click="emit('close')"
        />

        <!-- Modal Content - Full screen on mobile, centered modal on desktop -->
        <div class="absolute inset-0 sm:inset-auto sm:top-1/2 sm:left-1/2 sm:-translate-x-1/2 sm:-translate-y-1/2 bg-white sm:w-full sm:max-w-lg sm:rounded-2xl sm:max-h-[90vh] flex flex-col sm:shadow-xl">
          <!-- Header -->
          <div class="flex-shrink-0 bg-white px-4 sm:px-6 py-3 sm:py-4 border-b border-gray-200 flex items-center gap-3">
            <button
              type="button"
              class="p-2 -ml-2 hover:bg-gray-100 active:bg-gray-200 rounded-xl transition-colors sm:hidden"
              @click="emit('close')"
            >
              <svg class="w-6 h-6 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
            <h2 class="text-lg font-semibold text-gray-900 flex-1 text-center sm:text-left">Agregar Cuenta Bancaria</h2>
            <button
              type="button"
              class="p-2 -mr-2 hover:bg-gray-100 active:bg-gray-200 rounded-xl transition-colors hidden sm:block"
              @click="emit('close')"
            >
              <svg class="w-6 h-6 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
            <!-- Spacer for mobile to center title -->
            <div class="w-10 sm:hidden" />
          </div>

          <!-- Scrollable Body -->
          <form class="flex-1 overflow-y-auto p-4 sm:p-6 space-y-5" @submit.prevent="handleSubmit">
            <!-- Error Message -->
            <div v-if="error" class="p-4 bg-red-50 border border-red-200 rounded-xl text-red-600 text-sm">
              {{ error }}
            </div>

            <!-- CLABE -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">
                CLABE Interbancaria *
              </label>
              <div class="relative">
                <input
                  type="text"
                  :value="clabe"
                  @input="handleClabeInput"
                  inputmode="numeric"
                  autocomplete="off"
                  placeholder="18 dígitos"
                  class="w-full px-4 py-3.5 border rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500 font-mono text-lg tracking-wide"
                  :class="{
                    'border-gray-300': !clabeError && !clabeValid,
                    'border-red-500': clabeError,
                    'border-green-500': clabeValid
                  }"
                  maxlength="18"
                />
                <div v-if="isValidatingClabe" class="absolute right-3 top-1/2 -translate-y-1/2">
                  <div class="w-5 h-5 border-2 border-primary-600 border-t-transparent rounded-full animate-spin" />
                </div>
                <div v-else-if="clabeValid" class="absolute right-3 top-1/2 -translate-y-1/2">
                  <svg class="w-5 h-5 text-green-500" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
                  </svg>
                </div>
              </div>
              <p v-if="clabeError" class="mt-1 text-sm text-red-500">{{ clabeError }}</p>
              <p class="mt-1 text-xs text-gray-500">{{ clabe.length }}/18 dígitos</p>
            </div>

            <!-- Bank Name -->
            <div v-if="clabeValid">
              <label class="block text-sm font-medium text-gray-700 mb-1">
                Banco *
              </label>
              <!-- Auto-detected bank (readonly) -->
              <div v-if="bankNameAutoDetected" class="relative">
                <div class="px-4 py-3.5 bg-gray-50 border border-green-300 rounded-xl text-gray-900 font-medium min-h-[50px] flex items-center">
                  {{ bankName }}
                </div>
                <div class="absolute right-3 top-1/2 -translate-y-1/2">
                  <svg class="w-5 h-5 text-green-500" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
                  </svg>
                </div>
              </div>
              <!-- Manual bank entry -->
              <input
                v-else
                v-model="bankName"
                type="text"
                autocomplete="off"
                placeholder="Nombre del banco"
                class="w-full px-4 py-3.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
              />
              <p v-if="!bankNameAutoDetected" class="mt-1 text-xs text-gray-500">
                No se detectó el banco automáticamente. Ingresa el nombre manualmente.
              </p>
            </div>

            <!-- Account Type -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">
                Tipo de Cuenta
              </label>
              <div class="relative">
                <select
                  v-model="accountType"
                  class="w-full px-4 py-3.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500 bg-white appearance-none cursor-pointer text-base"
                >
                  <option
                    v-for="option in accountTypeOptions"
                    :key="option.value"
                    :value="option.value"
                  >
                    {{ option.label }}
                  </option>
                </select>
                <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                  <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                  </svg>
                </div>
              </div>
            </div>

            <!-- Holder Name -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">
                Nombre del Titular *
              </label>
              <input
                v-model="holderName"
                type="text"
                autocomplete="name"
                autocapitalize="characters"
                placeholder="Como aparece en el estado de cuenta"
                class="w-full px-4 py-3.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500 uppercase text-base"
              />
            </div>

            <!-- Holder RFC (optional) -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">
                RFC del Titular <span class="text-gray-400">(opcional)</span>
              </label>
              <input
                v-model="holderRfc"
                type="text"
                autocomplete="off"
                autocapitalize="characters"
                placeholder="13 caracteres"
                class="w-full px-4 py-3.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500 uppercase text-base"
                maxlength="13"
              />
            </div>

            <!-- Checkboxes -->
            <div class="space-y-4">
              <label class="flex items-center gap-3 cursor-pointer py-1 -mx-2 px-2 active:bg-gray-50 rounded-lg">
                <input
                  v-model="isOwnAccount"
                  type="checkbox"
                  class="w-6 h-6 rounded border-gray-300 text-primary-600 focus:ring-primary-500 flex-shrink-0"
                />
                <span class="text-base text-gray-700">Esta cuenta está a mi nombre</span>
              </label>

              <label class="flex items-center gap-3 cursor-pointer py-1 -mx-2 px-2 active:bg-gray-50 rounded-lg">
                <input
                  v-model="isPrimary"
                  type="checkbox"
                  class="w-6 h-6 rounded border-gray-300 text-primary-600 focus:ring-primary-500 flex-shrink-0"
                />
                <span class="text-base text-gray-700">Usar como cuenta principal para depósitos</span>
              </label>
            </div>
          </form>

          <!-- Footer -->
          <div class="flex-shrink-0 bg-white px-4 sm:px-6 py-4 border-t border-gray-200 safe-area-bottom">
            <button
              type="submit"
              :disabled="!canSubmit"
              class="w-full py-4 bg-primary-600 text-white rounded-xl font-semibold text-base disabled:opacity-50 disabled:cursor-not-allowed hover:bg-primary-700 active:bg-primary-800 transition-colors flex items-center justify-center gap-2 min-h-[52px]"
              @click="handleSubmit"
            >
              <div v-if="isSubmitting" class="w-5 h-5 border-2 border-white border-t-transparent rounded-full animate-spin" />
              <span>{{ isSubmitting ? 'Guardando...' : 'Agregar Cuenta' }}</span>
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.modal-enter-active,
.modal-leave-active {
  transition: opacity 0.2s ease;
}

.modal-enter-from,
.modal-leave-to {
  opacity: 0;
}

.safe-area-bottom {
  padding-bottom: max(1rem, env(safe-area-inset-bottom));
}
</style>