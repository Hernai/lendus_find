<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useApplicationStore, useTenantStore, useAuthStore } from '@/stores'
import { AppButton, AppSlider } from '@/components/common'
import { formatMoney, formatMoneyDecimals, formatFrequency } from '@/utils/formatters'
import type { PaymentFrequency, Product } from '@/types'

interface Props {
  compact?: boolean
  product?: Product | null
  inOnboarding?: boolean // Flag to indicate if simulator is within onboarding flow
}

const props = withDefaults(defineProps<Props>(), {
  compact: false,
  product: null,
  inOnboarding: false
})

const emit = defineEmits<{
  continue: [] // Emitted when user clicks "Solicitar ahora" in onboarding mode
}>()

const router = useRouter()
const applicationStore = useApplicationStore()
const tenantStore = useTenantStore()
const authStore = useAuthStore()

// Product rules (from prop or tenant config defaults)
const activeProduct = computed(() => props.product || tenantStore.activeProducts[0])
const minAmount = computed(() => activeProduct.value?.rules?.min_amount ?? 5000)
const maxAmount = computed(() => activeProduct.value?.rules?.max_amount ?? 500000)
const minTerm = computed(() => activeProduct.value?.rules?.min_term_months ?? 3)
const maxTerm = computed(() => activeProduct.value?.rules?.max_term_months ?? 48)

// Get term_config from product (could be in rules or directly on product)
const termConfig = computed(() =>
  activeProduct.value?.term_config || activeProduct.value?.rules?.term_config || null
)

// Filter out empty values and deduplicate frequencies (SEMANAL=WEEKLY, QUINCENAL=BIWEEKLY, MENSUAL=MONTHLY)
const availableFrequencies = computed(() => {
  const rawFrequencies = activeProduct.value?.rules?.payment_frequencies ?? ['MONTHLY']
  const seen = new Set<string>()
  return rawFrequencies.filter(freq => {
    if (!freq || freq.trim() === '') return false
    // Normalize equivalent values for deduplication
    const normalized = freq === 'SEMANAL' ? 'WEEKLY' :
                       freq === 'QUINCENAL' ? 'BIWEEKLY' :
                       freq === 'MENSUAL' ? 'MONTHLY' : freq
    if (seen.has(normalized)) return false
    seen.add(normalized)
    return true
  })
})

// Conversion factors: payments per month
const frequencyMultiplier: Record<PaymentFrequency, number> = {
  SEMANAL: 4.33,
  WEEKLY: 4.33,
  BIWEEKLY: 2,
  QUINCENAL: 2,
  MONTHLY: 1,
  MENSUAL: 1
}

// Normalize frequency key for term_config lookup (admin saves with English keys)
const normalizeFrequencyKey = (freq: PaymentFrequency): string => {
  switch (freq) {
    case 'SEMANAL':
    case 'WEEKLY':
      return 'WEEKLY'
    case 'QUINCENAL':
    case 'BIWEEKLY':
      return 'BIWEEKLY'
    case 'MENSUAL':
    case 'MONTHLY':
    default:
      return 'MONTHLY'
  }
}

