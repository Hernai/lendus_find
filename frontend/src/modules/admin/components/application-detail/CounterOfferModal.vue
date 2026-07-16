<script setup lang="ts">
/**
 * Modal "Crear Contraoferta": adaptivo por producto.
 * - Productos en meses: monto/plazo/tasa/frecuencia + amortización en vivo (como siempre).
 * - Productos en días (loan.term_in_days, ej. MoneyCapital): monto + plazo en días
 *   validados contra product_limits; tasa y comisión fijas del producto (sin campos),
 *   resumen bullet (interés + IVA + comisión + IVA, fórmula del mockup/LoanOfferView).
 * Ambos modos capturan la vigencia de la oferta en minutos (default 30).
 * El envío real queda en el padre (@submit). Cierre vía v-model:show.
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

export interface CounterOfferSubmitPayload {
  amount: number
  term_months?: number
  term_days?: number
  interest_rate?: number
  reason: string
  expires_in_minutes: number
}

const emit = defineEmits<{
  (e: 'update:show', value: boolean): void
  (e: 'submit', payload: CounterOfferSubmitPayload): void
}>()

const daysMode = computed(() => props.loan.term_in_days === true)
const limits = computed(() => props.loan.product_limits ?? null)

const counterOffer = ref({
  amount: 0,
  term_months: 12,
  term_days: 10,
  interest_rate: 36,
  payment_frequency: 'QUINCENAL',
  reason: '',
  expires_in_minutes: 30
})

// Pre-fill con los valores actuales del crédito al abrir (sin immediate: replica
// openCounterOfferModal, que solo pre-llena en la apertura).
watch(() => props.show, (open) => {
  if (open) {
    counterOffer.value = {
      amount: props.loan.requested_amount,
      term_months: props.loan.requested_term_months ?? props.loan.term_months,
      term_days: props.loan.requested_term_days ?? limits.value?.max_term_days ?? 10,
      interest_rate: props.loan.interest_rate,
      payment_frequency: props.loan.payment_frequency,
      reason: '',
      expires_in_minutes: 30
    }
  }
})

// Amortización para productos en meses (sin cambios)
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

// Resumen bullet para productos en días: misma fórmula que LoanOfferView
// (interés simple prorrateado por días + IVA; comisión de apertura + IVA).
const bulletCalculation = computed(() => {
  // Coerción explícita: el v-model del spinbutton puede entregar string y
  // `amount + interés` concatenaría en vez de sumar (Total = $NaN).
  const amount = Number(counterOffer.value.amount) || 0
  const days = Number(counterOffer.value.term_days) || 0
  const annualRate = limits.value?.annual_rate ?? 0
  const commissionRate = limits.value?.opening_commission ?? 0

  const interest = amount * (annualRate / 100) * (days / 365)
  const interestWithIva = interest * 1.16
  const commission = amount * (commissionRate / 100)
  const commissionWithIva = commission * 1.16
  const total = amount + interestWithIva + commissionWithIva

  return {
    interestWithIva: Math.round(interestWithIva * 100) / 100,
    commissionWithIva: Math.round(commissionWithIva * 100) / 100,
    total: Math.round(total * 100) / 100
  }
})

// Validación contra límites del producto (si el backend no los mandó, no bloquea;
// el server valida de todos modos)
const validationError = computed((): string | null => {
  const l = limits.value
  const { amount, term_days } = counterOffer.value
  if (l?.min_amount != null && amount < l.min_amount) return `El monto mínimo es ${formatMoney(l.min_amount)}`
  if (l?.max_amount != null && amount > l.max_amount) return `El monto máximo es ${formatMoney(l.max_amount)}`
  if (daysMode.value) {
    if (l?.min_term_days != null && term_days < l.min_term_days) return `El plazo mínimo es ${l.min_term_days} días`
    if (l?.max_term_days != null && term_days > l.max_term_days) return `El plazo máximo es ${l.max_term_days} días`
  }
  return null
})

const canSubmit = computed(() =>
  !props.isSubmitting && counterOffer.value.amount > 0 && !validationError.value &&
  (daysMode.value ? counterOffer.value.term_days > 0 : counterOffer.value.term_months > 0)
)

const submit = () => {
  const base = {
    amount: counterOffer.value.amount,
    reason: counterOffer.value.reason,
    expires_in_minutes: counterOffer.value.expires_in_minutes
  }
  // En días la tasa es fija del producto: no se manda interest_rate
  emit('submit', daysMode.value
    ? { ...base, term_days: counterOffer.value.term_days }
    : { ...base, term_months: counterOffer.value.term_months, interest_rate: counterOffer.value.interest_rate })
}
</script>

<template>
<!-- Sin cierre por clic en el backdrop: evita perder monto/plazo/razón ya
     capturados por un clic accidental. Se cierra solo con "Cancelar"/"✕". -->
<div
  v-if="show"
  class="fixed inset-0 bg-black/50 flex items-center justify-center z-50"
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
          <span v-if="daysMode"><strong>Plazo:</strong> {{ loan.requested_term_days }} días</span>
          <span v-else><strong>Plazo:</strong> {{ loan.term_months }} meses</span>
          <span><strong>Tasa:</strong> {{ daysMode ? (limits?.annual_rate ?? loan.interest_rate) : loan.interest_rate }}%</span>
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
              :min="limits?.min_amount ?? 1"
              :max="limits?.max_amount ?? undefined"
              :step="daysMode ? 100 : 1000"
              class="w-full pl-8 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
            />
          </div>
        </div>

        <!-- Plazo en días (productos term_in_days: tasa/frecuencia fijas del producto) -->
        <div v-if="daysMode">
          <label class="block text-sm font-medium text-gray-700 mb-2">
            Plazo (días)
          </label>
          <input
            v-model.number="counterOffer.term_days"
            type="number"
            :min="limits?.min_term_days ?? 1"
            :max="limits?.max_term_days ?? 365"
            step="1"
            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
          />
          <p class="text-xs text-gray-400 mt-1">
            Entre {{ limits?.min_term_days ?? 1 }} y {{ limits?.max_term_days ?? 365 }} días · pago único
          </p>
        </div>

        <div v-else>
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

        <div v-if="!daysMode">
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

        <div v-if="!daysMode">
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

        <!-- Vigencia (ambos modos) -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">
            Vigencia de la oferta (minutos)
          </label>
          <input
            v-model.number="counterOffer.expires_in_minutes"
            type="number"
            min="5"
            max="10080"
            step="5"
            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
          />
          <p class="text-xs text-gray-400 mt-1">
            Al vencer sin respuesta, la solicitud se cancela automáticamente
          </p>
        </div>
      </div>

      <!-- Calculation Preview — bullet (días) -->
      <div v-if="daysMode" class="bg-indigo-50 rounded-lg p-4">
        <p class="text-sm font-medium text-indigo-900 mb-3">Resumen de Contraoferta</p>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-center">
          <div>
            <p class="text-xs text-indigo-600">Monto</p>
            <p class="text-lg font-bold text-indigo-900">{{ formatMoney(counterOffer.amount) }}</p>
          </div>
          <div>
            <p class="text-xs text-indigo-600">Interés (IVA incl.)</p>
            <p class="text-lg font-bold text-indigo-900">{{ formatMoney(bulletCalculation.interestWithIva) }}</p>
          </div>
          <div>
            <p class="text-xs text-indigo-600">Comisión (IVA incl.)</p>
            <p class="text-lg font-bold text-indigo-900">{{ formatMoney(bulletCalculation.commissionWithIva) }}</p>
          </div>
          <div>
            <p class="text-xs text-indigo-600">Total a Pagar</p>
            <p class="text-lg font-bold text-indigo-900">{{ formatMoney(bulletCalculation.total) }}</p>
          </div>
        </div>
      </div>

      <!-- Calculation Preview — amortización (meses) -->
      <div v-else class="bg-indigo-50 rounded-lg p-4">
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

      <!-- Validación contra límites del producto -->
      <p v-if="validationError" class="text-sm text-red-600">{{ validationError }}</p>

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
        :disabled="!canSubmit"
        @click="submit"
      >
        Enviar Contraoferta
      </AppButton>
    </div>
  </div>
</div>
</template>
