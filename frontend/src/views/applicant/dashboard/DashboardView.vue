<script setup lang="ts">
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore, useTenantStore, useProfileStore, useApplicationStore, useOnboardingStore } from '@/stores'
import { AppButton } from '@/components/common'
import { v2, type V2Application } from '@/services/v2'
import { useWebSocket } from '@/composables/useWebSocket'
import type { ApplicationStatusChangedEvent, DocumentStatusChangedEvent } from '@/types/realtime'
import { logger } from '@/utils/logger'
import { formatMoney, formatDateShort } from '@/utils/formatters'

const log = logger.child('Dashboard')
const router = useRouter()
const authStore = useAuthStore()
const tenantStore = useTenantStore()
const profileStore = useProfileStore()
const applicationStore = useApplicationStore()
const onboardingStore = useOnboardingStore()

interface PendingDocument {
  type: string
  label: string
  description: string
  required: boolean
}

interface Application {
  id: string
  folio: string
  status: string
  product_name: string
  requested_amount: number
  term_months: number
  created_at: string
  updated_at: string
  next_action?: string
  pending_documents?: PendingDocument[]
  // Rejection info
  has_rejected_items?: boolean
  rejected_fields_count?: number
  rejected_documents_count?: number
}

const isLoading = ref(true)
const isCanceling = ref(false)
const showCancelConfirm = ref(false)
const showCancelled = ref(false)
const applicationToCancel = ref<Application | null>(null)
const applications = ref<Application[]>([])

// Carousel state for empty state
const currentSlide = ref(0)
const autoPlayInterval = ref<number | null>(null)

const filteredApplications = computed(() => {
  if (showCancelled.value) {
    return applications.value
  }
  return applications.value.filter(app => app.status !== 'CANCELLED')
})

const cancelledCount = computed(() => {
  return applications.value.filter(app => app.status === 'CANCELLED').length
})

// Computed refs for WebSocket (to allow reactive reconnection when tenant/profile loads)
const tenantIdRef = computed(() => tenantStore.tenant?.id)
const applicantIdRef = computed(() => profileStore.profile?.id)

// WebSocket connection for real-time updates
useWebSocket({
  tenantId: tenantIdRef,
  applicantId: applicantIdRef,
  onApplicationStatusChanged: (event: ApplicationStatusChangedEvent) => {
    log.info('Solicitud cambió a:', event.new_status)
    loadApplications() // Recargar lista de aplicaciones
  },
  onDocumentStatusChanged: (event: DocumentStatusChangedEvent) => {
    log.info('Documento actualizado:', { type: event.type, status: event.new_status })
    loadApplications() // Recargar lista
  },
})

// Load applications from API
const loadApplications = async () => {
  try {
    const response = await v2.applicant.application.list()
    if (response.success && response.data) {
      applications.value = response.data.applications.map((app: V2Application) => ({
        id: app.id,
        folio: app.folio || '',
        status: app.status,
        product_name: app.product?.name || 'Crédito',
        requested_amount: app.requested_amount,
        term_months: app.requested_term_months || app.term_months || 12,
        created_at: app.created_at,
        updated_at: app.updated_at,
        next_action: getNextAction(app.status, app.has_rejected_items),
        pending_documents: app.pending_documents,
        // Rejection info
        has_rejected_items: app.has_rejected_items,
        rejected_fields_count: app.rejected_fields_count,
        rejected_documents_count: app.rejected_documents_count,
      }))
    }
  } catch (e) {
    log.error('Failed to load applications:', e)
    applications.value = []
  }
}

// Load data on mount
onMounted(async () => {
  await tenantStore.loadConfig()

  // Load profile data to get the user's name
  await profileStore.loadProfile()

  // Load applications
  await loadApplications()

  isLoading.value = false
})

