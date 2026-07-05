<script setup lang="ts">
import { ref, computed, onMounted, watch, onBeforeUnmount } from 'vue'
import { v2 } from '@/services/v2'
import type {
  V2TenantConfig,
  V2TenantFilters,
} from '@/services/v2/tenant.staff.service'
import { AppConfirmModal } from '@/components/common'
import TenantBrandingEditor from '@/modules/admin/components/TenantBrandingEditor.vue'
import IntegrationsManager from '@/modules/admin/components/IntegrationsManager.vue'
import { tenantIntegrationsAdapter } from '@/services/v2/integrationsAdapters'
import { formatPhoneValue, stripPhoneFormatting, PHONE_INPUT_CONFIG } from '@/composables'
import { logger } from '@/utils/logger'
// Registry de configs per-tenant en build-time. Sirve para mostrar al
// admin qué customizaciones hard-coded tiene cada tenant.
import { getTenantCustomizations } from '@tenants'

const log = logger.child('AdminTenants')

// Extend the V2 types for local form handling with more complete branding
interface Tenant {
  id: string
  name: string
  slug: string
  /** Dominio público (ej. moneycapital.lendus.app). Null si aún no asignado. */
  domain: string | null
  legal_name: string | null
  rfc: string | null
  email: string | null
  phone: string | null
  website: string | null
  is_active: boolean
  branding: {
    is_locked?: boolean
    primary_color: string
    secondary_color?: string
    accent_color?: string
    logo_url?: string | null
    favicon_url?: string | null
    font_family?: string
    border_radius?: string
  }
  settings?: {
    otp_provider?: string
    kyc_provider?: string
    max_loan_amount?: number
    min_loan_amount?: number
    currency?: string
    timezone?: string
  }
  webhook_config?: {
    url?: string
    secret_key?: string
    events?: string[]
  } | null
  users_count?: number
  applications_count?: number
  created_at: string
  activated_at?: string | null
  suspended_at?: string | null
}

// State
const tenants = ref<Tenant[]>([])
const isLoading = ref(true)
const error = ref('')
const searchQuery = ref('')
const activeFilter = ref('all')
const currentPage = ref(1)
const totalPages = ref(1)
const totalItems = ref(0)

// Modal state
const showTenantModal = ref(false)
const editingTenant = ref<Tenant | null>(null)
const isSubmitting = ref(false)
const formError = ref('')
const activeTab = ref<'general' | 'branding' | 'settings' | 'webhook'>('general')

// Delete state
const showDeleteModal = ref(false)
const tenantToDelete = ref<Tenant | null>(null)
const isDeleting = ref(false)

// Config modal state - use V2 types
type TenantConfig = V2TenantConfig

const showConfigModal = ref(false)
const configTenant = ref<Tenant | null>(null)
const configData = ref<TenantConfig | null>(null)
const isLoadingConfig = ref(false)
const isSavingConfig = ref(false)
const configActiveTab = ref<'branding' | 'apis' | 'custom'>('branding')

// Personalizaciones hard-coded del tenant. Se leen estáticamente del
// archivo `frontend/tenants/<slug>.tenant.ts` (no del backend), porque
// son una decisión de código que viaja con el bundle del frontend. Si
// la entrada está poblada, ese pedazo NO respeta el branding del admin.
const customizationRows = computed(() => {
  const slug = configTenant.value?.slug ?? null
  const c = getTenantCustomizations(slug)
  return [
    {
      key: 'landing',
      label: 'Página de inicio (landing)',
      value: c.landing,
      description: 'Componente Vue dedicado para la página marketing.',
      fallbackDescription: 'Landing genérica de LendusFind.',
    },
    {
      key: 'authFlow',
      label: 'Flujo de autenticación',
      value: c.authFlow,
      description: 'Pantallas de login/registro con look propio.',
      fallbackDescription: 'Selector de método + OTP + PIN estándar.',
    },
    {
      key: 'simulator',
      label: 'Simulador de crédito',
      value: c.simulator,
      description: 'Calculadora con variables/visualización propia.',
      fallbackDescription: 'Simulador estándar con productos del tenant.',
    },
    {
      key: 'dashboard',
      label: 'Dashboard post-login',
      value: c.dashboard,
      description: 'Home del aplicante con promociones/CTAs propios.',
      fallbackDescription: 'Dashboard estándar con resumen y solicitudes.',
    },
  ]
})
const configSaveMessage = ref('')
const configSaveError = ref('')

