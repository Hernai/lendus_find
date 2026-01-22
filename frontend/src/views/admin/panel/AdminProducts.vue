<script setup lang="ts">
import { ref, computed, onMounted, onBeforeMount, onBeforeUnmount, watch } from 'vue'
import { v2 } from '@/services/v2'
import type { V2Product, V2TermConfig } from '@/services/v2/product.staff.service'
import { AppButton } from '@/components/common'
import { useToast } from '@/composables'
import { useAuthStore, useTenantStore } from '@/stores'
import { logger } from '@/utils/logger'
import { formatMoney } from '@/utils/formatters'

const log = logger.child('AdminProducts')
const toast = useToast()
const authStore = useAuthStore()
const tenantStore = useTenantStore()

// Permission check
const canManageProducts = computed(() => authStore.permissions?.canManageProducts ?? false)

// Use V2 Product type
type Product = V2Product
type TermConfig = V2TermConfig

// Build type labels from backend enum
const typeLabels = computed(() => {
  const labels: Record<string, string> = {}
  for (const opt of tenantStore.options.productType) {
    labels[opt.value] = opt.label
  }
  return labels
})

// SVG paths for product type icons (Heroicons style)
const typeIcons: Record<string, string> = {
  // Persona - icono de usuario
  PERSONAL: 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z',
  // Auto - icono de carro
  AUTO: 'M8 16.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zM15 16.5a1.5 1.5 0 103 0 1.5 1.5 0 00-3 0zM3 11l1.5-5.5a2 2 0 011.9-1.5h7.2a2 2 0 011.9 1.5L17 11M3 11v5a1 1 0 001 1h1m12-6v5a1 1 0 01-1 1h-1m-8 0h4',
  // Casa - icono de hogar
  HIPOTECARIO: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
  // Edificio - icono de oficina/empresa
  PYME: 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4',
  // Nómina - icono de billete/dinero con persona
  NOMINA: 'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z',
  // Arrendamiento - icono de llave
  ARRENDAMIENTO: 'M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z'
}

// Default icon for unknown types
const defaultIcon = 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4'

// Build frequency labels from backend enum
const frequencyLabels = computed(() => {
  const labels: Record<string, string> = {}
  for (const opt of tenantStore.options.paymentFrequency) {
    labels[opt.value] = opt.label
  }
  return labels
})

// Document labels - loaded from backend
const documentLabels = ref<Record<string, string>>({})
const allDocumentOptions = computed(() =>
  Object.entries(documentLabels.value).map(([value, label]) => ({ value, label }))
)

// Documents specific to nationals (exclude foreigner documents)
const nationalDocumentOptions = computed(() => {
  const excludedTypes = ['PASSPORT', 'FM2', 'FM3', 'RESIDENCE_CARD', 'VISA', 'CURP'] // Exclude CURP (non-DOC) to avoid duplicates
  return allDocumentOptions.value.filter(doc => !excludedTypes.includes(doc.value))
})

// Documents specific to foreigners (exclude national-only documents)
const foreignerDocumentOptions = computed(() => {
  const excludedTypes = ['INE_FRONT', 'INE_BACK', 'CURP_DOC', 'CURP'] // Exclude both CURP types
  return allDocumentOptions.value.filter(doc => !excludedTypes.includes(doc.value))
})

// Load document types from backend
const loadDocumentTypes = async () => {
  try {
    const response = await v2.staff.document.getTypes()
    if (response.success && response.data?.types) {
      // Backend returns types as Record<string, string> { TYPE: 'Label' }
      documentLabels.value = response.data.types as unknown as Record<string, string>
    }
  } catch (e) {
    log.error('Error loading document types', { error: e })
    // Fallback to basic types if API fails
    documentLabels.value = {
      INE_FRONT: 'INE (Frente)',
      INE_BACK: 'INE (Reverso)',
      PROOF_OF_ADDRESS: 'Comprobante de Domicilio',
      SELFIE: 'Selfie'
    }
  }
}

// Filters
const searchQuery = ref('')
const activeFilter = ref('')
const currentPage = ref(1)

// Data
const products = ref<Product[]>([])
const isLoading = ref(true)
const error = ref('')

// Modal state
const showProductModal = ref(false)
const editingProduct = ref<Product | null>(null)
const isSubmitting = ref(false)
const formError = ref('')
const activeTab = ref<'basic' | 'rules' | 'documents'>('basic')

// Delete state
const showDeleteModal = ref(false)
const productToDelete = ref<Product | null>(null)
const isDeleting = ref(false)

// Term config per frequency - lista de plazos disponibles
const defaultTermConfig: Record<string, TermConfig> = {
  WEEKLY: { available_terms: [4, 8, 12, 26, 52] },
  BIWEEKLY: { available_terms: [2, 4, 6, 12, 24] },
  MONTHLY: { available_terms: [3, 6, 12, 18, 24, 36, 48] }
}

const termUnitLabels: Record<string, string> = {
  WEEKLY: 'semanas',
  BIWEEKLY: 'quincenas',
  MONTHLY: 'meses'
}

// Input temporal para agregar plazos
const newTermInput = ref<Record<string, number | null>>({})

const addTerm = (freq: string) => {
  const rawValue = newTermInput.value[freq]
  const value = typeof rawValue === 'number' && !isNaN(rawValue) ? rawValue : 0

  if (value > 0) {
    if (!form.value.term_config[freq]) {
      form.value.term_config[freq] = { available_terms: [] }
    }
    // Only add if not already in the list
    if (!form.value.term_config[freq].available_terms.includes(value)) {
      form.value.term_config[freq].available_terms.push(value)
      form.value.term_config[freq].available_terms.sort((a, b) => a - b)
    }
    newTermInput.value[freq] = null
  }
}

const removeTerm = (freq: string, term: number) => {
  const config = form.value.term_config[freq]
  if (config) {
    config.available_terms = config.available_terms.filter(t => t !== term)
  }
}

// Check if a term is within the valid range (converted to months)
// Using exact conversion: 52 weeks = 12 months, 24 biweekly = 12 months
const isTermInRange = (freq: string, term: number): boolean => {
  const minMonths = form.value.min_term_months || 1
  const maxMonths = form.value.max_term_months || 999

  // Convert term to months based on frequency
  let termInMonths: number
  if (freq === 'WEEKLY') {
    termInMonths = term * 12 / 52 // 52 weeks = 12 months (e.g., 52 weeks = 12 months)
  } else if (freq === 'BIWEEKLY') {
    termInMonths = term / 2 // 24 biweekly = 12 months
  } else {
    termInMonths = term // already in months
  }

  // Round to allow for slight variations (e.g., 13 weeks ≈ 3 months)
  return Math.round(termInMonths) >= minMonths && Math.round(termInMonths) <= maxMonths
}

