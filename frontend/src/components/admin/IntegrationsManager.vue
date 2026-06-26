<template>
  <div :class="embedded ? '' : 'min-h-screen bg-gray-50 py-6'">
    <div :class="embedded ? '' : 'max-w-7xl mx-auto px-4 sm:px-6 lg:px-8'">
      <!-- Header (solo en modo standalone) -->
      <div v-if="!embedded" class="mb-6">
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
            @click="openNewIntegrationModal()"
            class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors flex items-center gap-2"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            Nueva Integración
          </button>
        </div>

        <!-- Empty State -->
        <div v-if="integrations.length === 0" class="text-center py-12">
          <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
          </svg>
          <h3 class="mt-2 text-sm font-medium text-gray-900">No hay integraciones configuradas</h3>
          <p class="mt-1 text-sm text-gray-500">Comienza agregando una nueva integración.</p>
        </div>

        <!-- Integraciones agrupadas por proveedor -->
        <div v-else class="space-y-8">
          <section v-for="group in integrationsByProvider" :key="group.provider">
            <!-- Provider section header -->
            <div class="flex items-center gap-3 mb-3">
              <div class="w-9 h-9 rounded-lg bg-white border border-gray-200 flex items-center justify-center text-base font-bold text-gray-600">
                {{ group.provider_label.charAt(0) }}
              </div>
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2">
                  <h2 class="text-base font-semibold text-gray-900 truncate">{{ group.provider_label }}</h2>
                  <span :class="['px-2 py-0.5 text-[10px] font-semibold rounded-full', statusMeta(group.provider_status).classes]">
                    {{ statusMeta(group.provider_status).label }}
                  </span>
                </div>
                <p class="text-xs text-gray-500">
                  {{ group.items.length }} {{ group.items.length === 1 ? 'servicio configurado' : 'servicios configurados' }}
                </p>
              </div>
              <!-- Activar otro servicio del proveedor (KYC, Email, etc.) -->
              <button
                type="button"
                class="flex-shrink-0 inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium rounded-lg border border-primary-200 text-primary-700 bg-primary-50 hover:bg-primary-100 transition-colors"
                @click="openNewIntegrationModal(group.provider)"
              >
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Servicio
              </button>
            </div>

            <!-- Tarjetas de servicio del proveedor (cada una con su prueba independiente) -->
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
              <div
                v-for="integration in group.items"
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
                    <!-- El proveedor + estado ya van en el encabezado del grupo;
                         aquí destacamos el servicio para no repetir. -->
                    <h3 class="font-semibold text-gray-900">{{ integration.service_type_label }}</h3>
                    <p class="text-xs text-gray-500">{{ integration.provider_label }}</p>
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

                <!-- SMTP specific -->
                <template v-else-if="integration.provider === 'smtp'">
                  <div v-if="integration.extra_config?.host" class="flex justify-between">
                    <span class="text-gray-500">Servidor</span>
                    <span class="font-mono text-gray-900 text-xs">{{ integration.extra_config.host }}:{{ integration.extra_config.port }}</span>
                  </div>
                  <div v-if="integration.extra_config?.encryption" class="flex justify-between">
                    <span class="text-gray-500">Encriptación</span>
                    <span class="font-mono text-gray-900 text-xs uppercase">{{ integration.extra_config.encryption }}</span>
                  </div>
                  <div v-if="integration.from_email" class="flex justify-between">
                    <span class="text-gray-500">Email</span>
                    <span class="font-mono text-gray-900 text-xs">{{ integration.from_email }}</span>
                  </div>
                </template>

                <!-- Email providers (API-based) -->
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
                  v-if="['sms', 'whatsapp', 'kyc', 'email'].includes(integration.service_type)"
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
            </div>
          </section>
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

        <form @submit.prevent="saveIntegration" class="p-6 space-y-5">
          <!-- Step 1: Provider selection -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
              <span class="inline-flex items-center justify-center w-5 h-5 mr-1.5 rounded-full bg-primary-100 text-primary-700 text-xs font-bold">1</span>
              Proveedor *
            </label>

            <!-- Edit mode: provider is locked -->
            <div v-if="editingIntegration" class="flex items-center gap-3 p-3 rounded-xl border border-gray-200 bg-gray-50">
              <div class="w-9 h-9 rounded-lg bg-white border border-gray-200 flex items-center justify-center text-sm font-bold text-gray-600">
                {{ providerLabel(form.provider).charAt(0) }}
              </div>
              <div>
                <p class="font-medium text-sm text-gray-900">{{ providerLabel(form.provider) }}</p>
                <p class="text-xs text-gray-500">El proveedor no se puede cambiar al editar</p>
              </div>
            </div>

            <!-- New mode: visual grid picker -->
            <div v-else class="grid grid-cols-2 sm:grid-cols-3 gap-2">
              <button
                v-for="p in sortedProviders"
                :key="p.key"
                type="button"
                :disabled="p.status === 'coming_soon'"
                @click="selectProvider(p)"
                :class="[
                  'relative flex flex-col items-start gap-1.5 p-3 rounded-xl border text-left transition-all',
                  form.provider === p.key
                    ? 'border-primary-500 ring-2 ring-primary-500/20 bg-primary-50'
                    : 'border-gray-200 bg-white',
                  p.status === 'coming_soon'
                    ? 'opacity-50 cursor-not-allowed'
                    : 'hover:border-primary-300 hover:shadow-sm cursor-pointer'
                ]"
              >
                <!-- Selected check -->
                <span
                  v-if="form.provider === p.key"
                  class="absolute top-2 right-2 w-5 h-5 rounded-full bg-primary-600 flex items-center justify-center"
                >
                  <svg class="w-3.5 h-3.5 text-white" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                  </svg>
                </span>
                <div class="flex items-center gap-2 w-full pr-5">
                  <div class="w-8 h-8 rounded-lg bg-gray-100 flex items-center justify-center text-sm font-bold text-gray-600 flex-shrink-0">
                    {{ p.label.charAt(0) }}
                  </div>
                  <span class="font-medium text-sm text-gray-900 truncate">{{ p.label }}</span>
                </div>
                <span :class="['px-1.5 py-0.5 text-[10px] font-semibold rounded-full', statusMeta(p.status).classes]">
                  {{ statusMeta(p.status).label }}
                </span>
              </button>
            </div>
          </div>

          <!-- Step 2: Service type (only providers' own services) -->
          <div v-if="form.provider">
            <label class="block text-sm font-medium text-gray-700 mb-2">
              <span class="inline-flex items-center justify-center w-5 h-5 mr-1.5 rounded-full bg-primary-100 text-primary-700 text-xs font-bold">2</span>
              Tipo de Servicio *
            </label>
            <div class="flex flex-wrap gap-2">
              <!-- Seleccionar / quitar todos (solo alta, si hay más de uno) -->
              <button
                v-if="!editingIntegration && availableServiceTypes.length > 1"
                type="button"
                @click="toggleAllServices"
                :class="[
                  'px-3.5 py-2 rounded-lg border text-sm font-medium transition-all cursor-pointer',
                  allServicesSelected
                    ? 'border-primary-600 bg-primary-600 text-white'
                    : 'border-dashed border-primary-300 bg-white text-primary-700 hover:border-primary-400'
                ]"
              >
                {{ allServicesSelected ? 'Quitar todos' : 'Todos' }}
              </button>
              <button
                v-for="s in availableServiceTypes"
                :key="s.key"
                type="button"
                :disabled="!!editingIntegration"
                @click="toggleService(s.key)"
                :class="[
                  'px-3.5 py-2 rounded-lg border text-sm font-medium transition-all',
                  form.service_types.includes(s.key)
                    ? 'border-primary-500 bg-primary-50 text-primary-700 ring-1 ring-primary-500/30'
                    : 'border-gray-200 bg-white text-gray-700 hover:border-primary-300',
                  editingIntegration ? 'cursor-not-allowed opacity-70' : 'cursor-pointer'
                ]"
              >
                {{ s.label }}
              </button>
            </div>
            <p class="mt-1.5 text-xs text-gray-500">
              Sólo se muestran los servicios que ofrece {{ providerLabel(form.provider) }}.
              <template v-if="!editingIntegration"> Puedes elegir varios; se crea una integración por servicio (mismas credenciales).</template>
              <template v-else> El servicio no se puede cambiar al editar. Para activar otro servicio de {{ providerLabel(form.provider) }} (p. ej. KYC), usa <b>Nueva Integración</b> o el botón <b>+ Servicio</b> del proveedor.</template>
            </p>
          </div>

          <!-- Provider-Specific Fields -->
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

          <!-- SMTP Specific Fields -->
          <div v-if="form.provider === 'smtp'" class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Host SMTP *</label>
                <input
                  v-model="form.smtp_host"
                  type="text"
                  required
                  placeholder="smtp.gmail.com"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Puerto *</label>
                <input
                  v-model="form.smtp_port"
                  type="number"
                  required
                  placeholder="587"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                />
              </div>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Encriptación *</label>
              <select
                v-model="form.smtp_encryption"
                required
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
              >
                <option value="tls">TLS (STARTTLS) - Puerto 587</option>
                <option value="ssl">SSL - Puerto 465</option>
                <option value="none">Sin encriptación - Puerto 25</option>
              </select>
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Usuario (email) *</label>
                <input
                  v-model="form.api_key"
                  type="text"
                  :required="!editingIntegration"
                  placeholder="tu@empresa.com"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                />
                <p v-if="editingIntegration" class="mt-1 text-xs text-gray-500">Dejar vacío para mantener el actual</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Contraseña *</label>
                <input
                  v-model="form.api_secret"
                  type="password"
                  :required="!editingIntegration"
                  placeholder="••••••••••••••••"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                />
                <p v-if="editingIntegration" class="mt-1 text-xs text-gray-500">Dejar vacío para mantener el actual</p>
              </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Email de Origen *</label>
                <input
                  v-model="form.from_email"
                  type="email"
                  required
                  placeholder="notificaciones@empresa.com"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nombre del Remitente</label>
                <input
                  v-model="form.smtp_from_name"
                  type="text"
                  placeholder="Mi Empresa"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                />
              </div>
            </div>

            <div class="p-3 bg-blue-50 border border-blue-200 rounded-lg">
              <div class="flex items-start gap-2">
                <svg class="w-5 h-5 text-blue-600 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                </svg>
                <div class="text-sm text-blue-800">
                  <p class="font-medium mb-1">Configuraciones comunes:</p>
                  <ul class="space-y-0.5 text-xs">
                    <li><strong>Gmail:</strong> smtp.gmail.com : 587 (TLS) - Usar contraseña de aplicación</li>
                    <li><strong>Office 365:</strong> smtp.office365.com : 587 (TLS)</li>
                    <li><strong>Outlook:</strong> smtp-mail.outlook.com : 587 (TLS)</li>
                  </ul>
                </div>
              </div>
            </div>
          </div>

          <!-- Generic API Key Fields (for other providers) -->
          <div v-if="form.provider && !['twilio', 'nubarium', 'smtp'].includes(form.provider)" class="space-y-4">
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

            <div v-if="form.service_types.includes('email')">
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
          <!-- SMS/WhatsApp: Require phone number (10 dígitos nacionales) -->
          <template v-if="testingIntegration && ['sms', 'whatsapp'].includes(testingIntegration.service_type)">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">
                Número de teléfono de prueba *
              </label>
              <div class="flex items-stretch">
                <span class="inline-flex flex-shrink-0 items-center gap-1.5 px-3 py-2 rounded-l-lg border border-r-0 border-gray-300 bg-gray-50 text-sm font-medium text-gray-600 whitespace-nowrap select-none">
                  <span class="text-base leading-none">🇲🇽</span>
                  <span>+52</span>
                </span>
                <input
                  :value="testForm.test_phone"
                  @input="onTestPhoneInput"
                  type="tel"
                  inputmode="numeric"
                  required
                  :maxlength="PHONE_INPUT_CONFIG.maxLength"
                  :placeholder="PHONE_INPUT_CONFIG.placeholder"
                  class="flex-1 min-w-0 px-3 py-2 border border-gray-300 rounded-r-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 focus:z-10"
                />
              </div>
              <p class="mt-1 text-xs text-gray-500">
                10 dígitos del celular, sin lada de país (México +52 se agrega solo).
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
import { ref, computed, watch, onMounted } from 'vue'
import type { V2Integration, V2IntegrationPayload, V2ProviderOption, ProviderStatus } from '@/services/v2/integration.staff.service'
import type { IntegrationsAdapter } from '@/services/v2/integrationsAdapters'
import { getErrorMessage } from '@/types/api'
import { AppConfirmModal } from '@/components/common'
import { useToast } from '@/composables'
import { logger } from '@/utils/logger'
import { formatPhoneInput, stripPhoneFormatting, PHONE_INPUT_CONFIG } from '@/utils/formatters'

