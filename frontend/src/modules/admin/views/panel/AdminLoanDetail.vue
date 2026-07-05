<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { v2 } from '@/services/v2'
import { useToast } from '@/composables/useToast'
import type { V2Loan } from '@/types/v2/loan'

const route = useRoute()
const router = useRouter()
const toast = useToast()

const loan = ref<V2Loan | null>(null)
const loading = ref(false)

const showPaymentForm = ref(false)
const newPayment = ref({ amount: 0, channel: 'MANUAL', provider_reference: '' })
const submitting = ref(false)

const formatMoney = (n: number) => new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(n)
const formatDate = (d: string | null) => d ? new Date(d).toLocaleString('es-MX') : '—'

const loanId = computed(() => String(route.params.id ?? ''))

const fetch = async () => {
  loading.value = true
  try {
    const res = await v2.staff.loan.get(loanId.value)
    loan.value = res.data ?? null
    if (loan.value) newPayment.value.amount = loan.value.outstanding_balance
  } finally {
    loading.value = false
  }
}

const recordPayment = async () => {
  if (!loan.value || newPayment.value.amount <= 0) return
  submitting.value = true
  try {
    await v2.staff.loan.recordPayment(loan.value.id, {
      amount: newPayment.value.amount,
      channel: newPayment.value.channel,
      provider_reference: newPayment.value.provider_reference || undefined,
    })
    toast.success('Pago registrado')
    showPaymentForm.value = false
    await fetch()
  } catch {
    toast.error('No fue posible registrar el pago')
  } finally {
    submitting.value = false
  }
}

const approveExtension = async (extensionId: string) => {
  if (!loan.value) return
  try {
    await v2.staff.loan.approveExtension(loan.value.id, extensionId)
    toast.success('Prórroga aprobada')
    await fetch()
  } catch {
    toast.error('No fue posible aprobar la prórroga')
  }
}

onMounted(fetch)
</script>

