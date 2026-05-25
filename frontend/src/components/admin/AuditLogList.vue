<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { v2 } from '@/services/v2'
import type { V2AuditLog, V2AuditLogPage, V2AuditLogFilters } from '@/services/v2/audit.staff.service'
import { logger } from '@/utils/logger'
import { formatDateTime } from '@/utils/formatters'

const log = logger.child('AuditLogList')

interface Props {
  /** ID de un applicant o aplicación para filtrar. */
  applicantId?: string
  applicationId?: string
  /** Excluir HTTP_REQUEST por defecto (true para vista limpia de acciones). */
  defaultExcludeHttp?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  defaultExcludeHttp: false,
})

const isLoading = ref(false)
const error = ref<string | null>(null)
const data = ref<V2AuditLogPage | null>(null)

const filters = ref<V2AuditLogFilters>({
  page: 1,
  per_page: 20,
  exclude_http: props.defaultExcludeHttp,
})

const items = computed<V2AuditLog[]>(() => data.value?.items ?? [])
const total = computed(() => data.value?.pagination.total ?? 0)
const currentPage = computed(() => data.value?.pagination.current_page ?? 1)
const lastPage = computed(() => data.value?.pagination.last_page ?? 1)

async function load() {
  if (!props.applicantId && !props.applicationId) return
  isLoading.value = true
  error.value = null
  try {
    const res = props.applicationId
      ? await v2.staff.auditLog.listByApplication(props.applicationId, filters.value)
      : await v2.staff.auditLog.listByApplicant(props.applicantId!, filters.value)
    if (res.success && res.data) {
      data.value = res.data
    } else {
      error.value = res.message || 'No se pudo cargar el registro de actividad'
    }
  } catch (e) {
    log.error('Failed to load audit logs', { error: e })
    error.value = 'Error al cargar el registro de actividad'
  } finally {
    isLoading.value = false
  }
}

function toggleHttp() {
  filters.value.exclude_http = !filters.value.exclude_http
  filters.value.page = 1
  load()
}

function nextPage() {
  if (currentPage.value < lastPage.value) {
    filters.value.page = currentPage.value + 1
    load()
  }
}

function prevPage() {
  if (currentPage.value > 1) {
    filters.value.page = currentPage.value - 1
    load()
  }
}

onMounted(load)
watch(() => [props.applicantId, props.applicationId], () => {
  filters.value.page = 1
  load()
})

// Helpers de display
function actionLabel(action: string): string {
  const map: Record<string, string> = {
    HTTP_REQUEST: 'Petición HTTP',
    OTP_REQUESTED: 'OTP solicitado',
    OTP_VERIFIED: 'OTP verificado',
    LOGIN_SUCCESS: 'Inicio de sesión',
    LOGIN_FAILED: 'Login fallido',
    LOGOUT: 'Cierre de sesión',
    PIN_SET: 'PIN creado',
    PIN_CHANGED: 'PIN cambiado',
    APPLICATION_CREATED: 'Solicitud creada',
    APPLICATION_UPDATED: 'Solicitud actualizada',
    APPLICATION_SUBMITTED: 'Solicitud enviada',
    APPLICATION_APPROVED: 'Solicitud aprobada',
    APPLICATION_REJECTED: 'Solicitud rechazada',
    DOCUMENT_UPLOADED: 'Documento subido',
    DOCUMENT_APPROVED: 'Documento aprobado',
    DOCUMENT_REJECTED: 'Documento rechazado',
    STEP_COMPLETED: 'Paso completado',
  }
  return map[action] || action
}

function actionBadgeClass(action: string): string {
  if (action === 'HTTP_REQUEST') return 'bg-gray-100 text-gray-600'
  if (action.includes('APPROVED') || action.includes('SUCCESS') || action.includes('VERIFIED'))
    return 'bg-green-100 text-green-700'
  if (action.includes('REJECTED') || action.includes('FAILED')) return 'bg-red-100 text-red-700'
  if (action.includes('CREATED') || action.includes('SUBMITTED')) return 'bg-blue-100 text-blue-700'
  return 'bg-indigo-100 text-indigo-700'
}

function hasGeo(log: V2AuditLog): boolean {
  return log.latitude !== null && log.longitude !== null
}

function geoText(log: V2AuditLog): string {
  const parts: string[] = []
  if (hasGeo(log)) {
    const lat = Number(log.latitude).toFixed(5)
    const lng = Number(log.longitude).toFixed(5)
    parts.push(`${lat}, ${lng}`)
  }
  if (log.city) parts.push(log.city)
  if (log.region) parts.push(log.region)
  if (log.country) parts.push(log.country)
  return parts.join(' · ')
}

function googleMapsUrl(log: V2AuditLog): string | null {
  if (!hasGeo(log)) return null
  return `https://www.google.com/maps?q=${log.latitude},${log.longitude}`
}

