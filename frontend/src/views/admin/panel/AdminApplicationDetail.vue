<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { AppButton, AppConfirmModal } from '@/components/common'

const route = useRoute()
const router = useRouter()

interface Document {
  id: string
  type: string
  name: string
  status: 'PENDING' | 'APPROVED' | 'REJECTED'
  url?: string
  uploaded_at?: string
}

interface Reference {
  id: string
  full_name: string
  relationship: string
  phone: string
  verified: boolean
}

interface ApplicationCompleteness {
  personal_data: boolean
  address: boolean
  employment: boolean
  documents: { uploaded: number; required: number; approved: number }
  references: { count: number; verified: number }
  signature: boolean
}

interface Application {
  id: string
  folio: string
  status: string
  created_at: string
  updated_at: string
  assigned_to?: string
  completeness: ApplicationCompleteness
  applicant: {
    id: string
    full_name: string
    first_name: string
    last_name_1: string
    last_name_2: string
    email: string
    phone: string
    curp: string
    rfc: string
    birth_date: string
    nationality: string
    gender: string
  }
  address: {
    street: string
    ext_number: string
    int_number?: string
    neighborhood: string
    postal_code: string
    municipality: string
    state: string
    housing_type: string
    years_living: number
    months_living: number
  }
  employment: {
    type: string
    company_name?: string
    position?: string
    monthly_income: number
    seniority_months: number
  }
  loan: {
    product_name: string
    requested_amount: number
    approved_amount?: number
    term_months: number
    payment_frequency: string
    interest_rate: number
    monthly_payment: number
    total_to_pay: number
    purpose: string
  }
  documents: Document[]
  references: Reference[]
  notes: { id: string; text: string; author: string; created_at: string }[]
  timeline: { id: string; action: string; description: string; author: string; created_at: string }[]
}

const application = ref<Application | null>(null)
const loading = ref(true)
const activeTab = ref('general')
const showStatusModal = ref(false)
const newStatus = ref('')
const statusNote = ref('')
const isUpdatingStatus = ref(false)

// Counter-offer state
const showCounterOfferModal = ref(false)
const isSubmittingCounterOffer = ref(false)
const counterOffer = ref({
  amount: 0,
  term_months: 12,
  interest_rate: 36,
  payment_frequency: 'QUINCENAL',
  reason: ''
})

// Document rejection state
const showDocRejectModal = ref(false)
const selectedDocument = ref<Document | null>(null)
const docRejectReason = ref('')
const docRejectComment = ref('')
const isRejectingDoc = ref(false)

const docRejectReasons = [
  { value: 'ILLEGIBLE', label: 'Documento ilegible' },
  { value: 'EXPIRED', label: 'Documento vencido' },
  { value: 'INCOMPLETE', label: 'Información incompleta' },
  { value: 'WRONG_DOC', label: 'Documento incorrecto' },
  { value: 'MISMATCH', label: 'No coincide con datos proporcionados' },
  { value: 'LOW_QUALITY', label: 'Imagen de baja calidad' },
  { value: 'OUTDATED', label: 'Antigüedad mayor a 3 meses' },
  { value: 'OTHER', label: 'Otro motivo' }
]

// Reference verification state
const showVerifyRefModal = ref(false)
const selectedReference = ref<Reference | null>(null)
const refVerifyResult = ref<'VERIFIED' | 'NOT_VERIFIED' | 'NO_ANSWER'>('VERIFIED')
const refVerifyNotes = ref('')
const isVerifyingRef = ref(false)

// Document approval state
const showDocApproveModal = ref(false)
const docToApprove = ref<Document | null>(null)
const isApprovingDoc = ref(false)

// Add note state
const newNoteText = ref('')
const isAddingNote = ref(false)

// Calculated values for counter-offer
const counterOfferCalculation = computed(() => {
  const amount = counterOffer.value.amount
  const termMonths = counterOffer.value.term_months
  const annualRate = counterOffer.value.interest_rate
  const frequency = counterOffer.value.payment_frequency

  const periodsPerYear = frequency === 'QUINCENAL' ? 24 : 12
  const totalPeriods = frequency === 'QUINCENAL' ? termMonths * 2 : termMonths
  const periodRate = (annualRate / 100) / periodsPerYear

  let payment = 0
  if (periodRate > 0) {
    payment = amount * (periodRate * Math.pow(1 + periodRate, totalPeriods)) /
      (Math.pow(1 + periodRate, totalPeriods) - 1)
  } else {
    payment = amount / totalPeriods
  }

  const totalToPay = payment * totalPeriods
  const totalInterest = totalToPay - amount

  return {
    payment: Math.round(payment * 100) / 100,
    totalPeriods,
    totalToPay: Math.round(totalToPay * 100) / 100,
    totalInterest: Math.round(totalInterest * 100) / 100
  }
})

const tabs = [
  { id: 'general', label: 'Información General' },
  { id: 'documents', label: 'Documentos' },
  { id: 'references', label: 'Referencias' },
  { id: 'timeline', label: 'Historial' }
]

const statusOptions = [
  { value: 'SUBMITTED', label: 'Nueva', color: 'blue' },
  { value: 'IN_REVIEW', label: 'En Revisión', color: 'yellow' },
  { value: 'DOCS_PENDING', label: 'Docs Pendientes', color: 'orange' },
  { value: 'COUNTER_OFFERED', label: 'Contraoferta', color: 'indigo' },
  { value: 'APPROVED', label: 'Aprobada', color: 'green' },
  { value: 'REJECTED', label: 'Rechazada', color: 'red' },
  { value: 'CANCELLED', label: 'Cancelada', color: 'gray' },
  { value: 'DISBURSED', label: 'Desembolsada', color: 'purple' }
]

