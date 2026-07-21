<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useToast } from '@/composables/useToast'
import { getErrorMessage, isAxiosError } from '@/types/api'
import { getEcho } from '@/plugins/echo'
import { logger } from '@/utils/logger'
import AppButton from '@/components/common/AppButton.vue'
import postalCatalog, {
  type PostalCatalogStatus,
  type PostalCodeImport,
  type PostalCodeImportPhase,
  type PostalCodeImportProgressEvent,
  type PostalCodeImportStatus,
} from '@/modules/admin/services/postalCatalog.staff.service'

/**
 * Configuración → Catálogos → Códigos postales (solo SUPER_ADMIN).
 *
 * Carga/actualiza el catálogo GLOBAL de códigos postales (SEPOMEX) sin CLI, en
 * dos fases: subir (parseo a staging vía job, progreso en vivo por Reverb) →
 * revisar el resumen → aplicar el swap atómico. El catálogo es nacional y
 * compartido: aplicar reemplaza el catálogo para TODOS los tenants.
 */

const log = logger.child('PostalCodes')
const toast = useToast()

// Canal Reverb del progreso; el evento usa broadcastAs `progress`, por eso se
// escucha con el prefijo `.` de Laravel Echo.
const CHANNEL_PREFIX = 'postal-codes-import.'
const PROGRESS_EVENT = '.progress'
const MAX_FILE_MB = 30
const ACCEPTED_EXT = /\.(zip|txt|csv)$/i

// ===================== Estado general =====================

const status = ref<PostalCatalogStatus | null>(null)
const history = ref<PostalCodeImport[]>([])
const loadingStatus = ref(true)
const loadingHistory = ref(true)

// ===================== Importación activa =====================

const activeImport = ref<PostalCodeImport | null>(null)
const uploading = ref(false)
const applying = ref(false)
const discarding = ref(false)
const dragOver = ref(false)
const fileInput = ref<HTMLInputElement | null>(null)

// Import (por id) sobre el que corre la acción en curso (aplicar/descartar).
// Permite deshabilitar solo la fila afectada del historial mientras trabaja.
const actionBusyId = ref<string | null>(null)

// Progreso en vivo (Reverb). Respaldo por polling de `getImport`.
const livePhase = ref<PostalCodeImportPhase | null>(null)
const liveProcessed = ref<number | null>(null)
const echoConnected = ref(false)

let pollTimer: ReturnType<typeof setInterval> | null = null
let subscribedChannel: string | null = null

// ===================== Carga inicial =====================

async function loadStatus() {
  loadingStatus.value = true
  try {
    const res = await postalCatalog.getStatus()
    status.value = res.data ?? null
  } catch (error) {
    toast.error(getErrorMessage(error, 'No se pudo cargar la vigencia del catálogo'))
  } finally {
    loadingStatus.value = false
  }
}

async function loadHistory() {
  loadingHistory.value = true
  try {
    const res = await postalCatalog.listImports({ per_page: 20 })
    history.value = res.data?.imports ?? []
  } catch (error) {
    toast.error(getErrorMessage(error, 'No se pudo cargar el historial'))
  } finally {
    loadingHistory.value = false
  }
}

onMounted(async () => {
  await Promise.all([loadStatus(), loadHistory()])
  // Continuidad tras recargar la página: retomar el import que quedó en vuelo.
  resumeInFlight()
})

onUnmounted(() => {
  stopPolling()
  unsubscribeProgress()
})

/**
 * Tras cargar el historial, si quedó un import en vuelo (PENDING_PARSE/PARSED)
 * lo restaura como `activeImport` para mostrar su resumen y sus acciones. Si
 * sigue en PENDING_PARSE, reengancha el canal Reverb y reanuda el polling de
 * respaldo (p. ej. tras recargar a media carga). No pisa un import ya activo.
 */
function resumeInFlight() {
  if (activeImport.value) return
  const inFlight = history.value.find(
    (imp) => imp.status === 'PENDING_PARSE' || imp.status === 'PARSED',
  )
  if (!inFlight) return
  activeImport.value = inFlight
  if (inFlight.status === 'PENDING_PARSE') {
    livePhase.value = 'parsing'
    subscribeProgress(inFlight.id)
    startPolling(inFlight.id) // respaldo siempre activo
  }
}

/** Lee el body V2 (`{ error, message }`) de un error de axios, o null. */
function axiosBody(error: unknown): { error?: string; message?: string } | null {
  return isAxiosError(error) ? (error.response?.data ?? null) : null
}

