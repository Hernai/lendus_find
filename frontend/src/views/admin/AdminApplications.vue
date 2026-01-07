<script setup lang="ts">
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { AppButton, AppConfirmModal } from '@/components/common'

const router = useRouter()

interface ApplicationCompleteness {
  personal_data: boolean
  address: boolean
  employment: boolean
  documents: { uploaded: number; required: number; approved: number }
  references: { count: number; verified: number }
  signature: boolean
}

interface Application {
  id: string
  folio: string
  applicant_name: string
  email: string
  phone: string
  requested_amount: number
  term_months: number
  status: string
  created_at: string
  updated_at: string
  product_name: string
  assigned_to?: string
  completeness: ApplicationCompleteness
}

// Filters
const searchQuery = ref('')
const statusFilter = ref('')
const productFilter = ref('')
const dateFrom = ref('')
const dateTo = ref('')
const currentPage = ref(1)
const itemsPerPage = ref(10)

const statusOptions = [
  { value: '', label: 'Todos los estados' },
  { value: 'SUBMITTED', label: 'Nueva' },
  { value: 'IN_REVIEW', label: 'En Revisión' },
  { value: 'DOCS_PENDING', label: 'Docs Pendientes' },
  { value: 'COUNTER_OFFERED', label: 'Contraoferta' },
  { value: 'APPROVED', label: 'Aprobada' },
  { value: 'REJECTED', label: 'Rechazada' },
  { value: 'CANCELLED', label: 'Cancelada' },
  { value: 'DISBURSED', label: 'Desembolsada' }
]

const productOptions = [
  { value: '', label: 'Todos los productos' },
  { value: 'PERSONAL', label: 'Crédito Personal' },
  { value: 'NOMINA', label: 'Crédito Nómina' },
  { value: 'ARRENDAMIENTO', label: 'Arrendamiento' }
]