// Mock data - will be replaced with API calls
onMounted(async () => {
  loading.value = true

  // Simulate API call
  await new Promise(resolve => setTimeout(resolve, 500))

  const appId = route.params.id as string

  application.value = {
    id: appId,
    folio: 'LEN-2026-00042',
    status: 'IN_REVIEW',
    created_at: new Date(Date.now() - 5 * 3600000).toISOString(),
    updated_at: new Date(Date.now() - 1 * 3600000).toISOString(),
    assigned_to: 'Agente 1',
    completeness: {
      personal_data: true,
      address: true,
      employment: true,
      documents: { uploaded: 5, required: 5, approved: 2 },
      references: { count: 2, verified: 1 },
      signature: true
    },
    applicant: {
      id: '1',
      full_name: 'JUAN CARLOS PÉREZ GARCÍA',
      first_name: 'JUAN CARLOS',
      last_name_1: 'PÉREZ',
      last_name_2: 'GARCÍA',
      email: 'juan.perez@email.com',
      phone: '5512345678',
      curp: 'PEGJ850101HDFRRL09',
      rfc: 'PEGJ850101AB5',
      birth_date: '1985-01-01',
      nationality: 'MEX',
      gender: 'M'
    },
    address: {
      street: 'AV. REFORMA',
      ext_number: '123',
      int_number: 'A',
      neighborhood: 'ROMA NORTE',
      postal_code: '06600',
      municipality: 'CUAUHTÉMOC',
      state: 'CIUDAD DE MEXICO',
      housing_type: 'RENTADA',
      years_living: 3,
      months_living: 6
    },
    employment: {
      type: 'EMPLEADO',
      company_name: 'EMPRESA EJEMPLO SA DE CV',
      position: 'GERENTE DE VENTAS',
      monthly_income: 45000,
      seniority_months: 36
    },
    loan: {
      product_name: 'Crédito Personal',
      requested_amount: 85000,
      term_months: 12,
      payment_frequency: 'QUINCENAL',
      interest_rate: 36,
      monthly_payment: 8500,
      total_to_pay: 102000,
      purpose: 'CONSOLIDACION_DEUDA'
    },
    documents: [
      { id: '1', type: 'INE_FRONT', name: 'INE Frente', status: 'APPROVED', uploaded_at: new Date().toISOString() },
      { id: '2', type: 'INE_BACK', name: 'INE Reverso', status: 'APPROVED', uploaded_at: new Date().toISOString() },
      { id: '3', type: 'PROOF_OF_ADDRESS', name: 'Comprobante de Domicilio', status: 'PENDING', uploaded_at: new Date().toISOString() },
      { id: '4', type: 'PROOF_OF_INCOME', name: 'Comprobante de Ingresos', status: 'PENDING', uploaded_at: new Date().toISOString() },
      { id: '5', type: 'BANK_STATEMENT', name: 'Estado de Cuenta', status: 'REJECTED', uploaded_at: new Date().toISOString() }
    ],
    references: [
      { id: '1', full_name: 'MARÍA PÉREZ GARCÍA', relationship: 'HERMANO', phone: '5511223344', verified: true },
      { id: '2', full_name: 'ROBERTO LÓPEZ', relationship: 'AMIGO', phone: '5555667788', verified: false }
    ],
    notes: [
      { id: '1', text: 'Cliente contactado por teléfono, confirmó datos.', author: 'Agente 1', created_at: new Date(Date.now() - 2 * 3600000).toISOString() },
      { id: '2', text: 'Estado de cuenta rechazado por antigüedad > 3 meses. Solicitar nuevo.', author: 'Agente 1', created_at: new Date(Date.now() - 1 * 3600000).toISOString() }
    ],
    timeline: [
      { id: '1', action: 'CREATED', description: 'Solicitud creada', author: 'Sistema', created_at: new Date(Date.now() - 5 * 3600000).toISOString() },
      { id: '2', action: 'DOCS_UPLOADED', description: 'Documentos cargados', author: 'Solicitante', created_at: new Date(Date.now() - 4 * 3600000).toISOString() },
      { id: '3', action: 'STATUS_CHANGE', description: 'Estado cambiado a En Revisión', author: 'Agente 1', created_at: new Date(Date.now() - 3 * 3600000).toISOString() },
      { id: '4', action: 'NOTE_ADDED', description: 'Nota agregada', author: 'Agente 1', created_at: new Date(Date.now() - 2 * 3600000).toISOString() },
      { id: '5', action: 'DOC_REJECTED', description: 'Documento rechazado: Estado de Cuenta', author: 'Agente 1', created_at: new Date(Date.now() - 1 * 3600000).toISOString() }
    ]
  }

  loading.value = false
})

// Formatters
const formatMoney = (amount: number) => {
  return new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
    minimumFractionDigits: 0
  }).format(amount)
}

const formatDate = (dateStr: string) => {
  return new Date(dateStr).toLocaleDateString('es-MX', {
    year: 'numeric',
    month: 'long',
    day: 'numeric'
  })
}

