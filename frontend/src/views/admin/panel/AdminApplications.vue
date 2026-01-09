<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { api } from '@/services/api'
import { AppButton, AppConfirmModal } from '@/components/common'

const router = useRouter()

interface Application {
  id: string
  folio: string
  applicant: {
    id: string
    name: string
    phone: string
    email: string
  } | null
  product: {
    id: string
    name: string
    type: string
  } | null
  requested_amount: number
  approved_amount: number | null
  term_months: number
  payment_frequency: string
  monthly_payment: number
  status: string
  created_at: string
  updated_at: string
  assigned_to: string | null
  risk_level: string | null
}

interface ApiResponse {
  data: Application[]
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
}

// Product interface
interface Product {
  id: string
  name: string
  type: string
}

// Filters
const searchQuery = ref('')
const statusFilter = ref('')
const assignmentFilter = ref('')
const productFilter = ref('')
const staleFilter = ref(false)
const activeQuickFilter = ref('')
const currentPage = ref(1)
const itemsPerPage = ref(20)

// Data
const applications = ref<Application[]>([])
const products = ref<Product[]>([])
const totalItems = ref(0)
const totalPages = ref(1)
const isLoading = ref(true)
const error = ref('')

// Quick filter presets
const quickFilters = [
  {
    id: 'new_unassigned',
    label: 'Nuevas sin asignar',
    icon: 'inbox',
    color: 'blue',
    filters: { status: 'SUBMITTED', assignment: 'unassigned' }
  },
  {
    id: 'needs_attention',
    label: 'Requiere atención',
    icon: 'alert',
    color: 'red',
    filters: { status: '', assignment: '', stale: true }
  },
  {
    id: 'pending_docs',
    label: 'Docs pendientes',
    icon: 'document',
    color: 'yellow',
    filters: { status: 'DOCS_PENDING', assignment: '' }
  },
  {
    id: 'in_review',
    label: 'En revisión',
    icon: 'eye',
    color: 'purple',
    filters: { status: 'IN_REVIEW', assignment: '' }
  },
  {
    id: 'ready_approve',
    label: 'Listas para aprobar',
    icon: 'check',
    color: 'green',
    filters: { status: 'IN_REVIEW', assignment: 'assigned' }
  },
]

const statusOptions = [
  { value: '', label: 'Todos los estados' },
  { value: 'DRAFT', label: 'Borrador' },
  { value: 'SUBMITTED', label: 'Nueva' },
  { value: 'IN_REVIEW', label: 'En Revisión' },
  { value: 'DOCS_PENDING', label: 'Docs Pendientes' },
  { value: 'APPROVED', label: 'Aprobada' },
  { value: 'REJECTED', label: 'Rechazada' },
  { value: 'CANCELLED', label: 'Cancelada' },
  { value: 'DISBURSED', label: 'Desembolsada' }
]

const assignmentOptions = [
  { value: '', label: 'Todas' },
  { value: 'unassigned', label: 'Sin asignar' },
  { value: 'assigned', label: 'Asignadas' }
]

const fetchApplications = async () => {
  isLoading.value = true
  error.value = ''

  try {
    const params: Record<string, unknown> = {
      page: currentPage.value,
      per_page: itemsPerPage.value
    }

    if (statusFilter.value) {
      params.status = statusFilter.value
    }

    if (searchQuery.value) {
      params.search = searchQuery.value
    }

    if (assignmentFilter.value) {
      params.assignment = assignmentFilter.value
    }

    if (productFilter.value) {
      params.product_id = productFilter.value
    }

    if (staleFilter.value) {
      params.stale = true
    }

    const response = await api.get<ApiResponse>('/admin/applications', { params })

    applications.value = response.data.data
    totalItems.value = response.data.meta.total
    totalPages.value = response.data.meta.last_page
  } catch (e) {
    console.error('Failed to fetch applications:', e)
    error.value = 'Error al cargar las solicitudes'
  } finally {
    isLoading.value = false
  }
}

