<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount } from 'vue'
import { v2 } from '@/services/v2'
import type { V2TenantInfo, V2ApiConfig } from '@/services/v2/config.staff.service'
import { AppInput, AppConfirmModal } from '@/components/common'
import TenantBrandingEditor, { type Branding, type TenantPreviewInfo } from '@/components/admin/TenantBrandingEditor.vue'
import { useToast } from '@/composables'
import { logger } from '@/utils/logger'

const log = logger.child('AdminSettings')
const toast = useToast()

// Use V2 types
type TenantInfo = V2TenantInfo
type ApiConfig = V2ApiConfig

// State
const isLoading = ref(true)
const error = ref('')
const activeTab = ref<'general' | 'branding' | 'apis'>('branding')

// Data
const tenant = ref<TenantInfo | null>(null)
const branding = ref<Branding | null>(null)
const apiConfigs = ref<ApiConfig[]>([])
const availableProviders = ref<Record<string, string>>({})
const availableServiceTypes = ref<Record<string, string>>({})

// Form state
const isSaving = ref(false)
const saveMessage = ref('')
const saveError = ref('')

// Timeout cleanup
const messageTimeouts: ReturnType<typeof setTimeout>[] = []
const clearMessageAfterDelay = () => {
  const timeoutId = setTimeout(() => {
    saveMessage.value = ''
    saveError.value = ''
  }, 3000)
  messageTimeouts.push(timeoutId)
}
onBeforeUnmount(() => {
  messageTimeouts.forEach(clearTimeout)
})

// API Config modal
const showApiModal = ref(false)
const editingApiConfig = ref<ApiConfig | null>(null)
const apiForm = ref({
  provider: '',
  service_type: '',
  api_key: '',
  api_secret: '',
  account_sid: '',
  auth_token: '',
  from_number: '',
  from_email: '',
  domain: '',
  is_active: true,
  is_sandbox: false
})

// Visibility toggles for credential fields
const showApiKey = ref(false)
const showApiSecret = ref(false)

// Computed tenant info for preview
const tenantPreviewInfo = ref<TenantPreviewInfo>({ name: '', slug: '' })

// Load data using V2 API
const loadConfig = async () => {
  isLoading.value = true
  error.value = ''

  try {
    const response = await v2.staff.config.getConfig()
    const data = response.data!

    tenant.value = data.tenant
    branding.value = data.branding as Branding
    apiConfigs.value = data.api_configs
    availableProviders.value = data.available_providers
    availableServiceTypes.value = data.available_service_types

    // Update preview info
    tenantPreviewInfo.value = {
      name: tenant.value.name,
      slug: tenant.value.slug
    }
  } catch (e) {
    log.error('Error al cargar configuración', { error: e })
    error.value = 'Error al cargar la configuracion'
  } finally {
    isLoading.value = false
  }
}

onMounted(loadConfig)

// Save tenant info using V2 API
const saveTenant = async () => {
  if (!tenant.value) return

  isSaving.value = true
  saveMessage.value = ''
  saveError.value = ''

  try {
    await v2.staff.config.updateTenant({
      name: tenant.value.name,
      legal_name: tenant.value.legal_name || null,
      rfc: tenant.value.rfc || null,
      email: tenant.value.email || null,
      phone: tenant.value.phone || null,
      website: tenant.value.website || null
    })
    saveMessage.value = 'Informacion guardada'
    clearMessageAfterDelay()
  } catch (e) {
    saveError.value = 'Error al guardar'
  } finally {
    isSaving.value = false
  }
}

// Save branding using V2 API
const saveBranding = async () => {
  if (!branding.value) return

  isSaving.value = true
  saveMessage.value = ''
  saveError.value = ''

  try {
    await v2.staff.config.updateBranding(branding.value as Parameters<typeof v2.staff.config.updateBranding>[0])
    saveMessage.value = 'Branding guardado'
    clearMessageAfterDelay()
  } catch (e) {
    saveError.value = 'Error al guardar'
  } finally {
    isSaving.value = false
  }
}

