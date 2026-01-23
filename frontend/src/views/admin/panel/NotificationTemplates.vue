<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import {
  notificationTemplatesApi,
  type NotificationTemplate,
  type TemplateConfig,
} from '@/services/notificationTemplates'

const router = useRouter()

const templates = ref<NotificationTemplate[]>([])
const config = ref<TemplateConfig | null>(null)
const loading = ref(false)
const error = ref<string | null>(null)

// Filters
const filterEvent = ref<string>('')
const filterChannel = ref<string>('')
const filterStatus = ref<string>('')
const searchQuery = ref('')

// Load data
const loadTemplates = async () => {
  loading.value = true
  error.value = null
  try {
    const params: any = {}
    if (filterEvent.value) params.event = filterEvent.value
    if (filterChannel.value) params.channel = filterChannel.value
    if (filterStatus.value) params.is_active = filterStatus.value === 'active'

    templates.value = await notificationTemplatesApi.getAll(params)
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Error al cargar plantillas'
    console.error('Error loading templates:', err)
  } finally {
    loading.value = false
  }
}

const loadConfig = async () => {
  try {
    config.value = await notificationTemplatesApi.getConfig()
  } catch (err) {
    console.error('Error loading config:', err)
  }
}

// Filtered templates
const filteredTemplates = computed(() => {
  let result = templates.value

  if (searchQuery.value) {
    const query = searchQuery.value.toLowerCase()
    result = result.filter(
      (t) =>
        t.name.toLowerCase().includes(query) ||
        t.event_label.toLowerCase().includes(query) ||
        t.channel_label.toLowerCase().includes(query)
    )
  }

  return result
})

// Group by event
const templatesByEvent = computed(() => {
  const groups: Record<string, NotificationTemplate[]> = {}
  filteredTemplates.value.forEach((template) => {
    if (!groups[template.event]) {
      groups[template.event] = []
    }
    groups[template.event].push(template)
  })
  return groups
})

// Stats
const stats = computed(() => ({
  total: templates.value.length,
  active: templates.value.filter((t) => t.is_active).length,
  inactive: templates.value.filter((t) => !t.is_active).length,
  byChannel: templates.value.reduce((acc, t) => {
    acc[t.channel] = (acc[t.channel] || 0) + 1
    return acc
  }, {} as Record<string, number>),
}))

// Delete template
const deleteTemplate = async (template: NotificationTemplate) => {
  if (
    !confirm(
      `¿Estás seguro de eliminar la plantilla "${template.name}"? Esta acción no se puede deshacer.`
    )
  ) {
    return
  }

  try {
    await notificationTemplatesApi.delete(template.id)
    await loadTemplates()
  } catch (err: any) {
    alert(err.response?.data?.message || 'Error al eliminar plantilla')
  }
}

// Toggle active status
const toggleActive = async (template: NotificationTemplate) => {
  try {
    await notificationTemplatesApi.update(template.id, {
      is_active: !template.is_active,
    })
    await loadTemplates()
  } catch (err: any) {
    alert(err.response?.data?.message || 'Error al actualizar plantilla')
  }
}

// Get channel badge color
const getChannelColor = (channel: string) => {
  const colors: Record<string, string> = {
    SMS: 'bg-blue-100 text-blue-800',
    WHATSAPP: 'bg-green-100 text-green-800',
    EMAIL: 'bg-purple-100 text-purple-800',
    IN_APP: 'bg-gray-100 text-gray-800',
  }
  return colors[channel] || 'bg-gray-100 text-gray-800'
}

// Get priority color
const getPriorityColor = (priority: number) => {
  if (priority <= 3) return 'text-red-600'
  if (priority <= 7) return 'text-yellow-600'
  return 'text-gray-600'
}

onMounted(() => {
  loadTemplates()
  loadConfig()
})
</script>

