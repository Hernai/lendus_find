<template>
  <div class="notification-preview">
    <!-- Compact Channel Tabs -->
    <div class="flex gap-2 mb-4">
      <button
        v-for="channel in availableChannels"
        :key="channel.value"
        @click="selectedChannel = channel.value"
        :class="[
          selectedChannel === channel.value
            ? 'bg-indigo-100 text-indigo-700 border-indigo-300'
            : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50',
          'px-3 py-2 border rounded-lg text-sm font-medium transition-all'
        ]"
      >
        {{ channel.label }}
      </button>
    </div>

    <!-- Preview Area -->
    <div class="preview-container bg-gray-50 rounded-lg p-6 min-h-[500px]">
      <!-- Email Preview -->
      <div v-if="selectedChannel === 'EMAIL'" class="email-preview">
        <div class="bg-white rounded-lg shadow-lg max-w-2xl mx-auto">
          <div class="bg-gray-100 px-4 py-3 border-b border-gray-200 rounded-t-lg">
            <div class="flex items-center gap-2 text-sm text-gray-600">
              <span class="font-semibold">De:</span>
              <span>{{ sampleData.tenant?.name || 'Tu Empresa' }} &lt;noreply@{{ sampleData.tenant?.slug || 'empresa' }}.com&gt;</span>
            </div>
            <div class="flex items-center gap-2 text-sm text-gray-600 mt-1">
              <span class="font-semibold">Para:</span>
              <span>{{ sampleData.applicant?.email || 'cliente@example.com' }}</span>
            </div>
            <div class="flex items-center gap-2 text-sm text-gray-900 mt-1">
              <span class="font-semibold">Asunto:</span>
              <span>{{ renderedSubject }}</span>
            </div>
          </div>
          <div class="p-6">
            <iframe
              ref="emailIframe"
              :srcdoc="renderedHtmlBody || renderedBody"
              class="w-full border-0 bg-white"
              style="min-height: 600px;"
              @load="resizeIframe"
            />
          </div>
        </div>
      </div>

      <!-- WhatsApp Preview -->
      <div v-else-if="selectedChannel === 'WHATSAPP'" class="whatsapp-preview">
        <div class="max-w-md mx-auto bg-[#e5ddd5] rounded-lg shadow-lg overflow-hidden" style="background-image: url('data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAoAAAAKCAYAAACNMs+9AAAAFUlEQVQYV2NkYGD4z8DAwMgABXAGAC3pAwf8VZ7lAAAAAElFTkSuQmCC'); background-repeat: repeat;">
          <!-- WhatsApp Header -->
          <div class="bg-[#075e54] px-4 py-3 flex items-center gap-3">
            <div class="w-10 h-10 rounded-full bg-gray-300 flex items-center justify-center text-gray-600 font-semibold">
              {{ (sampleData.tenant?.name || 'E')[0] }}
            </div>
            <div class="flex-1">
              <div class="text-white font-semibold">{{ sampleData.tenant?.name || 'Tu Empresa' }}</div>
              <div class="text-green-200 text-xs">En línea</div>
            </div>
          </div>

          <!-- Chat Messages -->
          <div class="p-4 space-y-2 min-h-[400px]">
            <!-- Message Bubble -->
            <div class="flex justify-end">
              <div class="bg-[#dcf8c6] rounded-lg px-4 py-2 max-w-[85%] shadow-sm">
                <div class="text-sm text-gray-800 whitespace-pre-wrap" v-html="formatWhatsAppMessage(stripHtmlTags(renderedBody))"></div>
                <div class="text-xs text-gray-500 mt-1 text-right">{{ currentTime }}</div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- SMS Preview -->
      <div v-else-if="selectedChannel === 'SMS'" class="sms-preview">
        <div class="max-w-sm mx-auto">
          <!-- Phone Mockup -->
          <div class="bg-gray-900 rounded-[3rem] p-3 shadow-2xl">
            <div class="bg-white rounded-[2.5rem] overflow-hidden">
              <!-- Phone Screen -->
              <div class="bg-gradient-to-b from-gray-50 to-gray-100 min-h-[600px] flex flex-col">
                <!-- Status Bar -->
                <div class="bg-gray-100 px-6 py-2 flex justify-between items-center text-xs">
                  <span>{{ currentTime }}</span>
                  <div class="flex gap-1 items-center">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                      <path d="M2 11a1 1 0 011-1h2a1 1 0 011 1v5a1 1 0 01-1 1H3a1 1 0 01-1-1v-5zM8 7a1 1 0 011-1h2a1 1 0 011 1v9a1 1 0 01-1 1H9a1 1 0 01-1-1V7zM14 4a1 1 0 011-1h2a1 1 0 011 1v12a1 1 0 01-1 1h-2a1 1 0 01-1-1V4z" />
                    </svg>
                  </div>
                </div>

                <!-- SMS Header -->
                <div class="bg-white px-4 py-3 border-b border-gray-200 flex items-center gap-3">
                  <button class="text-blue-500">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                  </button>
                  <div class="flex-1">
                    <div class="font-semibold text-gray-900">{{ sampleData.tenant?.name || 'Tu Empresa' }}</div>
                    <div class="text-xs text-gray-500">Mensaje de texto</div>
                  </div>
                </div>

                <!-- SMS Message -->
                <div class="flex-1 p-4">
                  <div class="flex justify-start mb-4">
                    <div class="bg-gray-200 rounded-2xl rounded-tl-sm px-4 py-2 max-w-[85%] shadow-sm">
                      <div class="text-sm text-gray-800 whitespace-pre-wrap">{{ stripHtmlTags(renderedBody) }}</div>
                    </div>
                  </div>

                  <!-- Character Count -->
                  <div class="text-center text-xs mt-4">
                    <span :class="characterCount > 160 ? 'text-red-600 font-semibold' : 'text-gray-500'">
                      {{ characterCount }} / 160 caracteres
                    </span>
                    <span v-if="characterCount > 160" class="block text-red-600 mt-1">
                      ⚠️ Excede el límite de SMS (se enviará como {{ Math.ceil(characterCount / 160) }} mensajes)
                    </span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- In-App Preview -->
      <div v-else-if="selectedChannel === 'IN_APP'" class="in-app-preview">
        <div class="max-w-2xl mx-auto space-y-4">
          <!-- Notification Card -->
          <div class="bg-white rounded-lg shadow-lg border border-gray-200 overflow-hidden hover:shadow-xl transition-shadow">
            <div class="p-6">
              <div class="flex items-start gap-4">
                <!-- Icon -->
                <div class="flex-shrink-0">
                  <div class="w-12 h-12 rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                    </svg>
                  </div>
                </div>

                <!-- Content -->
                <div class="flex-1 min-w-0">
                  <div class="flex items-start justify-between gap-4">
                    <div class="flex-1">
                      <h3 class="text-lg font-semibold text-gray-900 mb-1">
                        {{ stripHtmlTags(renderedSubject || 'Notificación') }}
                      </h3>
                      <p class="text-gray-600 whitespace-pre-wrap">{{ stripHtmlTags(renderedBody) }}</p>
                    </div>
                    <button class="text-gray-400 hover:text-gray-600">
                      <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" />
                      </svg>
                    </button>
                  </div>
                  <div class="flex items-center gap-4 mt-4 text-sm text-gray-500">
                    <span>{{ sampleData.tenant?.name || 'Tu Empresa' }}</span>
                    <span>•</span>
                    <span>{{ currentTime }}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Preview Info -->
          <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
            <div class="flex gap-3">
              <svg class="w-5 h-5 text-blue-600 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
              </svg>
              <div class="text-sm text-blue-800">
                <p class="font-semibold mb-1">Vista previa de notificación dentro de la aplicación</p>
                <p>Esta notificación aparecerá en el panel de notificaciones del usuario dentro de la plataforma.</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch, nextTick } from 'vue'