// Timeout cleanup for messages - single timer pattern
let configMessageTimeoutId: ReturnType<typeof setTimeout> | null = null
const clearConfigMessageAfterDelay = () => {
  if (configMessageTimeoutId) {
    clearTimeout(configMessageTimeoutId)
  }
  configMessageTimeoutId = setTimeout(() => {
    configSaveMessage.value = ''
    configSaveError.value = ''
  }, 3000)
}
onBeforeUnmount(() => {
  if (configMessageTimeoutId) {
    clearTimeout(configMessageTimeoutId)
  }
})



// Form state
const form = ref({
  name: '',
  slug: '',
  legal_name: '',
  rfc: '',
  email: '',
  phone: '',
  website: '',
  is_active: true,
  branding: {
    is_locked: false,
    primary_color: '#6366f1',
    secondary_color: '#10b981',
    accent_color: '#f59e0b',
    logo_url: '',
    favicon_url: '',
    font_family: 'Inter, sans-serif',
    border_radius: '12px'
  },
  settings: {
    otp_provider: 'twilio',
    kyc_provider: 'mati',
    max_loan_amount: 500000,
    min_loan_amount: 5000,
    currency: 'MXN',
    timezone: 'America/Mexico_City'
  },
  webhook_config: {
    url: '',
    secret_key: '',
    events: [] as string[]
  }
})

const formErrors = ref({
  name: '',
  slug: '',
  email: ''
})

// Phone formatting - computed for display with formatting
const formattedPhone = computed({
  get: () => formatPhoneValue(form.value.phone),
  set: (value: string) => {
    form.value.phone = stripPhoneFormatting(value)
  }
})



// Fetch tenants
const fetchTenants = async () => {
  isLoading.value = true
  error.value = ''

  try {
    const filters: V2TenantFilters = {
      page: currentPage.value,
      per_page: 20,
      search: searchQuery.value || undefined
    }

    if (activeFilter.value === 'active') {
      filters.active = true
    } else if (activeFilter.value === 'inactive') {
      filters.active = false
    }

    const response = await v2.staff.tenant.list(filters)

    tenants.value = (response.data?.tenants ?? []) as Tenant[]
    totalPages.value = response.data?.meta.last_page ?? 1
    totalItems.value = response.data?.meta.total ?? 0
  } catch (e) {
    log.error('Error al cargar tenants', { error: e })
    error.value = 'Error al cargar los tenants'
  } finally {
    isLoading.value = false
  }
}

// Watch for filter changes
watch([searchQuery, activeFilter], () => {
  currentPage.value = 1
  fetchTenants()
})

onMounted(fetchTenants)

// Modal methods
const openCreateModal = () => {
  editingTenant.value = null
  form.value = {
    name: '',
    slug: '',
    legal_name: '',
    rfc: '',
    email: '',
    phone: '',
    website: '',
    is_active: true,
    branding: {
      is_locked: false,
      primary_color: '#6366f1',
      secondary_color: '#10b981',
      accent_color: '#f59e0b',
      logo_url: '',
      favicon_url: '',
      font_family: 'Inter, sans-serif',
      border_radius: '12px'
    },
    settings: {
      otp_provider: 'twilio',
      kyc_provider: 'mati',
      max_loan_amount: 500000,
      min_loan_amount: 5000,
      currency: 'MXN',
      timezone: 'America/Mexico_City'
    },
    webhook_config: {
      url: '',
      secret_key: '',
      events: []
    }
  }
  formErrors.value = { name: '', slug: '', email: '' }
  formError.value = ''
  activeTab.value = 'general'
  showTenantModal.value = true
}

const openEditModal = (tenant: Tenant) => {
  editingTenant.value = tenant
  form.value = {
    name: tenant.name,
    slug: tenant.slug,
    legal_name: tenant.legal_name || '',
    rfc: tenant.rfc || '',
    email: tenant.email || '',
    phone: tenant.phone || '',
    website: tenant.website || '',
    is_active: tenant.is_active,
    branding: {
      is_locked: tenant.branding?.is_locked ?? false,
      primary_color: tenant.branding?.primary_color || '#6366f1',
      secondary_color: tenant.branding?.secondary_color || '#10b981',
      accent_color: tenant.branding?.accent_color || '#f59e0b',
      logo_url: tenant.branding?.logo_url || '',
      favicon_url: tenant.branding?.favicon_url || '',
      font_family: tenant.branding?.font_family || 'Inter, sans-serif',
      border_radius: tenant.branding?.border_radius || '12px'
    },
    settings: {
      otp_provider: tenant.settings?.otp_provider || 'twilio',
      kyc_provider: tenant.settings?.kyc_provider || 'mati',
      max_loan_amount: tenant.settings?.max_loan_amount || 500000,
      min_loan_amount: tenant.settings?.min_loan_amount || 5000,
      currency: tenant.settings?.currency || 'MXN',
      timezone: tenant.settings?.timezone || 'America/Mexico_City'
    },
    webhook_config: {
      url: tenant.webhook_config?.url || '',
      secret_key: tenant.webhook_config?.secret_key || '',
      events: tenant.webhook_config?.events || []
    }
  }
  formErrors.value = { name: '', slug: '', email: '' }
  formError.value = ''
  activeTab.value = 'general'
  showTenantModal.value = true
}

