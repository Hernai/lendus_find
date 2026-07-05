<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount } from 'vue'
import { staff } from '@/modules/admin/services'
import type { V2TenantInfo } from '@/modules/admin/services/config.staff.service'
import { AppInput } from '@/components/common'
import TenantBrandingEditor, { type Branding, type TenantPreviewInfo } from '@/modules/admin/components/TenantBrandingEditor.vue'
import { logger } from '@/utils/logger'

const log = logger.child('AdminSettings')

// Use V2 types
type TenantInfo = V2TenantInfo

// State
const isLoading = ref(true)
const error = ref('')
const activeTab = ref<'general' | 'branding'>('branding')

// Data
const tenant = ref<TenantInfo | null>(null)
const branding = ref<Branding | null>(null)

// Form state
const isSaving = ref(false)
const saveMessage = ref('')
const saveError = ref('')

// Timeout cleanup - single timer pattern to avoid memory buildup
let messageTimeoutId: ReturnType<typeof setTimeout> | null = null
const clearMessageAfterDelay = () => {
  // Clear any existing timer first
  if (messageTimeoutId) {
    clearTimeout(messageTimeoutId)
  }
  messageTimeoutId = setTimeout(() => {
    saveMessage.value = ''
    saveError.value = ''
  }, 3000)
}
onBeforeUnmount(() => {
  if (messageTimeoutId) {
    clearTimeout(messageTimeoutId)
  }
})

// Computed tenant info for preview
const tenantPreviewInfo = ref<TenantPreviewInfo>({ name: '', slug: '' })

// Load data using V2 API
const loadConfig = async () => {
  isLoading.value = true
  error.value = ''

  try {
    const response = await staff.config.getConfig()
    const data = response.data!

    tenant.value = data.tenant
    branding.value = data.branding as Branding

    // Update preview info
    tenantPreviewInfo.value = {
      name: tenant.value.name,
      slug: tenant.value.slug
    }
  } catch (e) {
    log.error('Error al cargar configuración', { error: e })
    error.value = 'Error al cargar la configuración'
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
    await staff.config.updateTenant({
      name: tenant.value.name,
      legal_name: tenant.value.legal_name || null,
      rfc: tenant.value.rfc || null,
      email: tenant.value.email || null,
      phone: tenant.value.phone || null,
      website: tenant.value.website || null
    })
    saveMessage.value = 'Información guardada'
    clearMessageAfterDelay()
  } catch {
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
    await staff.config.updateBranding(branding.value as Parameters<typeof staff.config.updateBranding>[0])
    saveMessage.value = 'Branding guardado'
    clearMessageAfterDelay()
  } catch {
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
            <p class="text-sm text-gray-500">{{ tenant.domain || `${tenant.slug}.lendus.app` }}</p>
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
              { id: 'branding', label: 'Branding', icon: 'M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01' }
            ]"
            :key="tab.id"
            @click="activeTab = tab.id as 'general' | 'branding'"
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
      <div v-show="activeTab === 'general'" class="p-6 w-full">
        <div class="bg-white rounded-xl border border-gray-200 p-6">
          <h2 class="text-lg font-semibold text-gray-900 mb-4">Información de la Empresa</h2>

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
              label="Razón social"
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

    </div>
  </div>
</template>
