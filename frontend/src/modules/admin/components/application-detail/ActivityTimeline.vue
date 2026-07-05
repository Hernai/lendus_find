<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { staff } from '@/modules/admin/services'
import type {
  ActivityItem,
  ActivityKind,
  ActivitySeverity,
  ActivityActorType,
} from '@/modules/admin/services/activity.staff.service'
import { logger } from '@/utils/logger'

/**
 * Timeline unificada de actividad para una solicitud.
 *
 * Reemplaza a TimelineSection + AuditLogList + ApiLogsSection. Lee del
 * endpoint `/v2/staff/applications/{id}/activity` y pinta los items
 * cronologicamente, con:
 *   - Filtros chip por tipo (Todo, Cambios, Auditoria, Integraciones)
 *   - Busqueda con debounce
 *   - Agrupacion por dia
 *   - Items expandibles para ver metadata completa
 *   - Infinite scroll con cursor
 */

const props = defineProps<{
  applicationId: string
}>()

const log = logger.child('ActivityTimeline')

interface FilterChip {
  value: ActivityKind | null
  label: string
}

const FILTER_CHIPS: FilterChip[] = [
  { value: null, label: 'Todo' },
  { value: 'event', label: 'Cambios' },
  { value: 'audit', label: 'Auditoría' },
  { value: 'api', label: 'Integraciones' },
]

const items = ref<ActivityItem[]>([])
const isLoading = ref(false)
const isLoadingMore = ref(false)
const hasMore = ref(false)
const cursor = ref<string | null>(null)
const error = ref<string | null>(null)

const activeKind = ref<ActivityKind | null>(null)
const searchTerm = ref('')
const includeHttp = ref(false)
const expandedIds = ref<Set<string>>(new Set())

let searchDebounceTimer: ReturnType<typeof setTimeout> | null = null

const groupedByDay = computed(() => {
  const groups = new Map<string, ActivityItem[]>()
  for (const item of items.value) {
    const day = item.timestamp.slice(0, 10)
    if (!groups.has(day)) groups.set(day, [])
    groups.get(day)!.push(item)
  }
  return Array.from(groups.entries()).map(([day, list]) => ({
    day,
    label: formatDayLabel(day),
    items: list,
  }))
})

function formatDayLabel(day: string): string {
  const today = new Date()
  today.setHours(0, 0, 0, 0)
  const target = new Date(day + 'T00:00:00')
  const diffDays = Math.round((today.getTime() - target.getTime()) / 86400000)
  if (diffDays === 0) return 'Hoy'
  if (diffDays === 1) return 'Ayer'
  return target.toLocaleDateString('es-MX', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: target.getFullYear() === today.getFullYear() ? undefined : 'numeric',
  })
}

function formatTime(timestamp: string): string {
  return new Date(timestamp).toLocaleTimeString('es-MX', {
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
  })
}

// Maps con fallback explicito en getters defensivos: si el backend agrega
// un nuevo `kind`, `severity` o `actorType` en el futuro, el render no
// truena con `undefined.dot` ni muestra labels vacios.
const SEVERITY_CLASSES: Record<string, { dot: string; chip: string }> = {
  info:    { dot: 'bg-blue-500',   chip: 'bg-blue-50 text-blue-700 ring-blue-200' },
  success: { dot: 'bg-emerald-500', chip: 'bg-emerald-50 text-emerald-700 ring-emerald-200' },
  warning: { dot: 'bg-amber-500',  chip: 'bg-amber-50 text-amber-700 ring-amber-200' },
  error:   { dot: 'bg-rose-500',   chip: 'bg-rose-50 text-rose-700 ring-rose-200' },
}
const DEFAULT_SEVERITY = SEVERITY_CLASSES.info!

function severityClass(s: ActivitySeverity | string): { dot: string; chip: string } {
  return SEVERITY_CLASSES[s] ?? DEFAULT_SEVERITY
}

