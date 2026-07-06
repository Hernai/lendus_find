<script setup lang="ts">
import { ref, computed, onMounted, onBeforeMount, onUnmounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { AppButton } from '@/components/common'
import AdminDocumentGallery from '@/modules/admin/components/AdminDocumentGallery.vue'
import ConfirmModal from '@/modules/admin/components/ConfirmModal.vue'
import ActivityTimeline from '@/modules/admin/components/application-detail/ActivityTimeline.vue'
import {
  ReferencesSection,
  BankAccountsSection,
  NotesSection,
  RisksSection,
  VerifiableField,
  LoanDetailsSection,
  LoanSummaryCards,
  CompletenessCard,
  SignatureSection,
} from '@/modules/admin/components/application-detail'
import { staff } from '@/modules/admin/services'
import type { Application, Document, Reference, BankAccount } from './applicationDetail.types'
import { mapApplicationDetail, isForeignNationality, requiredDocTypesFor } from './mapApplicationDetail'
import { platform } from '@/platform'
import { useWebSocket, useToast, useDocumentTypes } from '@/composables'
import { useTenantStore } from '@/stores/tenant'
import { useAuthStore } from '@/stores/auth'
import { logger } from '@/utils/logger'
import { formatMoney, formatDate, formatDateTime, formatPhone } from '@/utils/formatters'
import { getStateNameFromCurp } from '@/utils/validators'
import { getStatusBadge } from '@/utils/admin-styles'
import type { ApplicationStatusChangedEvent, DocumentStatusChangedEvent, DocumentDeletedEvent, DocumentUploadedEvent, ReferenceVerifiedEvent, BankAccountVerifiedEvent } from '@/types/realtime'

const log = logger.child('AdminApplicationDetail')
const toast = useToast()
const { loadDocumentTypes, getDocumentTypeLabel } = useDocumentTypes()

// Load document types from backend on mount
onBeforeMount(async () => {
  await loadDocumentTypes()
})

const route = useRoute()
const router = useRouter()
const tenantStore = useTenantStore()
const authStore = useAuthStore()

// Permission checks (from backend via authStore)
const canAssign = computed(() => authStore.permissions?.canAssignApplications ?? false)
const canChangeStatus = computed(() => authStore.permissions?.canChangeApplicationStatus ?? false)
const canApproveReject = computed(() => authStore.permissions?.canApproveRejectApplications ?? false)
const canReviewDocs = computed(() => authStore.permissions?.canReviewDocuments ?? false)
const canVerifyRefs = computed(() => authStore.permissions?.canVerifyReferences ?? false)

// Comparación de la verificación de INE: datos confirmados (persona) vs OCR vs
// RENAPO, para que el analista revise diferencias.
const ineComparison = computed(() => {
  const a = application.value?.applicant
  const iv = a?.ine_verification
  if (!a || !iv) return null
  const norm = (s?: string | null) => (s ?? '').toUpperCase().trim()
  // La entidad de nacimiento va codificada en la CURP (posiciones 12-13);
  // la derivamos de cada fuente. RENAPO valida la CURP del OCR, así que su
  // entidad es la de esa CURP cuando la validación fue exitosa.
  const state = (curp?: string | null) => norm(getStateNameFromCurp(norm(curp)))
  const rows = [
    { label: 'Nombre', confirmed: norm(a.first_name), ocr: norm(iv.ocr?.nombres), renapo: norm(iv.renapo?.nombres) },
    { label: 'Apellido paterno', confirmed: norm(a.last_name_1), ocr: norm(iv.ocr?.apellido_paterno), renapo: norm(iv.renapo?.apellido_paterno) },
    { label: 'Apellido materno', confirmed: norm(a.last_name_2), ocr: norm(iv.ocr?.apellido_materno), renapo: norm(iv.renapo?.apellido_materno) },
    { label: 'CURP', confirmed: norm(a.curp), ocr: norm(iv.ocr?.curp), renapo: iv.curp_valid ? norm(a.curp) : '' },
    { label: 'Entidad de nacimiento', confirmed: state(a.curp), ocr: state(iv.ocr?.curp), renapo: iv.curp_valid ? state(iv.ocr?.curp) : '' },
  ].map(r => ({
    ...r,
    // diferencia si el confirmado no coincide con OCR o RENAPO (cuando existen)
    diff: (!!r.ocr && r.confirmed !== r.ocr) || (!!r.renapo && r.confirmed !== r.renapo),
  }))
  return {
    rows,
    ineValid: iv.ine_valid ?? null,
    curpValid: iv.curp_valid ?? null,
    verifiedAt: iv.verified_at ?? null,
    hasDiffs: rows.some(r => r.diff),
  }
})

// La verificación de INE no se muestra al inicio; el analista la despliega con
// un botón (evita saturar el resumen y deja el detalle solo cuando lo consulta).
const showIneVerification = ref(false)

// Re-verificar el INE con Nubarium usando las imágenes ya subidas. Sirve cuando
// el servicio estaba caído durante el onboarding y quedó sin verificar.
const reverifyingIne = ref(false)
const reverifyIne = async () => {
  const app = application.value
  if (!app || reverifyingIne.value) return
  reverifyingIne.value = true
  try {
    await staff.application.reverifyIne(app.id)
    toast.success('INE verificado con Nubarium')
    showIneVerification.value = true
    await fetchApplication()
  } catch (e) {
    const msg = (e as { response?: { data?: { message?: string } } })?.response?.data?.message
    toast.error(msg || 'No se pudo verificar el INE')
  } finally {
    reverifyingIne.value = false
  }
}

// Allowed statuses from backend (based on user permissions)
const allowedStatuses = ref<{ value: string; label: string }[]>([])

// Los tipos del modelo de vista (Application, Document, Reference, BankAccount,
// ApplicationCompleteness) viven en ./applicationDetail.types y se importan arriba.
// El mapeo de V2ApplicationDetail → Application vive en ./mapApplicationDetail.

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

// Document viewer state
const showDocViewerModal = ref(false)
const docViewerUrl = ref('')
const docViewerName = ref('')
const docViewerMimeType = ref('')
const isLoadingDocViewer = ref(false)

// Selfie (profile photo) state - visible throughout the form
const selfieUrl = ref<string | null>(null)
const selfieStatus = ref<'PENDING' | 'APPROVED' | 'REJECTED'>('PENDING')
const selfieDocId = ref<string | null>(null)
const selfieIsKycVerified = ref(false) // True if verified by KYC face match - cannot be unapproved
const selfieFaceMatchScore = ref<number | null>(null) // Face match score percentage
const isLoadingSelfie = ref(false)
const showSelfieViewer = ref(false)
const showSelfieApproveModal = ref(false)
const showSelfieRejectModal = ref(false)
const showSelfieUnapproveModal = ref(false)
const showSelfieUnrejectModal = ref(false)
const isApprovingSelfie = ref(false)
const isRejectingSelfie = ref(false)
const isUnapprovingSelfie = ref(false)
const isUnrejectingSelfie = ref(false)

// Add note state
const isAddingNote = ref(false)

// Bank account verification state
const showBankAccountVerifyModal = ref(false)
const showBankAccountUnverifyModal = ref(false)
const selectedBankAccount = ref<BankAccount | null>(null)
const isVerifyingBankAccount = ref(false)
const isUnverifyingBankAccount = ref(false)

// Assignment state
interface StaffUser {
  id: string
  name: string
  email: string
  role: string
}

// Get role label from backend enum options
const getRoleLabel = (role: string) => {
  const option = tenantStore.options.userType.find(o => o.value === role)
  return option?.label || role
}
const showAssignModal = ref(false)
const staffUsers = ref<StaffUser[]>([])
const selectedUserId = ref('')
const isAssigning = ref(false)
const isLoadingUsers = ref(false)

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
  { id: 'bank_accounts', label: 'Cuentas Bancarias' },
  { id: 'risks', label: 'Riesgos' },
  // Tab unica que reemplaza a Historial + Actividad + Logs API. Lee del
  // endpoint /v2/staff/applications/{id}/activity (feed unificado).
  { id: 'activity', label: 'Actividad' },
]

// Status colors for UI display
const statusColors: Record<string, string> = {
  DRAFT: 'gray',
  SUBMITTED: 'blue',
  IN_REVIEW: 'yellow',
  DOCS_PENDING: 'orange',
  CORRECTIONS_PENDING: 'orange',
  COUNTER_OFFERED: 'indigo',
  APPROVED: 'green',
  REJECTED: 'red',
  CANCELLED: 'gray',
  DISBURSED: 'purple'
}

// Build status options from backend enum with local colors
const allStatusOptions = computed(() => {
  return tenantStore.options.applicationStatus.map(opt => ({
    value: opt.value,
    label: opt.label,
    color: statusColors[opt.value] || 'gray'
  }))
})

// Status options come from backend based on user permissions
const statusOptions = computed(() => {
  // Use backend-provided allowed statuses, adding colors from local definitions
  return allowedStatuses.value.map(status => {
    const local = allStatusOptions.value.find(opt => opt.value === status.value)
    return {
      value: status.value,
      label: status.label,
      color: local?.color || 'gray'
    }
  })
})

// Error state
const error = ref('')

// Computed refs for WebSocket (to allow reactive reconnection when tenant loads)
const tenantIdRef = computed(() => tenantStore.tenant?.id)

// WebSocket connection for real-time updates
useWebSocket({
  tenantId: tenantIdRef,
  applicationId: route.params.id as string,
  onApplicationStatusChanged: (event: ApplicationStatusChangedEvent) => {
    log.debug('Status changed', { from: event.previous_status, to: event.new_status })
    fetchApplication()
  },
  onDocumentStatusChanged: (event: DocumentStatusChangedEvent) => {
    log.debug('Document updated', { type: event.type, status: event.new_status })
    fetchApplication()
  },
  onDocumentDeleted: (event: DocumentDeletedEvent) => {
    log.debug('Document deleted', { type: event.type, by: event.deleted_by?.name })
    fetchApplication()
  },
  onDocumentUploaded: (event: DocumentUploadedEvent) => {
    log.debug('Document uploaded', { type: event.type, by: event.uploaded_by?.name })
    fetchApplication()
  },
  onReferenceVerified: (event: ReferenceVerifiedEvent) => {
    log.debug('Reference verified', { name: event.full_name, result: event.result })
    fetchApplication()
  },
  onBankAccountVerified: (event: BankAccountVerifiedEvent) => {
    log.debug('Bank account verification changed', { bank: event.bank_name, verified: event.is_verified })
    fetchApplication()
  },
})

// Fetch application data from API
const fetchApplication = async () => {
  // El spinner de página completa SOLO se muestra en la carga inicial (cuando
  // aún no hay datos). Los refrescos tras acciones/eventos actualizan la vista
  // EN SITIO, sin recargar toda la pantalla (evita el parpadeo visual).
  const initialLoad = application.value === null
  if (initialLoad) {
    loading.value = true
    error.value = ''
  }

  try {
    const appId = route.params.id as string
    const response = await staff.application.get(appId)

    // V2 response structure with new format
    const data = response.data!

    // Store allowed statuses from backend (based on user permissions)
    allowedStatuses.value = (response as { allowed_statuses?: { value: string; label: string }[] }).allowed_statuses || allStatusOptions.value.map(s => ({ value: s.value, label: s.label }))

    // El mapeo de la respuesta (V2ApplicationDetail) al modelo de vista vive en
    // ./mapApplicationDetail (función pura, testeable). getDocumentTypeLabel viene
    // del composable useDocumentTypes.
    application.value = mapApplicationDetail(data, { getDocumentTypeLabel })
  } catch (e) {
    console.error('Error completo:', e)
    console.error('Error message:', e instanceof Error ? e.message : String(e))
    console.error('Error stack:', e instanceof Error ? e.stack : 'No stack')
    log.error('Error al cargar solicitud', { error: e })
    // En un refresco no blanqueamos la vista con el estado de error; mantenemos
    // los datos actuales (el error inicial sí se muestra).
    if (initialLoad) {
      error.value = 'Error al cargar la solicitud'
    }
  } finally {
    if (initialLoad) {
      loading.value = false
    }
  }
}

// Switch tab. El feed de actividad (`activity`) carga su propia data al
// montarse el componente ActivityTimeline, no necesitamos prefetching aqui.
const switchTab = (tabId: string) => {
  activeTab.value = tabId
}