// Product type union
type ProductType = 'PERSONAL' | 'AUTO' | 'HIPOTECARIO' | 'PYME' | 'NOMINA' | 'ARRENDAMIENTO'

// Form state
const form = ref({
  name: '',
  code: '',
  type: 'PERSONAL' as ProductType,
  description: '',
  min_amount: 5000,
  max_amount: 100000,
  min_term_months: 3,
  max_term_months: 48,
  interest_rate: 36,
  opening_commission: 3,
  late_fee_rate: 2,
  payment_frequencies: ['MONTHLY'] as string[],
  term_config: { MONTHLY: { available_terms: [3, 6, 12, 18, 24, 36, 48] } } as Record<string, TermConfig>,
  required_documents: {
    nationals: [] as string[],
    foreigners: [] as string[]
  },
  is_active: true
})

// Document applicant type selection
const documentApplicantTypes = ref<('nationals' | 'foreigners')[]>(['nationals'])

// Quick add term options per frequency
const quickTermOptions: Record<string, number[]> = {
  WEEKLY: [4, 8, 12, 16, 26, 52],
  BIWEEKLY: [2, 4, 6, 12, 18, 24],
  MONTHLY: [3, 6, 9, 12, 18, 24, 36, 48, 60]
}

// Computed: merged and sorted terms for each frequency (selected + quick options)
const mergedTerms = computed(() => {
  const result: Record<string, number[]> = {}
  for (const freq of ['WEEKLY', 'BIWEEKLY', 'MONTHLY']) {
    const selected = form.value.term_config[freq]?.available_terms ?? []
    const quick = quickTermOptions[freq] ?? []
    result[freq] = [...new Set([...selected, ...quick])].sort((a, b) => a - b)
  }
  return result
})

// Helper: check if term is selected for a frequency
const isTermSelected = (freq: string, term: number): boolean => {
  return form.value.term_config[freq]?.available_terms?.includes(term) ?? false
}

// Helper: check if term is a quick option
const isQuickOption = (freq: string, term: number): boolean => {
  return (quickTermOptions[freq] ?? []).includes(term)
}

// Helper: add term to frequency config
const addTermToFrequency = (freq: string, term: number): void => {
  if (!form.value.term_config[freq]) {
    form.value.term_config[freq] = { available_terms: [] }
  }
  if (!form.value.term_config[freq].available_terms.includes(term)) {
    form.value.term_config[freq].available_terms.push(term)
    form.value.term_config[freq].available_terms.sort((a, b) => a - b)
  }
}

const formErrors = ref<Record<string, string>>({})

// Type options from backend enum
const typeOptions = computed(() => tenantStore.options.productType)

// Frequency options from backend enum
const frequencyOptions = computed(() => tenantStore.options.paymentFrequency)

const activeFilterOptions = [
  { value: '', label: 'Todos' },
  { value: 'true', label: 'Activos' },
  { value: 'false', label: 'Inactivos' }
]

// Fetch products
const fetchProducts = async () => {
  isLoading.value = true
  error.value = ''

  try {
    const filters: {
      search?: string
      active?: boolean
    } = {}

    if (searchQuery.value) {
      filters.search = searchQuery.value
    }
    if (activeFilter.value === 'true') {
      filters.active = true
    } else if (activeFilter.value === 'false') {
      filters.active = false
    }

    const response = await v2.staff.product.list(filters)
    products.value = response.data?.products ?? []
  } catch (e: unknown) {
    error.value = 'Error al cargar los productos'
    log.error('Error al cargar productos', { error: e })
  } finally {
    isLoading.value = false
  }
}

// Filtered products
const filteredProducts = computed(() => {
  return products.value
})

// Open create modal
const openCreateModal = () => {
  editingProduct.value = null
  activeTab.value = 'basic'
  form.value = {
    name: '',
    code: '',
    type: 'PERSONAL',
    description: '',
    min_amount: 5000,
    max_amount: 100000,
    min_term_months: 3,
    max_term_months: 48,
    interest_rate: 36,
    opening_commission: 3,
    late_fee_rate: 2,
    payment_frequencies: ['MONTHLY'],
    term_config: { MONTHLY: { available_terms: [3, 6, 12, 18, 24, 36, 48] } },
    required_documents: {
      nationals: ['INE_FRONT', 'INE_BACK', 'PROOF_OF_ADDRESS'],
      foreigners: ['PASSPORT', 'RESIDENCE_CARD', 'PROOF_OF_ADDRESS']
    },
    is_active: true
  }
  // Initialize newTermInput for all frequencies for proper reactivity
  newTermInput.value = {
    WEEKLY: null,
    BIWEEKLY: null,
    MONTHLY: null
  }
  formErrors.value = {}
  formError.value = ''
  // Default: both nationals and foreigners enabled for new products
  documentApplicantTypes.value = ['nationals', 'foreigners']
  showProductModal.value = true
}

// Open edit modal
const openEditModal = (product: Product) => {
  editingProduct.value = product
  activeTab.value = 'basic'

  // Build term_config from product data
  const frequencies = product.payment_frequencies || ['MONTHLY']
  const termConfig: Record<string, TermConfig> = {}

  // If product has term_config with available_terms, use it
  if (product.term_config && Object.keys(product.term_config).length > 0) {
    for (const [freq, config] of Object.entries(product.term_config)) {
      if (config?.available_terms) {
        termConfig[freq] = { available_terms: [...config.available_terms] }
      } else {
        // Legacy format with min/max - generate default terms
        termConfig[freq] = { available_terms: [...(defaultTermConfig[freq]?.available_terms || [3, 6, 12, 24, 36, 48])] }
      }
    }
  } else {
    // No term_config - use defaults for each frequency
    frequencies.forEach(freq => {
      termConfig[freq] = { available_terms: [...(defaultTermConfig[freq]?.available_terms || [3, 6, 12, 24, 36, 48])] }
    })
  }

  form.value = {
    name: product.name,
    code: product.code || '',
    type: product.type,
    description: product.description || '',
    min_amount: product.min_amount,
    max_amount: product.max_amount,
    min_term_months: product.min_term_months || 3,
    max_term_months: product.max_term_months || 48,
    interest_rate: product.interest_rate,
    opening_commission: product.opening_commission,
    late_fee_rate: product.late_fee_rate || 0,
    payment_frequencies: frequencies,
    term_config: termConfig,
    required_documents: normalizeRequiredDocuments(product.required_documents),
    is_active: product.is_active
  }
  // Initialize newTermInput for all frequencies for proper reactivity
  newTermInput.value = {
    WEEKLY: null,
    BIWEEKLY: null,
    MONTHLY: null
  }
  formErrors.value = {}
  formError.value = ''
  // Determine which applicant types are enabled based on documents
  const normalized = normalizeRequiredDocuments(product.required_documents)
  documentApplicantTypes.value = []
  if (normalized.nationals.length > 0) {
    documentApplicantTypes.value.push('nationals')
  }
  if (normalized.foreigners.length > 0) {
    documentApplicantTypes.value.push('foreigners')
  }
  // If both are empty, enable both by default
  if (documentApplicantTypes.value.length === 0) {
    documentApplicantTypes.value = ['nationals', 'foreigners']
  }
  showProductModal.value = true
}

