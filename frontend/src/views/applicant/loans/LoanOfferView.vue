<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useTenantStore } from '@/stores'
import { useToast } from '@/composables/useToast'
import { v2 } from '@/services/v2'
import AppButton from '@/components/common/AppButton.vue'

interface CounterOffer {
  amount?: number | null
  min_amount?: number | null
  max_amount?: number | null
  term_days?: number | null
  min_term_days?: number | null
  max_term_days?: number | null
  interest_rate?: number | null
  expires_at?: string | null
  cat?: number | null
}

const route = useRoute()
const router = useRouter()
const tenantStore = useTenantStore()
const toast = useToast()

const applicationId = computed(() => String(route.params.id ?? ''))

const offer = ref<CounterOffer | null>(null)
const loading = ref(false)
const submitting = ref(false)
const accepted = ref(false)
const amount = ref(0)
const termDays = ref(0)

// Cuenta regresiva del expires_at
const now = ref(Date.now())
let timerId: number | null = null

const expiresMs = computed(() => offer.value?.expires_at ? new Date(offer.value.expires_at).getTime() : null)
const remainingMs = computed(() => expiresMs.value ? Math.max(0, expiresMs.value - now.value) : null)
const remainingLabel = computed(() => {
  if (remainingMs.value == null) return null
  const mins = Math.floor(remainingMs.value / 60000)
  const secs = Math.floor((remainingMs.value % 60000) / 1000)
  return `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`
})
const expired = computed(() => remainingMs.value != null && remainingMs.value === 0)

const minAmount = computed(() => Number(offer.value?.min_amount ?? offer.value?.amount ?? 0))
const maxAmount = computed(() => Number(offer.value?.max_amount ?? offer.value?.amount ?? 0))
const minTerm = computed(() => Number(offer.value?.min_term_days ?? offer.value?.term_days ?? 0))
const maxTerm = computed(() => Number(offer.value?.max_term_days ?? offer.value?.term_days ?? 0))

const interestRate = computed(() => Number(offer.value?.interest_rate ?? 0))

const interestAmount = computed(() => {
  if (!amount.value || !termDays.value || !interestRate.value) return 0
  return +(amount.value * (interestRate.value / 100) * (termDays.value / 365)).toFixed(2)
})

const ivaInterest = computed(() => +(interestAmount.value * 0.16).toFixed(2))
const totalToPay = computed(() => +(amount.value + interestAmount.value + ivaInterest.value).toFixed(2))

const dueDate = computed(() => {
  if (!termDays.value) return null
  const d = new Date()
  d.setDate(d.getDate() + termDays.value)
  return d
})

const dueDateLabel = computed(() => {
  if (!dueDate.value) return '—'
  return dueDate.value.toLocaleDateString('es-MX', { day: '2-digit', month: 'long', year: 'numeric' })
})

const acceptContract = ref(false)

const canSubmit = computed(() =>
  !submitting.value &&
  !expired.value &&
  amount.value >= minAmount.value &&
  amount.value <= maxAmount.value &&
  termDays.value >= minTerm.value &&
  termDays.value <= maxTerm.value &&
  acceptContract.value,
)

const formatMoney = (n: number) => new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(n)

const loadOffer = async () => {
  loading.value = true
  try {
    const res = await v2.applicant.application.get(applicationId.value)
    const app = res.data
    const co = ((app as unknown as { counter_offer?: CounterOffer })?.counter_offer ?? null)
    if (!co) {
      toast.error('No hay oferta disponible')
      return
    }
    offer.value = co
    amount.value = Number(co.amount ?? co.max_amount ?? co.min_amount ?? 0)
    termDays.value = Number(co.term_days ?? co.max_term_days ?? co.min_term_days ?? 0)
  } finally {
    loading.value = false
  }
}

const respond = async (accept: boolean) => {
  submitting.value = true
  try {
    await v2.applicant.application.respondToCounterOffer(applicationId.value, {
      accept,
      amount: accept ? amount.value : undefined,
      term_days: accept ? termDays.value : undefined,
    })
    if (accept) {
      accepted.value = true
      toast.success('¡Préstamo aceptado!')
      router.replace({ name: 'm-processing', params: { id: applicationId.value } })
    } else {
      toast.success('Oferta rechazada')
      router.replace({ name: 'dashboard' })
    }
  } catch (e) {
    toast.error('No fue posible procesar la oferta')
  } finally {
    submitting.value = false
  }
}

onMounted(() => {
  loadOffer()
  timerId = window.setInterval(() => { now.value = Date.now() }, 1000)
})

onUnmounted(() => {
  if (timerId) window.clearInterval(timerId)
})
</script>

