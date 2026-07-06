<script setup lang="ts">
/**
 * Modal "Crear Contraoferta": modifica monto/plazo/tasa/frecuencia + razón, con
 * resumen de amortización en vivo. Extraído de AdminApplicationDetail.vue.
 *
 * El objeto de formulario (counterOffer) y el cálculo (counterOfferCalculation) son
 * 100% locales del modal; se pre-llenan desde el crédito (prop loan) al abrir
 * (watch sobre show, equivalente a openCounterOfferModal). El envío real
 * (submitCounterOffer) queda en el padre, que recibe el objeto por @submit. Cierre
 * vía v-model:show.
 */
import { ref, computed, watch } from 'vue'
import { AppButton } from '@/components/common'
import { formatMoney } from '@/utils/formatters'
import type { Application } from '@/modules/admin/views/panel/applicationDetail.types'

const props = defineProps<{
  show: boolean
  loan: Application['loan']
  isSubmitting: boolean
}>()

const emit = defineEmits<{
  (e: 'update:show', value: boolean): void
  (e: 'submit', payload: { amount: number; term_months: number; interest_rate: number; payment_frequency: string; reason: string }): void
}>()

const counterOffer = ref({
  amount: 0,
  term_months: 12,
  interest_rate: 36,
  payment_frequency: 'QUINCENAL',
  reason: ''
})

// Pre-fill con los valores actuales del crédito al abrir (sin immediate: replica
// openCounterOfferModal, que solo pre-llena en la apertura).
watch(() => props.show, (open) => {
  if (open) {
    counterOffer.value = {
      amount: props.loan.requested_amount,
      term_months: props.loan.term_months,
      interest_rate: props.loan.interest_rate,
      payment_frequency: props.loan.payment_frequency,
      reason: ''
    }
  }
})

const counterOfferCalculation = computed(() => {
  const amount = counterOffer.value.amount
  const termMonths = counterOffer.value.term_months
  const annualRate = counterOffer.value.interest_rate
  const frequency = counterOffer.value.payment_frequency

  const periodsPerYear = frequency === 'QUINCENAL' ? 24 : 12
  const totalPeriods = frequency === 'QUINCENAL' ? termMonths * 2 : termMonths
  const periodRate = (annualRate / 100) / periodsPerYear

  let payment = 0
  if (periodRate > 0) {
    payment = amount * (periodRate * Math.pow(1 + periodRate, totalPeriods)) /
      (Math.pow(1 + periodRate, totalPeriods) - 1)
  } else {
    payment = amount / totalPeriods
  }

  const totalToPay = payment * totalPeriods
  const totalInterest = totalToPay - amount

  return {
    payment: Math.round(payment * 100) / 100,
    totalPeriods,
    totalToPay: Math.round(totalToPay * 100) / 100,
    totalInterest: Math.round(totalInterest * 100) / 100
  }
})
</script>

<template>
<div
  v-if="show"
  class="fixed inset-0 bg-black/50 flex items-center justify-center z-50"
  @click.self="emit('update:show', false)"
>
  <div class="bg-white rounded-xl w-full max-w-2xl mx-4 max-h-[90vh] overflow-y-auto">
    <!-- Header -->
    <div class="p-6 border-b border-gray-200 bg-indigo-50 rounded-t-xl">
      <h3 class="text-lg font-semibold text-indigo-900">Crear Contraoferta</h3>
      <p class="text-sm text-indigo-700 mt-1">
        Modifica las condiciones del crédito para hacer una contraoferta al solicitante
      </p>
    </div>

    <div class="p-6 space-y-6">
      <!-- Original Request Summary -->
      <div class="bg-gray-50 rounded-lg p-4">
        <p class="text-sm font-medium text-gray-500 mb-2">Solicitud Original</p>
        <div class="flex flex-wrap gap-4 text-sm">
          <span><strong>Monto:</strong> {{ formatMoney(loan.requested_amount) }}</span>
          <span><strong>Plazo:</strong> {{ loan.term_months }} meses</span>
          <span><strong>Tasa:</strong> {{ loan.interest_rate }}%</span>
        </div>
      </div>

      <!-- Counter-offer Form -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">
            Monto Aprobado
          </label>
          <div class="relative">
            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500">$</span>
            <input
              v-model.number="counterOffer.amount"
              type="number"
              min="1000"
              step="1000"
              class="w-full pl-8 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
            />
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">
            Plazo (meses)
          </label>
          <select
            v-model.number="counterOffer.term_months"
            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
          >
            <option :value="6">6 meses</option>
            <option :value="12">12 meses</option>
            <option :value="18">18 meses</option>
            <option :value="24">24 meses</option>
            <option :value="36">36 meses</option>
            <option :value="48">48 meses</option>
          </select>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">
            Tasa Anual (%)
          </label>
          <input
            v-model.number="counterOffer.interest_rate"
            type="number"
            min="0"
            max="100"
            step="0.5"
            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
          />
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">
            Frecuencia de Pago
          </label>
          <select
            v-model="counterOffer.payment_frequency"
            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
          >
            <option value="QUINCENAL">Quincenal</option>
            <option value="MENSUAL">Mensual</option>
          </select>
        </div>
      </div>

      <!-- Calculation Preview -->
      <div class="bg-indigo-50 rounded-lg p-4">
        <p class="text-sm font-medium text-indigo-900 mb-3">Resumen de Contraoferta</p>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-center">
          <div>
            <p class="text-xs text-indigo-600">Monto</p>
            <p class="text-lg font-bold text-indigo-900">{{ formatMoney(counterOffer.amount) }}</p>
          </div>
          <div>
            <p class="text-xs text-indigo-600">Pago {{ counterOffer.payment_frequency === 'QUINCENAL' ? 'Quincenal' : 'Mensual' }}</p>
            <p class="text-lg font-bold text-indigo-900">{{ formatMoney(counterOfferCalculation.payment) }}</p>
          </div>
          <div>
            <p class="text-xs text-indigo-600">Total Pagos</p>
            <p class="text-lg font-bold text-indigo-900">{{ counterOfferCalculation.totalPeriods }}</p>
          </div>
          <div>
            <p class="text-xs text-indigo-600">Total a Pagar</p>
            <p class="text-lg font-bold text-indigo-900">{{ formatMoney(counterOfferCalculation.totalToPay) }}</p>
          </div>
        </div>
      </div>

      <!-- Reason -->
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">
          Razón de la contraoferta (opcional)
        </label>
        <textarea
          v-model="counterOffer.reason"
          rows="3"
          class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
          placeholder="Ej: Capacidad de pago limitada según ingresos reportados..."
        />
      </div>
    </div>

    <!-- Footer -->
    <div class="p-6 border-t border-gray-200 flex gap-3">
      <AppButton
        variant="outline"
        class="flex-1"
        @click="emit('update:show', false)"
      >
        Cancelar
      </AppButton>
      <AppButton
        variant="primary"
        class="flex-1 !bg-indigo-600 hover:!bg-indigo-700"
        :loading="isSubmitting"
        @click="emit('submit', counterOffer)"
      >
        Enviar Contraoferta
      </AppButton>
    </div>
  </div>
</div>
</template>
