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
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
          <div
            v-for="integration in integrations"
            :key="integration.id"
            class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden"
          >
            <!-- Card Header -->
            <div class="px-5 py-4 border-b border-gray-100 bg-gray-50/50">
              <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                  <!-- Provider Icon -->
                  <div class="w-10 h-10 rounded-lg bg-white border border-gray-200 flex items-center justify-center">
                    <span class="text-lg font-bold text-gray-600">{{ integration.provider_label.charAt(0) }}</span>
                  </div>
                  <div>
                    <h3 class="font-semibold text-gray-900">{{ integration.provider_label }}</h3>
                    <p class="text-xs text-gray-500">{{ integration.service_type_label }}</p>
                  </div>
                </div>
                <span
                  :class="[
                    'px-2.5 py-1 text-xs font-medium rounded-full',
                    integration.is_active
                      ? 'bg-green-100 text-green-700'
                      : 'bg-gray-100 text-gray-500'
                  ]"
                >
                  {{ integration.is_active ? 'Activo' : 'Inactivo' }}
                </span>
              </div>
            </div>

            <!-- Card Body -->
            <div class="px-5 py-4">
              <!-- Configuration Details -->
              <div class="space-y-2 text-sm">
                <!-- Twilio specific -->
                <template v-if="integration.provider === 'twilio'">
                  <div v-if="integration.from_number" class="flex justify-between">
                    <span class="text-gray-500">Número</span>
                    <span class="font-mono text-gray-900">{{ integration.from_number }}</span>
                  </div>
                  <div v-if="integration.masked_credentials.account_sid" class="flex justify-between">
                    <span class="text-gray-500">Account SID</span>
                    <span class="font-mono text-gray-600 text-xs">{{ integration.masked_credentials.account_sid }}</span>
                  </div>
                </template>

                <!-- Nubarium specific -->
                <template v-else-if="integration.provider === 'nubarium'">
                  <div v-if="integration.masked_credentials.api_key" class="flex justify-between">
                    <span class="text-gray-500">API Key</span>
                    <span class="font-mono text-gray-600 text-xs">{{ integration.masked_credentials.api_key }}</span>
                  </div>
                  <div v-if="integration.masked_credentials.api_secret" class="flex justify-between">
                    <span class="text-gray-500">API Secret</span>
                    <span class="font-mono text-gray-600 text-xs">{{ integration.masked_credentials.api_secret }}</span>
                  </div>
                </template>

                <!-- Email providers -->
                <template v-else-if="integration.service_type === 'email'">
                  <div v-if="integration.from_email" class="flex justify-between">
                    <span class="text-gray-500">Email</span>
                    <span class="font-mono text-gray-900 text-xs">{{ integration.from_email }}</span>
                  </div>
                  <div v-if="integration.domain" class="flex justify-between">
                    <span class="text-gray-500">Dominio</span>
                    <span class="font-mono text-gray-900 text-xs">{{ integration.domain }}</span>
                  </div>
                </template>

                <!-- Generic fallback -->
                <template v-else>
                  <div v-if="integration.masked_credentials.api_key" class="flex justify-between">
                    <span class="text-gray-500">API Key</span>
                    <span class="font-mono text-gray-600 text-xs">{{ integration.masked_credentials.api_key }}</span>
                  </div>
                </template>

                <!-- Sandbox badge -->
                <div v-if="integration.is_sandbox" class="pt-1">
                  <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-yellow-50 text-yellow-700 text-xs rounded border border-yellow-200">
                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                      <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                    </svg>
                    Sandbox
                  </span>
                </div>
              </div>

              <!-- Last Test Result -->
              <div v-if="integration.last_tested_at" class="mt-4 p-3 rounded-lg" :class="integration.last_test_success ? 'bg-green-50 border border-green-100' : 'bg-red-50 border border-red-100'">
                <div class="flex items-center gap-2 text-sm">
                  <svg v-if="integration.last_test_success" class="w-4 h-4 text-green-600 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                  </svg>
                  <svg v-else class="w-4 h-4 text-red-600 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                  </svg>
                  <span :class="integration.last_test_success ? 'text-green-700' : 'text-red-700'" class="font-medium">
                    {{ integration.last_test_success ? 'Conexión verificada' : 'Error de conexión' }}
                  </span>
                </div>
                <p v-if="!integration.last_test_success && integration.last_test_error" class="text-xs text-red-600 mt-1 truncate">
                  {{ integration.last_test_error }}
                </p>
              </div>
            </div>

            <!-- Card Footer / Actions -->
            <div class="px-5 py-4 bg-gray-50/50 border-t border-gray-100 space-y-2">
              <!-- Primary Actions Row -->
              <div class="flex gap-2">
                <button
                  v-if="['sms', 'whatsapp', 'kyc'].includes(integration.service_type)"
                  @click="openQuickTestModal(integration)"
                  class="flex-1 px-3 py-2 bg-primary-600 text-white text-sm rounded-lg hover:bg-primary-700 transition-colors flex items-center justify-center gap-1.5 font-medium"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                  Probar
                </button>
                <button
                  @click="toggleIntegrationStatus(integration)"
                  :disabled="isTogglingStatus === integration.id"
                  :class="[
                    'flex-1 px-3 py-2 text-sm rounded-lg font-medium transition-colors flex items-center justify-center gap-1.5',
                    integration.is_active
                      ? 'bg-amber-100 text-amber-700 hover:bg-amber-200'
                      : 'bg-emerald-100 text-emerald-700 hover:bg-emerald-200'
                  ]"
                >
                  <svg v-if="isTogglingStatus === integration.id" class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                  </svg>
                  <template v-else>
                    <svg v-if="integration.is_active" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                  </template>
                  {{ integration.is_active ? 'Pausar' : 'Activar' }}
                </button>
              </div>

              <!-- Secondary Actions Row -->
              <div class="flex gap-2">
                <button
                  @click="openEditModal(integration)"
                  class="flex-1 px-3 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg transition-colors flex items-center justify-center gap-1.5"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                  </svg>
                  Editar
                </button>
                <button
                  @click="confirmDeleteIntegration(integration)"
                  class="px-3 py-2 text-sm text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                  title="Eliminar"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                :disabled="!!editingIntegration"
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
                :disabled="!!editingIntegration"
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
                :required="!editingIntegration"
                placeholder="ACXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
              />
              <p v-if="editingIntegration" class="mt-1 text-xs text-gray-500">Dejar vacío para mantener el valor actual</p>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Auth Token *</label>
              <input
                v-model="form.auth_token"
                type="password"
                :required="!editingIntegration"
                placeholder="••••••••••••••••••••••••••••••••"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
              />
              <p v-if="editingIntegration" class="mt-1 text-xs text-gray-500">Dejar vacío para mantener el valor actual</p>
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

          <!-- Nubarium Specific Fields -->
          <div v-else-if="form.provider === 'nubarium'" class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">API Key *</label>
              <input
                v-model="form.api_key"
                type="text"
                :required="!editingIntegration"
                placeholder="Tu API Key de Nubarium"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
              />
              <p v-if="editingIntegration" class="mt-1 text-xs text-gray-500">Dejar vacío para mantener el valor actual</p>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">API Secret *</label>
              <input
                v-model="form.api_secret"
                type="password"
                :required="!editingIntegration"
                placeholder="••••••••••••••••••••••••••••••••"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
              />
              <p v-if="editingIntegration" class="mt-1 text-xs text-gray-500">Dejar vacío para mantener el valor actual</p>
            </div>

            <div class="p-3 bg-blue-50 border border-blue-200 rounded-lg">
              <div class="flex items-start gap-2">
                <svg class="w-5 h-5 text-blue-600 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                </svg>
                <p class="text-sm text-blue-800">
                  Obtén tus credenciales desde el panel de Nubarium en <a href="https://nubarium.com" target="_blank" class="underline font-medium">nubarium.com</a>
                </p>
              </div>
            </div>
          </div>

          <!-- Generic API Key Fields (for other providers) -->
          <div v-else-if="form.provider && !['twilio', 'nubarium'].includes(form.provider)" class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">API Key *</label>
              <input
                v-model="form.api_key"
                type="text"
                :required="!editingIntegration"
                placeholder="Tu API Key"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
              />
              <p v-if="editingIntegration" class="mt-1 text-xs text-gray-500">Dejar vacío para mantener el valor actual</p>
            </div>

            <div v-if="['mailgun', 'sendgrid', 'ses'].includes(form.provider)">
              <label class="block text-sm font-medium text-gray-700 mb-1">Dominio</label>
              <input
                v-model="form.domain"
                type="text"
                placeholder="mg.tudominio.com"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
              />
            </div>

            <div v-if="form.service_type === 'email'">
              <label class="block text-sm font-medium text-gray-700 mb-1">Email de Origen</label>
              <input
                v-model="form.from_email"
                type="email"
                placeholder="noreply@tudominio.com"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
              />
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
          <!-- SMS/WhatsApp: Require phone number -->
          <template v-if="testingIntegration && ['sms', 'whatsapp'].includes(testingIntegration.service_type)">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">
                Número de teléfono de prueba *
              </label>
              <input
                v-model="testForm.test_phone"
                type="tel"
                required
                placeholder="+521234567890"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
              />
              <p class="mt-1 text-xs text-gray-500">
                Se enviará un mensaje de prueba a este número (formato E.164)
              </p>
            </div>
          </template>

          <!-- KYC: Just credential verification, no input needed -->
          <template v-else-if="testingIntegration?.service_type === 'kyc'">
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-3">
              <div class="flex items-start gap-2">
                <svg class="w-5 h-5 text-blue-600 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                </svg>
                <p class="text-sm text-blue-800">
                  Se verificará la conexión con el servicio para confirmar que las credenciales son correctas.
                </p>
              </div>
            </div>
          </template>

          <!-- Email: Require email address -->
          <template v-else-if="testingIntegration?.service_type === 'email'">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">
                Email de prueba *
              </label>
              <input
                v-model="testForm.test_email"
                type="email"
                required
                placeholder="tu@email.com"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
              />
              <p class="mt-1 text-xs text-gray-500">
                Se enviará un mensaje de prueba a este email
              </p>
            </div>
          </template>

          <!-- Generic fallback -->
          <template v-else>
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-3">
              <div class="flex items-start gap-2">
                <svg class="w-5 h-5 text-blue-600 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                </svg>
                <p class="text-sm text-blue-800">
                  Se verificará la conexión con el servicio para confirmar que las credenciales son correctas.
                </p>
              </div>
            </div>
          </template>

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
                  Credenciales verificadas. La integración está funcionando correctamente.
                </p>
                <!-- Help for credential errors -->
                <div v-if="!testResult.success && isCredentialError(testResult.error)" class="mt-3 p-2 bg-yellow-50 border border-yellow-200 rounded text-xs text-yellow-800">
                  <p class="font-medium">Posibles soluciones:</p>
                  <ul class="mt-1 list-disc list-inside space-y-0.5">
                    <li>Verifique que el Account SID y Auth Token sean correctos</li>
                    <li>Obtenga las credenciales desde la consola de Twilio</li>
                    <li>Asegúrese de no copiar espacios adicionales</li>
                  </ul>
                </div>
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
              {{ isTesting ? 'Probando...' : 'Probar Conexión' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Delete Integration Confirmation Modal -->
    <AppConfirmModal
      :show="showDeleteModal"
      title="Eliminar Integración"
      :message="`¿Estás seguro de eliminar la integración de ${integrationToDelete?.provider_label} (${integrationToDelete?.service_type_label})? Esta acción no se puede deshacer.`"
      confirm-text="Eliminar"
      variant="danger"
      icon="danger"
      @confirm="deleteIntegration"
      @update:show="showDeleteModal = $event"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { v2 } from '@/services/v2'
import type { V2Integration } from '@/services/v2/integration.staff.service'
import { getErrorMessage } from '@/types/api'
import { AppConfirmModal } from '@/components/common'
import { useToast } from '@/composables'
import { logger } from '@/utils/logger'

const log = logger.child('AdminIntegrationsView')
const toast = useToast()

type Integration = V2Integration

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
  domain: '',
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

// Toggle status
const isTogglingStatus = ref<string | null>(null)

// Load integrations
const loadIntegrations = async () => {
  try {
    isLoading.value = true
    error.value = null
    const response = await v2.staff.integration.list()
    integrations.value = response.data?.integrations ?? []
  } catch (err: unknown) {
    error.value = getErrorMessage(err, 'Error al cargar integraciones')
    log.error('Error al cargar integraciones', { error: err })
  } finally {
    isLoading.value = false
  }
}

// Load options
const loadOptions = async () => {
  try {
    const response = await v2.staff.integration.getOptions()
    providers.value = response.data?.providers ?? {}
    serviceTypes.value = response.data?.service_types ?? {}
  } catch (err) {
    log.error('Error al cargar opciones', { error: err })
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
    domain: '',
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
    domain: integration.domain || '',
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
    await v2.staff.integration.save(form.value)
    await loadIntegrations()
    closeEditModal()
    toast.success('Integración guardada')
  } catch (err: unknown) {
    log.error('Error al guardar integración', { error: err })
    toast.error(getErrorMessage(err, 'Error al guardar integración'))
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
    const response = await v2.staff.integration.test(testingIntegration.value.id, testForm.value)
    // Map response to expected test result format
    testResult.value = {
      success: response.success,
      message: response.message ?? 'Test completado',
      error: !response.success ? (response.message ?? 'Error desconocido') : undefined,
    }
    await loadIntegrations() // Reload to show updated test status
  } catch (err: unknown) {
    testResult.value = {
      success: false,
      message: 'Error en la prueba',
      error: getErrorMessage(err, 'Error desconocido'),
    }
    log.error('Error al probar integración', { error: err })
  } finally {
    isTesting.value = false
  }
}

// Check if error is a credential error
const isCredentialError = (error?: string): boolean => {
  if (!error) return false
  const credentialErrors = ['401', 'Authenticate', 'credential', 'inválidas', 'Account SID', 'Auth Token']
  return credentialErrors.some(keyword => error.toLowerCase().includes(keyword.toLowerCase()))
}

// Toggle integration status (enable/disable)
const toggleIntegrationStatus = async (integration: Integration) => {
  try {
    isTogglingStatus.value = integration.id
    await v2.staff.integration.toggle(integration.id)
    await loadIntegrations()
    toast.success(integration.is_active ? 'Integración pausada' : 'Integración activada')
  } catch (err: unknown) {
    log.error('Error al cambiar estado de integración', { error: err })
    toast.error(getErrorMessage(err, 'Error al cambiar el estado de la integración'))
  } finally {
    isTogglingStatus.value = null
  }
}

// Delete integration confirmation
const showDeleteModal = ref(false)
const integrationToDelete = ref<Integration | null>(null)

const confirmDeleteIntegration = (integration: Integration) => {
  integrationToDelete.value = integration
  showDeleteModal.value = true
}

const deleteIntegration = async () => {
  if (!integrationToDelete.value) return

  try {
    await v2.staff.integration.destroy(integrationToDelete.value.id)
    showDeleteModal.value = false
    await loadIntegrations()
    toast.success('Integración eliminada')
  } catch (err: unknown) {
    log.error('Error al eliminar integración', { error: err })
    toast.error(getErrorMessage(err, 'Error al eliminar integración'))
  }
  integrationToDelete.value = null
}

onMounted(() => {
  loadOptions()
  loadIntegrations()
})
</script>