// Mock data - will be replaced with API calls
const applications = ref<Application[]>([
  { id: '1', folio: 'LEN-2026-00042', applicant_name: 'Juan Pérez García', email: 'juan.perez@email.com', phone: '5512345678', requested_amount: 85000, term_months: 12, status: 'SUBMITTED', created_at: new Date(Date.now() - 5 * 60000).toISOString(), updated_at: new Date(Date.now() - 5 * 60000).toISOString(), product_name: 'Crédito Personal', completeness: { personal_data: true, address: true, employment: false, documents: { uploaded: 1, required: 3, approved: 0 }, references: { count: 0, verified: 0 }, signature: false } },
  { id: '2', folio: 'LEN-2026-00041', applicant_name: 'María González López', email: 'maria.gonzalez@email.com', phone: '5598765432', requested_amount: 50000, term_months: 6, status: 'SUBMITTED', created_at: new Date(Date.now() - 30 * 60000).toISOString(), updated_at: new Date(Date.now() - 30 * 60000).toISOString(), product_name: 'Crédito Personal', completeness: { personal_data: true, address: false, employment: false, documents: { uploaded: 0, required: 3, approved: 0 }, references: { count: 0, verified: 0 }, signature: false } },
  { id: '3', folio: 'LEN-2026-00040', applicant_name: 'Carlos Rodríguez Martínez', email: 'carlos.rodriguez@email.com', phone: '5511223344', requested_amount: 120000, term_months: 24, status: 'SUBMITTED', created_at: new Date(Date.now() - 2 * 3600000).toISOString(), updated_at: new Date(Date.now() - 2 * 3600000).toISOString(), product_name: 'Crédito Nómina', completeness: { personal_data: true, address: true, employment: true, documents: { uploaded: 2, required: 3, approved: 1 }, references: { count: 1, verified: 0 }, signature: false } },
  { id: '4', folio: 'LEN-2026-00039', applicant_name: 'Ana Martínez Sánchez', email: 'ana.martinez@email.com', phone: '5544556677', requested_amount: 75000, term_months: 18, status: 'IN_REVIEW', created_at: new Date(Date.now() - 3 * 3600000).toISOString(), updated_at: new Date(Date.now() - 1 * 3600000).toISOString(), product_name: 'Crédito Personal', assigned_to: 'Carlos Ramírez', completeness: { personal_data: true, address: true, employment: true, documents: { uploaded: 3, required: 3, approved: 2 }, references: { count: 2, verified: 1 }, signature: true } },
  { id: '5', folio: 'LEN-2026-00038', applicant_name: 'Roberto Hernández Torres', email: 'roberto.hernandez@email.com', phone: '5588990011', requested_amount: 200000, term_months: 36, status: 'IN_REVIEW', created_at: new Date(Date.now() - 5 * 3600000).toISOString(), updated_at: new Date(Date.now() - 2 * 3600000).toISOString(), product_name: 'Arrendamiento', assigned_to: 'María López', completeness: { personal_data: true, address: true, employment: true, documents: { uploaded: 3, required: 3, approved: 3 }, references: { count: 2, verified: 2 }, signature: true } },
  { id: '6', folio: 'LEN-2026-00037', applicant_name: 'Laura Sánchez Flores', email: 'laura.sanchez@email.com', phone: '5522334455', requested_amount: 45000, term_months: 12, status: 'IN_REVIEW', created_at: new Date(Date.now() - 8 * 3600000).toISOString(), updated_at: new Date(Date.now() - 4 * 3600000).toISOString(), product_name: 'Crédito Personal', assigned_to: 'Carlos Ramírez', completeness: { personal_data: true, address: true, employment: true, documents: { uploaded: 3, required: 3, approved: 1 }, references: { count: 2, verified: 0 }, signature: true } },
  { id: '7', folio: 'LEN-2026-00035', applicant_name: 'Pedro Ramírez Díaz', email: 'pedro.ramirez@email.com', phone: '5566778899', requested_amount: 95000, term_months: 24, status: 'DOCS_PENDING', created_at: new Date(Date.now() - 24 * 3600000).toISOString(), updated_at: new Date(Date.now() - 12 * 3600000).toISOString(), product_name: 'Crédito Nómina', completeness: { personal_data: true, address: true, employment: true, documents: { uploaded: 1, required: 3, approved: 1 }, references: { count: 2, verified: 2 }, signature: true } },
  { id: '8', folio: 'LEN-2026-00034', applicant_name: 'Sofía Torres Ramírez', email: 'sofia.torres@email.com', phone: '5533445566', requested_amount: 60000, term_months: 18, status: 'DOCS_PENDING', created_at: new Date(Date.now() - 48 * 3600000).toISOString(), updated_at: new Date(Date.now() - 24 * 3600000).toISOString(), product_name: 'Crédito Personal', completeness: { personal_data: true, address: true, employment: true, documents: { uploaded: 2, required: 3, approved: 2 }, references: { count: 1, verified: 1 }, signature: true } },
  { id: '9', folio: 'LEN-2026-00030', applicant_name: 'Miguel Flores García', email: 'miguel.flores@email.com', phone: '5577889900', requested_amount: 150000, term_months: 36, status: 'APPROVED', created_at: new Date(Date.now() - 72 * 3600000).toISOString(), updated_at: new Date(Date.now() - 48 * 3600000).toISOString(), product_name: 'Arrendamiento', completeness: { personal_data: true, address: true, employment: true, documents: { uploaded: 3, required: 3, approved: 3 }, references: { count: 2, verified: 2 }, signature: true } },
  { id: '10', folio: 'LEN-2026-00028', applicant_name: 'Carmen Díaz López', email: 'carmen.diaz@email.com', phone: '5500112233', requested_amount: 35000, term_months: 6, status: 'APPROVED', created_at: new Date(Date.now() - 96 * 3600000).toISOString(), updated_at: new Date(Date.now() - 72 * 3600000).toISOString(), product_name: 'Crédito Personal', completeness: { personal_data: true, address: true, employment: true, documents: { uploaded: 3, required: 3, approved: 3 }, references: { count: 2, verified: 2 }, signature: true } },
  { id: '11', folio: 'LEN-2026-00025', applicant_name: 'Fernando López Hernández', email: 'fernando.lopez@email.com', phone: '5511224455', requested_amount: 80000, term_months: 12, status: 'REJECTED', created_at: new Date(Date.now() - 120 * 3600000).toISOString(), updated_at: new Date(Date.now() - 96 * 3600000).toISOString(), product_name: 'Crédito Nómina', completeness: { personal_data: true, address: true, employment: true, documents: { uploaded: 3, required: 3, approved: 3 }, references: { count: 2, verified: 2 }, signature: true } },
  { id: '12', folio: 'LEN-2026-00020', applicant_name: 'Patricia Gómez Ruiz', email: 'patricia.gomez@email.com', phone: '5533556677', requested_amount: 100000, term_months: 24, status: 'DISBURSED', created_at: new Date(Date.now() - 168 * 3600000).toISOString(), updated_at: new Date(Date.now() - 120 * 3600000).toISOString(), product_name: 'Crédito Personal', completeness: { personal_data: true, address: true, employment: true, documents: { uploaded: 3, required: 3, approved: 3 }, references: { count: 2, verified: 2 }, signature: true } },
])