const props = defineProps<{
  /** Origen de datos: tenant actual (self) o un tenant específico (super admin). */
  adapter: IntegrationsAdapter
  /** Si true, omite el chrome de página (para embeber en otra vista). */
  embedded?: boolean
}>()

const log = logger.child('IntegrationsManager')
const toast = useToast()

type Integration = V2Integration

const integrations = ref<Integration[]>([])
const providers = ref<V2ProviderOption[]>([])
const serviceTypes = ref<Record<string, string>>({})

// Etiqueta + clases del badge de estado de proveedor.
const STATUS_META: Record<ProviderStatus, { label: string; classes: string }> = {
  available: { label: 'Disponible', classes: 'bg-green-100 text-green-700' },
  beta: { label: 'Beta', classes: 'bg-amber-100 text-amber-700' },
  coming_soon: { label: 'Próximamente', classes: 'bg-gray-100 text-gray-500' },
}
const statusMeta = (status?: ProviderStatus) => STATUS_META[status ?? 'coming_soon']
const isLoading = ref(true)
const error = ref<string | null>(null)

// Edit Modal
const showEditModal = ref(false)
const editingIntegration = ref<Integration | null>(null)
const isSaving = ref(false)
const form = ref({
  provider: '',
  service_types: [] as string[],
  account_sid: '',
  auth_token: '',
  api_key: '',
  api_secret: '',
  from_number: '',
  from_email: '',
  domain: '',
  smtp_host: '',
  smtp_port: '587',
  smtp_encryption: 'tls',
  smtp_from_name: '',
  is_active: true,
  is_sandbox: false,
})

