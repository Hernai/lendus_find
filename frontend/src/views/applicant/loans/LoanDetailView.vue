<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useLoanStore } from '@/stores'

const route = useRoute()
const router = useRouter()
const loanStore = useLoanStore()

const loanId = computed(() => String(route.params.id ?? ''))
const loan = computed(() => loanStore.current)

const formatMoney = (n: number) => new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(n)
const formatDate = (d: string | null) => {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

onMounted(() => loanStore.fetchOne(loanId.value))
</script>

<template>
  <div class="min-h-screen bg-gray-50 pb-10">
    <header class="bg-white border-b border-gray-200 px-4 py-3 flex items-center gap-3 sticky top-0 z-10">
      <button type="button" class="p-2 -ml-2 hover:bg-gray-100 rounded-xl" @click="router.back()">
        <svg class="w-6 h-6 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
        </svg>
      </button>
      <h1 class="text-base font-semibold text-gray-900">Detalle de préstamo</h1>
    </header>

    <div v-if="loanStore.isLoading || !loan" class="flex justify-center pt-20">
      <div class="w-10 h-10 border-2 border-primary-600 border-t-transparent rounded-full animate-spin" />
    </div>

    <div v-else class="p-4 space-y-4">
      <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100">
        <p class="text-xs text-gray-500">Monto otorgado</p>
        <p class="text-2xl font-bold text-gray-900">{{ formatMoney(loan.principal_amount) }}</p>
        <div class="grid grid-cols-2 gap-3 mt-3 text-sm">
          <div>
            <p class="text-xs text-gray-500">Total a pagar</p>
            <p class="font-semibold">{{ formatMoney(loan.total_to_pay) }}</p>
          </div>
          <div>
            <p class="text-xs text-gray-500">Saldo</p>
            <p class="font-semibold text-primary-700">{{ formatMoney(loan.outstanding_balance) }}</p>
          </div>
          <div>
            <p class="text-xs text-gray-500">Plazo</p>
            <p class="font-semibold">{{ loan.term_days }} días</p>
          </div>
          <div>
            <p class="text-xs text-gray-500">Tasa anual</p>
            <p class="font-semibold">{{ loan.interest_rate }}%</p>
          </div>
          <div>
            <p class="text-xs text-gray-500">Dispersado</p>
            <p class="font-semibold">{{ formatDate(loan.disbursed_at) }}</p>
          </div>
          <div>
            <p class="text-xs text-gray-500">Vence</p>
            <p class="font-semibold">{{ formatDate(loan.due_date) }}</p>
          </div>
          <div v-if="loan.disbursement_reference" class="col-span-2">
            <p class="text-xs text-gray-500">Referencia STP</p>
            <p class="font-mono text-xs">{{ loan.disbursement_reference }}</p>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100">
        <h2 class="text-sm font-semibold text-gray-900 mb-3">Pagos realizados</h2>
        <div v-if="!loan.payments?.length" class="text-sm text-gray-500">Aún no hay pagos registrados.</div>
        <ul v-else class="divide-y divide-gray-100 -mx-2">
          <li v-for="p in loan.payments" :key="p.id" class="flex justify-between px-2 py-2 text-sm">
            <div>
              <p class="font-medium">{{ formatMoney(p.amount) }}</p>
              <p class="text-xs text-gray-500">{{ p.channel }} · {{ formatDate(p.paid_at) }}</p>
            </div>
            <span class="text-xs px-2 py-0.5 rounded-full bg-green-100 text-green-700 self-center">{{ p.status }}</span>
          </li>
        </ul>
      </div>

      <div v-if="loan.extensions?.length" class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100">
        <h2 class="text-sm font-semibold text-gray-900 mb-3">Prórrogas</h2>
        <ul class="divide-y divide-gray-100 -mx-2">
          <li v-for="e in loan.extensions" :key="e.id" class="flex justify-between px-2 py-2 text-sm">
            <div>
              <p class="font-medium">+{{ e.days_added }} días</p>
              <p class="text-xs text-gray-500">{{ formatDate(e.new_due_date) }} · Costo {{ formatMoney(e.fee_amount) }}</p>
            </div>
            <span class="text-xs px-2 py-0.5 rounded-full self-center" :class="{
              'bg-amber-100 text-amber-700': e.status === 'PENDING',
              'bg-green-100 text-green-700': e.status === 'APPROVED',
              'bg-red-100 text-red-700': e.status === 'REJECTED',
            }">{{ e.status }}</span>
          </li>
        </ul>
      </div>
    </div>
  </div>
</template>