// Apply quick filter
const applyQuickFilter = (filterId: string) => {
  const filter = quickFilters.find(f => f.id === filterId)
  if (!filter) return

  if (activeQuickFilter.value === filterId) {
    // Toggle off - clear filters
    activeQuickFilter.value = ''
    statusFilter.value = ''
    assignmentFilter.value = ''
    staleFilter.value = false
  } else {
    activeQuickFilter.value = filterId
    statusFilter.value = filter.filters.status
    assignmentFilter.value = filter.filters.assignment
    staleFilter.value = filter.filters.stale || false
  }
}

// Fetch products for filter dropdown
const fetchProducts = async () => {
  try {
    const response = await api.get<{ data: Product[] }>('/products')
    products.value = response.data.data
  } catch (e) {
    console.error('Failed to fetch products:', e)
  }
}

onMounted(() => {
  fetchApplications()
  fetchProducts()
})

// Watch filters and refetch
watch([statusFilter, searchQuery, assignmentFilter, productFilter, staleFilter], () => {
  currentPage.value = 1
  // Clear quick filter if manual filters changed
  if (activeQuickFilter.value) {
    const currentQuickFilter = quickFilters.find(f => f.id === activeQuickFilter.value)
    if (currentQuickFilter) {
      const matches = currentQuickFilter.filters.status === statusFilter.value &&
        currentQuickFilter.filters.assignment === assignmentFilter.value &&
        (currentQuickFilter.filters.stale || false) === staleFilter.value
      if (!matches) {
        activeQuickFilter.value = ''
      }
    }
  }
  fetchApplications()
})

watch(currentPage, () => {
  fetchApplications()
})

// Formatters
const formatMoney = (amount: number) => {
  return new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
    minimumFractionDigits: 0
  }).format(amount)
}

const formatDateOnly = (dateStr: string) => {
  return new Date(dateStr).toLocaleDateString('es-MX', {
    day: 'numeric',
    month: 'short',
    year: 'numeric'
  })
}

const formatTimeOnly = (dateStr: string) => {
  return new Date(dateStr).toLocaleTimeString('es-MX', {
    hour: '2-digit',
    minute: '2-digit'
  })
}

// Format phone number for display
const formatPhone = (phone: string | undefined | null): string => {
  if (!phone) return '-'
  const digits = phone.replace(/\D/g, '')
  if (digits.length === 10) {
    return `(${digits.slice(0, 2)}) ${digits.slice(2, 6)}-${digits.slice(6)}`
  }
  return phone
}

// Check if application needs attention (no updates in several hours)
const getInactivityInfo = (app: Application) => {
  const updatedAt = new Date(app.updated_at)
  const now = new Date()
  const hoursInactive = (now.getTime() - updatedAt.getTime()) / (1000 * 60 * 60)

  // Only show for active statuses that need follow-up
  const activeStatuses = ['SUBMITTED', 'IN_REVIEW', 'DOCS_PENDING', 'CORRECTIONS_PENDING']
  if (!activeStatuses.includes(app.status)) {
    return null
  }

  if (hoursInactive >= 48) {
    return { level: 'critical', label: '+48h sin actividad', color: 'text-red-600', bg: 'bg-red-50' }
  } else if (hoursInactive >= 24) {
    return { level: 'warning', label: '+24h sin actividad', color: 'text-orange-600', bg: 'bg-orange-50' }
  } else if (hoursInactive >= 8) {
    return { level: 'attention', label: '+8h sin actividad', color: 'text-yellow-600', bg: 'bg-yellow-50' }
  }
  return null
}

