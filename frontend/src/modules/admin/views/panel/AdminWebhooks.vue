<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useToast } from '@/composables/useToast'
import { getErrorMessage } from '@/types/api'
import AppButton from '@/components/common/AppButton.vue'
import webhookService, {
  type V2WebhookDelivery,
  type V2WebhookEndpoint,
  type WebhookEventOption,
} from '@/modules/admin/services/webhook.staff.service'

/**
 * Panel de integraciones / webhooks: endpoints suscriptores (alta/edición,
 * secreto, sandbox) y log de entregas (reenviar, enviar prueba). Ver la guía
 * del integrador en docs/integracion/webhooks.md.
 */

const toast = useToast()
const router = useRouter()
const loading = ref(true)
const endpoints = ref<V2WebhookEndpoint[]>([])
const eventOptions = ref<WebhookEventOption[]>([])
const deliveries = ref<V2WebhookDelivery[]>([])

const deliveryFilterEndpoint = ref('')
const deliveryFilterStatus = ref('')

async function load() {
  loading.value = true
  try {
    const [ep, ev] = await Promise.all([webhookService.listEndpoints(), webhookService.getEvents()])
    endpoints.value = ep.data?.endpoints ?? []
    eventOptions.value = ev.data?.events ?? []
    await loadDeliveries()
  } catch (error) {
    toast.error(getErrorMessage(error, 'No se pudieron cargar los webhooks'))
  } finally {
    loading.value = false
  }
}

async function loadDeliveries() {
  try {
    const res = await webhookService.listDeliveries({
      endpoint_id: deliveryFilterEndpoint.value || undefined,
      status: deliveryFilterStatus.value || undefined,
    })
    deliveries.value = res.data?.deliveries ?? []
  } catch (error) {
    toast.error(getErrorMessage(error, 'No se pudo cargar el log de entregas'))
  }
}

onMounted(load)

// ===================== Editor de endpoint =====================

const editorOpen = ref(false)
const saving = ref(false)
const editingId = ref<string | null>(null)
const form = ref<{ name: string; url: string; events: string[]; is_active: boolean; is_sandbox: boolean; description: string }>(
  { name: '', url: '', events: [], is_active: true, is_sandbox: false, description: '' })
// Secreto recién generado (se muestra una sola vez).
const revealedSecret = ref<string | null>(null)

function openCreate() {
  editingId.value = null
  form.value = { name: '', url: '', events: [], is_active: true, is_sandbox: false, description: '' }
  revealedSecret.value = null
  editorOpen.value = true
}

function openEdit(ep: V2WebhookEndpoint) {
  editingId.value = ep.id
  form.value = {
    name: ep.name, url: ep.url, events: [...ep.events],
    is_active: ep.is_active, is_sandbox: ep.is_sandbox, description: ep.description ?? '',
  }
  revealedSecret.value = null
  editorOpen.value = true
}

function toggleEvent(value: string) {
  const i = form.value.events.indexOf(value)
  if (i >= 0) form.value.events.splice(i, 1)
  else form.value.events.push(value)
}

async function save() {
  if (!form.value.url.startsWith('https://')) {
    toast.error('La URL del webhook debe ser HTTPS')
    return
  }
  if (form.value.events.length === 0) {
    toast.error('Suscribe al menos un evento')
    return
  }
  saving.value = true
  try {
    const payload = { ...form.value, description: form.value.description || undefined }
    if (editingId.value) {
      await webhookService.updateEndpoint(editingId.value, payload)
      toast.success('Endpoint actualizado')
      editorOpen.value = false
    } else {
      const res = await webhookService.createEndpoint(payload)
      revealedSecret.value = res.data?.secret ?? null
      toast.success('Endpoint creado')
    }
    await load()
  } catch (error) {
    toast.error(getErrorMessage(error, 'No se pudo guardar el endpoint'))
  } finally {
    saving.value = false
  }
}

async function rotate(ep: V2WebhookEndpoint) {
  if (!window.confirm('Rotar el secreto invalidará el anterior. ¿Continuar?')) return
  try {
    const res = await webhookService.rotateSecret(ep.id)
    revealedSecret.value = res.data?.secret ?? null
    editingId.value = ep.id
    form.value = { name: ep.name, url: ep.url, events: [...ep.events], is_active: ep.is_active, is_sandbox: ep.is_sandbox, description: ep.description ?? '' }
    editorOpen.value = true
  } catch (error) {
    toast.error(getErrorMessage(error, 'No se pudo rotar el secreto'))
  }
}

async function removeEndpoint(ep: V2WebhookEndpoint) {
  if (!window.confirm(`¿Eliminar el endpoint "${ep.name}"?`)) return
  try {
    await webhookService.deleteEndpoint(ep.id)
    toast.success('Endpoint eliminado')
    await load()
  } catch (error) {
    toast.error(getErrorMessage(error, 'No se pudo eliminar'))
  }
}

