<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { v2 } from '@/services/v2'
import type { V2BoardData, V2BoardItem, V2BoardColumn } from '@/services/v2/application.staff.service'
import type { V2ApplicationStatistics } from '@/types/v2'
import { logger } from '@/utils/logger'

const log = logger.child('AdminDashboard')
const router = useRouter()

// Board data from optimized endpoint
const boardData = ref<V2BoardData | null>(null)
const statistics = ref<V2ApplicationStatistics | null>(null)
const isLoading = ref(true)
const error = ref('')

// Column configuration with colors
const columnColors: Record<string, { color: string; title: string }> = {
  SUBMITTED: { color: 'blue', title: 'Nuevas' },
  IN_REVIEW: { color: 'yellow', title: 'En Revisión' },
  DOCS_PENDING: { color: 'orange', title: 'Docs Pendientes' },
  APPROVED: { color: 'green', title: 'Aprobadas' }
}

const fetchBoardData = async () => {
  isLoading.value = true
  error.value = ''

  try {
    // Fetch board data and statistics in parallel using optimized endpoints
    const [boardResponse, statsResponse] = await Promise.all([
      v2.staff.application.getBoard({
        columns: ['SUBMITTED', 'IN_REVIEW', 'DOCS_PENDING', 'APPROVED'],
        limit_per_column: 20,
        sort_by: 'created_at',
        sort_dir: 'desc'
      }),
      v2.staff.application.getStatistics()
    ])

    boardData.value = boardResponse.data ?? null
    statistics.value = statsResponse.data ?? null
  } catch (e) {
    log.error('Error al cargar solicitudes', { error: e })
    error.value = 'Error al cargar las solicitudes'
  } finally {
    isLoading.value = false
  }
}

onMounted(() => {
  fetchBoardData()
})

// Get column config
const getColumnConfig = (status: string) => {
  return columnColors[status] || { color: 'gray', title: status }
}

const formatMoney = (amount: number) => {
  return new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
    minimumFractionDigits: 0
  }).format(amount)
}

const formatTimeAgo = (dateStr: string | null) => {
  if (!dateStr) return ''
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

const viewApplication = (app: V2BoardItem) => {
  router.push(`/admin/solicitudes/${app.id}`)
}

// Stats computed from board data and statistics
const stats = computed(() => {
  const byStatus = boardData.value?.totals.by_status || {}
  const statsData = statistics.value

  if (statsData) {
    // Use statistics endpoint for accurate totals
    const statsByStatus = statsData.by_status || {}
    return {
      total: statsData.total,
      nuevas: statsByStatus.submitted || byStatus.SUBMITTED || 0,
      enRevision: statsByStatus.in_review || byStatus.IN_REVIEW || 0,
      pendientes: statsByStatus.docs_pending || byStatus.DOCS_PENDING || 0,
      aprobadas: statsByStatus.approved || byStatus.APPROVED || 0,
      montoTotal: 0 // Calculate from board items if needed
    }
  }

  return {
    total: boardData.value?.totals.all || 0,
    nuevas: byStatus.SUBMITTED || 0,
    enRevision: byStatus.IN_REVIEW || 0,
    pendientes: byStatus.DOCS_PENDING || 0,
    aprobadas: byStatus.APPROVED || 0,
    montoTotal: 0
  }
})

// Check if board has any items
const hasApplications = computed(() => {
  return boardData.value?.columns.some(col => col.items.length > 0) || false
})
</script>

<template>
  <div>
    <!-- Header -->
    <div class="mb-6 flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Dashboard</h1>
        <p class="text-gray-500">Resumen de solicitudes de crédito</p>
      </div>
      <button
        @click="fetchBoardData"
        class="flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50"
        :disabled="isLoading"
      >
        <svg
          class="w-4 h-4"
          :class="{ 'animate-spin': isLoading }"
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
        >
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
        </svg>
        Actualizar
      </button>
    </div>

    <!-- Loading State -->
    <div v-if="isLoading && !boardData" class="flex items-center justify-center py-12">
      <div class="animate-spin w-8 h-8 border-4 border-primary-500 border-t-transparent rounded-full"></div>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="bg-red-50 border border-red-200 rounded-xl p-6 text-center">
      <svg class="w-12 h-12 mx-auto text-red-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
      </svg>
      <p class="text-red-600">{{ error }}</p>
      <button
        @click="fetchBoardData"
        class="mt-4 px-4 py-2 text-sm font-medium text-red-600 hover:text-red-700"
      >
        Reintentar
      </button>
    </div>

    <template v-else-if="boardData">
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
              <p class="text-sm text-gray-500">Nuevas</p>
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
              <p class="text-sm text-gray-500">Aprobadas</p>
              <p class="text-2xl font-bold text-green-600">{{ stats.aprobadas }}</p>
            </div>
            <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
              <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </div>
          </div>
        </div>
      </div>

      <!-- Empty State -->
      <div v-if="!hasApplications" class="bg-white rounded-xl p-12 text-center">
        <svg class="w-16 h-16 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
        </svg>
        <h3 class="text-lg font-medium text-gray-900 mb-2">No hay solicitudes activas</h3>
        <p class="text-gray-500">Las nuevas solicitudes aparecerán aquí</p>
      </div>

      <!-- Kanban Board -->
      <div v-else class="overflow-x-auto pb-4">
        <div class="flex gap-4 min-w-max">
          <div
            v-for="column in boardData.columns"
            :key="column.status"
            class="w-72 flex-shrink-0 border border-gray-200 rounded-xl overflow-hidden shadow-sm"
          >
            <!-- Column Header -->
            <div :class="['p-3', getColumnBg(getColumnConfig(column.status).color)]">
              <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                  <div :class="['w-3 h-3 rounded-full', getColumnColor(getColumnConfig(column.status).color)]" />
                  <h3 class="font-semibold text-gray-900">{{ getColumnConfig(column.status).title }}</h3>
                </div>
                <span class="bg-white px-2 py-0.5 rounded-full text-sm font-medium text-gray-600">
                  {{ column.count }}
                </span>
              </div>
            </div>

            <!-- Column Content -->
            <div class="bg-gray-50 p-2 min-h-[500px] space-y-2">
              <div
                v-for="app in column.items"
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
                <p class="font-medium text-gray-900 mb-1 truncate">
                  {{ app.applicant_name || 'Sin nombre' }}
                </p>

                <!-- Product -->
                <p class="text-xs text-gray-500 mb-2">{{ app.product?.name || 'Sin producto' }}</p>

                <!-- Amount -->
                <div class="flex items-center justify-between">
                  <span class="font-semibold text-primary-600">{{ formatMoney(app.requested_amount) }}</span>
                  <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                  </svg>
                </div>
              </div>

              <!-- Has More Indicator -->
              <div
                v-if="column.has_more"
                class="text-center py-2 text-sm text-gray-500"
              >
                <router-link
                  :to="{ path: '/admin/solicitudes', query: { status: column.status } }"
                  class="text-primary-600 hover:text-primary-700 font-medium"
                >
                  Ver todas ({{ column.count }})
                </router-link>
              </div>

              <!-- Empty State -->
              <div
                v-if="column.items.length === 0"
                class="flex flex-col items-center justify-center py-12 text-gray-400"
              >
                <svg class="w-10 h-10 mb-2 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <p class="text-sm">Sin solicitudes</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>