const getStatusBadge = (status: string) => {
  const badges: Record<string, { bg: string; text: string; label: string }> = {
    DRAFT: { bg: 'bg-gray-100', text: 'text-gray-800', label: 'Borrador' },
    SUBMITTED: { bg: 'bg-blue-100', text: 'text-blue-800', label: 'Nueva' },
    IN_REVIEW: { bg: 'bg-yellow-100', text: 'text-yellow-800', label: 'En Revisión' },
    DOCS_PENDING: { bg: 'bg-orange-100', text: 'text-orange-800', label: 'Docs Pendientes' },
    CORRECTIONS_PENDING: { bg: 'bg-orange-100', text: 'text-orange-800', label: 'Correcciones Pendientes' },
    APPROVED: { bg: 'bg-green-100', text: 'text-green-800', label: 'Aprobada' },
    REJECTED: { bg: 'bg-red-100', text: 'text-red-800', label: 'Rechazada' },
    CANCELLED: { bg: 'bg-gray-100', text: 'text-gray-800', label: 'Cancelada' },
    DISBURSED: { bg: 'bg-purple-100', text: 'text-purple-800', label: 'Desembolsada' },
    SYNCED: { bg: 'bg-teal-100', text: 'text-teal-800', label: 'Sincronizada' }
  }
  return badges[status] || { bg: 'bg-gray-100', text: 'text-gray-800', label: status }
}

const viewApplication = (app: Application) => {
  router.push(`/admin/solicitudes/${app.id}`)
}

const clearFilters = () => {
  searchQuery.value = ''
  statusFilter.value = ''
  assignmentFilter.value = ''
  productFilter.value = ''
  staleFilter.value = false
  activeQuickFilter.value = ''
  currentPage.value = 1
}

// Check if any filter is active
const hasActiveFilters = computed(() => {
  return searchQuery.value || statusFilter.value || assignmentFilter.value || productFilter.value || staleFilter.value || activeQuickFilter.value
})

const exportToCSV = () => {
  const headers = ['Folio', 'Solicitante', 'Email', 'Teléfono', 'Monto', 'Plazo', 'Producto', 'Estado', 'Fecha']
  const rows = applications.value.map(app => [
    app.folio,
    app.applicant?.name || 'N/A',
    app.applicant?.email || 'N/A',
    app.applicant?.phone || 'N/A',
    app.requested_amount,
    `${app.term_months} meses`,
    app.product?.name || 'N/A',
    getStatusBadge(app.status).label,
    formatDate(app.created_at)
  ])

  const csvContent = [headers, ...rows].map(row => row.join(',')).join('\n')
  const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' })
  const link = document.createElement('a')
  link.href = URL.createObjectURL(blob)
  link.download = `solicitudes_${new Date().toISOString().split('T')[0]}.csv`
  link.click()
}

// Analyst interface
interface Analyst {
  id: string
  name: string
  email: string
  role: string
}

// Selection state
const selectedIds = ref<Set<string>>(new Set())
const showBulkAssignModal = ref(false)
const selectedAgentId = ref('')
const isAssigning = ref(false)
const isLoadingAnalysts = ref(false)

// Analysts list - fetched from API
const analysts = ref<Analyst[]>([])

// Selection functions
const isSelected = (id: string) => selectedIds.value.has(id)

const toggleSelection = (id: string) => {
  if (selectedIds.value.has(id)) {
    selectedIds.value.delete(id)
  } else {
    selectedIds.value.add(id)
  }
}

const toggleSelectAll = () => {
  if (selectedIds.value.size === applications.value.length) {
    selectedIds.value.clear()
  } else {
    applications.value.forEach(app => selectedIds.value.add(app.id))
  }
}

const isAllSelected = computed(() => {
  return applications.value.length > 0 &&
    selectedIds.value.size === applications.value.length
})

const isSomeSelected = computed(() => {
  return selectedIds.value.size > 0 &&
    selectedIds.value.size < applications.value.length
})

const clearSelection = () => {
  selectedIds.value.clear()
}

// Bulk assign
const openBulkAssignModal = async () => {
  selectedAgentId.value = ''
  showBulkAssignModal.value = true
  isLoadingAnalysts.value = true

  try {
    // Fetch only analysts for assignment
    const response = await api.get<{ data: Analyst[] }>('/admin/users', {
      params: { active: true, role: 'ANALYST' }
    })
    analysts.value = response.data.data
  } catch (e) {
    console.error('Failed to load analysts:', e)
    analysts.value = []
  } finally {
    isLoadingAnalysts.value = false
  }
}

const closeBulkAssignModal = () => {
  showBulkAssignModal.value = false
  selectedAgentId.value = ''
}

