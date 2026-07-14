<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useApplicationStore } from '@/stores'
import { AppButton, AppProgressBar } from '@/components/common'
import { logger } from '@/utils/logger'
import { formatMoney, formatFrequency } from '@/utils/formatters'
import { useToast } from '@/composables/useToast'
import { v2 } from '@/services/v2'
import type { V2CounterOffer } from '@/types/v2'

const log = logger.child('ApplicationStatusView')

const route = useRoute()
const router = useRouter()
const applicationStore = useApplicationStore()

const applicationId = computed(() => route.params.id as string)
const application = computed(() => applicationStore.currentApplication)
const simulation = computed(() => applicationStore.simulation)
const isLoading = ref(false)

// Arrendamiento: preferimos el lease_info PERSISTIDO (viene del detalle de la API en
// currentApplication) sobre el snapshot efímero del simulador, que es null si el
// usuario vuelve en otra sesión o dispositivo (restoreSimulationFromApp no rehidrata
// simulation.lease). Así "Ver Detalle" muestra la renta/desembolso siempre.
interface LeaseInfo {
  asset_type?: string | null; asset_brand?: string | null; asset_model?: string | null
  asset_year?: number | null; asset_capacity?: string | null; modality?: string | null
  asset_estimated_value?: number | null; term_months?: number | null
  monthly_rental?: number | null; first_installment_total?: number | null; purchase_option_amount?: number | null
}
const leaseInfo = computed(() => (application.value as { lease_info?: LeaseInfo | null } | null)?.lease_info ?? null)
const isLease = computed(() =>
  (application.value as { product?: { type?: string } } | null)?.product?.type === 'ARRENDAMIENTO'
  || !!simulation.value?.lease,
)
const leaseMonthly = computed(() => leaseInfo.value?.monthly_rental ?? simulation.value?.lease?.monthly_rental_with_iva ?? null)
const leaseFirstInstallment = computed(() => leaseInfo.value?.first_installment_total ?? simulation.value?.lease?.first_installment?.total ?? null)
const leaseTerm = computed(() => leaseInfo.value?.term_months ?? simulation.value?.term_months ?? null)
const ASSET_LABELS: Record<string, string> = { SOLAR_PANELS: 'Paneles solares', VEHICLE: 'Vehículo', MACHINERY: 'Maquinaria' }
const leaseAssetLabel = computed(() => {
  const li = leaseInfo.value
  const base = (li?.asset_type && ASSET_LABELS[li.asset_type]) || 'Bien a arrendar'
  const detail = li?.asset_type === 'SOLAR_PANELS'
    ? [li?.asset_brand, li?.asset_capacity].filter(Boolean).join(' ')
    : [li?.asset_brand, li?.asset_model, li?.asset_year].filter(Boolean).join(' ')
  return detail ? `${base} · ${detail}` : base
})

interface TimelineStep {
  id: string
  title: string
  description: string
  status: 'completed' | 'current' | 'pending'
  date?: string
}

const timeline = computed<TimelineStep[]>(() => {
  const status = application.value?.status || 'DRAFT'

  const steps = [
    { id: 'submitted', title: 'Solicitud enviada', description: 'Tu solicitud fue recibida', statusWhen: ['SUBMITTED', 'IN_REVIEW', 'DOCS_PENDING', 'CORRECTIONS_PENDING', 'COUNTER_OFFERED', 'APPROVED', 'REJECTED', 'SYNCED'] },
    { id: 'review', title: 'En revisión', description: 'Estamos analizando tu información', statusWhen: ['IN_REVIEW', 'DOCS_PENDING', 'CORRECTIONS_PENDING', 'COUNTER_OFFERED', 'APPROVED', 'REJECTED', 'SYNCED'] },
    { id: 'docs', title: 'Documentos verificados', description: 'Tus documentos fueron revisados', statusWhen: ['COUNTER_OFFERED', 'APPROVED', 'REJECTED', 'SYNCED'] },
    { id: 'decision', title: 'Decisión', description: 'Resultado de tu solicitud', statusWhen: ['APPROVED', 'REJECTED', 'SYNCED'] }
  ]

  const statusIndex = steps.findIndex(s => s.statusWhen.includes(status)) + 1

  return steps.map((step, index) => ({
    id: step.id,
    title: step.title,
    description: step.description,
    status: index < statusIndex ? 'completed' : index === statusIndex ? 'current' : 'pending',
    date: index < statusIndex ? application.value?.submitted_at?.split('T')[0] : undefined
  }))
})

const defaultConfig = { color: 'gray', label: 'Borrador', icon: 'edit' }