// ===================== Suscripción Reverb (defensiva) =====================
// Interfaz mínima del canal privado de Laravel Echo para evitar `any` explícito.
interface ProgressChannel {
  listen(event: string, callback: (payload: PostalCodeImportProgressEvent) => void): ProgressChannel
}

function subscribeProgress(id: string): boolean {
  try {
    const echo = getEcho()
    if (!echo) {
      log.warn('Echo no inicializado; se usará solo polling para el progreso.')
      return false
    }
    const name = `${CHANNEL_PREFIX}${id}`
    const channel = echo.private(name) as unknown as ProgressChannel
    channel.listen(PROGRESS_EVENT, (payload: PostalCodeImportProgressEvent) => {
      onProgress(payload)
    })
    subscribedChannel = name
    echoConnected.value = true
    return true
  } catch (error) {
    // Reverb puede no estar disponible en algunos entornos → degradar a polling.
    log.warn('No se pudo suscribir al canal de progreso; se usará polling.', error)
    echoConnected.value = false
    return false
  }
}

function unsubscribeProgress() {
  try {
    const echo = getEcho()
    if (echo && subscribedChannel) {
      echo.leave(subscribedChannel)
    }
  } catch {
    /* ignore: cleanup best-effort */
  }
  subscribedChannel = null
  echoConnected.value = false
}

function onProgress(event: PostalCodeImportProgressEvent) {
  if (!activeImport.value || event.importId !== activeImport.value.id) return
  livePhase.value = event.phase
  if (typeof event.processed === 'number') liveProcessed.value = event.processed
  // Fases terminales de cada paso → traer el registro completo (resumen/motivo).
  if (event.phase === 'ready' || event.phase === 'rejected' || event.phase === 'applied') {
    void refreshImport(event.importId)
  }
}

// ===================== Polling de respaldo =====================
// Camino principal ante ausencia de Reverb: sondea `getImport` cada 2s mientras
// el import esté en un estado transitorio (PENDING_PARSE / APPLYING).

function startPolling(id: string) {
  stopPolling()
  pollTimer = setInterval(() => {
    void refreshImport(id)
  }, 2000)
}

function stopPolling() {
  if (pollTimer) {
    clearInterval(pollTimer)
    pollTimer = null
  }
}

async function refreshImport(id: string) {
  try {
    const res = await postalCatalog.getImport(id)
    if (res.data) applyImportState(res.data)
  } catch {
    /* silencioso: el polling reintenta al siguiente tick */
  }
}

function applyImportState(imp: PostalCodeImport) {
  if (activeImport.value && activeImport.value.id !== imp.id) return
  activeImport.value = imp
  // Estados terminales del parseo → dejar de sondear.
  if (imp.status === 'PARSED' || imp.status === 'REJECTED' || imp.status === 'APPLIED') {
    stopPolling()
  }
}

// ===================== Subida =====================

function pickFile() {
  fileInput.value?.click()
}

function onInputChange(event: Event) {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  if (file) void handleFile(file)
  input.value = '' // permitir re-subir el mismo archivo
}

function onDrop(event: DragEvent) {
  dragOver.value = false
  const file = event.dataTransfer?.files?.[0]
  if (file) void handleFile(file)
}

async function handleFile(file: File) {
  if (!ACCEPTED_EXT.test(file.name)) {
    toast.error('Formato no soportado. Sube un archivo .zip, .txt o .csv')
    return
  }
  if (file.size > MAX_FILE_MB * 1024 * 1024) {
    toast.error(`El archivo excede el límite de ${MAX_FILE_MB} MB`)
    return
  }

  resetActiveImport()
  uploading.value = true
  livePhase.value = 'parsing'
  liveProcessed.value = 0
  try {
    const res = await postalCatalog.upload(file)
    if (!res.data) {
      toast.error(res.message || 'No se pudo subir el archivo')
      return
    }
    activeImport.value = res.data
    subscribeProgress(res.data.id)
    startPolling(res.data.id) // respaldo siempre activo
    toast.success('Archivo recibido; procesando el catálogo...')
  } catch (error) {
    livePhase.value = null
    // 409 IMPORT_IN_PROGRESS: la staging es única y compartida; ya hay una carga
    // en vuelo. No es un error del archivo: mostramos el mensaje del backend y
    // refrescamos el historial para que el admin retome/resuelva el import
    // pendiente (aplicarlo o descartarlo) antes de subir otro.
    if (isAxiosError(error) && error.response?.status === 409 && axiosBody(error)?.error === 'IMPORT_IN_PROGRESS') {
      toast.warning(axiosBody(error)?.message || 'Ya hay una importación en curso. Resuélvela antes de subir otra.')
      await loadHistory()
      resumeInFlight()
      return
    }
    toast.error(getErrorMessage(error, 'No se pudo subir el archivo'))
  } finally {
    uploading.value = false
  }
}