const confirmBulkAssign = async () => {
  if (!selectedAgentId.value || selectedIds.value.size === 0) return

  isAssigning.value = true

  try {
    // Call API for each selected application
    for (const appId of selectedIds.value) {
      await api.put(`/admin/applications/${appId}/assign`, {
        user_id: selectedAgentId.value
      })
    }

    // Refresh list
    await fetchApplications()
    clearSelection()
    closeBulkAssignModal()
  } catch (e) {
    console.error('Failed to assign applications:', e)
    error.value = 'Error al asignar las solicitudes'
  } finally {
    isAssigning.value = false
  }
}

// Bulk reject
const showBulkRejectModal = ref(false)
const bulkRejectReason = ref('')
const isRejecting = ref(false)

const rejectReasons = [
  { value: 'SCORE_BAJO', label: 'Score crediticio bajo' },
  { value: 'INGRESOS_INSUFICIENTES', label: 'Ingresos insuficientes' },
  { value: 'HISTORIAL_NEGATIVO', label: 'Historial crediticio negativo' },
  { value: 'DOCUMENTACION_FALSA', label: 'Documentación falsa o inconsistente' },
  { value: 'REFERENCIAS_NO_VERIFICADAS', label: 'Referencias no verificadas' },
  { value: 'SOBREENDEUDAMIENTO', label: 'Sobreendeudamiento' },
  { value: 'POLITICAS_INTERNAS', label: 'No cumple políticas internas' },
  { value: 'OTRO', label: 'Otro motivo' }
]

const openBulkRejectModal = () => {
  bulkRejectReason.value = ''
  showBulkRejectModal.value = true
}

const closeBulkRejectModal = () => {
  showBulkRejectModal.value = false
  bulkRejectReason.value = ''
}

const confirmBulkReject = async () => {
  if (!bulkRejectReason.value || selectedIds.value.size === 0) return

  isRejecting.value = true

  try {
    // Call API for each selected application
    for (const appId of selectedIds.value) {
      await api.put(`/admin/applications/${appId}/status`, {
        status: 'REJECTED',
        rejection_reason: bulkRejectReason.value
      })
    }

    // Refresh list
    await fetchApplications()
    clearSelection()
    closeBulkRejectModal()
  } catch (e) {
    console.error('Failed to reject applications:', e)
    error.value = 'Error al rechazar las solicitudes'
  } finally {
    isRejecting.value = false
  }
}
</script>

