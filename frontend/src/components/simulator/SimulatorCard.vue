<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useApplicationStore, useTenantStore } from '@/stores'
import { AppButton, AppSlider } from '@/components/common'
import type { PaymentFrequency, Product } from '@/types'

interface Props {
  compact?: boolean
  product?: Product | null
}

const props = withDefaults(defineProps<Props>(), {
  compact: false,
  product: null
})

const router = useRouter()
const applicationStore = useApplicationStore()
const tenantStore = useTenantStore()

// Product rules (from prop or tenant config defaults)
const activeProduct = computed(() => props.product || tenantStore.activeProducts[0])
const minAmount = computed(() => activeProduct.value?.rules.min_amount ?? 5000)
const maxAmount = computed(() => activeProduct.value?.rules.max_amount ?? 500000)
const minTerm = computed(() => activeProduct.value?.rules.min_term_months ?? 3)
const maxTerm = computed(() => activeProduct.value?.rules.max_term_months ?? 48)
const availableFrequencies = computed(() => activeProduct.value?.rules.payment_frequencies ?? ['WEEKLY', 'BIWEEKLY', 'MONTHLY'])

// Generate term options based on product rules
const termOptions = computed(() => {
  const terms = [3, 6, 12, 18, 24, 36, 48, 60]
  return terms.filter(t => t >= minTerm.value && t <= maxTerm.value)
})

// Form state - initialize with middle values
const amount = ref(50000)
const termMonths = ref(18)
const paymentFrequency = ref<PaymentFrequency>('MONTHLY')

// Initialize values based on product
onMounted(() => {
  // Set initial amount to middle of range
  amount.value = Math.round((minAmount.value + maxAmount.value) / 2 / 1000) * 1000
  // Set initial term to middle option
  const terms = termOptions.value
  termMonths.value = terms[Math.floor(terms.length / 2)] || 12
  // Set initial frequency to first available
  paymentFrequency.value = (availableFrequencies.value[0] as PaymentFrequency) || 'MONTHLY'
})

// Simulation result
const simulation = computed(() => applicationStore.simulation)
const isLoading = computed(() => applicationStore.isLoading)

// Auto-run simulation on changes
watch([amount, termMonths, paymentFrequency, activeProduct], async () => {
  if (activeProduct.value) {
    await applicationStore.runSimulation({
      product_id: activeProduct.value.id,
      amount: amount.value,
      term_months: termMonths.value,
      payment_frequency: paymentFrequency.value
    })
  }
}, { immediate: true })

// Format currency
const formatCurrency = (value: number) => {
  return new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0
  }).format(value)
}

const formatCurrencyDecimals = (value: number) => {
  return new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  }).format(value)
}

// Request credit
const handleRequestCredit = async () => {
  if (!activeProduct.value) return

  await applicationStore.createApplication({
    product_id: activeProduct.value.id,
    requested_amount: amount.value,
    term_months: termMonths.value,
    payment_frequency: paymentFrequency.value
  })

  router.push('/auth')
}

const frequencyLabels: Record<PaymentFrequency, string> = {
  WEEKLY: 'Semanal',
  BIWEEKLY: 'Quincenal',
  MONTHLY: 'Mensual'
}

const paymentLabel = computed(() => {
  return paymentFrequency.value === 'WEEKLY' ? 'semanal' :
         paymentFrequency.value === 'BIWEEKLY' ? 'quincenal' : 'mensual'
})
</script>

<template>
  <div class="bg-white rounded-2xl shadow-2xl p-6 md:p-8">
    <h2 v-if="!compact" class="text-2xl font-bold text-gray-900 mb-6">
      Simula tu crédito
    </h2>

    <!-- Amount slider -->
    <div class="mb-6">
      <AppSlider
        v-model="amount"
        :min="minAmount"
        :max="maxAmount"
        :step="1000"
        label="¿Cuánto necesitas?"
        :format-value="formatCurrency"
      />
    </div>

    <!-- Term selection -->
    <div class="mb-6">
      <label class="block text-sm font-medium text-gray-700 mb-3">
        ¿En cuánto tiempo? (meses)
      </label>
      <div class="flex flex-wrap gap-2">
        <button
          v-for="term in termOptions"
          :key="term"
          :class="[
            'px-4 py-3 rounded-xl text-sm font-medium transition-colors',
            termMonths === term
              ? 'bg-primary-600 text-white'
              : 'border border-gray-200 text-gray-600 hover:border-primary-300'
          ]"
          @click="termMonths = term"
        >
          {{ term }}
        </button>
      </div>
    </div>

    <!-- Payment frequency -->
    <div v-if="availableFrequencies.length > 1" class="mb-6">
      <label class="block text-sm font-medium text-gray-700 mb-3">
        ¿Cada cuándo pagas?
      </label>
      <div class="flex bg-gray-100 rounded-xl p-1">
        <button
          v-for="freq in (availableFrequencies as PaymentFrequency[])"
          :key="freq"
          :class="[
            'flex-1 py-2.5 rounded-lg text-sm font-medium transition-colors',
            paymentFrequency === freq
              ? 'bg-white text-primary-600 shadow-sm'
              : 'text-gray-600 hover:text-gray-900'
          ]"
          @click="paymentFrequency = freq"
        >
          {{ frequencyLabels[freq] }}
        </button>
      </div>
    </div>

    <!-- Results card -->
    <div
      v-if="simulation"
      class="bg-gradient-to-br from-primary-600 to-primary-700 rounded-xl p-5 md:p-6 text-white mb-6"
    >
      <div class="flex justify-between items-end mb-4">
        <div>
          <p class="text-primary-100 text-sm">Tu pago {{ paymentLabel }}</p>
          <p class="text-3xl md:text-4xl font-bold">
            {{ formatCurrencyDecimals(simulation.periodic_payment) }}
          </p>
        </div>
        <div class="text-right">
          <p class="text-primary-100 text-sm">CAT</p>
          <p class="text-xl md:text-2xl font-bold">{{ simulation.cat }}%</p>
        </div>
      </div>

      <div class="grid grid-cols-2 gap-4 pt-4 border-t border-white/20 text-sm">
        <div>
          <p class="text-primary-200">Total a pagar</p>
          <p class="font-semibold">{{ formatCurrencyDecimals(simulation.total_amount) }}</p>
        </div>
        <div>
          <p class="text-primary-200">Intereses</p>
          <p class="font-semibold">{{ formatCurrencyDecimals(simulation.total_interest) }}</p>
        </div>
      </div>
    </div>

    <!-- Loading state -->
    <div
      v-else
      class="bg-gray-100 rounded-xl p-6 mb-6 flex items-center justify-center"
    >
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600" />
    </div>

    <!-- CTA Button -->
    <AppButton
      variant="primary"
      size="lg"
      full-width
      :loading="isLoading"
      @click="handleRequestCredit"
    >
      ¡Lo quiero! Solicitar ahora
    </AppButton>

    <!-- Disclaimer -->
    <p class="text-xs text-gray-400 text-center mt-4">
      *CAT promedio informativo. Sujeto a aprobación de crédito.
    </p>
  </div>
</template>
