<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { api } from '@/services/api'
import { AppButton, AppInput } from '@/components/common'

interface TenantInfo {
  id: string
  name: string
  slug: string
  legal_name: string | null
  rfc: string | null
  email: string | null
  phone: string | null
  website: string | null
}

interface Branding {
  primary_color: string
  secondary_color: string
  accent_color: string
  background_color: string
  text_color: string
  logo_url: string | null
  logo_dark_url: string | null
  favicon_url: string | null
  login_background_url: string | null
  font_family: string
  heading_font_family: string | null
  border_radius: string
  button_style: string
  custom_css: string | null
}

interface ApiConfig {
  id: string
  provider: string
  provider_label: string
  service_type: string
  service_type_label: string
  from_number: string | null
  from_email: string | null
  domain: string | null
  is_active: boolean
  is_sandbox: boolean
  has_credentials: boolean
  masked_credentials: Record<string, string | null>
  last_tested_at: string | null
  last_test_success: boolean | null
  last_test_error: string | null
}

// State
const isLoading = ref(true)
const error = ref('')
const activeTab = ref<'general' | 'branding' | 'apis'>('general')

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

// Load data
const loadConfig = async () => {
  isLoading.value = true
  error.value = ''

  try {
    const response = await api.get<{
      data: {
        tenant: TenantInfo
        branding: Branding
        api_configs: ApiConfig[]
        available_providers: Record<string, string>
        available_service_types: Record<string, string>
      }
    }>('/admin/config')

    tenant.value = response.data.data.tenant
    branding.value = response.data.data.branding
    apiConfigs.value = response.data.data.api_configs
    availableProviders.value = response.data.data.available_providers
    availableServiceTypes.value = response.data.data.available_service_types
  } catch (e) {
    console.error('Failed to load config:', e)
    error.value = 'Error al cargar la configuración'
  } finally {
    isLoading.value = false
  }
}

onMounted(loadConfig)

// Save tenant info
const saveTenant = async () => {
  if (!tenant.value) return

  isSaving.value = true
  saveMessage.value = ''
  saveError.value = ''

  try {
    await api.put('/admin/config/tenant', {
      name: tenant.value.name,
      legal_name: tenant.value.legal_name || null,
      rfc: tenant.value.rfc || null,
      email: tenant.value.email || null,
      phone: tenant.value.phone || null,
      website: tenant.value.website || null
    })
    saveMessage.value = 'Información guardada'
    setTimeout(() => saveMessage.value = '', 3000)
  } catch (e) {
    saveError.value = 'Error al guardar'
  } finally {
    isSaving.value = false
  }
}

// Save branding
const saveBranding = async () => {
  if (!branding.value) return

  isSaving.value = true
  saveMessage.value = ''
  saveError.value = ''

  try {
    await api.put('/admin/config/branding', branding.value)
    saveMessage.value = 'Branding guardado'
    setTimeout(() => saveMessage.value = '', 3000)
  } catch (e) {
    saveError.value = 'Error al guardar'
  } finally {
    isSaving.value = false
  }
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
  showApiModal.value = true
}

const saveApiConfig = async () => {
  isSaving.value = true
  saveError.value = ''

  try {
    // Only send credentials if they were filled
    const payload: Record<string, unknown> = {
      provider: apiForm.value.provider,
      service_type: apiForm.value.service_type,
      from_number: apiForm.value.from_number || null,
      from_email: apiForm.value.from_email || null,
      domain: apiForm.value.domain || null,
      is_active: apiForm.value.is_active,
      is_sandbox: apiForm.value.is_sandbox
    }

    if (apiForm.value.api_key) payload.api_key = apiForm.value.api_key
    if (apiForm.value.api_secret) payload.api_secret = apiForm.value.api_secret
    if (apiForm.value.account_sid) payload.account_sid = apiForm.value.account_sid
    if (apiForm.value.auth_token) payload.auth_token = apiForm.value.auth_token

    await api.post('/admin/config/api-configs', payload)
    showApiModal.value = false
    await loadConfig()
    saveMessage.value = 'Configuración guardada'
    setTimeout(() => saveMessage.value = '', 3000)
  } catch (e) {
    saveError.value = 'Error al guardar'
  } finally {
    isSaving.value = false
  }
}