// Handle logo upload from component
const handleLogoUpload = (field: string, file: File) => {
  // In a full implementation, this would upload to S3
  // For now the component handles base64 conversion internally
  log.debug('Logo upload requested', { field, fileName: file.name })
}

// API Config methods
const openAddApiModal = () => {
  editingApiConfig.value = null
  apiForm.value = {
    provider: '',
    service_type: '',
    api_key: '',
    api_secret: '',
    account_sid: '',
    auth_token: '',
    from_number: '',
    from_email: '',
    domain: '',
    is_active: true,
    is_sandbox: false
  }
  // Reset visibility toggles
  showApiKey.value = false
  showApiSecret.value = false
  showApiModal.value = true
}

const openEditApiModal = (config: ApiConfig) => {
  editingApiConfig.value = config
  apiForm.value = {
    provider: config.provider,
    service_type: config.service_type,
    api_key: '',
    api_secret: '',
    account_sid: '',
    auth_token: '',
    from_number: config.from_number || '',
    from_email: config.from_email || '',
    domain: config.domain || '',
    is_active: config.is_active,
    is_sandbox: config.is_sandbox
  }
  // Reset visibility toggles
  showApiKey.value = false
  showApiSecret.value = false
  showApiModal.value = true
}

const saveApiConfig = async () => {
  isSaving.value = true
  saveError.value = ''

  try {
    const payload: Parameters<typeof v2.staff.config.saveApiConfig>[0] = {
      provider: apiForm.value.provider,
      service_type: apiForm.value.service_type,
      from_number: apiForm.value.from_number || undefined,
      from_email: apiForm.value.from_email || undefined,
      domain: apiForm.value.domain || undefined,
      is_active: apiForm.value.is_active,
      is_sandbox: apiForm.value.is_sandbox
    }

    // Only include credentials if provided
    if (apiForm.value.api_key) payload.api_key = apiForm.value.api_key
    if (apiForm.value.api_secret) payload.api_secret = apiForm.value.api_secret
    if (apiForm.value.account_sid) payload.account_sid = apiForm.value.account_sid
    if (apiForm.value.auth_token) payload.auth_token = apiForm.value.auth_token

    await v2.staff.config.saveApiConfig(payload)
    await loadConfig()
    showApiModal.value = false
    saveMessage.value = 'Configuracion guardada'
    clearMessageAfterDelay()
  } catch (e) {
    saveError.value = 'Error al guardar configuracion'
  } finally {
    isSaving.value = false
  }
}

// Delete API config confirmation
const showDeleteApiModal = ref(false)
const apiToDelete = ref<ApiConfig | null>(null)

const confirmDeleteApiConfig = (config: ApiConfig) => {
  apiToDelete.value = config
  showDeleteApiModal.value = true
}

const deleteApiConfig = async () => {
  if (!apiToDelete.value) return

  try {
    await v2.staff.config.deleteApiConfig(apiToDelete.value.id)
    showDeleteApiModal.value = false
    await loadConfig()
    toast.success('Configuración eliminada')
  } catch (e) {
    log.error('Error al eliminar configuración', { error: e })
    toast.error('Error al eliminar la configuración')
  }
  apiToDelete.value = null
}

const testApiConfig = async (config: ApiConfig) => {
  try {
    const response = await v2.staff.config.testApiConfig(config.id)
    if (response.success) {
      saveMessage.value = 'Conexion exitosa'
    } else {
      saveError.value = response.message || 'Error en la prueba'
    }
    await loadConfig()
    clearMessageAfterDelay()
  } catch (e) {
    saveError.value = 'Error al probar conexion'
  }
}

// Get provider fields
const getProviderFields = (provider: string) => {
  switch (provider) {
    case 'twilio':
      return ['account_sid', 'auth_token', 'from_number']
    case 'messagebird':
      return ['api_key', 'from_number']
    case 'sendgrid':
      return ['api_key', 'from_email']
    case 'mailgun':
      return ['api_key', 'domain', 'from_email']
    case 'nubarium':
    case 'mati':
    case 'incode':
    case 'truora':
    case 'onfido':
    case 'jumio':
      return ['api_key', 'api_secret']
    case 'buro_credito':
    case 'circulo_credito':
      return ['api_key', 'api_secret']
    default:
      return ['api_key']
  }
}

