<script setup lang="ts">
import { AppButton } from '@/components/common'

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

defineProps<{
  accounts: BankAccount[]
  canVerify: boolean
}>()

const emit = defineEmits<{
  (e: 'verify', account: BankAccount): void
  (e: 'unverify', account: BankAccount): void
}>()

const formatClabe = (clabe: string): string => {
  if (!clabe) return '-'
  // Format: XXXX XXXX XXXX XXXX XX
  return clabe.replace(/(\d{4})(?=\d)/g, '$1 ')
}

const formatDateTime = (dateStr: string) => {
  return new Date(dateStr).toLocaleString('es-MX', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}
</script>

<template>
  <div class="border border-gray-200 rounded-lg">
    <div class="bg-gray-50 px-3 py-2 border-b border-gray-200">
      <h3 class="text-sm font-semibold text-gray-900">Cuentas Bancarias</h3>
    </div>
    <div class="divide-y divide-gray-100">
      <div v-if="accounts.length === 0" class="p-4 text-center text-gray-500 text-sm">
        No hay cuentas bancarias registradas
      </div>
      <div
        v-for="account in accounts"
        :key="account.id"
        class="p-3"
      >
        <div class="flex items-start justify-between">
          <div class="flex-1">
            <div class="flex items-center gap-2 mb-1">
              <span class="font-medium text-gray-900">{{ account.bank_name }}</span>
              <span
                v-if="account.is_primary"
                class="px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-700"
              >
                Principal
              </span>
              <span
                :class="[
                  'px-2 py-0.5 rounded-full text-xs font-medium',
                  account.is_verified
                    ? 'bg-green-100 text-green-700'
                    : 'bg-gray-100 text-gray-600',
                ]"
              >
                {{ account.is_verified ? 'Verificada' : 'Pendiente' }}
              </span>
            </div>
            <div class="text-sm text-gray-600 space-y-1">
              <div class="flex items-center gap-4">
                <span class="text-gray-500">CLABE:</span>
                <span class="font-mono">{{ formatClabe(account.clabe) }}</span>
              </div>
              <div class="flex items-center gap-4">
                <span class="text-gray-500">Titular:</span>
                <span>{{ account.holder_name }}</span>
                <span v-if="!account.is_own_account" class="text-xs text-orange-600">
                  (Cuenta de tercero)
                </span>
              </div>
              <div v-if="account.holder_rfc" class="flex items-center gap-4">
                <span class="text-gray-500">RFC:</span>
                <span class="font-mono">{{ account.holder_rfc }}</span>
              </div>
              <div class="flex items-center gap-4">
                <span class="text-gray-500">Tipo:</span>
                <span>{{ account.account_type_label || account.account_type }}</span>
              </div>
            </div>
            <div v-if="account.created_at" class="text-xs text-gray-400 mt-2">
              Registrada el {{ formatDateTime(account.created_at) }}
            </div>
          </div>
          <div class="flex items-center gap-2">
            <AppButton
              v-if="canVerify && !account.is_verified"
              variant="outline"
              size="sm"
              @click="emit('verify', account)"
            >
              Verificar
            </AppButton>
            <AppButton
              v-if="canVerify && account.is_verified"
              variant="ghost"
              size="sm"
              @click="emit('unverify', account)"
            >
              Quitar Verificación
            </AppButton>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