// Computed filtered list
const filteredApplications = computed(() => {
  return applications.value.filter(app => {
    // Search filter
    if (searchQuery.value) {
      const query = searchQuery.value.toLowerCase()
      const matchesName = app.applicant_name.toLowerCase().includes(query)
      const matchesFolio = app.folio.toLowerCase().includes(query)
      const matchesEmail = app.email.toLowerCase().includes(query)
      const matchesPhone = app.phone.includes(query)
      if (!matchesName && !matchesFolio && !matchesEmail && !matchesPhone) {
        return false
      }
    }

    // Status filter
    if (statusFilter.value && app.status !== statusFilter.value) {
      return false
    }

    // Product filter
    if (productFilter.value) {
      const productMap: Record<string, string> = {
        'PERSONAL': 'Crédito Personal',
        'NOMINA': 'Crédito Nómina',
        'ARRENDAMIENTO': 'Arrendamiento'
      }
      if (app.product_name !== productMap[productFilter.value]) {
        return false
      }
    }

    // Date range filter
    if (dateFrom.value) {
      const fromDate = new Date(dateFrom.value)
      const appDate = new Date(app.created_at)
      if (appDate < fromDate) return false
    }

    if (dateTo.value) {
      const toDate = new Date(dateTo.value)
      toDate.setHours(23, 59, 59, 999)
      const appDate = new Date(app.created_at)
      if (appDate > toDate) return false
    }

    return true
  })
})

// Pagination
const totalPages = computed(() => Math.ceil(filteredApplications.value.length / itemsPerPage.value))
const paginatedApplications = computed(() => {
  const start = (currentPage.value - 1) * itemsPerPage.value
  const end = start + itemsPerPage.value
  return filteredApplications.value.slice(start, end)
})

// Reset pagination when filters change
const resetPage = () => {
  currentPage.value = 1
}

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
    SUBMITTED: { bg: 'bg-blue-100', text: 'text-blue-800', label: 'Nueva' },
    IN_REVIEW: { bg: 'bg-yellow-100', text: 'text-yellow-800', label: 'En Revisión' },
    DOCS_PENDING: { bg: 'bg-orange-100', text: 'text-orange-800', label: 'Docs Pendientes' },
    COUNTER_OFFERED: { bg: 'bg-indigo-100', text: 'text-indigo-800', label: 'Contraoferta' },
    APPROVED: { bg: 'bg-green-100', text: 'text-green-800', label: 'Aprobada' },
    REJECTED: { bg: 'bg-red-100', text: 'text-red-800', label: 'Rechazada' },
    CANCELLED: { bg: 'bg-gray-100', text: 'text-gray-800', label: 'Cancelada' },
    DISBURSED: { bg: 'bg-purple-100', text: 'text-purple-800', label: 'Desembolsada' }
  }
  return badges[status] || { bg: 'bg-gray-100', text: 'text-gray-800', label: status }
}