// Load selfie (profile photo) for display throughout the form
const loadSelfie = async () => {
  if (!application.value) return

  const selfieDoc = application.value.documents.find(d => d.type === 'SELFIE')

  if (!selfieDoc) {
    selfieUrl.value = null
    selfieDocId.value = null
    selfieIsKycVerified.value = false
    return
  }

  selfieDocId.value = selfieDoc.id
  selfieStatus.value = selfieDoc.status

  // Check if selfie was verified by KYC face match (cannot be unapproved)
  const metadata = selfieDoc.metadata || {}

  // Also check field_verifications for selfie/face_match verification
  // The field name could be stored in different formats
  const fieldVerifications = application.value?.field_verifications || {}
  const selfieVerification = fieldVerifications['SELFIE'] || fieldVerifications['selfie'] || fieldVerifications['face_match']
  const livenessVerification = fieldVerifications['liveness']

  // Check various metadata fields that indicate KYC face match verification
  // The metadata can come from different sources with slightly different field names
  // Check for any truthy value (JSON parsing may return string or boolean)
  const hasMetadataVerification = !!(
    metadata.face_match_passed === true ||
    String(metadata.face_match_passed) === 'true' ||
    metadata.face_match === true ||
    String(metadata.face_match) === 'true' ||
    metadata.validation_method === 'KYC_FACE_MATCH' ||
    metadata.kyc_validated === true ||
    String(metadata.kyc_validated) === 'true' ||
    metadata.nubarium_validated === true ||
    String(metadata.nubarium_validated) === 'true' ||
    metadata.source === 'kyc'
  )

  // Helper function to check if method is KYC-related
  // Method can be string (from backend) like 'KYC_FACE_MATCH' or 'KYC_LIVENESS'
  const isKycMethod = (method: string | undefined | null): boolean => {
    if (!method) return false
    const methodStr = String(method).toUpperCase()
    return methodStr.includes('KYC') || methodStr.includes('NUBARIUM') || methodStr.includes('LIVENESS')
  }

  // Check if there's a field verification for selfie/face_match/liveness with KYC method
  const hasFieldVerification = !!(
    (selfieVerification?.verified === true && isKycMethod(selfieVerification.method)) ||
    (livenessVerification?.verified === true && isKycMethod(livenessVerification.method))
  )

  // IMPORTANT: Also check if the applicant has KYC face_match verification recorded
  // This covers the case where metadata wasn't saved to document but face_match was recorded in data_verifications
  // If face_match or liveness exists as a verified field, the selfie was KYC verified
  const hasFaceMatchVerification = !!(
    fieldVerifications['face_match']?.verified === true ||
    fieldVerifications['liveness']?.verified === true
  )

  selfieIsKycVerified.value = hasMetadataVerification || hasFieldVerification || hasFaceMatchVerification

  // Extract face match score from metadata or field verifications
  // Try multiple sources: document metadata, face_match verification, selfie verification
  let faceMatchScore: number | null = null

  // 1. Try document metadata first
  if (metadata.face_match_score !== undefined && metadata.face_match_score !== null) {
    faceMatchScore = Number(metadata.face_match_score)
  }

  // 2. Try face_match field verification metadata (cast to access metadata property)
  if (faceMatchScore === null) {
    const fmVerification = fieldVerifications['face_match'] as Record<string, unknown> | undefined
    if (fmVerification?.metadata) {
      const fmMeta = fmVerification.metadata as Record<string, unknown>
      if (fmMeta.score !== undefined) {
        faceMatchScore = Number(fmMeta.score)
      }
    }
  }

  // 3. Try selfie verification metadata (cast to access metadata property)
  if (faceMatchScore === null && selfieVerification) {
    const selfieVerificationAny = selfieVerification as Record<string, unknown>
    if (selfieVerificationAny.metadata) {
      const selfieMeta = selfieVerificationAny.metadata as Record<string, unknown>
      if (selfieMeta.face_match_score !== undefined) {
        faceMatchScore = Number(selfieMeta.face_match_score)
      } else if (selfieMeta.score !== undefined) {
        faceMatchScore = Number(selfieMeta.score)
      }
    }
  }

  selfieFaceMatchScore.value = faceMatchScore

  isLoadingSelfie.value = true

  try {
    const blob = await staff.application.downloadDocument(application.value.id, selfieDoc.id)
    const typedBlob = new Blob([blob], { type: selfieDoc.mime_type || 'image/jpeg' })
    // Revocar el objectURL previo antes de crear uno nuevo, por si loadSelfie se
    // reejecuta en un refresco (evita fuga del blob anterior).
    if (selfieUrl.value) URL.revokeObjectURL(selfieUrl.value)
    selfieUrl.value = URL.createObjectURL(typedBlob)
  } catch (e) {
    log.error('Error al cargar selfie', { error: e })
    selfieUrl.value = null
  } finally {
    isLoadingSelfie.value = false
  }
}

// Approve selfie
const approveSelfie = async () => {
  if (!application.value || !selfieDocId.value) return

  isApprovingSelfie.value = true

  try {
    await staff.application.approveDocument(application.value.id, selfieDocId.value)
    selfieStatus.value = 'APPROVED'

    const doc = application.value.documents.find(d => d.id === selfieDocId.value)
    if (doc) doc.status = 'APPROVED'

    showSelfieApproveModal.value = false
    await fetchApplication()
    toast.success('Selfie aprobada correctamente')
  } catch (e) {
    log.error('Error al aprobar selfie', { error: e })
    toast.error('Error al aprobar la selfie')
  } finally {
    isApprovingSelfie.value = false
  }
}

// Reject selfie
const rejectSelfie = async (data: { selectValue?: string; comment?: string }) => {
  if (!application.value || !selfieDocId.value || !data.selectValue) return

  isRejectingSelfie.value = true

  try {
    await staff.application.rejectDocument(application.value.id, selfieDocId.value, {
      reason: data.selectValue,
      comment: data.comment || undefined
    })
    selfieStatus.value = 'REJECTED'

    // Update in documents array too
    const doc = application.value.documents.find(d => d.id === selfieDocId.value)
    if (doc) {
      doc.status = 'REJECTED'
      doc.rejection_reason = data.selectValue
      doc.rejection_comment = data.comment
    }

    showSelfieRejectModal.value = false
    await fetchApplication()
    toast.success('Selfie rechazada')
  } catch (e) {
    log.error('Error al rechazar selfie', { error: e })
    toast.error('Error al rechazar la selfie')
  } finally {
    isRejectingSelfie.value = false
  }
}

// Unapprove selfie (set back to pending)
const unapproveSelfie = async () => {
  if (!application.value || !selfieDocId.value) return

  isUnapprovingSelfie.value = true

  try {
    await staff.application.unapproveDocument(application.value.id, selfieDocId.value)
    selfieStatus.value = 'PENDING'

    const doc = application.value.documents.find(d => d.id === selfieDocId.value)
    if (doc) {
      doc.status = 'PENDING'
      doc.rejection_reason = undefined
      doc.rejection_comment = undefined
    }

    showSelfieUnapproveModal.value = false
    await fetchApplication()
    toast.success('Aprobación de selfie revertida')
  } catch (e) {
    log.error('Error al revertir aprobación de selfie', { error: e })
    toast.error('Error al revertir aprobación de selfie')
  } finally {
    isUnapprovingSelfie.value = false
  }
}

// Unreject selfie (set back to pending)
const unrejectSelfie = async () => {
  if (!application.value || !selfieDocId.value) return

  isUnrejectingSelfie.value = true

  try {
    await staff.application.unapproveDocument(application.value.id, selfieDocId.value)
    selfieStatus.value = 'PENDING'

    const doc = application.value.documents.find(d => d.id === selfieDocId.value)
    if (doc) {
      doc.status = 'PENDING'
      doc.rejection_reason = undefined
      doc.rejection_comment = undefined
    }

    showSelfieUnrejectModal.value = false
    await fetchApplication()
    toast.success('Rechazo de selfie revertido')
  } catch (e) {
    log.error('Error al revertir rechazo de selfie', { error: e })
    toast.error('Error al revertir rechazo de selfie')
  } finally {
    isUnrejectingSelfie.value = false
  }
}

// Open bank account verify modal
const openBankAccountVerifyModal = (account: BankAccount) => {
  selectedBankAccount.value = account
  showBankAccountVerifyModal.value = true
}

// Open bank account unverify modal
const openBankAccountUnverifyModal = (account: BankAccount) => {
  selectedBankAccount.value = account
  showBankAccountUnverifyModal.value = true
}

// Verify bank account
const verifyBankAccount = async () => {
  if (!application.value || !selectedBankAccount.value) return

  isVerifyingBankAccount.value = true

  try {
    await staff.application.verifyBankAccount(application.value.id, selectedBankAccount.value.id)

    // Update in bank_accounts array
    const account = application.value.bank_accounts.find(ba => ba.id === selectedBankAccount.value?.id)
    if (account) {
      account.is_verified = true
    }

    showBankAccountVerifyModal.value = false
    selectedBankAccount.value = null
    await fetchApplication()
    toast.success('Cuenta bancaria verificada')
  } catch (e) {
    log.error('Error al verificar cuenta bancaria', { error: e })
    toast.error('Error al verificar la cuenta bancaria')
  } finally {
    isVerifyingBankAccount.value = false
  }
}

// Unverify bank account
const unverifyBankAccount = async () => {
  if (!application.value || !selectedBankAccount.value) return

  isUnverifyingBankAccount.value = true

  try {
    await staff.application.unverifyBankAccount(application.value.id, selectedBankAccount.value.id)

    const account = application.value.bank_accounts.find(ba => ba.id === selectedBankAccount.value?.id)
    if (account) {
      account.is_verified = false
    }

    showBankAccountUnverifyModal.value = false
    selectedBankAccount.value = null
    await fetchApplication()
    toast.success('Verificación de cuenta bancaria revertida')
  } catch (e) {
    log.error('Error al revertir verificación de cuenta bancaria', { error: e })
    toast.error('Error al revertir verificación de cuenta bancaria')
  } finally {
    isUnverifyingBankAccount.value = false
  }
}

// Get document type display name (from backend enum)
const getDocTypeName = (type: string): string => {
  return getDocumentTypeLabel(type)
}

onMounted(async () => {
  await fetchApplication()
  await loadSelfie()
})

// Parse user agent to friendly name
const parseUserAgent = (ua: string): string => {
  if (!ua) return 'Desconocido'

  // Detect browser
  let browser = 'Navegador desconocido'
  if (ua.includes('Chrome') && !ua.includes('Edg')) browser = 'Chrome'
  else if (ua.includes('Safari') && !ua.includes('Chrome')) browser = 'Safari'
  else if (ua.includes('Firefox')) browser = 'Firefox'
  else if (ua.includes('Edg')) browser = 'Edge'
  else if (ua.includes('Opera') || ua.includes('OPR')) browser = 'Opera'

  // Detect OS
  let os = ''
  if (ua.includes('Windows')) os = 'Windows'
  else if (ua.includes('Mac OS')) os = 'macOS'
  else if (ua.includes('iPhone')) os = 'iPhone'
  else if (ua.includes('iPad')) os = 'iPad'
  else if (ua.includes('Android')) os = 'Android'
  else if (ua.includes('Linux')) os = 'Linux'

  return os ? `${browser} en ${os}` : browser
}

// Parse change value from "old → new" format
const parseChangeValue = (change: string, part: 'old' | 'new'): string => {
  if (!change) return ''
  const parts = change.split(' → ')
  if (parts.length !== 2) return change
  return part === 'old' ? (parts[0] ?? '') : (parts[1] ?? '')
}

const getEmploymentType = (type: string) => {
  const option = tenantStore.options.employmentType.find(o => o.value === type)
  return option?.label || type
}

const getHousingType = (type: string) => {
  const option = tenantStore.options.housingType.find(o => o.value === type)
  return option?.label || type
}

const formatAddressTenure = (years?: number, months?: number) => {
  if (years === undefined && months === undefined) return '—'
  if (years === null && months === null) return '—'

  const parts = []
  if (years && years > 0) {
    parts.push(`${years} ${years === 1 ? 'año' : 'años'}`)
  }
  if (months && months > 0) {
    parts.push(`${months} ${months === 1 ? 'mes' : 'meses'}`)
  }

  return parts.length > 0 ? parts.join(', ') : 'Menos de 1 mes'
}

