<script setup lang="ts">
import { computed, ref, onUnmounted } from 'vue'
import applicationService, { type NubariumValidationData } from '@/services/v2/application.staff.service'

interface BankAccount {
  id: string
  type: string
  bank_name: string
  bank_code: string
  clabe: string
  account_type: string
  account_type_label?: string
  holder_name: string
  holder_rfc?: string
  is_primary: boolean
  is_own_account: boolean
  is_verified: boolean
  created_at?: string
}

const props = defineProps<{
  accounts: BankAccount[]
  canVerify: boolean
  applicationId: string
}>()

const emit = defineEmits<{
  (e: 'verify', account: BankAccount): void
  (e: 'unverify', account: BankAccount): void
  (e: 'refresh'): void
}>()

const stats = computed(() => ({
  total: props.accounts.length,
  verified: props.accounts.filter(ba => ba.is_verified).length,
}))

// --- Validación CLABE con Nubarium (asíncrona por webhook) ---
const validatingId = ref<string | null>(null)
const showNubariumModal = ref(false)
const nubariumResult = ref<NubariumValidationData | null>(null)
const nubariumError = ref('')
const autoVerified = ref(false)
let nubariumPollCancelled = false

const statusLabel = computed(() => {
  const s = nubariumResult.value?.status
  if (s === 'completed') return 'CLABE válida'
  if (s === 'failed') return 'No válida'
  return 'En proceso'
})
const statusBadgeClass = computed(() => {
  const s = nubariumResult.value?.status
  if (s === 'completed') return 'bg-green-100 text-green-800'
  if (s === 'failed') return 'bg-red-100 text-red-700'
  return 'bg-gray-100 text-gray-600'
})
const resultEntries = computed<[string, unknown][]>(() =>
  Object.entries(nubariumResult.value?.result ?? {})
)
const formatVal = (v: unknown): string =>
  v !== null && typeof v === 'object' ? JSON.stringify(v) : String(v)

const validateWithNubarium = async (account: BankAccount) => {
  validatingId.value = account.id
  nubariumError.value = ''
  nubariumResult.value = null
  autoVerified.value = false
  nubariumPollCancelled = false
  try {
    const res = await applicationService.validateBankAccountClabe(props.applicationId, account.id)
    const id = res.data?.validation_id
    if (!id) throw new Error('sin id')
    await pollNubarium(id, account)
  } catch {
    if (!nubariumPollCancelled) {
      nubariumError.value = 'No se pudo iniciar la validación con Nubarium'
      showNubariumModal.value = true
    }
  } finally {
    validatingId.value = null
  }
}

// Nubarium responde por webhook; sondeamos ~60s (20 intentos × 3s).
const pollNubarium = async (id: string, account: BankAccount) => {
  for (let i = 0; i < 20 && !nubariumPollCancelled; i++) {
    const res = await applicationService.getNubariumValidation(id)
    const data = res.data
    if (data && data.status !== 'pending') {
      if (nubariumPollCancelled) return
      nubariumResult.value = data
      // Si Nubarium confirmó la CLABE, marcamos la cuenta como verificada
      // (verified_by = el analista logueado) y refrescamos la solicitud.
      if (data.status === 'completed' && !account.is_verified) {
        try {
          await applicationService.verifyBankAccount(props.applicationId, account.id)
          autoVerified.value = true
          emit('refresh')
        } catch {
          // No bloquea el modal; el analista puede marcar manualmente.
        }
      }
      showNubariumModal.value = true
      return
    }
    await new Promise((resolve) => setTimeout(resolve, 3000))
  }
  if (!nubariumPollCancelled) {
    nubariumError.value = 'La validación sigue en proceso; revisa más tarde.'
    showNubariumModal.value = true
  }
}

onUnmounted(() => { nubariumPollCancelled = true })
</script>