const saveTenant = async () => {
  // Validate
  formErrors.value = { name: '', slug: '', email: '' }
  if (!form.value.name) {
    formErrors.value.name = 'El nombre es requerido'
    return
  }
  if (!form.value.slug) {
    formErrors.value.slug = 'El slug es requerido'
    return
  }
  if (!/^[a-z0-9-]+$/.test(form.value.slug)) {
    formErrors.value.slug = 'Solo letras minúsculas, números y guiones'
    return
  }

  isSubmitting.value = true
  formError.value = ''

  try {
    // Helper to convert empty strings to null
    const emptyToNull = (val: string) => val?.trim() ? val.trim() : null

    const payload = {
      name: form.value.name.trim(),
      slug: form.value.slug.trim(),
      legal_name: emptyToNull(form.value.legal_name),
      rfc: emptyToNull(form.value.rfc),
      email: emptyToNull(form.value.email),
      phone: emptyToNull(form.value.phone),
      website: emptyToNull(form.value.website),
      is_active: form.value.is_active,
      branding: form.value.branding,
      settings: form.value.settings,
      webhook_config: form.value.webhook_config.url?.trim() ? form.value.webhook_config : null
    }

    if (editingTenant.value) {
      await v2.staff.tenant.update(editingTenant.value.id, payload)
    } else {
      await v2.staff.tenant.create(payload)
    }

    showTenantModal.value = false
    await fetchTenants()
  } catch (e: unknown) {
    const err = e as { response?: { data?: { errors?: Record<string, string[]>; message?: string } } }
    const data = err.response?.data
    if (data?.errors) {
      if (data.errors.slug) formErrors.value.slug = data.errors.slug[0] ?? ''
      if (data.errors.name) formErrors.value.name = data.errors.name[0] ?? ''
      if (data.errors.email) formErrors.value.email = data.errors.email[0] ?? ''
    } else {
      formError.value = data?.message || 'Error al guardar el tenant'
    }
  } finally {
    isSubmitting.value = false
  }
}

const confirmDelete = (tenant: Tenant) => {
  tenantToDelete.value = tenant
  showDeleteModal.value = true
}

const deleteTenant = async () => {
  if (!tenantToDelete.value) return

  isDeleting.value = true
  try {
    await v2.staff.tenant.destroy(tenantToDelete.value.id)
    showDeleteModal.value = false
    tenantToDelete.value = null
    await fetchTenants()
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string; error?: string } } }
    const data = err.response?.data
    formError.value = data?.message || 'Error al eliminar el tenant'
    if (data?.error === 'HAS_RELATED_DATA') {
      formError.value = 'No se puede eliminar porque tiene usuarios o solicitudes'
    }
  } finally {
    isDeleting.value = false
  }
}

// Adjust color brightness (positive = lighter, negative = darker)
const adjustColor = (hex: string, percent: number): string => {
  const num = parseInt(hex.replace('#', ''), 16)
  const amt = Math.round(2.55 * percent)
  const R = Math.max(Math.min((num >> 16) + amt, 255), 0)
  const G = Math.max(Math.min((num >> 8 & 0x00FF) + amt, 255), 0)
  const B = Math.max(Math.min((num & 0x0000FF) + amt, 255), 0)
  return '#' + (0x1000000 + R * 0x10000 + G * 0x100 + B).toString(16).slice(1)
}

const generateSlug = () => {
  form.value.slug = form.value.name
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/(^-|-$)/g, '')
}

// Open tenant configuration modal
const openTenantConfig = async (tenant: Tenant) => {
  configTenant.value = tenant
  configData.value = null
  configActiveTab.value = 'branding'
  configSaveMessage.value = ''
  configSaveError.value = ''
  showConfigModal.value = true
  isLoadingConfig.value = true

  try {
    const response = await v2.staff.tenant.getConfig(tenant.id)
    configData.value = response.data!
  } catch (e) {
    log.error('Error al cargar configuración del tenant', { error: e })
    configSaveError.value = 'Error al cargar la configuración'
  } finally {
    isLoadingConfig.value = false
  }
}

