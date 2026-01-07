<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useApplicationStore, useTenantStore, useAuthStore } from '@/stores'
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
const authStore = useAuthStore()

// Product rules (from prop or tenant config defaults)
const activeProduct = computed(() => props.product || tenantStore.activeProducts[0])
const minAmount = computed(() => activeProduct.value?.rules.min_amount ?? 5000)
const maxAmount = computed(() => activeProduct.value?.rules.max_amount ?? 500000)
const minTerm = computed(() => activeProduct.value?.rules.min_term_months ?? 3)
const maxTerm = computed(() => activeProduct.value?.rules.max_term_months ?? 48)
const availableFrequencies = computed(() => activeProduct.value?.rules.payment_frequencies ?? ['WEEKLY', 'BIWEEKLY', 'MONTHLY'])

// Conversion factors: payments per month
const frequencyMultiplier: Record<PaymentFrequency, number> = {
  WEEKLY: 4.33,
  BIWEEKLY: 2,
  QUINCENAL: 2,
  MONTHLY: 1,
  MENSUAL: 1
}

// Generate payment count options based on frequency and term limits
const paymentCountOptions = computed(() => {
  const multiplier = frequencyMultiplier[paymentFrequency.value]
  const minPayments = Math.round(minTerm.value * multiplier)
  const maxPayments = Math.round(maxTerm.value * multiplier)

  // Generate sensible payment options based on frequency
  let options: number[] = []
  if (paymentFrequency.value === 'WEEKLY') {
    options = [13, 26, 39, 52, 78, 104, 156, 208]
  } else if (paymentFrequency.value === 'BIWEEKLY') {
    options = [6, 12, 18, 24, 36, 48, 72, 96]
  } else {
    options = [3, 6, 12, 18, 24, 36, 48, 60]
  }

  return options.filter(p => p >= minPayments && p <= maxPayments)
})

// Form state - initialize with middle values
const amount = ref(50000)
const selectedPayments = ref(12)
const paymentFrequency = ref<PaymentFrequency>('MONTHLY')

// Convert selected payments to months for API
const termMonths = computed(() => {
  const multiplier = frequencyMultiplier[paymentFrequency.value]
  return Math.round(selectedPayments.value / multiplier)
})

// Initialize values based on product
onMounted(() => {
  // Set initial amount to middle of range
  amount.value = Math.round((minAmount.value + maxAmount.value) / 2 / 1000) * 1000
  // Set initial frequency to first available
  paymentFrequency.value = (availableFrequencies.value[0] as PaymentFrequency) || 'MONTHLY'
  // Set initial payment count to middle option
  const options = paymentCountOptions.value
  selectedPayments.value = options[Math.floor(options.length / 2)] || 12
})

// When frequency changes, adjust selected payments to closest valid option
watch(paymentFrequency, () => {
  const options = paymentCountOptions.value
  if (!options.includes(selectedPayments.value)) {
    // Find closest option
    const closest = options.reduce((prev, curr) =>
      Math.abs(curr - selectedPayments.value) < Math.abs(prev - selectedPayments.value) ? curr : prev
    )
    selectedPayments.value = closest
  }
})

// Simulation result
const simulation = computed(() => applicationStore.simulation)
const isLoading = computed(() => applicationStore.isLoading)

// Auto-run simulation on changes
watch([amount, selectedPayments, paymentFrequency, activeProduct], async () => {
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
  console.log('🚀 handleRequestCredit called')
  console.log('📦 activeProduct:', activeProduct.value?.id || 'NULL')

  if (!activeProduct.value) {
    console.error('❌ No active product!')
    return
  }

  // Store the selected product for use after authentication
  applicationStore.setSelectedProduct(activeProduct.value)

  // Save simulation params to localStorage for use after auth
  const pendingData = {
    product_id: activeProduct.value.id,
    requested_amount: amount.value,
    term_months: termMonths.value,
    payment_frequency: paymentFrequency.value
  }
  console.log('💾 Saving pending_application:', pendingData)
  localStorage.setItem('pending_application', JSON.stringify(pendingData))

  // Verify it was saved
  const saved = localStorage.getItem('pending_application')
  console.log('✅ Verified saved:', saved ? 'YES' : 'NO')

  // Check if user is already authenticated
  console.log('🔐 isAuthenticated:', authStore.isAuthenticated)

  if (authStore.isAuthenticated) {
    // Go directly to onboarding - application will be created there
    console.log('➡️ Navigating to /solicitud')
    router.push('/solicitud')
  } else {
    // Redirect to auth - application will be created after successful login
    console.log('➡️ Navigating to /auth')
    router.push('/auth')
  }
}

const frequencyLabels: Record<PaymentFrequency, string> = {
  WEEKLY: 'Semanal',
  BIWEEKLY: 'Quincenal',
  QUINCENAL: 'Quincenal',
  MONTHLY: 'Mensual',
  MENSUAL: 'Mensual'
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

    <!-- Payment frequency (shown first when multiple options) -->
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

    <!-- Payment count selection -->
    <div class="mb-6">
      <label class="block text-sm font-medium text-gray-700 mb-2">
        ¿En cuántos pagos?
      </label>
      <div class="flex flex-wrap gap-2">
        <button
          v-for="count in paymentCountOptions"
          :key="count"
          :class="[
            'px-4 py-3 rounded-xl text-sm font-medium transition-colors',
            selectedPayments === count
              ? 'bg-primary-600 text-white'
              : 'border border-gray-200 text-gray-600 hover:border-primary-300'
          ]"
          @click="selectedPayments = count"
        >
          {{ count }}
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
