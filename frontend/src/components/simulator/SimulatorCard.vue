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

// Pago único (SINGLE / BULLET): el plazo se mide en DÍAS, no en número de
// pagos. El cliente elige el plazo con un slider de días.
const isSinglePayment = computed(() =>
  availableFrequencies.value.length === 1 && availableFrequencies.value[0] === 'SINGLE'
)
const minDays = computed(() => activeProduct.value?.rules?.min_term_days ?? 1)
const maxDays = computed(() => activeProduct.value?.rules?.max_term_days ?? 30)
const defaultDays = computed(() =>
  activeProduct.value?.rules?.default_term_days ?? Math.round((minDays.value + maxDays.value) / 2)
)
const formatDays = (v: number) => `${v} ${v === 1 ? 'día' : 'días'}`
const formatPct = (v: number) => `${v}%`

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
  MENSUAL: 1,
  // SINGLE = pago único; el multiplier no se usa para conversión mensual,
  // pero hay que declararlo para satisfacer Record<PaymentFrequency, …>.
  SINGLE: 0,
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
const selectedDays = ref(10) // solo para pago único (SINGLE)
const paymentFrequency = ref<PaymentFrequency>('MONTHLY')

// --- Arrendamiento (leasing): 'amount' se reutiliza como VALOR DEL BIEN y se
// agrega el anticipo (%). Todo condicional a type === 'ARRENDAMIENTO'. ---
const isLease = computed(() => activeProduct.value?.type === 'ARRENDAMIENTO')
const leaseConfig = computed(() => activeProduct.value?.rules?.lease)
const anticipoMin = computed(() => leaseConfig.value?.anticipo_pct_min ?? 0)
const anticipoMax = computed(() => leaseConfig.value?.anticipo_pct_max ?? 30)
const downPaymentPct = ref(20)
const leaseResult = computed(() => applicationStore.simulation?.lease ?? null)

// --- Bien a arrendar: tipo + detalle (marca/modelo/año o capacidad). Se captura
// AQUÍ (en el simulador compartido) para que aparezca en landing, /simulador y
// onboarding por igual. Buffereado en el store hasta crear la solicitud, donde se
// vuelca a metadata.lease (ver persistLeaseMetadata). ---
const assetOptions = computed<{ value: string; label: string }[]>(() => {
  const allowed = leaseConfig.value?.asset_types ?? []
  const catalog = tenantStore.options.assetType ?? []
  return allowed.map((v) => catalog.find((o) => o.value === v) ?? { value: v, label: v })
})
const selectedAsset = computed<string>({
  get: () => applicationStore.selectedAssetType ?? '',
  set: (v) => applicationStore.setSelectedAssetType(v || null),
})
const isSolarAsset = computed(() => selectedAsset.value === 'SOLAR_PANELS')
const leaseBrand = computed<string>({
  get: () => applicationStore.leaseDraft.asset_brand ?? '',
  set: (v) => applicationStore.setLeaseDraft({ asset_brand: v }),
})
const leaseModel = computed<string>({
  get: () => applicationStore.leaseDraft.asset_model ?? '',
  set: (v) => applicationStore.setLeaseDraft({ asset_model: v }),
})
const leaseYear = computed<string>({
  get: () => (applicationStore.leaseDraft.asset_year ?? '').toString(),
  set: (v) => applicationStore.setLeaseDraft({ asset_year: v ? Number(v) : null }),
})
const leaseCapacity = computed<string>({
  get: () => applicationStore.leaseDraft.asset_capacity ?? '',
  set: (v) => applicationStore.setLeaseDraft({ asset_capacity: v }),
})

// --- Simulador de arrendamiento en 2 pasos DENTRO de la misma tarjeta:
// paso 1 = el bien (tipo + marca/modelo/año|capacidad), paso 2 = valor/anticipo/
// plazo → renta. Solo aplica si el producto tiene bienes que elegir; el crédito
// lo ignora por completo (hasAssetStep = false). ---
const hasAssetStep = computed(() => isLease.value && assetOptions.value.length > 0)
const leaseStep = ref<1 | 2>(1)
const canAdvanceLease = computed(() => !!selectedAsset.value)
// Al cambiar de producto volvemos al paso 1 del bien.
watch(activeProduct, () => { leaseStep.value = 1 })