<template>
  <div>
    <!-- Header -->
    <div class="flex items-center justify-between mb-4">
      <div>
        <h1 class="text-xl font-bold text-gray-900">Solicitudes</h1>
        <p class="text-sm text-gray-500">{{ totalItems }} solicitudes encontradas</p>
      </div>
      <div class="flex items-center gap-2">
        <button
          class="flex items-center gap-1.5 px-3 py-1.5 text-sm text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50"
          :disabled="isLoading"
          @click="fetchApplications"
        >
          <svg
            class="w-3.5 h-3.5"
            :class="{ 'animate-spin': isLoading }"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
          </svg>
          Actualizar
        </button>
        <button
          class="flex items-center gap-1.5 px-3 py-1.5 text-sm bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors"
          @click="exportToCSV"
        >
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
          </svg>
          Exportar CSV
        </button>
      </div>
    </div>

    <!-- Bulk Actions Bar -->
    <transition
      enter-active-class="transition-all duration-200 ease-out"
      enter-from-class="opacity-0 -translate-y-2"
      enter-to-class="opacity-100 translate-y-0"
      leave-active-class="transition-all duration-150 ease-in"
      leave-from-class="opacity-100 translate-y-0"
      leave-to-class="opacity-0 -translate-y-2"
    >
      <div
        v-if="selectedIds.size > 0"
        class="bg-primary-50 border border-primary-200 rounded-xl p-4 mb-4 flex items-center justify-between"
      >
        <div class="flex items-center gap-3">
          <span class="inline-flex items-center justify-center w-8 h-8 bg-primary-600 text-white rounded-full text-sm font-semibold">
            {{ selectedIds.size }}
          </span>
          <span class="text-primary-900 font-medium">
            {{ selectedIds.size === 1 ? 'solicitud seleccionada' : 'solicitudes seleccionadas' }}
          </span>
        </div>
        <div class="flex items-center gap-3">
          <AppButton
            variant="primary"
            size="sm"
            @click="openBulkAssignModal"
          >
            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
            </svg>
            Asignar a Analista
          </AppButton>
          <AppButton
            variant="danger"
            size="sm"
            @click="openBulkRejectModal"
          >
            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
            </svg>
            Rechazar
          </AppButton>
          <button
            class="text-gray-500 hover:text-gray-700 p-1"
            @click="clearSelection"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
      </div>
    </transition>

    <!-- Quick Filters -->
    <div class="mb-4">
      <div class="flex flex-wrap gap-2">
        <button
          v-for="qf in quickFilters"
          :key="qf.id"
          :class="[
            'inline-flex items-center gap-2 px-4 py-2 rounded-full text-sm font-medium transition-all duration-200',
            activeQuickFilter === qf.id
              ? qf.color === 'blue' ? 'bg-blue-600 text-white shadow-lg shadow-blue-200'
                : qf.color === 'yellow' ? 'bg-yellow-500 text-white shadow-lg shadow-yellow-200'
                : qf.color === 'purple' ? 'bg-purple-600 text-white shadow-lg shadow-purple-200'
                : qf.color === 'red' ? 'bg-red-600 text-white shadow-lg shadow-red-200'
                : 'bg-green-600 text-white shadow-lg shadow-green-200'
              : qf.color === 'blue' ? 'bg-blue-50 text-blue-700 hover:bg-blue-100'
                : qf.color === 'yellow' ? 'bg-yellow-50 text-yellow-700 hover:bg-yellow-100'
                : qf.color === 'purple' ? 'bg-purple-50 text-purple-700 hover:bg-purple-100'
                : qf.color === 'red' ? 'bg-red-50 text-red-700 hover:bg-red-100'
                : 'bg-green-50 text-green-700 hover:bg-green-100'
          ]"
          @click="applyQuickFilter(qf.id)"
        >
          <!-- Icons -->
          <svg v-if="qf.icon === 'inbox'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
          </svg>
          <svg v-else-if="qf.icon === 'alert'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
          </svg>
          <svg v-else-if="qf.icon === 'document'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
          </svg>
          <svg v-else-if="qf.icon === 'eye'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
          </svg>
          <svg v-else-if="qf.icon === 'check'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          {{ qf.label }}
        </button>
      </div>
    </div>

    <!-- Advanced Filters -->
    <div class="bg-white rounded-xl shadow-sm p-4 mb-4">
      <div class="grid grid-cols-1 md:grid-cols-5 gap-3">
        <!-- Search -->
        <div class="md:col-span-1">
          <label class="block text-xs font-medium text-gray-500 mb-1.5">Buscar</label>
          <div class="relative">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Nombre, folio, email..."
              class="w-full pl-9 pr-3 py-2 text-sm border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent bg-gray-50 focus:bg-white transition-colors"
            >
          </div>
        </div>

        <!-- Status Filter -->
        <div>
          <label class="block text-xs font-medium text-gray-500 mb-1.5">Estado</label>
          <select
            v-model="statusFilter"
            class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent bg-gray-50 focus:bg-white transition-colors"
          >
            <option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">
              {{ opt.label }}
            </option>
          </select>
        </div>

        <!-- Product Filter -->
        <div>
          <label class="block text-xs font-medium text-gray-500 mb-1.5">Tipo de crédito</label>
          <select
            v-model="productFilter"
            class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent bg-gray-50 focus:bg-white transition-colors"
          >
            <option value="">Todos los productos</option>
            <option v-for="prod in products" :key="prod.id" :value="prod.id">
              {{ prod.name }}
            </option>
          </select>
        </div>

        <!-- Assignment Filter -->
        <div>
          <label class="block text-xs font-medium text-gray-500 mb-1.5">Asignación</label>
          <select
            v-model="assignmentFilter"
            class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent bg-gray-50 focus:bg-white transition-colors"
          >
            <option v-for="opt in assignmentOptions" :key="opt.value" :value="opt.value">
              {{ opt.label }}
            </option>
          </select>
        </div>

        <!-- Clear Filters -->
        <div class="flex items-end">
          <button
            v-if="hasActiveFilters"
            class="w-full px-3 py-2 text-sm text-red-600 hover:text-red-700 hover:bg-red-50 rounded-lg transition-colors flex items-center justify-center gap-2"
            @click="clearFilters"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
            Limpiar filtros
          </button>
          <div v-else class="w-full px-3 py-2 text-sm text-gray-400 text-center">
            {{ totalItems }} solicitudes
          </div>
        </div>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="isLoading && applications.length === 0" class="flex items-center justify-center py-12">
      <div class="animate-spin w-8 h-8 border-4 border-primary-500 border-t-transparent rounded-full"></div>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="bg-red-50 border border-red-200 rounded-xl p-6 text-center">
      <p class="text-red-600">{{ error }}</p>
      <button
        @click="fetchApplications"
        class="mt-4 px-4 py-2 text-sm font-medium text-red-600 hover:text-red-700"
      >
        Reintentar
      </button>
    </div>

    <!-- Table -->
    <div v-else class="bg-white rounded-xl shadow-sm overflow-hidden">
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-3 py-2 text-left">
                <input
                  type="checkbox"
                  :checked="isAllSelected"
                  :indeterminate="isSomeSelected"
                  class="w-4 h-4 text-primary-600 border-gray-300 rounded focus:ring-primary-500 cursor-pointer"
                  @change="toggleSelectAll"
                >
              </th>
              <th class="px-3 py-2 text-left text-[11px] font-medium text-gray-500 uppercase tracking-wider">
                Folio
              </th>
              <th class="px-3 py-2 text-left text-[11px] font-medium text-gray-500 uppercase tracking-wider">
                Solicitante
              </th>
              <th class="px-3 py-2 text-left text-[11px] font-medium text-gray-500 uppercase tracking-wider">
                Monto
              </th>
              <th class="px-3 py-2 text-left text-[11px] font-medium text-gray-500 uppercase tracking-wider">
                Estado
              </th>
              <th class="px-3 py-2 text-left text-[11px] font-medium text-gray-500 uppercase tracking-wider">
                Fecha
              </th>
              <th class="px-3 py-2 text-left text-[11px] font-medium text-gray-500 uppercase tracking-wider">
                Acciones
              </th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <tr
              v-for="app in applications"
              :key="app.id"
              :class="[
                'hover:bg-gray-50 cursor-pointer transition-colors',
                isSelected(app.id) ? 'bg-primary-50' : '',
                getInactivityInfo(app)?.bg
              ]"
              @click="viewApplication(app)"
            >
              <td class="px-3 py-2 whitespace-nowrap" @click.stop>
                <input
                  type="checkbox"
                  :checked="isSelected(app.id)"
                  class="w-4 h-4 text-primary-600 border-gray-300 rounded focus:ring-primary-500 cursor-pointer"
                  @change="toggleSelection(app.id)"
                >
              </td>
              <td class="px-3 py-2 whitespace-nowrap">
                <div class="font-mono text-xs text-gray-900">{{ app.folio }}</div>
                <div class="text-[11px] text-gray-500">{{ app.product?.name || 'Sin producto' }}</div>
              </td>
              <td class="px-3 py-2 whitespace-nowrap">
                <div>
                  <div class="text-sm font-medium text-gray-900">{{ app.applicant?.name || 'Sin nombre' }}</div>
                  <div class="text-xs text-gray-500">{{ formatPhone(app.applicant?.phone) }}</div>
                </div>
              </td>
              <td class="px-3 py-2 whitespace-nowrap">
                <div class="text-sm font-semibold text-gray-900">{{ formatMoney(app.requested_amount) }}</div>
                <div class="text-xs text-gray-500">{{ app.term_months }} meses</div>
              </td>
              <td class="px-3 py-2 whitespace-nowrap">
                <div class="flex flex-col gap-0.5">
                  <span
                    :class="[
                      'px-2 py-0.5 text-[11px] font-medium rounded-full inline-block w-fit',
                      getStatusBadge(app.status).bg,
                      getStatusBadge(app.status).text
                    ]"
                  >
                    {{ getStatusBadge(app.status).label }}
                  </span>
                  <span v-if="app.assigned_to" class="text-[11px] text-gray-500">{{ app.assigned_to }}</span>
                  <span v-else class="text-[11px] text-gray-400 italic">Sin asignar</span>
                </div>
              </td>
              <td class="px-3 py-2 whitespace-nowrap">
                <div class="text-xs text-gray-900">{{ formatDateOnly(app.created_at) }} <span class="text-gray-500">{{ formatTimeOnly(app.created_at) }}</span></div>
                <!-- Inactivity indicator -->
                <div
                  v-if="getInactivityInfo(app)"
                  :class="['text-[10px] font-medium flex items-center gap-1 mt-0.5', getInactivityInfo(app)?.color]"
                >
                  <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                  </svg>
                  {{ getInactivityInfo(app)?.label }}
                </div>
              </td>
              <td class="px-3 py-2 whitespace-nowrap text-xs">
                <button
                  class="text-primary-600 hover:text-primary-900 font-medium"
                  @click.stop="viewApplication(app)"
                >
                  Ver detalle
                </button>
              </td>
            </tr>

            <!-- Empty state -->
            <tr v-if="applications.length === 0">
              <td colspan="7" class="px-6 py-12 text-center">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">No hay solicitudes</h3>
                <p class="mt-1 text-sm text-gray-500">
                  No se encontraron solicitudes con los filtros seleccionados.
                </p>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="totalPages > 1" class="bg-gray-50 px-4 py-3 flex items-center justify-between border-t border-gray-200">
        <div class="flex-1 flex justify-between sm:hidden">
          <button
            :disabled="currentPage === 1"
            class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
            @click="currentPage--"
          >
            Anterior
          </button>
          <button
            :disabled="currentPage === totalPages"
            class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
            @click="currentPage++"
          >
            Siguiente
          </button>
        </div>
        <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
          <div>
            <p class="text-sm text-gray-700">
              Mostrando
              <span class="font-medium">{{ (currentPage - 1) * itemsPerPage + 1 }}</span>
              a
              <span class="font-medium">{{ Math.min(currentPage * itemsPerPage, totalItems) }}</span>
              de
              <span class="font-medium">{{ totalItems }}</span>
              resultados
            </p>
          </div>
          <div>
            <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
              <button
                :disabled="currentPage === 1"
                class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                @click="currentPage--"
              >
                <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                </svg>
              </button>
              <button
                v-for="page in Math.min(totalPages, 5)"
                :key="page"
                :class="[
                  'relative inline-flex items-center px-4 py-2 border text-sm font-medium',
                  currentPage === page
                    ? 'z-10 bg-primary-50 border-primary-500 text-primary-600'
                    : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50'
                ]"
                @click="currentPage = page"
              >
                {{ page }}
              </button>
              <button
                :disabled="currentPage === totalPages"
                class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                @click="currentPage++"
              >
                <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                </svg>
              </button>
            </nav>
          </div>
        </div>
      </div>
    </div>

    <!-- Bulk Assign Modal -->
    <AppConfirmModal
      :show="showBulkAssignModal"
      title="Asignar Solicitudes a Analista"
      confirm-text="Asignar"
      :confirm-disabled="!selectedAgentId"
      :loading="isAssigning"
      size="lg"
      icon="info"
      @confirm="confirmBulkAssign"
      @cancel="closeBulkAssignModal"
    >
      <div class="space-y-4">
        <p class="text-gray-600">
          Selecciona el analista al que deseas asignar
          <span class="font-semibold text-gray-900">{{ selectedIds.size }}</span>
          {{ selectedIds.size === 1 ? 'solicitud' : 'solicitudes' }}.
        </p>

        <!-- Loading state -->
          <div v-if="isLoadingAnalysts" class="flex justify-center py-8">
            <div class="animate-spin w-8 h-8 border-4 border-primary-600 border-t-transparent rounded-full" />
          </div>

          <!-- Empty state -->
          <div v-else-if="analysts.length === 0" class="text-center py-8">
            <svg class="w-12 h-12 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
            <p class="text-gray-500 font-medium">No hay analistas disponibles</p>
            <p class="text-sm text-gray-400 mt-1">Crea un usuario con rol Analista en la sección de Usuarios</p>
          </div>

          <!-- Analysts list -->
          <div v-else class="space-y-2">
            <label class="block text-sm font-medium text-gray-700">Analista</label>
            <div class="space-y-2 max-h-60 overflow-y-auto">
              <label
                v-for="analyst in analysts"
                :key="analyst.id"
                :class="[
                  'flex items-center p-4 border-2 rounded-xl cursor-pointer transition-all duration-200',
                  selectedAgentId === analyst.id
                    ? 'border-primary-500 bg-gradient-to-r from-primary-50 to-primary-100 shadow-md shadow-primary-100'
                    : 'border-gray-200 hover:border-primary-300 hover:bg-gray-50'
                ]"
              >
                <input
                  v-model="selectedAgentId"
                  type="radio"
                  :value="analyst.id"
                  class="sr-only"
                >
                <div class="flex items-center gap-3 flex-1">
                  <div
                    :class="[
                      'w-12 h-12 rounded-full flex items-center justify-center transition-all duration-200',
                      selectedAgentId === analyst.id
                        ? 'bg-primary-600 text-white shadow-lg shadow-primary-200'
                        : 'bg-primary-100 text-primary-700'
                    ]"
                  >
                    <span class="font-bold text-sm">
                      {{ analyst.name.split(' ').map((n: string) => n[0]).join('').slice(0, 2).toUpperCase() }}
                    </span>
                  </div>
                  <div class="flex-1">
                    <div :class="[
                      'font-semibold transition-colors',
                      selectedAgentId === analyst.id ? 'text-primary-900' : 'text-gray-900'
                    ]">
                      {{ analyst.name }}
                    </div>
                    <div class="text-sm text-gray-500">{{ analyst.email }}</div>
                  </div>
                </div>
                <div
                  :class="[
                    'w-6 h-6 rounded-full border-2 flex items-center justify-center transition-all duration-200',
                    selectedAgentId === analyst.id
                      ? 'border-primary-600 bg-primary-600'
                      : 'border-gray-300 bg-white'
                  ]"
                >
                  <svg
                    v-if="selectedAgentId === analyst.id"
                    class="w-4 h-4 text-white"
                    fill="currentColor"
                    viewBox="0 0 20 20"
                  >
                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                  </svg>
                </div>
              </label>
          </div>
        </div>
      </div>
    </AppConfirmModal>

    <!-- Bulk Reject Modal -->
    <AppConfirmModal
      :show="showBulkRejectModal"
      title="Rechazar Solicitudes"
      confirm-text="Rechazar"
      :confirm-disabled="!bulkRejectReason"
      :loading="isRejecting"
      variant="danger"
      icon="danger"
      size="lg"
      @confirm="confirmBulkReject"
      @cancel="closeBulkRejectModal"
    >
      <div class="space-y-4">
        <div class="bg-red-50 border border-red-200 rounded-lg p-3">
          <div class="flex items-start gap-2">
            <svg class="w-5 h-5 text-red-600 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
            </svg>
            <div>
              <p class="text-sm font-medium text-red-800">
                Esta accion rechazara
                <span class="font-bold">{{ selectedIds.size }}</span>
                {{ selectedIds.size === 1 ? 'solicitud' : 'solicitudes' }}.
              </p>
              <p class="text-sm text-red-700 mt-1">Esta accion no se puede deshacer.</p>
            </div>
          </div>
        </div>

        <div class="space-y-2">
          <label class="block text-sm font-medium text-gray-700">Motivo del rechazo</label>
          <select
            v-model="bulkRejectReason"
            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent"
          >
            <option value="" disabled>Selecciona un motivo...</option>
            <option
              v-for="reason in rejectReasons"
              :key="reason.value"
              :value="reason.value"
            >
              {{ reason.label }}
            </option>
          </select>
        </div>
      </div>
    </AppConfirmModal>
  </div>
</template>
