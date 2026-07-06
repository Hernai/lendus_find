<script setup lang="ts">
/**
 * Card de solo lectura con los detalles del crédito (producto, monto, plazo,
 * frecuencia, tasa, pago, total, destino).
 *
 * Extraído de AdminApplicationDetail.vue (god-component). 100% presentación:
 * recibe `loan` por prop y no emite eventos ni muta estado. El estado central
 * (application.value) permanece en el padre.
 */
import { formatMoney } from '@/utils/formatters'
import type { Application } from '@/modules/admin/views/panel/applicationDetail.types'

defineProps<{
  loan: Application['loan']
}>()

// Etiqueta capitalizada de la frecuencia de pago. Variante hardcodeada usada en
// el detalle del crédito (distinta de formatFrequency() de utils/formatters, que
// devuelve minúsculas y depende de las opciones del backend). Función pura movida
// desde el padre — allí era su único consumidor.
function paymentFrequencyLabel(freq?: string | null): string {
  switch (freq) {
    case 'WEEKLY': case 'SEMANAL': return 'Semanal'
    case 'BIWEEKLY': case 'QUINCENAL': return 'Quincenal'
    case 'MONTHLY': case 'MENSUAL': return 'Mensual'
    case 'SINGLE': return 'Pago único'
    default: return 'Mensual'
  }
}
</script>

<template>
  <div class="border border-gray-200 rounded-lg">
    <div class="bg-gray-50 px-3 py-2 border-b border-gray-200">
      <h3 class="text-sm font-semibold text-gray-900">Detalles del Crédito</h3>
    </div>
    <div class="p-3">
      <div class="grid grid-cols-4 gap-3 text-sm">
        <div>
          <p class="text-xs text-gray-500">Producto</p>
          <p class="font-medium text-gray-900">{{ loan.product_name || '—' }}</p>
        </div>
        <div>
          <p class="text-xs text-gray-500">Monto</p>
          <p class="font-bold text-gray-900">{{ formatMoney(loan.requested_amount) }}</p>
        </div>
        <div>
          <p class="text-xs text-gray-500">Plazo</p>
          <p class="font-medium text-gray-900">
            <template v-if="loan.term_in_days">{{ loan.requested_term_days }} días</template>
            <template v-else>{{ loan.term_months ?? loan.requested_term_months }} meses</template>
          </p>
        </div>
        <div>
          <p class="text-xs text-gray-500">Frecuencia</p>
          <p class="font-medium text-gray-900">{{ paymentFrequencyLabel(loan.payment_frequency) }}</p>
        </div>
        <div>
          <p class="text-xs text-gray-500">Tasa</p>
          <p class="font-medium text-gray-900">{{ loan.interest_rate }}% anual</p>
        </div>
        <div>
          <p class="text-xs text-gray-500">Pago</p>
          <p class="font-bold text-gray-900">{{ formatMoney(loan.monthly_payment) }}</p>
        </div>
        <div>
          <p class="text-xs text-gray-500">Total</p>
          <p class="font-medium text-gray-900">{{ formatMoney(loan.total_to_pay) }}</p>
        </div>
        <div>
          <p class="text-xs text-gray-500">Destino</p>
          <p class="font-medium text-gray-900">{{ loan.purpose_label || loan.purpose || '—' }}</p>
        </div>
      </div>
    </div>
  </div>
</template>