const getNextAction = (status: string, hasRejectedItems?: boolean): string | undefined => {
  // If there are rejected items, show correction action regardless of status
  if (hasRejectedItems && !['CANCELLED', 'REJECTED', 'APPROVED', 'DISBURSED'].includes(status)) {
    return 'Corregir datos rechazados'
  }

  switch (status) {
    case 'DRAFT':
      return 'Completa tu solicitud'
    case 'DOCS_PENDING':
      return 'Subir documentos faltantes'
    case 'CORRECTIONS_PENDING':
      return 'Corregir datos rechazados'
    case 'COUNTER_OFFERED':
      return 'Revisar contraoferta'
    case 'SUBMITTED':
      return 'Esperando revisión'
    case 'IN_REVIEW':
      return 'En análisis por un asesor'
    case 'APPROVED':
      return 'Solicitud aprobada'
    case 'DISBURSED':
      return 'Fondos recibidos'
    default:
      return undefined
  }
}

const userName = computed(() => {
  // First try profile's first name
  const profile = profileStore.profile
  if (profile?.personal_data?.first_name) {
    // Capitalize first letter, rest lowercase
    const name = profile.personal_data.first_name.toLowerCase()
    return name.charAt(0).toUpperCase() + name.slice(1)
  }
  // Fallback to email
  const user = authStore.user
  if (user?.email) {
    return user.email.split('@')[0]
  }
  return 'Usuario'
})

const tenantName = computed(() => tenantStore.name || 'LendusFind')

// Tenant contact info
const supportPhone = computed(() => tenantStore.contact?.phone)
const supportWhatsapp = computed(() => tenantStore.contact?.whatsapp || tenantStore.contact?.phone)

// Format phone for display (add spaces for readability)
const formattedPhone = computed(() => {
  const phone = supportPhone.value
  if (!phone) return null
  // Remove country code if present and format as XX XXXX XXXX
  const cleaned = phone.replace(/\D/g, '').slice(-10)
  if (cleaned.length === 10) {
    return `${cleaned.slice(0, 2)} ${cleaned.slice(2, 6)} ${cleaned.slice(6)}`
  }
  return phone
})

// Build phone URL (with country code)
const phoneUrl = computed(() => {
  const phone = supportPhone.value
  if (!phone) return null
  // Ensure it has country code
  const cleaned = phone.replace(/\D/g, '')
  return `tel:+${cleaned.startsWith('52') ? cleaned : '52' + cleaned}`
})

// Build WhatsApp URL
const whatsappUrl = computed(() => {
  const phone = supportWhatsapp.value
  if (!phone) return null
  // Ensure it has country code
  const cleaned = phone.replace(/\D/g, '')
  return `https://wa.me/${cleaned.startsWith('52') ? cleaned : '52' + cleaned}`
})

// Terminal statuses that allow creating a new application
const terminalStatuses = ['REJECTED', 'CANCELLED', 'SYNCED']

// Check if user has any active (non-terminal) application
const hasActiveApplication = computed(() => {
  return applications.value.some(app => !terminalStatuses.includes(app.status))
})

// Carousel slides for empty state
const carouselSlides = computed(() => {
  const slides = []

  // Slide 1: Welcome with tenant info
  slides.push({
    type: 'welcome',
    title: `Bienvenido a ${tenantName.value}`,
    description: 'Tu financiera de confianza. Obtén el crédito que necesitas con tasas competitivas y proceso 100% digital.',
    icon: 'building',
  })

  // Slides 2+: Products (one per product)
  const products = tenantStore.activeProducts || []
  products.forEach(product => {
    slides.push({
      type: 'product',
      title: product.name,
      description: product.description || `Crédito ${product.name.toLowerCase()} con aprobación rápida`,
      productId: product.id,
      icon: 'cash',
    })
  })

  // Final slide: Benefits
  slides.push({
    type: 'benefits',
    title: '¿Por qué elegirnos?',
    description: 'Proceso rápido, 100% digital, aprobación en 24 horas y depósito directo a tu cuenta.',
    icon: 'star',
  })

  return slides
})

// Carousel controls
const nextSlide = () => {
  currentSlide.value = (currentSlide.value + 1) % carouselSlides.value.length
}

const prevSlide = () => {
  currentSlide.value = currentSlide.value === 0
    ? carouselSlides.value.length - 1
    : currentSlide.value - 1
}