function resetActiveImport() {
  stopPolling()
  unsubscribeProgress()
  activeImport.value = null
  livePhase.value = null
  liveProcessed.value = null
}

async function discardActive() {
  const imp = activeImport.value
  if (!imp) return
  // Solo hay staging que liberar cuando el import sigue en vuelo. Para estados
  // terminales (rechazado/aplicado/descartado) basta cerrar el panel.
  if (imp.status === 'PENDING_PARSE' || imp.status === 'PARSED') {
    await discardImport(imp)
  } else {
    resetActiveImport()
  }
}

// ===================== Aplicar / Descartar =====================

const APPLY_CONFIRM_MESSAGE =
  'Vas a reemplazar el catálogo NACIONAL de códigos postales para TODOS los tenants. ' +
  'Esta acción no se puede deshacer desde aquí. ¿Continuar?'

const DISCARD_CONFIRM_MESSAGE =
  'Vas a descartar esta importación. El catálogo vigente no cambia y la staging ' +
  'queda libre para una nueva carga. ¿Continuar?'

/**
 * Aplica el swap atómico. Sin argumento usa el import activo (botón principal);
 * con `imp` aplica una fila del historial (mismo flujo con confirmación).
 */
async function applyImport(imp?: PostalCodeImport | null) {
  const target = imp ?? activeImport.value
  if (!target) return
  if (!window.confirm(APPLY_CONFIRM_MESSAGE)) return

  applying.value = true
  actionBusyId.value = target.id
  livePhase.value = 'applying'
  try {
    const res = await postalCatalog.apply(target.id)
    if (res.data) {
      activeImport.value = res.data
      livePhase.value = 'applied'
      toast.success(res.message || 'Catálogo aplicado correctamente')
      await Promise.all([loadStatus(), loadHistory()])
    }
  } catch (error) {
    // 409 STAGING_MISMATCH: la staging cambió desde el parseo; el import ya no es
    // aplicable y hay que volver a subir. Mostramos el mensaje del backend (no
    // uno genérico) y refrescamos vigencia + historial.
    if (isAxiosError(error) && error.response?.status === 409 && axiosBody(error)?.error === 'STAGING_MISMATCH') {
      toast.warning(axiosBody(error)?.message || 'La staging cambió desde el parseo. Vuelve a subir el archivo.')
      resetActiveImport()
      await Promise.all([loadStatus(), loadHistory()])
      return
    }
    // El swap corre en transacción: ante error el catálogo vigente queda intacto.
    toast.error(getErrorMessage(error, 'No se pudo aplicar el catálogo'))
    await refreshImport(target.id)
    livePhase.value = null
  } finally {
    applying.value = false
    actionBusyId.value = null
  }
}

/**
 * Descarta un import en `PENDING_PARSE`/`PARSED` vía backend (libera la staging),
 * luego limpia el panel si era el activo y refresca vigencia + historial.
 */
async function discardImport(imp: PostalCodeImport) {
  if (!window.confirm(DISCARD_CONFIRM_MESSAGE)) return

  discarding.value = true
  actionBusyId.value = imp.id
  try {
    const res = await postalCatalog.discard(imp.id)
    toast.success(res.message || 'Importación descartada')
    if (activeImport.value?.id === imp.id) resetActiveImport()
    await Promise.all([loadStatus(), loadHistory()])
  } catch (error) {
    // 422 INVALID_STATE: el import ya no admite descarte (otro proceso lo avanzó).
    if (isAxiosError(error) && error.response?.status === 422 && axiosBody(error)?.error === 'INVALID_STATE') {
      toast.warning(axiosBody(error)?.message || 'La importación ya no se puede descartar.')
      await loadHistory()
      return
    }
    toast.error(getErrorMessage(error, 'No se pudo descartar la importación'))
  } finally {
    discarding.value = false
    actionBusyId.value = null
  }
}

// ===================== Derivados de UI =====================

