<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { notificationTemplatesApi, type NotificationTemplate } from '@/services/notificationTemplates'
import { notificationVariables } from '@/utils/notificationVariables'
import { isAxiosError } from 'axios'

interface Props {
  show: boolean
  template: NotificationTemplate | null
}

const props = defineProps<Props>()

const emit = defineEmits<{
  'update:show': [value: boolean]
  sent: []
}>()

const recipient = ref('')
const showVariables = ref(true)
const variables = ref<Record<string, Record<string, string>>>({})
const sending = ref(false)
const result = ref<{ success: boolean; message: string } | null>(null)

const isInApp = computed(() => props.template?.channel === 'IN_APP')

const recipientPlaceholder = computed(() => {
  if (!props.template) return ''
  return props.template.channel === 'EMAIL' ? 'correo@ejemplo.com' : '5512345678'
})

const recipientType = computed(() => {
  if (!props.template) return 'text'
  return props.template.channel === 'EMAIL' ? 'email' : 'tel'
})

const recipientLabel = computed(() => {
  if (!props.template) return 'Destinatario'
  return props.template.channel === 'EMAIL' ? 'Correo electrónico' : 'Teléfono (10 dígitos)'
})

// Build nested variables object from flat entries
const buildVariablesPayload = (): Record<string, unknown> => {
  const payload: Record<string, unknown> = {}
  for (const [group, fields] of Object.entries(variables.value)) {
    if (group === '_root') {
      // Root-level variables go directly into payload (not nested under _root)
      for (const [field, value] of Object.entries(fields)) {
        if (value) payload[field] = value
      }
    } else {
      const nested: Record<string, string> = {}
      let hasValue = false
      for (const [field, value] of Object.entries(fields)) {
        if (value) {
          nested[field] = value
          hasValue = true
        }
      }
      if (hasValue) {
        payload[group] = nested
      }
    }
  }
  return payload
}