// Get provider-specific labels for fields
const getProviderLabels = (provider: string) => {
  switch (provider) {
    case 'nubarium':
      return {
        api_key: 'Usuario (Username)',
        api_secret: 'Contraseña (Password)'
      }
    default:
      return {
        api_key: 'API Key',
        api_secret: 'API Secret'
      }
  }
}

// Get provider-specific help text
const getProviderHelpText = (provider: string) => {
  switch (provider) {
    case 'nubarium':
      return {
        api_key: 'Usuario proporcionado por Nubarium para autenticación Basic Auth',
        api_secret: 'Contraseña proporcionada por Nubarium para autenticación Basic Auth'
      }
    default:
      return {
        api_key: null,
        api_secret: null
      }
  }
}
</script>

<template>
  <div class="min-h-screen bg-gray-50">
    <!-- Loading -->
    <div v-if="isLoading" class="flex items-center justify-center h-64">
      <div class="animate-spin w-8 h-8 border-4 border-primary-500 border-t-transparent rounded-full"></div>
    </div>

    <!-- Error -->
    <div v-else-if="error" class="p-6">
      <div class="bg-red-50 border border-red-200 rounded-lg p-4 text-red-700">
        {{ error }}
      </div>
    </div>

    <!-- Content -->
    <div v-else-if="tenant && branding" class="max-w-6xl mx-auto">
      <!-- Header -->
      <div class="bg-white border-b border-gray-200 px-6 py-4">
        <div class="flex items-center gap-4">
          <div
            class="w-12 h-12 rounded-xl flex items-center justify-center text-white text-lg font-bold shadow-md"
            :style="{ backgroundColor: branding.primary_color }"
          >
            {{ tenant.name?.charAt(0) }}
          </div>
          <div>
            <h1 class="text-xl font-bold text-gray-900">{{ tenant.name }}</h1>
            <p class="text-sm text-gray-500">{{ tenant.slug }}.losapp.com</p>
          </div>
        </div>
      </div>

      <!-- Messages -->
      <div v-if="saveMessage" class="mx-6 mt-4">
        <div class="bg-green-50 border border-green-200 rounded-lg p-3 text-green-700 text-sm flex items-center gap-2">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
          </svg>
          {{ saveMessage }}
        </div>
      </div>
      <div v-if="saveError" class="mx-6 mt-4">
        <div class="bg-red-50 border border-red-200 rounded-lg p-3 text-red-700 text-sm flex items-center gap-2">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          {{ saveError }}
        </div>
      </div>

      <!-- Tabs -->
      <div class="px-6 pt-4 border-b border-gray-200 bg-white">
        <div class="flex gap-1">
          <button
            v-for="tab in [
              { id: 'general', label: 'General', icon: 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4' },
              { id: 'branding', label: 'Branding', icon: 'M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01' },
              { id: 'apis', label: 'Integraciones', icon: 'M13 10V3L4 14h7v7l9-11h-7z' }
            ]"
            :key="tab.id"
            @click="activeTab = tab.id as 'general' | 'branding' | 'apis'"
            :class="[
              'flex items-center gap-2 px-4 py-3 text-sm font-medium transition-all border-b-2 -mb-px',
              activeTab === tab.id
                ? 'border-primary-500 text-primary-600'
                : 'border-transparent text-gray-500 hover:text-gray-700'
            ]"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="tab.icon" />
            </svg>
            {{ tab.label }}
          </button>
        </div>
      </div>

      <!-- General Tab -->
      <div v-show="activeTab === 'general'" class="p-6">
        <div class="bg-white rounded-xl border border-gray-200 p-6 max-w-2xl">
          <h2 class="text-lg font-semibold text-gray-900 mb-4">Informacion de la Empresa</h2>

          <div class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
              <AppInput
                v-model="tenant.name"
                label="Nombre comercial"
                placeholder="Mi Empresa"
              />
              <AppInput
                v-model="tenant.slug"
                label="Slug (URL)"
                placeholder="mi-empresa"
                disabled
                hint="No se puede cambiar"
              />
            </div>

            <AppInput
              v-model="tenant.legal_name"
              label="Razon social"
              placeholder="Mi Empresa S.A. de C.V."
            />

            <div class="grid grid-cols-2 gap-4">
              <AppInput
                v-model="tenant.rfc"
                label="RFC"
                placeholder="XAXX010101000"
                :maxlength="13"
              />
              <AppInput
                v-model="tenant.phone"
                label="Telefono"
                placeholder="55 1234 5678"
                type="tel"
              />
            </div>

            <div class="grid grid-cols-2 gap-4">
              <AppInput
                v-model="tenant.email"
                label="Email de contacto"
                placeholder="contacto@miempresa.com"
                type="email"
              />
              <AppInput
                v-model="tenant.website"
                label="Sitio web"
                placeholder="https://miempresa.com"
                type="url"
              />
            </div>
          </div>

          <div class="mt-6 pt-4 border-t border-gray-100 flex justify-end">
            <button
              @click="saveTenant"
              class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors font-medium text-sm"
              :disabled="isSaving"
            >
              {{ isSaving ? 'Guardando...' : 'Guardar Cambios' }}
            </button>
          </div>
        </div>
      </div>

      <!-- Branding Tab - Using Component -->
      <div v-show="activeTab === 'branding'" class="bg-white border-b border-gray-200">
        <TenantBrandingEditor
          v-model="branding"
          :tenant="tenantPreviewInfo"
          @logo-upload="handleLogoUpload"
        />

        <!-- Save Button -->
        <div class="px-6 py-4 border-t border-gray-200 bg-gray-50 flex justify-end">
          <button
            @click="saveBranding"
            class="px-6 py-2.5 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors font-medium text-sm shadow-sm"
            :disabled="isSaving"
          >
            {{ isSaving ? 'Guardando...' : 'Guardar Branding' }}
          </button>
        </div>
      </div>

      <!-- APIs Tab -->
      <div v-show="activeTab === 'apis'" class="p-6">
        <!-- Empty State -->
        <div v-if="apiConfigs.length === 0" class="text-center py-12 bg-white rounded-xl border border-gray-200">
          <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-gray-100 flex items-center justify-center">
            <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
            </svg>
          </div>
          <h3 class="text-lg font-semibold text-gray-900 mb-2">Sin integraciones</h3>
          <p class="text-gray-500 mb-6">Configura proveedores de SMS, Email, KYC y Buro de credito.</p>
          <button
            @click="openAddApiModal"
            class="inline-flex items-center gap-2 px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors font-medium"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            Agregar Integracion
          </button>
        </div>

        <!-- Configs List -->
        <div v-else class="space-y-4">
          <div class="flex items-center justify-between">
            <h2 class="text-lg font-semibold text-gray-900">Integraciones configuradas</h2>
            <button
              @click="openAddApiModal"
              class="flex items-center gap-2 px-3 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors font-medium text-sm"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
              </svg>
              Agregar
            </button>
          </div>

          <div class="grid gap-4">
            <div
              v-for="config in apiConfigs"
              :key="config.id"
              class="bg-white rounded-xl border border-gray-200 p-4"
            >
              <div class="flex items-start justify-between">
                <div class="flex items-center gap-3">
                  <div
                    class="w-10 h-10 rounded-lg flex items-center justify-center"
                    :class="config.is_active ? 'bg-green-100' : 'bg-gray-100'"
                  >
                    <svg
                      class="w-5 h-5"
                      :class="config.is_active ? 'text-green-600' : 'text-gray-400'"
                      fill="none"
                      stroke="currentColor"
                      viewBox="0 0 24 24"
                    >
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                  </div>
                  <div>
                    <div class="flex items-center gap-2">
                      <span class="font-medium text-gray-900">{{ config.provider_label }}</span>
                      <span class="px-2 py-0.5 text-xs font-medium rounded-full" :class="config.is_sandbox ? 'bg-amber-100 text-amber-700' : 'bg-blue-100 text-blue-700'">
                        {{ config.is_sandbox ? 'Sandbox' : 'Produccion' }}
                      </span>
                    </div>
                    <p class="text-sm text-gray-500">{{ config.service_type_label }}</p>
                  </div>
                </div>
                <div class="flex items-center gap-2">
                  <button
                    @click="testApiConfig(config)"
                    class="p-2 text-gray-400 hover:text-primary-600 hover:bg-primary-50 rounded-lg transition-colors"
                    title="Probar conexion"
                  >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                  </button>
                  <button
                    @click="openEditApiModal(config)"
                    class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition-colors"
                    title="Editar"
                  >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                  </button>
                  <button
                    @click="confirmDeleteApiConfig(config)"
                    class="p-2 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                    title="Eliminar"
                  >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                  </button>
                </div>
              </div>

              <!-- Status indicators -->
              <div class="mt-3 pt-3 border-t border-gray-100 flex items-center gap-4 text-xs">
                <span v-if="config.has_credentials" class="flex items-center gap-1 text-green-600">
                  <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                  </svg>
                  Credenciales configuradas
                </span>
                <span v-else class="flex items-center gap-1 text-amber-600">
                  <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                  </svg>
                  Sin credenciales
                </span>
                <span v-if="config.last_tested_at" class="text-gray-400">
                  Ultima prueba: {{ new Date(config.last_tested_at).toLocaleDateString() }}
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- API Config Modal -->
    <Teleport to="body">
      <div
        v-if="showApiModal"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50"
        @click.self="showApiModal = false"
      >
        <div class="bg-white rounded-xl shadow-xl w-full max-w-lg max-h-[90vh] overflow-hidden">
          <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
            <h3 class="text-lg font-semibold text-gray-900">
              {{ editingApiConfig ? 'Editar Integracion' : 'Nueva Integracion' }}
            </h3>
            <button
              @click="showApiModal = false"
              class="p-2 text-gray-400 hover:text-gray-600 rounded-lg transition-colors"
            >
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <div class="p-6 space-y-4 overflow-y-auto max-h-[60vh]">
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Proveedor</label>
                <select
                  v-model="apiForm.provider"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                  :disabled="!!editingApiConfig"
                >
                  <option value="">Seleccionar...</option>
                  <option v-for="(label, key) in availableProviders" :key="key" :value="key">
                    {{ label }}
                  </option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Tipo de servicio</label>
                <select
                  v-model="apiForm.service_type"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                  :disabled="!!editingApiConfig"
                >
                  <option value="">Seleccionar...</option>
                  <option v-for="(label, key) in availableServiceTypes" :key="key" :value="key">
                    {{ label }}
                  </option>
                </select>
              </div>
            </div>

            <!-- Dynamic fields based on provider -->
            <div v-if="apiForm.provider" class="space-y-4">
              <div v-if="getProviderFields(apiForm.provider).includes('api_key')">
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ getProviderLabels(apiForm.provider).api_key }}</label>
                <div class="relative">
                  <input
                    v-model="apiForm.api_key"
                    :type="showApiKey ? 'text' : 'password'"
                    class="w-full px-3 py-2 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                    :placeholder="editingApiConfig ? '(sin cambios)' : apiForm.provider === 'nubarium' ? 'ej: nubarium' : 'sk-...'"
                  />
                  <button
                    type="button"
                    @click="showApiKey = !showApiKey"
                    class="absolute right-2 top-1/2 -translate-y-1/2 p-1 text-gray-400 hover:text-gray-600"
                  >
                    <svg v-if="!showApiKey" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                    <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                    </svg>
                  </button>
                </div>
                <p v-if="getProviderHelpText(apiForm.provider).api_key" class="mt-1 text-xs text-gray-500">
                  {{ getProviderHelpText(apiForm.provider).api_key }}
                </p>
              </div>

              <div v-if="getProviderFields(apiForm.provider).includes('api_secret')">
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ getProviderLabels(apiForm.provider).api_secret }}</label>
                <div class="relative">
                  <input
                    v-model="apiForm.api_secret"
                    :type="showApiSecret ? 'text' : 'password'"
                    class="w-full px-3 py-2 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                    :placeholder="editingApiConfig ? '(sin cambios)' : '...'"
                  />
                  <button
                    type="button"
                    @click="showApiSecret = !showApiSecret"
                    class="absolute right-2 top-1/2 -translate-y-1/2 p-1 text-gray-400 hover:text-gray-600"
                  >
                    <svg v-if="!showApiSecret" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                    <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                    </svg>
                  </button>
                </div>
                <p v-if="getProviderHelpText(apiForm.provider).api_secret" class="mt-1 text-xs text-gray-500">
                  {{ getProviderHelpText(apiForm.provider).api_secret }}
                </p>
              </div>

              <div v-if="getProviderFields(apiForm.provider).includes('account_sid')">
                <label class="block text-sm font-medium text-gray-700 mb-1">Account SID</label>
                <input
                  v-model="apiForm.account_sid"
                  type="text"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                  placeholder="AC..."
                />
              </div>

              <div v-if="getProviderFields(apiForm.provider).includes('auth_token')">
                <label class="block text-sm font-medium text-gray-700 mb-1">Auth Token</label>
                <input
                  v-model="apiForm.auth_token"
                  type="password"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                  :placeholder="editingApiConfig ? '(sin cambios)' : '...'"
                />
              </div>

              <div v-if="getProviderFields(apiForm.provider).includes('from_number')">
                <label class="block text-sm font-medium text-gray-700 mb-1">Numero de origen</label>
                <input
                  v-model="apiForm.from_number"
                  type="tel"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                  placeholder="+521234567890"
                />
              </div>

              <div v-if="getProviderFields(apiForm.provider).includes('from_email')">
                <label class="block text-sm font-medium text-gray-700 mb-1">Email de origen</label>
                <input
                  v-model="apiForm.from_email"
                  type="email"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                  placeholder="noreply@miempresa.com"
                />
              </div>

              <div v-if="getProviderFields(apiForm.provider).includes('domain')">
                <label class="block text-sm font-medium text-gray-700 mb-1">Dominio</label>
                <input
                  v-model="apiForm.domain"
                  type="text"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                  placeholder="mg.miempresa.com"
                />
              </div>
            </div>

            <div class="flex items-center gap-6 pt-2">
              <label class="flex items-center gap-2 cursor-pointer">
                <input
                  type="checkbox"
                  v-model="apiForm.is_active"
                  class="w-4 h-4 text-primary-600 border-gray-300 rounded focus:ring-primary-500"
                />
                <span class="text-sm text-gray-700">Activo</span>
              </label>
              <label class="flex items-center gap-2 cursor-pointer">
                <input
                  type="checkbox"
                  v-model="apiForm.is_sandbox"
                  class="w-4 h-4 text-primary-600 border-gray-300 rounded focus:ring-primary-500"
                />
                <span class="text-sm text-gray-700">Modo Sandbox</span>
              </label>
            </div>
          </div>

          <div class="px-6 py-4 border-t border-gray-200 flex justify-end gap-3">
            <button
              @click="showApiModal = false"
              class="px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors font-medium text-sm"
            >
              Cancelar
            </button>
            <button
              @click="saveApiConfig"
              :disabled="!apiForm.provider || !apiForm.service_type || isSaving"
              class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors font-medium text-sm disabled:opacity-50 disabled:cursor-not-allowed"
            >
              {{ isSaving ? 'Guardando...' : 'Guardar' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Delete API Config Confirmation Modal -->
    <AppConfirmModal
      :show="showDeleteApiModal"
      title="Eliminar Configuración"
      :message="`¿Estás seguro de eliminar esta configuración de API? Esta acción no se puede deshacer.`"
      confirm-text="Eliminar"
      variant="danger"
      icon="danger"
      @confirm="deleteApiConfig"
      @update:show="showDeleteApiModal = $event"
    />
  </div>
</template>
