<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  notificationTemplatesApi,
  type NotificationTemplate,
  type TemplateConfig,
  type CreateTemplateData,
} from '@/services/notificationTemplates'

const route = useRoute()
const router = useRouter()

const templateId = computed(() => route.params.id as string | undefined)
const isEdit = computed(() => !!templateId.value)

const config = ref<TemplateConfig | null>(null)
const template = ref<NotificationTemplate | null>(null)
const loading = ref(false)
const saving = ref(false)
const error = ref<string | null>(null)

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

// Preview
const showPreview = ref(false)
const previewData = ref<Record<string, any>>({})
const previewResult = ref<string>('')
const previewLoading = ref(false)

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

// Test render preview
const testRender = async () => {
  if (!form.value.body) return

  previewLoading.value = true
  try {
    const result = await notificationTemplatesApi.testRender({
      body: form.value.body,
      variables: previewData.value,
    })
    previewResult.value = result.rendered
    showPreview.value = true
  } catch (err: any) {
    alert(err.response?.data?.message || 'Error al renderizar plantilla')
  } finally {
    previewLoading.value = false
  }
}

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
        <p class="mt-1 text-xs text-gray-500">Puedes usar variables como {{user.first_name}}</p>
      </div>

      <!-- Body -->
      <div class="bg-white border border-gray-200 rounded-lg p-6">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-lg font-semibold text-gray-900">
            Contenido <span class="text-red-500">*</span>
          </h2>
          <div class="flex items-center gap-2 text-sm">
            <span
              class="text-gray-600"
              :class="{ 'text-red-600': characterLimit && bodyCharCount > characterLimit }"
            >
              {{ bodyCharCount }}
              <span v-if="characterLimit">/ {{ characterLimit }}</span>
              caracteres
            </span>
            <button
              type="button"
              class="px-3 py-1 bg-indigo-100 text-indigo-700 rounded hover:bg-indigo-200 transition-colors"
              :disabled="!form.body || previewLoading"
              @click="testRender"
            >
              {{ previewLoading ? 'Renderizando...' : 'Vista Previa' }}
            </button>
          </div>
        </div>
        <textarea
          v-model="form.body"
          required
          rows="10"
          placeholder="Escribe el contenido de la notificación aquí...

Usa variables con doble llave: {{user.first_name}}, {{application.folio}}, etc."
          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent font-mono text-sm"
        ></textarea>

        <!-- Variables help -->
        <div v-if="selectedEvent" class="mt-4 p-4 bg-gray-50 rounded-lg">
          <h3 class="text-sm font-medium text-gray-900 mb-2">Variables disponibles:</h3>
          <div class="grid grid-cols-2 md:grid-cols-3 gap-2 text-xs">
            <div
              v-for="(description, variable) in selectedEvent.available_variables"
              :key="variable"
              class="flex items-start gap-1"
            >
              <code class="bg-white px-2 py-1 rounded text-indigo-600">{{ '{{' + variable + '}}' }}</code>
              <span class="text-gray-600">{{ description }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- HTML Body (only for EMAIL and IN_APP) -->
      <div v-if="supportsHtml" class="bg-white border border-gray-200 rounded-lg p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Contenido HTML (Opcional)</h2>
        <textarea
          v-model="form.html_body"
          rows="10"
          placeholder="<h1>Hola {{user.first_name}}</h1>
<p>Tu solicitud ha sido aprobada...</p>"
          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent font-mono text-sm"
        ></textarea>
        <p class="mt-2 text-xs text-gray-500">
          Si no se proporciona, se usará el contenido de texto plano
        </p>
      </div>

      <!-- Actions -->
      <div class="flex items-center justify-end gap-3">
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

    <!-- Preview Modal -->
    <div
      v-if="showPreview"
      class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50"
      @click.self="showPreview = false"
    >
      <div class="bg-white rounded-lg max-w-2xl w-full max-h-[80vh] overflow-hidden">
        <div class="p-6 border-b border-gray-200 flex items-center justify-between">
          <h3 class="text-lg font-semibold text-gray-900">Vista Previa</h3>
          <button
            class="p-2 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100"
            @click="showPreview = false"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M6 18L18 6M6 6l12 12"
              />
            </svg>
          </button>
        </div>
        <div class="p-6 overflow-y-auto max-h-[60vh]">
          <div class="bg-gray-50 rounded-lg p-4 font-mono text-sm whitespace-pre-wrap">
            {{ previewResult }}
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