// Proveedores ordenados: disponibles primero, luego beta, luego próximamente.
const STATUS_ORDER: Record<ProviderStatus, number> = { available: 0, beta: 1, coming_soon: 2 }
const sortedProviders = computed(() =>
  [...providers.value].sort(
    (a, b) => (STATUS_ORDER[a.status] - STATUS_ORDER[b.status]) || a.label.localeCompare(b.label),
  ),
)

// Etiqueta legible de un proveedor por su key.
const providerLabel = (key: string) =>
  providers.value.find((p) => p.key === key)?.label ?? key

// Integraciones agrupadas por proveedor (para mostrar cada proveedor con sus
// servicios configurados y probar cada uno de forma independiente).
const integrationsByProvider = computed(() => {
  const map = new Map<
    string,
    { provider: string; provider_label: string; provider_status: ProviderStatus; items: Integration[] }
  >()
  for (const it of integrations.value) {
    let g = map.get(it.provider)
    if (!g) {
      g = { provider: it.provider, provider_label: it.provider_label, provider_status: it.provider_status, items: [] }
      map.set(it.provider, g)
    }
    g.items.push(it)
  }
  return [...map.values()]
})

// Sólo los tipos de servicio que ofrece el proveedor seleccionado. Si el
// proveedor no trae `services` (fallback), se muestran todos.
const availableServiceTypes = computed(() => {
  const provider = providers.value.find((p) => p.key === form.value.provider)
  const keys = provider?.services ?? Object.keys(serviceTypes.value)
  return keys
    .filter((k) => serviceTypes.value[k])
    .map((k) => ({ key: k, label: serviceTypes.value[k] ?? k }))
})