const goToSlide = (index: number) => {
  currentSlide.value = index
}

// Auto-play carousel
const startAutoPlay = () => {
  if (autoPlayInterval.value) return
  autoPlayInterval.value = window.setInterval(() => {
    nextSlide()
  }, 5000) // Change slide every 5 seconds
}

const stopAutoPlay = () => {
  if (autoPlayInterval.value) {
    clearInterval(autoPlayInterval.value)
    autoPlayInterval.value = null
  }
}

// Lifecycle hooks for auto-play
onMounted(() => {
  if (applications.value.length === 0) {
    startAutoPlay()
  }
})

onBeforeUnmount(() => {
  stopAutoPlay()
})

const getStatusInfo = (status: string) => {
  const statusMap: Record<string, { label: string; color: string; bg: string; icon: string; description: string }> = {
    DRAFT: {
      label: 'Borrador',
      color: 'text-gray-600',
      bg: 'bg-gray-100',
      icon: 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z',
      description: 'Completa tu solicitud para enviarla'
    },
    SUBMITTED: {
      label: 'Enviada',
      color: 'text-blue-600',
      bg: 'bg-blue-100',
      icon: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
      description: 'Tu solicitud está siendo procesada'
    },
    IN_REVIEW: {
      label: 'En Revisión',
      color: 'text-yellow-600',
      bg: 'bg-yellow-100',
      icon: 'M15 12a3 3 0 11-6 0 3 3 0 016 0z',
      description: 'Un analista está revisando tu información'
    },
    DOCS_PENDING: {
      label: 'Docs Pendientes',
      color: 'text-orange-600',
      bg: 'bg-orange-100',
      icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
      description: 'Necesitamos documentación adicional'
    },
    CORRECTIONS_PENDING: {
      label: 'Correcciones Pendientes',
      color: 'text-orange-600',
      bg: 'bg-orange-100',
      icon: 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z',
      description: 'Algunos datos necesitan corrección'
    },
    COUNTER_OFFERED: {
      label: 'Contraoferta',
      color: 'text-purple-600',
      bg: 'bg-purple-100',
      icon: 'M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4',
      description: 'Tenemos una propuesta alternativa para ti'
    },
    APPROVED: {
      label: 'Aprobada',
      color: 'text-green-600',
      bg: 'bg-green-100',
      icon: 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z',
      description: 'Tu crédito ha sido aprobado'
    },
    REJECTED: {
      label: 'Rechazada',
      color: 'text-red-600',
      bg: 'bg-red-100',
      icon: 'M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z',
      description: 'Tu solicitud no fue aprobada'
    },
    DISBURSED: {
      label: 'Desembolsada',
      color: 'text-purple-600',
      bg: 'bg-purple-100',
      icon: 'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z',
      description: 'El dinero ha sido depositado en tu cuenta'
    },
    CANCELLED: {
      label: 'Cancelada',
      color: 'text-red-500',
      bg: 'bg-red-50',
      icon: 'M6 18L18 6M6 6l12 12',
      description: 'Esta solicitud fue cancelada'
    },
    ACTIVE: {
      label: 'Activa',
      color: 'text-blue-600',
      bg: 'bg-blue-100',
      icon: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
      description: 'Tu crédito está activo'
    },
    COMPLETED: {
      label: 'Completada',
      color: 'text-green-700',
      bg: 'bg-green-50',
      icon: 'M5 13l4 4L19 7',
      description: 'Crédito liquidado exitosamente'
    },
    DEFAULT: {
      label: 'En Mora',
      color: 'text-red-700',
      bg: 'bg-red-100',
      icon: 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z',
      description: 'Hay pagos pendientes'
    }
  }
  return statusMap[status] || statusMap.SUBMITTED
}

const handleLogout = async () => {
  await authStore.logout()
  router.push('/')
}

const viewApplication = (app: Application) => {
  router.push(`/solicitud/${app.id}/estado`)
}

