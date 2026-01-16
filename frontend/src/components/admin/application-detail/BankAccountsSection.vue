<script setup lang="ts">
import { computed } from 'vue'

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
}>()

const emit = defineEmits<{
  (e: 'verify', account: BankAccount): void
  (e: 'unverify', account: BankAccount): void
}>()

const stats = computed(() => ({
  total: props.accounts.length,
  verified: props.accounts.filter(ba => ba.is_verified).length,
}))
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
        <div v-if="canVerify" class="mt-4 pt-3 border-t border-gray-100 flex gap-2">
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
        </div>
      </div>
    </div>
  </div>
</template>