const currentStatus = computed<PostalCodeImportStatus | null>(() => activeImport.value?.status ?? null)
const isParsing = computed(() => currentStatus.value === 'PENDING_PARSE')
const isReady = computed(() => currentStatus.value === 'PARSED')
const isRejected = computed(() => currentStatus.value === 'REJECTED')
const isApplied = computed(() => currentStatus.value === 'APPLIED')
const isApplying = computed(() => currentStatus.value === 'APPLYING' || applying.value)
// Barra animada (indeterminada) mientras hay trabajo en curso.
const isBusy = computed(() => isParsing.value || isApplying.value || (livePhase.value === 'validating'))

const phaseLabel = computed(() => {
  switch (livePhase.value) {
    case 'parsing':
      return 'Procesando archivo'
    case 'validating':
      return 'Validando conteos'
    case 'ready':
      return 'Listo para aplicar'
    case 'rejected':
      return 'Rechazado'
    case 'applying':
      return 'Aplicando catálogo'
    case 'applied':
      return 'Aplicado'
    default:
      return activeImport.value?.status_label ?? ''
  }
})

// Solo el COLOR del badge sale de aquí; el TEXTO viene de `status_label` (backend),
// única fuente de verdad de la etiqueta traducida.
const statusBadge = (s: PostalCodeImportStatus): string =>
  ({
    PENDING_PARSE: 'bg-amber-100 text-amber-700',
    PARSED: 'bg-indigo-100 text-indigo-700',
    APPLYING: 'bg-amber-100 text-amber-700',
    APPLIED: 'bg-green-100 text-green-700',
    REJECTED: 'bg-red-100 text-red-700',
    DISCARDED: 'bg-gray-100 text-gray-600',
  })[s] ?? 'bg-gray-100 text-gray-600'

const formatNumber = (n: number | null | undefined) =>
  typeof n === 'number' ? n.toLocaleString('es-MX') : '—'

const formatDate = (d: string | null | undefined) =>
  d ? new Date(d).toLocaleString('es-MX', { dateStyle: 'short', timeStyle: 'short' }) : '—'
</script>

