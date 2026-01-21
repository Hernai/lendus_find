<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, reactive } from 'vue'
import { useRouter } from 'vue-router'
import { AppButton } from '@/components/common'
import { v2 } from '@/services/v2'
import { useTenantStore } from '@/stores/tenant'
import { useProfileStore } from '@/stores/profile'
import { getEcho, type EchoInstance } from '@/plugins/echo'
import type { DataCorrectionSubmittedEvent } from '@/types/realtime'
import { logger } from '@/utils/logger'
import { formatMoney, formatDateTime } from '@/utils/formatters'

const log = logger.child('DataCorrections')
const router = useRouter()
const tenantStore = useTenantStore()
const profileStore = useProfileStore()

interface RejectedField {
  id: string
  field_name: string
  field_label: string
  current_value: string
  rejection_reason: string | null
  rejected_at: string | null
}

interface ApplicantData {
  first_name: string
  last_name_1: string
  last_name_2: string | null
  curp: string | null
  rfc: string | null
  ine_clave: string | null
  birth_date: string | null
  phone: string | null
  email: string | null
  address: {
    street: string
    ext_number: string
    int_number: string | null
    neighborhood: string
    postal_code: string
    municipality: string
    state: string
    housing_type: string | null
    years_at_address: number
    months_at_address: number
  } | null
  employment: {
    type: string
    company_name: string
    position: string
    monthly_income: number
    seniority_years: number
    seniority_months: number
  }
}

interface Section {
  id: string
  title: string
  icon: string
  fields: string[]
  rejectedFields: RejectedField[]
  isExpanded: boolean
}

interface CorrectionHistoryEntry {
  field_name: string | null
  field_label: string
  old_value: unknown
  new_value: unknown
  rejection_reason: string | null
  corrected_by: { id: string; name: string } | null
  corrected_at: string | null
}

interface RejectedDocument {
  id: string
  application_id: string | null
  type: string
  type_label: string
  name: string
  rejection_reason: string | null
  rejected_at: string | null
  isUploading?: boolean
  uploadError?: string
  uploadSuccess?: boolean
}

interface CorrectionsData {
  rejected_fields: RejectedField[]
  rejected_documents: RejectedDocument[]
  correction_history: CorrectionHistoryEntry[]
  applicant_data?: ApplicantData
  pending_applications: Array<{
    id: string
    folio: string
    status: string
  }>
  has_corrections_pending: boolean
}

const isLoading = ref(true)
const isSaving = ref(false)
const error = ref<string | null>(null)
const successMessage = ref<string | null>(null)
const correctionsData = ref<CorrectionsData | null>(null)

// Section being edited
const editingSection = ref<string | null>(null)

// Form data for each section
const formData = reactive({
  nombre: {
    first_name: '',
    last_name_1: '',
    last_name_2: ''
  },
  identidad: {
    curp: '',
    rfc: '',
    ine_clave: '',
    birth_date: ''
  },
  contacto: {
    phone: '',
    email: ''
  },
  direccion: {
    street: '',
    ext_number: '',
    int_number: '',
    neighborhood: '',
    postal_code: '',
    municipality: '',
    state: '',
    housing_type: '',
    years_at_address: 0,
    months_at_address: 0
  },
  empleo: {
    type: 'EMPLOYEE',
    company_name: '',
    position: '',
    monthly_income: 0,
    seniority_years: 0,
    seniority_months: 0
  }
})

// Section definitions with field mappings
const sectionDefinitions = {
  nombre: {
    title: 'Nombre Completo',
    icon: 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z',
    fields: ['first_name', 'last_name_1', 'last_name_2']
  },
  identidad: {
    title: 'Datos de Identidad',
    icon: 'M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2',
    fields: ['curp', 'rfc', 'ine_clave', 'birth_date']
  },
  contacto: {
    title: 'Datos de Contacto',
    icon: 'M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z',
    fields: ['phone', 'email']
  },
  direccion: {
    title: 'Domicilio',
    icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
    fields: ['address', 'street', 'ext_number', 'int_number', 'neighborhood', 'postal_code', 'municipality', 'state']
  },
  empleo: {
    title: 'Información Laboral',
    icon: 'M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z',
    fields: ['employment', 'type', 'company_name', 'position', 'monthly_income', 'seniority_months']
  }
}

// Field labels for display
const fieldLabels: Record<string, string> = {
  first_name: 'Nombre(s)',
  last_name_1: 'Apellido Paterno',
  last_name_2: 'Apellido Materno',
  curp: 'CURP',
  rfc: 'RFC',
  ine_clave: 'Clave de Elector (INE)',
  birth_date: 'Fecha de Nacimiento',
  phone: 'Teléfono',
  email: 'Correo Electrónico',
  street: 'Calle',
  ext_number: 'Número Exterior',
  int_number: 'Número Interior',
  neighborhood: 'Colonia',
  postal_code: 'Código Postal',
  municipality: 'Municipio/Alcaldía',
  state: 'Estado',
  type: 'Tipo de Empleo',
  company_name: 'Nombre de la Empresa',
  position: 'Puesto',
  monthly_income: 'Ingreso Mensual',
  seniority_months: 'Antigüedad (meses)'
}

// Options from backend
const employmentTypes = computed(() => tenantStore.options.employmentType)
const housingTypes = computed(() => tenantStore.options.housingType)

// Conditional display for employment fields (matching Step4Employment.vue logic)
const showCompanyDetails = computed(() => formData.empleo.type === 'EMPLOYEE')
const showBusinessDetails = computed(() =>
  ['SELF_EMPLOYED', 'BUSINESS_OWNER'].includes(formData.empleo.type)
)
// For other types (RETIRED, STUDENT, HOMEMAKER, UNEMPLOYED, OTHER) only show income

onMounted(async () => {
  await loadCorrections()
})