function platformLabel(meta: Record<string, unknown> | null): string {
  if (!meta) return ''
  const p = meta.platform as string | undefined
  const v = meta.app_version as string | undefined
  if (!p) return ''
  return v ? `${p} ${v}` : p
}

function methodColor(method?: string): string {
  switch (method?.toUpperCase()) {
    case 'POST': return 'text-blue-600'
    case 'PUT':
    case 'PATCH': return 'text-amber-600'
    case 'DELETE': return 'text-red-600'
    default: return 'text-gray-500'
  }
}

function statusColor(code?: number): string {
  if (!code) return 'text-gray-500'
  if (code >= 200 && code < 300) return 'text-green-600'
  if (code >= 400 && code < 500) return 'text-amber-600'
  if (code >= 500) return 'text-red-600'
  return 'text-gray-500'
}
</script>

<template>
  <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
    <!-- Header con filtros -->
    <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200 bg-gray-50">
      <div>
        <h3 class="text-sm font-semibold text-gray-900">Registro de actividad</h3>
        <p class="text-xs text-gray-500 mt-0.5">
          {{ total }} {{ total === 1 ? 'evento' : 'eventos' }}
        </p>
      </div>
      <label class="flex items-center gap-2 text-xs text-gray-600 cursor-pointer">
        <input
          type="checkbox"
          :checked="filters.exclude_http"
          class="rounded border-gray-300 text-primary-600 focus:ring-primary-500"
          @change="toggleHttp"
        />
        Ocultar peticiones HTTP
      </label>
    </div>

    <!-- Loading -->
    <div v-if="isLoading && !data" class="p-6 flex justify-center">
      <div class="animate-spin w-6 h-6 border-2 border-primary-600 border-t-transparent rounded-full" />
    </div>

    <!-- Error -->
    <div v-else-if="error" class="p-4 text-sm text-red-600">{{ error }}</div>

    <!-- Empty -->
    <div v-else-if="items.length === 0" class="p-6 text-center text-sm text-gray-500">
      No hay actividad registrada todavía.
    </div>

    <!-- Lista -->
    <ul v-else class="divide-y divide-gray-100">
      <li v-for="log in items" :key="log.id" class="px-4 py-3">
        <div class="flex items-start gap-3">
          <span
            class="text-xs font-medium px-2 py-0.5 rounded-full flex-shrink-0"
            :class="actionBadgeClass(log.action)"
          >
            {{ actionLabel(log.action) }}
          </span>
          <div class="flex-1 min-w-0">
            <div class="text-xs text-gray-500 flex flex-wrap gap-x-3 gap-y-0.5">
              <span>{{ formatDateTime(log.created_at) }}</span>
              <span v-if="log.metadata?.method" :class="methodColor(log.metadata.method as string)">
                {{ log.metadata.method }} {{ log.metadata.path }}
              </span>
              <span
                v-if="log.metadata?.status_code"
                :class="statusColor(log.metadata.status_code as number)"
              >
                {{ log.metadata.status_code }}
              </span>
              <span v-if="log.metadata?.duration_ms" class="text-gray-400">
                {{ log.metadata.duration_ms }}ms
              </span>
            </div>

            <div v-if="log.entity_type && log.entity_type !== 'http_request'" class="text-sm text-gray-700 mt-1">
              {{ log.entity_type }}<span v-if="log.entity_id"> · {{ log.entity_id }}</span>
            </div>

            <div class="mt-1 flex flex-wrap gap-x-3 gap-y-1 text-xs text-gray-500">
              <span v-if="platformLabel(log.metadata)" class="inline-flex items-center gap-1">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                {{ platformLabel(log.metadata) }}
              </span>
              <span v-if="log.ip_address" class="inline-flex items-center gap-1">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                {{ log.ip_address }}
              </span>
              <a
                v-if="hasGeo(log)"
                :href="googleMapsUrl(log)!"
                target="_blank"
                rel="noopener"
                class="inline-flex items-center gap-1 text-primary-600 hover:text-primary-700"
              >
                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                {{ geoText(log) }}
              </a>
            </div>
          </div>
        </div>
      </li>
    </ul>

    <!-- Paginación -->
    <div
      v-if="data && lastPage > 1"
      class="flex items-center justify-between px-4 py-2 border-t border-gray-200 bg-gray-50 text-sm"
    >
      <button
        type="button"
        class="px-3 py-1 rounded text-gray-700 hover:bg-gray-100 disabled:opacity-40 disabled:cursor-not-allowed"
        :disabled="currentPage <= 1 || isLoading"
        @click="prevPage"
      >
        ‹ Anterior
      </button>
      <span class="text-xs text-gray-500">Página {{ currentPage }} de {{ lastPage }}</span>
      <button
        type="button"
        class="px-3 py-1 rounded text-gray-700 hover:bg-gray-100 disabled:opacity-40 disabled:cursor-not-allowed"
        :disabled="currentPage >= lastPage || isLoading"
        @click="nextPage"
      >
        Siguiente ›
      </button>
    </div>
  </div>
</template>
