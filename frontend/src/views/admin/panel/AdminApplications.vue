<script setup lang="ts">
import { ref, computed, onMounted, watch, onBeforeUnmount } from 'vue'
import { useRouter } from 'vue-router'
import { v2 } from '@/services/v2'
import type { V2ApplicationFilters } from '@/types/v2'
import { AppButton, AppConfirmModal } from '@/components/common'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables'
import { logger } from '@/utils/logger'

const log = logger.child('AdminApplications')
const toast = useToast()

const router = useRouter()
const authStore = useAuthStore()

// Permisos
const canAssign = computed(() => authStore.permissions?.canAssignApplications ?? false)
const canApproveReject = computed(() => authStore.permissions?.canApproveRejectApplications ?? false)

/**
 * Application type for admin list view.
 * Mapped from V2 API response to match the local display needs.
 */
interface Application {
  id: string
  folio: string
  applicant_name: string | null
  applicant_phone: string | null
  applicant_email: string | null
  product: {
    id: string
    name: string
    type: string
  } | null
  requested_amount: number
  approved_amount: number | null
  term_months: number
  payment_frequency: string
  monthly_payment: number | null
  status: string
  status_label: string
  created_at: string
  updated_at: string
  assigned_to: string | null
  risk_level: string | null
}

interface Product {
  id: string
  name: string
  type: string
}

// View mode - default to table, Kanban is the Dashboard's main view
const viewMode = ref<'board' | 'table'>('table')

// Kanban columns configuration
const kanbanColumns = [
  { status: 'SUBMITTED', label: 'Nueva', color: 'blue', headerBg: 'bg-blue-500' },
  { status: 'IN_REVIEW', label: 'En Revisión', color: 'yellow', headerBg: 'bg-yellow-500' },
  { status: 'DOCS_PENDING', label: 'Docs Pendientes', color: 'orange', headerBg: 'bg-orange-500' },
  { status: 'APPROVED', label: 'Aprobada', color: 'green', headerBg: 'bg-green-500' },
  { status: 'REJECTED', label: 'Rechazada', color: 'red', headerBg: 'bg-red-500' },
]