const statusConfig = computed((): { color: string; label: string; icon: string } => {
  const configs: Record<string, { color: string; label: string; icon: string }> = {
    DRAFT: { color: 'gray', label: 'Borrador', icon: 'edit' },
    SUBMITTED: { color: 'blue', label: 'Enviada', icon: 'clock' },
    IN_REVIEW: { color: 'yellow', label: 'En revisión', icon: 'search' },
    DOCS_PENDING: { color: 'orange', label: 'Documentos pendientes', icon: 'document' },
    CORRECTIONS_PENDING: { color: 'orange', label: 'Correcciones pendientes', icon: 'edit' },
    COUNTER_OFFERED: { color: 'purple', label: 'Tienes una oferta', icon: 'refresh' },
    APPROVED: { color: 'green', label: 'Aprobada', icon: 'check' },
    REJECTED: { color: 'red', label: 'Rechazada', icon: 'x' },
    SYNCED: { color: 'purple', label: 'Sincronizada', icon: 'cloud' }
  }
  const status = application.value?.status || 'DRAFT'
  return configs[status] ?? defaultConfig
})

const needsAction = computed(() => {
  const status = application.value?.status
  return status === 'CORRECTIONS_PENDING' || status === 'DOCS_PENDING'
})

const actionMessage = computed(() => {
  const status = application.value?.status
  if (status === 'CORRECTIONS_PENDING') {
    return {
      title: 'Se requieren correcciones',
      description: 'Algunos de tus datos necesitan ser actualizados. Por favor revisa y corrige la información solicitada.',
      buttonText: 'Corregir datos',
      route: '/correcciones'
    }
  }
  if (status === 'DOCS_PENDING') {
    return {
      title: 'Documentos pendientes',
      description: 'Necesitamos que subas algunos documentos para continuar con tu solicitud.',
      buttonText: 'Subir documentos',
      route: `/solicitud/${applicationId.value}/documentos`
    }
  }
  return null
})

const goToAction = () => {
  if (actionMessage.value) {
    router.push(actionMessage.value.route)
  }
}

// ==========================================================
// Contraoferta (tenants con plazo en meses; los productos en
// días usan LoanOfferView en /m/solicitud/:id/oferta)
// ==========================================================
const toast = useToast()
const respondingOffer = ref(false)

const counterOffer = computed<V2CounterOffer | null>(() => {
  const app = application.value as { status?: string; counter_offer?: V2CounterOffer | null } | null
  if (!app || app.status !== 'COUNTER_OFFERED') return null
  const co = app.counter_offer
  return co && !co.responded_at ? co : null
})

const offerExpired = computed(() => {
  const exp = counterOffer.value?.expires_at
  return exp ? new Date(exp).getTime() < Date.now() : false
})

const respondToOffer = async (accepted: boolean) => {
  respondingOffer.value = true
  try {
    await v2.applicant.application.respondToCounterOffer(applicationId.value, { accepted })
    toast.success(accepted ? '¡Oferta aceptada! Tu crédito fue aprobado.' : 'Tu solicitud ha quedado cancelada')
    await applicationStore.loadApplication(applicationId.value)
  } catch (e) {
    log.error('Failed to respond to counter offer', { error: e })
    toast.error('No fue posible procesar tu respuesta')
  } finally {
    respondingOffer.value = false
  }
}



const goHome = () => router.push('/dashboard')

onMounted(async () => {
  isLoading.value = true
  try {
    await applicationStore.loadApplication(applicationId.value)
  } catch (e) {
    log.error('Failed to load application', { error: e })
  } finally {
    isLoading.value = false
  }
})
</script>