const viewApplication = (app: Application) => {
  router.push(`/admin/solicitudes/${app.id}`)
}

const clearFilters = () => {
  searchQuery.value = ''
  statusFilter.value = ''
  productFilter.value = ''
  dateFrom.value = ''
  dateTo.value = ''
  currentPage.value = 1
}

const exportToCSV = () => {
  const headers = ['Folio', 'Solicitante', 'Email', 'Teléfono', 'Monto', 'Plazo', 'Producto', 'Estado', 'Fecha']
  const rows = filteredApplications.value.map(app => [
    app.folio,
    app.applicant_name,
    app.email,
    app.phone,
    app.requested_amount,
    `${app.term_months} meses`,
    app.product_name,
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

// Agents list
const agents = ref([
  { id: 'agent1', name: 'Carlos Ramírez', email: 'carlos.ramirez@lendus.mx', active_cases: 12 },
  { id: 'agent2', name: 'María López', email: 'maria.lopez@lendus.mx', active_cases: 8 },
  { id: 'agent3', name: 'Juan Hernández', email: 'juan.hernandez@lendus.mx', active_cases: 15 },
  { id: 'agent4', name: 'Ana García', email: 'ana.garcia@lendus.mx', active_cases: 5 },
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
  if (selectedIds.value.size === paginatedApplications.value.length) {
    selectedIds.value.clear()
  } else {
    paginatedApplications.value.forEach(app => selectedIds.value.add(app.id))
  }
}

const isAllSelected = computed(() => {
  return paginatedApplications.value.length > 0 &&
    selectedIds.value.size === paginatedApplications.value.length
})

const isSomeSelected = computed(() => {
  return selectedIds.value.size > 0 &&
    selectedIds.value.size < paginatedApplications.value.length
})

const clearSelection = () => {
  selectedIds.value.clear()
}

// Completeness calculation
const getCompletenessPercent = (c: ApplicationCompleteness): number => {
  let completed = 0
  let total = 6

  if (c.personal_data) completed++
  if (c.address) completed++
  if (c.employment) completed++
  if (c.documents.uploaded >= c.documents.required) completed++
  if (c.references.count >= 2) completed++
  if (c.signature) completed++

  return Math.round((completed / total) * 100)
}

const getCompletenessColor = (percent: number): string => {
  if (percent >= 100) return 'bg-green-500'
  if (percent >= 75) return 'bg-blue-500'
  if (percent >= 50) return 'bg-yellow-500'
  return 'bg-red-500'
}

const getMissingItems = (c: ApplicationCompleteness): string[] => {
  const missing: string[] = []
  if (!c.personal_data) missing.push('Datos personales')
  if (!c.address) missing.push('Domicilio')
  if (!c.employment) missing.push('Empleo')
  if (c.documents.uploaded < c.documents.required) {
    missing.push(`Documentos (${c.documents.uploaded}/${c.documents.required})`)
  }
  if (c.references.count < 2) {
    missing.push(`Referencias (${c.references.count}/2)`)
  }
  if (!c.signature) missing.push('Firma')
  return missing
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
  const agent = agents.value.find(a => a.id === selectedAgentId.value)

  // Simulate API call
  await new Promise(resolve => setTimeout(resolve, 1000))

  // Update applications
  applications.value = applications.value.map(app => {
    if (selectedIds.value.has(app.id)) {
      return { ...app, assigned_to: agent?.name }
    }
    return app
  })

  isAssigning.value = false
  clearSelection()
  closeBulkAssignModal()
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

  // Simulate API call
  await new Promise(resolve => setTimeout(resolve, 1000))

  // Update applications
  applications.value = applications.value.map(app => {
    if (selectedIds.value.has(app.id)) {
      return { ...app, status: 'REJECTED' }
    }
    return app
  })

  isRejecting.value = false
  clearSelection()
  closeBulkRejectModal()
}
</script>

<template>
  <div>
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Solicitudes</h1>
        <p class="text-gray-500">{{ filteredApplications.length }} solicitudes encontradas</p>
      </div>
      <button
        class="flex items-center gap-2 px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors"
        @click="exportToCSV"
      >
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
        </svg>
        Exportar CSV
      </button>
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
    <div class="bg-white rounded-xl shadow-sm p-4 mb-6">
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
        <!-- Search -->
        <div class="lg:col-span-2">
          <label class="block text-sm font-medium text-gray-700 mb-1">Buscar</label>
          <div class="relative">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Nombre, folio, email, teléfono..."
              class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
              @input="resetPage"
            >
          </div>
        </div>

        <!-- Status Filter -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Estado</label>
          <select
            v-model="statusFilter"
            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
            @change="resetPage"
          >
            <option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">
              {{ opt.label }}
            </option>
          </select>
        </div>

        <!-- Product Filter -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Producto</label>
          <select
            v-model="productFilter"
            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
            @change="resetPage"
          >
            <option v-for="opt in productOptions" :key="opt.value" :value="opt.value">
              {{ opt.label }}
            </option>
          </select>
        </div>

        <!-- Clear Filters -->
        <div class="flex items-end">
          <button
            class="w-full px-4 py-2 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition-colors"
            @click="clearFilters"
          >
            Limpiar filtros
          </button>
        </div>
      </div>

      <!-- Date Range (collapsible) -->
      <div class="mt-4 pt-4 border-t border-gray-200">
        <div class="grid grid-cols-2 gap-4 max-w-md">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Desde</label>
            <input
              v-model="dateFrom"
              type="date"
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
              @change="resetPage"
            >
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Hasta</label>
            <input
              v-model="dateTo"
              type="date"
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
              @change="resetPage"
            >
          </div>
        </div>
      </div>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-4 py-3 text-left">
                <input
                  type="checkbox"
                  :checked="isAllSelected"
                  :indeterminate="isSomeSelected"
                  class="w-4 h-4 text-primary-600 border-gray-300 rounded focus:ring-primary-500 cursor-pointer"
                  @change="toggleSelectAll"
                >
              </th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Folio
              </th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Solicitante
              </th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Avance
              </th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Monto
              </th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Estado
              </th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Fecha
              </th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Acciones
              </th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <tr
              v-for="app in paginatedApplications"
              :key="app.id"
              :class="[
                'hover:bg-gray-50 cursor-pointer transition-colors',
                isSelected(app.id) ? 'bg-primary-50' : ''
              ]"
              @click="viewApplication(app)"
            >
              <td class="px-4 py-4 whitespace-nowrap" @click.stop>
                <input
                  type="checkbox"
                  :checked="isSelected(app.id)"
                  class="w-4 h-4 text-primary-600 border-gray-300 rounded focus:ring-primary-500 cursor-pointer"
                  @change="toggleSelection(app.id)"
                >
              </td>
              <td class="px-4 py-4 whitespace-nowrap">
                <div class="font-mono text-sm text-gray-900">{{ app.folio }}</div>
                <div class="text-xs text-gray-500">{{ app.product_name }}</div>
              </td>
              <td class="px-4 py-4 whitespace-nowrap">
                <div>
                  <div class="font-medium text-gray-900">{{ app.applicant_name }}</div>
                  <div class="text-sm text-gray-500">{{ app.phone }}</div>
                </div>
              </td>
              <td class="px-4 py-4 whitespace-nowrap">
                <div class="group relative">
                  <div class="flex items-center gap-2">
                    <div class="w-20 h-2 bg-gray-200 rounded-full overflow-hidden">
                      <div
                        :class="['h-full rounded-full transition-all', getCompletenessColor(getCompletenessPercent(app.completeness))]"
                        :style="{ width: getCompletenessPercent(app.completeness) + '%' }"
                      />
                    </div>
                    <span class="text-xs font-medium text-gray-600">{{ getCompletenessPercent(app.completeness) }}%</span>
                  </div>
                  <!-- Tooltip -->
                  <div
                    v-if="getMissingItems(app.completeness).length > 0"
                    class="absolute left-0 bottom-full mb-2 hidden group-hover:block z-10"
                  >
                    <div class="bg-gray-900 text-white text-xs rounded-lg py-2 px-3 shadow-lg whitespace-nowrap">
                      <div class="font-semibold mb-1">Pendiente:</div>
                      <ul class="space-y-0.5">
                        <li v-for="item in getMissingItems(app.completeness)" :key="item" class="flex items-center gap-1">
                          <svg class="w-3 h-3 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                          </svg>
                          {{ item }}
                        </li>
                      </ul>
                      <div class="absolute left-4 top-full w-0 h-0 border-l-4 border-r-4 border-t-4 border-transparent border-t-gray-900" />
                    </div>
                  </div>
                  <div
                    v-else
                    class="absolute left-0 bottom-full mb-2 hidden group-hover:block z-10"
                  >
                    <div class="bg-green-700 text-white text-xs rounded-lg py-2 px-3 shadow-lg whitespace-nowrap flex items-center gap-1">
                      <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                      </svg>
                      Expediente completo
                      <div class="absolute left-4 top-full w-0 h-0 border-l-4 border-r-4 border-t-4 border-transparent border-t-green-700" />
                    </div>
                  </div>
                </div>
              </td>
              <td class="px-4 py-4 whitespace-nowrap">
                <div class="font-semibold text-gray-900">{{ formatMoney(app.requested_amount) }}</div>
                <div class="text-sm text-gray-500">{{ app.term_months }} meses</div>
              </td>
              <td class="px-4 py-4 whitespace-nowrap">
                <span
                  :class="[
                    'px-2 py-1 text-xs font-medium rounded-full',
                    getStatusBadge(app.status).bg,
                    getStatusBadge(app.status).text
                  ]"
                >
                  {{ getStatusBadge(app.status).label }}
                </span>
                <div class="mt-1">
                  <span v-if="app.assigned_to" class="text-xs text-gray-500">{{ app.assigned_to }}</span>
                  <span v-else class="text-xs text-gray-400 italic">Sin asignar</span>
                </div>
              </td>
              <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500">
                {{ formatDate(app.created_at) }}
              </td>
              <td class="px-4 py-4 whitespace-nowrap text-sm">
                <button
                  class="text-primary-600 hover:text-primary-900 font-medium"
                  @click.stop="viewApplication(app)"
                >
                  Ver detalle
                </button>
              </td>
            </tr>

            <!-- Empty state -->
            <tr v-if="paginatedApplications.length === 0">
              <td colspan="8" class="px-6 py-12 text-center">
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
              <span class="font-medium">{{ Math.min(currentPage * itemsPerPage, filteredApplications.length) }}</span>
              de
              <span class="font-medium">{{ filteredApplications.length }}</span>
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
                v-for="page in totalPages"
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
                <div class="text-right">
                  <div class="text-sm font-medium text-gray-900">{{ agent.active_cases }}</div>
                  <div class="text-xs text-gray-500">casos activos</div>
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

        <div v-if="bulkRejectReason === 'OTRO'" class="space-y-2">
          <label class="block text-sm font-medium text-gray-700">Especifica el motivo</label>
          <textarea
            rows="3"
            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent resize-none"
            placeholder="Describe el motivo del rechazo..."
          />
        </div>
      </div>
    </AppConfirmModal>
  </div>
</template>
