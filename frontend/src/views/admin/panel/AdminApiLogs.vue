<script setup lang="ts">
import { ref, onMounted, watch, computed } from 'vue'
import { v2 } from '@/services/v2'
import type { V2ApiLog, V2ApiLogStats } from '@/services/v2/apilog.staff.service'
import { logger } from '@/utils/logger'
import { formatDateTime } from '@/utils/formatters'

const componentLog = logger.child('AdminApiLogs')

// Use V2 types
type ApiLog = V2ApiLog
type Stats = V2ApiLogStats

// State
const logs = ref<ApiLog[]>([])
const stats = ref<Stats | null>(null)
const providers = ref<string[]>([])
const isLoading = ref(false)
const isLoadingStats = ref(false)

// Filters
const filters = ref({
  provider: 'all',
  success: 'all',
  service: '',
  from_date: '',
  to_date: ''
})

// Pagination
const currentPage = ref(1)
const lastPage = ref(1)
const total = ref(0)
const perPage = 20

// Detail modal
const selectedLog = ref<ApiLog | null>(null)
const showDetailModal = ref(false)
const isLoadingDetail = ref(false)

// Computed
const successRate = computed(() => {
  if (!stats.value || stats.value.today.total === 0) return 0
  return Math.round((stats.value.today.successful / stats.value.today.total) * 100)
})

// Load logs using V2 API
const loadLogs = async () => {
  isLoading.value = true
  try {
    const response = await v2.staff.apiLog.list({
      page: currentPage.value,
      per_page: perPage,
      provider: filters.value.provider !== 'all' ? filters.value.provider : undefined,
      success: filters.value.success !== 'all' ? filters.value.success as 'true' | 'false' : undefined,
      service: filters.value.service || undefined,
      from_date: filters.value.from_date || undefined,
      to_date: filters.value.to_date || undefined,
    })

    logs.value = response.data?.logs ?? []
    currentPage.value = response.data?.meta.current_page ?? 1
    lastPage.value = response.data?.meta.last_page ?? 1
    total.value = response.data?.meta.total ?? 0
  } catch (e) {
    componentLog.error('Error al cargar logs de API', { error: e })
  } finally {
    isLoading.value = false
  }
}

// Load stats using V2 API
const loadStats = async () => {
  isLoadingStats.value = true
  try {
    const response = await v2.staff.apiLog.getStats()
    stats.value = response.data ?? null
  } catch (e) {
    componentLog.error('Error al cargar estadísticas', { error: e })
  } finally {
    isLoadingStats.value = false
  }
}

// Load providers using V2 API
const loadProviders = async () => {
  try {
    const response = await v2.staff.apiLog.getProviders()
    providers.value = response.data?.providers ?? []
  } catch (e) {
    componentLog.error('Error al cargar proveedores', { error: e })
  }
}

// Load log detail using V2 API
const loadLogDetail = async (apiLog: ApiLog) => {
  selectedLog.value = apiLog
  showDetailModal.value = true
  isLoadingDetail.value = true
  try {
    const response = await v2.staff.apiLog.get(apiLog.id)
    selectedLog.value = response.data?.log ?? null
  } catch (e) {
    componentLog.error('Error al cargar detalle del log', { error: e })
  } finally {
    isLoadingDetail.value = false
  }
}

// Use centralized formatter (alias for template compatibility)
const formatDate = formatDateTime

// Format duration
const formatDuration = (ms: number | null): string => {
  if (ms === null) return '-'
  if (ms < 1000) return `${ms}ms`
  return `${(ms / 1000).toFixed(2)}s`
}

// Format JSON for display
const formatJson = (obj: Record<string, unknown> | null): string => {
  if (!obj) return '{}'
  return JSON.stringify(obj, null, 2)
}

// Apply filters
const applyFilters = () => {
  currentPage.value = 1
  loadLogs()
}

// Reset filters
const resetFilters = () => {
  filters.value = {
    provider: 'all',
    success: 'all',
    service: '',
    from_date: '',
    to_date: ''
  }
  currentPage.value = 1
  loadLogs()
}