// Extract {{variable}} references from template body/subject/html_body
const extractTemplateVariables = (template: NotificationTemplate): string[] => {
  const texts = [template.body, template.subject, template.html_body].filter(Boolean)
  const allText = texts.join(' ')
  const matches = allText.matchAll(/\{\{([^#/^}]+?)\}\}/g)
  const helpers = ['currency', 'date', 'upper', 'lower', 'if', 'unless', 'each', 'with']
  return [...new Set([...matches].map(m => m[1].trim().split(' ').pop()!))]
    .filter(v => !helpers.includes(v) && v !== 'else')
}

// Initialize variable fields from extracted template variables, pre-filled with examples
const populateVariables = () => {
  if (!props.template) return

  const keys = extractTemplateVariables(props.template)
  const vars: Record<string, Record<string, string>> = {}

  for (const key of keys) {
    const def = notificationVariables.find((v) => v.key === key)
    const example = def?.example ?? key.split('.').pop()!.replace(/_/g, ' ').replace(/^\w/, c => c.toUpperCase())
    const parts = key.split('.')
    if (parts.length === 2) {
      const [group, field] = parts
      if (!vars[group]) vars[group] = {}
      vars[group][field] = example
    } else {
      if (!vars['_root']) vars['_root'] = {}
      vars['_root'][key] = example
    }
  }

  variables.value = vars
}

// Fill all variable fields with example data from notificationVariables dictionary
const fillTestData = () => {
  for (const [group, fields] of Object.entries(variables.value)) {
    for (const field of Object.keys(fields)) {
      const key = group === '_root' ? field : `${group}.${field}`
      const def = notificationVariables.find((v) => v.key === key)
      variables.value[group][field] = def?.example ?? field.replace(/_/g, ' ').replace(/^\w/, c => c.toUpperCase())
    }
  }
}

// Reset state when modal opens
watch(
  () => props.show,
  (newVal) => {
    if (newVal) {
      recipient.value = ''
      result.value = null
      sending.value = false
      populateVariables()
    }
  }
)

const close = () => {
  emit('update:show', false)
}

const send = async () => {
  if (!props.template || isInApp.value) return

  sending.value = true
  result.value = null

  try {
    const response = await notificationTemplatesApi.sendTest(props.template.id, {
      recipient: recipient.value,
      variables: buildVariablesPayload(),
    })
    result.value = { success: true, message: response.message || 'Notificación enviada exitosamente.' }
    emit('sent')
  } catch (err: unknown) {
    if (isAxiosError(err)) {
      result.value = {
        success: false,
        message: err.response?.data?.message || 'Error al enviar la notificación de prueba.',
      }
    } else {
      result.value = { success: false, message: 'Error inesperado al enviar.' }
    }
  } finally {
    sending.value = false
  }
}

const categoryLabels: Record<string, string> = {
  tenant: 'Tenant',
  applicant: 'Solicitante',
  application: 'Solicitud',
  otp: 'OTP',
  user: 'Usuario',
  document: 'Documento',
  reference: 'Referencia',
  _root: 'Otros',
}
</script>

<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition-opacity duration-200"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition-opacity duration-200"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="show"
        class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4"
        @click.self="close"
      >
        <Transition
          enter-active-class="transition-all duration-200"
          enter-from-class="opacity-0 scale-95"
          enter-to-class="opacity-100 scale-100"
          leave-active-class="transition-all duration-200"
          leave-from-class="opacity-100 scale-100"
          leave-to-class="opacity-0 scale-95"
        >
          <div
            v-if="show"
            class="bg-white rounded-xl shadow-xl w-full max-w-md max-h-[90vh] overflow-y-auto"
          >
            <!-- Header -->
            <div class="px-6 py-4 border-b border-gray-200">
              <div class="flex items-center justify-between">
                <div>
                  <h3 class="text-lg font-semibold text-gray-900">Enviar Prueba</h3>
                  <p class="text-sm text-gray-500 mt-0.5">{{ template?.name }}</p>
                </div>
                <button
                  class="p-1.5 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100 transition-colors"
                  @click="close"
                >
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                  </svg>
                </button>
              </div>
            </div>

            <div class="px-6 py-4 space-y-4">
              <!-- IN_APP info banner -->
              <div v-if="isInApp" class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <div class="flex items-start gap-3">
                  <svg class="w-5 h-5 text-blue-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                  <div>
                    <p class="text-sm font-medium text-blue-800">Canal no compatible</p>
                    <p class="text-sm text-blue-700 mt-1">
                      Las notificaciones internas (In-App) no se pueden enviar como prueba externa. Se muestran directamente en la aplicación.
                    </p>
                  </div>
                </div>
              </div>

              <!-- Channel badge -->
              <div v-if="!isInApp" class="flex items-center gap-2">
                <span
                  class="px-2.5 py-1 text-xs font-bold rounded"
                  :class="{
                    'bg-purple-100 text-purple-800': template?.channel === 'EMAIL',
                    'bg-blue-100 text-blue-800': template?.channel === 'SMS',
                    'bg-green-100 text-green-800': template?.channel === 'WHATSAPP',
                  }"
                >
                  {{ template?.channel_label }}
                </span>
                <span class="text-xs text-gray-500">{{ template?.event_label }}</span>
              </div>

              <!-- Recipient input -->
              <div v-if="!isInApp">
                <label class="block text-sm font-medium text-gray-700 mb-1.5">
                  {{ recipientLabel }} <span class="text-red-500">*</span>
                </label>
                <div class="flex items-center">
                  <span
                    v-if="template?.channel !== 'EMAIL'"
                    class="inline-flex items-center px-3 py-2 border border-r-0 border-gray-300 rounded-l-lg bg-gray-50 text-gray-500 text-sm"
                  >
                    +52
                  </span>
                  <input
                    v-model="recipient"
                    :type="recipientType"
                    :placeholder="recipientPlaceholder"
                    :class="[
                      'w-full px-3 py-2 border border-gray-300 focus:ring-2 focus:ring-indigo-500 focus:border-transparent text-sm',
                      template?.channel !== 'EMAIL' ? 'rounded-r-lg' : 'rounded-lg'
                    ]"
                    :maxlength="template?.channel === 'EMAIL' ? undefined : 10"
                  />
                </div>
              </div>

              <!-- Collapsible variables section -->
              <div v-if="!isInApp && Object.keys(variables).length > 0">
                <button
                  type="button"
                  class="w-full flex items-center justify-between text-sm font-medium text-gray-700 hover:text-gray-900 py-2"
                  @click="showVariables = !showVariables"
                >
                  <span>Variables de prueba</span>
                  <svg
                    class="w-4 h-4 transition-transform"
                    :class="showVariables ? 'rotate-180' : ''"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                  >
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                  </svg>
                </button>

                <div v-if="showVariables" class="space-y-3 mt-2">
                  <button
                    type="button"
                    class="w-full px-3 py-1.5 text-xs font-medium text-indigo-700 bg-indigo-50 border border-indigo-200 rounded-lg hover:bg-indigo-100 transition-colors"
                    @click="fillTestData"
                  >
                    Rellenar datos de prueba
                  </button>
                  <div
                    v-for="(fields, group) in variables"
                    :key="group"
                    class="bg-gray-50 rounded-lg p-3"
                  >
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
                      {{ categoryLabels[group] || group }}
                    </p>
                    <div class="space-y-2">
                      <div v-for="(value, field) in fields" :key="field">
                        <label class="block text-xs text-gray-600 mb-0.5">
                          {{ group === '_root' ? field : `${group}.${field}` }}
                        </label>
                        <input
                          v-model="variables[group][field]"
                          type="text"
                          class="w-full px-2.5 py-1.5 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-indigo-500 focus:border-transparent"
                        />
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Result banner -->
              <div
                v-if="result"
                class="rounded-lg p-4"
                :class="result.success ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200'"
              >
                <div class="flex items-start gap-3">
                  <svg
                    v-if="result.success"
                    class="w-5 h-5 text-green-500 mt-0.5 flex-shrink-0"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                  >
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                  <svg
                    v-else
                    class="w-5 h-5 text-red-500 mt-0.5 flex-shrink-0"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                  >
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                  <p
                    class="text-sm"
                    :class="result.success ? 'text-green-800' : 'text-red-800'"
                  >
                    {{ result.message }}
                  </p>
                </div>
              </div>
            </div>

            <!-- Footer -->
            <div class="px-6 py-4 border-t border-gray-200 flex items-center justify-end gap-3">
              <button
                type="button"
                class="px-4 py-2 text-sm font-medium text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
                @click="close"
              >
                Cerrar
              </button>
              <button
                v-if="!isInApp"
                type="button"
                class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
                :disabled="sending || !recipient"
                @click="send"
              >
                <svg
                  v-if="sending"
                  class="w-4 h-4 animate-spin"
                  fill="none"
                  viewBox="0 0 24 24"
                >
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                </svg>
                <svg
                  v-else
                  class="w-4 h-4"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                </svg>
                {{ sending ? 'Enviando...' : 'Enviar Prueba' }}
              </button>
            </div>
          </div>
        </Transition>
      </div>
    </Transition>
  </Teleport>
</template>