// Seleccionar proveedor desde el grid visual (sólo alta).
const selectProvider = (p: V2ProviderOption) => {
  if (p.status === 'coming_soon') return
  form.value.provider = p.key
}

// Al cambiar el proveedor (alta), preselecciona TODOS sus servicios; el admin
// puede quitar los que no quiera. Se crea una integración por servicio.
watch(() => form.value.provider, (provider) => {
  if (editingIntegration.value) return
  form.value.service_types = provider ? availableServiceTypes.value.map((s) => s.key) : []
})

// Multi-selección de servicios (sólo alta).
const allServicesSelected = computed(() =>
  availableServiceTypes.value.length > 0 &&
  availableServiceTypes.value.every((s) => form.value.service_types.includes(s.key)),
)
const toggleService = (key: string) => {
  if (editingIntegration.value) return
  const i = form.value.service_types.indexOf(key)
  if (i === -1) form.value.service_types.push(key)
  else form.value.service_types.splice(i, 1)
}
const toggleAllServices = () => {
  form.value.service_types = allServicesSelected.value
    ? []
    : availableServiceTypes.value.map((s) => s.key)
}

// Formatea el teléfono de prueba a 10 dígitos nacionales mientras se escribe.
const onTestPhoneInput = (e: Event) => {
  testForm.value.test_phone = formatPhoneInput((e.target as HTMLInputElement).value)
}

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