const ACTOR_TYPE_LABELS: Record<string, string> = {
  staff: 'Staff',
  applicant: 'Solicitante',
  system: 'Sistema',
}
function actorTypeLabel(t: ActivityActorType | string): string {
  return ACTOR_TYPE_LABELS[t] ?? t
}

const KIND_LABELS: Record<string, string> = {
  event: 'Cambio',
  audit: 'Auditoría',
  api: 'Integración',
}
function kindLabel(k: ActivityKind | string): string {
  return KIND_LABELS[k] ?? 'Otro'
}

/**
 * Lee geo del metadata del item: prioriza ciudad/país (resuelto por MaxMind
 * en backend) y cae a coords GPS truncadas (enviadas via X-Geo-* headers).
 */
function locationLabel(meta: Record<string, unknown>): string | null {
  const city = meta.city as string | null
  const region = meta.region as string | null
  const country = meta.country as string | null
  const parts = [city, region, country].filter(Boolean) as string[]
  if (parts.length) return parts.join(', ')

  const lat = meta.latitude as number | string | null
  const lng = meta.longitude as number | string | null
  if (lat != null && lng != null) {
    return `${Number(lat).toFixed(4)}, ${Number(lng).toFixed(4)}`
  }
  return null
}

function deviceLabel(meta: Record<string, unknown>): string | null {
  const type = meta.device_type as string | null
  const browser = meta.browser as string | null
  const parts = [type, browser].filter(Boolean) as string[]
  return parts.length ? parts.join(' · ') : null
}

function mapsUrl(meta: Record<string, unknown>): string | null {
  const lat = meta.latitude as number | string | null
  const lng = meta.longitude as number | string | null
  if (lat == null || lng == null) return null
  return `https://www.google.com/maps?q=${lat},${lng}`
}

async function loadFirstPage() {
  isLoading.value = true
  error.value = null
  cursor.value = null
  items.value = []
  expandedIds.value = new Set()

  try {
    const response = await staff.activity.getActivity(props.applicationId, {
      kind: activeKind.value,
      q: searchTerm.value || null,
      include_http: includeHttp.value,
      per_page: 50,
    })
    if (response.success && response.data) {
      items.value = response.data.items
      cursor.value = response.data.next_cursor
      hasMore.value = response.data.has_more
    }
  } catch (e) {
    log.error('Failed to load activity', { error: e })
    error.value = 'No se pudo cargar la actividad.'
  } finally {
    isLoading.value = false
  }
}

async function loadMore() {
  if (!hasMore.value || isLoadingMore.value || !cursor.value) return
  isLoadingMore.value = true
  try {
    const response = await staff.activity.getActivity(props.applicationId, {
      cursor: cursor.value,
      kind: activeKind.value,
      q: searchTerm.value || null,
      include_http: includeHttp.value,
      per_page: 50,
    })
    if (response.success && response.data) {
      items.value.push(...response.data.items)
      cursor.value = response.data.next_cursor
      hasMore.value = response.data.has_more
    }
  } catch (e) {
    log.error('Failed to load more activity', { error: e })
  } finally {
    isLoadingMore.value = false
  }
}

function selectFilter(kind: ActivityKind | null) {
  if (activeKind.value === kind) return
  activeKind.value = kind
  loadFirstPage()
}

function toggleExpanded(id: string) {
  if (expandedIds.value.has(id)) {
    expandedIds.value.delete(id)
  } else {
    expandedIds.value.add(id)
  }
  // Trigger reactivity (Set mutations are not reactive in Vue 3)
  expandedIds.value = new Set(expandedIds.value)
}

watch(searchTerm, () => {
  if (searchDebounceTimer) clearTimeout(searchDebounceTimer)
  searchDebounceTimer = setTimeout(() => {
    loadFirstPage()
  }, 350)
})

watch(includeHttp, () => loadFirstPage())

watch(() => props.applicationId, () => loadFirstPage())