const startNewApplication = () => {
  // Clear any previous application state before starting a new one
  log.info('Starting new application - clearing previous state')

  // Reset application store state
  applicationStore.reset()

  // Clear localStorage references to previous application
  localStorage.removeItem('current_application_id')
  localStorage.removeItem('pending_application')

  // Set flag to indicate this is intentionally a new application
  // This prevents OnboardingLayout from loading existing drafts
  localStorage.setItem('start_new_application', 'true')

  // Reset onboarding store data
  onboardingStore.reset()

  // Navigate to start new application flow
  router.push('/solicitud')
}

const uploadDocs = (app: Application) => {
  router.push(`/solicitud/${app.id}/documentos`)
}

const correctData = () => {
  router.push('/correcciones')
}

const acceptCounterOffer = async (app: Application) => {
  try {
    await v2.applicant.application.respondToCounterOffer(app.id, { accept: true })
    // Reload applications
    await loadApplications()
  } catch (e) {
    log.error('Failed to accept counter offer:', e)
  }
}

const rejectCounterOffer = async (app: Application) => {
  try {
    await v2.applicant.application.respondToCounterOffer(app.id, { accept: false, reason: 'Rechazado por el solicitante' })
    // Reload applications
    await loadApplications()
  } catch (e) {
    log.error('Failed to reject counter offer:', e)
  }
}

const canCancel = (status: string) => {
  return ['DRAFT', 'SUBMITTED', 'IN_REVIEW', 'DOCS_PENDING'].includes(status)
}

const confirmCancel = (app: Application) => {
  applicationToCancel.value = app
  showCancelConfirm.value = true
}

const handleCancelApplication = async () => {
  if (!applicationToCancel.value) return

  isCanceling.value = true

  try {
    await v2.applicant.application.cancel(applicationToCancel.value.id, 'Cancelado por el solicitante')
    // Update status in the list
    const app = applications.value.find(a => a.id === applicationToCancel.value?.id)
    if (app) {
      app.status = 'CANCELLED'
      app.next_action = undefined
      app.pending_documents = undefined
    }
    showCancelConfirm.value = false
    applicationToCancel.value = null
  } catch (e) {
    log.error('Failed to cancel application:', e)
  } finally {
    isCanceling.value = false
  }
}
</script>