async function sendTest(ep: V2WebhookEndpoint) {
  try {
    await webhookService.testEndpoint(ep.id)
    toast.success('Evento de prueba enviado. Revisa el log de entregas.')
    setTimeout(loadDeliveries, 1500)
  } catch (error) {
    toast.error(getErrorMessage(error, 'No se pudo enviar la prueba'))
  }
}

async function retry(delivery: V2WebhookDelivery) {
  try {
    await webhookService.retryDelivery(delivery.id)
    toast.success('Entrega reencolada')
    setTimeout(loadDeliveries, 1500)
  } catch (error) {
    toast.error(getErrorMessage(error, 'No se pudo reenviar'))
  }
}

const statusBadge = (s: string) => ({
  SENT: 'bg-green-100 text-green-700',
  PENDING: 'bg-gray-100 text-gray-600',
  RETRYING: 'bg-amber-100 text-amber-700',
  FAILED: 'bg-red-100 text-red-700',
}[s] ?? 'bg-gray-100 text-gray-600')

const eventLabel = (value: string) => eventOptions.value.find((e) => e.value === value)?.label ?? value
const formatDate = (d: string | null) => d ? new Date(d).toLocaleString('es-MX', { dateStyle: 'short', timeStyle: 'short' }) : '—'
const endpointName = (id: string) => endpoints.value.find((e) => e.id === id)?.name ?? '—'
</script>