// Load integrations.
// `silent` evita activar el spinner de página completa (isLoading), que al
// recargar tras una acción (probar, pausar, guardar) ocultaba todas las
// tarjetas y se veía como una "pantalla en blanco / recarga total" detrás del
// modal. Sólo la carga inicial muestra el spinner.
const loadIntegrations = async ({ silent = false }: { silent?: boolean } = {}) => {
  try {
    if (!silent) isLoading.value = true
    error.value = null
    integrations.value = await props.adapter.list()
  } catch (err: unknown) {
    if (!silent) error.value = getErrorMessage(err, 'Error al cargar integraciones')
    log.error('Error al cargar integraciones', { error: err })
  } finally {
    if (!silent) isLoading.value = false
  }
}

// Load options
const loadOptions = async () => {
  try {
    const options = await props.adapter.getOptions()
    providers.value = options.providers
    serviceTypes.value = options.service_types
  } catch (err) {
    log.error('Error al cargar opciones', { error: err })
  }
}

// Open new integration modal
const openNewIntegrationModal = (provider = '') => {
  editingIntegration.value = null
  form.value = {
    provider: '',
    service_types: [] as string[],
    account_sid: '',
    auth_token: '',
    api_key: '',
    api_secret: '',
    from_number: '',
    from_email: '',
    domain: '',
    smtp_host: '',
    smtp_port: '587',
    smtp_encryption: 'tls',
    smtp_from_name: '',
    is_active: true,
    is_sandbox: false,
  }
  // Preselección de proveedor (desde el botón "+ Servicio" del grupo): dispara
  // el watch que marca los servicios de ese proveedor para agregarlos.
  if (provider) {
    form.value.provider = provider
  }
  showEditModal.value = true
}

// Open edit modal
const openEditModal = (integration: Integration) => {
  editingIntegration.value = integration
  const extra = integration.extra_config ?? {}
  form.value = {
    provider: integration.provider,
    service_types: [integration.service_type],
    account_sid: '',
    auth_token: '',
    api_key: '',
    api_secret: '',
    from_number: integration.from_number || '',
    from_email: integration.from_email || '',
    domain: integration.domain || '',
    smtp_host: (extra.host as string) || '',
    smtp_port: (extra.port as string) || '587',
    smtp_encryption: (extra.encryption as string) || 'tls',
    smtp_from_name: (extra.from_name as string) || '',
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
  if (!form.value.provider || form.value.service_types.length === 0) {
    toast.error('Selecciona un proveedor y al menos un tipo de servicio')
    return
  }
  const count = form.value.service_types.length
  try {
    isSaving.value = true

    // Una integración por servicio seleccionado, con las mismas credenciales.
    for (const service_type of form.value.service_types) {
      const payload: V2IntegrationPayload = {
        provider: form.value.provider,
        service_type,
        account_sid: form.value.account_sid || undefined,
        auth_token: form.value.auth_token || undefined,
        api_key: form.value.api_key || undefined,
        api_secret: form.value.api_secret || undefined,
        from_number: form.value.from_number || undefined,
        from_email: form.value.from_email || undefined,
        domain: form.value.domain || undefined,
        is_active: form.value.is_active,
        is_sandbox: form.value.is_sandbox,
      }

      // Pack SMTP fields into extra_config
      if (form.value.provider === 'smtp') {
        payload.extra_config = {
          host: form.value.smtp_host,
          port: form.value.smtp_port,
          encryption: form.value.smtp_encryption,
          from_name: form.value.smtp_from_name,
        }
      }

      await props.adapter.save(payload)
    }

    await loadIntegrations({ silent: true })
    closeEditModal()
    toast.success(count > 1 ? `${count} integraciones guardadas` : 'Integración guardada')
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
    // Enviar el teléfono como 10 dígitos limpios (sin formato ni lada país).
    const response = await props.adapter.test(testingIntegration.value.id, {
      test_phone: stripPhoneFormatting(testForm.value.test_phone),
      test_email: testForm.value.test_email || undefined,
    })
    // Map response to expected test result format
    testResult.value = {
      success: response.success,
      message: response.message ?? 'Test completado',
      error: !response.success ? (response.message ?? 'Error desconocido') : undefined,
    }
    // Recarga SILENCIOSA: refresca el estado de la prueba en la tarjeta sin
    // parpadear la pantalla detrás del modal.
    await loadIntegrations({ silent: true })
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
    await props.adapter.toggle(integration)
    await loadIntegrations({ silent: true })
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
    await props.adapter.destroy(integrationToDelete.value.id)
    showDeleteModal.value = false
    await loadIntegrations({ silent: true })
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