const testApiConfig = async (config: ApiConfig) => {
  try {
    const response = await api.post<{ success: boolean; message: string }>(`/admin/config/api-configs/${config.id}/test`)
    await loadConfig()
    alert(response.data.message)
  } catch (e) {
    alert('Error al probar la conexión')
  }
}

const deleteApiConfig = async (config: ApiConfig) => {
  if (!confirm(`¿Eliminar la configuración de ${config.provider_label}?`)) return

  try {
    await api.delete(`/admin/config/api-configs/${config.id}`)
    await loadConfig()
  } catch (e) {
    alert('Error al eliminar')
  }
}

// Get fields needed for each provider
const providerFields = computed(() => {
  const provider = apiForm.value.provider
  switch (provider) {
    case 'twilio':
      return ['account_sid', 'auth_token', 'from_number']
    case 'mailgun':
      return ['api_key', 'domain', 'from_email']
    case 'sendgrid':
      return ['api_key', 'from_email']
    case 'nubarium':
    case 'mati':
    case 'onfido':
    case 'jumio':
      return ['api_key', 'api_secret']
    case 'circulo_credito':
      return ['api_key', 'api_secret']
    default:
      return ['api_key']
  }
})
</script>

<template>
  <div>
    <!-- Header -->
    <div class="mb-6">
      <h1 class="text-2xl font-bold text-gray-900">Configuración</h1>
      <p class="text-gray-500">Configura tu empresa, branding e integraciones</p>
    </div>

    <!-- Loading -->
    <div v-if="isLoading" class="flex justify-center py-12">
      <div class="animate-spin w-8 h-8 border-4 border-primary-500 border-t-transparent rounded-full"></div>
    </div>

    <!-- Error -->
    <div v-else-if="error" class="bg-red-50 border border-red-200 rounded-xl p-6 text-center">
      <p class="text-red-600">{{ error }}</p>
      <AppButton variant="outline" size="sm" class="mt-4" @click="loadConfig">Reintentar</AppButton>
    </div>

    <!-- Content -->
    <div v-else>
      <!-- Tabs -->
      <div class="border-b border-gray-200 mb-6">
        <div class="flex gap-8">
          <button
            v-for="tab in [
              { id: 'general', label: 'Información General', icon: 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16' },
              { id: 'branding', label: 'Branding', icon: 'M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01' },
              { id: 'apis', label: 'Integraciones API', icon: 'M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4' }
            ]"
            :key="tab.id"
            @click="activeTab = tab.id as typeof activeTab"
            :class="[
              'flex items-center gap-2 py-3 px-1 border-b-2 text-sm font-medium transition-colors',
              activeTab === tab.id
                ? 'border-primary-500 text-primary-600'
                : 'border-transparent text-gray-500 hover:text-gray-700'
            ]"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="tab.icon" />
            </svg>
            {{ tab.label }}
          </button>
        </div>
      </div>

      <!-- Success/Error Messages -->
      <div v-if="saveMessage" class="mb-4 p-3 bg-green-50 border border-green-200 rounded-lg text-green-700 text-sm">
        {{ saveMessage }}
      </div>
      <div v-if="saveError" class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-red-700 text-sm">
        {{ saveError }}
      </div>

      <!-- General Tab -->
      <div v-if="activeTab === 'general' && tenant" class="bg-white rounded-xl shadow-sm p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Información de la Empresa</h2>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Nombre</label>
            <AppInput v-model="tenant.name" />
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Subdominio</label>
            <div class="flex items-center">
              <span class="text-gray-500 mr-2">{{ tenant.slug }}</span>
              <span class="text-gray-400">.losapp.com</span>
            </div>
          </div>

          <div class="md:col-span-2">
            <label class="block text-sm font-medium text-gray-700 mb-1">Razón Social</label>
            <AppInput v-model="tenant.legal_name" placeholder="Empresa S.A. de C.V. SOFOM E.N.R." />
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">RFC</label>
            <AppInput v-model="tenant.rfc" placeholder="ABC123456789" :maxlength="13" />
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Teléfono</label>
            <AppInput v-model="tenant.phone" placeholder="5555555555" />
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
            <AppInput v-model="tenant.email" type="email" placeholder="contacto@empresa.mx" />
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Sitio Web</label>
            <AppInput v-model="tenant.website" placeholder="https://empresa.mx" />
          </div>
        </div>

        <div class="mt-6 flex justify-end">
          <AppButton variant="primary" @click="saveTenant" :loading="isSaving">
            Guardar Cambios
          </AppButton>
        </div>
      </div>

      <!-- Branding Tab -->
      <div v-if="activeTab === 'branding' && branding" class="bg-white rounded-xl shadow-sm p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Personalización Visual</h2>

        <!-- Colors -->
        <div class="mb-8">
          <h3 class="text-sm font-medium text-gray-700 mb-3">Colores</h3>
          <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
            <div v-for="color in [
              { key: 'primary_color', label: 'Primario' },
              { key: 'secondary_color', label: 'Secundario' },
              { key: 'accent_color', label: 'Acento' },
              { key: 'background_color', label: 'Fondo' },
              { key: 'text_color', label: 'Texto' }
            ]" :key="color.key">
              <label class="block text-xs text-gray-500 mb-1">{{ color.label }}</label>
              <div class="flex items-center gap-2">
                <input
                  type="color"
                  v-model="(branding as Record<string, string>)[color.key]"
                  class="w-10 h-10 rounded border border-gray-300 cursor-pointer"
                />
                <input
                  type="text"
                  v-model="(branding as Record<string, string>)[color.key]"
                  class="flex-1 px-2 py-1 text-xs border border-gray-300 rounded"
                />
              </div>
            </div>
          </div>
        </div>

        <!-- Logos -->
        <div class="mb-8">
          <h3 class="text-sm font-medium text-gray-700 mb-3">Logos e Imágenes</h3>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs text-gray-500 mb-1">Logo Principal (URL)</label>
              <AppInput v-model="branding.logo_url" placeholder="/images/logo.svg" />
            </div>
            <div>
              <label class="block text-xs text-gray-500 mb-1">Logo Oscuro (URL)</label>
              <AppInput v-model="branding.logo_dark_url" placeholder="/images/logo-dark.svg" />
            </div>
            <div>
              <label class="block text-xs text-gray-500 mb-1">Favicon (URL)</label>
              <AppInput v-model="branding.favicon_url" placeholder="/favicon.ico" />
            </div>
            <div>
              <label class="block text-xs text-gray-500 mb-1">Fondo de Login (URL)</label>
              <AppInput v-model="branding.login_background_url" placeholder="/images/login-bg.jpg" />
            </div>
          </div>
        </div>

        <!-- Typography & Style -->
        <div class="mb-8">
          <h3 class="text-sm font-medium text-gray-700 mb-3">Tipografía y Estilo</h3>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
              <label class="block text-xs text-gray-500 mb-1">Fuente Principal</label>
              <AppInput v-model="branding.font_family" placeholder="Inter, sans-serif" />
            </div>
            <div>
              <label class="block text-xs text-gray-500 mb-1">Radio de Bordes</label>
              <AppInput v-model="branding.border_radius" placeholder="12px" />
            </div>
            <div>
              <label class="block text-xs text-gray-500 mb-1">Estilo de Botones</label>
              <select
                v-model="branding.button_style"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg"
              >
                <option value="rounded">Redondeado</option>
                <option value="pill">Pill</option>
                <option value="square">Cuadrado</option>
              </select>
            </div>
          </div>
        </div>

        <!-- Preview -->
        <div class="mb-6 p-4 bg-gray-50 rounded-lg">
          <p class="text-sm font-medium text-gray-700 mb-3">Vista Previa</p>
          <div class="flex gap-3">
            <button
              class="px-4 py-2 text-white text-sm font-medium"
              :style="{
                backgroundColor: branding.primary_color,
                borderRadius: branding.border_radius
              }"
            >
              Primario
            </button>
            <button
              class="px-4 py-2 text-white text-sm font-medium"
              :style="{
                backgroundColor: branding.secondary_color,
                borderRadius: branding.border_radius
              }"
            >
              Secundario
            </button>
            <button
              class="px-4 py-2 text-white text-sm font-medium"
              :style="{
                backgroundColor: branding.accent_color,
                borderRadius: branding.border_radius
              }"
            >
              Acento
            </button>
          </div>
        </div>

        <div class="flex justify-end">
          <AppButton variant="primary" @click="saveBranding" :loading="isSaving">
            Guardar Branding
          </AppButton>
        </div>
      </div>

      <!-- APIs Tab -->
      <div v-if="activeTab === 'apis'" class="space-y-6">
        <div class="flex items-center justify-between">
          <h2 class="text-lg font-semibold text-gray-900">Integraciones API</h2>
          <AppButton variant="primary" @click="openAddApiModal">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            Agregar Integración
          </AppButton>
        </div>

        <!-- API Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div
            v-for="config in apiConfigs"
            :key="config.id"
            class="bg-white rounded-xl shadow-sm p-5 border-l-4"
            :class="config.is_active ? 'border-green-500' : 'border-gray-300'"
          >
            <div class="flex items-start justify-between mb-3">
              <div>
                <h3 class="font-medium text-gray-900">{{ config.provider_label }}</h3>
                <p class="text-sm text-gray-500">{{ config.service_type_label }}</p>
              </div>
              <div class="flex gap-2">
                <span
                  :class="[
                    'px-2 py-0.5 rounded-full text-xs font-medium',
                    config.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600'
                  ]"
                >
                  {{ config.is_active ? 'Activo' : 'Inactivo' }}
                </span>
                <span
                  v-if="config.is_sandbox"
                  class="px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800"
                >
                  Sandbox
                </span>
              </div>
            </div>

            <div class="text-sm text-gray-600 mb-3">
              <div v-if="config.from_number">Tel: {{ config.from_number }}</div>
              <div v-if="config.from_email">Email: {{ config.from_email }}</div>
              <div v-if="config.domain">Dominio: {{ config.domain }}</div>
              <div class="mt-2" :class="config.has_credentials ? 'text-green-600' : 'text-red-600'">
                {{ config.has_credentials ? '✓ Credenciales configuradas' : '✗ Sin credenciales' }}
              </div>
            </div>

            <div v-if="config.last_tested_at" class="text-xs text-gray-400 mb-3">
              Última prueba: {{ new Date(config.last_tested_at).toLocaleString('es-MX') }}
              <span :class="config.last_test_success ? 'text-green-600' : 'text-red-600'">
                {{ config.last_test_success ? '(Exitosa)' : '(Fallida)' }}
              </span>
            </div>

            <div class="flex gap-2">
              <button
                @click="openEditApiModal(config)"
                class="text-sm text-primary-600 hover:text-primary-800"
              >
                Editar
              </button>
              <button
                @click="testApiConfig(config)"
                class="text-sm text-blue-600 hover:text-blue-800"
              >
                Probar
              </button>
              <button
                @click="deleteApiConfig(config)"
                class="text-sm text-red-600 hover:text-red-800"
              >
                Eliminar
              </button>
            </div>
          </div>
        </div>

        <!-- Empty state -->
        <div v-if="apiConfigs.length === 0" class="bg-white rounded-xl p-12 text-center">
          <svg class="w-16 h-16 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" />
          </svg>
          <h3 class="text-lg font-medium text-gray-900 mb-2">Sin integraciones</h3>
          <p class="text-gray-500 mb-4">Configura tus proveedores de SMS, Email, KYC y más</p>
          <AppButton variant="primary" @click="openAddApiModal">Agregar Integración</AppButton>
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
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg">
          <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
            <h2 class="text-lg font-semibold text-gray-900">
              {{ editingApiConfig ? 'Editar Integración' : 'Nueva Integración' }}
            </h2>
            <button @click="showApiModal = false" class="text-gray-400 hover:text-gray-600">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <div class="px-6 py-5 space-y-4">
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Proveedor *</label>
                <select
                  v-model="apiForm.provider"
                  :disabled="!!editingApiConfig"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg"
                >
                  <option value="">Seleccionar...</option>
                  <option v-for="(label, key) in availableProviders" :key="key" :value="key">
                    {{ label }}
                  </option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Tipo de Servicio *</label>
                <select
                  v-model="apiForm.service_type"
                  :disabled="!!editingApiConfig"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg"
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
              <div v-if="providerFields.includes('account_sid')">
                <label class="block text-sm font-medium text-gray-700 mb-1">Account SID</label>
                <AppInput v-model="apiForm.account_sid" :placeholder="editingApiConfig ? '(sin cambios)' : ''" />
              </div>

              <div v-if="providerFields.includes('auth_token')">
                <label class="block text-sm font-medium text-gray-700 mb-1">Auth Token</label>
                <AppInput v-model="apiForm.auth_token" type="password" :placeholder="editingApiConfig ? '(sin cambios)' : ''" />
              </div>

              <div v-if="providerFields.includes('api_key')">
                <label class="block text-sm font-medium text-gray-700 mb-1">API Key</label>
                <AppInput v-model="apiForm.api_key" :placeholder="editingApiConfig ? '(sin cambios)' : ''" />
              </div>

              <div v-if="providerFields.includes('api_secret')">
                <label class="block text-sm font-medium text-gray-700 mb-1">API Secret</label>
                <AppInput v-model="apiForm.api_secret" type="password" :placeholder="editingApiConfig ? '(sin cambios)' : ''" />
              </div>

              <div v-if="providerFields.includes('from_number')">
                <label class="block text-sm font-medium text-gray-700 mb-1">Número de Origen</label>
                <AppInput v-model="apiForm.from_number" placeholder="+521234567890" />
              </div>

              <div v-if="providerFields.includes('from_email')">
                <label class="block text-sm font-medium text-gray-700 mb-1">Email de Origen</label>
                <AppInput v-model="apiForm.from_email" placeholder="noreply@empresa.mx" />
              </div>

              <div v-if="providerFields.includes('domain')">
                <label class="block text-sm font-medium text-gray-700 mb-1">Dominio</label>
                <AppInput v-model="apiForm.domain" placeholder="mg.empresa.mx" />
              </div>
            </div>

            <div class="flex gap-4 pt-2">
              <label class="flex items-center gap-2">
                <input type="checkbox" v-model="apiForm.is_active" class="w-4 h-4 text-primary-600 rounded" />
                <span class="text-sm text-gray-700">Activo</span>
              </label>
              <label class="flex items-center gap-2">
                <input type="checkbox" v-model="apiForm.is_sandbox" class="w-4 h-4 text-primary-600 rounded" />
                <span class="text-sm text-gray-700">Modo Sandbox</span>
              </label>
            </div>

            <div v-if="saveError" class="p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-600">
              {{ saveError }}
            </div>
          </div>

          <div class="px-6 py-4 border-t border-gray-200 flex justify-end gap-3">
            <AppButton variant="outline" @click="showApiModal = false">Cancelar</AppButton>
            <AppButton
              variant="primary"
              @click="saveApiConfig"
              :loading="isSaving"
              :disabled="!apiForm.provider || !apiForm.service_type"
            >
              Guardar
            </AppButton>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>
