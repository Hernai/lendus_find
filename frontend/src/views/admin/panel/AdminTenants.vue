<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { api } from '@/services/api'
import { AppInput, AppConfirmModal } from '@/components/common'
import TenantBrandingEditor, { type Branding } from '@/components/admin/TenantBrandingEditor.vue'

interface Tenant {
  id: string
  name: string
  slug: string
  legal_name: string | null
  rfc: string | null
  email: string | null
  phone: string | null
  website: string | null
  is_active: boolean
  branding: {
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

// Config modal state
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
  last_tested_at: string | null
  last_test_success: boolean | null
}

interface TenantConfig {
  branding: {
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
  api_configs: ApiConfig[]
  available_providers: Record<string, string>
  available_service_types: Record<string, string>
}

const showConfigModal = ref(false)
const configTenant = ref<Tenant | null>(null)
const configData = ref<TenantConfig | null>(null)
const isLoadingConfig = ref(false)
const isSavingConfig = ref(false)
const configActiveTab = ref<'branding' | 'apis'>('branding')
const configSaveMessage = ref('')
const configSaveError = ref('')

// Preview state
const showPreview = ref(true)

// Collapsible branding sections
const brandingSections = ref({
  logos: true,
  colors: true,
  typography: false
})
const previewMode = ref<'desktop' | 'mobile'>('desktop')

// Logo upload state
const logoUploadRefs = ref<Record<string, HTMLInputElement | null>>({})
const uploadingLogo = ref<string | null>(null)

// Suggested fonts
const suggestedFonts = [
  'Inter, sans-serif',
  'Roboto, sans-serif',
  'Open Sans, sans-serif',
  'Montserrat, sans-serif',
  'Poppins, sans-serif',
  'Lato, sans-serif',
  'Source Sans Pro, sans-serif',
  'Nunito, sans-serif'
]

// Suggested icons/logos (SVG paths for common business icons)
const suggestedIcons = [
  // Row 1
  { name: 'Building', svg: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M12 7V3H2v18h20V7H12zM6 19H4v-2h2v2zm0-4H4v-2h2v2zm0-4H4V9h2v2zm0-4H4V5h2v2zm4 12H8v-2h2v2zm0-4H8v-2h2v2zm0-4H8V9h2v2zm0-4H8V5h2v2zm10 12h-8v-2h2v-2h-2v-2h2v-2h-2V9h8v10zm-2-8h-2v2h2v-2zm0 4h-2v2h2v-2z"/></svg>' },
  { name: 'Bank', svg: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M4 10h3v7H4zM10.5 10h3v7h-3zM2 19h20v3H2zM17 10h3v7h-3zM12 1L2 6v2h20V6z"/></svg>' },
  { name: 'Wallet', svg: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M21 18v1c0 1.1-.9 2-2 2H5c-1.11 0-2-.9-2-2V5c0-1.1.89-2 2-2h14c1.1 0 2 .9 2 2v1h-9c-1.11 0-2 .9-2 2v8c0 1.1.89 2 2 2h9zm-9-2h10V8H12v8zm4-2.5c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5z"/></svg>' },
  { name: 'Credit Card', svg: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M20 4H4c-1.11 0-1.99.89-1.99 2L2 18c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V6c0-1.11-.89-2-2-2zm0 14H4v-6h16v6zm0-10H4V6h16v2z"/></svg>' },
  { name: 'Money', svg: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M11.8 10.9c-2.27-.59-3-1.2-3-2.15 0-1.09 1.01-1.85 2.7-1.85 1.78 0 2.44.85 2.5 2.1h2.21c-.07-1.72-1.12-3.3-3.21-3.81V3h-3v2.16c-1.94.42-3.5 1.68-3.5 3.61 0 2.31 1.91 3.46 4.7 4.13 2.5.6 3 1.48 3 2.41 0 .69-.49 1.79-2.7 1.79-2.06 0-2.87-.92-2.98-2.1h-2.2c.12 2.19 1.76 3.42 3.68 3.83V21h3v-2.15c1.95-.37 3.5-1.5 3.5-3.55 0-2.84-2.43-3.81-4.7-4.4z"/></svg>' },
  { name: 'Chart', svg: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M3.5 18.49l6-6.01 4 4L22 6.92l-1.41-1.41-7.09 7.97-4-4L2 16.99z"/></svg>' },
  { name: 'Shield', svg: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm0 10.99h7c-.53 4.12-3.28 7.79-7 8.94V12H5V6.3l7-3.11v8.8z"/></svg>' },
  { name: 'Star', svg: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>' },
  // Row 2
  { name: 'Bolt', svg: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M11 21h-1l1-7H7.5c-.58 0-.57-.32-.38-.66.19-.34.05-.08.07-.12C8.48 10.94 10.42 7.54 13 3h1l-1 7h3.5c.49 0 .56.33.47.51l-.07.15C12.96 17.55 11 21 11 21z"/></svg>' },
  { name: 'Globe', svg: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/></svg>' },
  { name: 'Users', svg: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>' },
  { name: 'Lock', svg: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/></svg>' },
  { name: 'Heart', svg: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>' },
  { name: 'Diamond', svg: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M19 3H5L2 9l10 12L22 9l-3-6zM9.62 8l1.5-3h1.76l1.5 3H9.62zM11 10v6.68L5.44 10H11zm2 0h5.56L13 16.68V10zm6.26-2h-2.65l-1.5-3h2.65l1.5 3zM6.24 5h2.65l-1.5 3H4.74l1.5-3z"/></svg>' }
]

// API Config form in config modal
const showApiFormInConfig = ref(false)
const showProvidersReference = ref(false)
const editingApiInConfig = ref<ApiConfig | null>(null)
const apiFormConfig = ref({
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

// Options
const otpProviders = [
  { value: 'twilio', label: 'Twilio' },
  { value: 'messagebird', label: 'MessageBird' },
  { value: 'vonage', label: 'Vonage' }
]

const kycProviders = [
  { value: 'mati', label: 'Mati (Metamap)' },
  { value: 'onfido', label: 'Onfido' },
  { value: 'jumio', label: 'Jumio' }
]

const webhookEvents = [
  'APPLICATION_SUBMITTED',
  'APPLICATION_APPROVED',
  'APPLICATION_REJECTED',
  'APPLICATION_DISBURSED',
  'DOCUMENT_UPLOADED',
  'DOCUMENT_APPROVED'
]

// Fetch tenants
const fetchTenants = async () => {
  isLoading.value = true
  error.value = ''

  try {
    const params: Record<string, unknown> = {
      page: currentPage.value,
      per_page: 20,
      search: searchQuery.value || undefined
    }

    if (activeFilter.value === 'active') {
      params.active = true
    } else if (activeFilter.value === 'inactive') {
      params.active = false
    }

    const response = await api.get<{
      data: Tenant[]
      meta: { current_page: number; last_page: number; total: number }
    }>('/admin/tenants', { params })

    tenants.value = response.data.data
    totalPages.value = response.data.meta.last_page
    totalItems.value = response.data.meta.total
  } catch (e) {
    console.error('Failed to fetch tenants:', e)
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
      await api.put(`/admin/tenants/${editingTenant.value.id}`, payload)
    } else {
      await api.post('/admin/tenants', payload)
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
    await api.delete(`/admin/tenants/${tenantToDelete.value.id}`)
    showDeleteModal.value = false
    tenantToDelete.value = null
    await fetchTenants()
  } catch (e: unknown) {
    const err = e as { message?: string; error?: string }
    formError.value = err.message || 'Error al eliminar el tenant'
    if (err.error === 'HAS_RELATED_DATA') {
      formError.value = 'No se puede eliminar porque tiene usuarios o solicitudes'
    }
  } finally {
    isDeleting.value = false
  }
}

// Helpers
const formatDate = (dateStr: string | null | undefined) => {
  if (!dateStr) return '-'
  return new Date(dateStr).toLocaleDateString('es-MX', {
    year: 'numeric',
    month: 'short',
    day: 'numeric'
  })
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

const toggleEvent = (event: string) => {
  const idx = form.value.webhook_config.events.indexOf(event)
  if (idx === -1) {
    form.value.webhook_config.events.push(event)
  } else {
    form.value.webhook_config.events.splice(idx, 1)
  }
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
    const response = await api.get<{ data: TenantConfig }>(`/admin/tenants/${tenant.id}/config`)
    configData.value = response.data.data
  } catch (e) {
    console.error('Failed to load tenant config:', e)
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
    await api.put(`/admin/tenants/${configTenant.value.id}/branding`, configData.value.branding)
    configSaveMessage.value = 'Branding guardado'
    setTimeout(() => configSaveMessage.value = '', 3000)
  } catch (e) {
    configSaveError.value = 'Error al guardar el branding'
  } finally {
    isSavingConfig.value = false
  }
}

// API Config methods for config modal
const openAddApiInConfig = () => {
  editingApiInConfig.value = null
  apiFormConfig.value = {
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
  showApiFormInConfig.value = true
}

const openEditApiInConfig = (config: ApiConfig) => {
  editingApiInConfig.value = config
  apiFormConfig.value = {
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
  showApiFormInConfig.value = true
}

const saveApiInConfig = async () => {
  if (!configTenant.value) return

  isSavingConfig.value = true
  configSaveError.value = ''

  try {
    const payload: Record<string, unknown> = {
      provider: apiFormConfig.value.provider,
      service_type: apiFormConfig.value.service_type,
      from_number: apiFormConfig.value.from_number || null,
      from_email: apiFormConfig.value.from_email || null,
      domain: apiFormConfig.value.domain || null,
      is_active: apiFormConfig.value.is_active,
      is_sandbox: apiFormConfig.value.is_sandbox
    }

    if (apiFormConfig.value.api_key) payload.api_key = apiFormConfig.value.api_key
    if (apiFormConfig.value.api_secret) payload.api_secret = apiFormConfig.value.api_secret
    if (apiFormConfig.value.account_sid) payload.account_sid = apiFormConfig.value.account_sid
    if (apiFormConfig.value.auth_token) payload.auth_token = apiFormConfig.value.auth_token

    await api.post(`/admin/tenants/${configTenant.value.id}/api-configs`, payload)
    showApiFormInConfig.value = false

    // Reload config
    const response = await api.get<{ data: TenantConfig }>(`/admin/tenants/${configTenant.value.id}/config`)
    configData.value = response.data.data
    configSaveMessage.value = 'Integración guardada'
    setTimeout(() => configSaveMessage.value = '', 3000)
  } catch (e) {
    configSaveError.value = 'Error al guardar la integración'
  } finally {
    isSavingConfig.value = false
  }
}

const deleteApiInConfig = async (config: ApiConfig) => {
  if (!configTenant.value || !confirm(`¿Eliminar ${config.provider_label}?`)) return

  try {
    await api.delete(`/admin/tenants/${configTenant.value.id}/api-configs/${config.id}`)
    // Reload config
    const response = await api.get<{ data: TenantConfig }>(`/admin/tenants/${configTenant.value.id}/config`)
    configData.value = response.data.data
  } catch (e) {
    alert('Error al eliminar')
  }
}

const testApiInConfig = async (config: ApiConfig) => {
  if (!configTenant.value) return

  try {
    const response = await api.post<{ success: boolean; message: string }>(
      `/admin/tenants/${configTenant.value.id}/api-configs/${config.id}/test`
    )
    // Reload config
    const reloadResponse = await api.get<{ data: TenantConfig }>(`/admin/tenants/${configTenant.value.id}/config`)
    configData.value = reloadResponse.data.data
    alert(response.data.message)
  } catch (e) {
    alert('Error al probar la conexión')
  }
}

// Get provider fields for API form
const getProviderFieldsConfig = computed(() => {
  const provider = apiFormConfig.value.provider
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

// Logo upload handler
const triggerLogoUpload = (field: string) => {
  const input = logoUploadRefs.value[field]
  if (input) input.click()
}

const handleLogoUpload = async (event: Event, field: string) => {
  const target = event.target as HTMLInputElement
  const file = target.files?.[0]
  if (!file || !configTenant.value || !configData.value) return

  // Validate file
  const maxSize = 2 * 1024 * 1024 // 2MB
  const allowedTypes = ['image/png', 'image/jpeg', 'image/svg+xml', 'image/webp', 'image/x-icon']

  if (!allowedTypes.includes(file.type)) {
    configSaveError.value = 'Formato no válido. Usa PNG, JPG, SVG o WebP'
    return
  }

  if (file.size > maxSize) {
    configSaveError.value = 'El archivo es muy grande. Máximo 2MB'
    return
  }

  uploadingLogo.value = field
  configSaveError.value = ''

  try {
    const formData = new FormData()
    formData.append('file', file)
    formData.append('field', field)

    const response = await api.post<{ url: string }>(
      `/admin/tenants/${configTenant.value.id}/upload-logo`,
      formData
    )

    // Update the branding field with the new URL
    const brandingRecord = configData.value.branding as Record<string, string | null>
    brandingRecord[field] = response.data.url
    configSaveMessage.value = 'Logo subido correctamente'
    setTimeout(() => configSaveMessage.value = '', 3000)
  } catch (e) {
    configSaveError.value = 'Error al subir el logo'
  } finally {
    uploadingLogo.value = null
    // Reset file input
    target.value = ''
  }
}

// Select suggested icon as logo
const selectSuggestedIcon = (iconSvg: string, primaryColor: string) => {
  if (!configData.value) return

  // Create a data URL from the SVG with the primary color
  const coloredSvg = iconSvg.replace('currentColor', primaryColor)
  const base64 = btoa(coloredSvg)
  configData.value.branding.logo_url = `data:image/svg+xml;base64,${base64}`
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
            class="w-full pl-9 pr-3 py-2 text-sm border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent bg-gray-50 focus:bg-white transition-colors"
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
              :href="`https://${tenant.slug}.losapp.com`"
              target="_blank"
              class="text-sm text-gray-500 hover:text-primary-600 inline-flex items-center gap-1"
            >
              {{ tenant.slug }}.losapp.com
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
                    'w-full px-3 py-2 text-sm border rounded-lg transition-colors',
                    formErrors.name
                      ? 'border-red-300 bg-red-50 focus:border-red-400 focus:ring-1 focus:ring-red-400'
                      : 'border-gray-200 bg-gray-50 focus:bg-white focus:border-primary-400 focus:ring-1 focus:ring-primary-400'
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
                      'flex-1 px-3 py-2 text-sm border rounded-lg transition-colors',
                      formErrors.slug
                        ? 'border-red-300 bg-red-50 focus:border-red-400 focus:ring-1 focus:ring-red-400'
                        : 'border-gray-200 bg-gray-50 focus:bg-white focus:border-primary-400 focus:ring-1 focus:ring-primary-400',
                      editingTenant ? 'bg-gray-100 cursor-not-allowed' : ''
                    ]"
                  />
                  <span class="text-xs text-gray-500 whitespace-nowrap">.losapp.com</span>
                </div>
                <p v-if="formErrors.slug" class="mt-1 text-xs text-red-600">{{ formErrors.slug }}</p>
              </div>
            </div>

            <div>
              <label class="block text-xs font-medium text-gray-700 mb-1.5">Razón Social</label>
              <input
                v-model="form.legal_name"
                placeholder="Empresa S.A. de C.V. SOFOM E.N.R."
                class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg bg-gray-50 focus:bg-white focus:border-primary-400 focus:ring-1 focus:ring-primary-400 transition-colors"
              />
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-medium text-gray-700 mb-1.5">RFC</label>
                <input
                  v-model="form.rfc"
                  placeholder="ABC123456789"
                  maxlength="13"
                  class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg bg-gray-50 focus:bg-white focus:border-primary-400 focus:ring-1 focus:ring-primary-400 transition-colors uppercase"
                />
              </div>
              <div>
                <label class="block text-xs font-medium text-gray-700 mb-1.5">Teléfono</label>
                <input
                  v-model="form.phone"
                  placeholder="5555555555"
                  type="tel"
                  class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg bg-gray-50 focus:bg-white focus:border-primary-400 focus:ring-1 focus:ring-primary-400 transition-colors"
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
                    'w-full px-3 py-2 text-sm border rounded-lg transition-colors',
                    formErrors.email
                      ? 'border-red-300 bg-red-50 focus:border-red-400 focus:ring-1 focus:ring-red-400'
                      : 'border-gray-200 bg-gray-50 focus:bg-white focus:border-primary-400 focus:ring-1 focus:ring-primary-400'
                  ]"
                />
                <p v-if="formErrors.email" class="mt-1 text-xs text-red-600">{{ formErrors.email }}</p>
              </div>
              <div>
                <label class="block text-xs font-medium text-gray-700 mb-1.5">Sitio Web</label>
                <input
                  v-model="form.website"
                  placeholder="https://empresa.mx"
                  class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg bg-gray-50 focus:bg-white focus:border-primary-400 focus:ring-1 focus:ring-primary-400 transition-colors"
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
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm"
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
                <p class="text-sm text-gray-500">{{ configTenant?.slug }}.losapp.com</p>
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
                  { id: 'apis', label: 'Integraciones', icon: 'M13 10V3L4 14h7v7l9-11h-7z' }
                ]"
                :key="tab.id"
                @click="configActiveTab = tab.id as 'branding' | 'apis'"
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

            <!-- APIs Tab -->
            <div v-show="configActiveTab === 'apis'" class="p-6 space-y-4">
              <!-- Empty State -->
              <div v-if="configData.api_configs.length === 0 && !showApiFormInConfig" class="text-center py-6">
                <div class="w-12 h-12 mx-auto mb-3 rounded-full bg-gray-100 flex items-center justify-center">
                  <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                  </svg>
                </div>
                <h3 class="text-base font-medium text-gray-900 mb-1">Sin integraciones</h3>
                <p class="text-sm text-gray-500 mb-4">Configura proveedores de SMS, Email, KYC y Buró.</p>
                <button
                  @click="openAddApiInConfig"
                  class="inline-flex items-center gap-2 px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors font-medium text-sm"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                  </svg>
                  Agregar Integración
                </button>
              </div>

              <!-- Header + Add Button (when there are configs or form is hidden) -->
              <div v-if="configData.api_configs.length > 0 && !showApiFormInConfig" class="flex items-center justify-between">
                <div>
                  <h3 class="text-sm font-semibold text-gray-900">Integraciones</h3>
                  <p class="text-xs text-gray-500">{{ configData.api_configs.length }} configurada(s)</p>
                </div>
                <button
                  @click="openAddApiInConfig"
                  class="flex items-center gap-1.5 px-3 py-1.5 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors font-medium text-xs"
                >
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                  </svg>
                  Agregar
                </button>
              </div>

              <!-- Configured APIs Grid -->
              <div v-if="configData.api_configs.length > 0" class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div
                  v-for="config in configData.api_configs"
                  :key="config.id"
                  class="p-3 border rounded-lg transition-all hover:shadow-sm"
                  :class="config.is_active ? 'border-green-200 bg-green-50/30' : 'border-gray-200 bg-gray-50'"
                >
                  <div class="flex items-center justify-between mb-2">
                    <div class="flex items-center gap-2">
                      <div
                        class="w-8 h-8 rounded-lg flex items-center justify-center text-white text-sm font-bold"
                        :class="config.is_active ? 'bg-green-500' : 'bg-gray-400'"
                      >
                        {{ config.provider_label.charAt(0) }}
                      </div>
                      <div>
                        <h5 class="font-medium text-sm text-gray-900">{{ config.provider_label }}</h5>
                        <p class="text-xs text-gray-500">{{ config.service_type_label }}</p>
                      </div>
                    </div>
                    <div class="flex items-center gap-0.5">
                      <button
                        @click="openEditApiInConfig(config)"
                        class="p-1.5 text-gray-400 hover:text-primary-600 hover:bg-primary-50 rounded"
                        title="Editar"
                      >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                      </button>
                      <button
                        @click="testApiInConfig(config)"
                        class="p-1.5 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded"
                        title="Probar conexión"
                      >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                      </button>
                      <button
                        @click="deleteApiInConfig(config)"
                        class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded"
                        title="Eliminar"
                      >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                      </button>
                    </div>
                  </div>
                  <div class="flex flex-wrap gap-1.5">
                    <span
                      :class="[
                        'px-1.5 py-0.5 rounded text-xs font-medium',
                        config.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-200 text-gray-600'
                      ]"
                    >
                      {{ config.is_active ? 'Activo' : 'Inactivo' }}
                    </span>
                    <span v-if="config.is_sandbox" class="px-1.5 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-700">
                      Sandbox
                    </span>
                    <span
                      :class="[
                        'px-1.5 py-0.5 rounded text-xs font-medium',
                        config.has_credentials ? 'bg-blue-100 text-blue-700' : 'bg-red-100 text-red-700'
                      ]"
                    >
                      {{ config.has_credentials ? 'Credenciales OK' : 'Sin credenciales' }}
                    </span>
                    <span
                      v-if="config.last_test_success !== null"
                      :class="[
                        'px-1.5 py-0.5 rounded text-xs font-medium',
                        config.last_test_success ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700'
                      ]"
                    >
                      {{ config.last_test_success ? 'Test OK' : 'Test falló' }}
                    </span>
                  </div>
                </div>
              </div>

              <!-- Available Providers Reference (Collapsible) -->
              <div class="border border-gray-200 rounded-lg overflow-hidden">
                <button
                  @click="showProvidersReference = !showProvidersReference"
                  class="w-full px-4 py-3 bg-gray-50 flex items-center justify-between hover:bg-gray-100 transition-colors"
                >
                  <div class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span class="text-sm font-medium text-gray-700">Ver proveedores disponibles</span>
                  </div>
                  <svg
                    class="w-4 h-4 text-gray-400 transition-transform"
                    :class="showProvidersReference ? 'rotate-180' : ''"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                  >
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                  </svg>
                </button>

                <div v-show="showProvidersReference" class="p-4 bg-white border-t border-gray-200">
                  <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                    <!-- SMS/WhatsApp -->
                    <div class="text-center p-3 bg-purple-50 rounded-lg">
                      <div class="w-8 h-8 mx-auto mb-2 rounded-lg bg-purple-100 flex items-center justify-center">
                        <svg class="w-4 h-4 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" />
                        </svg>
                      </div>
                      <p class="text-xs font-medium text-gray-900 mb-1">SMS / WhatsApp</p>
                      <p class="text-xs text-gray-500">Twilio, MessageBird, Vonage</p>
                    </div>

                    <!-- Email -->
                    <div class="text-center p-3 bg-blue-50 rounded-lg">
                      <div class="w-8 h-8 mx-auto mb-2 rounded-lg bg-blue-100 flex items-center justify-center">
                        <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                      </div>
                      <p class="text-xs font-medium text-gray-900 mb-1">Email</p>
                      <p class="text-xs text-gray-500">Mailgun, SendGrid, SES</p>
                    </div>

                    <!-- KYC -->
                    <div class="text-center p-3 bg-green-50 rounded-lg">
                      <div class="w-8 h-8 mx-auto mb-2 rounded-lg bg-green-100 flex items-center justify-center">
                        <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                        </svg>
                      </div>
                      <p class="text-xs font-medium text-gray-900 mb-1">KYC / Identidad</p>
                      <p class="text-xs text-gray-500">Mati, Onfido, Jumio, Nubarium</p>
                    </div>

                    <!-- Buró -->
                    <div class="text-center p-3 bg-amber-50 rounded-lg">
                      <div class="w-8 h-8 mx-auto mb-2 rounded-lg bg-amber-100 flex items-center justify-center">
                        <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                      </div>
                      <p class="text-xs font-medium text-gray-900 mb-1">Buró de Crédito</p>
                      <p class="text-xs text-gray-500">Círculo de Crédito</p>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Add/Edit API Form -->
              <div v-if="showApiFormInConfig" class="p-5 border-2 border-primary-300 rounded-xl bg-white shadow-lg">
                <div class="flex items-center justify-between mb-4">
                  <h4 class="text-lg font-semibold text-gray-900">
                    {{ editingApiInConfig ? 'Editar Integración' : 'Nueva Integración' }}
                  </h4>
                  <button @click="showApiFormInConfig = false" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                  </button>
                </div>

                <div class="space-y-4">
                  <div class="grid grid-cols-2 gap-4">
                    <div>
                      <label class="block text-xs font-medium text-gray-700 mb-1.5">Proveedor *</label>
                      <select
                        v-model="apiFormConfig.provider"
                        :disabled="!!editingApiInConfig"
                        class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg bg-gray-50 focus:bg-white focus:border-primary-400 focus:ring-1 focus:ring-primary-400 transition-colors disabled:bg-gray-100 disabled:cursor-not-allowed"
                      >
                        <option value="">Seleccionar proveedor...</option>
                        <option v-for="(label, key) in configData.available_providers" :key="key" :value="key">
                          {{ label }}
                        </option>
                      </select>
                    </div>
                    <div>
                      <label class="block text-xs font-medium text-gray-700 mb-1.5">Tipo de Servicio *</label>
                      <select
                        v-model="apiFormConfig.service_type"
                        :disabled="!!editingApiInConfig"
                        class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg bg-gray-50 focus:bg-white focus:border-primary-400 focus:ring-1 focus:ring-primary-400 transition-colors disabled:bg-gray-100 disabled:cursor-not-allowed"
                      >
                        <option value="">Seleccionar servicio...</option>
                        <option v-for="(label, key) in configData.available_service_types" :key="key" :value="key">
                          {{ label }}
                        </option>
                      </select>
                    </div>
                  </div>

                  <!-- Dynamic fields based on provider -->
                  <div v-if="apiFormConfig.provider" class="p-4 bg-white border border-gray-200 rounded-lg space-y-4">
                    <h5 class="text-xs font-medium text-gray-700">Credenciales de {{ configData.available_providers[apiFormConfig.provider] }}</h5>

                    <div class="grid grid-cols-2 gap-4">
                      <div v-if="getProviderFieldsConfig.includes('account_sid')">
                        <label class="block text-xs font-medium text-gray-700 mb-1.5">Account SID</label>
                        <input
                          v-model="apiFormConfig.account_sid"
                          :placeholder="editingApiInConfig ? '(dejar vacío para mantener)' : 'ACxxxxxxxxx'"
                          class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg bg-gray-50 focus:bg-white focus:border-primary-400 focus:ring-1 focus:ring-primary-400 transition-colors"
                        />
                      </div>
                      <div v-if="getProviderFieldsConfig.includes('auth_token')">
                        <label class="block text-xs font-medium text-gray-700 mb-1.5">Auth Token</label>
                        <input
                          v-model="apiFormConfig.auth_token"
                          type="password"
                          :placeholder="editingApiInConfig ? '(dejar vacío para mantener)' : ''"
                          class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg bg-gray-50 focus:bg-white focus:border-primary-400 focus:ring-1 focus:ring-primary-400 transition-colors"
                        />
                      </div>
                      <div v-if="getProviderFieldsConfig.includes('api_key')">
                        <label class="block text-xs font-medium text-gray-700 mb-1.5">API Key</label>
                        <input
                          v-model="apiFormConfig.api_key"
                          :placeholder="editingApiInConfig ? '(dejar vacío para mantener)' : ''"
                          class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg bg-gray-50 focus:bg-white focus:border-primary-400 focus:ring-1 focus:ring-primary-400 transition-colors"
                        />
                      </div>
                      <div v-if="getProviderFieldsConfig.includes('api_secret')">
                        <label class="block text-xs font-medium text-gray-700 mb-1.5">API Secret</label>
                        <input
                          v-model="apiFormConfig.api_secret"
                          type="password"
                          :placeholder="editingApiInConfig ? '(dejar vacío para mantener)' : ''"
                          class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg bg-gray-50 focus:bg-white focus:border-primary-400 focus:ring-1 focus:ring-primary-400 transition-colors"
                        />
                      </div>
                      <div v-if="getProviderFieldsConfig.includes('from_number')">
                        <label class="block text-xs font-medium text-gray-700 mb-1.5">Número de Origen</label>
                        <input
                          v-model="apiFormConfig.from_number"
                          placeholder="+521234567890"
                          class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg bg-gray-50 focus:bg-white focus:border-primary-400 focus:ring-1 focus:ring-primary-400 transition-colors"
                        />
                      </div>
                      <div v-if="getProviderFieldsConfig.includes('from_email')">
                        <label class="block text-xs font-medium text-gray-700 mb-1.5">Email de Origen</label>
                        <input
                          v-model="apiFormConfig.from_email"
                          type="email"
                          placeholder="noreply@empresa.mx"
                          class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg bg-gray-50 focus:bg-white focus:border-primary-400 focus:ring-1 focus:ring-primary-400 transition-colors"
                        />
                      </div>
                      <div v-if="getProviderFieldsConfig.includes('domain')">
                        <label class="block text-xs font-medium text-gray-700 mb-1.5">Dominio</label>
                        <input
                          v-model="apiFormConfig.domain"
                          placeholder="mg.empresa.mx"
                          class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg bg-gray-50 focus:bg-white focus:border-primary-400 focus:ring-1 focus:ring-primary-400 transition-colors"
                        />
                      </div>
                    </div>
                  </div>

                  <div class="flex items-center gap-6 pt-2">
                    <label class="flex items-center gap-2 cursor-pointer">
                      <input type="checkbox" v-model="apiFormConfig.is_active" class="w-4 h-4 text-primary-600 rounded border-gray-300 focus:ring-primary-500" />
                      <span class="text-sm text-gray-700">Activo</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                      <input type="checkbox" v-model="apiFormConfig.is_sandbox" class="w-4 h-4 text-yellow-500 rounded border-gray-300 focus:ring-yellow-500" />
                      <span class="text-sm text-gray-700">Modo Sandbox/Pruebas</span>
                    </label>
                  </div>

                  <div class="flex justify-end gap-3 pt-4 border-t border-gray-200">
                    <button
                      @click="showApiFormInConfig = false"
                      class="px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors font-medium text-sm"
                    >
                      Cancelar
                    </button>
                    <button
                      @click="saveApiInConfig"
                      :disabled="isSavingConfig || !apiFormConfig.provider || !apiFormConfig.service_type"
                      class="flex items-center gap-2 px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors font-medium text-sm disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                      <svg v-if="isSavingConfig" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                      </svg>
                      <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                      </svg>
                      {{ editingApiInConfig ? 'Actualizar' : 'Guardar' }} Integración
                    </button>
                  </div>
                </div>
              </div>
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