// Filters
const searchQuery = ref('')
const statusFilter = ref('')
const assignmentFilter = ref('')
const productFilter = ref('')
const staleFilter = ref(false)
const activeQuickFilter = ref('')
const currentPage = ref(1)
const itemsPerPage = ref(100) // Higher for board view

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
    label: 'Sin asignar',
    icon: 'inbox',
    color: 'blue',
    filters: { status: '', assignment: 'unassigned', stale: false }
  },
  {
    id: 'needs_attention',
    label: 'Requiere atención',
    icon: 'alert',
    color: 'red',
    filters: { status: '', assignment: '', stale: true }
  },
  {
    id: 'assigned_to_me',
    label: 'Asignadas',
    icon: 'user',
    color: 'purple',
    filters: { status: '', assignment: 'assigned', stale: false }
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

// Only show active workflow states as click filters
const activeStatusFilters = [
  { value: 'SUBMITTED', label: 'Nueva' },
  { value: 'IN_REVIEW', label: 'En Revisión' },
  { value: 'DOCS_PENDING', label: 'Docs Pendientes' }
]

const fetchApplications = async () => {
  isLoading.value = true
  error.value = ''

  try {
    const filters: V2ApplicationFilters = {
      page: currentPage.value,
      per_page: viewMode.value === 'board' ? 200 : itemsPerPage.value
    }

    if (statusFilter.value) {
      filters.status = statusFilter.value as V2ApplicationFilters['status']
    }

    if (searchQuery.value) {
      filters.search = searchQuery.value
    }

    if (assignmentFilter.value) {
      filters.assignment = assignmentFilter.value as 'assigned' | 'unassigned'
    }

    if (productFilter.value) {
      filters.product_id = productFilter.value
    }

    if (staleFilter.value) {
      filters.stale = true
    }

    const response = await v2.staff.application.list(filters)

    // Map V2 response to local Application type
    applications.value = response.data.map((app) => ({
      id: app.id,
      folio: app.folio,
      applicant_name: app.applicant?.full_name ?? null,
      applicant_phone: app.applicant?.phone ?? null,
      applicant_email: app.applicant?.email ?? null,
      product: app.product ? {
        id: app.product.id,
        name: app.product.name,
        type: app.product.type
      } : null,
      requested_amount: app.requested_amount,
      approved_amount: app.approved_amount,
      term_months: app.term_months,
      payment_frequency: app.payment_frequency,
      monthly_payment: app.monthly_payment,
      status: app.status,
      status_label: app.status_reason ?? app.status,
      created_at: app.created_at,
      updated_at: app.updated_at,
      assigned_to: app.assigned_to_id,
      risk_level: app.risk_score ? (app.risk_score > 70 ? 'HIGH' : app.risk_score > 40 ? 'MEDIUM' : 'LOW') : null
    }))
    totalItems.value = response.meta.total
    totalPages.value = response.meta.last_page
  } catch (e) {
    log.error('Error al cargar solicitudes', { error: e })
    error.value = 'Error al cargar las solicitudes'
  } finally {
    isLoading.value = false
  }
}

// Group applications by status for Kanban view
const applicationsByStatus = computed(() => {
  const grouped: Record<string, Application[]> = {}
  kanbanColumns.forEach(col => {
    grouped[col.status] = []
  })

  applications.value.forEach(app => {
    const statusGroup = grouped[app.status]
    if (statusGroup) {
      statusGroup.push(app)
    }
  })

  return grouped
})

// Count by status
const countByStatus = computed(() => {
  const counts: Record<string, number> = {}
  kanbanColumns.forEach(col => {
    counts[col.status] = applicationsByStatus.value[col.status]?.length || 0
  })
  return counts
})

// Apply quick filter
const applyQuickFilter = (filterId: string) => {
  const filter = quickFilters.find(f => f.id === filterId)
  if (!filter) return

  if (activeQuickFilter.value === filterId) {
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
    const response = await v2.staff.product.list({ active: true })
    products.value = response.data.map((p) => ({
      id: p.id,
      name: p.name,
      type: p.type
    }))
  } catch (e) {
    log.error('Error al cargar productos', { error: e })
  }
}

onMounted(() => {
  fetchApplications()
  fetchProducts()
})

// Debounce timer for filter changes
let filterDebounceTimer: ReturnType<typeof setTimeout> | null = null

// Cleanup on unmount
onBeforeUnmount(() => {
  if (filterDebounceTimer) {
    clearTimeout(filterDebounceTimer)
  }
})

// Watch filters and refetch with debounce to prevent race conditions
watch([statusFilter, searchQuery, assignmentFilter, productFilter, staleFilter, viewMode], () => {
  currentPage.value = 1
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

  // Debounce the fetch to prevent multiple rapid calls
  if (filterDebounceTimer) {
    clearTimeout(filterDebounceTimer)
  }
  filterDebounceTimer = setTimeout(() => {
    fetchApplications()
  }, 150)
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

const formatMoneyShort = (amount: number) => {
  if (amount >= 1000000) {
    return `$${(amount / 1000000).toFixed(1)}M`
  } else if (amount >= 1000) {
    return `$${(amount / 1000).toFixed(0)}K`
  }
  return `$${amount}`
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

const formatDateShort = (dateStr: string) => {
  return new Date(dateStr).toLocaleDateString('es-MX', {
    day: 'numeric',
    month: 'short'
  })
}

const formatPhone = (phone: string | undefined | null): string => {
  if (!phone) return '-'
  const digits = phone.replace(/\D/g, '')
  if (digits.length === 10) {
    return `(${digits.slice(0, 2)}) ${digits.slice(2, 6)}-${digits.slice(6)}`
  }
  return phone
}

const getInactivityInfo = (app: Application) => {
  const updatedAt = new Date(app.updated_at)
  const now = new Date()
  const hoursInactive = (now.getTime() - updatedAt.getTime()) / (1000 * 60 * 60)

  const activeStatuses = ['SUBMITTED', 'IN_REVIEW', 'DOCS_PENDING', 'CORRECTIONS_PENDING']
  if (!activeStatuses.includes(app.status)) {
    return null
  }

  if (hoursInactive >= 48) {
    return { level: 'critical', label: '+48h', color: 'text-red-600', bg: 'bg-red-100', border: 'border-red-300' }
  } else if (hoursInactive >= 24) {
    return { level: 'warning', label: '+24h', color: 'text-orange-600', bg: 'bg-orange-100', border: 'border-orange-300' }
  } else if (hoursInactive >= 8) {
    return { level: 'attention', label: '+8h', color: 'text-yellow-600', bg: 'bg-yellow-100', border: 'border-yellow-300' }
  }
  return null
}

const getStatusBadge = (status: string) => {
  const badges: Record<string, { bg: string; text: string; label: string }> = {
    DRAFT: { bg: 'bg-gray-100', text: 'text-gray-800', label: 'Borrador' },
    SUBMITTED: { bg: 'bg-blue-100', text: 'text-blue-800', label: 'Nueva' },
    IN_REVIEW: { bg: 'bg-yellow-100', text: 'text-yellow-800', label: 'En Revisión' },
    DOCS_PENDING: { bg: 'bg-orange-100', text: 'text-orange-800', label: 'Docs Pendientes' },
    CORRECTIONS_PENDING: { bg: 'bg-orange-100', text: 'text-orange-800', label: 'Correcciones' },
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

const hasActiveFilters = computed(() => {
  return searchQuery.value || statusFilter.value || assignmentFilter.value || productFilter.value || staleFilter.value || activeQuickFilter.value
})

// Escape CSV value to handle commas, quotes, and newlines
const escapeCsvValue = (value: unknown): string => {
  const str = String(value ?? '')
  // If contains comma, quote, or newline, wrap in quotes and escape existing quotes
  if (str.includes(',') || str.includes('"') || str.includes('\n') || str.includes('\r')) {
    return `"${str.replace(/"/g, '""')}"`
  }
  return str
}

const exportToCSV = (): void => {
  const headers = ['Folio', 'Solicitante', 'Email', 'Teléfono', 'Monto', 'Plazo', 'Producto', 'Estado', 'Fecha']
  const rows = applications.value.map(app => [
    app.folio,
    app.applicant_name || 'N/A',
    app.applicant_email || 'N/A',
    app.applicant_phone || 'N/A',
    app.requested_amount,
    `${app.term_months} meses`,
    app.product?.name || 'N/A',
    getStatusBadge(app.status).label,
    formatDateOnly(app.created_at)
  ])

  // Properly escape all values for CSV format
  const csvContent = [headers, ...rows]
    .map(row => row.map(escapeCsvValue).join(','))
    .join('\n')

  // Add BOM for UTF-8 compatibility with Excel
  const bom = '\uFEFF'
  const blob = new Blob([bom + csvContent], { type: 'text/csv;charset=utf-8;' })
  const link = document.createElement('a')
  link.href = URL.createObjectURL(blob)
  link.download = `solicitudes_${new Date().toISOString().split('T')[0]}.csv`
  link.click()
  URL.revokeObjectURL(link.href) // Clean up
}

// Analyst interface
interface Analyst {
  id: string
  name: string
  email: string
  role: string
}

// Selection state (for table view)
const selectedIds = ref<Set<string>>(new Set())
const showBulkAssignModal = ref(false)
const selectedAgentId = ref('')
const isAssigning = ref(false)
const isLoadingAnalysts = ref(false)
const analysts = ref<Analyst[]>([])

const isSelected = (id: string) => selectedIds.value.has(id)

// Use proper Set reassignment to trigger Vue reactivity
const toggleSelection = (id: string) => {
  const newSet = new Set(selectedIds.value)
  if (newSet.has(id)) {
    newSet.delete(id)
  } else {
    newSet.add(id)
  }
  selectedIds.value = newSet
}

const toggleSelectAll = () => {
  if (selectedIds.value.size === applications.value.length) {
    selectedIds.value = new Set()
  } else {
    selectedIds.value = new Set(applications.value.map(app => app.id))
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
  selectedIds.value = new Set()
}

const openBulkAssignModal = async () => {
  selectedAgentId.value = ''
  showBulkAssignModal.value = true
  isLoadingAnalysts.value = true

  try {
    const response = await v2.staff.user.list({ active: true, role: 'ANALYST' })
    analysts.value = response.data.map((u) => ({
      id: u.id,
      name: u.name,
      email: u.email,
      role: u.role
    }))
  } catch (e) {
    log.error('Error al cargar analistas', { error: e })
    analysts.value = []
  } finally {
    isLoadingAnalysts.value = false
  }
}

const closeBulkAssignModal = () => {
  showBulkAssignModal.value = false
  selectedAgentId.value = ''
}

const confirmBulkAssign = async (): Promise<void> => {
  if (!selectedAgentId.value || selectedIds.value.size === 0) return

  isAssigning.value = true

  try {
    // Use Promise.allSettled to handle partial failures gracefully
    const results = await Promise.allSettled(
      Array.from(selectedIds.value).map(appId =>
        v2.staff.application.assign(appId, { user_id: selectedAgentId.value })
      )
    )

    const succeeded = results.filter(r => r.status === 'fulfilled').length
    const failed = results.filter(r => r.status === 'rejected').length

    await fetchApplications()
    clearSelection()
    closeBulkAssignModal()

    if (failed === 0) {
      toast.success(`${succeeded} solicitudes asignadas correctamente`)
    } else if (succeeded > 0) {
      toast.warning(`${succeeded} asignadas, ${failed} fallaron`)
    } else {
      toast.error('Error al asignar las solicitudes')
    }
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

const confirmBulkReject = async (): Promise<void> => {
  if (!bulkRejectReason.value || selectedIds.value.size === 0) return

  isRejecting.value = true

  try {
    // Use Promise.allSettled to handle partial failures gracefully
    const results = await Promise.allSettled(
      Array.from(selectedIds.value).map(appId =>
        v2.staff.application.reject(appId, { reason: bulkRejectReason.value })
      )
    )

    const succeeded = results.filter(r => r.status === 'fulfilled').length
    const failed = results.filter(r => r.status === 'rejected').length

    await fetchApplications()
    clearSelection()
    closeBulkRejectModal()

    if (failed === 0) {
      toast.success(`${succeeded} solicitudes rechazadas`)
    } else if (succeeded > 0) {
      toast.warning(`${succeeded} rechazadas, ${failed} fallaron`)
    } else {
      toast.error('Error al rechazar las solicitudes')
    }
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
        <p class="text-sm text-gray-500">{{ totalItems }} solicitudes</p>
      </div>
      <div class="flex items-center gap-2">
        <!-- View Toggle -->
        <div class="bg-gray-100 rounded-lg p-1 flex" role="group" aria-label="Cambiar vista">
          <button
            :class="[
              'px-3 py-1.5 text-sm font-medium rounded-md transition-colors',
              viewMode === 'board' ? 'bg-white shadow text-gray-900' : 'text-gray-500 hover:text-gray-700'
            ]"
            :aria-pressed="viewMode === 'board'"
            aria-label="Vista tablero Kanban"
            @click="viewMode = 'board'"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2" />
            </svg>
          </button>
          <button
            :class="[
              'px-3 py-1.5 text-sm font-medium rounded-md transition-colors',
              viewMode === 'table' ? 'bg-white shadow text-gray-900' : 'text-gray-500 hover:text-gray-700'
            ]"
            :aria-pressed="viewMode === 'table'"
            aria-label="Vista tabla"
            @click="viewMode = 'table'"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
            </svg>
          </button>
        </div>

        <button
          class="flex items-center gap-1.5 px-3 py-1.5 text-sm text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50"
          :disabled="isLoading"
          aria-label="Actualizar lista"
          @click="fetchApplications"
        >
          <svg
            class="w-3.5 h-3.5"
            :class="{ 'animate-spin': isLoading }"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
            aria-hidden="true"
          >
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
          </svg>
        </button>
        <button
          class="flex items-center gap-1.5 px-3 py-1.5 text-sm bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors"
          aria-label="Exportar a CSV"
          @click="exportToCSV"
        >
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
          </svg>
          CSV
        </button>
      </div>
    </div>

    <!-- Filters Bar -->
    <div class="bg-white rounded-xl shadow-sm p-3 mb-4" role="search" aria-label="Filtros de solicitudes">
      <div class="flex flex-wrap items-center gap-3">
        <!-- Search -->
        <div class="relative flex-1 min-w-[280px] max-w-lg">
          <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
          </svg>
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Buscar por nombre, folio, email..."
            aria-label="Buscar solicitudes"
            class="w-full pl-9 pr-3 py-2 text-sm border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent bg-gray-50 focus:bg-white transition-colors"
          >
        </div>

        <!-- Product Filter -->
        <select
          v-model="productFilter"
          aria-label="Filtrar por producto"
          class="px-3 py-2 text-sm border border-gray-200 rounded-lg bg-gray-50 focus:bg-white"
        >
          <option value="">Todos los productos</option>
          <option v-for="prod in products" :key="prod.id" :value="prod.id">
            {{ prod.name }}
          </option>
        </select>

        <!-- Status Filters (workflow order: Nueva → En Revisión → Docs Pendientes) -->
        <div class="flex items-center gap-1.5">
          <button
            v-for="opt in activeStatusFilters"
            :key="opt.value"
            :class="[
              'px-2.5 py-1 text-xs font-medium rounded-full transition-all',
              statusFilter === opt.value
                ? 'bg-gray-800 text-white'
                : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
            ]"
            @click="statusFilter = statusFilter === opt.value ? '' : opt.value"
          >
            {{ opt.label }}
          </button>
        </div>

        <!-- Quick Filters -->
        <div class="flex items-center gap-1.5 ml-auto">
          <button
            v-for="qf in quickFilters"
            :key="qf.id"
            :class="[
              'px-2.5 py-1 text-xs font-medium rounded-full transition-all',
              activeQuickFilter === qf.id
                ? qf.color === 'blue' ? 'bg-blue-600 text-white'
                  : qf.color === 'red' ? 'bg-red-600 text-white'
                  : 'bg-purple-600 text-white'
                : qf.color === 'blue' ? 'bg-blue-50 text-blue-700 hover:bg-blue-100'
                  : qf.color === 'red' ? 'bg-red-50 text-red-700 hover:bg-red-100'
                  : 'bg-purple-50 text-purple-700 hover:bg-purple-100'
            ]"
            @click="applyQuickFilter(qf.id)"
          >
            {{ qf.label }}
          </button>

          <!-- Clear Filters -->
          <button
            v-if="hasActiveFilters"
            class="p-1.5 text-red-600 hover:bg-red-50 rounded-lg"
            title="Limpiar filtros"
            aria-label="Limpiar filtros"
            @click="clearFilters"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
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

    <!-- BOARD VIEW -->
    <div v-else-if="viewMode === 'board'" class="flex gap-4 overflow-x-auto pb-4" style="min-height: calc(100vh - 280px)">
      <!-- Kanban Columns -->
      <div
        v-for="column in kanbanColumns"
        :key="column.status"
        class="flex-shrink-0 w-72 flex flex-col border border-gray-200 rounded-xl overflow-hidden shadow-sm"
      >
        <!-- Column Header -->
        <div
          class="px-3 py-2 flex items-center justify-between"
          :class="column.headerBg"
        >
          <span class="font-semibold text-white text-sm">{{ column.label }}</span>
          <span class="bg-white/20 text-white text-xs font-bold px-2 py-0.5 rounded-full">
            {{ countByStatus[column.status] }}
          </span>
        </div>

        <!-- Cards Container -->
        <div class="flex-1 p-2 space-y-2 overflow-y-auto bg-gray-50 min-h-[500px]" style="max-height: calc(100vh - 300px)">
          <div
            v-for="app in applicationsByStatus[column.status]"
            :key="app.id"
            class="bg-white rounded-lg shadow-sm border border-gray-200 p-3 cursor-pointer hover:shadow-md hover:border-gray-300 transition-all"
            :class="{ [getInactivityInfo(app)?.border || '']: getInactivityInfo(app) }"
            @click="viewApplication(app)"
          >
            <!-- Card Header -->
            <div class="flex items-start justify-between mb-2">
              <span class="font-mono text-xs text-gray-500">{{ app.folio }}</span>
              <div v-if="getInactivityInfo(app)" class="flex items-center gap-1">
                <span :class="['text-[10px] font-bold px-1.5 py-0.5 rounded', getInactivityInfo(app)?.bg, getInactivityInfo(app)?.color]">
                  {{ getInactivityInfo(app)?.label }}
                </span>
              </div>
            </div>

            <!-- Applicant Name -->
            <p class="font-medium text-gray-900 text-sm truncate mb-1">
              {{ app.applicant_name || 'Sin nombre' }}
            </p>

            <!-- Amount & Term -->
            <div class="flex items-center justify-between mb-2">
              <span class="text-sm font-semibold text-primary-600">{{ formatMoneyShort(app.requested_amount) }}</span>
              <span class="text-xs text-gray-500">{{ app.term_months }}m</span>
            </div>

            <!-- Product Badge -->
            <div class="flex items-center justify-between">
              <span class="text-[10px] px-2 py-0.5 bg-gray-100 text-gray-600 rounded-full truncate max-w-[120px]">
                {{ app.product?.name || 'Sin producto' }}
              </span>
              <span class="text-[10px] text-gray-400">{{ formatDateShort(app.created_at) }}</span>
            </div>

            <!-- Assigned To -->
            <div v-if="app.assigned_to" class="mt-2 pt-2 border-t border-gray-100">
              <div class="flex items-center gap-1.5">
                <div class="w-5 h-5 rounded-full bg-primary-100 flex items-center justify-center">
                  <span class="text-[10px] font-medium text-primary-700">
                    {{ app.assigned_to?.charAt(0)?.toUpperCase() ?? '?' }}
                  </span>
                </div>
                <span class="text-[10px] text-gray-500 truncate">{{ app.assigned_to }}</span>
              </div>
            </div>
          </div>

          <!-- Empty Column State -->
          <div
            v-if="(applicationsByStatus[column.status]?.length ?? 0) === 0"
            class="text-center py-8 text-gray-400"
          >
            <svg class="w-8 h-8 mx-auto mb-2 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <p class="text-xs">Sin solicitudes</p>
          </div>
        </div>
      </div>
    </div>

    <!-- TABLE VIEW -->
    <div v-else class="bg-white rounded-xl shadow-sm overflow-hidden">
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
          class="bg-primary-50 border-b border-primary-200 p-3 flex items-center justify-between"
        >
          <div class="flex items-center gap-3">
            <span class="inline-flex items-center justify-center w-6 h-6 bg-primary-600 text-white rounded-full text-xs font-semibold">
              {{ selectedIds.size }}
            </span>
            <span class="text-primary-900 text-sm font-medium">seleccionadas</span>
          </div>
          <div class="flex items-center gap-2">
            <!-- Solo supervisores/admins pueden asignar -->
            <AppButton v-if="canAssign" variant="primary" size="sm" @click="openBulkAssignModal">
              Asignar
            </AppButton>
            <!-- Solo supervisores/admins pueden rechazar -->
            <AppButton v-if="canApproveReject" variant="danger" size="sm" @click="openBulkRejectModal">
              Rechazar
            </AppButton>
            <button class="text-gray-500 hover:text-gray-700 p-1" aria-label="Deseleccionar todo" @click="clearSelection">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
        </div>
      </transition>

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
              <th class="px-3 py-2 text-left text-[11px] font-medium text-gray-500 uppercase tracking-wider">Folio</th>
              <th class="px-3 py-2 text-left text-[11px] font-medium text-gray-500 uppercase tracking-wider">Solicitante</th>
              <th class="px-3 py-2 text-left text-[11px] font-medium text-gray-500 uppercase tracking-wider">Monto</th>
              <th class="px-3 py-2 text-left text-[11px] font-medium text-gray-500 uppercase tracking-wider">Estado</th>
              <th class="px-3 py-2 text-left text-[11px] font-medium text-gray-500 uppercase tracking-wider">Fecha</th>
              <th class="px-3 py-2 text-left text-[11px] font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
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
                  <div class="text-sm font-medium text-gray-900">{{ app.applicant_name || 'Sin nombre' }}</div>
                  <div class="text-xs text-gray-500">{{ formatPhone(app.applicant_phone) }}</div>
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
                <div
                  v-if="getInactivityInfo(app)"
                  :class="['text-[10px] font-medium flex items-center gap-1 mt-0.5', getInactivityInfo(app)?.color]"
                >
                  <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                  </svg>
                  {{ getInactivityInfo(app)?.label }} sin actividad
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

            <tr v-if="applications.length === 0">
              <td colspan="7" class="px-6 py-12 text-center">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">No hay solicitudes</h3>
                <p class="mt-1 text-sm text-gray-500">No se encontraron solicitudes con los filtros seleccionados.</p>
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
            </p>
          </div>
          <div>
            <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Paginación">
              <button
                :disabled="currentPage === 1"
                class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                aria-label="Página anterior"
                @click="currentPage--"
              >
                <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
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
                :aria-label="`Página ${page}`"
                :aria-current="currentPage === page ? 'page' : undefined"
                @click="currentPage = page"
              >
                {{ page }}
              </button>
              <button
                :disabled="currentPage === totalPages"
                class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                aria-label="Página siguiente"
                @click="currentPage++"
              >
                <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
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
      title="Asignar Solicitudes"
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
          Selecciona el analista para
          <span class="font-semibold text-gray-900">{{ selectedIds.size }}</span>
          {{ selectedIds.size === 1 ? 'solicitud' : 'solicitudes' }}.
        </p>

        <div v-if="isLoadingAnalysts" class="flex justify-center py-8">
          <div class="animate-spin w-8 h-8 border-4 border-primary-600 border-t-transparent rounded-full" />
        </div>

        <div v-else-if="analysts.length === 0" class="text-center py-8">
          <p class="text-gray-500">No hay analistas disponibles</p>
        </div>

        <div v-else class="space-y-2">
          <div
            v-for="analyst in analysts"
            :key="analyst.id"
            class="flex items-center justify-between p-3 rounded-lg cursor-pointer transition-colors"
            :class="selectedAgentId === analyst.id ? 'bg-primary-50 border border-primary-200' : 'bg-gray-50 hover:bg-gray-100'"
            @click="selectedAgentId = analyst.id"
          >
            <div class="flex items-center gap-3">
              <div class="w-8 h-8 rounded-full bg-primary-100 flex items-center justify-center text-sm font-medium text-primary-700">
                {{ analyst.name.charAt(0).toUpperCase() }}
              </div>
              <div>
                <p class="font-medium text-gray-900 text-sm">{{ analyst.name }}</p>
                <p class="text-xs text-gray-500">{{ analyst.email }}</p>
              </div>
            </div>
            <svg v-if="selectedAgentId === analyst.id" class="w-5 h-5 text-primary-600" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
            </svg>
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
          <p class="text-sm text-red-800">
            Esta acción rechazará
            <span class="font-bold">{{ selectedIds.size }}</span>
            {{ selectedIds.size === 1 ? 'solicitud' : 'solicitudes' }}.
          </p>
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
