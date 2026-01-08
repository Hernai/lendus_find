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

// Filters
const searchQuery = ref('')
const statusFilter = ref('')
const currentPage = ref(1)
const itemsPerPage = ref(20)

// Data
const applications = ref<Application[]>([])
const totalItems = ref(0)
const totalPages = ref(1)
const isLoading = ref(true)
const error = ref('')

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

onMounted(() => {
  fetchApplications()
})

// Watch filters and refetch
watch([statusFilter, searchQuery], () => {
  currentPage.value = 1
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

const formatDate = (dateStr: string) => {
  return new Date(dateStr).toLocaleDateString('es-MX', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
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
  currentPage.value = 1
}

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

// Selection state
const selectedIds = ref<Set<string>>(new Set())
const showBulkAssignModal = ref(false)
const selectedAgentId = ref('')
const isAssigning = ref(false)

// Agents list - will be fetched from API
const agents = ref([
  { id: 'agent1', name: 'Admin Demo', email: 'admin@demo.com', active_cases: 0 },
  { id: 'agent2', name: 'Analista Demo', email: 'analista@demo.com', active_cases: 0 },
  { id: 'agent3', name: 'Agente Demo', email: 'agente@demo.com', active_cases: 0 },
])

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
const openBulkAssignModal = () => {
  selectedAgentId.value = ''
  showBulkAssignModal.value = true
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
            Asignar a Agente
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

    <!-- Filters -->
    <div class="bg-white rounded-xl shadow-sm p-3 mb-4">
      <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
        <!-- Search -->
        <div class="md:col-span-1">
          <label class="block text-xs font-medium text-gray-700 mb-1">Buscar</label>
          <div class="relative">
            <svg class="absolute left-2.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Nombre, folio, email..."
              class="w-full pl-8 pr-3 py-1.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
            >
          </div>
        </div>

        <!-- Status Filter -->
        <div>
          <label class="block text-xs font-medium text-gray-700 mb-1">Estado</label>
          <select
            v-model="statusFilter"
            class="w-full px-3 py-1.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
          >
            <option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">
              {{ opt.label }}
            </option>
          </select>
        </div>

        <!-- Clear Filters -->
        <div class="flex items-end">
          <button
            class="w-full px-3 py-1.5 text-sm text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition-colors"
            @click="clearFilters"
          >
            Limpiar filtros
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
                isSelected(app.id) ? 'bg-primary-50' : ''
              ]"
              @click="viewApplication(app)"
            >
              <td class="px-3 py-2.5 whitespace-nowrap" @click.stop>
                <input
                  type="checkbox"
                  :checked="isSelected(app.id)"
                  class="w-4 h-4 text-primary-600 border-gray-300 rounded focus:ring-primary-500 cursor-pointer"
                  @change="toggleSelection(app.id)"
                >
              </td>
              <td class="px-3 py-2.5 whitespace-nowrap">
                <div class="font-mono text-xs text-gray-900">{{ app.folio }}</div>
                <div class="text-[11px] text-gray-500">{{ app.product?.name || 'Sin producto' }}</div>
              </td>
              <td class="px-3 py-2.5 whitespace-nowrap">
                <div>
                  <div class="text-sm font-medium text-gray-900">{{ app.applicant?.name || 'Sin nombre' }}</div>
                  <div class="text-xs text-gray-500">{{ app.applicant?.phone || 'Sin teléfono' }}</div>
                </div>
              </td>
              <td class="px-3 py-2.5 whitespace-nowrap">
                <div class="text-sm font-semibold text-gray-900">{{ formatMoney(app.requested_amount) }}</div>
                <div class="text-xs text-gray-500">{{ app.term_months }} meses</div>
              </td>
              <td class="px-3 py-2.5 whitespace-nowrap">
                <span
                  :class="[
                    'px-2 py-0.5 text-[11px] font-medium rounded-full',
                    getStatusBadge(app.status).bg,
                    getStatusBadge(app.status).text
                  ]"
                >
                  {{ getStatusBadge(app.status).label }}
                </span>
                <div class="mt-0.5">
                  <span v-if="app.assigned_to" class="text-[11px] text-gray-500">{{ app.assigned_to }}</span>
                  <span v-else class="text-[11px] text-gray-400 italic">Sin asignar</span>
                </div>
              </td>
              <td class="px-3 py-2.5 whitespace-nowrap text-xs text-gray-500">
                {{ formatDate(app.created_at) }}
              </td>
              <td class="px-3 py-2.5 whitespace-nowrap text-xs">
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
      title="Asignar Solicitudes a Agente"
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
          Selecciona el agente al que deseas asignar
          <span class="font-semibold text-gray-900">{{ selectedIds.size }}</span>
          {{ selectedIds.size === 1 ? 'solicitud' : 'solicitudes' }}.
        </p>

        <div class="space-y-2">
          <label class="block text-sm font-medium text-gray-700">Agente</label>
          <div class="space-y-2 max-h-60 overflow-y-auto">
            <label
              v-for="agent in agents"
              :key="agent.id"
              :class="[
                'flex items-center p-3 border rounded-lg cursor-pointer transition-all',
                selectedAgentId === agent.id
                  ? 'border-primary-500 bg-primary-50 ring-2 ring-primary-500'
                  : 'border-gray-200 hover:border-gray-300 hover:bg-gray-50'
              ]"
            >
              <input
                v-model="selectedAgentId"
                type="radio"
                :value="agent.id"
                class="sr-only"
              >
              <div class="flex items-center gap-3 flex-1">
                <div class="w-10 h-10 bg-primary-100 rounded-full flex items-center justify-center">
                  <span class="text-primary-700 font-semibold text-sm">
                    {{ agent.name.split(' ').map(n => n[0]).join('').slice(0, 2) }}
                  </span>
                </div>
                <div class="flex-1">
                  <div class="font-medium text-gray-900">{{ agent.name }}</div>
                  <div class="text-sm text-gray-500">{{ agent.email }}</div>
                </div>
              </div>
              <svg
                v-if="selectedAgentId === agent.id"
                class="w-5 h-5 text-primary-600 ml-2"
                fill="currentColor"
                viewBox="0 0 20 20"
              >
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
              </svg>
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