// Validate form
const validateForm = (): boolean => {
  formErrors.value = {}

  if (!form.value.name.trim()) {
    formErrors.value.name = 'El nombre es requerido'
  }

  if (!form.value.code.trim()) {
    formErrors.value.code = 'El código es requerido'
  } else if (!/^[A-Z0-9_-]+$/i.test(form.value.code)) {
    formErrors.value.code = 'Solo letras, números, guiones y guiones bajos'
  }

  if (form.value.min_amount <= 0) {
    formErrors.value.min_amount = 'Debe ser mayor a 0'
  }

  if (form.value.max_amount <= form.value.min_amount) {
    formErrors.value.max_amount = 'Debe ser mayor al monto mínimo'
  }

  if (form.value.min_term_months <= 0) {
    formErrors.value.min_term_months = 'Debe ser mayor a 0'
  }

  if (form.value.max_term_months <= form.value.min_term_months) {
    formErrors.value.max_term_months = 'Debe ser mayor al plazo mínimo'
  }

  if (form.value.interest_rate < 0 || form.value.interest_rate > 100) {
    formErrors.value.interest_rate = 'Debe estar entre 0 y 100'
  }

  if (form.value.payment_frequencies.length === 0) {
    formErrors.value.payment_frequencies = 'Selecciona al menos una frecuencia'
  }

  // Validate term config for each selected frequency
  for (const freq of form.value.payment_frequencies) {
    const config = form.value.term_config[freq]
    if (!config || !config.available_terms || config.available_terms.length === 0) {
      formErrors.value[`term_${freq}`] = 'Agrega al menos un plazo'
    }
  }

  return Object.keys(formErrors.value).length === 0
}

// Submit form
const submitForm = async () => {
  if (!validateForm()) return

  isSubmitting.value = true
  formError.value = ''

  try {
    const payload = {
      ...form.value,
      code: form.value.code.toUpperCase()
    }

    if (editingProduct.value) {
      await v2.staff.product.update(editingProduct.value.id, payload)
    } else {
      await v2.staff.product.create(payload)
    }

    showProductModal.value = false
    await fetchProducts()
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }
    if (err.response?.data?.errors) {
      const serverErrors = err.response.data.errors
      for (const [field, messages] of Object.entries(serverErrors)) {
        formErrors.value[field] = messages[0] ?? ''
      }
    } else {
      formError.value = err.response?.data?.message || 'Error al guardar el producto'
    }
  } finally {
    isSubmitting.value = false
  }
}

// Toggle active status
const toggleActive = async (product: Product): Promise<void> => {
  if (!canManageProducts.value) {
    toast.error('No tienes permisos para modificar productos')
    return
  }

  try {
    await v2.staff.product.update(product.id, {
      is_active: !product.is_active
    })
    product.is_active = !product.is_active
  } catch (e) {
    log.error('Error al cambiar estado del producto', { error: e })
    toast.error('Error al cambiar el estado del producto')
  }
}

// Open delete modal
const openDeleteModal = (product: Product) => {
  productToDelete.value = product
  showDeleteModal.value = true
}

// Delete product
const deleteProduct = async () => {
  if (!productToDelete.value) return

  isDeleting.value = true
  try {
    await v2.staff.product.remove(productToDelete.value.id)
    showDeleteModal.value = false
    await fetchProducts()
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string } } }
    log.error('Error al eliminar producto', { error: e })
    toast.error(err.response?.data?.message || 'Error al eliminar el producto')
  } finally {
    isDeleting.value = false
  }
}

// Alias for template compatibility
const formatCurrency = formatMoney

// Cleanup modal state on unmount to prevent stale UI
onBeforeUnmount(() => {
  showProductModal.value = false
  showDeleteModal.value = false
  editingProduct.value = null
  productToDelete.value = null
})

// Toggle frequency
const toggleFrequency = (freq: string) => {
  const index = form.value.payment_frequencies.indexOf(freq)
  if (index >= 0) {
    // Remove frequency and its term config
    form.value.payment_frequencies.splice(index, 1)
    delete form.value.term_config[freq]
    delete newTermInput.value[freq]
  } else {
    // Add frequency with default term config (copy the array)
    form.value.payment_frequencies.push(freq)
    form.value.term_config[freq] = { available_terms: [...(defaultTermConfig[freq]?.available_terms || [])] }
    // Initialize input for reactivity
    newTermInput.value[freq] = null
  }
}

// Normalize required documents to new structure
const normalizeRequiredDocuments = (docs: any) => {
  // If already in new format
  if (docs && typeof docs === 'object' && ('nationals' in docs || 'foreigners' in docs)) {
    return {
      nationals: Array.isArray(docs.nationals) ? docs.nationals : [],
      foreigners: Array.isArray(docs.foreigners) ? docs.foreigners : []
    }
  }

  // If old format (array), convert to new format
  if (Array.isArray(docs)) {
    // Map INE to PASSPORT for foreigners
    const foreignerDocs = docs.map(doc => {
      if (doc === 'INE_FRONT') return 'PASSPORT'
      if (doc === 'INE_BACK') return 'RESIDENCE_CARD'
      return doc
    })
    return {
      nationals: docs,
      foreigners: foreignerDocs
    }
  }

  // Default empty structure
  return {
    nationals: [],
    foreigners: []
  }
}

// Toggle document for nationals
const toggleDocumentNationals = (doc: string) => {
  const index = form.value.required_documents.nationals.indexOf(doc)
  if (index >= 0) {
    form.value.required_documents.nationals.splice(index, 1)
  } else {
    form.value.required_documents.nationals.push(doc)
  }
}

// Toggle document for foreigners
const toggleDocumentForeigners = (doc: string) => {
  const index = form.value.required_documents.foreigners.indexOf(doc)
  if (index >= 0) {
    form.value.required_documents.foreigners.splice(index, 1)
  } else {
    form.value.required_documents.foreigners.push(doc)
  }
}

