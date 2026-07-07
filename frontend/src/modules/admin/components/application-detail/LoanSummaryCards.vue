<script setup lang="ts">
/**
 * Fila de 4 tarjetas resumen. En CRÉDITO: Monto / Pago / Plazo / Tasa. En
 * ARRENDAMIENTO (cuando llega `leaseInfo`): Renta mensual / Desembolso inicial /
 * Plazo / Opción de compra (o Valor del bien) — para no mostrar el valor del bien
 * como si fuera un monto de crédito.
 *
 * 100% presentación: recibe props, cero emits.
 */
import { computed } from 'vue'
import { formatMoney } from '@/utils/formatters'
import type { Application, LeaseInfo } from '@/modules/admin/views/panel/applicationDetail.types'

const props = defineProps<{
  loan: Application['loan']
  leaseInfo?: LeaseInfo | null
}>()

const isLease = computed(
  () => !!props.leaseInfo?.lease?.asset_type || !!props.leaseInfo?.lease?.simulation,
)
const sim = computed(() => props.leaseInfo?.lease?.simulation ?? null)
const money = (v?: number | null) => (v == null ? '—' : formatMoney(v))
</script>

<template>
  <div class="grid grid-cols-4 gap-3">
    <!-- ARRENDAMIENTO -->
    <template v-if="isLease">
      <div class="bg-gray-50 rounded px-3 py-2">
        <p class="text-xs text-gray-500">Renta mensual</p>
        <p class="text-lg font-bold text-gray-900">{{ money(sim?.monthly_rental_with_iva) }}</p>
      </div>
      <div class="bg-gray-50 rounded px-3 py-2">
        <p class="text-xs text-gray-500">Desembolso inicial</p>
        <p class="text-lg font-bold text-gray-900">{{ money(sim?.first_installment?.total) }}</p>
      </div>
      <div class="bg-gray-50 rounded px-3 py-2">
        <p class="text-xs text-gray-500">Plazo</p>
        <p class="text-lg font-bold text-gray-900">
          {{ leaseInfo?.lease?.term_months ?? loan.term_months ?? loan.requested_term_months }} meses
        </p>
      </div>
      <div class="bg-gray-50 rounded px-3 py-2">
        <p class="text-xs text-gray-500">{{ sim?.purchase_option_amount ? 'Opción de compra' : 'Valor del bien' }}</p>
        <p class="text-lg font-bold text-gray-900">
          {{ money(sim?.purchase_option_amount || leaseInfo?.lease?.asset_estimated_value) }}
        </p>
      </div>
    </template>

    <!-- CRÉDITO -->
    <template v-else>
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
    </template>
  </div>
</template>