<template>
  <div class="min-h-screen bg-gray-50">
    <!-- Header -->
    <header class="bg-white px-4 py-4 border-b sticky top-0 z-50">
      <div class="max-w-2xl mx-auto flex items-center justify-between">
        <button class="p-1 -ml-1" @click="goHome">
          <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
          </svg>
        </button>
        <h1 class="font-semibold text-gray-900">Estado de solicitud</h1>
        <div class="w-6" />
      </div>
    </header>

    <main class="max-w-2xl mx-auto px-4 py-6">
      <!-- Loading state -->
      <div v-if="isLoading" class="flex flex-col items-center justify-center py-20">
        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-primary-600 mb-4"></div>
        <p class="text-gray-500">Cargando solicitud...</p>
      </div>

      <!-- No application found -->
      <div v-else-if="!application" class="text-center py-20">
        <div class="w-16 h-16 rounded-full bg-gray-100 flex items-center justify-center mx-auto mb-4">
          <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
          </svg>
        </div>
        <h2 class="text-lg font-semibold text-gray-900 mb-2">No se encontró la solicitud</h2>
        <p class="text-gray-500 mb-6 max-w-sm mx-auto">
          Esta solicitud no existe o no tienes permiso para verla.
          Por favor verifica el enlace o regresa a tu panel.
        </p>
        <AppButton variant="primary" @click="goHome">Ir a mis solicitudes</AppButton>
      </div>

      <!-- Application content -->
      <template v-else>
      <!-- Status Card -->
      <div class="bg-white rounded-2xl shadow-sm p-6 mb-6">
        <div class="flex items-center gap-4 mb-4">
          <div
            class="w-12 h-12 rounded-xl flex items-center justify-center"
            :class="{
              'bg-gray-100': statusConfig.color === 'gray',
              'bg-blue-100': statusConfig.color === 'blue',
              'bg-yellow-100': statusConfig.color === 'yellow',
              'bg-orange-100': statusConfig.color === 'orange',
              'bg-green-100': statusConfig.color === 'green',
              'bg-red-100': statusConfig.color === 'red',
              'bg-purple-100': statusConfig.color === 'purple'
            }"
          >
            <svg
              v-if="statusConfig.icon === 'clock'"
              class="w-6 h-6 text-blue-600"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <svg
              v-else-if="statusConfig.icon === 'check'"
              class="w-6 h-6 text-green-600"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <svg
              v-else-if="statusConfig.icon === 'search'"
              class="w-6 h-6 text-yellow-600"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
            <svg
              v-else
              class="w-6 h-6 text-gray-600"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
          </div>
          <div>
            <p class="text-sm text-gray-500">Folio: {{ application?.folio }}</p>
            <p class="font-semibold text-gray-900">{{ statusConfig.label }}</p>
          </div>
        </div>

        <!-- Info: arrendamiento (bien + renta/desembolso) vs crédito (monto/pago) -->
        <template v-if="isLease">
          <div class="pt-4 border-t border-gray-100">
            <p class="text-xs text-gray-500">Bien a arrendar</p>
            <p class="font-semibold text-gray-900">{{ leaseAssetLabel }}</p>
          </div>
          <div class="grid grid-cols-3 gap-4 pt-3">
            <div>
              <p class="text-xs text-gray-500">Renta mensual</p>
              <p class="font-semibold text-gray-900">{{ leaseMonthly != null ? formatMoney(leaseMonthly) : '—' }}</p>
            </div>
            <div>
              <p class="text-xs text-gray-500">Plazo</p>
              <p class="font-semibold text-gray-900">{{ leaseTerm ?? '—' }} meses</p>
            </div>
            <div>
              <p class="text-xs text-gray-500">Desembolso inicial</p>
              <p class="font-semibold text-gray-900">{{ leaseFirstInstallment != null ? formatMoney(leaseFirstInstallment) : '—' }}</p>
            </div>
          </div>
        </template>
        <div v-else-if="simulation" class="grid grid-cols-3 gap-4 pt-4 border-t border-gray-100">
          <div>
            <p class="text-xs text-gray-500">Monto</p>
            <p class="font-semibold text-gray-900">{{ formatMoney(simulation.requested_amount) }}</p>
          </div>
          <div>
            <p class="text-xs text-gray-500">Plazo</p>
            <p class="font-semibold text-gray-900">{{ simulation.term_months }} meses</p>
          </div>
          <div>
            <p class="text-xs text-gray-500">Pago {{ formatFrequency(simulation.payment_frequency) }}</p>
            <p class="font-semibold text-gray-900">{{ formatMoney(simulation.periodic_payment) }}</p>
          </div>
        </div>
      </div>

      <!-- Contraoferta pendiente (productos en meses) -->
      <div v-if="counterOffer" class="bg-purple-50 border-2 border-purple-200 rounded-2xl p-6 mb-6">
        <div class="flex items-start gap-3 mb-4">
          <div class="w-10 h-10 rounded-full bg-purple-100 flex items-center justify-center flex-shrink-0">
            <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
            </svg>
          </div>
          <div class="flex-1">
            <h3 class="font-semibold text-purple-900 mb-1">Te proponemos una oferta ajustada</h3>
            <p class="text-sm text-purple-700">
              Revisamos tu solicitud y podemos ofrecerte estas condiciones:
            </p>
          </div>
        </div>

        <div class="bg-white rounded-xl p-4 mb-4 grid grid-cols-3 gap-4 text-center">
          <div>
            <p class="text-xs text-gray-500">Monto</p>
            <p class="font-bold text-gray-900">{{ formatMoney(counterOffer.amount) }}</p>
          </div>
          <div>
            <p class="text-xs text-gray-500">Plazo</p>
            <p class="font-bold text-gray-900">{{ counterOffer.term_months }} meses</p>
          </div>
          <div>
            <p class="text-xs text-gray-500">Tasa anual</p>
            <p class="font-bold text-gray-900">{{ counterOffer.interest_rate }}%</p>
          </div>
        </div>

        <p v-if="counterOffer.reason" class="text-sm text-purple-700 mb-4 italic">
          {{ counterOffer.reason }}
        </p>

        <div v-if="offerExpired" class="bg-red-50 text-red-700 rounded-xl p-3 text-sm text-center mb-2">
          Esta oferta ya expiró. Un asesor se pondrá en contacto contigo.
        </div>
        <template v-else>
          <AppButton
            variant="primary"
            size="lg"
            class="w-full"
            :loading="respondingOffer"
            @click="respondToOffer(true)"
          >
            Aceptar oferta
          </AppButton>
          <button
            type="button"
            class="w-full text-center text-sm text-gray-500 py-2 mt-2"
            :disabled="respondingOffer"
            @click="respondToOffer(false)"
          >
            No me interesa
          </button>
        </template>
      </div>

      <!-- Action Required Alert -->
      <div v-if="needsAction && actionMessage" class="bg-orange-50 border-2 border-orange-200 rounded-2xl p-6 mb-6">
        <div class="flex items-start gap-3 mb-4">
          <div class="w-10 h-10 rounded-full bg-orange-100 flex items-center justify-center flex-shrink-0">
            <svg class="w-5 h-5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
          </div>
          <div class="flex-1">
            <h3 class="font-semibold text-orange-900 mb-1">{{ actionMessage.title }}</h3>
            <p class="text-sm text-orange-700">{{ actionMessage.description }}</p>
          </div>
        </div>
        <AppButton variant="primary" size="lg" class="w-full" @click="goToAction">
          {{ actionMessage.buttonText }}
        </AppButton>
      </div>

      <!-- Timeline -->
      <div class="bg-white rounded-2xl shadow-sm p-6">
        <h2 class="font-semibold text-gray-900 mb-6">Seguimiento</h2>

        <div class="space-y-0">
          <div
            v-for="(step, index) in timeline"
            :key="step.id"
            class="relative pl-8"
            :class="{ 'pb-6': index < timeline.length - 1 }"
          >
            <!-- Line -->
            <div
              v-if="index < timeline.length - 1"
              class="absolute left-[11px] top-6 w-0.5 h-full"
              :class="{
                'bg-primary-500': step.status === 'completed',
                'bg-gray-200': step.status !== 'completed'
              }"
            />

            <!-- Dot -->
            <div
              class="absolute left-0 w-6 h-6 rounded-full flex items-center justify-center"
              :class="{
                'bg-primary-500': step.status === 'completed',
                'bg-primary-100 ring-4 ring-primary-50': step.status === 'current',
                'bg-gray-200': step.status === 'pending'
              }"
            >
              <svg
                v-if="step.status === 'completed'"
                class="w-4 h-4 text-white"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
              </svg>
              <div
                v-else-if="step.status === 'current'"
                class="w-2 h-2 bg-primary-600 rounded-full"
              />
            </div>

            <!-- Content -->
            <div>
              <h3
                class="font-medium"
                :class="{
                  'text-gray-900': step.status !== 'pending',
                  'text-gray-400': step.status === 'pending'
                }"
              >
                {{ step.title }}
              </h3>
              <p class="text-sm text-gray-500">{{ step.description }}</p>
              <p v-if="step.date" class="text-xs text-gray-400 mt-1">{{ step.date }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Help Section -->
      <div class="mt-6 bg-blue-50 rounded-2xl p-6">
        <h3 class="font-medium text-blue-900 mb-2">¿Tienes dudas?</h3>
        <p class="text-sm text-blue-700 mb-4">
          Nuestro equipo está disponible para ayudarte con cualquier pregunta.
        </p>
        <AppButton variant="outline" size="sm">
          Contactar soporte
        </AppButton>
      </div>

      <!-- Go to Dashboard -->
      <div class="mt-6 text-center">
        <AppButton variant="primary" size="lg" class="w-full" @click="goHome">
          Ver mis solicitudes
        </AppButton>
      </div>
      </template>
    </main>
  </div>
</template>