// Toggle applicant type (nationals/foreigners)
const toggleApplicantType = (type: 'nationals' | 'foreigners') => {
  const index = documentApplicantTypes.value.indexOf(type)
  if (index >= 0) {
    // Prevent disabling if it's the only one enabled
    if (documentApplicantTypes.value.length === 1) {
      return // At least one must remain selected
    }
    // Disabling: remove from list and clear documents
    documentApplicantTypes.value.splice(index, 1)
    form.value.required_documents[type] = []
  } else {
    // Enabling: add to list
    documentApplicantTypes.value.push(type)
    // Add default documents based on type
    if (type === 'nationals') {
      form.value.required_documents.nationals = ['INE_FRONT', 'INE_BACK', 'PROOF_OF_ADDRESS']
    } else {
      form.value.required_documents.foreigners = ['PASSPORT', 'RESIDENCE_CARD', 'PROOF_OF_ADDRESS']
    }
  }
}

// Computed: is type enabled
const isTypeEnabled = (type: 'nationals' | 'foreigners') => {
  return documentApplicantTypes.value.includes(type)
}

// Watch filters
watch([searchQuery, activeFilter], () => {
  currentPage.value = 1
  fetchProducts()
})

onBeforeMount(loadDocumentTypes)
onMounted(fetchProducts)
</script>