<template>
  <div class="p-6 max-w-6xl mx-auto space-y-6">
    <div class="flex items-start justify-between gap-4 flex-wrap">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Webhooks / Integraciones</h1>
        <p class="text-sm text-gray-500 mt-1">
          Notifica a sistemas externos (cartera, core bancario) los eventos del ciclo del crédito.
          Consulta la guía del integrador para el catálogo de eventos, los payloads y la verificación de firma.
        </p>
      </div>
      <div class="flex gap-2 flex-wrap">
        <AppButton variant="outline" @click="router.push({ name: 'admin-webhooks-docs' })">Documentación</AppButton>
        <AppButton variant="primary" @click="openCreate">Nuevo endpoint</AppButton>
      </div>
    </div>

    <div v-if="loading" class="flex justify-center py-16">
      <div class="w-10 h-10 border-2 border-primary-600 border-t-transparent rounded-full animate-spin" />
    </div>

    <template v-else>
      <!-- Endpoints -->
      <section class="space-y-3">
        <div v-if="!endpoints.length" class="text-center text-gray-500 py-8 bg-white rounded-xl border border-gray-200">
          No hay endpoints configurados. Crea uno para empezar a recibir webhooks.
        </div>
        <div
          v-for="ep in endpoints"
          :key="ep.id"
          class="bg-white rounded-xl border border-gray-200 shadow-sm p-4"
        >
          <div class="flex items-start justify-between gap-3 flex-wrap">
            <div class="min-w-0">
              <div class="flex items-center gap-2 flex-wrap">
                <h2 class="font-semibold text-gray-900">{{ ep.name }}</h2>
                <span class="text-xs px-2 py-0.5 rounded-full" :class="ep.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-200 text-gray-600'">
                  {{ ep.is_active ? 'Activo' : 'Inactivo' }}
                </span>
                <span v-if="ep.is_sandbox" class="text-xs px-2 py-0.5 rounded-full bg-amber-100 text-amber-700">Sandbox</span>
              </div>
              <p class="text-sm text-gray-500 truncate font-mono">{{ ep.url }}</p>
              <div class="flex flex-wrap gap-1 mt-2">
                <span v-for="e in ep.events" :key="e" class="text-xs px-2 py-0.5 rounded bg-indigo-50 text-indigo-700">{{ eventLabel(e) }}</span>
              </div>
              <p v-if="ep.last_success_at || ep.last_failure_at" class="text-xs text-gray-400 mt-2">
                Último éxito: {{ formatDate(ep.last_success_at) }} · Último fallo: {{ formatDate(ep.last_failure_at) }}
              </p>
            </div>
            <div class="flex gap-2 shrink-0 flex-wrap">
              <AppButton size="sm" variant="secondary" @click="sendTest(ep)">Probar</AppButton>
              <AppButton size="sm" variant="secondary" @click="rotate(ep)">Rotar secreto</AppButton>
              <AppButton size="sm" variant="secondary" @click="openEdit(ep)">Editar</AppButton>
              <button type="button" class="text-red-500 text-sm px-2" @click="removeEndpoint(ep)">Eliminar</button>
            </div>
          </div>
        </div>
      </section>

      <!-- Log de entregas -->
      <section class="bg-white rounded-xl border border-gray-200 shadow-sm p-4">
        <div class="flex items-center justify-between gap-3 flex-wrap mb-3">
          <h2 class="font-semibold text-gray-900">Log de entregas</h2>
          <div class="flex gap-2">
            <select v-model="deliveryFilterEndpoint" class="text-sm border border-gray-300 rounded-lg px-2 py-1" @change="loadDeliveries">
              <option value="">Todos los endpoints</option>
              <option v-for="ep in endpoints" :key="ep.id" :value="ep.id">{{ ep.name }}</option>
            </select>
            <select v-model="deliveryFilterStatus" class="text-sm border border-gray-300 rounded-lg px-2 py-1" @change="loadDeliveries">
              <option value="">Todos los estados</option>
              <option value="SENT">Enviado</option>
              <option value="RETRYING">Reintentando</option>
              <option value="FAILED">Fallido</option>
              <option value="PENDING">Pendiente</option>
            </select>
          </div>
        </div>

        <div v-if="!deliveries.length" class="text-center text-gray-400 py-6 text-sm">Sin entregas aún.</div>
        <div v-else class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="text-left text-xs text-gray-500 border-b border-gray-100">
                <th class="py-2 pr-3">Evento</th>
                <th class="py-2 pr-3">Endpoint</th>
                <th class="py-2 pr-3">Estado</th>
                <th class="py-2 pr-3">Código</th>
                <th class="py-2 pr-3">Intentos</th>
                <th class="py-2 pr-3">Fecha</th>
                <th class="py-2"></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="d in deliveries" :key="d.id" class="border-b border-gray-50">
                <td class="py-2 pr-3 font-mono text-xs">{{ d.event }}</td>
                <td class="py-2 pr-3">{{ endpointName(d.webhook_endpoint_id) }}</td>
                <td class="py-2 pr-3"><span class="text-xs px-2 py-0.5 rounded-full" :class="statusBadge(d.status)">{{ d.status }}</span></td>
                <td class="py-2 pr-3">{{ d.response_code ?? '—' }}</td>
                <td class="py-2 pr-3">{{ d.attempts }}/{{ d.max_attempts }}</td>
                <td class="py-2 pr-3 text-xs text-gray-500">{{ formatDate(d.created_at) }}</td>
                <td class="py-2 text-right">
                  <button
                    v-if="d.status === 'FAILED' || d.status === 'RETRYING'"
                    type="button"
                    class="text-primary-600 text-xs font-medium"
                    @click="retry(d)"
                  >Reenviar</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </template>

    <!-- Modal editor -->
    <div v-if="editorOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg max-h-[90vh] overflow-y-auto p-6 space-y-4">
        <div class="flex items-center justify-between">
          <h3 class="text-lg font-semibold text-gray-900">{{ editingId ? 'Editar endpoint' : 'Nuevo endpoint' }}</h3>
          <button type="button" class="text-gray-400 hover:text-gray-600 text-xl leading-none" @click="editorOpen = false">×</button>
        </div>

        <div v-if="revealedSecret" class="bg-amber-50 border border-amber-200 rounded-lg p-3 text-sm">
          <p class="font-semibold text-amber-800">Guarda este secreto — no se volverá a mostrar:</p>
          <code class="block mt-1 break-all font-mono text-amber-900">{{ revealedSecret }}</code>
        </div>

        <label class="text-sm text-gray-600 block">
          Nombre
          <input v-model="form.name" type="text" maxlength="120" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
        </label>
        <label class="text-sm text-gray-600 block">
          URL (HTTPS)
          <input v-model="form.url" type="url" placeholder="https://cartera.example/webhooks" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
        </label>

        <div>
          <p class="text-sm text-gray-600 mb-1">Eventos suscritos</p>
          <div class="space-y-1">
            <label v-for="e in eventOptions" :key="e.value" class="flex items-center gap-2 text-sm text-gray-700">
              <input type="checkbox" :checked="form.events.includes(e.value)" class="w-4 h-4 rounded text-primary-600" @change="toggleEvent(e.value)" />
              {{ e.label }} <span class="text-xs text-gray-400 font-mono">{{ e.value }}</span>
            </label>
          </div>
        </div>

        <div class="flex gap-4">
          <label class="flex items-center gap-2 text-sm text-gray-700">
            <input v-model="form.is_active" type="checkbox" class="w-4 h-4 rounded text-primary-600" /> Activo
          </label>
          <label class="flex items-center gap-2 text-sm text-gray-700">
            <input v-model="form.is_sandbox" type="checkbox" class="w-4 h-4 rounded text-primary-600" /> Sandbox
          </label>
        </div>

        <label class="text-sm text-gray-600 block">
          Descripción (opcional)
          <input v-model="form.description" type="text" maxlength="500" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
        </label>

        <div class="flex justify-end gap-2 pt-2">
          <AppButton variant="secondary" @click="editorOpen = false">Cerrar</AppButton>
          <AppButton variant="primary" :loading="saving" @click="save">{{ editingId ? 'Guardar' : 'Crear' }}</AppButton>
        </div>
      </div>
    </div>
  </div>
</template>
