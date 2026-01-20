<script setup lang="ts">
import { formatMoney } from '@/utils/formatters'

interface LoanData {
  requested_amount: number
  monthly_payment: number
  term_months: number
  interest_rate: number
}

defineProps<{
  loan: LoanData
}>()
</script>

<template>
  <div class="grid grid-cols-4 gap-3">
    <div class="bg-gray-50 rounded px-3 py-2">
      <p class="text-xs text-gray-500">Monto</p>
      <p class="text-lg font-bold text-gray-900">{{ formatMoney(loan.requested_amount) }}</p>
    </div>
    <div class="bg-gray-50 rounded px-3 py-2">
      <p class="text-xs text-gray-500">Pago</p>
      <p class="text-lg font-bold text-gray-900">{{ formatMoney(loan.monthly_payment) }}</p>
    </div>
    <div class="bg-gray-50 rounded px-3 py-2">
      <p class="text-xs text-gray-500">Plazo</p>
      <p class="text-lg font-bold text-gray-900">{{ loan.term_months }} meses</p>
    </div>
    <div class="bg-gray-50 rounded px-3 py-2">
      <p class="text-xs text-gray-500">Tasa</p>
      <p class="text-lg font-bold text-gray-900">{{ loan.interest_rate }}%</p>
    </div>
  </div>
</template>
