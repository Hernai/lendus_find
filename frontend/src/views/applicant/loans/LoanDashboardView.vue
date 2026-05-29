<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore, useLoanStore, useTenantStore } from '@/stores'
import LoanExtensionSheet from './LoanExtensionSheet.vue'
import LoanPayModal from './LoanPayModal.vue'
import MobileBottomNav from '@/components/mobile/MobileBottomNav.vue'

const loanStore = useLoanStore()
const authStore = useAuthStore()
const tenantStore = useTenantStore()
const router = useRouter()

const tab = ref<'all' | 'active' | 'completed'>('active')
const showExtension = ref(false)
const showPay = ref(false)

const visibleLoans = computed(() => {
  if (tab.value === 'active') return loanStore.loans.filter((l) => l.status === 'ACTIVE' || l.status === 'DISBURSED')
  if (tab.value === 'completed') return loanStore.completedLoans
  return loanStore.loans
})

const active = computed(() => loanStore.activeLoan)

const daysLeft = computed(() => {
  const l = active.value
  if (!l?.due_date) return null
  const due = new Date(l.due_date).getTime()
  const now = Date.now()
  return Math.max(0, Math.ceil((due - now) / 86400000))
})

const productLabel = computed(() => {
  return tenantStore.activeProducts?.[0]?.name || 'Préstamo'
})

const formatMoney = (n: number) => new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(n)
const formatDate = (d: string | null) => {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric' })
}

const greeting = computed(() => {
  const name = (authStore.user as { first_name?: string } | null)?.first_name || 'cliente'
  return `¡Bienvenido, ${name}!`
})

onMounted(async () => {
  await loanStore.fetchAll()
})

const goToDetail = (id: string) => {
  router.push({ name: 'm-loan-detail', params: { id } })
}
</script>

<template>
  <div class="min-h-screen bg-gray-50 pb-32">
    <header class="bg-white border-b border-gray-200 px-4 py-3 sticky top-0 z-10">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-lg font-bold text-gray-900">Mis préstamos</h1>
          <p class="text-xs text-gray-500">{{ greeting }}</p>
        </div>
        <button
          v-if="active"
          type="button"
          class="text-xs text-primary-600 font-semibold"
          @click="goToDetail(active.id)"
        >
          Ver contrato
        </button>
      </div>
    </header>

    <div v-if="loanStore.isLoading && !loanStore.loans.length" class="flex justify-center pt-20">
      <div class="w-10 h-10 border-2 border-primary-600 border-t-transparent rounded-full animate-spin" />
    </div>

    <div v-else class="p-4 space-y-4">
      <!-- Préstamo activo -->
      <div v-if="active" class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100 space-y-3">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm text-gray-500">{{ productLabel }}</p>
            <p class="text-lg font-bold text-gray-900">{{ formatMoney(active.principal_amount) }}</p>
          </div>
          <span class="px-2.5 py-1 bg-green-100 text-green-700 rounded-full text-xs font-semibold">Activo</span>
        </div>

        <div class="grid grid-cols-2 gap-3 text-sm pt-2 border-t border-gray-100">
          <div>
            <p class="text-xs text-gray-500">Total a pagar</p>
            <p class="font-semibold text-gray-900">{{ formatMoney(active.outstanding_balance) }}</p>
          </div>
          <div>
            <p class="text-xs text-gray-500">Vence</p>
            <p class="font-semibold text-gray-900">{{ formatDate(active.due_date) }}</p>
          </div>
          <div class="col-span-2">
            <p class="text-xs text-gray-500">
              Restan <span class="font-semibold text-primary-700">{{ daysLeft }}</span> días
            </p>
          </div>
        </div>

        <button
          type="button"
          class="w-full py-3 bg-primary-600 text-white rounded-xl font-semibold hover:bg-primary-700 active:bg-primary-800"
          @click="showPay = true"
        >
          Pagar
        </button>
      </div>

      <!-- Card prórroga -->
      <div v-if="active" class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100 space-y-2">
        <p class="text-sm font-semibold text-gray-900">Prórroga / Extensión de fecha</p>
        <p class="text-xs text-gray-500">
          Aplaza tu fecha de pago 7 o 15 días pagando una pequeña comisión.
        </p>
        <button
          type="button"
          class="w-full py-3 mt-1 bg-primary-50 text-primary-700 rounded-xl font-semibold hover:bg-primary-100"
          @click="showExtension = true"
        >
          Solicitar prórroga
        </button>
      </div>

      <!-- Card recompensas -->
      <div v-if="active" class="bg-gradient-to-br from-amber-50 to-amber-100 rounded-2xl p-5 border border-amber-200">
        <p class="text-sm font-semibold text-amber-900">Recompensas {{ tenantStore.tenant?.name || '' }}</p>
        <p class="text-xs text-amber-800 mt-1">
          Paga puntual y gana puntos. Invita amigos y obtén bonificaciones.
        </p>
      </div>

      <!-- Tabs y lista -->
      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="grid grid-cols-3 border-b border-gray-100 text-sm font-medium">
          <button
            type="button"
            class="py-3 transition-colors"
            :class="tab === 'all' ? 'text-primary-700 border-b-2 border-primary-600' : 'text-gray-500'"
            @click="tab = 'all'"
          >Todos</button>
          <button
            type="button"
            class="py-3 transition-colors"
            :class="tab === 'active' ? 'text-primary-700 border-b-2 border-primary-600' : 'text-gray-500'"
            @click="tab = 'active'"
          >Actuales</button>
          <button
            type="button"
            class="py-3 transition-colors"
            :class="tab === 'completed' ? 'text-primary-700 border-b-2 border-primary-600' : 'text-gray-500'"
            @click="tab = 'completed'"
          >Finalizados</button>
        </div>

        <div v-if="visibleLoans.length === 0" class="p-6 text-center text-sm text-gray-500">
          Aún no tienes préstamos en esta categoría.
        </div>
        <ul v-else class="divide-y divide-gray-100">
          <li
            v-for="l in visibleLoans"
            :key="l.id"
            class="px-4 py-3 flex items-center justify-between cursor-pointer active:bg-gray-50"
            @click="goToDetail(l.id)"
          >
            <div>
              <p class="text-sm font-medium text-gray-900">{{ formatMoney(l.principal_amount) }}</p>
              <p class="text-xs text-gray-500">{{ l.term_days }} días · vence {{ formatDate(l.due_date) }}</p>
            </div>
            <span
              class="text-xs px-2 py-0.5 rounded-full font-medium"
              :class="{
                'bg-green-100 text-green-700': l.status === 'ACTIVE' || l.status === 'DISBURSED',
                'bg-gray-100 text-gray-700': l.status === 'COMPLETED',
                'bg-red-100 text-red-700': l.status === 'DEFAULT',
              }"
            >{{ l.status }}</span>
          </li>
        </ul>
      </div>
    </div>

    <!-- Bottom sheets / modales -->
    <LoanExtensionSheet
      v-if="active"
      :open="showExtension"
      :loan-id="active.id"
      @close="showExtension = false"
      @success="loanStore.fetchAll()"
    />
    <LoanPayModal
      v-if="active"
      :open="showPay"
      :loan="active"
      @close="showPay = false"
      @success="loanStore.fetchAll()"
    />

    <MobileBottomNav />
  </div>
</template>