// Pagination
const goToPage = (page: number) => {
  if (page < 1 || page > lastPage.value) return
  currentPage.value = page
  loadLogs()
}

// Watch for filter changes (with debounce for text input)
let searchTimeout: ReturnType<typeof setTimeout> | null = null
watch(() => filters.value.service, () => {
  if (searchTimeout) clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    applyFilters()
  }, 500)
})

// Load on mount
onMounted(() => {
  loadLogs()
  loadStats()
  loadProviders()
})
</script>

<template>
  <div class="p-6 max-w-7xl mx-auto">
    <!-- Header -->
    <div class="mb-6">
      <h1 class="text-2xl font-bold text-gray-900">Logs de APIs Externas</h1>
      <p class="text-sm text-gray-500 mt-1">Historial de llamadas a servicios externos (Nubarium, Twilio, etc.)</p>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
      <div class="bg-white rounded-xl border border-gray-200 p-4">
        <p class="text-xs text-gray-500 uppercase tracking-wide">Hoy</p>
        <p class="text-2xl font-bold text-gray-900">{{ stats?.today.total || 0 }}</p>
        <p class="text-xs text-gray-500">llamadas</p>
      </div>
      <div class="bg-white rounded-xl border border-gray-200 p-4">
        <p class="text-xs text-gray-500 uppercase tracking-wide">Exitosas Hoy</p>
        <p class="text-2xl font-bold text-green-600">{{ stats?.today.successful || 0 }}</p>
        <p class="text-xs text-gray-500">{{ successRate }}% tasa de exito</p>
      </div>
      <div class="bg-white rounded-xl border border-gray-200 p-4">
        <p class="text-xs text-gray-500 uppercase tracking-wide">Fallidas Hoy</p>
        <p class="text-2xl font-bold text-red-600">{{ stats?.today.failed || 0 }}</p>
        <p class="text-xs text-gray-500">errores</p>
      </div>
      <div class="bg-white rounded-xl border border-gray-200 p-4">
        <p class="text-xs text-gray-500 uppercase tracking-wide">Tiempo Promedio</p>
        <p class="text-2xl font-bold text-gray-900">{{ formatDuration(stats?.avg_duration_ms || 0) }}</p>
        <p class="text-xs text-gray-500">ultimos 7 dias</p>
      </div>
    </div>

    <!-- By Provider Stats -->
    <div v-if="stats?.by_provider && stats.by_provider.length > 0" class="bg-white rounded-xl border border-gray-200 p-4 mb-6">
      <h3 class="text-sm font-semibold text-gray-900 mb-3">Por Proveedor (ultimos 7 dias)</h3>
      <div class="flex flex-wrap gap-4">
        <div v-for="provider in stats.by_provider" :key="provider.provider" class="flex items-center gap-2 px-3 py-2 bg-gray-50 rounded-lg">
          <span class="font-medium text-gray-900">{{ provider.provider }}</span>
          <span class="text-xs text-gray-500">{{ provider.total }} total</span>
          <span class="text-xs text-green-600">{{ provider.successful }} ok</span>
          <span v-if="provider.failed > 0" class="text-xs text-red-600">{{ provider.failed }} err</span>
        </div>
      </div>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-xl border border-gray-200 p-4 mb-6">
      <div class="flex flex-wrap gap-4 items-end">
        <!-- Provider -->
        <div>
          <label class="block text-xs text-gray-500 mb-1">Proveedor</label>
          <select
            v-model="filters.provider"
            class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
            @change="applyFilters"
          >
            <option value="all">Todos</option>
            <option v-for="p in providers" :key="p" :value="p">{{ p }}</option>
          </select>
        </div>

        <!-- Success -->
        <div>
          <label class="block text-xs text-gray-500 mb-1">Estado</label>
          <select
            v-model="filters.success"
            class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
            @change="applyFilters"
          >
            <option value="all">Todos</option>
            <option value="true">Exitosos</option>
            <option value="false">Fallidos</option>
          </select>
        </div>

        <!-- Service search -->
        <div>
          <label class="block text-xs text-gray-500 mb-1">Servicio</label>
          <input
            v-model="filters.service"
            type="text"
            placeholder="Buscar servicio..."
            class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
          />
        </div>

        <!-- Date range -->
        <div>
          <label class="block text-xs text-gray-500 mb-1">Desde</label>
          <input
            v-model="filters.from_date"
            type="date"
            class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
            @change="applyFilters"
          />
        </div>
        <div>
          <label class="block text-xs text-gray-500 mb-1">Hasta</label>
          <input
            v-model="filters.to_date"
            type="date"
            class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
            @change="applyFilters"
          />
        </div>

        <!-- Reset -->
        <button
          class="px-3 py-2 text-sm text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition-colors"
          @click="resetFilters"
        >
          Limpiar
        </button>
      </div>
    </div>

    <!-- Logs Table -->
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
      <!-- Loading -->
      <div v-if="isLoading" class="p-8 text-center">
        <div class="animate-spin w-8 h-8 border-2 border-primary-600 border-t-transparent rounded-full mx-auto" />
        <p class="text-sm text-gray-500 mt-2">Cargando logs...</p>
      </div>

      <!-- Empty state -->
      <div v-else-if="logs.length === 0" class="p-8 text-center">
        <svg class="w-12 h-12 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
        </svg>
        <p class="text-gray-500">No hay logs de API</p>
      </div>

      <!-- Table -->
      <table v-else class="w-full">
        <thead class="bg-gray-50 border-b border-gray-200">
          <tr>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fecha</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Proveedor</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Servicio</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Estado</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">HTTP</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Duracion</th>
            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Acciones</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
          <tr
            v-for="log in logs"
            :key="log.id"
            class="hover:bg-gray-50 cursor-pointer"
            @click="loadLogDetail(log)"
          >
            <td class="px-4 py-3 text-sm text-gray-900">{{ formatDate(log.created_at) }}</td>
            <td class="px-4 py-3">
              <span class="px-2 py-1 text-xs font-medium bg-gray-100 text-gray-700 rounded">
                {{ log.provider }}
              </span>
            </td>
            <td class="px-4 py-3 text-sm text-gray-600 max-w-xs truncate">{{ log.service }}</td>
            <td class="px-4 py-3">
              <span
                class="px-2 py-1 text-xs font-medium rounded"
                :class="log.success ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'"
              >
                {{ log.success ? 'OK' : 'ERROR' }}
              </span>
            </td>
            <td class="px-4 py-3 text-sm">
              <span
                :class="{
                  'text-green-600': log.response_status && log.response_status >= 200 && log.response_status < 300,
                  'text-yellow-600': log.response_status && log.response_status >= 300 && log.response_status < 400,
                  'text-red-600': log.response_status && log.response_status >= 400
                }"
              >
                {{ log.response_status || '-' }}
              </span>
            </td>
            <td class="px-4 py-3 text-sm text-gray-600">{{ formatDuration(log.duration_ms) }}</td>
            <td class="px-4 py-3 text-right">
              <button
                class="text-primary-600 hover:text-primary-800 text-sm font-medium"
                @click.stop="loadLogDetail(log)"
              >
                Ver
              </button>
            </td>
          </tr>
        </tbody>
      </table>

      <!-- Pagination -->
      <div v-if="logs.length > 0" class="px-4 py-3 border-t border-gray-200 flex items-center justify-between">
        <p class="text-sm text-gray-500">
          Mostrando {{ logs.length }} de {{ total }} registros
        </p>
        <div class="flex gap-2">
          <button
            class="px-3 py-1 text-sm border rounded-lg disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-50"
            :disabled="currentPage === 1"
            @click="goToPage(currentPage - 1)"
          >
            Anterior
          </button>
          <span class="px-3 py-1 text-sm text-gray-600">
            Pagina {{ currentPage }} de {{ lastPage }}
          </span>
          <button
            class="px-3 py-1 text-sm border rounded-lg disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-50"
            :disabled="currentPage === lastPage"
            @click="goToPage(currentPage + 1)"
          >
            Siguiente
          </button>
        </div>
      </div>
    </div>

    <!-- Detail Modal -->
    <Teleport to="body">
      <Transition
        enter-active-class="transition-opacity duration-200"
        leave-active-class="transition-opacity duration-200"
        enter-from-class="opacity-0"
        leave-to-class="opacity-0"
      >
        <div
          v-if="showDetailModal && selectedLog"
          class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4"
          @click="showDetailModal = false"
        >
          <div
            class="bg-white rounded-xl shadow-xl max-w-4xl w-full max-h-[90vh] overflow-hidden flex flex-col"
            @click.stop
          >
            <!-- Header -->
            <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between flex-shrink-0">
              <div>
                <h3 class="font-semibold text-gray-900">Detalle de Llamada API</h3>
                <p class="text-sm text-gray-500">{{ selectedLog.provider }} - {{ selectedLog.service }}</p>
              </div>
              <button
                class="p-2 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100"
                @click="showDetailModal = false"
              >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>

            <!-- Content -->
            <div class="p-6 overflow-y-auto flex-1">
              <!-- Loading -->
              <div v-if="isLoadingDetail" class="flex items-center justify-center py-8">
                <div class="animate-spin w-6 h-6 border-2 border-primary-600 border-t-transparent rounded-full" />
              </div>

              <div v-else class="space-y-6">
                <!-- Basic Info -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                  <div>
                    <p class="text-xs text-gray-500 uppercase">Fecha</p>
                    <p class="text-sm font-medium text-gray-900">{{ formatDate(selectedLog.created_at) }}</p>
                  </div>
                  <div>
                    <p class="text-xs text-gray-500 uppercase">Metodo</p>
                    <p class="text-sm font-medium text-gray-900">{{ selectedLog.method }}</p>
                  </div>
                  <div>
                    <p class="text-xs text-gray-500 uppercase">Estado HTTP</p>
                    <p class="text-sm font-medium" :class="selectedLog.success ? 'text-green-600' : 'text-red-600'">
                      {{ selectedLog.response_status || 'N/A' }}
                    </p>
                  </div>
                  <div>
                    <p class="text-xs text-gray-500 uppercase">Duracion</p>
                    <p class="text-sm font-medium text-gray-900">{{ formatDuration(selectedLog.duration_ms) }}</p>
                  </div>
                </div>

                <!-- Endpoint -->
                <div>
                  <p class="text-xs text-gray-500 uppercase mb-1">Endpoint</p>
                  <p class="text-sm font-mono bg-gray-100 px-3 py-2 rounded-lg break-all">{{ selectedLog.endpoint }}</p>
                </div>

                <!-- Error (if any) -->
                <div v-if="!selectedLog.success && selectedLog.error_message" class="bg-red-50 border border-red-200 rounded-lg p-4">
                  <p class="text-xs text-red-600 uppercase mb-1">Error</p>
                  <p class="text-sm text-red-800">{{ selectedLog.error_code }}: {{ selectedLog.error_message }}</p>
                </div>

                <!-- Request Payload -->
                <div>
                  <p class="text-xs text-gray-500 uppercase mb-1">Request Payload</p>
                  <pre class="text-xs bg-gray-900 text-green-400 p-4 rounded-lg overflow-x-auto max-h-48">{{ formatJson(selectedLog.request_payload) }}</pre>
                </div>

                <!-- Response Body -->
                <div>
                  <p class="text-xs text-gray-500 uppercase mb-1">Response Body</p>
                  <pre class="text-xs bg-gray-900 text-green-400 p-4 rounded-lg overflow-x-auto max-h-64">{{ formatJson(selectedLog.response_body) }}</pre>
                </div>
              </div>
            </div>

            <!-- Footer -->
            <div class="px-6 py-4 border-t border-gray-200 flex-shrink-0">
              <button
                class="w-full px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors"
                @click="showDetailModal = false"
              >
                Cerrar
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>
