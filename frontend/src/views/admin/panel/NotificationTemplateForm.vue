<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  notificationTemplatesApi,
  type NotificationTemplate,
  type TemplateConfig,
  type CreateTemplateData,
} from '@/services/notificationTemplates'
import NotificationPreview from '@/components/admin/notification-templates/NotificationPreview.vue'
import HtmlEditor from '@/components/admin/notification-templates/HtmlEditor.vue'
import SendTestModal from '@/components/admin/notification-templates/SendTestModal.vue'

const route = useRoute()
const router = useRouter()

const templateId = computed(() => route.params.id as string | undefined)
const isEdit = computed(() => !!templateId.value)

const config = ref<TemplateConfig | null>(null)
const template = ref<NotificationTemplate | null>(null)
const loading = ref(false)
const saving = ref(false)
const error = ref<string | null>(null)

// Send test modal
const showSendTestModal = ref(false)

// Form data
const form = ref<CreateTemplateData>({
  name: '',
  event: '',
  channel: '',
  is_active: true,
  priority: 5,
  subject: null,
  body: '',
  html_body: null,
  metadata: null,
})

// Main view mode (Editor or Preview)
const mainViewMode = ref<'editor' | 'preview'>('editor')

// Preview data for real-time preview
const previewData = ref<Record<string, any>>({
  tenant: {
    name: 'Lendus Demo',
    slug: 'demo',
  },
  applicant: {
    name: 'Juan Pérez García',
    first_name: 'Juan',
    email: 'juan.perez@example.com',
  },
  application: {
    folio: 'APP-2024-001',
    amount: '$50,000.00',
  },
  otp: {
    code: '123456',
  },
})

// Load config
const loadConfig = async () => {
  try {
    config.value = await notificationTemplatesApi.getConfig()
  } catch (err) {
    console.error('Error loading config:', err)
  }
}

// Load template if editing
const loadTemplate = async () => {
  if (!templateId.value) return

  loading.value = true
  error.value = null
  try {
    template.value = await notificationTemplatesApi.getById(templateId.value)
    form.value = {
      name: template.value.name,
      event: template.value.event,
      channel: template.value.channel,
      is_active: template.value.is_active,
      priority: template.value.priority,
      subject: template.value.subject,
      body: template.value.body,
      html_body: template.value.html_body,
      metadata: template.value.metadata,
    }
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Error al cargar plantilla'
    console.error('Error loading template:', err)
  } finally {
    loading.value = false
  }
}

// Get selected event
const selectedEvent = computed(() => {
  return config.value?.events.find((e) => e.value === form.value.event)
})

// Get selected channel
const selectedChannel = computed(() => {
  return config.value?.channels.find((c) => c.value === form.value.channel)
})

// Check if subject is required
const requiresSubject = computed(() => {
  return selectedChannel.value?.requires_subject || false
})

// Check if HTML is supported
const supportsHtml = computed(() => {
  return selectedChannel.value?.supports_html || false
})

// Get character limit
const characterLimit = computed(() => {
  return selectedChannel.value?.character_limit || null
})

// Body character count
const bodyCharCount = computed(() => form.value.body.length)

// Watch event change to update preview variables
watch(
  () => form.value.event,
  () => {
    if (selectedEvent.value) {
      // Initialize preview data with sample values
      previewData.value = Object.keys(selectedEvent.value.available_variables).reduce(
        (acc, key) => {
          acc[key] = `{${key}}`
          return acc
        },
        {} as Record<string, any>
      )
    }
  }
)

// Helper to format variable syntax for display
const formatVariable = (variable: string) => {
  return `{{${variable}}}`
}

// Watch channel change
watch(
  () => form.value.channel,
  () => {
    // Clear HTML body if channel doesn't support HTML
    if (!supportsHtml.value) {
      form.value.html_body = null
    }
    // Clear subject if channel doesn't require it
    if (!requiresSubject.value) {
      form.value.subject = null
    }
  }
)

// Watch body changes to sync with html_body for channels that support HTML
watch(
  () => form.value.body,
  (newBody) => {
    if (supportsHtml.value && newBody) {
      // Auto-sync body to html_body for HTML-supported channels
      form.value.html_body = newBody
    }
  }
)

// Save template
const save = async () => {
  // Validation
  if (!form.value.name) {
    alert('El nombre es requerido')
    return
  }
  if (!form.value.event) {
    alert('Selecciona un evento')
    return
  }
  if (!form.value.channel) {
    alert('Selecciona un canal')
    return
  }
  if (!form.value.body) {
    alert('El contenido es requerido')
    return
  }
  if (requiresSubject.value && !form.value.subject) {
    alert('El asunto es requerido para el canal Email')
    return
  }
  if (characterLimit.value && bodyCharCount.value > characterLimit.value) {
    alert(`El contenido excede el límite de ${characterLimit.value} caracteres`)
    return
  }

  saving.value = true
  error.value = null
  try {
    if (isEdit.value && templateId.value) {
      await notificationTemplatesApi.update(templateId.value, form.value)
    } else {
      await notificationTemplatesApi.create(form.value)
    }
    router.push('/admin/notificaciones')
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Error al guardar plantilla'
    console.error('Error saving template:', err)
  } finally {
    saving.value = false
  }
}

onMounted(() => {
  loadConfig()
  if (isEdit.value) {
    loadTemplate()
  }
})
</script>