// Convert selected payments to months for API
const termMonths = computed(() => {
  // Pago único: el backend usa term_days; mandamos el plazo en meses del
  // producto (1) para pasar la validación de rango, sin dividir por 0.
  if (isSinglePayment.value) return activeProduct.value?.rules?.min_term_months ?? 1
  const multiplier = frequencyMultiplier[paymentFrequency.value]
  return Math.round(selectedPayments.value / multiplier)
})

// Track if component is initialized
const isInitialized = ref(false)

// Corre la simulación con los valores actuales (compartido por onMounted y el
// watch). Para pago único manda term_days; para multi-pago valida el rango de
// meses antes de llamar a la API.
const simulate = async () => {
  if (!activeProduct.value) return

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
    term_days: isSinglePayment.value ? selectedDays.value : undefined,
    payment_frequency: paymentFrequency.value,
    down_payment_pct: isLease.value ? downPaymentPct.value : undefined
  })
}

// Initialize values based on product
onMounted(async () => {
  // Set initial amount to middle of range
  amount.value = Math.round((minAmount.value + maxAmount.value) / 2 / 1000) * 1000
  // Set initial frequency to first available
  paymentFrequency.value = (availableFrequencies.value[0] as PaymentFrequency) || 'MONTHLY'

  if (isSinglePayment.value) {
    // Pago único: plazo en días (clamp al rango del producto)
    selectedDays.value = Math.min(maxDays.value, Math.max(minDays.value, defaultDays.value))
  } else {
    // Set initial payment count to middle option
    const options = paymentCountOptions.value
    selectedPayments.value = options[Math.floor(options.length / 2)] || 12
  }

  // Anticipo inicial para arrendamiento (del producto).
  if (isLease.value) {
    downPaymentPct.value = leaseConfig.value?.anticipo_pct_default ?? 20
  }

  // Mark as initialized and run initial simulation
  isInitialized.value = true
  await simulate()
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
watch([amount, selectedPayments, selectedDays, paymentFrequency, activeProduct, downPaymentPct], async () => {
  // Skip if not initialized yet (onMounted handles initial simulation)
  if (!isInitialized.value) return
  await simulate()
})


// Solicitar crédito
const handleRequestCredit = async () => {
  if (!activeProduct.value) {
    return
  }

  // Guardamos el producto seleccionado para usarlo después de autenticarse.
  applicationStore.setSelectedProduct(activeProduct.value)

  // Persistimos los parámetros de simulación para retomarlos tras el login.
  const pendingData = {
    product_id: activeProduct.value.id,
    requested_amount: amount.value,
    term_months: termMonths.value,
    requested_term_days: isSinglePayment.value ? selectedDays.value : undefined,
    payment_frequency: paymentFrequency.value
  }
  localStorage.setItem('pending_application', JSON.stringify(pendingData))

  // En modo onboarding emitimos el evento al padre en vez de navegar.
  if (props.inOnboarding) {
    emit('continue')
    return
  }

  if (authStore.isAuthenticated) {
    // Ya autenticado y con producto elegido: directo a la verificación (KYC).
    router.push('/solicitud/verificacion')
  } else {
    // Sin sesión: a auth; la solicitud se crea tras el login exitoso.
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
  if (freq === 'SINGLE') return 'único'
  if (freq === 'SEMANAL' || freq === 'WEEKLY') return 'semanal'
  if (freq === 'QUINCENAL' || freq === 'BIWEEKLY') return 'quincenal'
  return 'mensual'
})
</script>

<template>
  <div class="bg-white rounded-2xl shadow-2xl p-6 md:p-8">
    <h2 v-if="!compact" class="text-2xl font-bold text-tenant" :class="hasAssetStep ? 'mb-1' : 'mb-6'">
      {{ isLease ? 'Simula tu arrendamiento' : 'Simula tu crédito' }}
    </h2>
    <!-- Indicador de paso (arrendamiento en 2 pasos: el bien / tu renta) -->
    <p v-if="hasAssetStep" class="text-sm font-medium text-gray-500 mb-6">
      Paso {{ leaseStep }} de 2 · {{ leaseStep === 1 ? 'El bien' : 'Tu renta' }}
    </p>

    <!-- PASO 1 — Bien a arrendar: tipo + detalle. Se captura ANTES del valor para
         que "lo primero" sea el bien; al continuar pasamos a la simulación (paso 2). -->
    <div v-if="hasAssetStep && leaseStep === 1" class="mb-6">
      <label class="block text-sm font-medium text-tenant mb-2">¿Qué deseas arrendar?</label>
      <select
        v-model="selectedAsset"
        class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-white focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors"
      >
        <option value="" disabled>Selecciona el bien…</option>
        <option v-for="opt in assetOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
      </select>

      <!-- Detalle: marca/modelo/año (vehículo/maquinaria) o capacidad (solar) -->
      <div v-if="selectedAsset" class="mt-3 grid grid-cols-2 gap-3">
        <div :class="isSolarAsset ? 'col-span-2' : 'col-span-1'">
          <label class="block text-xs font-medium text-gray-600 mb-1">Marca</label>
          <input
            v-model="leaseBrand"
            type="text"
            :placeholder="isSolarAsset ? 'Ej. Jinko, LONGi…' : 'Ej. Toyota, Caterpillar…'"
            class="w-full px-3 py-2 border border-gray-200 rounded-lg bg-white text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
          />
        </div>
        <div v-if="!isSolarAsset">
          <label class="block text-xs font-medium text-gray-600 mb-1">Modelo</label>
          <input
            v-model="leaseModel"
            type="text"
            placeholder="Ej. Hilux, 320D…"
            class="w-full px-3 py-2 border border-gray-200 rounded-lg bg-white text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
          />
        </div>
        <div v-if="!isSolarAsset">
          <label class="block text-xs font-medium text-gray-600 mb-1">Año</label>
          <input
            v-model="leaseYear"
            type="number"
            inputmode="numeric"
            placeholder="Ej. 2024"
            class="w-full px-3 py-2 border border-gray-200 rounded-lg bg-white text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
          />
        </div>
        <div v-else class="col-span-2">
          <label class="block text-xs font-medium text-gray-600 mb-1">Capacidad (kW)</label>
          <input
            v-model="leaseCapacity"
            type="text"
            placeholder="Ej. 5 kW"
            class="w-full px-3 py-2 border border-gray-200 rounded-lg bg-white text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
          />
        </div>
      </div>
    </div>

    <!-- PASO 1 → 2: continuar del bien a la simulación de la renta -->
    <AppButton
      v-if="hasAssetStep && leaseStep === 1"
      variant="primary"
      size="lg"
      full-width
      :disabled="!canAdvanceLease"
      @click="leaseStep = 2"
    >
      Continuar
    </AppButton>

    <!-- PASO 2 — Simulación: valor/anticipo/plazo → renta y desembolso.
         (Crédito: siempre visible; no tiene paso del bien.) -->
    <template v-if="!hasAssetStep || leaseStep === 2">
    <!-- Volver a elegir el bien (solo arrendamiento) -->
    <button
      v-if="hasAssetStep"
      type="button"
      class="inline-flex items-center gap-1 text-sm font-medium text-gray-500 hover:text-gray-700 mb-4"
      @click="leaseStep = 1"
    >
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
      </svg>
      Cambiar el bien
    </button>

    <!-- Monto (crédito) / Valor del bien (arrendamiento) -->
    <div class="mb-6">
      <AppSlider
        v-model="amount"
        :min="minAmount"
        :max="maxAmount"
        :step="1000"
        :label="isLease ? '¿Cuál es el valor del bien?' : '¿Cuánto necesitas?'"
        :format-value="formatMoney"
      />
    </div>

    <!-- Anticipo / enganche (solo arrendamiento) -->
    <div v-if="isLease" class="mb-6">
      <AppSlider
        v-model="downPaymentPct"
        :min="anticipoMin"
        :max="anticipoMax"
        :step="1"
        label="Anticipo (enganche)"
        :format-value="formatPct"
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

    <!-- Plazo: pago único = slider de días; multi-pago = número de pagos -->
    <div class="mb-6">
      <!-- Pago único (SINGLE): slider de días -->
      <div v-if="isSinglePayment">
        <AppSlider
          v-model="selectedDays"
          :min="minDays"
          :max="maxDays"
          :step="1"
          label="¿A qué plazo?"
          :format-value="formatDays"
        />
      </div>
      <!-- Multi-pago: selección del número de pagos -->
      <div v-else>
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
    </div>

    <!-- Resultado CRÉDITO -->
    <div
      v-if="simulation && !isLease"
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

    <!-- Resultado ARRENDAMIENTO -->
    <div v-else-if="simulation && isLease && leaseResult" class="mb-6 space-y-4">
      <!-- Renta mensual -->
      <div class="bg-gradient-to-br from-primary-600 to-primary-700 rounded-xl p-5 md:p-6 text-white">
        <p class="text-primary-100 text-sm">Tu renta mensual <span class="opacity-80">(IVA incl.)</span></p>
        <p class="text-3xl md:text-4xl font-bold">{{ formatMoneyDecimals(leaseResult.monthly_rental_with_iva) }}</p>
        <p class="text-primary-100 text-xs mt-1">
          {{ leaseResult.is_pago_anticipado
            ? 'La 1ª renta va en el desembolso inicial; la siguiente en ~30 días.'
            : 'Tu primera renta cae en ~30 días.' }}
        </p>
      </div>

      <!-- Desembolso inicial desglosado -->
      <div class="bg-gray-50 rounded-xl p-4">
        <h3 class="font-semibold text-gray-900 mb-3">Desembolso inicial (al firmar)</h3>
        <div class="space-y-2 text-sm">
          <div v-if="leaseResult.first_installment.anticipo" class="flex justify-between">
            <span class="text-gray-600">Anticipo</span>
            <span class="font-medium">{{ formatMoneyDecimals(leaseResult.first_installment.anticipo) }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-gray-600">Comisión de apertura <span class="text-gray-400">(+IVA)</span></span>
            <span class="font-medium">{{ formatMoneyDecimals(leaseResult.first_installment.comision + leaseResult.first_installment.comision_iva) }}</span>
          </div>
          <div v-if="leaseResult.first_installment.deposito_reembolsable" class="flex justify-between">
            <span class="text-gray-600">Depósito en garantía <span class="text-emerald-600">(reembolsable)</span></span>
            <span class="font-medium">{{ formatMoneyDecimals(leaseResult.first_installment.deposito_reembolsable) }}</span>
          </div>
          <div v-if="leaseResult.first_installment.rentas_anticipadas" class="flex justify-between">
            <span class="text-gray-600">Renta anticipada <span class="text-gray-400">(IVA incl.)</span></span>
            <span class="font-medium">{{ formatMoneyDecimals(leaseResult.first_installment.rentas_anticipadas) }}</span>
          </div>
          <div class="flex justify-between pt-2 border-t border-gray-200">
            <span class="text-gray-900 font-semibold">Total a pagar hoy</span>
            <span class="font-bold text-primary-600">{{ formatMoneyDecimals(leaseResult.first_installment.total) }}</span>
          </div>
        </div>
      </div>

      <!-- Opción de compra -->
      <div v-if="leaseResult.purchase_option && leaseResult.purchase_option_amount" class="bg-blue-50 rounded-xl p-4 text-sm text-blue-800">
        Al final del plazo puedes <strong>comprar el bien</strong> por
        {{ formatMoneyDecimals(leaseResult.purchase_option_amount) }} (valor residual).
      </div>

      <!-- Nota fiscal/informativa según la modalidad (solo presentacional). -->
      <div class="flex gap-2 text-xs text-gray-500">
        <svg class="w-4 h-4 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <p>
          {{ leaseResult.modality === 'PURO'
            ? 'Arrendamiento puro: la renta es 100% deducible como gasto. Al final devuelves el bien (sin opción de compra).'
            : 'Arrendamiento financiero: deduces la depreciación del bien más los intereses, y tienes opción de compra al final del plazo.' }}
        </p>
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
    </template>
  </div>
</template>
