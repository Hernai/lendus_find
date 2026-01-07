<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'

const router = useRouter()

interface Application {
  id: string
  folio: string
  applicant_name: string
  requested_amount: number
  status: string
  created_at: string
  product_name: string
}

// Mock data - will be replaced with API calls
const applications = ref<Application[]>([
  { id: '1', folio: 'LEN-2026-00042', applicant_name: 'Juan Pérez García', requested_amount: 85000, status: 'SUBMITTED', created_at: new Date(Date.now() - 5 * 60000).toISOString(), product_name: 'Crédito Personal' },
  { id: '2', folio: 'LEN-2026-00041', applicant_name: 'María González López', requested_amount: 50000, status: 'SUBMITTED', created_at: new Date(Date.now() - 30 * 60000).toISOString(), product_name: 'Crédito Personal' },
  { id: '3', folio: 'LEN-2026-00040', applicant_name: 'Carlos Rodríguez', requested_amount: 120000, status: 'SUBMITTED', created_at: new Date(Date.now() - 2 * 3600000).toISOString(), product_name: 'Crédito Nómina' },
  { id: '4', folio: 'LEN-2026-00039', applicant_name: 'Ana Martínez', requested_amount: 75000, status: 'IN_REVIEW', created_at: new Date(Date.now() - 3 * 3600000).toISOString(), product_name: 'Crédito Personal' },
  { id: '5', folio: 'LEN-2026-00038', applicant_name: 'Roberto Hernández', requested_amount: 200000, status: 'IN_REVIEW', created_at: new Date(Date.now() - 5 * 3600000).toISOString(), product_name: 'Arrendamiento' },
  { id: '6', folio: 'LEN-2026-00037', applicant_name: 'Laura Sánchez', requested_amount: 45000, status: 'IN_REVIEW', created_at: new Date(Date.now() - 8 * 3600000).toISOString(), product_name: 'Crédito Personal' },
  { id: '7', folio: 'LEN-2026-00035', applicant_name: 'Pedro Ramírez', requested_amount: 95000, status: 'DOCS_PENDING', created_at: new Date(Date.now() - 24 * 3600000).toISOString(), product_name: 'Crédito Nómina' },
  { id: '8', folio: 'LEN-2026-00034', applicant_name: 'Sofía Torres', requested_amount: 60000, status: 'DOCS_PENDING', created_at: new Date(Date.now() - 48 * 3600000).toISOString(), product_name: 'Crédito Personal' },
  { id: '9', folio: 'LEN-2026-00030', applicant_name: 'Miguel Flores', requested_amount: 150000, status: 'APPROVED', created_at: new Date(Date.now() - 72 * 3600000).toISOString(), product_name: 'Arrendamiento' },
  { id: '10', folio: 'LEN-2026-00028', applicant_name: 'Carmen Díaz', requested_amount: 35000, status: 'APPROVED', created_at: new Date(Date.now() - 96 * 3600000).toISOString(), product_name: 'Crédito Personal' },
])

const columns = [
  { id: 'SUBMITTED', title: 'Nuevas', color: 'blue' },
  { id: 'IN_REVIEW', title: 'En Revisión', color: 'yellow' },
  { id: 'DOCS_PENDING', title: 'Docs Pendientes', color: 'orange' },
  { id: 'APPROVED', title: 'Aprobadas', color: 'green' }
]

const getColumnApps = (status: string) => {
  return applications.value.filter(app => app.status === status)
}

const formatMoney = (amount: number) => {
  return new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
    minimumFractionDigits: 0
  }).format(amount)
}

const formatTimeAgo = (dateStr: string) => {
  const date = new Date(dateStr)
  const now = new Date()
  const diffMs = now.getTime() - date.getTime()
  const diffMins = Math.floor(diffMs / 60000)
  const diffHours = Math.floor(diffMs / 3600000)
  const diffDays = Math.floor(diffMs / 86400000)

  if (diffMins < 60) return `Hace ${diffMins} min`
  if (diffHours < 24) return `Hace ${diffHours}h`
  return `Hace ${diffDays}d`
}

const getColumnColor = (color: string) => {
  const colors: Record<string, string> = {
    blue: 'bg-blue-500',
    yellow: 'bg-yellow-500',
    orange: 'bg-orange-500',
    green: 'bg-green-500'
  }
  return colors[color] || 'bg-gray-500'
}

