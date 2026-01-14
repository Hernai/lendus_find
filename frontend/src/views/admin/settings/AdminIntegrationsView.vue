<template>
  <div class="min-h-screen bg-gray-50 py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <!-- Header -->
      <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Integraciones</h1>
        <p class="mt-1 text-sm text-gray-500">Configura las integraciones con servicios externos (Twilio, Email, KYC, etc.)</p>
      </div>

      <!-- Loading State -->
      <div v-if="isLoading" class="flex justify-center py-12">
        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-primary-600"></div>
      </div>

      <!-- Error State -->
      <div v-else-if="error" class="bg-red-50 border border-red-200 rounded-lg p-4">
        <p class="text-red-800">{{ error }}</p>
      </div>

      <!-- Integrations List -->
      <div v-else>
        <!-- Add New Integration Button -->
        <div class="mb-6 flex justify-end">
          <button
            @click="openNewIntegrationModal"
            class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors flex items-center gap-2"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            Nueva Integración
          </button>
        </div>

        <!-- Integrations Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          <div
            v-for="integration in integrations"
            :key="integration.id"
            class="bg-white rounded-lg shadow border border-gray-200 p-6"
          >
            <!-- Header with Status -->
            <div class="flex items-start justify-between mb-4">
              <div class="flex-1">
                <h3 class="text-lg font-semibold text-gray-900">{{ integration.provider_label }}</h3>
                <p class="text-sm text-gray-500">{{ integration.service_type_label }}</p>
              </div>
              <span
                :class="[
                  'px-2 py-1 text-xs font-medium rounded-full',
                  integration.is_active
                    ? 'bg-green-100 text-green-800'
                    : 'bg-gray-100 text-gray-800'
                ]"
              >
                {{ integration.is_active ? 'Activo' : 'Inactivo' }}
              </span>
            </div>

            <!-- Configuration Details -->
            <div class="space-y-2 mb-4">
              <div v-if="integration.from_number" class="text-sm">
                <span class="text-gray-500">Número:</span>
                <span class="ml-2 font-mono text-gray-900">{{ integration.from_number }}</span>
              </div>
              <div v-if="integration.from_email" class="text-sm">
                <span class="text-gray-500">Email:</span>
                <span class="ml-2 font-mono text-gray-900">{{ integration.from_email }}</span>
              </div>
              <div v-if="integration.masked_credentials.account_sid" class="text-sm">
                <span class="text-gray-500">Account SID:</span>
                <span class="ml-2 font-mono text-gray-600">{{ integration.masked_credentials.account_sid }}</span>
              </div>
              <div v-if="integration.is_sandbox" class="text-sm">
                <span class="px-2 py-0.5 bg-yellow-100 text-yellow-800 text-xs rounded">Modo Sandbox</span>
              </div>
            </div>

            <!-- Last Test Result -->
            <div v-if="integration.last_tested_at" class="mb-4 p-3 rounded-lg" :class="integration.last_test_success ? 'bg-green-50' : 'bg-red-50'">
              <div class="flex items-center gap-2 text-sm">
                <svg v-if="integration.last_test_success" class="w-4 h-4 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <svg v-else class="w-4 h-4 text-red-600" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                </svg>
                <span :class="integration.last_test_success ? 'text-green-800' : 'text-red-800'">
                  {{ integration.last_test_success ? 'Última prueba exitosa' : 'Última prueba falló' }}
                </span>
              </div>
              <p v-if="!integration.last_test_success && integration.last_test_error" class="text-xs text-red-700 mt-1 truncate">
                {{ integration.last_test_error }}
              </p>
            </div>

            <!-- Actions -->
            <div class="space-y-2">
              <!-- Quick Test Button (Prominent) -->
              <button
                v-if="['sms', 'whatsapp'].includes(integration.service_type)"
                @click="openQuickTestModal(integration)"
                class="w-full px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors flex items-center justify-center gap-2 font-medium"
                title="Probar conexión"
              >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                </svg>
                Probar Conexión
              </button>

              <!-- Secondary Actions -->
              <div class="flex gap-2">
                <button
                  @click="openEditModal(integration)"
                  class="flex-1 px-3 py-2 text-sm text-primary-600 hover:bg-primary-50 rounded-lg transition-colors border border-primary-200"
                >
                  Editar
                </button>
                <button
                  @click="deleteIntegration(integration)"
                  class="px-3 py-2 text-sm text-red-600 hover:bg-red-50 rounded-lg transition-colors border border-red-200"
                >
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                  </svg>
                </button>
              </div>
            </div>
          </div>

          <!-- Empty State -->
          <div v-if="integrations.length === 0" class="col-span-full text-center py-12">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
            </svg>
            <h3 class="mt-2 text-sm font-medium text-gray-900">No hay integraciones configuradas</h3>
            <p class="mt-1 text-sm text-gray-500">Comienza agregando una nueva integración.</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Edit/Create Integration Modal -->
    <div v-if="showEditModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
        <div class="sticky top-0 bg-white border-b border-gray-200 px-6 py-4 flex items-center justify-between">
          <h2 class="text-xl font-bold text-gray-900">
            {{ editingIntegration ? 'Editar Integración' : 'Nueva Integración' }}
          </h2>
          <button @click="closeEditModal" class="text-gray-400 hover:text-gray-600">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <form @submit.prevent="saveIntegration" class="p-6 space-y-4">
          <!-- Provider and Service Type -->
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Proveedor *</label>
              <select
                v-model="form.provider"
                required
                :disabled="editingIntegration"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 disabled:bg-gray-100"
              >
                <option value="">Seleccionar...</option>
                <option v-for="(label, key) in providers" :key="key" :value="key">{{ label }}</option>
              </select>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Tipo de Servicio *</label>
              <select
                v-model="form.service_type"
                required
                :disabled="editingIntegration"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 disabled:bg-gray-100"
              >
                <option value="">Seleccionar...</option>
                <option v-for="(label, key) in serviceTypes" :key="key" :value="key">{{ label }}</option>
              </select>
            </div>
          </div>

          <!-- Twilio Specific Fields -->
          <div v-if="form.provider === 'twilio'" class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Account SID *</label>
              <input
                v-model="form.account_sid"
                type="text"
                required
                placeholder="ACXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
              />
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Auth Token *</label>
              <input
                v-model="form.auth_token"
                type="password"
                required
                placeholder="••••••••••••••••••••••••••••••••"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
              />
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Número de Origen *</label>
              <input
                v-model="form.from_number"
                type="text"
                required
                placeholder="+521234567890"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
              />
              <p class="mt-1 text-xs text-gray-500">Formato E.164 (ej: +521234567890)</p>
            </div>
          </div>

          <!-- Status Toggles -->
          <div class="flex items-center gap-6">
            <label class="flex items-center gap-2">
              <input
                v-model="form.is_active"
                type="checkbox"
                class="w-4 h-4 text-primary-600 border-gray-300 rounded focus:ring-primary-500"
              />
              <span class="text-sm text-gray-700">Activo</span>
            </label>

            <label class="flex items-center gap-2">
              <input
                v-model="form.is_sandbox"
                type="checkbox"
                class="w-4 h-4 text-primary-600 border-gray-300 rounded focus:ring-primary-500"
              />
              <span class="text-sm text-gray-700">Modo Sandbox/Pruebas</span>
            </label>
          </div>

          <!-- Actions -->
          <div class="flex gap-3 pt-4 border-t border-gray-200">
            <button
              type="button"
              @click="closeEditModal"
              class="flex-1 px-4 py-2 text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
            >
              Cancelar
            </button>
            <button
              type="submit"
              :disabled="isSaving"
              class="flex-1 px-4 py-2 text-white bg-primary-600 rounded-lg hover:bg-primary-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
            >
              {{ isSaving ? 'Guardando...' : 'Guardar Integración' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Quick Test Modal (Simplified) -->
    <div v-if="showTestModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
        <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center">
              <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
              </svg>
            </div>
            <div>
              <h2 class="text-xl font-bold text-gray-900">Prueba Rápida</h2>
              <p class="text-sm text-gray-500">{{ testingIntegration?.provider_label }} - {{ testingIntegration?.service_type_label }}</p>
            </div>
          </div>
          <button @click="closeTestModal" class="text-gray-400 hover:text-gray-600">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <form @submit.prevent="runTest" class="p-6 space-y-4">
          <!-- Info Alert -->
          <div class="bg-blue-50 border border-blue-200 rounded-lg p-3">
            <div class="flex items-start gap-2">
              <svg class="w-5 h-5 text-blue-600 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
              </svg>
              <p class="text-sm text-blue-800">
                Se enviará un mensaje de prueba al número que ingreses para verificar que la configuración funciona correctamente.
              </p>
            </div>
          </div>

          <!-- Phone Input -->
          <div v-if="testingIntegration?.service_type === 'sms' || testingIntegration?.service_type === 'whatsapp'">
            <label class="block text-sm font-medium text-gray-700 mb-2">Número de Celular *</label>
            <div class="relative">
              <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <span class="text-gray-500 text-sm">+52</span>
              </div>
              <input
                v-model="testForm.test_phone"
                type="text"
                required
                placeholder="9611838818"
                maxlength="10"
                pattern="[0-9]{10}"
                class="w-full pl-12 pr-3 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 text-lg font-mono"
              />
            </div>
            <p class="mt-2 text-xs text-gray-500">
              <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              10 dígitos sin código de país (ej: 9611838818)
            </p>
          </div>

          <!-- Test Result -->
          <div v-if="testResult" class="p-4 rounded-lg animate-fade-in" :class="testResult.success ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200'">
            <div class="flex items-start gap-3">
              <div v-if="testResult.success" class="w-8 h-8 bg-green-600 rounded-full flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                </svg>
              </div>
              <div v-else class="w-8 h-8 bg-red-600 rounded-full flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                </svg>
              </div>
              <div class="flex-1">
                <p :class="testResult.success ? 'text-green-900' : 'text-red-900'" class="font-semibold text-sm">
                  {{ testResult.message }}
                </p>
                <p v-if="testResult.error" class="text-xs text-red-700 mt-1">{{ testResult.error }}</p>
                <p v-if="testResult.success" class="text-xs text-green-700 mt-1">
                  El mensaje ha sido enviado exitosamente. Deberías recibirlo en unos segundos.
                </p>
              </div>
            </div>
          </div>

          <!-- Actions -->
          <div class="flex gap-3 pt-4">
            <button
              type="button"
              @click="closeTestModal"
              class="flex-1 px-4 py-3 text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors font-medium"
            >
              {{ testResult ? 'Cerrar' : 'Cancelar' }}
            </button>
            <button
              v-if="!testResult"
              type="submit"
              :disabled="isTesting"
              class="flex-1 px-4 py-3 text-white bg-green-600 rounded-lg hover:bg-green-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2 font-medium"
            >
              <svg v-if="isTesting" class="animate-spin h-5 w-5" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
              <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
              </svg>
              {{ isTesting ? 'Enviando...' : 'Enviar Mensaje' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { api } from '@/services/api'

interface Integration {
  id: string
  provider: string
  provider_label: string
  service_type: string
  service_type_label: string
  from_number?: string
  from_email?: string
  is_active: boolean
  is_sandbox: boolean
  has_credentials: boolean
  masked_credentials: {
    account_sid?: string
    auth_token?: string
    api_key?: string
    api_secret?: string
  }
  last_tested_at?: string
  last_test_success?: boolean
  last_test_error?: string
}

const integrations = ref<Integration[]>([])
const providers = ref<Record<string, string>>({})
const serviceTypes = ref<Record<string, string>>({})
const isLoading = ref(true)
const error = ref<string | null>(null)

// Edit Modal
const showEditModal = ref(false)
const editingIntegration = ref<Integration | null>(null)
const isSaving = ref(false)
const form = ref({
  provider: '',
  service_type: '',
  account_sid: '',
  auth_token: '',
  api_key: '',
  api_secret: '',
  from_number: '',
  from_email: '',
  is_active: true,
  is_sandbox: false,
})

// Test Modal
const showTestModal = ref(false)
const testingIntegration = ref<Integration | null>(null)
const isTesting = ref(false)
const testForm = ref({
  test_phone: '',
  test_email: '',
})
const testResult = ref<{ success: boolean; message: string; error?: string } | null>(null)

// Load integrations
const loadIntegrations = async () => {
  try {
    isLoading.value = true
    error.value = null
    const response = await api.get<{ data: Integration[] }>('/admin/integrations')
    integrations.value = response.data.data
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Error al cargar integraciones'
    console.error('Error loading integrations:', err)
  } finally {
    isLoading.value = false
  }
}

// Load options
const loadOptions = async () => {
  try {
    const response = await api.get<{ providers: Record<string, string>; service_types: Record<string, string> }>('/admin/integrations/options')
    providers.value = response.data.providers
    serviceTypes.value = response.data.service_types
  } catch (err) {
    console.error('Error loading options:', err)
  }
}

// Open new integration modal
const openNewIntegrationModal = () => {
  editingIntegration.value = null
  form.value = {
    provider: '',
    service_type: '',
    account_sid: '',
    auth_token: '',
    api_key: '',
    api_secret: '',
    from_number: '',
    from_email: '',
    is_active: true,
    is_sandbox: false,
  }
  showEditModal.value = true
}

// Open edit modal
const openEditModal = (integration: Integration) => {
  editingIntegration.value = integration
  form.value = {
    provider: integration.provider,
    service_type: integration.service_type,
    account_sid: '',
    auth_token: '',
    api_key: '',
    api_secret: '',
    from_number: integration.from_number || '',
    from_email: integration.from_email || '',
    is_active: integration.is_active,
    is_sandbox: integration.is_sandbox,
  }
  showEditModal.value = true
}

// Close edit modal
const closeEditModal = () => {
  showEditModal.value = false
  editingIntegration.value = null
}

// Save integration
const saveIntegration = async () => {
  try {
    isSaving.value = true
    await api.post('/admin/integrations', form.value)
    await loadIntegrations()
    closeEditModal()
  } catch (err: any) {
    alert(err.response?.data?.message || 'Error al guardar integración')
    console.error('Error saving integration:', err)
  } finally {
    isSaving.value = false
  }
}

// Open quick test modal
const openQuickTestModal = (integration: Integration) => {
  testingIntegration.value = integration
  testForm.value = {
    test_phone: '',
    test_email: '',
  }
  testResult.value = null
  showTestModal.value = true
}

// Open test modal (alias for compatibility)
const openTestModal = openQuickTestModal

// Close test modal
const closeTestModal = () => {
  showTestModal.value = false
  testingIntegration.value = null
  testResult.value = null
}

// Run test
const runTest = async () => {
  if (!testingIntegration.value) return

  try {
    isTesting.value = true
    testResult.value = null
    const response = await api.post<{ success: boolean; message: string; error?: string }>(
      `/admin/integrations/${testingIntegration.value.id}/test`,
      testForm.value
    )
    testResult.value = response.data
    await loadIntegrations() // Reload to show updated test status
  } catch (err: any) {
    testResult.value = {
      success: false,
      message: 'Error en la prueba',
      error: err.response?.data?.error || err.response?.data?.message || 'Error desconocido',
    }
    console.error('Error testing integration:', err)
  } finally {
    isTesting.value = false
  }
}

// Delete integration
const deleteIntegration = async (integration: Integration) => {
  if (!confirm(`¿Estás seguro de eliminar la integración de ${integration.provider_label} (${integration.service_type_label})?`)) {
    return
  }

  try {
    await api.delete(`/admin/integrations/${integration.id}`)
    await loadIntegrations()
  } catch (err: any) {
    alert(err.response?.data?.message || 'Error al eliminar integración')
    console.error('Error deleting integration:', err)
  }
}

onMounted(() => {
  loadOptions()
  loadIntegrations()
})
</script>