// Save tenant branding
const saveTenantBranding = async () => {
  if (!configTenant.value || !configData.value) return

  isSavingConfig.value = true
  configSaveMessage.value = ''
  configSaveError.value = ''

  try {
    // Cast button_style to the correct type
    const brandingPayload = {
      ...configData.value.branding,
      button_style: configData.value.branding.button_style as 'rounded' | 'pill' | 'square' | undefined
    }
    await v2.staff.tenant.updateBranding(configTenant.value.id, brandingPayload)
    configSaveMessage.value = 'Branding guardado'
    clearConfigMessageAfterDelay()
  } catch {
    configSaveError.value = 'Error al guardar el branding'
  } finally {
    isSavingConfig.value = false
  }
}
</script>

<template>
  <div>
    <!-- Header -->
    <div class="flex items-center justify-between mb-4">
      <div>
        <h1 class="text-xl font-bold text-gray-900">Tenants</h1>
        <p class="text-sm text-gray-500">{{ totalItems }} empresas registradas</p>
      </div>
      <button
        @click="openCreateModal"
        class="flex items-center gap-2 px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors font-medium text-sm shadow-sm"
      >
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
        </svg>
        Nuevo Tenant
      </button>
    </div>

    <!-- Filters Bar -->
    <div class="bg-white rounded-xl shadow-sm p-3 mb-4">
      <div class="flex flex-wrap items-center gap-3">
        <!-- Search -->
        <div class="relative flex-1 min-w-[200px] max-w-md">
          <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
          </svg>
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Buscar por nombre, slug, RFC..."
            class="w-full pl-9 pr-3 py-2.5 text-sm border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 bg-gray-50 focus:bg-white transition-colors"
          />
        </div>

        <!-- Status Filters -->
        <div class="flex items-center gap-1.5">
          <button
            v-for="filter in [
              { value: 'all', label: 'Todos' },
              { value: 'active', label: 'Activos' },
              { value: 'inactive', label: 'Inactivos' }
            ]"
            :key="filter.value"
            @click="activeFilter = filter.value"
            :class="[
              'px-2.5 py-1 text-xs font-medium rounded-full transition-all',
              activeFilter === filter.value
                ? 'bg-gray-800 text-white'
                : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
            ]"
          >
            {{ filter.label }}
          </button>
        </div>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="isLoading" class="flex justify-center py-12">
      <div class="animate-spin w-8 h-8 border-4 border-primary-500 border-t-transparent rounded-full"></div>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="bg-red-50 border border-red-200 rounded-lg p-6 text-center">
      <p class="text-red-600 mb-4">{{ error }}</p>
      <button
        @click="fetchTenants"
        class="px-4 py-2 border-2 border-gray-200 text-gray-700 rounded-lg hover:border-primary-600 hover:text-primary-600 font-medium text-sm transition-colors"
      >
        Reintentar
      </button>
    </div>

    <!-- Empty State -->
    <div v-else-if="tenants.length === 0" class="bg-white rounded-lg border border-gray-200 p-12 text-center">
      <p class="text-gray-500 mb-4">No hay tenants registrados</p>
      <button
        @click="openCreateModal"
        class="px-6 py-2.5 bg-primary-600 text-white rounded-lg hover:bg-primary-700 font-medium text-sm transition-colors shadow-sm"
      >
        Crear Primer Tenant
      </button>
    </div>

    <!-- Tenants Grid -->
    <div v-else class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
      <div
        v-for="tenant in tenants"
        :key="tenant.id"
        class="group bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-lg hover:border-gray-200 transition-all duration-300"
      >
        <!-- Header with gradient -->
        <div
          class="h-24 relative overflow-hidden"
          :style="{ background: `linear-gradient(135deg, ${tenant.branding?.primary_color || '#6366f1'} 0%, ${adjustColor(tenant.branding?.primary_color || '#6366f1', -20)} 100%)` }"
        >
          <!-- Pattern overlay -->
          <div class="absolute inset-0 opacity-20">
            <svg class="w-full h-full" viewBox="0 0 100 100" preserveAspectRatio="none">
              <circle cx="80" cy="20" r="40" fill="white" opacity="0.3"/>
              <circle cx="20" cy="80" r="30" fill="white" opacity="0.2"/>
            </svg>
          </div>

          <!-- Logo or Initial -->
          <div class="absolute bottom-0 left-5 transform translate-y-1/2">
            <div
              class="w-16 h-16 bg-white rounded-xl shadow-lg flex items-center justify-center border-4 border-white overflow-hidden"
            >
              <img
                v-if="tenant.branding?.logo_url"
                :src="tenant.branding.logo_url"
                :alt="tenant.name"
                class="w-full h-full object-contain p-1"
                @error="($event.target as HTMLImageElement).style.display = 'none'; ($event.target as HTMLImageElement).nextElementSibling?.classList.remove('hidden')"
              />
              <div
                :class="['w-full h-full flex items-center justify-center', tenant.branding?.logo_url ? 'hidden' : '']"
                :style="{ backgroundColor: tenant.branding?.primary_color || '#6366f1' }"
              >
                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                </svg>
              </div>
            </div>
          </div>

          <!-- Status badge -->
          <div class="absolute top-3 right-3">
            <span
              :class="[
                'px-3 py-1 rounded-full text-xs font-semibold shadow-sm',
                tenant.is_active
                  ? 'bg-green-500 text-white'
                  : 'bg-gray-500 text-white'
              ]"
            >
              {{ tenant.is_active ? 'Activo' : 'Inactivo' }}
            </span>
          </div>
        </div>

        <div class="p-5 pt-10">
          <!-- Name & Slug -->
          <div class="mb-4">
            <h3 class="font-bold text-lg text-gray-900 group-hover:text-primary-600 transition-colors">{{ tenant.name }}</h3>
            <a
              :href="`https://${tenant.domain || `${tenant.slug}.lendus.app`}`"
              target="_blank"
              class="text-sm text-gray-500 hover:text-primary-600 inline-flex items-center gap-1"
            >
              {{ tenant.domain || `${tenant.slug}.lendus.app` }}
              <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
              </svg>
            </a>
          </div>

          <!-- Info Pills -->
          <div class="flex flex-wrap gap-2 mb-4">
            <span v-if="tenant.rfc" class="inline-flex items-center gap-1 px-2 py-1 bg-gray-100 rounded-lg text-xs text-gray-600">
              {{ tenant.rfc }}
            </span>
            <span v-if="tenant.email" class="inline-flex items-center gap-1 px-2 py-1 bg-gray-100 rounded-lg text-xs text-gray-600">
              {{ tenant.email }}
            </span>
          </div>

          <!-- Stats Row -->
          <div class="flex items-center gap-4 py-3 border-t border-gray-100 mb-4 text-sm">
            <span class="text-gray-600">{{ tenant.users_count ?? 0 }} usuarios</span>
            <span class="text-gray-600">{{ tenant.applications_count ?? 0 }} solicitudes</span>
          </div>

          <!-- Actions -->
          <div class="flex items-center gap-2">
            <button
              @click="openTenantConfig(tenant)"
              class="flex-1 flex items-center justify-center gap-2 px-4 py-2.5 bg-primary-50 text-primary-700 rounded-xl font-medium hover:bg-primary-100 transition-colors"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
              </svg>
              Configurar
            </button>
            <button
              @click="openEditModal(tenant)"
              class="p-2.5 text-gray-400 hover:text-primary-600 hover:bg-primary-50 rounded-xl transition-colors"
              title="Editar información"
            >
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
              </svg>
            </button>
            <button
              @click="confirmDelete(tenant)"
              class="p-2.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-xl transition-colors"
              title="Eliminar"
            >
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
              </svg>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Pagination -->
    <div v-if="totalPages > 1" class="flex justify-center">
      <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-2 inline-flex items-center gap-1">
        <button
          @click="currentPage > 1 && (currentPage--, fetchTenants())"
          :disabled="currentPage === 1"
          class="p-2 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 disabled:opacity-50 disabled:cursor-not-allowed"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
          </svg>
        </button>
        <button
          v-for="page in totalPages"
          :key="page"
          @click="currentPage = page; fetchTenants()"
          :class="[
            'min-w-[40px] h-10 rounded-lg text-sm font-medium transition-all',
            currentPage === page
              ? 'bg-primary-600 text-white shadow-sm'
              : 'text-gray-600 hover:bg-gray-100'
          ]"
        >
          {{ page }}
        </button>
        <button
          @click="currentPage < totalPages && (currentPage++, fetchTenants())"
          :disabled="currentPage === totalPages"
          class="p-2 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 disabled:opacity-50 disabled:cursor-not-allowed"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
          </svg>
        </button>
      </div>
    </div>

    <!-- Create/Edit Modal (Simplified - Basic Info Only) -->
    <Teleport to="body">
      <div
        v-if="showTenantModal"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50"
        @click.self="showTenantModal = false"
      >
        <div class="bg-white rounded-xl shadow-xl w-full max-w-2xl max-h-[90vh] overflow-hidden">
          <!-- Header -->
          <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
            <div>
              <h2 class="text-lg font-semibold text-gray-900">
                {{ editingTenant ? 'Editar Tenant' : 'Nuevo Tenant' }}
              </h2>
              <p class="text-sm text-gray-500">Información básica de la empresa</p>
            </div>
            <button @click="showTenantModal = false" class="p-1 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition-colors">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <!-- Content -->
          <div class="px-6 py-5 overflow-y-auto max-h-[60vh] space-y-4">
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-medium text-gray-700 mb-1.5">Nombre *</label>
                <input
                  v-model="form.name"
                  placeholder="Lendus Demo"
                  @blur="!editingTenant && !form.slug && generateSlug()"
                  :class="[
                    'w-full px-3 py-2.5 text-sm border rounded-lg transition-colors',
                    formErrors.name
                      ? 'border-red-300 bg-red-50 focus:border-red-500 focus:ring-2 focus:ring-red-500'
                      : 'border-gray-200 bg-gray-50 focus:bg-white focus:border-primary-500 focus:ring-2 focus:ring-primary-500'
                  ]"
                />
                <p v-if="formErrors.name" class="mt-1 text-xs text-red-600">{{ formErrors.name }}</p>
              </div>
              <div>
                <label class="block text-xs font-medium text-gray-700 mb-1.5">Slug *</label>
                <div class="flex items-center gap-2">
                  <input
                    v-model="form.slug"
                    placeholder="demo"
                    :disabled="!!editingTenant"
                    :class="[
                      'flex-1 px-3 py-2.5 text-sm border rounded-lg transition-colors',
                      formErrors.slug
                        ? 'border-red-300 bg-red-50 focus:border-red-500 focus:ring-2 focus:ring-red-500'
                        : 'border-gray-200 bg-gray-50 focus:bg-white focus:border-primary-500 focus:ring-2 focus:ring-primary-500',
                      editingTenant ? 'bg-gray-100 cursor-not-allowed' : ''
                    ]"
                  />
                  <span class="text-xs text-gray-500 whitespace-nowrap">.lendus.app</span>
                </div>
                <p v-if="formErrors.slug" class="mt-1 text-xs text-red-600">{{ formErrors.slug }}</p>
              </div>
            </div>

            <div>
              <label class="block text-xs font-medium text-gray-700 mb-1.5">Razón Social</label>
              <input
                v-model="form.legal_name"
                placeholder="Empresa S.A. de C.V. SOFOM E.N.R."
                class="w-full px-3 py-2.5 text-sm border border-gray-200 rounded-lg bg-gray-50 focus:bg-white focus:border-primary-500 focus:ring-2 focus:ring-primary-500 transition-colors"
              />
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-medium text-gray-700 mb-1.5">RFC</label>
                <input
                  v-model="form.rfc"
                  placeholder="ABC123456789"
                  maxlength="13"
                  class="w-full px-3 py-2.5 text-sm border border-gray-200 rounded-lg bg-gray-50 focus:bg-white focus:border-primary-500 focus:ring-2 focus:ring-primary-500 transition-colors uppercase"
                />
              </div>
              <div>
                <label class="block text-xs font-medium text-gray-700 mb-1.5">Teléfono</label>
                <input
                  v-model="formattedPhone"
                  :placeholder="PHONE_INPUT_CONFIG.placeholder"
                  :type="PHONE_INPUT_CONFIG.type"
                  :inputmode="PHONE_INPUT_CONFIG.inputMode"
                  :maxlength="PHONE_INPUT_CONFIG.maxLength"
                  class="w-full px-3 py-2.5 text-sm border border-gray-200 rounded-lg bg-gray-50 focus:bg-white focus:border-primary-500 focus:ring-2 focus:ring-primary-500 transition-colors"
                />
              </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-medium text-gray-700 mb-1.5">Email</label>
                <input
                  v-model="form.email"
                  type="email"
                  placeholder="contacto@empresa.mx"
                  :class="[
                    'w-full px-3 py-2.5 text-sm border rounded-lg transition-colors',
                    formErrors.email
                      ? 'border-red-300 bg-red-50 focus:border-red-500 focus:ring-2 focus:ring-red-500'
                      : 'border-gray-200 bg-gray-50 focus:bg-white focus:border-primary-500 focus:ring-2 focus:ring-primary-500'
                  ]"
                />
                <p v-if="formErrors.email" class="mt-1 text-xs text-red-600">{{ formErrors.email }}</p>
              </div>
              <div>
                <label class="block text-xs font-medium text-gray-700 mb-1.5">Sitio Web</label>
                <input
                  v-model="form.website"
                  placeholder="https://empresa.mx"
                  class="w-full px-3 py-2.5 text-sm border border-gray-200 rounded-lg bg-gray-50 focus:bg-white focus:border-primary-500 focus:ring-2 focus:ring-primary-500 transition-colors"
                />
              </div>
            </div>

            <div class="flex items-center gap-3 pt-3 border-t border-gray-100">
              <input
                type="checkbox"
                id="is_active"
                v-model="form.is_active"
                class="w-4 h-4 text-primary-600 border-gray-300 rounded focus:ring-primary-500"
              />
              <label for="is_active" class="text-sm text-gray-700">Tenant activo</label>
            </div>

            <!-- Hint for new tenants -->
            <div v-if="!editingTenant" class="p-3 bg-primary-50 border border-primary-100 rounded-lg">
              <p class="text-xs text-primary-700">
                <strong>Nota:</strong> Después de crear el tenant, usa el botón "Configurar" para personalizar el branding y las integraciones API.
              </p>
            </div>

            <!-- Error -->
            <div v-if="formError" class="p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-600">
              {{ formError }}
            </div>
          </div>

          <!-- Footer -->
          <div class="px-6 py-4 border-t border-gray-200 flex justify-end gap-3">
            <button
              @click="showTenantModal = false"
              class="px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors font-medium text-sm"
            >
              Cancelar
            </button>
            <button
              @click="saveTenant"
              :disabled="isSubmitting"
              class="flex items-center gap-2 px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors font-medium text-sm disabled:opacity-50 disabled:cursor-not-allowed"
            >
              <svg v-if="isSubmitting" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
              {{ editingTenant ? 'Guardar Cambios' : 'Crear Tenant' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Delete Confirmation Modal -->
    <AppConfirmModal
      :show="showDeleteModal"
      title="Eliminar Tenant"
      :message="`¿Estás seguro de eliminar el tenant '${tenantToDelete?.name}'? Esta acción no se puede deshacer.`"
      confirm-text="Eliminar"
      confirm-variant="danger"
      :loading="isDeleting"
      @confirm="deleteTenant"
      @cancel="showDeleteModal = false"
    />

    <!-- Tenant Configuration Modal -->
    <Teleport to="body">
      <div
        v-if="showConfigModal"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50"
        @click.self="showConfigModal = false"
      >
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-6xl max-h-[90vh] overflow-hidden">
          <!-- Header -->
          <div class="px-6 py-4 border-b border-gray-100 bg-gradient-to-r from-gray-50 to-white flex items-center justify-between">
            <div class="flex items-center gap-4">
              <div
                class="w-10 h-10 rounded-xl flex items-center justify-center text-white font-bold shadow-md"
                :style="{ backgroundColor: configTenant?.branding?.primary_color || '#6366f1' }"
              >
                {{ configTenant?.name?.charAt(0) }}
              </div>
              <div>
                <h2 class="text-lg font-bold text-gray-900">{{ configTenant?.name }}</h2>
                <p class="text-sm text-gray-500">{{ configTenant?.domain || `${configTenant?.slug}.lendus.app` }}</p>
              </div>
            </div>
            <button @click="showConfigModal = false" class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition-colors">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <!-- Tabs -->
          <div class="px-6 border-b border-gray-100 bg-gray-50/50">
            <div class="flex gap-1">
              <button
                v-for="tab in [
                  { id: 'branding', label: 'Branding', icon: 'M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01' },
                  { id: 'apis', label: 'Integraciones', icon: 'M13 10V3L4 14h7v7l9-11h-7z' },
                  { id: 'custom', label: 'Personalizaciones', icon: 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z' }
                ]"
                :key="tab.id"
                @click="configActiveTab = tab.id as 'branding' | 'apis' | 'custom'"
                :class="[
                  'flex items-center gap-2 px-4 py-3 text-sm font-medium transition-all border-b-2 -mb-px',
                  configActiveTab === tab.id
                    ? 'border-primary-500 text-primary-600 bg-white rounded-t-lg'
                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:bg-white/50 rounded-t-lg'
                ]"
              >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="tab.icon" />
                </svg>
                {{ tab.label }}
              </button>
            </div>
          </div>

          <!-- Loading -->
          <div v-if="isLoadingConfig" class="flex justify-center py-12">
            <div class="animate-spin w-8 h-8 border-4 border-primary-500 border-t-transparent rounded-full"></div>
          </div>

          <!-- Content -->
          <div v-else-if="configData" class="overflow-y-auto max-h-[65vh]">
            <!-- Branding Tab - Using Component -->
            <div v-show="configActiveTab === 'branding'" class="min-h-[500px]">
              <TenantBrandingEditor
                v-model="configData.branding"
                :tenant="{ name: configTenant?.name || '', slug: configTenant?.slug || '' }"
                :show-preview-toggle="true"
              />
            </div>

            <!-- Personalizaciones Tab (read-only). Refleja lo que el tenant
                 declara en `frontend/tenants/<slug>.tenant.ts`. Las piezas
                 marcadas como "custom hard" NO respetan el branding del
                 admin: son componentes Vue dedicados, requieren release
                 para cambiar. -->
            <div v-show="configActiveTab === 'custom'" class="p-6 space-y-4">
              <div class="mb-4">
                <h3 class="text-sm font-semibold text-gray-900">Personalizaciones del tenant</h3>
                <p class="text-xs text-gray-500 mt-0.5">
                  Indica qué pedazos del frontend tienen un componente Vue dedicado
                  y NO respetan el branding configurado arriba. Para cambiarlos
                  hay que pedir release del equipo de desarrollo.
                </p>
              </div>
              <ul class="divide-y divide-gray-100 rounded-xl border border-gray-200 bg-white">
                <li v-for="row in customizationRows" :key="row.key" class="flex items-start gap-3 p-4">
                  <span
                    :class="[
                      'mt-0.5 flex-shrink-0 w-2 h-2 rounded-full',
                      row.value ? 'bg-emerald-500' : 'bg-gray-300',
                    ]"
                  />
                  <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2">
                      <p class="text-sm font-medium text-gray-900">{{ row.label }}</p>
                      <span
                        v-if="row.value"
                        class="px-1.5 py-0.5 text-xs font-medium rounded-md bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-200"
                      >
                        Custom hard
                      </span>
                      <span
                        v-else
                        class="px-1.5 py-0.5 text-xs font-medium rounded-md bg-gray-50 text-gray-600 ring-1 ring-inset ring-gray-200"
                      >
                        Estándar
                      </span>
                    </div>
                    <p class="text-xs text-gray-500 mt-1">
                      <template v-if="row.value">
                        Componente: <code class="font-mono text-gray-700">{{ row.value }}</code>
                        · {{ row.description }}
                      </template>
                      <template v-else>
                        {{ row.fallbackDescription }} · Respeta el branding del admin.
                      </template>
                    </p>
                  </div>
                </li>
              </ul>
              <p class="text-xs text-gray-400 mt-3">
                Para agregar/quitar customizaciones, edita
                <code class="font-mono text-gray-600">frontend/tenants/{{ configTenant?.slug }}.tenant.ts</code>
                y haz release.
              </p>
            </div>

            <!-- APIs Tab: gestionado por el componente compartido IntegrationsManager -->
            <div v-show="configActiveTab === 'apis'" class="p-6">
              <IntegrationsManager
                v-if="configTenant"
                :key="configTenant.id"
                :adapter="tenantIntegrationsAdapter(configTenant.id)"
                embedded
              />
            </div>
          </div>

          <!-- Footer -->
          <div class="px-6 py-4 border-t border-gray-200 flex justify-between items-center">
            <div>
              <!-- Success/Error messages -->
              <p v-if="configSaveMessage" class="text-sm text-green-600 flex items-center gap-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                {{ configSaveMessage }}
              </p>
              <p v-if="configSaveError" class="text-sm text-red-600 flex items-center gap-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
                {{ configSaveError }}
              </p>
            </div>
            <div class="flex items-center gap-3">
              <button
                @click="showConfigModal = false"
                class="px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors font-medium text-sm"
              >
                Cerrar
              </button>
              <button
                v-if="configActiveTab === 'branding'"
                @click="saveTenantBranding"
                :disabled="isSavingConfig"
                class="flex items-center gap-2 px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors font-medium text-sm disabled:opacity-50 disabled:cursor-not-allowed"
              >
                <svg v-if="isSavingConfig" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                Guardar Cambios
              </button>
            </div>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>