<template>
  <div class="space-y-4">
    <button type="button" class="text-sm text-primary-600 font-medium" @click="router.back()">
      ← Volver
    </button>

    <div v-if="loading || !loan" class="bg-white rounded-xl p-10 text-center">
      <div class="inline-block w-8 h-8 border-2 border-primary-600 border-t-transparent rounded-full animate-spin" />
    </div>

    <template v-else>
      <div class="bg-white rounded-xl border border-gray-100 p-5">
        <h1 class="text-xl font-bold text-gray-900">Préstamo {{ loan.id.slice(0, 8) }}</h1>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mt-4 text-sm">
          <div>
            <p class="text-xs text-gray-500">Principal</p>
            <p class="font-semibold">{{ formatMoney(loan.principal_amount) }}</p>
          </div>
          <div>
            <p class="text-xs text-gray-500">Total a pagar</p>
            <p class="font-semibold">{{ formatMoney(loan.total_to_pay) }}</p>
          </div>
          <div>
            <p class="text-xs text-gray-500">Saldo</p>
            <p class="font-semibold text-primary-700">{{ formatMoney(loan.outstanding_balance) }}</p>
          </div>
          <div>
            <p class="text-xs text-gray-500">Estado</p>
            <p class="font-semibold">{{ loan.status }}</p>
          </div>
          <div>
            <p class="text-xs text-gray-500">Tasa anual</p>
            <p class="font-semibold">{{ loan.interest_rate }}%</p>
          </div>
          <div>
            <p class="text-xs text-gray-500">Plazo</p>
            <p class="font-semibold">{{ loan.term_days }} días</p>
          </div>
          <div>
            <p class="text-xs text-gray-500">Dispersado</p>
            <p class="font-semibold">{{ formatDate(loan.disbursed_at) }}</p>
          </div>
          <div>
            <p class="text-xs text-gray-500">Vence</p>
            <p class="font-semibold">{{ formatDate(loan.due_date) }}</p>
          </div>
        </div>
      </div>

      <!-- Pagos -->
      <div class="bg-white rounded-xl border border-gray-100 p-5">
        <div class="flex items-center justify-between mb-3">
          <h2 class="text-base font-semibold">Pagos</h2>
          <button
            type="button"
            class="px-3 py-1.5 bg-primary-600 text-white text-sm rounded-lg font-medium hover:bg-primary-700"
            @click="showPaymentForm = !showPaymentForm"
          >
            {{ showPaymentForm ? 'Cancelar' : 'Registrar pago manual' }}
          </button>
        </div>

        <div v-if="showPaymentForm" class="bg-gray-50 rounded-lg p-4 mb-4 grid grid-cols-1 sm:grid-cols-4 gap-3">
          <div>
            <label class="block text-xs text-gray-500 mb-1">Monto</label>
            <input
              v-model.number="newPayment.amount"
              type="number"
              step="0.01"
              class="w-full px-2 py-1.5 border border-gray-200 rounded text-sm"
            />
          </div>
          <div>
            <label class="block text-xs text-gray-500 mb-1">Canal</label>
            <select v-model="newPayment.channel" class="w-full px-2 py-1.5 border border-gray-200 rounded text-sm bg-white">
              <option value="MANUAL">Manual</option>
              <option value="CONEKTA">Conekta</option>
              <option value="OPENPAY">OpenPay</option>
              <option value="STP">STP</option>
            </select>
          </div>
          <div class="sm:col-span-2">
            <label class="block text-xs text-gray-500 mb-1">Referencia</label>
            <input
              v-model="newPayment.provider_reference"
              type="text"
              class="w-full px-2 py-1.5 border border-gray-200 rounded text-sm"
            />
          </div>
          <div class="sm:col-span-4 flex justify-end">
            <button
              type="button"
              class="px-3 py-1.5 bg-primary-600 text-white text-sm rounded-lg font-medium disabled:opacity-50"
              :disabled="submitting"
              @click="recordPayment"
            >Guardar</button>
          </div>
        </div>

        <table class="w-full text-sm">
          <thead class="text-left text-xs uppercase text-gray-500">
            <tr>
              <th class="py-2">Fecha</th>
              <th class="py-2">Monto</th>
              <th class="py-2">Canal</th>
              <th class="py-2">Referencia</th>
              <th class="py-2">Estado</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="p in loan.payments" :key="p.id">
              <td class="py-2">{{ formatDate(p.paid_at) }}</td>
              <td class="py-2 font-medium">{{ formatMoney(p.amount) }}</td>
              <td class="py-2">{{ p.channel }}</td>
              <td class="py-2 font-mono text-xs">{{ p.provider_reference || '—' }}</td>
              <td class="py-2">{{ p.status }}</td>
            </tr>
            <tr v-if="!loan.payments?.length">
              <td colspan="5" class="py-6 text-center text-sm text-gray-500">Sin pagos registrados.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Prórrogas -->
      <div v-if="loan.extensions?.length" class="bg-white rounded-xl border border-gray-100 p-5">
        <h2 class="text-base font-semibold mb-3">Prórrogas</h2>
        <table class="w-full text-sm">
          <thead class="text-left text-xs uppercase text-gray-500">
            <tr>
              <th class="py-2">Días</th>
              <th class="py-2">Costo</th>
              <th class="py-2">Nueva fecha</th>
              <th class="py-2">Estado</th>
              <th class="py-2"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="e in loan.extensions" :key="e.id">
              <td class="py-2">+{{ e.days_added }}</td>
              <td class="py-2">{{ formatMoney(e.fee_amount) }}</td>
              <td class="py-2">{{ formatDate(e.new_due_date) }}</td>
              <td class="py-2">{{ e.status }}</td>
              <td class="py-2 text-right">
                <button
                  v-if="e.status === 'PENDING'"
                  type="button"
                  class="text-sm text-primary-600 font-medium"
                  @click="approveExtension(e.id)"
                >Aprobar</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>
  </div>
</template>