<template>
  <div class="min-h-screen bg-gray-50 pb-32">
    <header class="bg-white border-b border-gray-200 px-4 py-3 sticky top-0 z-10">
      <h1 class="text-base font-semibold text-gray-900 text-center">Oferta de préstamo</h1>
    </header>

    <div v-if="loading" class="flex justify-center pt-20">
      <div class="w-10 h-10 border-2 border-primary-600 border-t-transparent rounded-full animate-spin" />
    </div>

    <div v-else-if="offer" class="px-4 py-5 space-y-4">
      <div class="bg-gradient-to-br from-primary-600 to-primary-700 text-white rounded-2xl p-5 shadow-md">
        <p class="text-sm opacity-90">¡Felicidades, {{ tenantStore.tenant?.name || 'cliente' }}!</p>
        <h2 class="text-xl font-bold mt-1">Tu préstamo fue preaprobado</h2>
        <p class="text-sm opacity-90 mt-2">
          Este primer crédito construye tu historial. Paga puntual y podrás acceder a un monto mayor.
        </p>
      </div>

      <!-- Timer -->
      <div
        v-if="remainingLabel"
        class="rounded-xl p-3 flex items-center justify-between text-sm"
        :class="expired ? 'bg-red-50 text-red-700' : 'bg-amber-50 text-amber-800'"
      >
        <span>{{ expired ? 'Oferta expirada' : 'Oferta disponible por' }}</span>
        <span class="font-mono font-bold text-base">{{ remainingLabel }}</span>
      </div>

      <!-- Slider monto -->
      <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100">
        <label class="block text-sm text-gray-600">Monto a recibir</label>
        <div class="text-3xl font-bold text-gray-900 mt-1">{{ formatMoney(amount) }}</div>
        <input
          type="range"
          :min="minAmount"
          :max="maxAmount"
          step="100"
          v-model.number="amount"
          class="w-full mt-3 accent-primary-600"
          :disabled="minAmount === maxAmount"
        />
        <div class="flex justify-between text-xs text-gray-400 mt-1">
          <span>{{ formatMoney(minAmount) }}</span>
          <span>{{ formatMoney(maxAmount) }}</span>
        </div>
      </div>

      <!-- Slider plazo -->
      <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100">
        <label class="block text-sm text-gray-600">Plazo</label>
        <div class="text-3xl font-bold text-gray-900 mt-1">{{ termDays }} días</div>
        <input
          type="range"
          :min="minTerm"
          :max="maxTerm"
          step="1"
          v-model.number="termDays"
          class="w-full mt-3 accent-primary-600"
          :disabled="minTerm === maxTerm"
        />
        <div class="flex justify-between text-xs text-gray-400 mt-1">
          <span>{{ minTerm }} días</span>
          <span>{{ maxTerm }} días</span>
        </div>
      </div>

      <!-- Resumen -->
      <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100 space-y-2 text-sm">
        <div class="flex justify-between text-gray-600">
          <span>Interés ordinario</span>
          <span class="font-medium text-gray-900">{{ formatMoney(interestAmount) }}</span>
        </div>
        <div class="flex justify-between text-gray-600">
          <span>IVA del interés (16%)</span>
          <span class="font-medium text-gray-900">{{ formatMoney(ivaInterest) }}</span>
        </div>
        <div v-if="offer?.cat" class="flex justify-between text-gray-600">
          <span>CAT informativo</span>
          <span class="font-medium text-gray-900">{{ offer.cat }}%</span>
        </div>
        <div class="border-t border-gray-100 pt-2 mt-2 flex justify-between">
          <span class="text-gray-900 font-semibold">Total a pagar</span>
          <span class="text-xl font-bold text-primary-700">{{ formatMoney(totalToPay) }}</span>
        </div>
        <div class="flex justify-between text-gray-600">
          <span>Fecha límite de pago</span>
          <span class="font-medium text-gray-900">{{ dueDateLabel }}</span>
        </div>
      </div>

      <!-- Contrato -->
      <label class="bg-white rounded-2xl p-4 shadow-sm border border-gray-100 flex items-start gap-3 cursor-pointer">
        <input
          v-model="acceptContract"
          type="checkbox"
          class="mt-1 w-5 h-5 rounded border-gray-300 text-primary-600 focus:ring-primary-500"
        />
        <span class="text-sm text-gray-700">
          Acepto los términos y condiciones del contrato. Al aceptar, otorgas tu consentimiento electrónico para formalizar esta oferta.
          <a class="text-primary-600 font-semibold ml-1" href="javascript:void(0)" @click.prevent>Ver contrato</a>
        </span>
      </label>
    </div>

    <!-- Footer fijo -->
    <div
      v-if="offer && !accepted"
      class="fixed bottom-0 inset-x-0 bg-white border-t border-gray-200 px-4 py-3 space-y-2 z-20"
      style="padding-bottom: max(0.75rem, env(safe-area-inset-bottom));"
    >
      <AppButton
        variant="primary"
        size="lg"
        class="w-full"
        :disabled="!canSubmit"
        :loading="submitting"
        @click="respond(true)"
      >
        Recibir mi préstamo ahora
      </AppButton>
      <button
        type="button"
        class="w-full text-center text-sm text-gray-500 py-2"
        :disabled="submitting"
        @click="respond(false)"
      >
        Rechazar oferta
      </button>
    </div>
  </div>
</template>