const loadCorrections = async () => {
  isLoading.value = true
  error.value = null

  try {
    const response = await v2.applicant.correction.index()
    if (response.success && response.data) {
      correctionsData.value = response.data
    } else {
      // Handle empty response
      correctionsData.value = {
        rejected_fields: [],
        rejected_documents: [],
        correction_history: [],
        applicant_data: undefined,
        pending_applications: [],
        has_corrections_pending: false,
      }
    }

    // Initialize form data with current values
    if (correctionsData.value?.applicant_data) {
      initializeFormData(correctionsData.value.applicant_data)
    }
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string } } }
    error.value = err.response?.data?.message || 'Error al cargar las correcciones'
    log.error('Failed to load corrections:', e)
  } finally {
    isLoading.value = false
  }
}

const initializeFormData = (data: ApplicantData) => {
  formData.nombre = {
    first_name: data.first_name || '',
    last_name_1: data.last_name_1 || '',
    last_name_2: data.last_name_2 || ''
  }
  formData.identidad = {
    curp: data.curp || '',
    rfc: data.rfc || '',
    ine_clave: data.ine_clave || '',
    birth_date: data.birth_date || ''
  }
  formData.contacto = {
    phone: data.phone || '',
    email: data.email || ''
  }
  if (data.address) {
    formData.direccion = {
      street: data.address.street || '',
      ext_number: data.address.ext_number || '',
      int_number: data.address.int_number || '',
      neighborhood: data.address.neighborhood || '',
      postal_code: data.address.postal_code || '',
      municipality: data.address.municipality || '',
      state: data.address.state || '',
      housing_type: data.address.housing_type || '',
      years_at_address: data.address.years_at_address || 0,
      months_at_address: data.address.months_at_address || 0
    }
  }
  if (data.employment) {
    formData.empleo = {
      type: data.employment.type || 'EMPLOYEE',
      company_name: data.employment.company_name || '',
      position: data.employment.position || '',
      monthly_income: data.employment.monthly_income || 0,
      seniority_years: data.employment.seniority_years || 0,
      seniority_months: data.employment.seniority_months || 0
    }
  }
}

// Group rejected fields by section
const groupedSections = computed(() => {
  const rejected = correctionsData.value?.rejected_fields || []
  const sections: Section[] = []

  for (const [sectionId, def] of Object.entries(sectionDefinitions)) {
    const sectionRejectedFields = rejected.filter(f =>
      def.fields.includes(f.field_name)
    )

    if (sectionRejectedFields.length > 0) {
      sections.push({
        id: sectionId,
        title: def.title,
        icon: def.icon,
        fields: def.fields,
        rejectedFields: sectionRejectedFields,
        isExpanded: false
      })
    }
  }

  return sections
})

const hasCorrections = computed(() => groupedSections.value.length > 0 || (correctionsData.value?.rejected_documents?.length || 0) > 0)
const totalRejectedFields = computed(() => correctionsData.value?.rejected_fields?.length || 0)
const totalRejectedDocuments = computed(() => correctionsData.value?.rejected_documents?.length || 0)

// Check if a specific field is rejected
const isFieldRejected = (sectionId: string, fieldName: string): boolean => {
  const section = groupedSections.value.find(s => s.id === sectionId)
  if (!section) return false
  return section.rejectedFields.some(f => f.field_name === fieldName)
}

// Get rejection reason for a field
const getFieldRejectionReason = (sectionId: string, fieldName: string): string | null => {
  const section = groupedSections.value.find(s => s.id === sectionId)
  if (!section) return null
  const field = section.rejectedFields.find(f => f.field_name === fieldName)
  return field?.rejection_reason || null
}

const startEditingSection = (sectionId: string) => {
  editingSection.value = sectionId
}

const cancelEditing = () => {
  editingSection.value = null
  // Reset form data to current values
  if (correctionsData.value?.applicant_data) {
    initializeFormData(correctionsData.value.applicant_data)
  }
}

const submitSectionCorrection = async (sectionId: string) => {
  isSaving.value = true
  error.value = null
  successMessage.value = null

  try {
    // Submit corrections for each rejected field in this section
    const section = groupedSections.value.find(s => s.id === sectionId)
    if (!section) return

    // Get the form data for this section
    const sectionFormData = formData[sectionId as keyof typeof formData]

    // Submit corrections based on section type
    if (sectionId === 'nombre') {
      // Enviar el nombre completo como objeto (el rechazo es de sección completa)
      // El backend identificará qué campo específico fue rechazado y actualizará todos
      const rejectedField = section.rejectedFields[0]
      if (rejectedField) {
        await v2.applicant.correction.submit({
          field_name: rejectedField.field_name,
          new_value: formData.nombre // Enviar todos los campos de nombre
        })
      }
    } else if (sectionId === 'identidad') {
      // Submit each identity field that was rejected
      for (const field of section.rejectedFields) {
        const fieldKey = field.field_name as keyof typeof formData.identidad
        const newValue = formData.identidad[fieldKey]
        await v2.applicant.correction.submit({
          field_name: field.field_name,
          new_value: newValue
        })
      }
    } else if (sectionId === 'contacto') {
      // Submit each contact field that was rejected
      for (const field of section.rejectedFields) {
        const fieldKey = field.field_name as keyof typeof formData.contacto
        const newValue = formData.contacto[fieldKey]
        await v2.applicant.correction.submit({
          field_name: field.field_name,
          new_value: newValue
        })
      }
    } else if (sectionId === 'direccion') {
      await v2.applicant.correction.submit({
        field_name: 'address',
        new_value: formData.direccion
      })
    } else if (sectionId === 'empleo') {
      await v2.applicant.correction.submit({
        field_name: 'employment',
        new_value: formData.empleo
      })
    }

    successMessage.value = `${section.title} actualizado correctamente`
    editingSection.value = null

    // Reload corrections
    await loadCorrections()
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string } } }
    error.value = err.response?.data?.message || 'Error al enviar la corrección'
    log.error('Failed to submit correction:', e)
  } finally {
    isSaving.value = false
  }
}


const goBack = () => {
  router.push('/dashboard')
}

// Correction history computed
const correctionHistory = computed(() => correctionsData.value?.correction_history || [])
const hasCorrectionHistory = computed(() => correctionHistory.value.length > 0)