const formatDateTime = (dateStr: string) => {
  return new Date(dateStr).toLocaleDateString('es-MX', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}

const getStatusBadge = (status: string) => {
  const badges: Record<string, { bg: string; text: string; label: string }> = {
    SUBMITTED: { bg: 'bg-blue-100', text: 'text-blue-800', label: 'Nueva' },
    IN_REVIEW: { bg: 'bg-yellow-100', text: 'text-yellow-800', label: 'En Revisión' },
    DOCS_PENDING: { bg: 'bg-orange-100', text: 'text-orange-800', label: 'Docs Pendientes' },
    COUNTER_OFFERED: { bg: 'bg-indigo-100', text: 'text-indigo-800', label: 'Contraoferta' },
    APPROVED: { bg: 'bg-green-100', text: 'text-green-800', label: 'Aprobada' },
    REJECTED: { bg: 'bg-red-100', text: 'text-red-800', label: 'Rechazada' },
    CANCELLED: { bg: 'bg-gray-100', text: 'text-gray-800', label: 'Cancelada' },
    DISBURSED: { bg: 'bg-purple-100', text: 'text-purple-800', label: 'Desembolsada' }
  }
  return badges[status] || { bg: 'bg-gray-100', text: 'text-gray-800', label: status }
}

const getDocStatusBadge = (status: string) => {
  const badges: Record<string, { bg: string; text: string; label: string }> = {
    PENDING: { bg: 'bg-yellow-100', text: 'text-yellow-800', label: 'Pendiente' },
    APPROVED: { bg: 'bg-green-100', text: 'text-green-800', label: 'Aprobado' },
    REJECTED: { bg: 'bg-red-100', text: 'text-red-800', label: 'Rechazado' }
  }
  return badges[status] || { bg: 'bg-gray-100', text: 'text-gray-800', label: status }
}

const getEmploymentType = (type: string) => {
  const types: Record<string, string> = {
    EMPLEADO: 'Empleado',
    INDEPENDIENTE: 'Independiente',
    NEGOCIO_PROPIO: 'Negocio Propio',
    PENSIONADO: 'Pensionado',
    OTRO: 'Otro'
  }
  return types[type] || type
}

const getHousingType = (type: string) => {
  const types: Record<string, string> = {
    PROPIA: 'Propia',
    RENTADA: 'Rentada',
    FAMILIAR: 'Familiar',
    HIPOTECADA: 'Hipotecada'
  }
  return types[type] || type
}

const getPurpose = (purpose: string) => {
  const purposes: Record<string, string> = {
    CONSOLIDACION_DEUDA: 'Consolidación de deuda',
    GASTOS_MEDICOS: 'Gastos médicos',
    MEJORAS_HOGAR: 'Mejoras del hogar',
    EDUCACION: 'Educación',
    VEHICULO: 'Vehículo',
    NEGOCIO: 'Negocio',
    VIAJE: 'Viaje',
    EMERGENCIA: 'Emergencia',
    OTRO: 'Otro'
  }
  return purposes[purpose] || purpose
}

// Completeness calculation
const completenessItems = computed(() => {
  if (!application.value) return []

  const c = application.value.completeness
  return [
    { label: 'Datos personales', complete: c.personal_data, icon: 'user' },
    { label: 'Domicilio', complete: c.address, icon: 'home' },
    { label: 'Empleo', complete: c.employment, icon: 'briefcase' },
    {
      label: `Documentos (${c.documents.approved}/${c.documents.required} aprobados)`,
      complete: c.documents.approved >= c.documents.required,
      partial: c.documents.uploaded >= c.documents.required && c.documents.approved < c.documents.required,
      icon: 'document'
    },
    {
      label: `Referencias (${c.references.verified}/${c.references.count} verificadas)`,
      complete: c.references.verified >= 2,
      partial: c.references.count >= 2 && c.references.verified < 2,
      icon: 'users'
    },
    { label: 'Firma digital', complete: c.signature, icon: 'pencil' }
  ]
})

const completenessPercent = computed(() => {
  if (!application.value) return 0

  const c = application.value.completeness
  let completed = 0

  if (c.personal_data) completed++
  if (c.address) completed++
  if (c.employment) completed++
  if (c.documents.approved >= c.documents.required) completed++
  if (c.references.verified >= 2) completed++
  if (c.signature) completed++

  return Math.round((completed / 6) * 100)
})

const completenessColor = computed(() => {
  const p = completenessPercent.value
  if (p >= 100) return { bg: 'bg-green-500', text: 'text-green-600', light: 'bg-green-100' }
  if (p >= 75) return { bg: 'bg-blue-500', text: 'text-blue-600', light: 'bg-blue-100' }
  if (p >= 50) return { bg: 'bg-yellow-500', text: 'text-yellow-600', light: 'bg-yellow-100' }
  return { bg: 'bg-red-500', text: 'text-red-600', light: 'bg-red-100' }
})

const goBack = () => {
  router.push('/admin/solicitudes')
}

const openStatusModal = () => {
  if (application.value) {
    newStatus.value = application.value.status
    statusNote.value = ''
    showStatusModal.value = true
  }
}

const updateStatus = async () => {
  if (!application.value || !newStatus.value) return

  isUpdatingStatus.value = true

  // Simulate API call
  await new Promise(resolve => setTimeout(resolve, 1000))

  application.value.status = newStatus.value
  application.value.timeline.push({
    id: String(Date.now()),
    action: 'STATUS_CHANGE',
    description: `Estado cambiado a ${getStatusBadge(newStatus.value).label}${statusNote.value ? ': ' + statusNote.value : ''}`,
    author: 'Admin',
    created_at: new Date().toISOString()
  })

  if (statusNote.value) {
    application.value.notes.push({
      id: String(Date.now()),
      text: statusNote.value,
      author: 'Admin',
      created_at: new Date().toISOString()
    })
  }

  isUpdatingStatus.value = false
  showStatusModal.value = false
}

const openDocApproveModal = (doc: Document) => {
  docToApprove.value = doc
  showDocApproveModal.value = true
}

const confirmApproveDocument = async () => {
  if (!docToApprove.value) return

  isApprovingDoc.value = true
  await new Promise(resolve => setTimeout(resolve, 500))

  docToApprove.value.status = 'APPROVED'

  if (application.value) {
    application.value.timeline.push({
      id: String(Date.now()),
      action: 'DOC_APPROVED',
      description: `Documento aprobado: ${docToApprove.value.name}`,
      author: 'Admin',
      created_at: new Date().toISOString()
    })
  }

  isApprovingDoc.value = false
  showDocApproveModal.value = false
}

const openDocRejectModal = (doc: Document) => {
  selectedDocument.value = doc
  docRejectReason.value = ''
  docRejectComment.value = ''
  showDocRejectModal.value = true
}

const confirmRejectDocument = async () => {
  if (!selectedDocument.value || !docRejectReason.value) return

  isRejectingDoc.value = true
  await new Promise(resolve => setTimeout(resolve, 500))

  selectedDocument.value.status = 'REJECTED'

  const reasonLabel = docRejectReasons.find(r => r.value === docRejectReason.value)?.label || docRejectReason.value

  if (application.value) {
    application.value.timeline.push({
      id: String(Date.now()),
      action: 'DOC_REJECTED',
      description: `Documento rechazado: ${selectedDocument.value.name} - ${reasonLabel}`,
      author: 'Admin',
      created_at: new Date().toISOString()
    })

    if (docRejectComment.value) {
      application.value.notes.push({
        id: String(Date.now()),
        text: `Rechazo de ${selectedDocument.value.name}: ${docRejectComment.value}`,
        author: 'Admin',
        created_at: new Date().toISOString()
      })
    }
  }

  isRejectingDoc.value = false
  showDocRejectModal.value = false
}

// Reference verification
const openVerifyRefModal = (ref: Reference) => {
  selectedReference.value = ref
  refVerifyResult.value = 'VERIFIED'
  refVerifyNotes.value = ''
  showVerifyRefModal.value = true
}

const confirmVerifyReference = async () => {
  if (!selectedReference.value) return

  isVerifyingRef.value = true
  await new Promise(resolve => setTimeout(resolve, 500))

  selectedReference.value.verified = refVerifyResult.value === 'VERIFIED'

  const resultLabels = {
    'VERIFIED': 'Verificada correctamente',
    'NOT_VERIFIED': 'No verificada - datos incorrectos',
    'NO_ANSWER': 'Sin respuesta'
  }

  if (application.value) {
    application.value.timeline.push({
      id: String(Date.now()),
      action: 'REF_VERIFIED',
      description: `Referencia ${selectedReference.value.full_name}: ${resultLabels[refVerifyResult.value]}`,
      author: 'Admin',
      created_at: new Date().toISOString()
    })

    if (refVerifyNotes.value) {
      application.value.notes.push({
        id: String(Date.now()),
        text: `Verificación de referencia ${selectedReference.value.full_name}: ${refVerifyNotes.value}`,
        author: 'Admin',
        created_at: new Date().toISOString()
      })
    }
  }

  isVerifyingRef.value = false
  showVerifyRefModal.value = false
}

const openCounterOfferModal = () => {
  if (application.value) {
    // Pre-fill with current loan values
    counterOffer.value = {
      amount: application.value.loan.requested_amount,
      term_months: application.value.loan.term_months,
      interest_rate: application.value.loan.interest_rate,
      payment_frequency: application.value.loan.payment_frequency,
      reason: ''
    }
    showCounterOfferModal.value = true
  }
}

const submitCounterOffer = async () => {
  if (!application.value) return

  isSubmittingCounterOffer.value = true

  // Simulate API call
  await new Promise(resolve => setTimeout(resolve, 1000))

  // Update application with counter-offer
  application.value.status = 'COUNTER_OFFERED'
  application.value.loan.approved_amount = counterOffer.value.amount

  // Add to timeline
  application.value.timeline.push({
    id: String(Date.now()),
    action: 'COUNTER_OFFER',
    description: `Contraoferta enviada: ${formatMoney(counterOffer.value.amount)} a ${counterOffer.value.term_months} meses`,
    author: 'Admin',
    created_at: new Date().toISOString()
  })

  // Add note if reason provided
  if (counterOffer.value.reason) {
    application.value.notes.push({
      id: String(Date.now()),
      text: `Contraoferta: ${counterOffer.value.reason}`,
      author: 'Admin',
      created_at: new Date().toISOString()
    })
  }

  isSubmittingCounterOffer.value = false
  showCounterOfferModal.value = false
}

const addNote = async () => {
  if (!application.value || !newNoteText.value.trim()) return

  isAddingNote.value = true

  // Simulate API call
  await new Promise(resolve => setTimeout(resolve, 500))

  // Add note to the beginning of the list
  application.value.notes.unshift({
    id: String(Date.now()),
    text: newNoteText.value.trim(),
    author: 'Admin',
    created_at: new Date().toISOString()
  })

  // Add to timeline
  application.value.timeline.push({
    id: String(Date.now()),
    action: 'NOTE_ADDED',
    description: 'Nota agregada',
    author: 'Admin',
    created_at: new Date().toISOString()
  })

  newNoteText.value = ''
  isAddingNote.value = false
}
</script>

<template>
  <div>
    <!-- Loading State -->
    <div v-if="loading" class="flex items-center justify-center py-12">
      <div class="animate-spin w-8 h-8 border-4 border-primary-600 border-t-transparent rounded-full" />
    </div>

    <template v-else-if="application">
      <!-- Header -->
      <div class="flex items-start justify-between mb-6">
        <div>
          <button
            class="flex items-center gap-2 text-gray-600 hover:text-gray-900 mb-2"
            @click="goBack"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            Volver a solicitudes
          </button>
          <div class="flex items-center gap-4">
            <h1 class="text-2xl font-bold text-gray-900">{{ application.folio }}</h1>
            <span
              :class="[
                'px-3 py-1 text-sm font-medium rounded-full',
                getStatusBadge(application.status).bg,
                getStatusBadge(application.status).text
              ]"
            >
              {{ getStatusBadge(application.status).label }}
            </span>
          </div>
          <p class="text-gray-500 mt-1">
            Creada {{ formatDateTime(application.created_at) }}
            <span v-if="application.assigned_to" class="ml-2">
              · Asignada a {{ application.assigned_to }}
            </span>
          </p>
        </div>

        <div class="flex gap-3">
          <AppButton
            v-if="['IN_REVIEW', 'DOCS_PENDING'].includes(application.status)"
            variant="outline"
            class="!border-indigo-500 !text-indigo-600 hover:!bg-indigo-50"
            @click="openCounterOfferModal"
          >
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
            </svg>
            Contraoferta
          </AppButton>
          <AppButton variant="outline" @click="openStatusModal">
            Cambiar Estado
          </AppButton>
          <AppButton v-if="application.status === 'APPROVED'" variant="primary">
            Generar Contrato
          </AppButton>
        </div>
      </div>

      <!-- Completeness Card -->
      <div class="bg-white rounded-xl shadow-sm p-5 mb-6">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Avance del Expediente</h3>
          <div class="flex items-center gap-2">
            <span :class="['text-2xl font-bold', completenessColor.text]">{{ completenessPercent }}%</span>
            <span
              v-if="completenessPercent >= 100"
              class="px-2 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-full"
            >
              Completo
            </span>
          </div>
        </div>

        <!-- Progress Bar -->
        <div class="w-full h-3 bg-gray-200 rounded-full overflow-hidden mb-4">
          <div
            :class="['h-full rounded-full transition-all duration-500', completenessColor.bg]"
            :style="{ width: completenessPercent + '%' }"
          />
        </div>

        <!-- Checklist -->
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
          <div
            v-for="item in completenessItems"
            :key="item.label"
            :class="[
              'flex items-center gap-2 p-2 rounded-lg text-sm',
              item.complete ? 'bg-green-50' : item.partial ? 'bg-yellow-50' : 'bg-gray-50'
            ]"
          >
            <div
              :class="[
                'w-5 h-5 rounded-full flex items-center justify-center flex-shrink-0',
                item.complete ? 'bg-green-500' : item.partial ? 'bg-yellow-500' : 'bg-gray-300'
              ]"
            >
              <svg v-if="item.complete" class="w-3 h-3 text-white" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
              </svg>
              <svg v-else-if="item.partial" class="w-3 h-3 text-white" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd" />
              </svg>
            </div>
            <span :class="item.complete ? 'text-green-800' : item.partial ? 'text-yellow-800' : 'text-gray-500'">
              {{ item.label }}
            </span>
          </div>
        </div>
      </div>

      <!-- Tabs -->
      <div class="bg-white rounded-xl shadow-sm mb-6">
        <div class="border-b border-gray-200">
          <nav class="flex -mb-px">
            <button
              v-for="tab in tabs"
              :key="tab.id"
              :class="[
                'px-6 py-4 text-sm font-medium border-b-2 transition-colors',
                activeTab === tab.id
                  ? 'border-primary-500 text-primary-600'
                  : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
              ]"
              @click="activeTab = tab.id"
            >
              {{ tab.label }}
            </button>
          </nav>
        </div>

        <!-- Tab Content -->
        <div class="p-6">
          <!-- General Tab -->
          <div v-if="activeTab === 'general'" class="space-y-8">
            <!-- Applicant Info -->
            <div>
              <h3 class="text-lg font-semibold text-gray-900 mb-4">Datos del Solicitante</h3>
              <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div>
                  <p class="text-sm text-gray-500">Nombre Completo</p>
                  <p class="font-medium">{{ application.applicant.full_name }}</p>
                </div>
                <div>
                  <p class="text-sm text-gray-500">Email</p>
                  <p class="font-medium">{{ application.applicant.email }}</p>
                </div>
                <div>
                  <p class="text-sm text-gray-500">Teléfono</p>
                  <p class="font-medium">{{ application.applicant.phone }}</p>
                </div>
                <div>
                  <p class="text-sm text-gray-500">CURP</p>
                  <p class="font-medium font-mono">{{ application.applicant.curp }}</p>
                </div>
                <div>
                  <p class="text-sm text-gray-500">RFC</p>
                  <p class="font-medium font-mono">{{ application.applicant.rfc }}</p>
                </div>
                <div>
                  <p class="text-sm text-gray-500">Fecha de Nacimiento</p>
                  <p class="font-medium">{{ formatDate(application.applicant.birth_date) }}</p>
                </div>
              </div>
            </div>

            <!-- Address -->
            <div>
              <h3 class="text-lg font-semibold text-gray-900 mb-4">Domicilio</h3>
              <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div class="lg:col-span-2">
                  <p class="text-sm text-gray-500">Dirección</p>
                  <p class="font-medium">
                    {{ application.address.street }} {{ application.address.ext_number }}
                    <span v-if="application.address.int_number">, Int. {{ application.address.int_number }}</span>
                  </p>
                </div>
                <div>
                  <p class="text-sm text-gray-500">Colonia</p>
                  <p class="font-medium">{{ application.address.neighborhood }}</p>
                </div>
                <div>
                  <p class="text-sm text-gray-500">Código Postal</p>
                  <p class="font-medium">{{ application.address.postal_code }}</p>
                </div>
                <div>
                  <p class="text-sm text-gray-500">Municipio / Estado</p>
                  <p class="font-medium">{{ application.address.municipality }}, {{ application.address.state }}</p>
                </div>
                <div>
                  <p class="text-sm text-gray-500">Tipo de Vivienda</p>
                  <p class="font-medium">{{ getHousingType(application.address.housing_type) }}</p>
                </div>
                <div>
                  <p class="text-sm text-gray-500">Tiempo en Domicilio</p>
                  <p class="font-medium">{{ application.address.years_living }} años, {{ application.address.months_living }} meses</p>
                </div>
              </div>
            </div>

            <!-- Employment -->
            <div>
              <h3 class="text-lg font-semibold text-gray-900 mb-4">Información Laboral</h3>
              <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div>
                  <p class="text-sm text-gray-500">Tipo de Empleo</p>
                  <p class="font-medium">{{ getEmploymentType(application.employment.type) }}</p>
                </div>
                <div v-if="application.employment.company_name">
                  <p class="text-sm text-gray-500">Empresa</p>
                  <p class="font-medium">{{ application.employment.company_name }}</p>
                </div>
                <div v-if="application.employment.position">
                  <p class="text-sm text-gray-500">Puesto</p>
                  <p class="font-medium">{{ application.employment.position }}</p>
                </div>
                <div>
                  <p class="text-sm text-gray-500">Ingreso Mensual</p>
                  <p class="font-medium text-green-600">{{ formatMoney(application.employment.monthly_income) }}</p>
                </div>
                <div>
                  <p class="text-sm text-gray-500">Antigüedad</p>
                  <p class="font-medium">{{ Math.floor(application.employment.seniority_months / 12) }} años, {{ application.employment.seniority_months % 12 }} meses</p>
                </div>
              </div>
            </div>

            <!-- Loan Details -->
            <div>
              <h3 class="text-lg font-semibold text-gray-900 mb-4">Detalles del Crédito</h3>
              <div class="bg-gray-50 rounded-xl p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                  <div>
                    <p class="text-sm text-gray-500">Producto</p>
                    <p class="font-medium">{{ application.loan.product_name }}</p>
                  </div>
                  <div>
                    <p class="text-sm text-gray-500">Monto Solicitado</p>
                    <p class="text-xl font-bold text-primary-600">{{ formatMoney(application.loan.requested_amount) }}</p>
                  </div>
                  <div>
                    <p class="text-sm text-gray-500">Plazo</p>
                    <p class="font-medium">{{ application.loan.term_months }} meses</p>
                  </div>
                  <div>
                    <p class="text-sm text-gray-500">Frecuencia de Pago</p>
                    <p class="font-medium">{{ application.loan.payment_frequency === 'QUINCENAL' ? 'Quincenal' : 'Mensual' }}</p>
                  </div>
                  <div>
                    <p class="text-sm text-gray-500">Tasa de Interés</p>
                    <p class="font-medium">{{ application.loan.interest_rate }}% anual</p>
                  </div>
                  <div>
                    <p class="text-sm text-gray-500">Pago por Periodo</p>
                    <p class="font-medium">{{ formatMoney(application.loan.monthly_payment) }}</p>
                  </div>
                  <div>
                    <p class="text-sm text-gray-500">Total a Pagar</p>
                    <p class="font-medium">{{ formatMoney(application.loan.total_to_pay) }}</p>
                  </div>
                  <div>
                    <p class="text-sm text-gray-500">Destino del Crédito</p>
                    <p class="font-medium">{{ getPurpose(application.loan.purpose) }}</p>
                  </div>
                </div>
              </div>
            </div>

            <!-- Notes -->
            <div>
              <h3 class="text-lg font-semibold text-gray-900 mb-4">Notas</h3>

              <!-- Add Note Form -->
              <div class="mb-4">
                <div class="flex gap-3">
                  <textarea
                    v-model="newNoteText"
                    rows="2"
                    class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent resize-none"
                    placeholder="Agregar una nota..."
                    @keydown.ctrl.enter="addNote"
                  />
                  <AppButton
                    variant="primary"
                    size="sm"
                    :loading="isAddingNote"
                    :disabled="!newNoteText.trim()"
                    class="self-end"
                    @click="addNote"
                  >
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Agregar
                  </AppButton>
                </div>
                <p class="text-xs text-gray-400 mt-1">Ctrl+Enter para enviar</p>
              </div>

              <!-- Notes List -->
              <div v-if="application.notes.length > 0" class="space-y-3">
                <div
                  v-for="note in application.notes"
                  :key="note.id"
                  class="bg-yellow-50 rounded-lg p-4"
                >
                  <p class="text-gray-800">{{ note.text }}</p>
                  <p class="text-sm text-gray-500 mt-2">
                    {{ note.author }} · {{ formatDateTime(note.created_at) }}
                  </p>
                </div>
              </div>
              <div v-else class="text-gray-500 text-sm italic">
                No hay notas todavía
              </div>
            </div>
          </div>

          <!-- Documents Tab -->
          <div v-if="activeTab === 'documents'">
            <div class="space-y-4">
              <div
                v-for="doc in application.documents"
                :key="doc.id"
                class="flex items-center justify-between p-4 border rounded-lg"
              >
                <div class="flex items-center gap-4">
                  <div class="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center">
                    <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                  </div>
                  <div>
                    <p class="font-medium text-gray-900">{{ doc.name }}</p>
                    <p class="text-sm text-gray-500">
                      Subido {{ doc.uploaded_at ? formatDateTime(doc.uploaded_at) : 'N/A' }}
                    </p>
                  </div>
                </div>

                <div class="flex items-center gap-3">
                  <span
                    :class="[
                      'px-2 py-1 text-xs font-medium rounded-full',
                      getDocStatusBadge(doc.status).bg,
                      getDocStatusBadge(doc.status).text
                    ]"
                  >
                    {{ getDocStatusBadge(doc.status).label }}
                  </span>

                  <div class="flex gap-2">
                    <button
                      class="p-2 text-gray-400 hover:text-gray-600 transition-colors"
                      title="Ver documento"
                    >
                      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                      </svg>
                    </button>
                    <button
                      v-if="doc.status === 'PENDING'"
                      class="p-2 text-green-500 hover:text-green-700 transition-colors"
                      title="Aprobar"
                      @click="openDocApproveModal(doc)"
                    >
                      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                      </svg>
                    </button>
                    <button
                      v-if="doc.status === 'PENDING'"
                      class="p-2 text-red-500 hover:text-red-700 transition-colors"
                      title="Rechazar"
                      @click="openDocRejectModal(doc)"
                    >
                      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                      </svg>
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- References Tab -->
          <div v-if="activeTab === 'references'">
            <div class="space-y-4">
              <div
                v-for="ref in application.references"
                :key="ref.id"
                class="flex items-center justify-between p-4 border rounded-lg"
              >
                <div class="flex items-center gap-4">
                  <div class="w-10 h-10 bg-gray-100 rounded-full flex items-center justify-center">
                    <span class="font-medium text-gray-600">{{ ref.full_name.charAt(0) }}</span>
                  </div>
                  <div>
                    <p class="font-medium text-gray-900">{{ ref.full_name }}</p>
                    <p class="text-sm text-gray-500">{{ ref.relationship }} · {{ ref.phone }}</p>
                  </div>
                </div>
                <div class="flex items-center gap-3">
                  <span
                    :class="[
                      'px-2 py-1 text-xs font-medium rounded-full',
                      ref.verified ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'
                    ]"
                  >
                    {{ ref.verified ? 'Verificada' : 'Pendiente' }}
                  </span>
                  <!-- Call reference -->
                  <a
                    :href="'tel:' + ref.phone"
                    class="p-2 text-primary-600 hover:text-primary-800 transition-colors"
                    title="Llamar"
                  >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                    </svg>
                  </a>
                  <!-- Verify reference -->
                  <button
                    v-if="!ref.verified"
                    class="p-2 text-green-600 hover:text-green-800 transition-colors"
                    title="Registrar verificación"
                    @click="openVerifyRefModal(ref)"
                  >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- Timeline Tab -->
          <div v-if="activeTab === 'timeline'">
            <div class="flow-root">
              <ul class="-mb-8">
                <li v-for="(event, index) in application.timeline" :key="event.id">
                  <div class="relative pb-8">
                    <span
                      v-if="index !== application.timeline.length - 1"
                      class="absolute top-4 left-4 -ml-px h-full w-0.5 bg-gray-200"
                    />
                    <div class="relative flex space-x-3">
                      <div>
                        <span class="h-8 w-8 rounded-full bg-primary-100 flex items-center justify-center ring-8 ring-white">
                          <svg class="h-4 w-4 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                          </svg>
                        </span>
                      </div>
                      <div class="min-w-0 flex-1 pt-1.5 flex justify-between space-x-4">
                        <div>
                          <p class="text-sm text-gray-800">
                            {{ event.description }}
                          </p>
                          <p class="text-xs text-gray-500 mt-1">
                            Por {{ event.author }}
                          </p>
                        </div>
                        <div class="text-right text-sm whitespace-nowrap text-gray-500">
                          {{ formatDateTime(event.created_at) }}
                        </div>
                      </div>
                    </div>
                  </div>
                </li>
              </ul>
            </div>
          </div>
        </div>
      </div>
    </template>

    <!-- Status Change Modal -->
    <div
      v-if="showStatusModal"
      class="fixed inset-0 bg-black/50 flex items-center justify-center z-50"
      @click.self="showStatusModal = false"
    >
      <div class="bg-white rounded-xl p-6 w-full max-w-md mx-4">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Cambiar Estado</h3>

        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Nuevo Estado</label>
            <select
              v-model="newStatus"
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
            >
              <option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">
                {{ opt.label }}
              </option>
            </select>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Nota (opcional)</label>
            <textarea
              v-model="statusNote"
              rows="3"
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
              placeholder="Agregar una nota sobre el cambio de estado..."
            />
          </div>
        </div>

        <div class="flex gap-3 mt-6">
          <AppButton
            variant="outline"
            class="flex-1"
            @click="showStatusModal = false"
          >
            Cancelar
          </AppButton>
          <AppButton
            variant="primary"
            class="flex-1"
            :loading="isUpdatingStatus"
            @click="updateStatus"
          >
            Guardar
          </AppButton>
        </div>
      </div>
    </div>

    <!-- Counter-Offer Modal -->
    <div
      v-if="showCounterOfferModal && application"
      class="fixed inset-0 bg-black/50 flex items-center justify-center z-50"
      @click.self="showCounterOfferModal = false"
    >
      <div class="bg-white rounded-xl w-full max-w-2xl mx-4 max-h-[90vh] overflow-y-auto">
        <!-- Header -->
        <div class="p-6 border-b border-gray-200 bg-indigo-50 rounded-t-xl">
          <h3 class="text-lg font-semibold text-indigo-900">Crear Contraoferta</h3>
          <p class="text-sm text-indigo-700 mt-1">
            Modifica las condiciones del crédito para hacer una contraoferta al solicitante
          </p>
        </div>

        <div class="p-6 space-y-6">
          <!-- Original Request Summary -->
          <div class="bg-gray-50 rounded-lg p-4">
            <p class="text-sm font-medium text-gray-500 mb-2">Solicitud Original</p>
            <div class="flex flex-wrap gap-4 text-sm">
              <span><strong>Monto:</strong> {{ formatMoney(application.loan.requested_amount) }}</span>
              <span><strong>Plazo:</strong> {{ application.loan.term_months }} meses</span>
              <span><strong>Tasa:</strong> {{ application.loan.interest_rate }}%</span>
            </div>
          </div>

          <!-- Counter-offer Form -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">
                Monto Aprobado
              </label>
              <div class="relative">
                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500">$</span>
                <input
                  v-model.number="counterOffer.amount"
                  type="number"
                  min="1000"
                  step="1000"
                  class="w-full pl-8 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                />
              </div>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">
                Plazo (meses)
              </label>
              <select
                v-model.number="counterOffer.term_months"
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
              >
                <option :value="6">6 meses</option>
                <option :value="12">12 meses</option>
                <option :value="18">18 meses</option>
                <option :value="24">24 meses</option>
                <option :value="36">36 meses</option>
                <option :value="48">48 meses</option>
              </select>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">
                Tasa Anual (%)
              </label>
              <input
                v-model.number="counterOffer.interest_rate"
                type="number"
                min="0"
                max="100"
                step="0.5"
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
              />
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">
                Frecuencia de Pago
              </label>
              <select
                v-model="counterOffer.payment_frequency"
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
              >
                <option value="QUINCENAL">Quincenal</option>
                <option value="MENSUAL">Mensual</option>
              </select>
            </div>
          </div>

          <!-- Calculation Preview -->
          <div class="bg-indigo-50 rounded-lg p-4">
            <p class="text-sm font-medium text-indigo-900 mb-3">Resumen de Contraoferta</p>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-center">
              <div>
                <p class="text-xs text-indigo-600">Monto</p>
                <p class="text-lg font-bold text-indigo-900">{{ formatMoney(counterOffer.amount) }}</p>
              </div>
              <div>
                <p class="text-xs text-indigo-600">Pago {{ counterOffer.payment_frequency === 'QUINCENAL' ? 'Quincenal' : 'Mensual' }}</p>
                <p class="text-lg font-bold text-indigo-900">{{ formatMoney(counterOfferCalculation.payment) }}</p>
              </div>
              <div>
                <p class="text-xs text-indigo-600">Total Pagos</p>
                <p class="text-lg font-bold text-indigo-900">{{ counterOfferCalculation.totalPeriods }}</p>
              </div>
              <div>
                <p class="text-xs text-indigo-600">Total a Pagar</p>
                <p class="text-lg font-bold text-indigo-900">{{ formatMoney(counterOfferCalculation.totalToPay) }}</p>
              </div>
            </div>
          </div>

          <!-- Reason -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
              Razón de la contraoferta (opcional)
            </label>
            <textarea
              v-model="counterOffer.reason"
              rows="3"
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
              placeholder="Ej: Capacidad de pago limitada según ingresos reportados..."
            />
          </div>
        </div>

        <!-- Footer -->
        <div class="p-6 border-t border-gray-200 flex gap-3">
          <AppButton
            variant="outline"
            class="flex-1"
            @click="showCounterOfferModal = false"
          >
            Cancelar
          </AppButton>
          <AppButton
            variant="primary"
            class="flex-1 !bg-indigo-600 hover:!bg-indigo-700"
            :loading="isSubmittingCounterOffer"
            @click="submitCounterOffer"
          >
            Enviar Contraoferta
          </AppButton>
        </div>
      </div>
    </div>

    <!-- Document Reject Modal -->
    <div
      v-if="showDocRejectModal && selectedDocument"
      class="fixed inset-0 bg-black/50 flex items-center justify-center z-50"
      @click.self="showDocRejectModal = false"
    >
      <div class="bg-white rounded-xl p-6 w-full max-w-md mx-4">
        <h3 class="text-lg font-semibold text-gray-900 mb-2">Rechazar Documento</h3>
        <p class="text-sm text-gray-500 mb-4">{{ selectedDocument.name }}</p>

        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
              Motivo de rechazo <span class="text-red-500">*</span>
            </label>
            <select
              v-model="docRejectReason"
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent"
            >
              <option value="">Seleccionar motivo...</option>
              <option v-for="reason in docRejectReasons" :key="reason.value" :value="reason.value">
                {{ reason.label }}
              </option>
            </select>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
              Comentario adicional
            </label>
            <textarea
              v-model="docRejectComment"
              rows="3"
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent"
              placeholder="Detalle adicional para el solicitante..."
            />
          </div>
        </div>

        <div class="flex gap-3 mt-6">
          <AppButton
            variant="outline"
            class="flex-1"
            @click="showDocRejectModal = false"
          >
            Cancelar
          </AppButton>
          <AppButton
            variant="primary"
            class="flex-1 !bg-red-600 hover:!bg-red-700"
            :loading="isRejectingDoc"
            :disabled="!docRejectReason"
            @click="confirmRejectDocument"
          >
            Rechazar
          </AppButton>
        </div>
      </div>
    </div>

    <!-- Reference Verification Modal -->
    <div
      v-if="showVerifyRefModal && selectedReference"
      class="fixed inset-0 bg-black/50 flex items-center justify-center z-50"
      @click.self="showVerifyRefModal = false"
    >
      <div class="bg-white rounded-xl p-6 w-full max-w-md mx-4">
        <h3 class="text-lg font-semibold text-gray-900 mb-2">Verificar Referencia</h3>
        <div class="bg-gray-50 rounded-lg p-3 mb-4">
          <p class="font-medium">{{ selectedReference.full_name }}</p>
          <p class="text-sm text-gray-500">{{ selectedReference.relationship }} · {{ selectedReference.phone }}</p>
        </div>

        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
              Resultado de verificación
            </label>
            <div class="space-y-2">
              <label class="flex items-center gap-3 p-3 border rounded-lg cursor-pointer hover:bg-gray-50"
                :class="{ 'border-green-500 bg-green-50': refVerifyResult === 'VERIFIED' }"
              >
                <input
                  v-model="refVerifyResult"
                  type="radio"
                  value="VERIFIED"
                  class="text-green-600 focus:ring-green-500"
                />
                <div>
                  <p class="font-medium text-gray-900">Verificada</p>
                  <p class="text-sm text-gray-500">La referencia confirmó conocer al solicitante</p>
                </div>
              </label>

              <label class="flex items-center gap-3 p-3 border rounded-lg cursor-pointer hover:bg-gray-50"
                :class="{ 'border-red-500 bg-red-50': refVerifyResult === 'NOT_VERIFIED' }"
              >
                <input
                  v-model="refVerifyResult"
                  type="radio"
                  value="NOT_VERIFIED"
                  class="text-red-600 focus:ring-red-500"
                />
                <div>
                  <p class="font-medium text-gray-900">No verificada</p>
                  <p class="text-sm text-gray-500">Datos incorrectos o no conoce al solicitante</p>
                </div>
              </label>

              <label class="flex items-center gap-3 p-3 border rounded-lg cursor-pointer hover:bg-gray-50"
                :class="{ 'border-yellow-500 bg-yellow-50': refVerifyResult === 'NO_ANSWER' }"
              >
                <input
                  v-model="refVerifyResult"
                  type="radio"
                  value="NO_ANSWER"
                  class="text-yellow-600 focus:ring-yellow-500"
                />
                <div>
                  <p class="font-medium text-gray-900">Sin respuesta</p>
                  <p class="text-sm text-gray-500">No contestaron o número fuera de servicio</p>
                </div>
              </label>
            </div>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
              Notas de la llamada
            </label>
            <textarea
              v-model="refVerifyNotes"
              rows="3"
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
              placeholder="Comentarios adicionales sobre la verificación..."
            />
          </div>
        </div>

        <div class="flex gap-3 mt-6">
          <AppButton
            variant="outline"
            class="flex-1"
            @click="showVerifyRefModal = false"
          >
            Cancelar
          </AppButton>
          <AppButton
            variant="primary"
            class="flex-1"
            :loading="isVerifyingRef"
            @click="confirmVerifyReference"
          >
            Guardar
          </AppButton>
        </div>
      </div>
    </div>

    <!-- Document Approval Confirmation Modal -->
    <AppConfirmModal
      v-model:show="showDocApproveModal"
      title="Aprobar Documento"
      :message="docToApprove ? `¿Confirmas aprobar el documento '${docToApprove.name}'?` : ''"
      confirm-text="Aprobar"
      variant="success"
      icon="success"
      :loading="isApprovingDoc"
      @confirm="confirmApproveDocument"
    />
  </div>
</template>
