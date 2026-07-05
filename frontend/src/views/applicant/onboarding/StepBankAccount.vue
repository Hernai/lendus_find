<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useOnboardingStore, useApplicationStore } from '@/stores'
import { AppButton } from '@/components/common'
import BankAccountStepRenderer from '@/components/onboarding/steps/BankAccountStepRenderer.vue'
import type { BankAccountStep } from '@/types/v2/onboardingStep'
import { getErrorMessage } from '@/types/api'
import { logger } from '@/utils/logger'

const log = logger.child('StepBankAccount')

const router = useRouter()
const onboardingStore = useOnboardingStore()
const applicationStore = useApplicationStore()

interface BankAccount {
  type: 'CLABE' | 'CARD'
  bank_code: string
  account_number: string
}

// El renderer exige un `step` tipado; no lo usa en su lógica, pasamos uno mínimo.
const stepConfig: BankAccountStep = {
  id: 'bank_account',
  type: 'bank_account',
  label: 'Cuenta bancaria',
  required: true,
}

const bankAccount = ref<BankAccount | null>(null)
const isSaving = ref(false)
const submitError = ref('')

onMounted(async () => {
  await onboardingStore.init()
  // Pre-llenar si el usuario ya capturó la cuenta (al retroceder y volver).
  const saved = onboardingStore.data.dynamic.bank_account as BankAccount | undefined
  if (saved?.bank_code && saved?.account_number) {
    bankAccount.value = {
      type: saved.type === 'CARD' ? 'CARD' : 'CLABE',
      bank_code: saved.bank_code,
      account_number: saved.account_number,
    }
  }
})

// Persistir localmente para conservar la captura al navegar entre pasos.
watch(bankAccount, (val) => {
  if (val) onboardingStore.setDynamicField('bank_account', val)
}, { deep: true })

const canContinue = computed(
  () => !!bankAccount.value?.bank_code && !!bankAccount.value?.account_number
)

const handleSubmit = async () => {
  submitError.value = ''
  if (!canContinue.value || !bankAccount.value) return

  const appId = applicationStore.currentApplication?.id
  if (!appId || appId === 'null' || appId === 'undefined') {
    submitError.value = 'No se encontró la solicitud. Regresa al inicio e intenta de nuevo.'
    return
  }

  isSaving.value = true
  try {
    // Reutiliza la lógica del onboarding dinámico: borra cuentas previas y crea la
    // nueva vía createBankAccount (holder desde el perfil, account_type CLABE/tarjeta).
    await onboardingStore.persistDynamic('bank_account', 'bank_account', {
      type: bankAccount.value.type,
      bank_code: bankAccount.value.bank_code,
      account_number: bankAccount.value.account_number,
    })
    router.push('/solicitud/paso-8')
  } catch (e: unknown) {
    log.error('Failed to save bank account', { error: e })
    submitError.value = getErrorMessage(e, 'Error al guardar la cuenta bancaria')
  } finally {
    isSaving.value = false
  }
}

const prevStep = () => router.push('/solicitud/paso-7')
</script>

<template>
  <div class="px-4 py-6">
    <div class="max-w-md mx-auto">
      <h1 class="text-2xl font-bold text-gray-900 mb-2">Cuenta bancaria</h1>
      <p class="text-gray-500 mb-6">¿Dónde quieres recibir tu préstamo?</p>

      <!-- Loading state -->
      <div v-if="onboardingStore.isLoading" class="flex justify-center py-8">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600"></div>
      </div>

      <template v-else>
        <div v-if="submitError" class="mb-4 p-3 bg-red-50 border border-red-200 rounded-xl">
          <p class="text-sm text-red-600">{{ submitError }}</p>
        </div>

        <BankAccountStepRenderer :step="stepConfig" v-model="bankAccount" />

        <!-- Sticky Footer -->
        <div class="fixed bottom-0 left-0 right-0 p-3 bg-white border-t">
          <div class="max-w-md mx-auto flex gap-3">
            <AppButton type="button" variant="outline" size="lg" class="flex-1" @click="prevStep">
              Atrás
            </AppButton>
            <AppButton
              type="button"
              variant="primary"
              size="lg"
              class="flex-1"
              :loading="isSaving"
              :disabled="!canContinue"
              @click="handleSubmit"
            >
              Continuar
            </AppButton>
          </div>
        </div>
      </template>
    </div>
  </div>
</template>