<template>
  <div class="min-h-screen bg-gray-50">
    <!-- Header -->
    <header class="bg-gradient-to-br from-primary-600 to-primary-700 px-4 pt-3 pb-12">
      <div class="max-w-2xl mx-auto">
        <!-- Top bar: Logo + Actions -->
        <div class="flex items-center justify-between mb-3">
          <div class="flex items-center gap-2">
            <div class="w-8 h-8 bg-white/20 rounded-lg flex items-center justify-center">
              <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
              </svg>
            </div>
            <span class="text-white font-semibold text-sm">{{ tenantName }}</span>
          </div>
          <div class="flex items-center gap-1.5">
            <router-link
              to="/perfil"
              class="flex items-center gap-1.5 px-2.5 py-1.5 bg-white/10 rounded-lg text-white text-xs hover:bg-white/20 transition-colors"
            >
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
              </svg>
              Perfil
            </router-link>
            <button
              class="flex items-center gap-1.5 px-2.5 py-1.5 bg-white/10 rounded-lg text-white text-xs hover:bg-white/20 transition-colors"
              @click="handleLogout"
            >
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
              </svg>
              Salir
            </button>
          </div>
        </div>

        <!-- Greeting + Toggle -->
        <div class="flex items-center justify-between">
          <h1 class="text-xl font-bold text-white">Hola, {{ userName }}</h1>
          <!-- Toggle Cancelled -->
          <button
            v-if="cancelledCount > 0"
            :class="[
              'flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-medium transition-colors',
              showCancelled
                ? 'bg-red-500/20 text-red-200 hover:bg-red-500/30'
                : 'bg-white/10 text-white/80 hover:bg-white/20'
            ]"
            @click="showCancelled = !showCancelled"
          >
            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path v-if="showCancelled" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
              <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
              <path v-if="!showCancelled" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
            </svg>
            {{ showCancelled ? 'Ocultar' : 'Ver' }} ({{ cancelledCount }})
          </button>
        </div>
      </div>
    </header>

    <!-- Main Content -->
    <main class="max-w-2xl mx-auto px-4 -mt-6">
      <!-- Loading State -->
      <div v-if="isLoading" class="bg-white rounded-2xl shadow-lg p-8 text-center">
        <div class="animate-spin w-8 h-8 border-4 border-primary-600 border-t-transparent rounded-full mx-auto" />
        <p class="text-gray-500 mt-4">Cargando tus solicitudes...</p>
      </div>

      <!-- Applications List -->
      <template v-else>
        <!-- Has Applications -->
        <template v-if="filteredApplications.length > 0 || cancelledCount > 0">
          <div class="space-y-4">
            <div
              v-for="app in filteredApplications"
              :key="app.id"
              class="bg-white rounded-2xl shadow-lg overflow-hidden"
            >
              <!-- Status Banner -->
              <div :class="[getStatusInfo(app.status)?.bg ?? 'bg-gray-100', 'px-6 py-3']">
                <div class="flex items-center justify-between">
                  <div class="flex items-center gap-2">
                    <svg :class="['w-5 h-5', getStatusInfo(app.status)?.color ?? 'text-gray-600']" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="getStatusInfo(app.status)?.icon ?? ''" />
                    </svg>
                    <span :class="['font-medium', getStatusInfo(app.status)?.color ?? 'text-gray-600']">
                      {{ getStatusInfo(app.status)?.label ?? app.status }}
                    </span>
                  </div>
                  <span class="text-sm text-gray-500 font-mono">{{ app.folio }}</span>
                </div>
                <p class="text-sm text-gray-600 mt-1">{{ getStatusInfo(app.status)?.description ?? '' }}</p>
              </div>

              <!-- Application Details -->
              <div class="p-6">
                <div class="flex items-start justify-between mb-4">
                  <div>
                    <p class="text-sm text-gray-500">{{ app.product_name }}</p>
                    <p class="text-2xl font-bold text-gray-900">{{ formatMoney(app.requested_amount) }}</p>
                    <p class="text-sm text-gray-500">{{ app.term_months }} meses</p>
                  </div>
                  <div class="text-right text-sm text-gray-500">
                    <p>Creada</p>
                    <p class="font-medium text-gray-700">{{ formatDateShort(app.created_at) }}</p>
                  </div>
                </div>

                <!-- Rejected Items Alert -->
                <div v-if="app.has_rejected_items" class="bg-red-50 rounded-xl p-4 mb-4">
                  <div class="flex items-start gap-3">
                    <svg class="w-5 h-5 text-red-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    <div class="min-w-0 flex-1">
                      <p class="font-medium text-red-800">
                        Datos rechazados que requieren corrección
                      </p>
                      <p class="text-sm text-red-700 mt-1">
                        <template v-if="app.rejected_fields_count && app.rejected_fields_count > 0">
                          {{ app.rejected_fields_count }} campo{{ app.rejected_fields_count > 1 ? 's' : '' }}
                        </template>
                        <template v-if="app.rejected_fields_count && app.rejected_documents_count">, </template>
                        <template v-if="app.rejected_documents_count && app.rejected_documents_count > 0">
                          {{ app.rejected_documents_count }} documento{{ app.rejected_documents_count > 1 ? 's' : '' }}
                        </template>
                      </p>
                    </div>
                  </div>
                </div>

                <!-- Pending Documents Alert -->
                <div v-if="app.pending_documents && app.pending_documents.length > 0" class="bg-orange-50 rounded-xl p-4 mb-4">
                  <div class="flex items-start gap-3">
                    <svg class="w-5 h-5 text-orange-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    <div class="min-w-0 flex-1">
                      <p class="font-medium text-orange-800">Faltan {{ app.pending_documents.length }} documento{{ app.pending_documents.length > 1 ? 's' : '' }}</p>
                      <ul class="text-sm text-orange-700 mt-1 space-y-1">
                        <li v-for="doc in app.pending_documents" :key="doc.type" class="flex items-start gap-2">
                          <svg class="w-4 h-4 text-orange-400 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                          </svg>
                          <span>{{ doc.label }}</span>
                        </li>
                      </ul>
                    </div>
                  </div>
                </div>

                <!-- Next Action -->
                <div v-if="app.next_action" class="bg-primary-50 rounded-xl p-4 mb-4">
                  <div class="flex items-center gap-3">
                    <div class="w-8 h-8 bg-primary-100 rounded-full flex items-center justify-center">
                      <svg class="w-4 h-4 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                      </svg>
                    </div>
                    <div>
                      <p class="text-sm text-primary-600 font-medium">Próximo paso</p>
                      <p class="text-primary-800">{{ app.next_action }}</p>
                    </div>
                  </div>
                </div>

                <!-- Actions -->
                <div class="flex gap-3">
                  <AppButton
                    v-if="app.pending_documents && app.pending_documents.length > 0"
                    variant="primary"
                    class="flex-1"
                    @click="uploadDocs(app)"
                  >
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                    </svg>
                    Subir Documentos
                  </AppButton>
                  <AppButton
                    v-if="app.status === 'CORRECTIONS_PENDING' || app.has_rejected_items"
                    variant="primary"
                    class="flex-1"
                    @click="correctData()"
                  >
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                    Corregir Datos
                  </AppButton>
                  <AppButton
                    v-if="app.status === 'COUNTER_OFFERED'"
                    variant="primary"
                    class="flex-1"
                    @click="acceptCounterOffer(app)"
                  >
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    Aceptar
                  </AppButton>
                  <AppButton
                    v-if="app.status === 'COUNTER_OFFERED'"
                    variant="outline"
                    class="flex-1"
                    @click="rejectCounterOffer(app)"
                  >
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                    Rechazar
                  </AppButton>
                  <AppButton
                    v-if="!['CORRECTIONS_PENDING', 'COUNTER_OFFERED'].includes(app.status) && !(app.pending_documents && app.pending_documents.length > 0) && !app.has_rejected_items"
                    variant="outline"
                    class="flex-1"
                    @click="viewApplication(app)"
                  >
                    Ver Detalle
                  </AppButton>
                </div>

                <!-- Cancel Button -->
                <div v-if="canCancel(app.status)" class="mt-3">
                  <button
                    class="w-full py-2.5 px-4 border border-red-200 rounded-xl text-red-600 text-sm font-medium hover:bg-red-50 hover:border-red-300 transition-colors"
                    @click="confirmCancel(app)"
                  >
                    Cancelar solicitud
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- Promotional Carousel (when user has applications) -->
          <div class="mt-6 bg-white rounded-2xl shadow-lg overflow-hidden">
            <!-- Carousel -->
            <div class="relative" @mouseenter="stopAutoPlay" @mouseleave="startAutoPlay">
              <!-- Slides -->
              <div class="relative h-64 overflow-hidden">
                <TransitionGroup name="carousel">
                  <div
                    v-for="(slide, index) in carouselSlides"
                    v-show="index === currentSlide"
                    :key="index"
                    class="absolute inset-0 flex items-center justify-center p-6"
                  >
                    <div class="text-center max-w-md">
                      <!-- Icon -->
                      <div class="w-16 h-16 bg-primary-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <!-- Building icon -->
                        <svg v-if="slide.icon === 'building'" class="w-8 h-8 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                        </svg>
                        <!-- Cash icon -->
                        <svg v-else-if="slide.icon === 'cash'" class="w-8 h-8 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <!-- Star icon -->
                        <svg v-else-if="slide.icon === 'star'" class="w-8 h-8 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
                        </svg>
                      </div>

                      <!-- Content -->
                      <h3 class="text-xl font-bold text-gray-900 mb-2">{{ slide.title }}</h3>
                      <p class="text-gray-600 text-sm leading-relaxed">{{ slide.description }}</p>
                    </div>
                  </div>
                </TransitionGroup>
              </div>

              <!-- Navigation Arrows -->
              <button
                v-if="carouselSlides.length > 1"
                @click="prevSlide"
                class="absolute left-3 top-1/2 -translate-y-1/2 w-8 h-8 bg-white/90 hover:bg-white rounded-full shadow-lg flex items-center justify-center transition-all hover:scale-110"
                aria-label="Slide anterior"
              >
                <svg class="w-4 h-4 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
              </button>
              <button
                v-if="carouselSlides.length > 1"
                @click="nextSlide"
                class="absolute right-3 top-1/2 -translate-y-1/2 w-8 h-8 bg-white/90 hover:bg-white rounded-full shadow-lg flex items-center justify-center transition-all hover:scale-110"
                aria-label="Slide siguiente"
              >
                <svg class="w-4 h-4 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
              </button>

              <!-- Dots Indicator -->
              <div v-if="carouselSlides.length > 1" class="absolute bottom-3 left-0 right-0 flex justify-center gap-1.5">
                <button
                  v-for="(slide, index) in carouselSlides"
                  :key="index"
                  @click="goToSlide(index)"
                  :class="[
                    'h-1.5 rounded-full transition-all',
                    index === currentSlide
                      ? 'w-6 bg-primary-600'
                      : 'w-1.5 bg-gray-300 hover:bg-gray-400'
                  ]"
                  :aria-label="`Ir a slide ${index + 1}`"
                />
              </div>
            </div>

            <!-- CTA Section -->
            <div v-if="!hasActiveApplication" class="px-6 py-4 border-t border-gray-100 bg-gray-50">
              <button
                @click="startNewApplication"
                class="w-full py-3 bg-primary-600 hover:bg-primary-700 text-white font-semibold rounded-xl transition-colors"
              >
                Explorar Productos
              </button>
            </div>
          </div>

          <!-- New Application CTA (only show if no active application) -->
          <div v-if="!hasActiveApplication" class="mt-6 text-center">
            <button
              class="text-primary-600 font-medium hover:text-primary-700"
              @click="startNewApplication"
            >
              + Nueva solicitud
            </button>
          </div>
        </template>

        <!-- No Applications - Carousel -->
        <template v-else>
          <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
            <!-- Carousel -->
            <div class="relative" @mouseenter="stopAutoPlay" @mouseleave="startAutoPlay">
              <!-- Slides -->
              <div class="relative h-80 overflow-hidden">
                <TransitionGroup name="carousel">
                  <div
                    v-for="(slide, index) in carouselSlides"
                    v-show="index === currentSlide"
                    :key="index"
                    class="absolute inset-0 flex items-center justify-center p-8"
                  >
                    <div class="text-center max-w-md">
                      <!-- Icon -->
                      <div class="w-20 h-20 bg-primary-100 rounded-full flex items-center justify-center mx-auto mb-6">
                        <!-- Building icon -->
                        <svg v-if="slide.icon === 'building'" class="w-10 h-10 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                        </svg>
                        <!-- Cash icon -->
                        <svg v-else-if="slide.icon === 'cash'" class="w-10 h-10 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <!-- Star icon -->
                        <svg v-else-if="slide.icon === 'star'" class="w-10 h-10 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
                        </svg>
                      </div>

                      <!-- Content -->
                      <h2 class="text-2xl font-bold text-gray-900 mb-3">{{ slide.title }}</h2>
                      <p class="text-gray-600 leading-relaxed">{{ slide.description }}</p>
                    </div>
                  </div>
                </TransitionGroup>
              </div>

              <!-- Navigation Arrows -->
              <button
                v-if="carouselSlides.length > 1"
                @click="prevSlide"
                class="absolute left-4 top-1/2 -translate-y-1/2 w-10 h-10 bg-white/90 hover:bg-white rounded-full shadow-lg flex items-center justify-center transition-all hover:scale-110"
                aria-label="Slide anterior"
              >
                <svg class="w-5 h-5 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
              </button>
              <button
                v-if="carouselSlides.length > 1"
                @click="nextSlide"
                class="absolute right-4 top-1/2 -translate-y-1/2 w-10 h-10 bg-white/90 hover:bg-white rounded-full shadow-lg flex items-center justify-center transition-all hover:scale-110"
                aria-label="Slide siguiente"
              >
                <svg class="w-5 h-5 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
              </button>

              <!-- Dots Indicator -->
              <div v-if="carouselSlides.length > 1" class="absolute bottom-4 left-0 right-0 flex justify-center gap-2">
                <button
                  v-for="(slide, index) in carouselSlides"
                  :key="index"
                  @click="goToSlide(index)"
                  :class="[
                    'h-2 rounded-full transition-all',
                    index === currentSlide
                      ? 'w-8 bg-primary-600'
                      : 'w-2 bg-gray-300 hover:bg-gray-400'
                  ]"
                  :aria-label="`Ir a slide ${index + 1}`"
                />
              </div>
            </div>

            <!-- CTA Button -->
            <div class="p-8 pt-6 border-t border-gray-100">
              <AppButton
                variant="primary"
                full-width
                size="lg"
                @click="startNewApplication"
              >
                Solicitar Crédito Ahora
              </AppButton>
              <p class="text-center text-sm text-gray-500 mt-3">
                Proceso 100% digital • Aprobación en 24 horas
              </p>
            </div>
          </div>
        </template>
      </template>

      <!-- Help Section -->
      <div v-if="phoneUrl || whatsappUrl" class="bg-white rounded-2xl shadow-sm p-6 mt-10 mb-8">
        <h3 class="font-semibold text-gray-900 mb-3">¿Necesitas ayuda?</h3>
        <div class="space-y-3">
          <a v-if="phoneUrl" :href="phoneUrl" class="flex items-center gap-3 text-gray-600 hover:text-primary-600">
            <div class="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
              </svg>
            </div>
            <div>
              <p class="font-medium">Llámanos</p>
              <p class="text-sm text-gray-500">{{ formattedPhone }}</p>
            </div>
          </a>
          <a v-if="whatsappUrl" :href="whatsappUrl" target="_blank" class="flex items-center gap-3 text-gray-600 hover:text-primary-600">
            <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
              <svg class="w-5 h-5 text-green-600" viewBox="0 0 24 24" fill="currentColor">
                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
              </svg>
            </div>
            <div>
              <p class="font-medium">WhatsApp</p>
              <p class="text-sm text-gray-500">Escríbenos</p>
            </div>
          </a>
        </div>
      </div>
    </main>

    <!-- Cancel Confirmation Modal -->
    <div
      v-if="showCancelConfirm"
      class="fixed inset-0 bg-black/50 flex items-center justify-center p-4 z-50"
      @click.self="showCancelConfirm = false"
    >
      <div class="bg-white rounded-2xl max-w-sm w-full p-6">
        <div class="text-center mb-6">
          <div class="w-14 h-14 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <svg class="w-7 h-7 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
          </div>
          <h3 class="text-lg font-semibold text-gray-900">¿Cancelar solicitud?</h3>
          <p v-if="applicationToCancel" class="text-sm text-gray-500 mt-1">{{ applicationToCancel.folio }}</p>
          <p class="text-gray-500 mt-2">
            Esta acción no se puede deshacer. Tu solicitud será cancelada permanentemente.
          </p>
        </div>
        <div class="space-y-3">
          <AppButton
            variant="primary"
            full-width
            class="!bg-red-600 hover:!bg-red-700"
            :loading="isCanceling"
            @click="handleCancelApplication"
          >
            Sí, cancelar solicitud
          </AppButton>
          <AppButton
            variant="outline"
            full-width
            :disabled="isCanceling"
            @click="showCancelConfirm = false"
          >
            No, continuar
          </AppButton>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* Carousel transitions */
.carousel-enter-active {
  transition: all 0.5s ease;
}

.carousel-leave-active {
  transition: all 0.5s ease;
}

.carousel-enter-from {
  opacity: 0;
  transform: translateX(30px);
}

.carousel-leave-to {
  opacity: 0;
  transform: translateX(-30px);
}
</style>