onMounted(() => loadFirstPage())
onUnmounted(() => {
  if (searchDebounceTimer) clearTimeout(searchDebounceTimer)
})

// SVG icons keyed por el icon string del backend
const ICONS: Record<string, string> = {
  document: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
  shield: 'M12 3l8 3v6c0 5-3.5 8.5-8 9-4.5-.5-8-4-8-9V6l8-3z',
  fingerprint: 'M12 11c0 3.517-1.009 6.799-2.753 9.571m-3.44-2.04l.054-.09A13.916 13.916 0 008 11a4 4 0 118 0c0 1.017-.07 2.019-.203 3m-2.118 6.844A21.88 21.88 0 0015.171 17m3.839 1.132c.645-2.266.99-4.659.99-7.132A8 8 0 008 4.07M3 15.364c.64-1.319 1-2.8 1-4.364 0-1.457.39-2.823 1.07-4',
  file: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
  flag: 'M5 13.5V21h2v-7h12V3H7v2H5v8.5z',
  lightning: 'M13 10V3L4 14h7v7l9-11h-7z',
  circle: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
}

const FALLBACK_ICON = 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'

function iconPath(name: string): string {
  return ICONS[name] ?? FALLBACK_ICON
}
</script>

<template>
  <div class="activity-timeline">
    <!-- Toolbar: filter chips + search -->
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between mb-4">
      <div class="flex flex-wrap items-center gap-2">
        <button
          v-for="chip in FILTER_CHIPS"
          :key="chip.label"
          type="button"
          class="px-3 py-1.5 text-sm font-medium rounded-full ring-1 transition-colors"
          :class="activeKind === chip.value
            ? 'bg-gray-900 text-white ring-gray-900'
            : 'bg-white text-gray-600 ring-gray-200 hover:bg-gray-50'"
          @click="selectFilter(chip.value)"
        >
          {{ chip.label }}
        </button>
        <label class="ml-2 flex items-center gap-2 text-sm text-gray-500 cursor-pointer">
          <input v-model="includeHttp" type="checkbox" class="rounded border-gray-300" />
          Incluir HTTP
        </label>
      </div>
      <div class="relative">
        <svg class="absolute left-3 top-2.5 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
        </svg>
        <input
          v-model="searchTerm"
          type="search"
          placeholder="Buscar en la actividad..."
          class="w-full sm:w-72 pl-9 pr-3 py-2 text-sm bg-white border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-gray-900/10 focus:border-gray-300"
        />
      </div>
    </div>

    <!-- Loading state -->
    <div v-if="isLoading && items.length === 0" class="py-12 flex justify-center">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-gray-900" />
    </div>

    <!-- Error state -->
    <div v-else-if="error" class="py-8 text-center text-sm text-rose-600">
      {{ error }}
    </div>

    <!-- Empty state -->
    <div v-else-if="items.length === 0" class="py-12 text-center text-sm text-gray-500">
      <svg class="mx-auto w-10 h-10 text-gray-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
      </svg>
      Sin actividad para los filtros seleccionados.
    </div>

    <!-- Timeline -->
    <div v-else class="space-y-8">
      <section v-for="group in groupedByDay" :key="group.day">
        <div class="sticky top-0 z-10 bg-gradient-to-b from-gray-50 to-gray-50/95 backdrop-blur pb-2 mb-3">
          <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-500">
            {{ group.label }} · <span class="text-gray-400 font-normal normal-case">{{ group.day }}</span>
          </h3>
        </div>
        <ol class="relative border-l border-gray-200 pl-6 space-y-4 ml-2">
          <li v-for="item in group.items" :key="item.id" class="relative">
            <span
              class="absolute -left-[33px] top-1.5 w-3.5 h-3.5 rounded-full ring-4 ring-gray-50"
              :class="severityClass(item.severity).dot"
            />
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden hover:shadow-sm transition-shadow">
              <button
                type="button"
                class="w-full flex items-start gap-3 px-4 py-3 text-left"
                :aria-expanded="expandedIds.has(item.id)"
                :aria-controls="`activity-detail-${item.id}`"
                @click="toggleExpanded(item.id)"
              >
                <div class="flex-shrink-0 mt-0.5 w-8 h-8 rounded-lg bg-gray-50 flex items-center justify-center text-gray-600">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" :d="iconPath(item.icon)" />
                  </svg>
                </div>
                <div class="flex-1 min-w-0">
                  <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                      <p class="text-sm font-medium text-gray-900 truncate">{{ item.title }}</p>
                      <p v-if="item.summary" class="text-sm text-gray-500 mt-0.5">{{ item.summary }}</p>
                    </div>
                    <span class="flex-shrink-0 text-xs text-gray-400 tabular-nums">{{ formatTime(item.timestamp) }}</span>
                  </div>
                  <div class="mt-2 flex flex-wrap items-center gap-2 text-xs">
                    <span
                      class="px-1.5 py-0.5 rounded-md ring-1 ring-inset font-medium"
                      :class="severityClass(item.severity).chip"
                    >
                      {{ kindLabel(item.kind) }}
                    </span>
                    <span class="text-gray-500">
                      {{ item.actor.name }} ·
                      <span class="text-gray-400">{{ actorTypeLabel(item.actor.type) }}</span>
                    </span>
                  </div>
                  <!-- Segunda fila: chips de trazabilidad (ubicacion, IP,
                       dispositivo). Solo se renderizan cuando hay datos
                       en el metadata; en local con 127.0.0.1 queda invisible. -->
                  <div
                    v-if="locationLabel(item.metadata) || item.metadata.ip_address || deviceLabel(item.metadata)"
                    class="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-gray-400"
                    @click.stop
                  >
                    <a
                      v-if="locationLabel(item.metadata) && mapsUrl(item.metadata)"
                      :href="mapsUrl(item.metadata) || '#'"
                      target="_blank"
                      rel="noopener noreferrer"
                      class="inline-flex items-center gap-1 hover:text-gray-600 hover:underline"
                    >
                      <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                      </svg>
                      {{ locationLabel(item.metadata) }}
                    </a>
                    <span
                      v-else-if="locationLabel(item.metadata)"
                      class="inline-flex items-center gap-1"
                    >
                      <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                      </svg>
                      {{ locationLabel(item.metadata) }}
                    </span>
                    <span v-if="item.metadata.ip_address" class="font-mono">
                      {{ item.metadata.ip_address }}
                    </span>
                    <span v-if="deviceLabel(item.metadata)" class="inline-flex items-center gap-1">
                      <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                      </svg>
                      {{ deviceLabel(item.metadata) }}
                    </span>
                  </div>
                </div>
                <svg
                  class="flex-shrink-0 w-4 h-4 text-gray-400 mt-1 transition-transform"
                  :class="expandedIds.has(item.id) ? 'rotate-180' : ''"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
              </button>
              <div
                v-if="expandedIds.has(item.id)"
                :id="`activity-detail-${item.id}`"
                role="region"
                :aria-label="`Detalle de ${item.title}`"
                class="px-4 pb-4 pt-1 border-t border-gray-100 bg-gray-50/50"
              >
                <pre class="text-xs text-gray-600 overflow-x-auto whitespace-pre-wrap break-words font-mono leading-relaxed">{{ JSON.stringify(item.metadata, null, 2) }}</pre>
              </div>
            </div>
          </li>
        </ol>
      </section>

      <div v-if="hasMore" class="flex justify-center pt-2">
        <button
          type="button"
          class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 disabled:opacity-50"
          :disabled="isLoadingMore"
          @click="loadMore"
        >
          {{ isLoadingMore ? 'Cargando...' : 'Cargar más' }}
        </button>
      </div>
    </div>
  </div>
</template>