// Generate payment count options based on term_config or fallback to defaults
const paymentCountOptions = computed(() => {
  const config = termConfig.value
  const freqKey = normalizeFrequencyKey(paymentFrequency.value)

  // If term_config exists and has config for this frequency, use it
  if (config && config[freqKey]?.available_terms?.length) {
    return [...config[freqKey].available_terms].sort((a, b) => a - b)
  }

  // Fallback to legacy behavior
  const multiplier = frequencyMultiplier[paymentFrequency.value]
  const minPayments = Math.round(minTerm.value * multiplier)
  const maxPayments = Math.round(maxTerm.value * multiplier)

  let options: number[] = []
  if (paymentFrequency.value === 'SEMANAL' || paymentFrequency.value === 'WEEKLY') {
    options = [13, 26, 39, 52, 78, 104, 156, 208]
  } else if (paymentFrequency.value === 'QUINCENAL' || paymentFrequency.value === 'BIWEEKLY') {
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

// Track if component is initialized
const isInitialized = ref(false)

// Initialize values based on product
onMounted(async () => {
  // Set initial amount to middle of range
  amount.value = Math.round((minAmount.value + maxAmount.value) / 2 / 1000) * 1000
  // Set initial frequency to first available
  paymentFrequency.value = (availableFrequencies.value[0] as PaymentFrequency) || 'MONTHLY'
  // Set initial payment count to middle option
  const options = paymentCountOptions.value
  selectedPayments.value = options[Math.floor(options.length / 2)] || 12

  // Mark as initialized and run initial simulation
  isInitialized.value = true

  // Run initial simulation with valid values
  if (activeProduct.value) {
    const term = termMonths.value
    const minTermMonths = activeProduct.value.rules?.min_term_months ?? 3
    const maxTermMonths = activeProduct.value.rules?.max_term_months ?? 48

    if (term >= minTermMonths && term <= maxTermMonths) {
      await applicationStore.runSimulation({
        product_id: activeProduct.value.id,
        amount: amount.value,
        term_months: term,
        payment_frequency: paymentFrequency.value
      })
    }
  }
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

// Auto-run simulation on changes (only after initialization)
watch([amount, selectedPayments, paymentFrequency, activeProduct], async () => {
  // Skip if not initialized yet (onMounted handles initial simulation)
  if (!isInitialized.value) return

  if (activeProduct.value) {
    // Validate term_months is within product range before calling API
    const term = termMonths.value
    const minTermMonths = activeProduct.value.rules?.min_term_months ?? 3
    const maxTermMonths = activeProduct.value.rules?.max_term_months ?? 48

    if (term < minTermMonths || term > maxTermMonths) {
      console.warn(`[Simulator] term_months ${term} out of range [${minTermMonths}, ${maxTermMonths}], skipping simulation`)
      return
    }

    await applicationStore.runSimulation({
      product_id: activeProduct.value.id,
      amount: amount.value,
      term_months: term,
      payment_frequency: paymentFrequency.value
    })
  }
})


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

  // If we're in onboarding mode, emit event to parent instead of navigating
  if (props.inOnboarding) {
    console.log('➡️ In onboarding mode - emitting continue event')
    emit('continue')
    return
  }

  // Check if user is already authenticated
  console.log('🔐 isAuthenticated:', authStore.isAuthenticated)

  if (authStore.isAuthenticated) {
    // User is already authenticated and has product selected
    // Skip simulator and go directly to verification (KYC)
    console.log('✅ User authenticated with product selected - skipping simulator')
    console.log('➡️ Navigating to /solicitud/verificacion')
    router.push('/solicitud/verificacion')
  } else {
    // Redirect to auth - application will be created after successful login
    console.log('➡️ Navigating to /auth')
    router.push('/auth')
  }
}

// Get frequency label from backend enum via formatters utility
const getFrequencyLabel = (freq: PaymentFrequency) => {
  // Capitalize first letter for display in selector buttons
  const label = formatFrequency(freq)
  return label.charAt(0).toUpperCase() + label.slice(1)
}

const paymentLabel = computed(() => {
  const freq = paymentFrequency.value
  if (freq === 'SEMANAL' || freq === 'WEEKLY') return 'semanal'
  if (freq === 'QUINCENAL' || freq === 'BIWEEKLY') return 'quincenal'
  return 'mensual'
})
</script>

<template>
  <div class="bg-white rounded-2xl shadow-2xl p-6 md:p-8">
    <h2 v-if="!compact" class="text-2xl font-bold text-tenant mb-6">
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
        :format-value="formatMoney"
      />
    </div>

    <!-- Payment frequency (shown first when multiple options) -->
    <div v-if="availableFrequencies.length > 1" class="mb-6">
      <label class="block text-sm font-medium text-tenant mb-3">
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
          {{ getFrequencyLabel(freq) }}
        </button>
      </div>
    </div>

    <!-- Payment count selection -->
    <div class="mb-6">
      <label class="block text-sm font-medium text-tenant mb-2">
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
            {{ formatMoneyDecimals(simulation.periodic_payment) }}
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
          <p class="font-semibold">{{ formatMoneyDecimals(simulation.total_amount) }}</p>
        </div>
        <div class="text-right">
          <p class="text-primary-200">Intereses</p>
          <p class="font-semibold">{{ formatMoneyDecimals(simulation.total_interest) }}</p>
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