import type { NotificationChannel } from '@/services/notificationTemplates'

interface Props {
  body: string
  htmlBody?: string | null
  subject?: string | null
  channel: string
  variables?: Record<string, string>
}

const props = defineProps<Props>()

const selectedChannel = ref<string>(props.channel)
const emailIframe = ref<HTMLIFrameElement>()

// Available channels with icons
const availableChannels = computed(() => [
  { value: 'EMAIL', label: 'Email' },
  { value: 'WHATSAPP', label: 'WhatsApp' },
  { value: 'SMS', label: 'SMS' },
  { value: 'IN_APP', label: 'En App' },
])

// Sample data for preview
const sampleData = computed(() => ({
  tenant: {
    name: 'Lendus Demo',
    slug: 'demo',
    support_email: 'soporte@lendus.mx',
    support_phone: '555-1234-567',
  },
  applicant: {
    name: 'Juan Pérez García',
    first_name: 'Juan',
    email: 'juan.perez@example.com',
    phone: '5551234567',
  },
  application: {
    id: 'APP-2024-001',
    product_name: 'Crédito Simple',
    amount: '$50,000.00',
    status: 'En Revisión',
  },
  otp: {
    code: '123456',
    expires_in: '10 minutos',
  },
  analyst: {
    name: 'Ana Martínez',
    email: 'ana.martinez@lendus.mx',
  },
  document: {
    type: 'INE',
    type_label: 'Identificación Oficial (INE)',
    status: 'Aprobado',
  },
  ...props.variables,
}))