<template>
  <div>
    <!-- Bank Account Stats -->
    <div class="flex items-center gap-4 mb-4 text-sm text-gray-500">
      <span>Total: <b class="text-gray-900">{{ stats.total }}</b></span>
      <span>Verificadas: <b class="text-gray-900">{{ stats.verified }}</b></span>
    </div>

    <div v-if="accounts.length === 0" class="text-center py-6 text-gray-500 text-sm">
      No hay cuentas bancarias registradas
    </div>

    <div v-else class="space-y-3">
      <div
        v-for="account in accounts"
        :key="account.id"
        class="border border-gray-200 rounded-lg p-4"
      >
        <div class="flex items-start justify-between mb-3">
          <div class="flex items-center gap-2">
            <div class="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center flex-shrink-0">
              <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
              </svg>
            </div>
            <div>
              <div class="flex items-center gap-2">
                <span class="font-semibold text-gray-900">{{ account.bank_name }}</span>
                <span
                  v-if="account.is_primary"
                  class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800"
                >
                  Principal
                </span>
                <span
                  v-if="account.is_verified"
                  class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800"
                >
                  Verificada
                </span>
                <span
                  v-else
                  class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600"
                >
                  Sin verificar
                </span>
              </div>
              <p class="text-xs text-gray-500">{{ account.account_type_label || account.account_type }}</p>
            </div>
          </div>
        </div>

        <div class="space-y-2 text-sm">
          <div class="flex justify-between">
            <span class="text-gray-500">CLABE</span>
            <span class="text-gray-900 font-mono">{{ account.clabe }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-gray-500">Titular</span>
            <span class="text-gray-900 text-right">{{ account.holder_name }}</span>
          </div>
          <div v-if="account.holder_rfc" class="flex justify-between">
            <span class="text-gray-500">RFC</span>
            <span class="text-gray-900 font-mono">{{ account.holder_rfc }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-gray-500">Cuenta propia</span>
            <span class="text-gray-900">{{ account.is_own_account ? 'Sí' : 'No' }}</span>
          </div>
        </div>

        <!-- Verification actions -->
        <div v-if="canVerify" class="mt-4 pt-3 border-t border-gray-100 flex flex-wrap gap-2">
          <button
            v-if="!account.is_verified"
            class="flex-1 px-3 py-1.5 text-sm text-green-700 bg-green-50 hover:bg-green-100 rounded-lg transition-colors font-medium"
            @click="emit('verify', account)"
          >
            Verificar
          </button>
          <button
            v-else
            class="flex-1 px-3 py-1.5 text-sm text-yellow-700 bg-yellow-50 hover:bg-yellow-100 rounded-lg transition-colors font-medium"
            @click="emit('unverify', account)"
          >
            Quitar verificación
          </button>
          <!-- Validar la CLABE contra el banco con Nubarium -->
          <button
            class="flex-1 px-3 py-1.5 text-sm text-primary-700 bg-primary-50 hover:bg-primary-100 rounded-lg transition-colors font-medium disabled:opacity-60 disabled:cursor-not-allowed inline-flex items-center justify-center gap-1.5"
            :disabled="validatingId === account.id"
            @click="validateWithNubarium(account)"
          >
            <svg v-if="validatingId === account.id" class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
            </svg>
            {{ validatingId === account.id ? 'Validando…' : 'Validar con Nubarium' }}
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal: resultado de la validación de CLABE con Nubarium -->
  <Teleport to="body">
    <div
      v-if="showNubariumModal"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
      @click.self="showNubariumModal = false"
    >
      <div class="bg-white rounded-xl shadow-xl w-full max-w-lg max-h-[85vh] flex flex-col overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
          <h3 class="text-lg font-semibold text-gray-900">Validación CLABE con Nubarium</h3>
          <button class="text-gray-400 hover:text-gray-600" @click="showNubariumModal = false">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <div class="p-6 overflow-y-auto space-y-4">
          <div v-if="nubariumError" class="bg-amber-50 border border-amber-200 text-amber-800 rounded-lg p-3 text-sm">
            {{ nubariumError }}
          </div>

          <template v-else-if="nubariumResult">
            <div class="flex items-center gap-2 flex-wrap">
              <span :class="['inline-flex items-center px-2.5 py-1 rounded-full text-sm font-medium', statusBadgeClass]">
                {{ statusLabel }}
              </span>
              <span v-if="nubariumResult.validation_code" class="text-xs text-gray-500 font-mono">
                código: {{ nubariumResult.validation_code }}
              </span>
            </div>

            <p v-if="autoVerified" class="text-sm text-green-700 flex items-center gap-1.5">
              <svg class="w-4 h-4 flex-shrink-0" fill="currentColor" viewBox="0 0 24 24">
                <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
              </svg>
              Cuenta marcada como verificada automáticamente.
            </p>

            <!-- Campos que devolvió Nubarium -->
            <div v-if="resultEntries.length" class="border border-gray-200 rounded-lg divide-y divide-gray-100">
              <div
                v-for="[key, val] in resultEntries"
                :key="key"
                class="flex justify-between gap-4 px-3 py-2 text-sm"
              >
                <span class="text-gray-500 flex-shrink-0">{{ key }}</span>
                <span class="text-gray-900 text-right break-all font-mono">{{ formatVal(val) }}</span>
              </div>
            </div>
            <p v-else class="text-sm text-gray-500">Nubarium no devolvió campos adicionales.</p>

            <!-- Cadena cruda completa -->
            <details class="text-xs">
              <summary class="cursor-pointer text-gray-500 select-none">Ver respuesta cruda de Nubarium</summary>
              <pre class="mt-2 bg-gray-50 border border-gray-200 rounded-lg p-3 overflow-x-auto text-gray-700">{{ JSON.stringify(nubariumResult.result, null, 2) }}</pre>
            </details>
          </template>
        </div>

        <div class="px-6 py-4 border-t border-gray-200 flex justify-end">
          <button
            class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors"
            @click="showNubariumModal = false"
          >
            Cerrar
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>