<template>
  <div class="p-6 max-w-6xl mx-auto space-y-6">
    <!-- Encabezado -->
    <div class="flex items-start justify-between gap-4 flex-wrap">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Códigos postales</h1>
        <p class="text-sm text-gray-500 mt-1">
          Carga y actualiza el catálogo nacional de códigos postales (SEPOMEX) desde el panel.
          Acepta el ZIP oficial de "Descarga nacional" o el TXT/CSV ya extraído.
        </p>
      </div>
    </div>

    <!-- Aviso de impacto global -->
    <div class="rounded-xl border border-amber-300 bg-amber-50 p-4 flex gap-3">
      <svg class="w-5 h-5 text-amber-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
      </svg>
      <div class="text-sm text-amber-800">
        <p class="font-semibold">Este catálogo es nacional y compartido.</p>
        <p class="mt-0.5">
          Al aplicar una actualización se reemplaza el catálogo de códigos postales para
          <strong>TODOS los tenants</strong>. El reemplazo solo ocurre tras tu confirmación explícita;
          hasta entonces, el catálogo vigente permanece intacto.
        </p>
      </div>
    </div>

    <!-- Vigencia actual -->
    <section class="bg-white rounded-xl border border-gray-200 shadow-sm p-4">
      <h2 class="font-semibold text-gray-900 mb-3">Vigencia actual</h2>
      <div v-if="loadingStatus" class="text-sm text-gray-400">Cargando...</div>
      <div v-else class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
          <p class="text-xs text-gray-500">Registros en el catálogo vivo</p>
          <p class="text-2xl font-bold text-gray-900">{{ formatNumber(status?.current_count) }}</p>
        </div>
        <div>
          <p class="text-xs text-gray-500">Última actualización aplicada</p>
          <p class="text-sm text-gray-800 mt-1">
            {{ status?.last_import ? formatDate(status.last_import.at) : 'Sin registro' }}
            <span v-if="status?.last_import" class="text-gray-400">
              · {{ formatNumber(status.last_import.count) }} filas
            </span>
          </p>
        </div>
      </div>
    </section>

    <!-- Zona de subida -->
    <section class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 space-y-4">
      <h2 class="font-semibold text-gray-900">Subir catálogo</h2>

      <div
        class="rounded-xl border-2 border-dashed p-8 text-center transition-colors cursor-pointer"
        :class="[
          dragOver ? 'border-primary-500 bg-primary-50' : 'border-gray-300 hover:border-primary-400',
          uploading ? 'opacity-60 pointer-events-none' : '',
        ]"
        role="button"
        tabindex="0"
        @click="pickFile"
        @keydown.enter.prevent="pickFile"
        @dragover.prevent="dragOver = true"
        @dragleave.prevent="dragOver = false"
        @drop.prevent="onDrop"
      >
        <input
          ref="fileInput"
          type="file"
          accept=".zip,.txt,.csv"
          class="hidden"
          @change="onInputChange"
        />
        <svg class="w-10 h-10 mx-auto text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
        </svg>
        <p class="mt-3 text-sm text-gray-600">
          <span v-if="uploading">Subiendo archivo...</span>
          <span v-else>
            Arrastra el archivo aquí o <span class="text-primary-600 font-medium">selecciónalo</span>
          </span>
        </p>
        <p class="text-xs text-gray-400 mt-1">.zip, .txt o .csv · máx {{ MAX_FILE_MB }} MB</p>
      </div>

      <!-- Progreso / resumen de la importación activa -->
      <div v-if="activeImport" class="rounded-lg border border-gray-200 p-4 space-y-3">
        <div class="flex items-center justify-between gap-3 flex-wrap">
          <div class="min-w-0">
            <p class="text-sm font-medium text-gray-900 truncate">{{ activeImport.original_filename }}</p>
            <p class="text-xs text-gray-500">{{ phaseLabel }}</p>
          </div>
          <span class="text-xs px-2 py-0.5 rounded-full" :class="statusBadge(activeImport.status)">
            {{ activeImport.status_label }}
          </span>
        </div>

        <!-- Barra de progreso -->
        <div v-if="isBusy" class="space-y-1">
          <div class="h-2 w-full bg-gray-100 rounded-full overflow-hidden">
            <div class="h-full w-1/3 bg-primary-500 rounded-full animate-pulse" style="animation-duration: 1s" />
          </div>
          <p v-if="isParsing && liveProcessed" class="text-xs text-gray-500">
            {{ formatNumber(liveProcessed) }} filas procesadas
          </p>
          <p v-if="!echoConnected" class="text-xs text-gray-400">
            Progreso por sondeo (WebSocket no disponible).
          </p>
        </div>

        <!-- Rechazado -->
        <div v-if="isRejected" class="rounded-lg bg-red-50 border border-red-200 p-3">
          <p class="text-sm font-semibold text-red-700">Importación rechazada</p>
          <p class="text-sm text-red-600 mt-0.5">
            {{ activeImport.rejection_reason || 'El archivo no pasó la validación de integridad.' }}
          </p>
          <div class="mt-2">
            <button type="button" class="text-sm text-gray-500 hover:text-gray-700" @click="discardActive">
              Descartar
            </button>
          </div>
        </div>

        <!-- Resumen (listo para aplicar) -->
        <div v-if="isReady" class="space-y-3">
          <div class="grid grid-cols-3 gap-3">
            <div class="rounded-lg bg-gray-50 p-3 text-center">
              <p class="text-lg font-bold text-gray-900">{{ formatNumber(activeImport.rows_count) }}</p>
              <p class="text-xs text-gray-500">Filas</p>
            </div>
            <div class="rounded-lg bg-gray-50 p-3 text-center">
              <p class="text-lg font-bold text-gray-900">{{ formatNumber(activeImport.states_count) }}</p>
              <p class="text-xs text-gray-500">Estados</p>
            </div>
            <div class="rounded-lg bg-gray-50 p-3 text-center">
              <p class="text-lg font-bold text-gray-900">{{ formatNumber(activeImport.municipalities_count) }}</p>
              <p class="text-xs text-gray-500">Municipios</p>
            </div>
          </div>

          <!-- Muestra -->
          <div v-if="activeImport.sample && activeImport.sample.length" class="overflow-x-auto">
            <p class="text-xs text-gray-500 mb-1">Muestra ({{ activeImport.sample.length }} filas):</p>
            <table class="w-full text-xs">
              <thead>
                <tr class="text-left text-gray-500 border-b border-gray-100">
                  <th class="py-1 pr-3">CP</th>
                  <th class="py-1 pr-3">Asentamiento</th>
                  <th class="py-1 pr-3">Municipio</th>
                  <th class="py-1 pr-3">Estado</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(row, i) in activeImport.sample" :key="i" class="border-b border-gray-50">
                  <td class="py-1 pr-3 font-mono">{{ row.cp || '—' }}</td>
                  <td class="py-1 pr-3">{{ row.asentamiento || '—' }}</td>
                  <td class="py-1 pr-3">{{ row.municipio || '—' }}</td>
                  <td class="py-1 pr-3">{{ row.estado || '—' }}</td>
                </tr>
              </tbody>
            </table>
          </div>

          <div class="flex justify-end gap-2 pt-1">
            <AppButton variant="secondary" :disabled="applying" :loading="discarding" @click="discardActive">
              Descartar
            </AppButton>
            <AppButton variant="primary" :loading="applying" :disabled="discarding" @click="applyImport()">
              Aplicar catálogo
            </AppButton>
          </div>
        </div>

        <!-- Aplicado -->
        <div v-if="isApplied" class="rounded-lg bg-green-50 border border-green-200 p-3">
          <p class="text-sm font-semibold text-green-700">Catálogo aplicado</p>
          <p class="text-sm text-green-600 mt-0.5">
            {{ formatNumber(activeImport.rows_count) }} filas ahora vigentes para todos los tenants.
          </p>
          <div class="mt-2">
            <button type="button" class="text-sm text-gray-500 hover:text-gray-700" @click="discardActive">
              Cerrar
            </button>
          </div>
        </div>
      </div>
    </section>

    <!-- Historial -->
    <section class="bg-white rounded-xl border border-gray-200 shadow-sm p-4">
      <div class="flex items-center justify-between gap-3 mb-3">
        <h2 class="font-semibold text-gray-900">Historial de importaciones</h2>
        <button type="button" class="text-sm text-primary-600 hover:text-primary-700" @click="loadHistory">
          Actualizar
        </button>
      </div>

      <div v-if="loadingHistory" class="text-center text-gray-400 py-6 text-sm">Cargando...</div>
      <div v-else-if="!history.length" class="text-center text-gray-400 py-6 text-sm">
        Aún no hay importaciones registradas.
      </div>
      <div v-else class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="text-left text-xs text-gray-500 border-b border-gray-100">
              <th class="py-2 pr-3">Archivo</th>
              <th class="py-2 pr-3">Estado</th>
              <th class="py-2 pr-3">Filas</th>
              <th class="py-2 pr-3">Tenant de origen</th>
              <th class="py-2 pr-3">Fecha</th>
              <th class="py-2 pr-3 text-right">Acciones</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="imp in history" :key="imp.id" class="border-b border-gray-50">
              <td class="py-2 pr-3">
                <span class="font-medium text-gray-800">{{ imp.original_filename }}</span>
                <p v-if="imp.status === 'REJECTED' && imp.rejection_reason" class="text-xs text-red-500 mt-0.5">
                  {{ imp.rejection_reason }}
                </p>
              </td>
              <td class="py-2 pr-3">
                <span class="text-xs px-2 py-0.5 rounded-full" :class="statusBadge(imp.status)">
                  {{ imp.status_label }}
                </span>
              </td>
              <td class="py-2 pr-3">{{ formatNumber(imp.rows_count) }}</td>
              <td class="py-2 pr-3 text-gray-600">{{ imp.origin_tenant_slug || '—' }}</td>
              <td class="py-2 pr-3 text-xs text-gray-500">{{ formatDate(imp.created_at) }}</td>
              <td class="py-2 pr-3">
                <!-- PARSED: aplicar o descartar; PENDING_PARSE: descartar (parseo colgado). -->
                <div v-if="imp.status === 'PARSED'" class="flex justify-end gap-2">
                  <AppButton
                    variant="primary"
                    size="sm"
                    :loading="applying && actionBusyId === imp.id"
                    :disabled="actionBusyId !== null"
                    @click="applyImport(imp)"
                  >
                    Aplicar
                  </AppButton>
                  <AppButton
                    variant="secondary"
                    size="sm"
                    :loading="discarding && actionBusyId === imp.id"
                    :disabled="actionBusyId !== null"
                    @click="discardImport(imp)"
                  >
                    Descartar
                  </AppButton>
                </div>
                <div v-else-if="imp.status === 'PENDING_PARSE'" class="flex justify-end">
                  <AppButton
                    variant="secondary"
                    size="sm"
                    :loading="discarding && actionBusyId === imp.id"
                    :disabled="actionBusyId !== null"
                    @click="discardImport(imp)"
                  >
                    Descartar
                  </AppButton>
                </div>
                <span v-else class="block text-right text-gray-300">—</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </div>
</template>
