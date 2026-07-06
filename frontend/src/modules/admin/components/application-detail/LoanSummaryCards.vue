<script setup lang="ts">
/**
 * Fila de 4 tarjetas resumen del crédito (Monto / Pago / Plazo / Tasa).
 *
 * Extraído de AdminApplicationDetail.vue. 100% presentación: recibe `loan` por
 * prop, cero emits.
 */
import { formatMoney } from '@/utils/formatters'
import type { Application } from '@/modules/admin/views/panel/applicationDetail.types'

defineProps<{
  loan: Application['loan']
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
      <p class="text-lg font-bold text-gray-900">
        <template v-if="loan.term_in_days">{{ loan.requested_term_days }} días</template>
        <template v-else>{{ loan.term_months ?? loan.requested_term_months }} meses</template>
      </p>
    </div>
    <div class="bg-gray-50 rounded px-3 py-2">
      <p class="text-xs text-gray-500">Tasa</p>
      <p class="text-lg font-bold text-gray-900">{{ loan.interest_rate }}%</p>
    </div>
  </div>
</template>