// Rejected documents computed
const rejectedDocuments = computed(() => correctionsData.value?.rejected_documents || [])
const hasRejectedDocuments = computed(() => rejectedDocuments.value.length > 0)

// Document upload handler
const handleDocumentUpload = async (doc: RejectedDocument, event: Event) => {
  const input = event.target as HTMLInputElement
  if (!input.files || !input.files[0]) return

  const file = input.files[0]

  // Validate file size (10MB max)
  if (file.size > 10 * 1024 * 1024) {
    doc.uploadError = 'El archivo excede el tamaño máximo de 10MB'
    return
  }

  // Validate file type
  const validTypes = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png']
  if (!validTypes.includes(file.type)) {
    doc.uploadError = 'Formato no válido. Usa PDF, JPG o PNG'
    return
  }

  doc.isUploading = true
  doc.uploadError = undefined

  try {
    // Note: Document is automatically associated with Person
    // application_id is passed in metadata for reference
    await v2.applicant.document.upload(file, doc.type, {
      metadata: { application_id: doc.application_id }
    })
    doc.uploadSuccess = true
    successMessage.value = `${doc.type_label} subido correctamente`

    // Reload corrections to update the list
    setTimeout(() => {
      loadCorrections()
    }, 1500)
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string } } }
    doc.uploadError = err.response?.data?.message || 'Error al subir el documento'
    log.error('Failed to upload document:', e)
  } finally {
    doc.isUploading = false
  }
}

// Format file size
const formatFileSize = (bytes: number): string => {
  if (bytes < 1024) return bytes + ' B'
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB'
  return (bytes / (1024 * 1024)).toFixed(1) + ' MB'
}

// Format value for display
const formatValue = (value: unknown): string => {
  if (value === null || value === undefined) return '(empty)'
  if (typeof value === 'object') {
    const obj = value as Record<string, unknown>

    // For name objects, display as "FirstName LastName1 LastName2"
    if ('first_name' in obj || 'last_name_1' in obj || 'last_name_2' in obj) {
      const parts = [obj.first_name, obj.last_name_1, obj.last_name_2]
        .filter(Boolean)
        .map(String)
      return parts.join(' ') || '(empty)'
    }

    // For other objects (address, employment), display values separated by comma
    const parts: string[] = []
    for (const [, val] of Object.entries(obj)) {
      if (val) parts.push(String(val))
    }
    return parts.join(', ') || '(empty)'
  }
  return String(value) || '(empty)'
}

// WebSocket channel reference
 
let echoChannel: ReturnType<EchoInstance['private']> | null = null

// Setup WebSocket listener
const setupWebSocket = () => {
  const echo = getEcho()
  if (!echo) return

  const tenantId = tenantStore.tenant?.id
  const applicantId = profileStore.profile?.id

  if (!tenantId || !applicantId) {
    log.warn('Cannot setup WebSocket: missing tenant or applicant ID')
    return
  }

  const channelName = `tenant.${tenantId}.applicant.${applicantId}`
  log.debug('Connecting to corrections channel:', channelName)

  echoChannel = echo.private(channelName)
  echoChannel.listen('.data.correction.submitted', (event: DataCorrectionSubmittedEvent) => {
    log.debug('Correction submitted via WebSocket:', event)
    // Reload corrections to get the latest data
    loadCorrections()
  })
}

// Cleanup WebSocket on unmount
onUnmounted(() => {
  if (echoChannel) {
    const echo = getEcho()
    if (echo) {
      const tenantId = tenantStore.tenant?.id
      const applicantId = profileStore.profile?.id
      if (tenantId && applicantId) {
        echo.leave(`tenant.${tenantId}.applicant.${applicantId}`)
      }
    }
  }
})

// Setup WebSocket after mount
onMounted(() => {
  setupWebSocket()
})
</script>