// Convert total months to "X años, Y meses" format
const formatTenureFromMonths = (totalMonths?: number) => {
  if (totalMonths === undefined || totalMonths === null) return '—'

  const years = Math.floor(totalMonths / 12)
  const months = totalMonths % 12

  const parts = []
  if (years > 0) {
    parts.push(`${years} ${years === 1 ? 'año' : 'años'}`)
  }
  if (months > 0) {
    parts.push(`${months} ${months === 1 ? 'mes' : 'meses'}`)
  }

  return parts.length > 0 ? parts.join(', ') : 'Menos de 1 mes'
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

// Get Mexican state name from code using tenant store options
const getMexicanStateName = (stateCode: string | undefined): string => {
  if (!stateCode) return '—'

  const option = tenantStore.options.mexicanState.find(opt => opt.value === stateCode.toUpperCase())
  return option?.label || stateCode
}

// Entidad de nacimiento: la CURP es la fuente canónica (codifica la entidad en
// las posiciones 12-13, ej. "CS" -> Chiapas). La preferimos porque el campo
// birth_state puede traer la clave CURP de 2 letras ("CS") que el catálogo de
// estados (códigos de 3 letras, "CHP") no resuelve. Fallback al campo guardado.
const birthStateDisplay = computed(() => {
  const a = application.value?.applicant
  if (!a) return '—'
  const fromCurp = getStateNameFromCurp((a.curp ?? '').toUpperCase())
  if (fromCurp) return fromCurp
  const fromField = getMexicanStateName(a.birth_state)
  return fromField && fromField !== '—' ? fromField : '—'
})

// isForeignNationality y requiredDocTypesFor se importan de ./mapApplicationDetail
// (helpers puros compartidos entre el mapeo y estos computeds).

// Computed: check if applicant is foreigner
const isForeigner = computed(() => {
  return isForeignNationality(application.value?.applicant?.nationality)
})

// Computed: normalize required documents based on nationality
const normalizedRequiredDocuments = computed(() => {
  const requiredDocs = application.value?.required_documents ?? []

  // Handle new structure: {nationals: [], foreigners: []} or legacy flat array
  if (Array.isArray(requiredDocs)) {
    // Legacy format: flat array
    return requiredDocs
  } else if (typeof requiredDocs === 'object' && requiredDocs !== null) {
    // New format: {nationals: [], foreigners: []}
    return isForeigner.value
      ? (requiredDocs.foreigners || [])
      : (requiredDocs.nationals || [])
  }

  return []
})

// Computed: all documents (uploaded + missing required)
const allDocuments = computed(() => {
  if (!application.value) return []

  const uploadedDocs = application.value.documents
  const uploadedTypes = new Set(uploadedDocs.map(d => d.type))
  const requiredDocs = application.value.required_documents || []

  const requiredDocsArray = requiredDocTypesFor(requiredDocs, isForeigner.value)

  // Create list with uploaded docs first
  const result: Array<Document & { missing?: boolean }> = [...uploadedDocs]

  // Add missing required docs
  for (const docType of requiredDocsArray) {
    if (!uploadedTypes.has(docType)) {
      result.push({
        id: `missing-${docType}`,
        type: docType,
        name: getDocTypeName(docType),
        status: 'PENDING' as const,
        missing: true
      })
    }
  }

  return result
})

// Check if product requires signature
const requiresSignature = computed(() => {
  const requiredDocs = application.value?.required_documents ?? []

  const isForeigner = isForeignNationality(application.value?.applicant?.nationality)
  const requiredDocsArray = requiredDocTypesFor(requiredDocs, isForeigner)

  return requiredDocsArray.includes('SIGNATURE')
})

// Completeness calculation
const completenessItems = computed(() => {
  if (!application.value) return []

  const c = application.value.completeness
  const items = [
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
  ]

  // Only add signature if product requires it
  if (requiresSignature.value) {
    items.push({ label: 'Firma digital', complete: c.signature, icon: 'pencil' })
  }

  return items
})

const completenessPercent = computed(() => {
  if (!application.value) return 0

  const c = application.value.completeness
  let completed = 0
  let total = 5 // Base: personal_data, address, employment, documents, references

  if (c.personal_data) completed++
  if (c.address) completed++
  if (c.employment) completed++
  if (c.documents.approved >= c.documents.required) completed++
  if (c.references.verified >= 2) completed++

  // Only count signature if product requires it
  if (requiresSignature.value) {
    total++
    if (c.signature) completed++
  }

  return Math.round((completed / total) * 100)
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

  try {
    // Make actual API call to update status
    await staff.application.changeStatus(application.value.id, {
      status: newStatus.value as import('@/types/v2').V2ApplicationStatus,
      notes: statusNote.value || undefined
    })

    await fetchApplication()
    showStatusModal.value = false
    toast.success('Estado actualizado correctamente')
  } catch (error: unknown) {
    log.error('Error al actualizar estado', { error })
    const axiosError = error as { response?: { data?: { message?: string } } }
    toast.error(axiosError.response?.data?.message || 'Error al cambiar el estado')
  } finally {
    isUpdatingStatus.value = false
  }
}

// Assignment methods
const openAssignModal = async () => {
  showAssignModal.value = true
  isLoadingUsers.value = true
  selectedUserId.value = ''

  try {
    const response = await staff.user.list({ active: true, role: 'ANALYST' })
    staffUsers.value = (response.data?.users ?? []).map(u => ({
      id: u.id,
      name: u.name,
      email: u.email,
      role: u.role
    }))
  } catch (error) {
    log.error('Error al cargar analistas', { error })
  } finally {
    isLoadingUsers.value = false
  }
}

const assignApplication = async () => {
  if (!application.value || !selectedUserId.value) return

  isAssigning.value = true

  try {
    await staff.application.assign(application.value.id, {
      user_id: selectedUserId.value
    })

    await fetchApplication()
    showAssignModal.value = false
    toast.success('Solicitud asignada correctamente')
  } catch (error: unknown) {
    log.error('Error al asignar solicitud', { error })
    const axiosError = error as { response?: { data?: { message?: string } } }
    toast.error(axiosError.response?.data?.message || 'Error al asignar la solicitud')
  } finally {
    isAssigning.value = false
  }
}

const openDocApproveModal = (doc: Document) => {
  docToApprove.value = doc
  showDocApproveModal.value = true
}

// View document
const viewDocument = async (doc: Document) => {
  if (!application.value) return

  isLoadingDocViewer.value = true
  docViewerName.value = doc.name

  try {
    const response = await staff.application.getDocumentUrl(application.value.id, doc.id)
    const data = response.data!

    docViewerUrl.value = data.url
    docViewerMimeType.value = data.mime_type || doc.mime_type || ''

    if (docViewerMimeType.value === 'application/pdf') {
      platform.browser.open(docViewerUrl.value, { external: true })
    } else {
      showDocViewerModal.value = true
    }
  } catch (e) {
    log.error('Error al obtener URL del documento', { error: e })
    toast.error('Error al cargar el documento')
  } finally {
    isLoadingDocViewer.value = false
  }
}

const confirmApproveDocument = async (_data?: { selectValue?: string; comment?: string }) => {
  if (!docToApprove.value || !application.value) return

  isApprovingDoc.value = true

  try {
    await staff.application.approveDocument(application.value.id, docToApprove.value.id)
    docToApprove.value.status = 'APPROVED'
    await fetchApplication()
    showDocApproveModal.value = false
    toast.success('Documento aprobado correctamente')
  } catch (e) {
    log.error('Error al aprobar documento', { error: e })
    toast.error('Error al aprobar el documento')
  } finally {
    isApprovingDoc.value = false
  }
}

const openDocRejectModal = (doc: Document) => {
  selectedDocument.value = doc
  docRejectReason.value = ''
  docRejectComment.value = ''
  showDocRejectModal.value = true
}

const confirmRejectDocument = async () => {
  if (!selectedDocument.value || !docRejectReason.value || !application.value) return

  isRejectingDoc.value = true

  try {
    await staff.application.rejectDocument(application.value.id, selectedDocument.value.id, {
      reason: docRejectReason.value,
      comment: docRejectComment.value || undefined
    })

    selectedDocument.value.status = 'REJECTED'
    selectedDocument.value.rejection_reason = docRejectReason.value
    selectedDocument.value.rejection_comment = docRejectComment.value

    await fetchApplication()
    showDocRejectModal.value = false
    toast.success('Documento rechazado')
  } catch (e) {
    log.error('Error al rechazar documento', { error: e })
    toast.error('Error al rechazar el documento')
  } finally {
    isRejectingDoc.value = false
  }
}

// Reference verification
const openVerifyRefModal = (ref: Reference) => {
  selectedReference.value = ref
  refVerifyResult.value = 'VERIFIED'
  refVerifyNotes.value = ''
  showVerifyRefModal.value = true
}

const confirmVerifyReference = async () => {
  if (!selectedReference.value || !application.value) return

  isVerifyingRef.value = true

  try {
    await staff.application.verifyReference(application.value.id, selectedReference.value.id, {
      result: refVerifyResult.value,
      notes: refVerifyNotes.value || undefined
    })

    selectedReference.value.verified = refVerifyResult.value === 'VERIFIED'
    selectedReference.value.verification_result = refVerifyResult.value
    selectedReference.value.verification_notes = refVerifyNotes.value

    await fetchApplication()
    showVerifyRefModal.value = false
    toast.success('Referencia verificada correctamente')
  } catch (e) {
    log.error('Error al verificar referencia', { error: e })
    toast.error('Error al verificar la referencia')
  } finally {
    isVerifyingRef.value = false
  }
}

// Verify data (field-level verification)
const isVerifyingData = ref(false)
type VerifiableField = 'first_name' | 'last_name_1' | 'last_name_2' | 'curp' | 'rfc' | 'ine_clave' | 'birth_date' | 'phone' | 'email' | 'address' | 'employment' | 'passport_number' | 'passport_issue_date' | 'passport_expiry_date'

// Reject data modal state
const showRejectDataModal = ref(false)
const rejectDataField = ref<VerifiableField | null>(null)

// Unverify modal state (for removing verification/rejection)
const showUnverifyModal = ref(false)
const unverifyField = ref<VerifiableField | null>(null)

// Helper to check if a field is verified
const isFieldVerified = (field: string): boolean => {
  const verification = application.value?.field_verifications?.[field]
  return verification?.status === 'VERIFIED' || verification?.verified === true
}

// Helper to check if a field is rejected
const isFieldRejected = (field: string): boolean => {
  const verification = application.value?.field_verifications?.[field]
  return verification?.status === 'REJECTED'
}

// Helper to check if a field is pending (was verified/rejected but then unverified)
const isFieldPending = (field: string): boolean => {
  const verification = application.value?.field_verifications?.[field]
  return verification?.status === 'PENDING'
}

// Helper to get field verification details
const getFieldVerification = (field: string) => {
  return application.value?.field_verifications?.[field]
}

// Helper to check if a field is locked (verified by KYC and cannot be modified)
const isFieldLocked = (field: string): boolean => {
  const verification = application.value?.field_verifications?.[field]
  return verification?.is_locked === true
}

// Helper to get field label in Spanish
const getFieldLabel = (field: string): string => {
  const labels: Record<string, string> = {
    'first_name': 'Nombre',
    'last_name_1': 'Apellido Paterno',
    'last_name_2': 'Apellido Materno',
    'curp': 'CURP',
    'rfc': 'RFC',
    'ine_clave': 'Clave INE',
    'birth_date': 'Fecha de Nacimiento',
    'phone': 'Teléfono',
    'email': 'Email',
    'address': 'Dirección',
    'employment': 'Información Laboral',
    'passport_number': 'Número de Pasaporte',
    'passport_issue_date': 'Fecha de Emisión (Pasaporte)',
    'passport_expiry_date': 'Fecha de Expiración (Pasaporte)'
  }
  return labels[field] || field
}

const verifyData = async (field: VerifiableField, action: 'verify' | 'reject' | 'unverify', reason?: string) => {
  if (!application.value) return

  isVerifyingData.value = true

  try {
    await staff.application.verifyData(application.value.id, {
      field,
      action,
      method: 'MANUAL',
      rejection_reason: action === 'reject' ? reason || undefined : undefined,
      notes: action === 'unverify' ? reason || undefined : undefined
    })

    // Update local state for field_verifications
    if (!application.value.field_verifications) {
      application.value.field_verifications = {}
    }

    if (action === 'verify') {
      application.value.field_verifications[field] = {
        status: 'VERIFIED',
        verified: true,
        method: 'MANUAL',
        method_label: 'Manual',
        verified_at: new Date().toISOString()
      }
    } else if (action === 'unverify') {
      // Set to PENDING state instead of deleting (matches backend behavior)
      application.value.field_verifications[field] = {
        status: 'PENDING',
        verified: false,
        method: null,
        method_label: null,
        verified_at: new Date().toISOString()
      }
    } else if (action === 'reject') {
      application.value.field_verifications[field] = {
        status: 'REJECTED',
        verified: false,
        method: 'MANUAL',
        method_label: 'Manual',
        verified_at: new Date().toISOString(),
        rejection_reason: reason
      }
    }

    // Also update legacy verification for backwards compatibility
    if (application.value.verification) {
      const verified = action === 'verify'
      switch (field) {
        case 'phone':
          application.value.verification.phone_verified = verified
          break
        case 'email':
          application.value.verification.email_verified = verified
          break
        case 'address':
          application.value.verification.address_verified = verified
          break
        case 'employment':
          application.value.verification.employment_verified = verified
          break
      }
    }

    await fetchApplication()
    toast.success(action === 'verify' ? 'Dato verificado' : action === 'reject' ? 'Dato rechazado' : 'Verificación removida')
  } catch (e) {
    log.error('Error al verificar datos', { error: e })
    toast.error('Error al verificar los datos')
  } finally {
    isVerifyingData.value = false
  }
}

// Open reject modal
const openRejectDataModal = (field: VerifiableField) => {
  rejectDataField.value = field
  showRejectDataModal.value = true
}

// Confirm data rejection (receives data from ConfirmModal)
const confirmRejectData = async (data: { selectValue?: string; comment?: string }) => {
  log.debug('confirmRejectData called', { data, rejectDataField: rejectDataField.value })

  if (!rejectDataField.value) {
    log.warn('No rejectDataField set')
    return
  }

  if (!data.comment?.trim()) {
    log.warn('No comment provided for rejection')
    toast.error('Debes proporcionar un motivo de rechazo')
    return
  }

  await verifyData(rejectDataField.value, 'reject', data.comment)
  showRejectDataModal.value = false
}

// Editar teléfono del solicitante. Solo SUPER_ADMIN y de uso para PRUEBAS:
// el número es único por tenant y no se "recicla", así que cambiarlo aquí
// libera el número original para volver a registrarlo desde cero. Reutiliza
// ConfirmModal (campo comment) para capturar el nuevo número.
const showEditPhoneModal = ref(false)
const isEditingPhone = ref(false)

const openEditPhoneModal = () => {
  showEditPhoneModal.value = true
}

const confirmEditPhone = async (data: { selectValue?: string; comment?: string }) => {
  if (!application.value) return

  const phone = (data.comment || '').replace(/\D/g, '').slice(-10)
  if (phone.length !== 10) {
    toast.error('Ingresa un teléfono de 10 dígitos')
    return
  }

  isEditingPhone.value = true
  try {
    const res = await staff.application.updateApplicantPhone(application.value.id, phone)
    application.value.applicant.phone = res.data?.phone || phone
    toast.success(res.message || 'Teléfono actualizado.')
    showEditPhoneModal.value = false
  } catch (e: unknown) {
    const body = (e as { response?: { data?: { message?: string; error?: string } } })?.response?.data
    toast.error(body?.message || body?.error || 'No se pudo actualizar el teléfono')
  } finally {
    isEditingPhone.value = false
  }
}

// Open unverify modal
const openUnverifyModal = (field: VerifiableField) => {
  unverifyField.value = field
  showUnverifyModal.value = true
}

// Confirm unverify (receives data from ConfirmModal)
const confirmUnverify = async (data: { selectValue?: string; comment?: string }) => {
  log.debug('confirmUnverify called', { data, unverifyField: unverifyField.value })

  if (!unverifyField.value) {
    log.warn('No unverifyField set')
    return
  }

  if (!data.comment?.trim()) {
    log.warn('No comment provided for unverify')
    toast.error('Debes proporcionar un motivo')
    return
  }

  await verifyData(unverifyField.value, 'unverify', data.comment)
  showUnverifyModal.value = false
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

  try {
    await staff.application.createCounterOffer(application.value.id, {
      amount: counterOffer.value.amount,
      term_months: counterOffer.value.term_months,
      interest_rate: counterOffer.value.interest_rate,
      reason: counterOffer.value.reason
    })

    await fetchApplication()
    showCounterOfferModal.value = false
    toast.success('Contraoferta enviada correctamente')
  } catch (e) {
    log.error('Error al enviar contraoferta', { error: e })
    toast.error('Error al enviar la contraoferta')
  } finally {
    isSubmittingCounterOffer.value = false
  }
}

// Handler for NotesSection component
const handleAddNote = async (text: string) => {
  if (!application.value || !text.trim()) return

  isAddingNote.value = true

  try {
    const response = await staff.application.addNote(application.value.id, {
      content: text.trim()
    })

    const noteData = response.data!
    application.value.notes.unshift({
      id: noteData.id,
      text: noteData.content,
      author: noteData.author?.name || 'Sistema',
      created_at: noteData.created_at
    })

    toast.success('Nota agregada')
  } catch (e) {
    log.error('Error al agregar nota', { error: e })
    toast.error('Error al agregar la nota')
  } finally {
    isAddingNote.value = false
  }
}


// Cleanup: revoke object URLs to prevent memory leaks
onUnmounted(() => {
  if (selfieUrl.value) {
    URL.revokeObjectURL(selfieUrl.value)
  }
})
</script>

<template>
  <div>
    <!-- Loading State -->
    <div v-if="loading" class="flex items-center justify-center py-12">
      <div class="animate-spin w-8 h-8 border-4 border-primary-600 border-t-transparent rounded-full" />
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="flex flex-col items-center justify-center py-12 text-center">
      <svg class="w-16 h-16 text-red-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
      </svg>
      <h2 class="text-xl font-semibold text-gray-900 mb-2">{{ error }}</h2>
      <p class="text-gray-600 mb-4">No se pudo cargar la información de la solicitud.</p>
      <button
        class="px-4 py-2 text-sm font-medium text-white rounded-lg transition-colors"
        :style="{ backgroundColor: tenantStore.branding?.primary_color || '#7c3aed' }"
        @click="fetchApplication"
      >
        Reintentar
      </button>
    </div>

    <template v-else-if="application">
      <!-- Header with subtle border -->
      <div class="relative bg-white rounded-lg border-t-4 shadow-sm mb-6" :style="{ borderTopColor: tenantStore.branding?.primary_color || '#7c3aed' }">
        <!-- Back button + Header content in single row -->
        <div class="flex items-center justify-between gap-4 px-6 py-3">
          <!-- Left: Back button + Info -->
          <div class="flex items-center gap-4 flex-1 min-w-0">
            <button
              class="flex items-center gap-2 text-gray-600 hover:text-primary-600 transition-colors flex-shrink-0"
              @click="goBack"
            >
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
              </svg>
            </button>

            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-3 mb-0.5">
                <h1 class="text-xl font-bold text-gray-900">{{ application.folio }}</h1>
                <span
                  :class="[
                    'px-2.5 py-0.5 text-xs font-medium rounded-full',
                    getStatusBadge(application.status).bg,
                    getStatusBadge(application.status).text
                  ]"
                >
                  {{ getStatusBadge(application.status).label }}
                </span>
              </div>
              <p class="text-base text-gray-800 font-medium truncate">{{ application.applicant.full_name }}</p>
              <p class="text-gray-500 text-xs">
                {{ formatDateTime(application.created_at) }}
                <span v-if="application.assigned_to" class="ml-2">
                  · {{ application.assigned_to }}
                </span>
              </p>
            </div>
          </div>

          <!-- Center: Action Buttons -->
          <div class="flex flex-col gap-2 flex-shrink-0">
            <!-- Contraoferta: Solo supervisores/admins que pueden aprobar/rechazar -->
            <button
              v-if="canApproveReject && ['IN_REVIEW', 'DOCS_PENDING'].includes(application.status)"
              class="px-3 py-1.5 text-xs font-medium rounded-lg border transition-colors"
              :style="{
                backgroundColor: tenantStore.branding?.primary_color ? `${tenantStore.branding.primary_color}15` : '#7c3aed15',
                color: tenantStore.branding?.primary_color || '#7c3aed',
                borderColor: tenantStore.branding?.primary_color ? `${tenantStore.branding.primary_color}40` : '#7c3aed40'
              }"
              @click="openCounterOfferModal"
            >
              Contraoferta
            </button>
            <!-- Asignar: Solo supervisores/admins -->
            <button
              v-if="canAssign"
              class="px-3 py-1.5 text-xs font-medium rounded-lg border transition-colors"
              :style="{
                backgroundColor: tenantStore.branding?.primary_color ? `${tenantStore.branding.primary_color}15` : '#7c3aed15',
                color: tenantStore.branding?.primary_color || '#7c3aed',
                borderColor: tenantStore.branding?.primary_color ? `${tenantStore.branding.primary_color}40` : '#7c3aed40'
              }"
              @click="openAssignModal"
            >
              Asignar
            </button>
            <!-- Cambiar Estado: Analistas y superiores -->
            <button
              v-if="canChangeStatus"
              class="px-3 py-1.5 text-xs font-medium rounded-lg border transition-colors"
              :style="{
                backgroundColor: tenantStore.branding?.primary_color ? `${tenantStore.branding.primary_color}15` : '#7c3aed15',
                color: tenantStore.branding?.primary_color || '#7c3aed',
                borderColor: tenantStore.branding?.primary_color ? `${tenantStore.branding.primary_color}40` : '#7c3aed40'
              }"
              @click="openStatusModal"
            >
              Cambiar Estado
            </button>
            <!-- Generar Contrato: Solo supervisores/admins con solicitud aprobada -->
            <button
              v-if="canApproveReject && application.status === 'APPROVED'"
              class="px-3 py-1.5 text-xs font-medium text-white rounded-lg transition-colors"
              :style="{
                backgroundColor: tenantStore.branding?.primary_color || '#7c3aed'
              }"
            >
              Generar Contrato
            </button>
          </div>

          <!-- Right: Selfie Photo -->
          <div class="flex-shrink-0">
            <div class="relative w-28 h-28">
              <!-- Photo container -->
              <button
                class="w-full h-full rounded-lg overflow-hidden bg-gray-100 flex items-center justify-center border-2 transition-all hover:ring-2 hover:ring-primary-200"
                :class="{
                  'border-green-400': selfieStatus === 'APPROVED',
                  'border-red-400': selfieStatus === 'REJECTED',
                  'border-yellow-400': selfieStatus === 'PENDING' && selfieUrl,
                  'border-gray-300': !selfieUrl
                }"
                @click="selfieUrl ? showSelfieViewer = true : null"
                :disabled="!selfieUrl"
              >
                <img
                  v-if="selfieUrl"
                  :src="selfieUrl"
                  alt="Foto del solicitante"
                  class="w-full h-full object-cover"
                />
                <div v-else-if="isLoadingSelfie" class="animate-spin w-6 h-6 border-2 border-primary-600 border-t-transparent rounded-full" />
                <svg v-else class="w-10 h-10 text-gray-400" fill="currentColor" viewBox="0 0 24 24">
                  <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                </svg>
              </button>

              <!-- Status badge -->
              <span
                v-if="selfieUrl"
                class="absolute -top-1 -right-1 px-1.5 py-0.5 rounded text-xs font-medium z-10"
                :class="{
                  'bg-green-100 text-green-800': selfieStatus === 'APPROVED',
                  'bg-red-100 text-red-800': selfieStatus === 'REJECTED',
                  'bg-yellow-100 text-yellow-800': selfieStatus === 'PENDING'
                }"
                :title="selfieIsKycVerified && selfieFaceMatchScore !== null ? `Face match: ${selfieFaceMatchScore.toFixed(0)}%` : ''"
              >
                <template v-if="selfieStatus === 'APPROVED' && selfieIsKycVerified && selfieFaceMatchScore !== null">
                  {{ selfieFaceMatchScore.toFixed(0) }}%
                </template>
                <template v-else>
                  {{ selfieStatus === 'APPROVED' ? 'OK' : selfieStatus === 'REJECTED' ? 'X' : '?' }}
                </template>
              </span>

              <!-- Approve/Reject/Unapprove buttons inside photo box (subtle icons) - Solo con permiso de revisar documentos -->
              <div
                v-if="selfieUrl && canReviewDocs"
                class="absolute bottom-1 left-1/2 -translate-x-1/2 flex gap-1"
              >
                <!-- PENDING: show approve and reject -->
                <template v-if="selfieStatus === 'PENDING'">
                  <button
                    class="w-6 h-6 flex items-center justify-center rounded-full bg-black/40 hover:bg-green-600 text-white/80 hover:text-white transition-colors"
                    @click.stop="showSelfieApproveModal = true"
                    title="Aprobar"
                  >
                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24">
                      <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
                    </svg>
                  </button>
                  <button
                    class="w-6 h-6 flex items-center justify-center rounded-full bg-black/40 hover:bg-red-600 text-white/80 hover:text-white transition-colors"
                    @click.stop="showSelfieRejectModal = true"
                    title="Rechazar"
                  >
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                  </button>
                </template>
                <!-- APPROVED: show unapprove (back to pending) - NOT if KYC verified -->
                <template v-else-if="selfieStatus === 'APPROVED' && !selfieIsKycVerified">
                  <button
                    class="w-6 h-6 flex items-center justify-center rounded-full bg-black/40 hover:bg-yellow-600 text-white/80 hover:text-white transition-colors"
                    @click.stop="showSelfieUnapproveModal = true"
                    title="Desaprobar (volver a pendiente)"
                  >
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/>
                    </svg>
                  </button>
                </template>
                <!-- APPROVED + KYC verified: show lock indicator only -->
                <template v-else-if="selfieStatus === 'APPROVED' && selfieIsKycVerified">
                  <div
                    class="w-6 h-6 flex items-center justify-center rounded-full bg-green-600/80 text-white"
                    title="Validado por KYC - No modificable"
                  >
                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                      <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
                    </svg>
                  </div>
                </template>
                <!-- REJECTED: show only unreject (back to pending), no direct approve -->
                <template v-else-if="selfieStatus === 'REJECTED'">
                  <button
                    class="w-6 h-6 flex items-center justify-center rounded-full bg-black/40 hover:bg-yellow-600 text-white/80 hover:text-white transition-colors"
                    @click.stop="showSelfieUnrejectModal = true"
                    title="Quitar Rechazo (volver a pendiente)"
                  >
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/>
                    </svg>
                  </button>
                </template>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Avance del Expediente -->
      <CompletenessCard
        :percent="completenessPercent"
        :color="completenessColor"
        :items="completenessItems"
      />

      <!-- Tabs -->
      <div class="bg-white rounded-xl shadow-sm mb-6">
        <div class="border-b border-gray-100">
          <nav class="flex -mb-px">
            <button
              v-for="tab in tabs"
              :key="tab.id"
              :class="[
                'px-6 py-3 text-sm font-medium border-b transition-colors',
                activeTab === tab.id
                  ? 'border-primary-500 text-primary-600'
                  : 'border-transparent text-gray-500 hover:text-gray-700'
              ]"
              @click="switchTab(tab.id)"
            >
              {{ tab.label }}
            </button>
          </nav>
        </div>

        <!-- Tab Content -->
        <div class="p-6">
          <!-- General Tab -->
          <div v-if="activeTab === 'general'" class="space-y-4">
            <!-- Modal: Verificación de INE (confirmado cliente vs OCR vs RENAPO) -->
            <Teleport to="body">
              <div
                v-if="ineComparison && showIneVerification"
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40"
                @click.self="showIneVerification = false"
              >
                <div
                  class="bg-white rounded-lg shadow-xl w-full max-w-3xl max-h-[85vh] overflow-hidden flex flex-col"
                  :class="ineComparison.hasDiffs ? 'ring-1 ring-amber-300' : ''"
                >
                  <div
                    class="px-5 py-3 flex items-center justify-between gap-3 border-b border-gray-100"
                    :class="ineComparison.hasDiffs ? 'bg-amber-50' : 'bg-gray-50'"
                  >
                    <span class="font-semibold text-gray-800 inline-flex items-center gap-2">
                      Verificación de INE
                      <span v-if="ineComparison.hasDiffs" class="text-xs font-semibold text-amber-700 bg-amber-100 px-1.5 py-0.5 rounded">
                        Revisar diferencias
                      </span>
                    </span>
                    <div class="flex items-center gap-3 text-xs">
                      <span :class="ineComparison.ineValid ? 'text-green-700' : 'text-gray-500'">
                        INE {{ ineComparison.ineValid === true ? '✓' : ineComparison.ineValid === false ? '✕' : '—' }}
                      </span>
                      <span :class="ineComparison.curpValid ? 'text-green-700' : 'text-gray-500'">
                        RENAPO {{ ineComparison.curpValid === true ? '✓' : ineComparison.curpValid === false ? '✕' : '—' }}
                      </span>
                      <button
                        type="button"
                        class="text-gray-400 hover:text-gray-600 text-lg leading-none"
                        aria-label="Cerrar"
                        @click="showIneVerification = false"
                      >
                        ✕
                      </button>
                    </div>
                  </div>
                  <div class="overflow-auto">
                    <table class="w-full text-sm">
                      <thead>
                        <tr class="text-xs text-gray-500 border-b border-gray-100">
                          <th class="text-left font-medium px-5 py-2">Campo</th>
                          <th class="text-left font-medium px-3 py-2">Confirmado</th>
                          <th class="text-left font-medium px-3 py-2">OCR (INE)</th>
                          <th class="text-left font-medium px-3 py-2">RENAPO</th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr
                          v-for="row in ineComparison.rows"
                          :key="row.label"
                          class="border-b border-gray-50 last:border-0"
                          :class="row.diff ? 'bg-amber-50/50' : ''"
                        >
                          <td class="px-5 py-2 text-gray-500">{{ row.label }}</td>
                          <td class="px-3 py-2 font-medium" :class="row.diff ? 'text-amber-800' : 'text-gray-900'">{{ row.confirmed || '—' }}</td>
                          <td class="px-3 py-2 text-gray-700">{{ row.ocr || '—' }}</td>
                          <td class="px-3 py-2 text-gray-700">{{ row.renapo || '—' }}</td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            </Teleport>

            <!-- Summary Cards -->
            <LoanSummaryCards :loan="application.loan" />

            <!-- Applicant Info - Improved UI with completion/verification distinction -->
            <div class="border border-gray-200 rounded-lg">
              <div class="bg-gray-50 px-3 py-2 border-b border-gray-200 flex items-center justify-between">
                <div class="flex items-center gap-2">
                  <h3 class="text-sm font-semibold text-gray-900">Datos del Solicitante</h3>
                  <span v-if="isForeigner" class="px-2 py-0.5 text-xs font-medium bg-blue-100 text-blue-800 rounded">
                    Extranjero
                  </span>
                  <!-- Acceso a la verificación de INE (abre el modal) -->
                  <button
                    v-if="ineComparison"
                    type="button"
                    class="inline-flex items-center gap-1.5 text-xs px-2 py-0.5 rounded border transition-colors"
                    :class="ineComparison.hasDiffs ? 'border-amber-300 bg-amber-50 text-amber-700 hover:bg-amber-100' : 'border-gray-200 text-gray-600 hover:bg-gray-100'"
                    @click="showIneVerification = true"
                  >
                    <span class="font-medium">INE</span>
                    <span :class="ineComparison.ineValid ? 'text-green-600' : 'text-gray-400'">{{ ineComparison.ineValid === true ? '✓' : ineComparison.ineValid === false ? '✕' : '—' }}</span>
                    <span>{{ ineComparison.hasDiffs ? 'Revisar diferencias' : 'Ver detalle' }}</span>
                  </button>
                  <!-- Sin verificación previa: dispararla (Nubarium caído en onboarding) -->
                  <button
                    v-else-if="canReviewDocs"
                    type="button"
                    class="inline-flex items-center gap-1.5 text-xs px-2 py-0.5 rounded border border-dashed border-gray-300 text-primary-600 hover:bg-gray-100 disabled:opacity-60 disabled:cursor-not-allowed"
                    :disabled="reverifyingIne"
                    @click="reverifyIne"
                  >
                    <svg v-if="reverifyingIne" class="animate-spin h-3 w-3" viewBox="0 0 24 24" fill="none">
                      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.4 0 0 5.4 0 12h4z" />
                    </svg>
                    {{ reverifyingIne ? 'Verificando…' : 'Verificar INE' }}
                  </button>
                </div>
                <div class="flex items-center gap-3 text-xs text-gray-500">
                  <span class="flex items-center gap-1">
                    <span class="w-2 h-2 rounded-full bg-gray-300"></span>
                    Vacío
                  </span>
                  <span class="flex items-center gap-1">
                    <span class="w-2 h-2 rounded-full bg-blue-500"></span>
                    Completado
                  </span>
                  <span class="flex items-center gap-1">
                    <span class="w-2 h-2 rounded-full bg-green-500"></span>
                    Verificado
                  </span>
                  <span class="flex items-center gap-1">
                    <span class="w-2 h-2 rounded-full bg-yellow-500"></span>
                    Pendiente
                  </span>
                  <span class="flex items-center gap-1">
                    <span class="w-2 h-2 rounded-full bg-red-500"></span>
                    Rechazado
                  </span>
                </div>
              </div>
              <div class="p-3">
                <div class="grid grid-cols-3 gap-x-4 gap-y-3 text-sm">
                  <!-- Nombre -->
                  <VerifiableField
                    label="Nombre"
                    :value="application.applicant.full_name"
                    field-key="first_name"
                    :verification="getFieldVerification('first_name')"
                    :is-locked="isFieldLocked('first_name')"
                    :is-verifying="isVerifyingData"
                    :can-verify="true"
                    @verify="(action) => verifyData('first_name', action)"
                    @reject="openRejectDataModal('first_name')"
                    @unreject="openUnverifyModal('first_name')"
                  />
                  <!-- Nacionalidad / Entidad de Nacimiento -->
                  <div class="group relative">
                    <div class="flex items-center gap-1.5 mb-0.5">
                      <span
                        class="w-2 h-2 rounded-full flex-shrink-0 transition-colors"
                        :class="isForeigner ? 'bg-blue-500' : isFieldRejected('birth_state') ? 'bg-red-500' : isFieldVerified('birth_state') ? 'bg-green-500' : isFieldPending('birth_state') ? 'bg-yellow-500' : birthStateDisplay ? 'bg-blue-500' : 'bg-gray-300'"
                      ></span>
                      <span class="text-xs text-gray-500">{{ isForeigner ? 'Nacionalidad' : 'Entidad de Nacimiento' }}</span>
                    </div>
                    <p class="font-medium text-gray-900 flex items-center gap-1.5">
                      <span v-if="isForeigner" class="text-xl">{{ application.applicant.nationality_info?.flag || '🌍' }}</span>
                      <span v-if="isForeigner">{{ application.applicant.nationality_info?.name || application.applicant.nationality || '—' }}</span>
                      <span v-else>{{ birthStateDisplay }}</span>
                      <span v-if="isForeigner" class="text-xs font-medium bg-blue-100 text-blue-800 px-1.5 py-0.5 rounded">
                        Extranjero
                      </span>
                    </p>
                  </div>
                  <!-- Estado civil (capturado en el onboarding) -->
                  <div class="group relative">
                    <div class="flex items-center gap-1.5 mb-0.5">
                      <span class="w-2 h-2 rounded-full flex-shrink-0" :class="application.applicant.marital_status_label ? 'bg-blue-500' : 'bg-gray-300'"></span>
                      <span class="text-xs text-gray-500">Estado civil</span>
                    </div>
                    <p class="font-medium text-gray-900">{{ application.applicant.marital_status_label || '—' }}</p>
                  </div>
                  <!-- Nivel educativo (capturado en el onboarding) -->
                  <div class="group relative">
                    <div class="flex items-center gap-1.5 mb-0.5">
                      <span class="w-2 h-2 rounded-full flex-shrink-0" :class="application.applicant.education_level_label ? 'bg-blue-500' : 'bg-gray-300'"></span>
                      <span class="text-xs text-gray-500">Nivel educativo</span>
                    </div>
                    <p class="font-medium text-gray-900">{{ application.applicant.education_level_label || '—' }}</p>
                  </div>
                  <!-- Email -->
                  <div class="group relative">
                    <div class="flex items-center gap-1.5 mb-0.5">
                      <span
                        class="w-2 h-2 rounded-full flex-shrink-0 transition-colors"
                        :class="isFieldRejected('email') ? 'bg-red-500' : isFieldVerified('email') ? 'bg-green-500' : isFieldPending('email') ? 'bg-yellow-500' : application.applicant.email ? 'bg-blue-500' : 'bg-gray-300'"
                      ></span>
                      <span class="text-xs text-gray-500">Email</span>
                      <div v-if="application.applicant.email" class="opacity-0 group-hover:opacity-100 transition-opacity ml-auto flex items-center gap-0.5">
                        <button
                          v-if="!isFieldVerified('email') && !isFieldRejected('email')"
                          class="p-0.5 rounded hover:bg-green-100 text-gray-400 hover:text-green-600"
                          :disabled="isVerifyingData"
                          title="Verificar dato"
                          @click="verifyData('email', 'verify')"
                        >
                          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                          </svg>
                        </button>
                        <button
                          v-if="isFieldVerified('email')"
                          class="p-0.5 rounded hover:bg-gray-100 text-green-600"
                          :disabled="isVerifyingData"
                          title="Quitar verificación"
                          @click="verifyData('email', 'unverify')"
                        >
                          <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                          </svg>
                        </button>
                        <button
                          v-if="!isFieldRejected('email')"
                          class="p-0.5 rounded hover:bg-red-100 text-gray-400 hover:text-red-600"
                          :disabled="isVerifyingData"
                          title="Rechazar dato"
                          @click="openRejectDataModal('email')"
                        >
                          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                          </svg>
                        </button>
                        <button
                          v-if="isFieldRejected('email')"
                          class="p-0.5 rounded hover:bg-gray-100 text-red-600"
                          :disabled="isVerifyingData"
                          title="Quitar rechazo"
                          @click="openUnverifyModal('email')"
                        >
                          <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                          </svg>
                        </button>
                      </div>
                    </div>
                    <p class="font-medium text-gray-900 truncate">{{ application.applicant.email || '—' }}</p>
                    <p v-if="isFieldRejected('email')" class="text-xs text-red-600 mt-0.5">
                      ⚠ {{ getFieldVerification('email')?.rejection_reason }}
                    </p>
                  </div>
                  <!-- Teléfono -->
                  <div class="group relative">
                    <div class="flex items-center gap-1.5 mb-0.5">
                      <span
                        class="w-2 h-2 rounded-full flex-shrink-0 transition-colors"
                        :class="isFieldRejected('phone') ? 'bg-red-500' : isFieldVerified('phone') ? 'bg-green-500' : isFieldPending('phone') ? 'bg-yellow-500' : application.applicant.phone ? 'bg-blue-500' : 'bg-gray-300'"
                      ></span>
                      <span class="text-xs text-gray-500">Teléfono</span>
                      <svg v-if="isFieldLocked('phone')" class="w-3 h-3 text-gray-400" fill="currentColor" viewBox="0 0 20 20" title="Verificado por OTP - No modificable">
                        <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
                      </svg>
                      <!-- Editar teléfono (solo SUPER_ADMIN, uso de pruebas: libera el número) -->
                      <button
                        v-if="authStore.isSuperAdmin"
                        class="p-0.5 rounded hover:bg-blue-100 text-gray-400 hover:text-blue-600"
                        :disabled="isEditingPhone"
                        title="Editar teléfono (pruebas: libera el número para volver a registrarlo)"
                        @click="openEditPhoneModal"
                      >
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                      </button>
                      <div v-if="application.applicant.phone && !isFieldLocked('phone')" class="opacity-0 group-hover:opacity-100 transition-opacity ml-auto flex items-center gap-0.5">
                        <button
                          v-if="!isFieldVerified('phone') && !isFieldRejected('phone')"
                          class="p-0.5 rounded hover:bg-green-100 text-gray-400 hover:text-green-600"
                          :disabled="isVerifyingData"
                          title="Verificar dato"
                          @click="verifyData('phone', 'verify')"
                        >
                          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                          </svg>
                        </button>
                        <button
                          v-if="isFieldVerified('phone')"
                          class="p-0.5 rounded hover:bg-gray-100 text-green-600"
                          :disabled="isVerifyingData"
                          title="Quitar verificación"
                          @click="verifyData('phone', 'unverify')"
                        >
                          <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                          </svg>
                        </button>
                        <button
                          v-if="!isFieldRejected('phone')"
                          class="p-0.5 rounded hover:bg-red-100 text-gray-400 hover:text-red-600"
                          :disabled="isVerifyingData"
                          title="Rechazar dato"
                          @click="openRejectDataModal('phone')"
                        >
                          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                          </svg>
                        </button>
                        <button
                          v-if="isFieldRejected('phone')"
                          class="p-0.5 rounded hover:bg-gray-100 text-red-600"
                          :disabled="isVerifyingData"
                          title="Quitar rechazo"
                          @click="openUnverifyModal('phone')"
                        >
                          <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                          </svg>
                        </button>
                      </div>
                      <!-- Method badge for locked phone -->
                      <div v-if="isFieldLocked('phone')" class="ml-auto">
                        <span class="text-[10px] text-gray-500 bg-gray-100 px-1.5 py-0.5 rounded">
                          {{ getFieldVerification('phone')?.method_label || 'OTP' }}
                        </span>
                      </div>
                    </div>
                    <p class="font-medium text-gray-900">{{ formatPhone(application.applicant.phone) }}</p>
                    <p v-if="isFieldRejected('phone')" class="text-xs text-red-600 mt-0.5">
                      ⚠ {{ getFieldVerification('phone')?.rejection_reason }}
                    </p>
                    <p v-if="isFieldLocked('phone')" class="text-[10px] text-gray-500 mt-0.5">
                      Verificado automáticamente - No modificable
                    </p>
                  </div>
                  <!-- CURP (solo para nacionales) — piloto de VerifiableField -->
                  <VerifiableField
                    v-if="!isForeigner"
                    label="CURP"
                    :value="application.applicant.curp"
                    field-key="curp"
                    :verification="getFieldVerification('curp')"
                    :is-locked="isFieldLocked('curp')"
                    :is-verifying="isVerifyingData"
                    :can-verify="true"
                    mono
                    @verify="(action) => verifyData('curp', action)"
                    @reject="openRejectDataModal('curp')"
                    @unreject="openUnverifyModal('curp')"
                  />
                  <!-- RFC -->
                  <VerifiableField
                    label="RFC"
                    :value="application.applicant.rfc"
                    field-key="rfc"
                    :verification="getFieldVerification('rfc')"
                    :is-locked="isFieldLocked('rfc')"
                    :is-verifying="isVerifyingData"
                    :can-verify="true"
                    mono
                    @verify="(action) => verifyData('rfc', action)"
                    @reject="openRejectDataModal('rfc')"
                    @unreject="openUnverifyModal('rfc')"
                  />
                  <!-- Número de Pasaporte (solo para extranjeros) -->
                  <VerifiableField
                    v-if="isForeigner"
                    label="Número de Pasaporte"
                    :value="application.applicant.passport_number"
                    field-key="passport_number"
                    :verification="getFieldVerification('passport_number')"
                    :is-locked="isFieldLocked('passport_number')"
                    :is-verifying="isVerifyingData"
                    :can-verify="true"
                    mono
                    @verify="(action) => verifyData('passport_number', action)"
                    @reject="openRejectDataModal('passport_number')"
                    @unreject="openUnverifyModal('passport_number')"
                  />
                  <!-- Fecha de Emisión (solo para extranjeros) -->
                  <VerifiableField
                    v-if="isForeigner"
                    label="Fecha de Emisión"
                    :value="application.applicant.passport_issue_date ? formatDate(application.applicant.passport_issue_date) : null"
                    field-key="passport_issue_date"
                    :verification="getFieldVerification('passport_issue_date')"
                    :is-locked="isFieldLocked('passport_issue_date')"
                    :is-verifying="isVerifyingData"
                    :can-verify="true"
                    @verify="(action) => verifyData('passport_issue_date', action)"
                    @reject="openRejectDataModal('passport_issue_date')"
                    @unreject="openUnverifyModal('passport_issue_date')"
                  />
                  <!-- Fecha de Expiración (solo para extranjeros) -->
                  <VerifiableField
                    v-if="isForeigner"
                    label="Fecha de Expiración"
                    :value="application.applicant.passport_expiry_date ? formatDate(application.applicant.passport_expiry_date) : null"
                    field-key="passport_expiry_date"
                    :verification="getFieldVerification('passport_expiry_date')"
                    :is-locked="isFieldLocked('passport_expiry_date')"
                    :is-verifying="isVerifyingData"
                    :can-verify="true"
                    @verify="(action) => verifyData('passport_expiry_date', action)"
                    @reject="openRejectDataModal('passport_expiry_date')"
                    @unreject="openUnverifyModal('passport_expiry_date')"
                  />
                  <!-- Clave INE (solo para nacionales) -->
                  <VerifiableField
                    v-if="!isForeigner"
                    label="Clave INE"
                    :value="application.applicant.ine_clave"
                    field-key="ine_clave"
                    :verification="getFieldVerification('ine_clave')"
                    :is-locked="isFieldLocked('ine_clave')"
                    :is-verifying="isVerifyingData"
                    :can-verify="true"
                    mono
                    @verify="(action) => verifyData('ine_clave', action)"
                    @reject="openRejectDataModal('ine_clave')"
                    @unreject="openUnverifyModal('ine_clave')"
                  />
                  <!-- OCR del INE (capturado en onboarding sin proveedor KYC) -->
                  <div v-if="application.applicant.ine_ocr" class="group relative">
                    <div class="flex items-center gap-1.5 mb-0.5">
                      <span
                        class="w-2 h-2 rounded-full flex-shrink-0 transition-colors"
                        :class="isFieldRejected('ine_ocr') ? 'bg-red-500' : isFieldVerified('ine_ocr') ? 'bg-green-500' : isFieldPending('ine_ocr') ? 'bg-yellow-500' : 'bg-blue-500'"
                      ></span>
                      <span class="text-xs text-gray-500">OCR (INE)</span>
                    </div>
                    <p class="font-mono text-sm text-gray-900">{{ application.applicant.ine_ocr }}</p>
                  </div>
                  <!-- Folio / CIC del INE -->
                  <div
                    v-if="application.applicant.ine_folio && application.applicant.ine_folio !== application.applicant.ine_clave"
                    class="group relative"
                  >
                    <div class="flex items-center gap-1.5 mb-0.5">
                      <span
                        class="w-2 h-2 rounded-full flex-shrink-0 transition-colors"
                        :class="isFieldRejected('ine_folio') ? 'bg-red-500' : isFieldVerified('ine_folio') ? 'bg-green-500' : isFieldPending('ine_folio') ? 'bg-yellow-500' : 'bg-blue-500'"
                      ></span>
                      <span class="text-xs text-gray-500">Folio (INE)</span>
                    </div>
                    <p class="font-mono text-sm text-gray-900">{{ application.applicant.ine_folio }}</p>
                  </div>
                  <!-- Fecha Nacimiento -->
                  <VerifiableField
                    label="Fecha Nacimiento"
                    :value="application.applicant.birth_date ? formatDate(application.applicant.birth_date) : null"
                    field-key="birth_date"
                    :verification="getFieldVerification('birth_date')"
                    :is-locked="isFieldLocked('birth_date')"
                    :is-verifying="isVerifyingData"
                    :can-verify="true"
                    @verify="(action) => verifyData('birth_date', action)"
                    @reject="openRejectDataModal('birth_date')"
                    @unreject="openUnverifyModal('birth_date')"
                  />
                  <!-- Género (capturado en el onboarding) -->
                  <div class="group relative">
                    <div class="flex items-center gap-1.5 mb-0.5">
                      <span class="w-2 h-2 rounded-full flex-shrink-0 transition-colors" :class="isFieldRejected('gender') ? 'bg-red-500' : isFieldVerified('gender') ? 'bg-green-500' : isFieldPending('gender') ? 'bg-yellow-500' : application.applicant.gender ? 'bg-blue-500' : 'bg-gray-300'"></span>
                      <span class="text-xs text-gray-500">Género</span>
                    </div>
                    <p class="font-medium text-gray-900">
                      {{ application.applicant.gender === 'M' ? 'Masculino' : application.applicant.gender === 'F' ? 'Femenino' : (application.applicant.gender || '—') }}
                    </p>
                  </div>
                </div>
              </div>
            </div>

            <!-- Address & Employment Row -->
            <div class="grid grid-cols-2 gap-4">
              <!-- Address -->
              <div class="border border-gray-200 rounded-lg">
                <div class="bg-gray-50 px-3 py-1.5 border-b border-gray-200 flex items-center justify-between">
                  <div class="flex items-center gap-2">
                    <span
                      class="w-2 h-2 rounded-full flex-shrink-0"
                      :class="isFieldRejected('address') ? 'bg-red-500' : isFieldVerified('address') ? 'bg-green-500' : isFieldPending('address') ? 'bg-yellow-500' : application.address.street ? 'bg-blue-500' : 'bg-gray-300'"
                    ></span>
                    <h3 class="text-sm font-semibold text-gray-900">Domicilio</h3>
                  </div>
                  <div class="flex items-center gap-0.5">
                    <button
                      v-if="!isFieldVerified('address') && !isFieldRejected('address')"
                      class="flex items-center gap-1 text-xs px-2 py-0.5 rounded transition-colors text-gray-500 hover:bg-green-100 hover:text-green-700"
                      :disabled="isVerifyingData"
                      title="Verificar domicilio"
                      @click="verifyData('address', 'verify')"
                    >
                      <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                      </svg>
                      <span>Verificar</span>
                    </button>
                    <button
                      v-if="isFieldVerified('address')"
                      class="flex items-center gap-1 text-xs px-2 py-0.5 rounded transition-colors bg-green-100 text-green-700 hover:bg-gray-100"
                      :disabled="isVerifyingData"
                      title="Quitar verificación"
                      @click="verifyData('address', 'unverify')"
                    >
                      <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                      </svg>
                      <span>Verificado</span>
                    </button>
                    <button
                      v-if="!isFieldVerified('address') && !isFieldRejected('address')"
                      class="flex items-center gap-1 text-xs px-2 py-0.5 rounded transition-colors text-gray-500 hover:bg-red-100 hover:text-red-700"
                      :disabled="isVerifyingData"
                      title="Rechazar domicilio"
                      @click="openRejectDataModal('address')"
                    >
                      <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                      </svg>
                      <span>Rechazar</span>
                    </button>
                    <button
                      v-if="isFieldRejected('address')"
                      class="flex items-center gap-1 text-xs px-2 py-0.5 rounded transition-colors bg-red-100 text-red-700 hover:bg-gray-100"
                      :disabled="isVerifyingData"
                      title="Quitar rechazo"
                      @click="openUnverifyModal('address')"
                    >
                      <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                      </svg>
                      <span>Rechazado</span>
                    </button>
                  </div>
                </div>
                <div class="p-3">
                  <div v-if="isFieldRejected('address')" class="mb-3 p-2 bg-red-50 border border-red-200 rounded text-xs text-red-700">
                    <span class="font-semibold">⚠ Dato rechazado:</span> {{ getFieldVerification('address')?.rejection_reason }}
                  </div>
                  <div class="grid grid-cols-2 gap-2 text-sm">
                    <div class="col-span-2">
                      <p class="text-xs text-gray-500">Dirección</p>
                      <p class="font-medium text-gray-900">
                        {{ application.address.street || '—' }} {{ application.address.ext_number }}
                        <span v-if="application.address.int_number">, Int. {{ application.address.int_number }}</span>
                      </p>
                    </div>
                    <div>
                      <p class="text-xs text-gray-500">Colonia</p>
                      <p class="font-medium text-gray-900">{{ application.address.neighborhood || '—' }}</p>
                    </div>
                    <div>
                      <p class="text-xs text-gray-500">C.P.</p>
                      <p class="font-medium text-gray-900">{{ application.address.postal_code || '—' }}</p>
                    </div>
                    <div v-if="application.address.city && application.address.city !== application.address.municipality">
                      <p class="text-xs text-gray-500">Ciudad</p>
                      <p class="font-medium text-gray-900">{{ application.address.city }}</p>
                    </div>
                    <div>
                      <p class="text-xs text-gray-500">Municipio/Estado</p>
                      <p class="font-medium text-gray-900">{{ application.address.municipality || '—' }}, {{ application.address.state || '—' }}</p>
                    </div>
                    <div>
                      <p class="text-xs text-gray-500">Vivienda</p>
                      <p class="font-medium text-gray-900">{{ application.address.housing_type_label || getHousingType(application.address.housing_type) || '—' }}</p>
                    </div>
                    <div>
                      <p class="text-xs text-gray-500">Antigüedad en domicilio</p>
                      <p class="font-medium text-gray-900">{{ formatAddressTenure(application.address.years_at_address, application.address.months_at_address) }}</p>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Employment -->
              <div class="border border-gray-200 rounded-lg">
                <div class="bg-gray-50 px-3 py-1.5 border-b border-gray-200 flex items-center justify-between">
                  <div class="flex items-center gap-2">
                    <span
                      class="w-2 h-2 rounded-full flex-shrink-0"
                      :class="isFieldRejected('employment') ? 'bg-red-500' : isFieldVerified('employment') ? 'bg-green-500' : isFieldPending('employment') ? 'bg-yellow-500' : application.employment.employment_type ? 'bg-blue-500' : 'bg-gray-300'"
                    ></span>
                    <h3 class="text-sm font-semibold text-gray-900">Información Laboral</h3>
                  </div>
                  <div class="flex items-center gap-0.5">
                    <button
                      v-if="!isFieldVerified('employment') && !isFieldRejected('employment')"
                      class="flex items-center gap-1 text-xs px-2 py-0.5 rounded transition-colors text-gray-500 hover:bg-green-100 hover:text-green-700"
                      :disabled="isVerifyingData"
                      title="Verificar empleo"
                      @click="verifyData('employment', 'verify')"
                    >
                      <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                      </svg>
                      <span>Verificar</span>
                    </button>
                    <button
                      v-if="isFieldVerified('employment')"
                      class="flex items-center gap-1 text-xs px-2 py-0.5 rounded transition-colors bg-green-100 text-green-700 hover:bg-gray-100"
                      :disabled="isVerifyingData"
                      title="Quitar verificación"
                      @click="verifyData('employment', 'unverify')"
                    >
                      <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                      </svg>
                      <span>Verificado</span>
                    </button>
                    <button
                      v-if="!isFieldVerified('employment') && !isFieldRejected('employment')"
                      class="flex items-center gap-1 text-xs px-2 py-0.5 rounded transition-colors text-gray-500 hover:bg-red-100 hover:text-red-700"
                      :disabled="isVerifyingData"
                      title="Rechazar empleo"
                      @click="openRejectDataModal('employment')"
                    >
                      <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                      </svg>
                      <span>Rechazar</span>
                    </button>
                    <button
                      v-if="isFieldRejected('employment')"
                      class="flex items-center gap-1 text-xs px-2 py-0.5 rounded transition-colors bg-red-100 text-red-700 hover:bg-gray-100"
                      :disabled="isVerifyingData"
                      title="Quitar rechazo"
                      @click="openUnverifyModal('employment')"
                    >
                      <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                      </svg>
                      <span>Rechazado</span>
                    </button>
                  </div>
                </div>
                <div class="p-3">
                  <div v-if="isFieldRejected('employment')" class="mb-3 p-2 bg-red-50 border border-red-200 rounded text-xs text-red-700">
                    <span class="font-semibold">⚠ Dato rechazado:</span> {{ getFieldVerification('employment')?.rejection_reason }}
                  </div>
                  <div class="grid grid-cols-2 gap-2 text-sm">
                    <div>
                      <p class="text-xs text-gray-500">Tipo</p>
                      <p class="font-medium text-gray-900">{{ getEmploymentType(application.employment.employment_type || '') || '—' }}</p>
                    </div>
                    <div>
                      <p class="text-xs text-gray-500">Empresa</p>
                      <p class="font-medium text-gray-900">{{ application.employment.company_name || '—' }}</p>
                    </div>
                    <div>
                      <p class="text-xs text-gray-500">Puesto</p>
                      <p class="font-medium text-gray-900">{{ application.employment.position || '—' }}</p>
                    </div>
                    <div>
                      <p class="text-xs text-gray-500">Antigüedad</p>
                      <p class="font-medium text-gray-900">{{ formatTenureFromMonths(application.employment.seniority_months) }}</p>
                    </div>
                    <div class="col-span-2">
                      <p class="text-xs text-gray-500">Ingreso mensual</p>
                      <p class="font-bold text-gray-900 text-lg">
                        <template v-if="application.employment.monthly_income">
                          {{ formatMoney(application.employment.monthly_income) }}
                          <span v-if="application.employment.income_range_label" class="text-xs font-normal text-gray-500">
                            ({{ application.employment.income_range_label }})
                          </span>
                        </template>
                        <template v-else>{{ application.employment.income_range_label || '—' }}</template>
                      </p>
                    </div>
                    <div class="col-span-2">
                      <p class="text-xs text-gray-500">Préstamos en línea solicitados (onboarding)</p>
                      <p class="font-medium text-gray-900">
                        {{ application.online_loans_count != null
                          ? (application.online_loans_count === 10 ? '10 o más' : application.online_loans_count)
                          : '—' }}
                      </p>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Loan Details -->
            <LoanDetailsSection :loan="application.loan" />

            <!-- Signature - only show if product requires it or if user already signed -->
            <SignatureSection
              v-if="requiresSignature || application.signature?.has_signed"
              :signature="application.signature"
            />

            <!-- Notes -->
            <NotesSection
              :notes="application.notes"
              :is-adding="isAddingNote"
              @add="handleAddNote"
            />
          </div>

          <!-- Documents Tab - Gallery View -->
          <div v-if="activeTab === 'documents'">
            <AdminDocumentGallery
              :application-id="application.id"
              :documents="application.documents"
              :required-documents="normalizedRequiredDocuments"
              :can-review="canReviewDocs"
              @refresh="fetchApplication"
            />
          </div>

          <!-- References Tab -->
          <div v-if="activeTab === 'references'">
            <ReferencesSection
              :references="application.references"
              :can-verify="canVerifyRefs"
              @verify="openVerifyRefModal"
            />
          </div>

          <!-- Bank Accounts Tab -->
          <div v-if="activeTab === 'bank_accounts'">
            <BankAccountsSection
              :accounts="application.bank_accounts"
              :can-verify="canVerifyRefs"
              :can-edit="authStore.isSuperAdmin"
              :application-id="application.id"
              @verify="openBankAccountVerifyModal"
              @unverify="openBankAccountUnverifyModal"
              @refresh="fetchApplication"
            />
          </div>

          <!-- Risks Tab: consolidado de riesgos/validaciones Nubarium -->
          <div v-if="activeTab === 'risks'">
            <RisksSection :application-id="application.id" />
          </div>

          <!-- Activity Tab unificado: eventos de negocio + auditoria + integraciones -->
          <div v-if="activeTab === 'activity'">
            <ActivityTimeline :application-id="application.id" />
          </div>
        </div>
      </div>
    </template>

    <!-- Empty State (no loading, no error, no application) -->
    <div v-else class="flex flex-col items-center justify-center py-12 text-center">
      <svg class="w-16 h-16 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
      </svg>
      <h2 class="text-xl font-semibold text-gray-900 mb-2">Solicitud no encontrada</h2>
      <p class="text-gray-600 mb-4">La solicitud que buscas no existe o no tienes acceso.</p>
      <button
        class="px-4 py-2 text-sm font-medium text-white rounded-lg transition-colors"
        :style="{ backgroundColor: tenantStore.branding?.primary_color || '#7c3aed' }"
        @click="goBack"
      >
        Volver al listado
      </button>
    </div>

    <!-- API Log Detail Modal -->
    <!-- API Log Detail Modal removido: la nueva ActivityTimeline expande
         cada item inline con su metadata JSON; no necesita modal aparte. -->

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

    <!-- Assignment Modal -->
    <div
      v-if="showAssignModal"
      class="fixed inset-0 bg-black/50 flex items-center justify-center z-50"
      @click.self="showAssignModal = false"
    >
      <div class="bg-white rounded-xl p-6 w-full max-w-md mx-4">
        <h3 class="text-lg font-semibold text-gray-900 mb-2">Asignar para Revisión</h3>
        <p class="text-sm text-gray-500 mb-4">Selecciona un analista para revisar esta solicitud</p>

        <div class="space-y-4">
          <div v-if="isLoadingUsers" class="flex justify-center py-8">
            <div class="animate-spin w-8 h-8 border-4 border-primary-600 border-t-transparent rounded-full" />
          </div>

          <div v-else-if="staffUsers.length === 0" class="text-center py-8">
            <svg class="w-12 h-12 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
            <p class="text-gray-500">No hay analistas disponibles</p>
            <p class="text-sm text-gray-400 mt-1">Crea un usuario con rol Analista en la sección de Usuarios</p>
          </div>

          <template v-else>
            <!-- Analysts list -->
            <div class="space-y-2">
              <div
                v-for="user in staffUsers"
                :key="user.id"
                class="flex items-center justify-between p-3 rounded-lg cursor-pointer transition-colors"
                :class="selectedUserId === user.id ? 'bg-primary-50 border border-primary-200' : 'bg-gray-50 hover:bg-gray-100'"
                @click="selectedUserId = user.id"
              >
                <div class="flex items-center gap-3">
                  <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center text-sm font-medium text-blue-700">
                    {{ user.name.charAt(0).toUpperCase() }}
                  </div>
                  <div>
                    <p class="font-medium text-gray-900 text-sm">{{ user.name }}</p>
                    <p class="text-xs text-gray-500">{{ user.email }}</p>
                  </div>
                </div>
                <svg v-if="selectedUserId === user.id" class="w-5 h-5 text-primary-600" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
              </div>
            </div>
          </template>
        </div>

        <div class="flex gap-3 mt-6">
          <AppButton
            variant="outline"
            class="flex-1"
            @click="showAssignModal = false"
          >
            Cancelar
          </AppButton>
          <AppButton
            variant="primary"
            class="flex-1"
            :loading="isAssigning"
            :disabled="!selectedUserId"
            @click="assignApplication"
          >
            Asignar
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

    <!-- Data Rejection Modal -->
    <ConfirmModal
      v-model:show="showRejectDataModal"
      title="Rechazar Dato"
      :subtitle="rejectDataField ? getFieldLabel(rejectDataField) : ''"
      icon="x"
      icon-color="red"
      comment-label="Motivo de rechazo"
      comment-placeholder="Explica por qué este dato es incorrecto y qué debe corregir el solicitante..."
      comment-required
      :comment-rows="4"
      confirm-text="Rechazar"
      confirm-color="red"
      :loading="isVerifyingData"
      @confirm="confirmRejectData"
    />

    <!-- Unverify/Unreject Data Modal -->
    <ConfirmModal
      v-model:show="showUnverifyModal"
      title="Remover Verificación/Rechazo"
      :subtitle="unverifyField ? getFieldLabel(unverifyField) : ''"
      icon="undo"
      icon-color="yellow"
      comment-label="Motivo"
      comment-placeholder="Explica por qué se está removiendo la verificación o rechazo de este dato..."
      comment-required
      :comment-rows="4"
      confirm-text="Confirmar"
      confirm-color="blue"
      :loading="isVerifyingData"
      @confirm="confirmUnverify"
    />

    <!-- Editar Teléfono (PRUEBAS, solo super admin) -->
    <ConfirmModal
      v-model:show="showEditPhoneModal"
      title="Editar teléfono"
      subtitle="Uso de pruebas: libera el número anterior para volver a registrarlo"
      icon="info"
      icon-color="blue"
      comment-label="Nuevo teléfono (10 dígitos)"
      comment-placeholder="Ej. 5512345678"
      comment-required
      :comment-rows="1"
      confirm-text="Guardar"
      confirm-color="blue"
      :loading="isEditingPhone"
      @confirm="confirmEditPhone"
    />

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
          <p class="text-sm text-gray-500">{{ selectedReference.relationship }} · {{ formatPhone(selectedReference.phone) }}</p>
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
    <ConfirmModal
      v-model:show="showDocApproveModal"
      title="Aprobar Documento"
      :subtitle="docToApprove?.name"
      message="¿Confirmas que el documento es válido y cumple con los requisitos?"
      icon="check"
      icon-color="green"
      confirm-text="Aprobar"
      confirm-color="green"
      :loading="isApprovingDoc"
      @confirm="confirmApproveDocument"
    />

    <!-- Bank Account Verify Modal -->
    <ConfirmModal
      v-model:show="showBankAccountVerifyModal"
      title="Verificar Cuenta Bancaria"
      :subtitle="selectedBankAccount?.bank_name"
      :message="`¿Confirmas que la cuenta CLABE ${selectedBankAccount?.clabe} pertenece al solicitante y es válida?`"
      icon="check"
      icon-color="green"
      confirm-text="Verificar"
      confirm-color="green"
      :loading="isVerifyingBankAccount"
      @confirm="verifyBankAccount"
    />

    <!-- Bank Account Unverify Modal -->
    <ConfirmModal
      v-model:show="showBankAccountUnverifyModal"
      title="Quitar Verificación"
      :subtitle="selectedBankAccount?.bank_name"
      :message="`¿Confirmas que deseas quitar la verificación de la cuenta CLABE ${selectedBankAccount?.clabe}?`"
      icon="undo"
      icon-color="yellow"
      confirm-text="Quitar verificación"
      confirm-color="yellow"
      :loading="isUnverifyingBankAccount"
      @confirm="unverifyBankAccount"
    />

    <!-- Document Viewer Modal -->
    <div
      v-if="showDocViewerModal"
      class="fixed inset-0 bg-black/80 flex items-center justify-center z-50"
      @click.self="showDocViewerModal = false"
    >
      <div class="relative w-full max-w-4xl mx-4 max-h-[90vh] bg-white rounded-xl overflow-hidden">
        <!-- Header -->
        <div class="flex items-center justify-between p-4 border-b border-gray-200 bg-gray-50">
          <h3 class="text-lg font-semibold text-gray-900 truncate">{{ docViewerName }}</h3>
          <div class="flex items-center gap-2">
            <a
              :href="docViewerUrl"
              target="_blank"
              class="p-2 text-gray-500 hover:text-gray-700 transition-colors"
              title="Abrir en nueva pestaña"
            >
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
              </svg>
            </a>
            <button
              class="p-2 text-gray-500 hover:text-gray-700 transition-colors"
              title="Cerrar"
              @click="showDocViewerModal = false"
            >
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
        </div>

        <!-- Content -->
        <div class="p-4 overflow-auto" style="max-height: calc(90vh - 80px);">
          <!-- Image viewer -->
          <img
            v-if="docViewerMimeType.startsWith('image/')"
            :src="docViewerUrl"
            :alt="docViewerName"
            class="max-w-full h-auto mx-auto rounded-lg shadow-lg"
          />

          <!-- PDF viewer fallback (iframe) -->
          <iframe
            v-else-if="docViewerMimeType === 'application/pdf'"
            :src="docViewerUrl"
            class="w-full h-[70vh] rounded-lg"
            frameborder="0"
          />

          <!-- Unknown type -->
          <div v-else class="text-center py-12">
            <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <p class="text-gray-500 mb-4">Este tipo de archivo no se puede previsualizar</p>
            <a
              :href="docViewerUrl"
              target="_blank"
              class="inline-flex items-center gap-2 px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
              </svg>
              Descargar archivo
            </a>
          </div>
        </div>
      </div>
    </div>

    <!-- Selfie Viewer Modal -->
    <Teleport to="body">
      <Transition
        enter-active-class="transition-opacity duration-200"
        leave-active-class="transition-opacity duration-200"
        enter-from-class="opacity-0"
        leave-to-class="opacity-0"
      >
        <div
          v-if="showSelfieViewer && selfieUrl"
          class="fixed inset-0 z-50 bg-black/90 flex flex-col"
          @click="showSelfieViewer = false"
        >
          <!-- Header -->
          <div class="flex items-center justify-between px-4 py-3 text-white">
            <div class="flex items-center gap-3">
              <h3 class="font-medium">Foto del Solicitante</h3>
              <span
                class="px-2 py-0.5 rounded-full text-xs font-medium"
                :class="{
                  'bg-green-100 text-green-800': selfieStatus === 'APPROVED',
                  'bg-red-100 text-red-800': selfieStatus === 'REJECTED',
                  'bg-yellow-100 text-yellow-800': selfieStatus === 'PENDING'
                }"
              >
                {{ selfieStatus === 'APPROVED' ? 'Aprobada' : selfieStatus === 'REJECTED' ? 'Rechazada' : 'Pendiente' }}
              </span>
            </div>
            <button
              class="p-2 bg-white/10 hover:bg-white/20 rounded-lg transition-colors"
              @click="showSelfieViewer = false"
            >
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <!-- Image -->
          <div class="flex-1 flex items-center justify-center p-4 overflow-auto" @click.stop>
            <img
              :src="selfieUrl"
              alt="Foto del solicitante"
              class="max-w-full max-h-full object-contain rounded-lg"
            />
          </div>

          <!-- Footer with actions -->
          <div v-if="selfieStatus === 'PENDING'" class="px-4 py-4 pb-safe flex justify-center gap-4">
            <button
              class="flex items-center gap-2 bg-green-500 text-white px-6 py-3 rounded-full shadow-lg hover:bg-green-600 active:bg-green-700 transition-colors"
              @click.stop="showSelfieViewer = false; showSelfieApproveModal = true"
            >
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
              </svg>
              <span class="font-medium">Aprobar</span>
            </button>
            <button
              class="flex items-center gap-2 bg-red-500 text-white px-6 py-3 rounded-full shadow-lg hover:bg-red-600 active:bg-red-700 transition-colors"
              @click.stop="showSelfieViewer = false; showSelfieRejectModal = true"
            >
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
              <span class="font-medium">Rechazar</span>
            </button>
          </div>

          <!-- APPROVED: badge + unapprove button -->
          <div v-else-if="selfieStatus === 'APPROVED'" class="px-4 py-4 pb-safe flex justify-center gap-4">
            <div class="flex items-center gap-2 bg-green-500 text-white px-4 py-2 rounded-full shadow-lg">
              <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
              </svg>
              <span class="font-medium">
                {{ selfieIsKycVerified ? 'Verificada por KYC' : 'Aprobada' }}
                <template v-if="selfieIsKycVerified && selfieFaceMatchScore !== null">
                  ({{ selfieFaceMatchScore.toFixed(0) }}% match)
                </template>
              </span>
            </div>
            <!-- Only show unapprove button if NOT verified by KYC face match -->
            <button
              v-if="!selfieIsKycVerified"
              class="flex items-center gap-2 bg-yellow-500 text-white px-4 py-2 rounded-full shadow-lg hover:bg-yellow-600 active:bg-yellow-700 transition-colors"
              @click.stop="showSelfieViewer = false; showSelfieUnapproveModal = true"
            >
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" />
              </svg>
              <span class="font-medium">Desaprobar</span>
            </button>
          </div>

          <!-- REJECTED: badge + unreject button (no direct approve) -->
          <div v-else-if="selfieStatus === 'REJECTED'" class="px-4 py-4 pb-safe flex justify-center gap-4">
            <div class="flex items-center gap-2 bg-red-500 text-white px-4 py-2 rounded-full shadow-lg">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
              <span class="font-medium">Rechazada</span>
            </div>
            <button
              class="flex items-center gap-2 bg-yellow-500 text-white px-4 py-2 rounded-full shadow-lg hover:bg-yellow-600 active:bg-yellow-700 transition-colors"
              @click.stop="showSelfieViewer = false; showSelfieUnrejectModal = true"
            >
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" />
              </svg>
              <span class="font-medium">Quitar Rechazo</span>
            </button>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- Selfie Approve Modal -->
    <ConfirmModal
      v-model:show="showSelfieApproveModal"
      title="Aprobar Selfie"
      subtitle="Foto del solicitante"
      message="¿Confirmas que la foto es válida y corresponde al solicitante?"
      icon="check"
      icon-color="green"
      confirm-text="Aprobar"
      confirm-color="green"
      :loading="isApprovingSelfie"
      @confirm="approveSelfie"
    />

    <!-- Selfie Reject Modal -->
    <ConfirmModal
      v-model:show="showSelfieRejectModal"
      title="Rechazar Selfie"
      subtitle="Foto del solicitante"
      icon="x"
      icon-color="red"
      select-label="Motivo del rechazo"
      :select-options="docRejectReasons"
      select-required
      comment-label="Comentario adicional"
      comment-placeholder="Explica qué debe corregir el solicitante..."
      confirm-text="Rechazar"
      confirm-color="red"
      :loading="isRejectingSelfie"
      @confirm="rejectSelfie"
    />

    <!-- Selfie Unapprove Modal -->
    <ConfirmModal
      v-model:show="showSelfieUnapproveModal"
      title="Desaprobar Selfie"
      subtitle="Volver a estado pendiente"
      message="La foto volverá a estado pendiente y podrá ser revisada nuevamente."
      icon="undo"
      icon-color="yellow"
      confirm-text="Desaprobar"
      confirm-color="yellow"
      :loading="isUnapprovingSelfie"
      @confirm="unapproveSelfie"
    />

    <!-- Selfie Unreject Modal -->
    <ConfirmModal
      v-model:show="showSelfieUnrejectModal"
      title="Quitar Rechazo"
      subtitle="Volver a estado pendiente"
      message="La foto volverá a estado pendiente y podrá ser revisada nuevamente."
      icon="undo"
      icon-color="yellow"
      confirm-text="Quitar Rechazo"
      confirm-color="yellow"
      :loading="isUnrejectingSelfie"
      @confirm="unrejectSelfie"
    />
  </div>
</template>