<template>
  <div class="p-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Productos</h1>
        <p class="text-gray-500 mt-1">Gestiona los productos de crédito disponibles</p>
      </div>
      <AppButton variant="primary" @click="openCreateModal">
        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
        </svg>
        Nuevo Producto
      </AppButton>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-xl shadow-sm p-4 mb-6">
      <div class="flex flex-col sm:flex-row gap-4">
        <!-- Search -->
        <div class="flex-1">
          <div class="relative">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Buscar por nombre o código..."
              class="w-full pl-10 pr-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors"
            />
          </div>
        </div>

        <!-- Active filter -->
        <div class="w-full sm:w-48">
          <select
            v-model="activeFilter"
            class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors"
          >
            <option v-for="opt in activeFilterOptions" :key="opt.value" :value="opt.value">
              {{ opt.label }}
            </option>
          </select>
        </div>
      </div>
    </div>

    <!-- Products Grid -->
    <div v-if="isLoading" class="flex justify-center py-12">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600"></div>
    </div>

    <div v-else-if="error" class="bg-red-50 text-red-600 p-4 rounded-lg">
      {{ error }}
      <button class="underline ml-2" @click="fetchProducts">Reintentar</button>
    </div>

    <div v-else-if="filteredProducts.length === 0" class="bg-white rounded-xl shadow-sm p-12 text-center">
      <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
      </svg>
      <h3 class="text-lg font-medium text-gray-900 mb-2">No hay productos</h3>
      <p class="text-gray-500 mb-4">Crea tu primer producto de crédito</p>
      <AppButton variant="primary" @click="openCreateModal">Crear Producto</AppButton>
    </div>

    <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
      <div
        v-for="product in filteredProducts"
        :key="product.id"
        class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-md transition-shadow"
      >
        <!-- Header -->
        <div class="p-5 border-b border-gray-100">
          <div class="flex items-start justify-between">
            <div class="flex items-center gap-3">
              <div class="w-12 h-12 rounded-xl bg-primary-100 flex items-center justify-center">
                <svg class="w-6 h-6 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="typeIcons[product.type] || defaultIcon" />
                </svg>
              </div>
              <div>
                <h3 class="font-semibold text-gray-900">{{ product.name }}</h3>
                <p class="text-sm text-gray-500">{{ product.code }}</p>
              </div>
            </div>
            <span
              :class="[
                'px-2.5 py-1 rounded-full text-xs font-medium',
                product.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600'
              ]"
            >
              {{ product.is_active ? 'Activo' : 'Inactivo' }}
            </span>
          </div>
          <p v-if="product.description" class="text-sm text-gray-600 mt-3 line-clamp-2">
            {{ product.description }}
          </p>
        </div>

        <!-- Stats -->
        <div class="p-5 grid grid-cols-2 gap-4">
          <div>
            <p class="text-xs text-gray-500 mb-1">Monto</p>
            <p class="text-sm font-medium text-gray-900">
              {{ formatCurrency(product.min_amount) }} - {{ formatCurrency(product.max_amount) }}
            </p>
          </div>
          <div>
            <p class="text-xs text-gray-500 mb-1">Plazo</p>
            <p class="text-sm font-medium text-gray-900">
              {{ product.min_term_months }} - {{ product.max_term_months }} meses
            </p>
          </div>
          <div>
            <p class="text-xs text-gray-500 mb-1">Tasa anual</p>
            <p class="text-sm font-medium text-gray-900">{{ product.interest_rate }}%</p>
          </div>
          <div>
            <p class="text-xs text-gray-500 mb-1">Comisión apertura</p>
            <p class="text-sm font-medium text-gray-900">{{ product.opening_commission }}%</p>
          </div>
        </div>

        <!-- Frequencies -->
        <div class="px-5 pb-3">
          <p class="text-xs text-gray-500 mb-2">Frecuencias de pago</p>
          <div class="flex flex-wrap gap-1.5">
            <span
              v-for="freq in product.payment_frequencies"
              :key="freq"
              class="px-2 py-0.5 bg-gray-100 rounded text-xs text-gray-600"
            >
              {{ frequencyLabels[freq] || freq }}
            </span>
          </div>
        </div>

        <!-- Footer -->
        <div class="px-5 py-4 bg-gray-50 border-t border-gray-100 flex items-center justify-between">
          <span class="text-sm text-gray-500">
            {{ product.applications_count }} solicitudes
          </span>
          <div class="flex items-center gap-2">
            <button
              class="p-2 text-gray-400 hover:text-primary-600 hover:bg-primary-50 rounded-lg transition-colors"
              title="Editar"
              @click="openEditModal(product)"
            >
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
              </svg>
            </button>
            <button
              class="p-2 text-gray-400 hover:text-yellow-600 hover:bg-yellow-50 rounded-lg transition-colors"
              :title="product.is_active ? 'Desactivar' : 'Activar'"
              @click="toggleActive(product)"
            >
              <svg v-if="product.is_active" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
              </svg>
              <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </button>
            <button
              v-if="product.applications_count === 0"
              class="p-2 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors"
              title="Eliminar"
              @click="openDeleteModal(product)"
            >
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
              </svg>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Create/Edit Modal -->
    <Teleport to="body">
      <div v-if="showProductModal" class="fixed inset-0 z-50 overflow-y-auto">
        <div class="flex min-h-full items-center justify-center p-4">
          <div class="fixed inset-0 bg-black/50" @click="showProductModal = false"></div>

          <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-4xl max-h-[90vh] overflow-hidden">
            <!-- Modal header -->
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
              <h2 class="text-xl font-semibold text-gray-900">
                {{ editingProduct ? 'Editar Producto' : 'Nuevo Producto' }}
              </h2>
              <button
                class="p-2 text-gray-400 hover:text-gray-600 rounded-lg"
                @click="showProductModal = false"
              >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>

            <!-- Tabs -->
            <div class="flex border-b border-gray-100">
              <button
                :class="[
                  'flex-1 px-4 py-3 text-sm font-medium transition-colors',
                  activeTab === 'basic' ? 'text-primary-600 border-b-2 border-primary-600' : 'text-gray-500 hover:text-gray-700'
                ]"
                @click="activeTab = 'basic'"
              >
                Información Básica
              </button>
              <button
                :class="[
                  'flex-1 px-4 py-3 text-sm font-medium transition-colors',
                  activeTab === 'rules' ? 'text-primary-600 border-b-2 border-primary-600' : 'text-gray-500 hover:text-gray-700'
                ]"
                @click="activeTab = 'rules'"
              >
                Reglas y Tasas
              </button>
              <button
                :class="[
                  'flex-1 px-4 py-3 text-sm font-medium transition-colors',
                  activeTab === 'documents' ? 'text-primary-600 border-b-2 border-primary-600' : 'text-gray-500 hover:text-gray-700'
                ]"
                @click="activeTab = 'documents'"
              >
                Documentos
              </button>
            </div>

            <!-- Modal body -->
            <div class="p-6 overflow-y-auto max-h-[60vh]">
              <!-- Error message -->
              <div v-if="formError" class="mb-4 p-3 bg-red-50 text-red-600 rounded-lg text-sm">
                {{ formError }}
              </div>

              <!-- Basic Info Tab -->
              <div v-show="activeTab === 'basic'" class="space-y-4">
                <!-- Name -->
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">
                    Nombre del Producto *
                  </label>
                  <input
                    v-model="form.name"
                    type="text"
                    placeholder="Ej: Crédito Personal"
                    :class="[
                      'w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors',
                      formErrors.name ? 'border-red-300' : 'border-gray-200'
                    ]"
                  />
                  <p v-if="formErrors.name" class="mt-1 text-sm text-red-500">{{ formErrors.name }}</p>
                </div>

                <!-- Code and Type -->
                <div class="grid grid-cols-2 gap-4">
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                      Código *
                    </label>
                    <input
                      v-model="form.code"
                      type="text"
                      placeholder="Ej: PERS-001"
                      :class="[
                        'w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors uppercase',
                        formErrors.code ? 'border-red-300' : 'border-gray-200'
                      ]"
                    />
                    <p v-if="formErrors.code" class="mt-1 text-sm text-red-500">{{ formErrors.code }}</p>
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                      Tipo *
                    </label>
                    <select
                      v-model="form.type"
                      class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors"
                    >
                      <option v-for="opt in typeOptions" :key="opt.value" :value="opt.value">
                        {{ opt.label }}
                      </option>
                    </select>
                  </div>
                </div>

                <!-- Description -->
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">
                    Descripción
                  </label>
                  <textarea
                    v-model="form.description"
                    rows="3"
                    placeholder="Descripción breve del producto..."
                    class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors resize-none"
                  ></textarea>
                </div>

                <!-- Active -->
                <div class="flex items-center gap-3">
                  <button
                    type="button"
                    :class="[
                      'relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2',
                      form.is_active ? 'bg-primary-600' : 'bg-gray-200'
                    ]"
                    @click="form.is_active = !form.is_active"
                  >
                    <span
                      :class="[
                        'pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out',
                        form.is_active ? 'translate-x-5' : 'translate-x-0'
                      ]"
                    />
                  </button>
                  <span class="text-sm text-gray-700">Producto activo</span>
                </div>
              </div>

              <!-- Rules Tab -->
              <div v-show="activeTab === 'rules'" class="space-y-6">
                <!-- Amount and Term Ranges -->
                <div class="grid grid-cols-2 gap-6">
                  <!-- Amount Range -->
                  <div class="bg-gray-50 rounded-xl p-4">
                    <h4 class="text-sm font-medium text-gray-900 mb-3 flex items-center gap-2">
                      <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                      </svg>
                      Rango de Montos
                    </h4>
                    <div class="grid grid-cols-2 gap-3">
                      <div>
                        <label class="block text-xs text-gray-500 mb-1">Mínimo *</label>
                        <div class="relative">
                          <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">$</span>
                          <input
                            v-model.number="form.min_amount"
                            type="number"
                            min="0"
                            step="1000"
                            :class="[
                              'w-full pl-7 pr-3 py-2.5 border rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 text-sm bg-white transition-colors',
                              formErrors.min_amount ? 'border-red-300' : 'border-gray-200'
                            ]"
                          />
                        </div>
                        <p v-if="formErrors.min_amount" class="mt-1 text-xs text-red-500">{{ formErrors.min_amount }}</p>
                      </div>
                      <div>
                        <label class="block text-xs text-gray-500 mb-1">Máximo *</label>
                        <div class="relative">
                          <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">$</span>
                          <input
                            v-model.number="form.max_amount"
                            type="number"
                            min="0"
                            step="1000"
                            :class="[
                              'w-full pl-7 pr-3 py-2.5 border rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 text-sm bg-white transition-colors',
                              formErrors.max_amount ? 'border-red-300' : 'border-gray-200'
                            ]"
                          />
                        </div>
                        <p v-if="formErrors.max_amount" class="mt-1 text-xs text-red-500">{{ formErrors.max_amount }}</p>
                      </div>
                    </div>
                  </div>

                  <!-- Term Range (in months - global) -->
                  <div class="bg-gray-50 rounded-xl p-4">
                    <h4 class="text-sm font-medium text-gray-900 mb-3 flex items-center gap-2">
                      <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                      </svg>
                      Rango de Plazos (meses)
                    </h4>
                    <div class="grid grid-cols-2 gap-3">
                      <div>
                        <label class="block text-xs text-gray-500 mb-1">Mínimo *</label>
                        <input
                          v-model.number="form.min_term_months"
                          type="number"
                          min="1"
                          :class="[
                            'w-full px-3 py-2.5 border rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 text-sm bg-white transition-colors',
                            formErrors.min_term_months ? 'border-red-300' : 'border-gray-200'
                          ]"
                        />
                        <p v-if="formErrors.min_term_months" class="mt-1 text-xs text-red-500">{{ formErrors.min_term_months }}</p>
                      </div>
                      <div>
                        <label class="block text-xs text-gray-500 mb-1">Máximo *</label>
                        <input
                          v-model.number="form.max_term_months"
                          type="number"
                          min="1"
                          :class="[
                            'w-full px-3 py-2.5 border rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 text-sm bg-white transition-colors',
                            formErrors.max_term_months ? 'border-red-300' : 'border-gray-200'
                          ]"
                        />
                        <p v-if="formErrors.max_term_months" class="mt-1 text-xs text-red-500">{{ formErrors.max_term_months }}</p>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Rates (before frequencies) -->
                <div class="bg-gray-50 rounded-xl p-4">
                  <h4 class="text-sm font-medium text-gray-900 mb-3 flex items-center gap-2">
                    <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                    </svg>
                    Tasas y Comisiones
                  </h4>
                  <div class="grid grid-cols-3 gap-4">
                    <div>
                      <label class="block text-xs text-gray-500 mb-1">Tasa Anual (%) *</label>
                      <input
                        v-model.number="form.interest_rate"
                        type="number"
                        min="0"
                        max="100"
                        step="0.1"
                        :class="['w-full px-3 py-2.5 border rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 text-sm bg-white transition-colors', formErrors.interest_rate ? 'border-red-300' : 'border-gray-200']"
                      />
                      <p v-if="formErrors.interest_rate" class="mt-1 text-xs text-red-500">{{ formErrors.interest_rate }}</p>
                    </div>
                    <div>
                      <label class="block text-xs text-gray-500 mb-1">Comisión Apertura (%)</label>
                      <input v-model.number="form.opening_commission" type="number" min="0" max="100" step="0.1" class="w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 text-sm bg-white transition-colors" />
                    </div>
                    <div>
                      <label class="block text-xs text-gray-500 mb-1">Mora (%)</label>
                      <input v-model.number="form.late_fee_rate" type="number" min="0" max="100" step="0.1" class="w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 text-sm bg-white transition-colors" />
                    </div>
                  </div>
                </div>

                <!-- Payment Frequencies with Term Config - Simplified Design -->
                <div class="bg-gray-50 rounded-xl p-4">
                  <h4 class="text-sm font-medium text-gray-900 mb-4 flex items-center gap-2">
                    <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                    </svg>
                    Frecuencias de Pago y Plazos
                  </h4>

                  <div class="space-y-4">
                    <!-- Frequency: Semanal -->
                    <div class="bg-white rounded-lg border" :class="form.payment_frequencies.includes('WEEKLY') ? 'border-primary-300' : 'border-gray-200'">
                      <div class="flex items-center justify-between px-4 py-3">
                        <label class="flex items-center gap-3 cursor-pointer flex-1" @click="toggleFrequency('WEEKLY')">
                          <input type="checkbox" :checked="form.payment_frequencies.includes('WEEKLY')" class="w-4 h-4 text-primary-600 border-gray-300 rounded focus:ring-primary-500" @click.stop />
                          <div>
                            <span class="font-medium text-gray-900">Semanal</span>
                            <span class="text-xs text-gray-500 ml-2">(semanas)</span>
                          </div>
                        </label>
                        <div v-if="form.payment_frequencies.includes('WEEKLY')" class="text-xs text-gray-500">
                          {{ form.term_config['WEEKLY']?.available_terms?.length || 0 }} plazos
                        </div>
                      </div>
                      <div v-if="form.payment_frequencies.includes('WEEKLY')" class="px-4 pb-3 border-t border-gray-100">
                        <div class="flex flex-wrap gap-1.5 pt-3">
                          <!-- All terms (selected + quick options) sorted together -->
                          <template v-for="term in mergedTerms['WEEKLY']" :key="`weekly-${term}-${form.min_term_months}-${form.max_term_months}`">
                            <!-- Selected term from quick options -->
                            <button
                              v-if="isTermSelected('WEEKLY', term) && isQuickOption('WEEKLY', term)"
                              type="button"
                              :class="[
                                'w-10 h-8 text-xs rounded-md transition-all font-medium',
                                isTermInRange('WEEKLY', term) ? 'bg-primary-600 text-white shadow-sm' : 'bg-red-500 text-white shadow-sm'
                              ]"
                              @click="removeTerm('WEEKLY', term)"
                            >{{ term }}</button>
                            <!-- Selected custom term -->
                            <button
                              v-else-if="isTermSelected('WEEKLY', term)"
                              type="button"
                              :class="[
                                'h-8 px-2 text-xs rounded-md font-medium text-white shadow-sm flex items-center gap-1',
                                isTermInRange('WEEKLY', term) ? 'bg-green-600 hover:bg-green-700' : 'bg-red-500 hover:bg-red-600'
                              ]"
                              @click="removeTerm('WEEKLY', term)"
                            >
                              {{ term }}
                              <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                              </svg>
                            </button>
                            <!-- Unselected quick option -->
                            <button
                              v-else
                              type="button"
                              class="w-10 h-8 text-xs rounded-md transition-all font-medium bg-gray-100 text-gray-600 hover:bg-gray-200"
                              @click="addTermToFrequency('WEEKLY', term)"
                            >{{ term }}</button>
                          </template>
                          <!-- Add custom input -->
                          <input
                            v-model.number="newTermInput['WEEKLY']"
                            type="number"
                            min="1"
                            placeholder="Otro"
                            class="w-16 h-8 ml-2 px-2 text-xs text-center border border-dashed border-gray-300 rounded-md focus:ring-1 focus:ring-primary-500 focus:border-primary-500"
                            @keyup.enter="addTerm('WEEKLY')"
                          />
                          <button
                            type="button"
                            class="h-8 w-8 ml-1 flex items-center justify-center bg-primary-100 hover:bg-primary-200 text-primary-600 rounded-md transition-colors"
                            @click="addTerm('WEEKLY')"
                          >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                          </button>
                        </div>
                        <p v-if="formErrors['term_WEEKLY']" class="mt-2 text-xs text-red-500">{{ formErrors['term_WEEKLY'] }}</p>
                      </div>
                    </div>

                    <!-- Frequency: Quincenal -->
                    <div class="bg-white rounded-lg border" :class="form.payment_frequencies.includes('BIWEEKLY') ? 'border-primary-300' : 'border-gray-200'">
                      <div class="flex items-center justify-between px-4 py-3">
                        <label class="flex items-center gap-3 cursor-pointer flex-1" @click="toggleFrequency('BIWEEKLY')">
                          <input type="checkbox" :checked="form.payment_frequencies.includes('BIWEEKLY')" class="w-4 h-4 text-primary-600 border-gray-300 rounded focus:ring-primary-500" @click.stop />
                          <div>
                            <span class="font-medium text-gray-900">Quincenal</span>
                            <span class="text-xs text-gray-500 ml-2">(quincenas)</span>
                          </div>
                        </label>
                        <div v-if="form.payment_frequencies.includes('BIWEEKLY')" class="text-xs text-gray-500">
                          {{ form.term_config['BIWEEKLY']?.available_terms?.length || 0 }} plazos
                        </div>
                      </div>
                      <div v-if="form.payment_frequencies.includes('BIWEEKLY')" class="px-4 pb-3 border-t border-gray-100">
                        <div class="flex flex-wrap gap-1.5 pt-3">
                          <!-- All terms (selected + quick options) sorted together -->
                          <template v-for="term in mergedTerms['BIWEEKLY']" :key="`biweekly-${term}-${form.min_term_months}-${form.max_term_months}`">
                            <!-- Selected term from quick options -->
                            <button
                              v-if="isTermSelected('BIWEEKLY', term) && isQuickOption('BIWEEKLY', term)"
                              type="button"
                              :class="[
                                'w-10 h-8 text-xs rounded-md transition-all font-medium',
                                isTermInRange('BIWEEKLY', term) ? 'bg-primary-600 text-white shadow-sm' : 'bg-red-500 text-white shadow-sm'
                              ]"
                              @click="removeTerm('BIWEEKLY', term)"
                            >{{ term }}</button>
                            <!-- Selected custom term -->
                            <button
                              v-else-if="isTermSelected('BIWEEKLY', term)"
                              type="button"
                              :class="[
                                'h-8 px-2 text-xs rounded-md font-medium text-white shadow-sm flex items-center gap-1',
                                isTermInRange('BIWEEKLY', term) ? 'bg-green-600 hover:bg-green-700' : 'bg-red-500 hover:bg-red-600'
                              ]"
                              @click="removeTerm('BIWEEKLY', term)"
                            >
                              {{ term }}
                              <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                              </svg>
                            </button>
                            <!-- Unselected quick option -->
                            <button
                              v-else
                              type="button"
                              class="w-10 h-8 text-xs rounded-md transition-all font-medium bg-gray-100 text-gray-600 hover:bg-gray-200"
                              @click="addTermToFrequency('BIWEEKLY', term)"
                            >{{ term }}</button>
                          </template>
                          <!-- Add custom input -->
                          <input
                            v-model.number="newTermInput['BIWEEKLY']"
                            type="number"
                            min="1"
                            placeholder="Otro"
                            class="w-16 h-8 ml-2 px-2 text-xs text-center border border-dashed border-gray-300 rounded-md focus:ring-1 focus:ring-primary-500 focus:border-primary-500"
                            @keyup.enter="addTerm('BIWEEKLY')"
                          />
                          <button
                            type="button"
                            class="h-8 w-8 ml-1 flex items-center justify-center bg-primary-100 hover:bg-primary-200 text-primary-600 rounded-md transition-colors"
                            @click="addTerm('BIWEEKLY')"
                          >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                          </button>
                        </div>
                        <p v-if="formErrors['term_BIWEEKLY']" class="mt-2 text-xs text-red-500">{{ formErrors['term_BIWEEKLY'] }}</p>
                      </div>
                    </div>

                    <!-- Frequency: Mensual -->
                    <div class="bg-white rounded-lg border" :class="form.payment_frequencies.includes('MONTHLY') ? 'border-primary-300' : 'border-gray-200'">
                      <div class="flex items-center justify-between px-4 py-3">
                        <label class="flex items-center gap-3 cursor-pointer flex-1" @click="toggleFrequency('MONTHLY')">
                          <input type="checkbox" :checked="form.payment_frequencies.includes('MONTHLY')" class="w-4 h-4 text-primary-600 border-gray-300 rounded focus:ring-primary-500" @click.stop />
                          <div>
                            <span class="font-medium text-gray-900">Mensual</span>
                            <span class="text-xs text-gray-500 ml-2">(meses)</span>
                          </div>
                        </label>
                        <div v-if="form.payment_frequencies.includes('MONTHLY')" class="text-xs text-gray-500">
                          {{ form.term_config['MONTHLY']?.available_terms?.length || 0 }} plazos
                        </div>
                      </div>
                      <div v-if="form.payment_frequencies.includes('MONTHLY')" class="px-4 pb-3 border-t border-gray-100">
                        <div class="flex flex-wrap gap-1.5 pt-3">
                          <!-- All terms (selected + quick options) sorted together -->
                          <template v-for="term in mergedTerms['MONTHLY']" :key="`monthly-${term}-${form.min_term_months}-${form.max_term_months}`">
                            <!-- Selected term from quick options -->
                            <button
                              v-if="isTermSelected('MONTHLY', term) && isQuickOption('MONTHLY', term)"
                              type="button"
                              :class="[
                                'w-10 h-8 text-xs rounded-md transition-all font-medium',
                                isTermInRange('MONTHLY', term) ? 'bg-primary-600 text-white shadow-sm' : 'bg-red-500 text-white shadow-sm'
                              ]"
                              @click="removeTerm('MONTHLY', term)"
                            >{{ term }}</button>
                            <!-- Selected custom term -->
                            <button
                              v-else-if="isTermSelected('MONTHLY', term)"
                              type="button"
                              :class="[
                                'h-8 px-2 text-xs rounded-md font-medium text-white shadow-sm flex items-center gap-1',
                                isTermInRange('MONTHLY', term) ? 'bg-green-600 hover:bg-green-700' : 'bg-red-500 hover:bg-red-600'
                              ]"
                              @click="removeTerm('MONTHLY', term)"
                            >
                              {{ term }}
                              <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                              </svg>
                            </button>
                            <!-- Unselected quick option -->
                            <button
                              v-else
                              type="button"
                              class="w-10 h-8 text-xs rounded-md transition-all font-medium bg-gray-100 text-gray-600 hover:bg-gray-200"
                              @click="addTermToFrequency('MONTHLY', term)"
                            >{{ term }}</button>
                          </template>
                          <!-- Add custom input -->
                          <input
                            v-model.number="newTermInput['MONTHLY']"
                            type="number"
                            min="1"
                            placeholder="Otro"
                            class="w-16 h-8 ml-2 px-2 text-xs text-center border border-dashed border-gray-300 rounded-md focus:ring-1 focus:ring-primary-500 focus:border-primary-500"
                            @keyup.enter="addTerm('MONTHLY')"
                          />
                          <button
                            type="button"
                            class="h-8 w-8 ml-1 flex items-center justify-center bg-primary-100 hover:bg-primary-200 text-primary-600 rounded-md transition-colors"
                            @click="addTerm('MONTHLY')"
                          >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                          </button>
                        </div>
                        <p v-if="formErrors['term_MONTHLY']" class="mt-2 text-xs text-red-500">{{ formErrors['term_MONTHLY'] }}</p>
                      </div>
                    </div>
                  </div>
                  <p v-if="formErrors.payment_frequencies" class="mt-3 text-xs text-red-500">{{ formErrors.payment_frequencies }}</p>
                </div>

              </div>

              <!-- Documents Tab -->
              <div v-show="activeTab === 'documents'" class="space-y-6">
                <div class="space-y-4">
                  <p class="text-sm text-gray-500">
                    Selecciona los tipos de solicitantes y sus documentos requeridos
                  </p>

                  <!-- Applicant Type Selector -->
                  <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                    <h4 class="text-sm font-semibold text-gray-900 mb-3">
                      ¿Para qué tipos de solicitantes es este producto?
                    </h4>
                    <div class="flex flex-wrap gap-3">
                      <label class="flex items-center gap-2 cursor-pointer">
                        <input
                          type="checkbox"
                          :checked="isTypeEnabled('nationals')"
                          class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                          @change="toggleApplicantType('nationals')"
                        />
                        <span class="text-sm font-medium text-gray-700">
                          <span class="inline-block w-2 h-2 rounded-full bg-blue-500 mr-1"></span>
                          Nacionales
                        </span>
                      </label>
                      <label class="flex items-center gap-2 cursor-pointer">
                        <input
                          type="checkbox"
                          :checked="isTypeEnabled('foreigners')"
                          class="w-4 h-4 text-green-600 border-gray-300 rounded focus:ring-green-500"
                          @change="toggleApplicantType('foreigners')"
                        />
                        <span class="text-sm font-medium text-gray-700">
                          <span class="inline-block w-2 h-2 rounded-full bg-green-500 mr-1"></span>
                          Extranjeros
                        </span>
                      </label>
                    </div>
                    <p class="text-xs text-gray-500 mt-2">
                      Al menos un tipo debe estar seleccionado
                    </p>
                  </div>
                </div>

                <!-- Nationals Section -->
                <div v-if="isTypeEnabled('nationals')" class="space-y-3">
                  <h3 class="text-base font-semibold text-gray-900 flex items-center gap-2">
                    <span class="inline-block w-2 h-2 rounded-full bg-blue-500"></span>
                    Documentos para Nacionales
                  </h3>
                  <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    <label
                      v-for="doc in nationalDocumentOptions"
                      :key="'nationals-' + doc.value"
                      class="flex items-center gap-3 p-3 border border-gray-200 rounded-lg cursor-pointer hover:bg-gray-50 transition-colors"
                      :class="{ 'border-blue-500 bg-blue-50': form.required_documents.nationals.includes(doc.value) }"
                    >
                      <input
                        type="checkbox"
                        :checked="form.required_documents.nationals.includes(doc.value)"
                        class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                        @change="toggleDocumentNationals(doc.value)"
                      />
                      <span class="text-sm text-gray-700">{{ doc.label }}</span>
                    </label>
                  </div>
                  <div class="p-3 bg-blue-50 rounded-lg border border-blue-100">
                    <p class="text-sm text-blue-700">
                      <strong>{{ form.required_documents.nationals.length }}</strong> documentos seleccionados
                    </p>
                  </div>
                </div>

                <!-- Foreigners Section -->
                <div v-if="isTypeEnabled('foreigners')" class="space-y-3">
                  <h3 class="text-base font-semibold text-gray-900 flex items-center gap-2">
                    <span class="inline-block w-2 h-2 rounded-full bg-green-500"></span>
                    Documentos para Extranjeros
                  </h3>
                  <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    <label
                      v-for="doc in foreignerDocumentOptions"
                      :key="'foreigners-' + doc.value"
                      class="flex items-center gap-3 p-3 border border-gray-200 rounded-lg cursor-pointer hover:bg-gray-50 transition-colors"
                      :class="{ 'border-green-500 bg-green-50': form.required_documents.foreigners.includes(doc.value) }"
                    >
                      <input
                        type="checkbox"
                        :checked="form.required_documents.foreigners.includes(doc.value)"
                        class="w-4 h-4 text-green-600 border-gray-300 rounded focus:ring-green-500"
                        @change="toggleDocumentForeigners(doc.value)"
                      />
                      <span class="text-sm text-gray-700">{{ doc.label }}</span>
                    </label>
                  </div>
                  <div class="p-3 bg-green-50 rounded-lg border border-green-100">
                    <p class="text-sm text-green-700">
                      <strong>{{ form.required_documents.foreigners.length }}</strong> documentos seleccionados
                    </p>
                  </div>
                </div>
              </div>
            </div>

            <!-- Modal footer -->
            <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-100 bg-gray-50">
              <AppButton variant="outline" @click="showProductModal = false">
                Cancelar
              </AppButton>
              <AppButton variant="primary" :loading="isSubmitting" @click="submitForm">
                {{ editingProduct ? 'Guardar Cambios' : 'Crear Producto' }}
              </AppButton>
            </div>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Delete Confirmation Modal -->
    <Teleport to="body">
      <div v-if="showDeleteModal" class="fixed inset-0 z-50 overflow-y-auto">
        <div class="flex min-h-full items-center justify-center p-4">
          <div class="fixed inset-0 bg-black/50" @click="showDeleteModal = false"></div>

          <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-md p-6">
            <div class="text-center">
              <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-red-100 flex items-center justify-center">
                <svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
              </div>
              <h3 class="text-xl font-semibold text-gray-900 mb-2">Eliminar Producto</h3>
              <p class="text-gray-500 mb-6">
                ¿Estás seguro de que deseas eliminar <strong>{{ productToDelete?.name }}</strong>?
                Esta acción no se puede deshacer.
              </p>
              <div class="flex gap-3">
                <AppButton variant="outline" class="flex-1" @click="showDeleteModal = false">
                  Cancelar
                </AppButton>
                <AppButton variant="danger" class="flex-1" :loading="isDeleting" @click="deleteProduct">
                  Eliminar
                </AppButton>
              </div>
            </div>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>