<template>
  <div class="min-h-screen bg-gray-50">
    <!-- Header -->
    <header class="bg-white border-b px-4 py-4 sticky top-0 z-10">
      <div class="max-w-2xl mx-auto flex items-center gap-4">
        <button
          class="p-2 -ml-2 text-gray-500 hover:text-gray-700 active:bg-gray-100 rounded-lg transition-colors"
          @click="goBack"
        >
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
          </svg>
        </button>
        <div>
          <h1 class="text-lg font-semibold text-gray-900">Correcciones Pendientes</h1>
          <p class="text-sm text-gray-500">Revisa y corrige la información rechazada</p>
        </div>
      </div>
    </header>

    <!-- Main Content -->
    <main class="max-w-2xl mx-auto px-4 py-6 pb-24">
      <!-- Loading State -->
      <div v-if="isLoading" class="bg-white rounded-2xl shadow-sm p-8 text-center">
        <div class="animate-spin w-8 h-8 border-4 border-primary-600 border-t-transparent rounded-full mx-auto" />
        <p class="text-gray-500 mt-4">Cargando correcciones...</p>
      </div>

      <!-- Error State -->
      <div v-else-if="error && !hasCorrections" class="bg-red-50 rounded-xl p-4 mb-6">
        <div class="flex gap-3">
          <svg class="w-5 h-5 text-red-500 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <div class="text-sm text-red-800">
            <p class="font-medium">Error</p>
            <p class="text-red-600 mt-1">{{ error }}</p>
          </div>
        </div>
      </div>

      <template v-else>
        <!-- Success Message -->
        <Transition
          enter-active-class="transition ease-out duration-200"
          enter-from-class="opacity-0 -translate-y-2"
          enter-to-class="opacity-100 translate-y-0"
          leave-active-class="transition ease-in duration-150"
          leave-from-class="opacity-100 translate-y-0"
          leave-to-class="opacity-0 -translate-y-2"
        >
          <div v-if="successMessage" class="bg-green-50 rounded-xl p-4 mb-6">
            <div class="flex gap-3">
              <svg class="w-5 h-5 text-green-500 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              <p class="text-sm text-green-800">{{ successMessage }}</p>
            </div>
          </div>
        </Transition>

        <!-- Error Banner -->
        <div v-if="error" class="bg-red-50 rounded-xl p-4 mb-6">
          <div class="flex gap-3">
            <svg class="w-5 h-5 text-red-500 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <p class="text-sm text-red-600">{{ error }}</p>
          </div>
        </div>

        <!-- No Corrections State -->
        <div v-if="!hasCorrections" class="space-y-6">
          <div class="bg-white rounded-2xl shadow-sm p-8 text-center">
            <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto">
              <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </div>
            <h2 class="mt-4 text-lg font-semibold text-gray-900">Todo en orden</h2>
            <p class="mt-2 text-gray-500">No tienes datos pendientes de corrección</p>
            <AppButton
              variant="primary"
              class="mt-6"
              @click="goBack"
            >
              Volver al inicio
            </AppButton>
          </div>

          <!-- Correction History when no pending corrections -->
          <div v-if="hasCorrectionHistory" class="bg-white rounded-2xl shadow-sm overflow-hidden">
            <div class="p-4 border-b bg-gray-50">
              <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-blue-100 rounded-xl flex items-center justify-center">
                  <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                </div>
                <div>
                  <h3 class="font-semibold text-gray-900">Historial de Correcciones</h3>
                  <p class="text-xs text-gray-500">{{ correctionHistory.length }} corrección(es) realizadas</p>
                </div>
              </div>
            </div>
            <div class="divide-y">
              <div
                v-for="(entry, index) in correctionHistory"
                :key="index"
                class="p-4"
              >
                <div class="flex items-start justify-between mb-2">
                  <span class="font-medium text-gray-900">{{ entry.field_label }}</span>
                  <div class="text-right">
                    <span class="text-xs text-gray-500 block">{{ formatDateTime(entry.corrected_at) }}</span>
                    <span v-if="entry.corrected_by" class="text-xs text-gray-400">por {{ entry.corrected_by.name }}</span>
                  </div>
                </div>
                <div class="space-y-1 text-sm">
                  <div class="flex items-start gap-2">
                    <span class="text-red-500 line-through">{{ formatValue(entry.old_value) }}</span>
                  </div>
                  <div class="flex items-start gap-2">
                    <span class="text-green-600 font-medium">{{ formatValue(entry.new_value) }}</span>
                  </div>
                  <div v-if="entry.rejection_reason" class="text-xs text-gray-500 italic mt-1">
                    Motivo: {{ entry.rejection_reason }}
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Sections List -->
        <div v-else class="space-y-4">
          <!-- Info Banner -->
          <div class="bg-amber-50 rounded-xl p-4 mb-6">
            <div class="flex gap-3">
              <svg class="w-5 h-5 text-amber-600 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
              </svg>
              <div class="text-sm text-amber-800">
                <p class="font-medium">
                  <template v-if="totalRejectedFields > 0 && totalRejectedDocuments > 0">
                    Tienes {{ totalRejectedFields }} campo(s) y {{ totalRejectedDocuments }} documento(s) que requieren corrección
                  </template>
                  <template v-else-if="totalRejectedFields > 0">
                    Tienes {{ totalRejectedFields }} campo(s) en {{ groupedSections.length }} sección(es) que requieren corrección
                  </template>
                  <template v-else>
                    Tienes {{ totalRejectedDocuments }} documento(s) que requieren corrección
                  </template>
                </p>
                <p class="text-amber-700 mt-1">Por favor revisa y corrige la información marcada para continuar con tu solicitud.</p>
              </div>
            </div>
          </div>

          <!-- Section Cards -->
          <div
            v-for="section in groupedSections"
            :key="section.id"
            class="bg-white rounded-2xl shadow-sm overflow-hidden"
          >
            <!-- Section Header -->
            <div class="p-4 border-b bg-gray-50">
              <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                  <div class="w-10 h-10 bg-red-100 rounded-xl flex items-center justify-center">
                    <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="section.icon" />
                    </svg>
                  </div>
                  <div>
                    <h3 class="font-semibold text-gray-900">{{ section.title }}</h3>
                    <p class="text-xs text-gray-500">{{ section.rejectedFields.length }} campo(s) rechazado(s)</p>
                  </div>
                </div>
                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-red-100 text-red-700">
                  <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                  </svg>
                  Requiere corrección
                </span>
              </div>
            </div>

            <div class="p-4">
              <!-- Rejection Reasons -->
              <div class="space-y-2 mb-4">
                <div
                  v-for="field in section.rejectedFields"
                  :key="field.id"
                  class="bg-red-50 rounded-lg p-3"
                >
                  <div class="flex items-start gap-2">
                    <svg class="w-4 h-4 text-red-500 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                      <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                    </svg>
                    <div class="flex-1">
                      <p class="text-sm font-medium text-red-800">{{ field.field_label }}</p>
                      <p class="text-sm text-red-700">{{ field.rejection_reason }}</p>
                      <p class="text-xs text-red-500 mt-1">Rechazado {{ formatDateTime(field.rejected_at) }}</p>
                    </div>
                  </div>
                </div>
              </div>

              <!-- View/Edit Mode -->
              <template v-if="editingSection !== section.id">
                <!-- Current Values Preview -->
                <div class="bg-gray-50 rounded-xl p-4 mb-4">
                  <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-3">Valores Actuales</p>

                  <!-- Nombre Section -->
                  <template v-if="section.id === 'nombre'">
                    <div class="grid grid-cols-1 gap-3">
                      <div>
                        <p class="text-xs text-gray-500">{{ fieldLabels.first_name }}</p>
                        <p class="text-sm font-medium text-gray-900">{{ formData.nombre.first_name || '(vacío)' }}</p>
                      </div>
                      <div>
                        <p class="text-xs text-gray-500">{{ fieldLabels.last_name_1 }}</p>
                        <p class="text-sm font-medium text-gray-900">{{ formData.nombre.last_name_1 || '(vacío)' }}</p>
                      </div>
                      <div>
                        <p class="text-xs text-gray-500">{{ fieldLabels.last_name_2 }}</p>
                        <p class="text-sm font-medium text-gray-900">{{ formData.nombre.last_name_2 || '(vacío)' }}</p>
                      </div>
                    </div>
                  </template>

                  <!-- Identidad Section -->
                  <template v-else-if="section.id === 'identidad'">
                    <div class="grid grid-cols-1 gap-3">
                      <div v-if="section.rejectedFields.some(f => f.field_name === 'curp')">
                        <p class="text-xs text-gray-500">{{ fieldLabels.curp }}</p>
                        <p class="text-sm font-medium text-gray-900 font-mono">{{ formData.identidad.curp || '(vacío)' }}</p>
                      </div>
                      <div v-if="section.rejectedFields.some(f => f.field_name === 'rfc')">
                        <p class="text-xs text-gray-500">{{ fieldLabels.rfc }}</p>
                        <p class="text-sm font-medium text-gray-900 font-mono">{{ formData.identidad.rfc || '(vacío)' }}</p>
                      </div>
                      <div v-if="section.rejectedFields.some(f => f.field_name === 'ine_clave')">
                        <p class="text-xs text-gray-500">{{ fieldLabels.ine_clave }}</p>
                        <p class="text-sm font-medium text-gray-900 font-mono">{{ formData.identidad.ine_clave || '(vacío)' }}</p>
                      </div>
                      <div v-if="section.rejectedFields.some(f => f.field_name === 'birth_date')">
                        <p class="text-xs text-gray-500">{{ fieldLabels.birth_date }}</p>
                        <p class="text-sm font-medium text-gray-900">{{ formData.identidad.birth_date || '(vacío)' }}</p>
                      </div>
                    </div>
                  </template>

                  <!-- Contacto Section -->
                  <template v-else-if="section.id === 'contacto'">
                    <div class="grid grid-cols-1 gap-3">
                      <div v-if="section.rejectedFields.some(f => f.field_name === 'phone')">
                        <p class="text-xs text-gray-500">{{ fieldLabels.phone }}</p>
                        <p class="text-sm font-medium text-gray-900">{{ formData.contacto.phone || '(vacío)' }}</p>
                      </div>
                      <div v-if="section.rejectedFields.some(f => f.field_name === 'email')">
                        <p class="text-xs text-gray-500">{{ fieldLabels.email }}</p>
                        <p class="text-sm font-medium text-gray-900">{{ formData.contacto.email || '(vacío)' }}</p>
                      </div>
                    </div>
                  </template>

                  <!-- Direccion Section -->
                  <template v-else-if="section.id === 'direccion'">
                    <div class="grid grid-cols-1 gap-3">
                      <div>
                        <p class="text-xs text-gray-500">Tipo de vivienda</p>
                        <p class="text-sm font-medium text-gray-900">
                          {{ housingTypes.find(t => t.value === formData.direccion.housing_type)?.label || formData.direccion.housing_type || '(vacío)' }}
                        </p>
                      </div>
                      <div>
                        <p class="text-xs text-gray-500">Dirección</p>
                        <p class="text-sm font-medium text-gray-900">
                          {{ formData.direccion.street }} {{ formData.direccion.ext_number }}{{ formData.direccion.int_number ? ' Int. ' + formData.direccion.int_number : '' }}
                        </p>
                      </div>
                      <div>
                        <p class="text-xs text-gray-500">Colonia / C.P.</p>
                        <p class="text-sm font-medium text-gray-900">
                          {{ formData.direccion.neighborhood }}, {{ formData.direccion.postal_code }}
                        </p>
                      </div>
                      <div>
                        <p class="text-xs text-gray-500">Municipio / Estado</p>
                        <p class="text-sm font-medium text-gray-900">
                          {{ formData.direccion.municipality }}, {{ formData.direccion.state }}
                        </p>
                      </div>
                      <div>
                        <p class="text-xs text-gray-500">Tiempo en este domicilio</p>
                        <p class="text-sm font-medium text-gray-900">
                          {{ formData.direccion.years_at_address }} año{{ formData.direccion.years_at_address !== 1 ? 's' : '' }}, {{ formData.direccion.months_at_address }} mes{{ formData.direccion.months_at_address !== 1 ? 'es' : '' }}
                        </p>
                      </div>
                    </div>
                  </template>

                  <!-- Empleo Section -->
                  <template v-else-if="section.id === 'empleo'">
                    <div class="grid grid-cols-1 gap-3">
                      <div>
                        <p class="text-xs text-gray-500">{{ fieldLabels.type }}</p>
                        <p class="text-sm font-medium text-gray-900">
                          {{ employmentTypes.find(t => t.value === formData.empleo.type)?.label || formData.empleo.type }}
                        </p>
                      </div>
                      <!-- EMPLOYEE: Show company name, position, seniority -->
                      <template v-if="showCompanyDetails">
                        <div>
                          <p class="text-xs text-gray-500">Nombre de la Empresa</p>
                          <p class="text-sm font-medium text-gray-900">{{ formData.empleo.company_name || '(vacío)' }}</p>
                        </div>
                        <div>
                          <p class="text-xs text-gray-500">Puesto</p>
                          <p class="text-sm font-medium text-gray-900">{{ formData.empleo.position || '(vacío)' }}</p>
                        </div>
                        <div>
                          <p class="text-xs text-gray-500">Antigüedad</p>
                          <p class="text-sm font-medium text-gray-900">
                            {{ formData.empleo.seniority_years }} año{{ formData.empleo.seniority_years !== 1 ? 's' : '' }}, {{ formData.empleo.seniority_months }} mes{{ formData.empleo.seniority_months !== 1 ? 'es' : '' }}
                          </p>
                        </div>
                      </template>
                      <!-- SELF_EMPLOYED / BUSINESS_OWNER: Show business name, seniority -->
                      <template v-else-if="showBusinessDetails">
                        <div>
                          <p class="text-xs text-gray-500">{{ formData.empleo.type === 'BUSINESS_OWNER' ? 'Nombre del Negocio' : 'Descripción de Actividad' }}</p>
                          <p class="text-sm font-medium text-gray-900">{{ formData.empleo.company_name || '(vacío)' }}</p>
                        </div>
                        <div>
                          <p class="text-xs text-gray-500">{{ formData.empleo.type === 'BUSINESS_OWNER' ? 'Años con Negocio' : 'Años de Experiencia' }}</p>
                          <p class="text-sm font-medium text-gray-900">
                            {{ formData.empleo.seniority_years }} año{{ formData.empleo.seniority_years !== 1 ? 's' : '' }}, {{ formData.empleo.seniority_months }} mes{{ formData.empleo.seniority_months !== 1 ? 'es' : '' }}
                          </p>
                        </div>
                      </template>
                      <!-- Always show income -->
                      <div>
                        <p class="text-xs text-gray-500">{{ fieldLabels.monthly_income }}</p>
                        <p class="text-sm font-medium text-gray-900">{{ formatMoney(formData.empleo.monthly_income) }}</p>
                      </div>
                    </div>
                  </template>
                </div>

                <!-- Edit Button -->
                <AppButton
                  variant="primary"
                  full-width
                  @click="startEditingSection(section.id)"
                >
                  <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                  </svg>
                  Corregir {{ section.title }}
                </AppButton>
              </template>

              <!-- Edit Form -->
              <template v-else>
                <div class="space-y-4">
                  <!-- Nombre Form - Todos editables porque el rechazo es de la sección completa -->
                  <template v-if="section.id === 'nombre'">
                    <div>
                      <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ fieldLabels.first_name }} *</label>
                      <input
                        v-model="formData.nombre.first_name"
                        type="text"
                        placeholder="Ej: Juan Carlos"
                        class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                      />
                    </div>
                    <div>
                      <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ fieldLabels.last_name_1 }} *</label>
                      <input
                        v-model="formData.nombre.last_name_1"
                        type="text"
                        placeholder="Ej: García"
                        class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                      />
                    </div>
                    <div>
                      <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ fieldLabels.last_name_2 }}</label>
                      <input
                        v-model="formData.nombre.last_name_2"
                        type="text"
                        placeholder="Ej: López"
                        class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                      />
                    </div>
                  </template>

                  <!-- Identidad Form -->
                  <template v-else-if="section.id === 'identidad'">
                    <div v-if="section.rejectedFields.some(f => f.field_name === 'curp')">
                      <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ fieldLabels.curp }} *</label>
                      <input
                        v-model="formData.identidad.curp"
                        type="text"
                        maxlength="18"
                        placeholder="18 caracteres"
                        class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500 font-mono uppercase"
                      />
                    </div>
                    <div v-if="section.rejectedFields.some(f => f.field_name === 'rfc')">
                      <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ fieldLabels.rfc }} *</label>
                      <input
                        v-model="formData.identidad.rfc"
                        type="text"
                        maxlength="13"
                        placeholder="12-13 caracteres"
                        class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500 font-mono uppercase"
                      />
                    </div>
                    <div v-if="section.rejectedFields.some(f => f.field_name === 'ine_clave')">
                      <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ fieldLabels.ine_clave }}</label>
                      <input
                        v-model="formData.identidad.ine_clave"
                        type="text"
                        placeholder="Clave de elector"
                        class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500 font-mono"
                      />
                    </div>
                    <div v-if="section.rejectedFields.some(f => f.field_name === 'birth_date')">
                      <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ fieldLabels.birth_date }} *</label>
                      <input
                        v-model="formData.identidad.birth_date"
                        type="date"
                        class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                      />
                    </div>
                  </template>

                  <!-- Contacto Form -->
                  <template v-else-if="section.id === 'contacto'">
                    <div v-if="section.rejectedFields.some(f => f.field_name === 'phone')">
                      <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ fieldLabels.phone }} *</label>
                      <input
                        v-model="formData.contacto.phone"
                        type="tel"
                        maxlength="10"
                        placeholder="10 dígitos"
                        class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                      />
                    </div>
                    <div v-if="section.rejectedFields.some(f => f.field_name === 'email')">
                      <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ fieldLabels.email }} *</label>
                      <input
                        v-model="formData.contacto.email"
                        type="email"
                        placeholder="correo@ejemplo.com"
                        class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                      />
                    </div>
                  </template>

                  <!-- Direccion Form -->
                  <template v-else-if="section.id === 'direccion'">
                    <div>
                      <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ fieldLabels.street }} *</label>
                      <input
                        v-model="formData.direccion.street"
                        type="text"
                        placeholder="Nombre de la calle"
                        class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                      />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                      <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ fieldLabels.ext_number }} *</label>
                        <input
                          v-model="formData.direccion.ext_number"
                          type="text"
                          placeholder="Núm. Ext."
                          class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                        />
                      </div>
                      <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ fieldLabels.int_number }}</label>
                        <input
                          v-model="formData.direccion.int_number"
                          type="text"
                          placeholder="Núm. Int."
                          class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                        />
                      </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                      <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ fieldLabels.postal_code }} *</label>
                        <input
                          v-model="formData.direccion.postal_code"
                          type="text"
                          maxlength="5"
                          placeholder="C.P."
                          class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                        />
                      </div>
                      <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ fieldLabels.neighborhood }} *</label>
                        <input
                          v-model="formData.direccion.neighborhood"
                          type="text"
                          placeholder="Colonia"
                          class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                        />
                      </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                      <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ fieldLabels.municipality }} *</label>
                        <input
                          v-model="formData.direccion.municipality"
                          type="text"
                          placeholder="Municipio"
                          class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                        />
                      </div>
                      <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ fieldLabels.state }} *</label>
                        <input
                          v-model="formData.direccion.state"
                          type="text"
                          placeholder="Estado"
                          class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                        />
                      </div>
                    </div>
                    <!-- Tipo de vivienda -->
                    <div>
                      <label class="block text-sm font-medium text-gray-700 mb-1.5">Tipo de vivienda *</label>
                      <div class="relative">
                        <select
                          v-model="formData.direccion.housing_type"
                          class="w-full px-4 py-3 pr-10 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500 bg-white appearance-none cursor-pointer"
                        >
                          <option value="">Selecciona una opción</option>
                          <option v-for="type in housingTypes" :key="type.value" :value="type.value">
                            {{ type.label }}
                          </option>
                        </select>
                        <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                          <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                          </svg>
                        </div>
                      </div>
                    </div>
                    <!-- Tiempo en el domicilio -->
                    <div>
                      <label class="block text-sm font-medium text-gray-700 mb-1.5">Tiempo en este domicilio *</label>
                      <div class="grid grid-cols-2 gap-3">
                        <div>
                          <div class="relative">
                            <input
                              v-model.number="formData.direccion.years_at_address"
                              type="number"
                              min="0"
                              max="99"
                              placeholder="0"
                              class="w-full px-4 py-3 pr-16 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                              inputmode="numeric"
                            />
                            <span class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-500 text-sm">años</span>
                          </div>
                        </div>
                        <div>
                          <div class="relative">
                            <input
                              v-model.number="formData.direccion.months_at_address"
                              type="number"
                              min="0"
                              max="11"
                              placeholder="0"
                              class="w-full px-4 py-3 pr-20 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                              inputmode="numeric"
                            />
                            <span class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-500 text-sm">meses</span>
                          </div>
                        </div>
                      </div>
                    </div>
                  </template>

                  <!-- Empleo Form -->
                  <template v-else-if="section.id === 'empleo'">
                    <!-- Employment Type (always shown) -->
                    <div>
                      <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ fieldLabels.type }} *</label>
                      <div class="relative">
                        <select
                          v-model="formData.empleo.type"
                          class="w-full px-4 py-3 pr-10 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500 bg-white appearance-none cursor-pointer"
                        >
                          <option v-for="type in employmentTypes" :key="type.value" :value="type.value">
                            {{ type.label }}
                          </option>
                        </select>
                        <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                          <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                          </svg>
                        </div>
                      </div>
                    </div>

                    <!-- EMPLOYEE fields -->
                    <template v-if="showCompanyDetails">
                      <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Nombre de la Empresa *</label>
                        <input
                          v-model="formData.empleo.company_name"
                          type="text"
                          placeholder="EMPRESA S.A. DE C.V."
                          class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500 uppercase"
                        />
                      </div>
                      <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Puesto *</label>
                        <input
                          v-model="formData.empleo.position"
                          type="text"
                          placeholder="GERENTE DE VENTAS"
                          class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500 uppercase"
                        />
                      </div>
                      <div class="grid grid-cols-2 gap-3">
                        <div>
                          <label class="block text-sm font-medium text-gray-700 mb-1.5">Años en empleo</label>
                          <div class="relative">
                            <input
                              v-model.number="formData.empleo.seniority_years"
                              type="number"
                              min="0"
                              max="99"
                              placeholder="0"
                              class="w-full px-4 py-3 pr-16 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                              inputmode="numeric"
                            />
                            <span class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-500 text-sm">años</span>
                          </div>
                        </div>
                        <div>
                          <label class="block text-sm font-medium text-gray-700 mb-1.5">Meses adicionales</label>
                          <div class="relative">
                            <input
                              v-model.number="formData.empleo.seniority_months"
                              type="number"
                              min="0"
                              max="11"
                              placeholder="0"
                              class="w-full px-4 py-3 pr-20 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                              inputmode="numeric"
                            />
                            <span class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-500 text-sm">meses</span>
                          </div>
                        </div>
                      </div>
                    </template>

                    <!-- SELF_EMPLOYED / BUSINESS_OWNER fields -->
                    <template v-else-if="showBusinessDetails">
                      <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">
                          {{ formData.empleo.type === 'BUSINESS_OWNER' ? 'Nombre del Negocio' : 'Descripción de Actividad' }}
                        </label>
                        <input
                          v-model="formData.empleo.company_name"
                          type="text"
                          :placeholder="formData.empleo.type === 'BUSINESS_OWNER' ? 'MI NEGOCIO S.A.' : 'SERVICIOS PROFESIONALES'"
                          class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500 uppercase"
                        />
                      </div>
                      <div class="grid grid-cols-2 gap-3">
                        <div>
                          <label class="block text-sm font-medium text-gray-700 mb-1.5">
                            {{ formData.empleo.type === 'BUSINESS_OWNER' ? 'Años con negocio' : 'Años de experiencia' }}
                          </label>
                          <div class="relative">
                            <input
                              v-model.number="formData.empleo.seniority_years"
                              type="number"
                              min="0"
                              max="99"
                              placeholder="0"
                              class="w-full px-4 py-3 pr-16 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                              inputmode="numeric"
                            />
                            <span class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-500 text-sm">años</span>
                          </div>
                        </div>
                        <div>
                          <label class="block text-sm font-medium text-gray-700 mb-1.5">Meses adicionales</label>
                          <div class="relative">
                            <input
                              v-model.number="formData.empleo.seniority_months"
                              type="number"
                              min="0"
                              max="11"
                              placeholder="0"
                              class="w-full px-4 py-3 pr-20 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                              inputmode="numeric"
                            />
                            <span class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-500 text-sm">meses</span>
                          </div>
                        </div>
                      </div>
                    </template>

                    <!-- Monthly Income (always shown) -->
                    <div>
                      <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ fieldLabels.monthly_income }} *</label>
                      <div class="relative">
                        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-500">$</span>
                        <input
                          v-model.number="formData.empleo.monthly_income"
                          type="number"
                          min="0"
                          placeholder="0"
                          class="w-full pl-8 pr-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                        />
                      </div>
                    </div>
                  </template>

                  <!-- Action Buttons -->
                  <div class="flex gap-3 pt-2">
                    <AppButton
                      variant="outline"
                      class="flex-1"
                      :disabled="isSaving"
                      @click="cancelEditing"
                    >
                      Cancelar
                    </AppButton>
                    <AppButton
                      variant="primary"
                      class="flex-1"
                      :loading="isSaving"
                      @click="submitSectionCorrection(section.id)"
                    >
                      <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                      </svg>
                      Guardar cambios
                    </AppButton>
                  </div>
                </div>
              </template>
            </div>
          </div>

          <!-- Rejected Documents Section -->
          <div v-if="hasRejectedDocuments" class="bg-white rounded-2xl shadow-sm overflow-hidden">
            <div class="p-4 border-b bg-gray-50">
              <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                  <div class="w-10 h-10 bg-red-100 rounded-xl flex items-center justify-center">
                    <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                  </div>
                  <div>
                    <h3 class="font-semibold text-gray-900">Documentos Rechazados</h3>
                    <p class="text-xs text-gray-500">{{ rejectedDocuments.length }} documento(s) por corregir</p>
                  </div>
                </div>
                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-red-100 text-red-700">
                  <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                  </svg>
                  Requiere corrección
                </span>
              </div>
            </div>

            <div class="divide-y">
              <div
                v-for="doc in rejectedDocuments"
                :key="doc.id"
                class="p-4"
              >
                <!-- Document Info -->
                <div class="flex items-start gap-3 mb-3">
                  <div class="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                    </svg>
                  </div>
                  <div class="flex-1 min-w-0">
                    <p class="font-medium text-gray-900">{{ doc.type_label }}</p>
                    <p class="text-sm text-gray-500 truncate">{{ doc.name }}</p>
                  </div>
                </div>

                <!-- Rejection Reason -->
                <div class="bg-red-50 rounded-lg p-3 mb-4">
                  <div class="flex items-start gap-2">
                    <svg class="w-4 h-4 text-red-500 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                      <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                    </svg>
                    <div class="flex-1">
                      <p class="text-sm font-medium text-red-800">Motivo del rechazo</p>
                      <p class="text-sm text-red-700">{{ doc.rejection_reason }}</p>
                      <p class="text-xs text-red-500 mt-1">Rechazado {{ formatDateTime(doc.rejected_at) }}</p>
                    </div>
                  </div>
                </div>

                <!-- Upload Success State -->
                <div v-if="doc.uploadSuccess" class="bg-green-50 rounded-xl p-4 text-center">
                  <svg class="w-8 h-8 text-green-500 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                  <p class="text-sm font-medium text-green-800">Documento subido correctamente</p>
                  <p class="text-xs text-green-600 mt-1">Pendiente de revisión</p>
                </div>

                <!-- Upload Area -->
                <template v-else>
                  <!-- Error Message -->
                  <div v-if="doc.uploadError" class="bg-red-50 rounded-lg p-3 mb-3">
                    <p class="text-sm text-red-600">{{ doc.uploadError }}</p>
                  </div>

                  <!-- Upload Button -->
                  <label class="block">
                    <div
                      class="border-2 border-dashed border-gray-300 rounded-xl p-6 text-center cursor-pointer hover:border-primary-400 hover:bg-primary-50 transition-colors"
                      :class="{ 'opacity-50 pointer-events-none': doc.isUploading }"
                    >
                      <template v-if="doc.isUploading">
                        <div class="animate-spin w-8 h-8 border-4 border-primary-600 border-t-transparent rounded-full mx-auto mb-2" />
                        <p class="text-sm text-gray-600">Subiendo documento...</p>
                      </template>
                      <template v-else>
                        <svg class="w-10 h-10 text-gray-400 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                        </svg>
                        <p class="text-sm font-medium text-gray-700">Toca para subir nuevo documento</p>
                        <p class="text-xs text-gray-500 mt-1">PDF, JPG o PNG (máx. 10MB)</p>
                      </template>
                    </div>
                    <input
                      type="file"
                      class="hidden"
                      accept=".pdf,.jpg,.jpeg,.png"
                      :disabled="doc.isUploading"
                      @change="handleDocumentUpload(doc, $event)"
                    />
                  </label>
                </template>
              </div>
            </div>
          </div>

          <!-- Correction History when has pending corrections -->
          <div v-if="hasCorrectionHistory" class="bg-white rounded-2xl shadow-sm overflow-hidden mt-6">
            <div class="p-4 border-b bg-gray-50">
              <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-blue-100 rounded-xl flex items-center justify-center">
                  <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                </div>
                <div>
                  <h3 class="font-semibold text-gray-900">Historial de Correcciones</h3>
                  <p class="text-xs text-gray-500">{{ correctionHistory.length }} corrección(es) anteriores</p>
                </div>
              </div>
            </div>
            <div class="divide-y">
              <div
                v-for="(entry, index) in correctionHistory"
                :key="index"
                class="p-4"
              >
                <div class="flex items-start justify-between mb-2">
                  <span class="font-medium text-gray-900">{{ entry.field_label }}</span>
                  <div class="text-right">
                    <span class="text-xs text-gray-500 block">{{ formatDateTime(entry.corrected_at) }}</span>
                    <span v-if="entry.corrected_by" class="text-xs text-gray-400">por {{ entry.corrected_by.name }}</span>
                  </div>
                </div>
                <div class="space-y-1 text-sm">
                  <div class="flex items-start gap-2">
                    <span class="text-red-500 line-through">{{ formatValue(entry.old_value) }}</span>
                  </div>
                  <div class="flex items-start gap-2">
                    <span class="text-green-600 font-medium">{{ formatValue(entry.new_value) }}</span>
                  </div>
                  <div v-if="entry.rejection_reason" class="text-xs text-gray-500 italic mt-1">
                    Motivo: {{ entry.rejection_reason }}
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </template>
    </main>

    <!-- Fixed Bottom Button -->
    <div v-if="hasCorrections" class="fixed bottom-0 left-0 right-0 bg-white border-t px-4 py-4 safe-area-bottom">
      <div class="max-w-2xl mx-auto">
        <AppButton
          variant="outline"
          size="lg"
          full-width
          @click="goBack"
        >
          Volver al inicio
        </AppButton>
      </div>
    </div>
  </div>
</template>

<style scoped>
.safe-area-bottom {
  padding-bottom: max(1rem, env(safe-area-inset-bottom));
}
</style>
