<script setup lang="ts">
import { ref, onMounted } from 'vue'
import {
  notificationPreferencesApi,
  type NotificationPreferences,
} from '@/services/notificationPreferences'
import { notificationTemplatesApi, type NotificationEvent } from '@/services/notificationTemplates'

const preferences = ref<NotificationPreferences>({
  sms_enabled: true,
  whatsapp_enabled: true,
  email_enabled: true,
  in_app_enabled: true,
  disabled_events: [],
})
const events = ref<NotificationEvent[]>([])
const loading = ref(false)
const saving = ref(false)
const error = ref<string | null>(null)
const successMessage = ref<string | null>(null)

// Load preferences and events
const loadPreferences = async () => {
  loading.value = true
  error.value = null
  try {
    preferences.value = await notificationPreferencesApi.get('staff')
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Error al cargar preferencias'
    console.error('Error loading preferences:', err)
  } finally {
    loading.value = false
  }
}

const loadEvents = async () => {
  try {
    const config = await notificationTemplatesApi.getConfig()
    // Filter events that are relevant for staff
    events.value = config.events.filter((e) => {
      const staffEvents = [
        'application.submitted',
        'application.assigned',
        'documents.uploaded',
        'reference.added',
        'kyc.completed',
        'staff.application_assigned',
      ]
      return staffEvents.includes(e.value)
    })
  } catch (err) {
    console.error('Error loading events:', err)
  }
}

// Save preferences
const save = async () => {
  saving.value = true
  error.value = null
  successMessage.value = null
  try {
    const result = await notificationPreferencesApi.update(
      {
        sms_enabled: preferences.value.sms_enabled,
        whatsapp_enabled: preferences.value.whatsapp_enabled,
        email_enabled: preferences.value.email_enabled,
        in_app_enabled: preferences.value.in_app_enabled,
        disabled_events: preferences.value.disabled_events,
      },
      'staff'
    )
    successMessage.value = result.message
    setTimeout(() => {
      successMessage.value = null
    }, 3000)
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Error al guardar preferencias'
    console.error('Error saving preferences:', err)
  } finally {
    saving.value = false
  }
}

// Toggle event
const toggleEvent = (eventValue: string) => {
  const index = preferences.value.disabled_events.indexOf(eventValue)
  if (index > -1) {
    preferences.value.disabled_events.splice(index, 1)
  } else {
    preferences.value.disabled_events.push(eventValue)
  }
}

// Check if event is enabled
const isEventEnabled = (eventValue: string) => {
  return !preferences.value.disabled_events.includes(eventValue)
}

onMounted(() => {
  loadPreferences()
  loadEvents()
})
</script>

<template>
  <div class="p-6 max-w-5xl mx-auto">
    <!-- Header -->
    <div class="mb-6">
      <h1 class="text-2xl font-bold text-gray-900">Preferencias de Notificación</h1>
      <p class="text-sm text-gray-600 mt-1">
        Personaliza cómo y cuándo quieres recibir notificaciones de trabajo
      </p>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="text-center py-12">
      <div
        class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600"
      ></div>
      <p class="mt-2 text-sm text-gray-600">Cargando preferencias...</p>
    </div>

    <!-- Content -->
    <div v-else class="space-y-6">
      <!-- Success Message -->
      <div
        v-if="successMessage"
        class="bg-green-50 border border-green-200 rounded-lg p-4 text-green-800 text-sm"
      >
        {{ successMessage }}
      </div>

      <!-- Error -->
      <div
        v-if="error"
        class="bg-red-50 border border-red-200 rounded-lg p-4 text-red-800 text-sm"
      >
        {{ error }}
      </div>

      <!-- Channel Preferences -->
      <div class="bg-white border border-gray-200 rounded-lg p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Canales de Notificación</h2>
        <div class="space-y-4">
          <!-- SMS -->
          <label class="flex items-start gap-3 cursor-pointer">
            <input
              v-model="preferences.sms_enabled"
              type="checkbox"
              class="mt-1 w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500"
            />
            <div class="flex-1">
              <div class="font-medium text-gray-900">SMS</div>
              <p class="text-sm text-gray-600">
                Recibe mensajes de texto en tu número de teléfono
              </p>
            </div>
          </label>

          <!-- WhatsApp -->
          <label class="flex items-start gap-3 cursor-pointer">
            <input
              v-model="preferences.whatsapp_enabled"
              type="checkbox"
              class="mt-1 w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500"
            />
            <div class="flex-1">
              <div class="font-medium text-gray-900">WhatsApp</div>
              <p class="text-sm text-gray-600">Recibe mensajes en WhatsApp</p>
            </div>
          </label>

          <!-- Email -->
          <label class="flex items-start gap-3 cursor-pointer">
            <input
              v-model="preferences.email_enabled"
              type="checkbox"
              class="mt-1 w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500"
            />
            <div class="flex-1">
              <div class="font-medium text-gray-900">Email</div>
              <p class="text-sm text-gray-600">
                Recibe notificaciones en tu correo electrónico
              </p>
            </div>
          </label>

          <!-- In-App -->
          <label class="flex items-start gap-3 cursor-pointer">
            <input
              v-model="preferences.in_app_enabled"
              type="checkbox"
              class="mt-1 w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500"
            />
            <div class="flex-1">
              <div class="font-medium text-gray-900">Notificaciones en la App</div>
              <p class="text-sm text-gray-600">Ver notificaciones dentro de la aplicación</p>
            </div>
          </label>
        </div>
      </div>

      <!-- Event Preferences -->
      <div class="bg-white border border-gray-200 rounded-lg p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Tipos de Notificación</h2>
        <p class="text-sm text-gray-600 mb-4">
          Selecciona qué eventos de trabajo deseas recibir
        </p>
        <div class="space-y-3">
          <div
            v-for="event in events"
            :key="event.value"
            class="flex items-start gap-3 p-3 rounded-lg hover:bg-gray-50"
          >
            <input
              :id="event.value"
              type="checkbox"
              :checked="isEventEnabled(event.value)"
              class="mt-1 w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500"
              @change="toggleEvent(event.value)"
            />
            <label :for="event.value" class="flex-1 cursor-pointer">
              <div class="font-medium text-gray-900">{{ event.label }}</div>
              <div class="text-sm text-gray-600 mt-1">
                Canales recomendados: {{ event.recommended_channels.join(', ') }}
              </div>
            </label>
          </div>
        </div>
        <div v-if="events.length === 0" class="text-center py-8 text-gray-500 text-sm">
          No hay eventos configurados para el personal
        </div>
      </div>

      <!-- Save Button -->
      <div class="flex items-center justify-end gap-3">
        <button
          type="button"
          class="px-6 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
          :disabled="saving"
          @click="save"
        >
          {{ saving ? 'Guardando...' : 'Guardar Preferencias' }}
        </button>
      </div>
    </div>
  </div>
</template>
