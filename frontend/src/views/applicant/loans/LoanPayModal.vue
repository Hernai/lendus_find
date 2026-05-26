<script setup lang="ts">
import { ref, watch } from 'vue'
import { useLoanStore } from '@/stores'
import { useToast } from '@/composables/useToast'
import type { V2Loan } from '@/types/v2/loan'

const props = defineProps<{ open: boolean; loan: V2Loan }>()
const emit = defineEmits<{ close: []; success: [] }>()

const loanStore = useLoanStore()
const toast = useToast()

const amount = ref(0)
const channel = ref<'CONEKTA' | 'OPENPAY' | 'STP'>('CONEKTA')
const submitting = ref(false)
const paymentUrl = ref<string | null>(null)
const reference = ref<string | null>(null)

watch(() => props.open, (v) => {
  if (v) {
    amount.value = props.loan.outstanding_balance
    paymentUrl.value = null
    reference.value = null
  }
})

const formatMoney = (n: number) => new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(n)

const submit = async () => {
  submitting.value = true
  try {
    const res = await loanStore.pay(props.loan.id, amount.value, channel.value)
    paymentUrl.value = res?.payment_url ?? null
    reference.value = res?.reference ?? null
    toast.success('Pago iniciado')
    emit('success')
  } catch {
    toast.error('No fue posible iniciar el pago')
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <Teleport to="body">
    <Transition name="fade">
      <div v-if="open" class="fixed inset-0 z-50 bg-black/60 flex items-end sm:items-center justify-center p-0 sm:p-4" @click.self="emit('close')">
        <div class="bg-white w-full sm:max-w-md rounded-t-2xl sm:rounded-2xl p-5 space-y-4 shadow-xl">
          <div class="flex items-center justify-between">
            <h3 class="text-lg font-semibold text-gray-900">Pagar préstamo</h3>
            <button type="button" class="text-gray-400 hover:text-gray-600" @click="emit('close')">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <template v-if="!paymentUrl && !reference">
            <div class="bg-primary-50 rounded-xl p-4 text-center">
              <p class="text-xs text-primary-700">Saldo pendiente</p>
              <p class="text-2xl font-bold text-primary-700">{{ formatMoney(loan.outstanding_balance) }}</p>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Monto a pagar</label>
              <input
                v-model.number="amount"
                type="number"
                step="0.01"
                :min="0"
                :max="loan.outstanding_balance"
                class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-100 focus:border-primary-500 font-mono text-sm"
              />
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Método</label>
              <select
                v-model="channel"
                class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-100 focus:border-primary-500 bg-white"
              >
                <option value="CONEKTA">Tarjeta (Conekta)</option>
                <option value="OPENPAY">Tarjeta (OpenPay)</option>
                <option value="STP">Transferencia SPEI (STP)</option>
              </select>
            </div>

            <button
              type="button"
              class="w-full py-3 bg-primary-600 text-white rounded-xl font-semibold disabled:opacity-50"
              :disabled="submitting || amount <= 0"
              @click="submit"
            >
              <span v-if="submitting">Procesando...</span>
              <span v-else>Continuar al pago</span>
            </button>
          </template>

          <template v-else>
            <div class="bg-green-50 rounded-xl p-4 text-sm space-y-1 text-green-800">
              <p class="font-semibold">Pago iniciado</p>
              <p v-if="paymentUrl">
                Completa el pago en
                <a :href="paymentUrl" target="_blank" class="underline font-semibold">{{ paymentUrl }}</a>
              </p>
              <p v-if="reference">
                Referencia: <span class="font-mono">{{ reference }}</span>
              </p>
            </div>
            <button
              type="button"
              class="w-full py-3 bg-primary-600 text-white rounded-xl font-semibold"
              @click="emit('close')"
            >
              Cerrar
            </button>
          </template>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.2s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