<template>
  <div class="p-6 max-w-5xl mx-auto">
    <!-- Header -->
    <div class="mb-6">
      <button
        class="text-sm text-gray-600 hover:text-gray-900 mb-4 flex items-center gap-1"
        @click="router.push('/admin/notificaciones')"
      >
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path
            stroke-linecap="round"
            stroke-linejoin="round"
            stroke-width="2"
            d="M15 19l-7-7 7-7"
          />
        </svg>
        Volver
      </button>
      <h1 class="text-2xl font-bold text-gray-900">
        {{ isEdit ? 'Editar Plantilla' : 'Nueva Plantilla' }}
      </h1>
      <p class="text-sm text-gray-600 mt-1">
        {{
          isEdit
            ? 'Modifica los datos de la plantilla de notificación'
            : 'Crea una nueva plantilla de notificación'
        }}
      </p>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="text-center py-12">
      <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600"></div>
      <p class="mt-2 text-sm text-gray-600">Cargando plantilla...</p>
    </div>

    <!-- Form -->
    <form v-else class="space-y-6" @submit.prevent="save">
      <!-- Error -->
      <div
        v-if="error"
        class="bg-red-50 border border-red-200 rounded-lg p-4 text-red-800 text-sm"
      >
        {{ error }}
      </div>

      <!-- Basic Info -->
      <div class="bg-white border border-gray-200 rounded-lg p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Información Básica</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <!-- Name -->
          <div class="md:col-span-2">
            <label class="block text-sm font-medium text-gray-700 mb-1"
              >Nombre <span class="text-red-500">*</span></label
            >
            <input
              v-model="form.name"
              type="text"
              required
              placeholder="ej. Solicitud Aprobada - Email"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
            />
          </div>

          <!-- Event -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1"
              >Evento <span class="text-red-500">*</span></label
            >
            <select
              v-model="form.event"
              required
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
            >
              <option value="">Selecciona un evento</option>
              <option v-for="event in config?.events" :key="event.value" :value="event.value">
                {{ event.label }}
              </option>
            </select>
            <p v-if="selectedEvent" class="mt-1 text-xs text-gray-500">
              Canales recomendados:
              {{ selectedEvent.recommended_channels.join(', ') }}
            </p>
          </div>

          <!-- Channel -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1"
              >Canal <span class="text-red-500">*</span></label
            >
            <select
              v-model="form.channel"
              required
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
            >
              <option value="">Selecciona un canal</option>
              <option
                v-for="channel in config?.channels"
                :key="channel.value"
                :value="channel.value"
              >
                {{ channel.label }}
              </option>
            </select>
            <p v-if="selectedChannel" class="mt-1 text-xs text-gray-500">
              <span v-if="characterLimit">Límite: {{ characterLimit }} caracteres. </span>
              <span v-if="supportsHtml">Soporta HTML. </span>
              <span v-if="requiresSubject">Requiere asunto.</span>
            </p>
          </div>

          <!-- Priority -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Prioridad</label>
            <input
              v-model.number="form.priority"
              type="number"
              min="1"
              max="10"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
            />
            <p class="mt-1 text-xs text-gray-500">1 = Alta, 10 = Baja</p>
          </div>

          <!-- Active -->
          <div class="flex items-center">
            <label class="flex items-center cursor-pointer">
              <input v-model="form.is_active" type="checkbox" class="w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500" />
              <span class="ml-2 text-sm font-medium text-gray-700">Plantilla activa</span>
            </label>
          </div>
        </div>
      </div>

      <!-- Subject (only for EMAIL) -->
      <div v-if="requiresSubject" class="bg-white border border-gray-200 rounded-lg p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">
          Asunto <span class="text-red-500">*</span>
        </h2>
        <input
          v-model="form.subject"
          type="text"
          required
          placeholder="ej. ¡Felicidades! Tu solicitud ha sido aprobada"
          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
        />
        <p class="mt-1 text-xs text-gray-500">Puedes usar variables como {{ formatVariable('user.first_name') }}</p>
      </div>

      <!-- Editor Area - Single Column -->
      <div class="bg-white rounded-xl shadow-lg overflow-hidden border border-gray-200">
        <div class="bg-gradient-to-r from-slate-800 to-slate-900 px-4 py-2 flex items-center justify-between">
          <h3 class="text-sm font-semibold text-white">Editor de Contenido</h3>
          <div class="flex items-center gap-4">
            <div v-if="form.channel" class="flex items-center gap-2 px-2 py-1 bg-white/20 rounded text-xs text-white">
              <div class="w-2 h-2 rounded-full bg-green-400 animate-pulse"></div>
              {{ form.channel }}
            </div>
            <div class="flex items-center gap-2 text-sm">
              <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
              </svg>
              <span :class="characterLimit && bodyCharCount > characterLimit ? 'text-red-400 font-bold' : 'text-gray-300'">
                {{ bodyCharCount }}
                <span v-if="characterLimit" class="text-gray-500">/ {{ characterLimit }}</span>
              </span>
            </div>
          </div>
        </div>

        <HtmlEditor
          v-model="form.body"
          v-model:html-body="form.html_body"
          :available-variables="selectedEvent?.available_variables"
          :channel="form.channel"
          :subject="form.subject"
          :preview-variables="previewData"
        />
      </div>

      <!-- Actions -->
      <div class="flex items-center justify-end gap-3">
        <button
          v-if="isEdit"
          type="button"
          class="px-4 py-2 border border-indigo-300 text-indigo-700 rounded-lg hover:bg-indigo-50 transition-colors flex items-center gap-2"
          @click="showSendTestModal = true"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
          </svg>
          Enviar Prueba
        </button>
        <button
          type="button"
          class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors"
          @click="router.push('/admin/notificaciones')"
        >
          Cancelar
        </button>
        <button
          type="submit"
          class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
          :disabled="saving"
        >
          {{ saving ? 'Guardando...' : isEdit ? 'Actualizar' : 'Crear Plantilla' }}
        </button>
      </div>
    </form>

    <!-- Send Test Modal -->
    <SendTestModal
      v-if="isEdit && template"
      v-model:show="showSendTestModal"
      :template="template"
    />
  </div>
</template>