// Current time for previews
const currentTime = computed(() => {
  const now = new Date()
  return now.toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' })
})

// Render template with sample data
const renderTemplate = (template: string): string => {
  let rendered = template

  // Simple Handlebars-style replacement
  const flatData: Record<string, any> = {}

  // Flatten nested objects
  Object.entries(sampleData.value).forEach(([key, value]) => {
    if (typeof value === 'object' && value !== null) {
      Object.entries(value).forEach(([subKey, subValue]) => {
        flatData[`${key}.${subKey}`] = subValue
      })
    } else {
      flatData[key] = value
    }
  })

  // Replace variables (escape special regex characters in key)
  Object.entries(flatData).forEach(([key, value]) => {
    const escapedKey = key.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
    const regex = new RegExp(`\\{\\{\\s*${escapedKey}\\s*\\}\\}`, 'g')
    rendered = rendered.replace(regex, String(value))
  })

  return rendered
}

const renderedBody = computed(() => renderTemplate(props.body))
const renderedHtmlBody = computed(() => props.htmlBody ? renderTemplate(props.htmlBody) : null)
const renderedSubject = computed(() => props.subject ? renderTemplate(props.subject) : '')

// Character count for SMS
const characterCount = computed(() => renderedBody.value.length)

// Strip HTML tags to show plain text
const stripHtmlTags = (html: string): string => {
  // Create a temporary div to parse HTML
  const tmp = document.createElement('div')
  tmp.innerHTML = html
  // Get text content which strips all tags
  return tmp.textContent || tmp.innerText || ''
}

// Format WhatsApp message with basic formatting
const formatWhatsAppMessage = (text: string): string => {
  return text
    .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>') // Bold
    .replace(/\*(.*?)\*/g, '<em>$1</em>') // Italic
    .replace(/_(.*?)_/g, '<em>$1</em>') // Italic alternative
    .replace(/~(.*?)~/g, '<del>$1</del>') // Strikethrough
    .replace(/\n/g, '<br>') // Line breaks
}

// Resize iframe to content
const resizeIframe = () => {
  nextTick(() => {
    if (emailIframe.value?.contentWindow?.document.body) {
      const height = emailIframe.value.contentWindow.document.body.scrollHeight
      emailIframe.value.style.height = `${height + 20}px`
    }
  })
}

// Watch for channel changes from props
watch(() => props.channel, (newChannel) => {
  if (newChannel) {
    selectedChannel.value = newChannel
  }
}, { immediate: true })

// Watch for content changes to resize iframe
watch([renderedHtmlBody, renderedBody], () => {
  if (selectedChannel.value === 'EMAIL') {
    nextTick(() => resizeIframe())
  }
})

// Get channel icon component
const getChannelIcon = (channel: string) => {
  const icons: Record<string, any> = {
    EMAIL: 'svg',
    WHATSAPP: 'svg',
    SMS: 'svg',
    IN_APP: 'svg',
  }
  return icons[channel] || 'svg'
}
</script>

<style scoped>
.notification-preview {
  @apply w-full;
}

.preview-container {
  min-height: 500px;
}

/* WhatsApp pattern background */
.whatsapp-preview {
  background-color: #e5ddd5;
}

/* Email iframe styling */
iframe {
  display: block;
  width: 100%;
  border: none;
  background: white;
}
</style>
