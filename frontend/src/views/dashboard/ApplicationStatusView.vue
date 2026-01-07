<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useApplicationStore } from '@/stores'
import { AppButton, AppProgressBar } from '@/components/common'

const route = useRoute()
const router = useRouter()
const applicationStore = useApplicationStore()

const applicationId = computed(() => route.params.id as string)
const application = computed(() => applicationStore.currentApplication)
const simulation = computed(() => applicationStore.simulation)
const isLoading = ref(false)

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
    { id: 'submitted', title: 'Solicitud enviada', description: 'Tu solicitud fue recibida', statusWhen: ['SUBMITTED', 'IN_REVIEW', 'DOCS_PENDING', 'APPROVED', 'REJECTED', 'SYNCED'] },
    { id: 'review', title: 'En revisión', description: 'Estamos analizando tu información', statusWhen: ['IN_REVIEW', 'DOCS_PENDING', 'APPROVED', 'REJECTED', 'SYNCED'] },
    { id: 'docs', title: 'Documentos verificados', description: 'Tus documentos fueron revisados', statusWhen: ['APPROVED', 'REJECTED', 'SYNCED'] },
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
    APPROVED: { color: 'green', label: 'Aprobada', icon: 'check' },
    REJECTED: { color: 'red', label: 'Rechazada', icon: 'x' },
    SYNCED: { color: 'purple', label: 'Sincronizada', icon: 'cloud' }
  }
  const status = application.value?.status || 'DRAFT'
  return configs[status] ?? defaultConfig
})

const formatMoney = (amount: number) => {
  return new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0
  }).format(amount)
}

const frequencyLabels: Record<string, string> = {
  WEEKLY: 'semanal',
  BIWEEKLY: 'quincenal',
  MONTHLY: 'mensual'
}

const goHome = () => router.push('/dashboard')

onMounted(async () => {
  isLoading.value = true
  try {
    await applicationStore.loadApplication(applicationId.value)
  } catch (e) {
    console.error('Failed to load application:', e)
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
        <p class="text-gray-500 mb-4">No se encontró la solicitud</p>
        <AppButton variant="primary" @click="goHome">Ir al inicio</AppButton>
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

        <!-- Loan info -->
        <div v-if="simulation" class="grid grid-cols-3 gap-4 pt-4 border-t border-gray-100">
          <div>
            <p class="text-xs text-gray-500">Monto</p>
            <p class="font-semibold text-gray-900">{{ formatMoney(simulation.requested_amount) }}</p>
          </div>
          <div>
            <p class="text-xs text-gray-500">Plazo</p>
            <p class="font-semibold text-gray-900">{{ simulation.term_months }} meses</p>
          </div>
          <div>
            <p class="text-xs text-gray-500">Pago {{ frequencyLabels[simulation.payment_frequency] }}</p>
            <p class="font-semibold text-gray-900">{{ formatMoney(simulation.periodic_payment) }}</p>
          </div>
        </div>
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