const getColumnBg = (color: string) => {
  const colors: Record<string, string> = {
    blue: 'bg-blue-50',
    yellow: 'bg-yellow-50',
    orange: 'bg-orange-50',
    green: 'bg-green-50'
  }
  return colors[color] || 'bg-gray-50'
}

const viewApplication = (app: Application) => {
  router.push(`/admin/solicitudes/${app.id}`)
}

// Stats
const stats = computed(() => ({
  total: applications.value.length,
  nuevas: applications.value.filter(a => a.status === 'SUBMITTED').length,
  enRevision: applications.value.filter(a => a.status === 'IN_REVIEW').length,
  pendientes: applications.value.filter(a => a.status === 'DOCS_PENDING').length,
  aprobadas: applications.value.filter(a => a.status === 'APPROVED').length,
  montoTotal: applications.value.reduce((sum, a) => sum + a.requested_amount, 0)
}))
</script>

<template>
  <div>
    <!-- Header -->
    <div class="mb-6">
      <h1 class="text-2xl font-bold text-gray-900">Dashboard</h1>
      <p class="text-gray-500">Resumen de solicitudes de crédito</p>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
      <div class="bg-white rounded-xl p-4 shadow-sm">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm text-gray-500">Total Solicitudes</p>
            <p class="text-2xl font-bold text-gray-900">{{ stats.total }}</p>
          </div>
          <div class="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center">
            <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-xl p-4 shadow-sm">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm text-gray-500">Nuevas Hoy</p>
            <p class="text-2xl font-bold text-blue-600">{{ stats.nuevas }}</p>
          </div>
          <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
            <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
            </svg>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-xl p-4 shadow-sm">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm text-gray-500">En Proceso</p>
            <p class="text-2xl font-bold text-yellow-600">{{ stats.enRevision + stats.pendientes }}</p>
          </div>
          <div class="w-10 h-10 bg-yellow-100 rounded-lg flex items-center justify-center">
            <svg class="w-5 h-5 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-xl p-4 shadow-sm">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm text-gray-500">Monto Total</p>
            <p class="text-2xl font-bold text-green-600">{{ formatMoney(stats.montoTotal) }}</p>
          </div>
          <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
            <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
        </div>
      </div>
    </div>

    <!-- Kanban Board -->
    <div class="overflow-x-auto pb-4">
      <div class="flex gap-4 min-w-max">
        <div
          v-for="column in columns"
          :key="column.id"
          class="w-72 flex-shrink-0"
        >
          <!-- Column Header -->
          <div :class="['rounded-t-xl p-3', getColumnBg(column.color)]">
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-2">
                <div :class="['w-3 h-3 rounded-full', getColumnColor(column.color)]" />
                <h3 class="font-semibold text-gray-900">{{ column.title }}</h3>
              </div>
              <span class="bg-white px-2 py-0.5 rounded-full text-sm font-medium text-gray-600">
                {{ getColumnApps(column.id).length }}
              </span>
            </div>
          </div>

          <!-- Column Content -->
          <div class="bg-gray-100 rounded-b-xl p-2 min-h-[400px] space-y-2">
            <div
              v-for="app in getColumnApps(column.id)"
              :key="app.id"
              class="bg-white rounded-lg p-3 shadow-sm hover:shadow-md transition-shadow cursor-pointer"
              @click="viewApplication(app)"
            >
              <!-- Card Header -->
              <div class="flex items-start justify-between mb-2">
                <span class="text-xs font-mono text-gray-500">{{ app.folio }}</span>
                <span class="text-xs text-gray-400">{{ formatTimeAgo(app.created_at) }}</span>
              </div>

              <!-- Applicant Name -->
              <p class="font-medium text-gray-900 mb-1 truncate">{{ app.applicant_name }}</p>

              <!-- Product -->
              <p class="text-xs text-gray-500 mb-2">{{ app.product_name }}</p>

              <!-- Amount -->
              <div class="flex items-center justify-between">
                <span class="font-semibold text-primary-600">{{ formatMoney(app.requested_amount) }}</span>
                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
              </div>
            </div>

            <!-- Empty State -->
            <div
              v-if="getColumnApps(column.id).length === 0"
              class="flex flex-col items-center justify-center py-8 text-gray-400"
            >
              <svg class="w-8 h-8 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
              </svg>
              <p class="text-sm">Sin solicitudes</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