<template>
  <div class="p-6">
    <!-- Header -->
    <div class="mb-6">
      <div class="flex items-center justify-between mb-4">
        <div>
          <h1 class="text-2xl font-bold text-gray-900">Plantillas de Notificación</h1>
          <p class="text-sm text-gray-600 mt-1">
            Gestiona las plantillas de notificaciones multi-canal
          </p>
        </div>
        <button
          class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors flex items-center gap-2"
          @click="router.push('/admin/notificaciones/nueva')"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M12 4v16m8-8H4"
            />
          </svg>
          Nueva Plantilla
        </button>
      </div>

      <!-- Stats -->
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white p-4 rounded-lg border border-gray-200">
          <div class="text-sm text-gray-600">Total</div>
          <div class="text-2xl font-bold text-gray-900">{{ stats.total }}</div>
        </div>
        <div class="bg-white p-4 rounded-lg border border-gray-200">
          <div class="text-sm text-gray-600">Activas</div>
          <div class="text-2xl font-bold text-green-600">{{ stats.active }}</div>
        </div>
        <div class="bg-white p-4 rounded-lg border border-gray-200">
          <div class="text-sm text-gray-600">Inactivas</div>
          <div class="text-2xl font-bold text-gray-600">{{ stats.inactive }}</div>
        </div>
        <div class="bg-white p-4 rounded-lg border border-gray-200">
          <div class="text-sm text-gray-600">Canales</div>
          <div class="flex gap-1 mt-1">
            <span
              v-for="(count, channel) in stats.byChannel"
              :key="channel"
              class="text-xs px-2 py-0.5 rounded"
              :class="getChannelColor(channel as string)"
            >
              {{ channel }}: {{ count }}
            </span>
          </div>
        </div>
      </div>

      <!-- Filters -->
      <div class="bg-white p-4 rounded-lg border border-gray-200">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
          <!-- Search -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Buscar</label>
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Nombre, evento, canal..."
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
            />
          </div>

          <!-- Event filter -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Evento</label>
            <select
              v-model="filterEvent"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
              @change="loadTemplates"
            >
              <option value="">Todos</option>
              <option v-for="event in config?.events" :key="event.value" :value="event.value">
                {{ event.label }}
              </option>
            </select>
          </div>

          <!-- Channel filter -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Canal</label>
            <select
              v-model="filterChannel"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
              @change="loadTemplates"
            >
              <option value="">Todos</option>
              <option
                v-for="channel in config?.channels"
                :key="channel.value"
                :value="channel.value"
              >
                {{ channel.label }}
              </option>
            </select>
          </div>

          <!-- Status filter -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Estado</label>
            <select
              v-model="filterStatus"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
              @change="loadTemplates"
            >
              <option value="">Todos</option>
              <option value="active">Activas</option>
              <option value="inactive">Inactivas</option>
            </select>
          </div>
        </div>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="text-center py-12">
      <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600"></div>
      <p class="mt-2 text-sm text-gray-600">Cargando plantillas...</p>
    </div>

    <!-- Error -->
    <div
      v-else-if="error"
      class="bg-red-50 border border-red-200 rounded-lg p-4 text-red-800 text-sm"
    >
      {{ error }}
    </div>

    <!-- Templates grouped by event -->
    <div v-else-if="Object.keys(templatesByEvent).length > 0" class="space-y-6">
      <div v-for="(eventTemplates, event) in templatesByEvent" :key="event">
        <h2 class="text-lg font-semibold text-gray-900 mb-3">
          {{ eventTemplates[0].event_label }}
        </h2>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
          <div
            v-for="template in eventTemplates"
            :key="template.id"
            class="bg-white border border-gray-200 rounded-lg p-4 hover:border-gray-300 transition-colors"
          >
            <!-- Header -->
            <div class="flex items-start justify-between mb-3">
              <div class="flex-1">
                <div class="flex items-center gap-2 mb-1">
                  <h3 class="font-medium text-gray-900">{{ template.name }}</h3>
                  <span
                    class="px-2 py-0.5 text-xs font-medium rounded"
                    :class="template.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600'"
                  >
                    {{ template.is_active ? 'Activa' : 'Inactiva' }}
                  </span>
                </div>
                <div class="flex items-center gap-2 text-sm text-gray-600">
                  <span class="px-2 py-0.5 rounded text-xs" :class="getChannelColor(template.channel)">
                    {{ template.channel_label }}
                  </span>
                  <span class="text-xs" :class="getPriorityColor(template.priority)">
                    Prioridad: {{ template.priority }}
                  </span>
                </div>
              </div>

              <!-- Actions -->
              <div class="flex items-center gap-1">
                <button
                  class="p-2 text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 rounded transition-colors"
                  title="Editar"
                  @click="router.push(`/admin/notificaciones/${template.id}/editar`)"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"
                    />
                  </svg>
                </button>
                <button
                  class="p-2 text-gray-400 hover:text-green-600 hover:bg-green-50 rounded transition-colors"
                  :title="template.is_active ? 'Desactivar' : 'Activar'"
                  @click="toggleActive(template)"
                >
                  <svg
                    v-if="template.is_active"
                    class="w-4 h-4"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                  >
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"
                    />
                  </svg>
                  <svg
                    v-else
                    class="w-4 h-4"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                  >
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
                    />
                  </svg>
                </button>
                <button
                  class="p-2 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded transition-colors"
                  title="Eliminar"
                  @click="deleteTemplate(template)"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                    />
                  </svg>
                </button>
              </div>
            </div>

            <!-- Body preview -->
            <div class="bg-gray-50 rounded p-3 text-sm text-gray-700 font-mono overflow-hidden">
              <div class="line-clamp-3">{{ template.body }}</div>
            </div>

            <!-- Footer -->
            <div class="mt-3 flex items-center justify-between text-xs text-gray-500">
              <span v-if="template.created_by">Por {{ template.created_by.name }}</span>
              <span>{{ new Date(template.updated_at).toLocaleDateString('es-MX') }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Empty state -->
    <div v-else class="text-center py-12">
      <svg
        class="mx-auto h-12 w-12 text-gray-400"
        fill="none"
        stroke="currentColor"
        viewBox="0 0 24 24"
      >
        <path
          stroke-linecap="round"
          stroke-linejoin="round"
          stroke-width="2"
          d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"
        />
      </svg>
      <h3 class="mt-2 text-sm font-medium text-gray-900">No hay plantillas</h3>
      <p class="mt-1 text-sm text-gray-500">
        Comienza creando tu primera plantilla de notificación.
      </p>
      <div class="mt-6">
        <button
          class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
          @click="router.push('/admin/notificaciones/nueva')"
        >
          <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M12 4v16m8-8H4"
            />
          </svg>
          Nueva Plantilla
        </button>
      </div>
    </div>
  </div>
</template>
