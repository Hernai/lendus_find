<script setup lang="ts">
import { ref, computed, onMounted, onBeforeMount, onUnmounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AdminDocumentGallery from '@/modules/admin/components/AdminDocumentGallery.vue'
import ConfirmModal from '@/modules/admin/components/ConfirmModal.vue'
import ActivityTimeline from '@/modules/admin/components/application-detail/ActivityTimeline.vue'
import {
  ReferencesSection,
  BankAccountsSection,
  NotesSection,
  RisksSection,
  LoanDetailsSection,
  LoanSummaryCards,
  CompletenessCard,
  SignatureSection,
  ApplicationNotFoundState,
  AddressSection,
  EmploymentSection,
  LeaseInfoSection,
  TabsBar,
  ApplicantDataSection,
  IneVerificationModal,
  StatusChangeModal,
  AssignAnalystModal,
  ReferenceVerifyModal,
  CounterOfferModal,
  ApplicationDetailHeader,
  SelfieViewerModal,
} from '@/modules/admin/components/application-detail'
import { staff } from '@/modules/admin/services'
import type { Application, Reference, BankAccount, VerifiableFieldKey, StaffUser } from './applicationDetail.types'
import { mapApplicationDetail, isForeignNationality, requiredDocTypesFor } from './mapApplicationDetail'
import { useFieldVerification } from '@/modules/admin/composables/useFieldVerification'
import { useWebSocket, useToast, useDocumentTypes } from '@/composables'
import { useTenantStore } from '@/stores/tenant'
import { useAuthStore } from '@/stores/auth'
import { logger } from '@/utils/logger'
import { getStateNameFromCurp } from '@/utils/validators'
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
// newStatus/statusNote se movieron a StatusChangeModal (estado de formulario local).
const isUpdatingStatus = ref(false)

// Counter-offer state
const showCounterOfferModal = ref(false)
const isSubmittingCounterOffer = ref(false)
// counterOffer y counterOfferCalculation (form + amortización) se movieron a CounterOfferModal.

// docRejectReasons se conserva: lo consume el modal de rechazo de SELFIE (más abajo).
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
// refVerifyResult/refVerifyNotes se movieron a ReferenceVerifyModal (form local).
const isVerifyingRef = ref(false)

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
// StaffUser se importa de applicationDetail.types (compartido con AssignAnalystModal).

const showAssignModal = ref(false)
const staffUsers = ref<StaffUser[]>([])
// selectedUserId se movió a AssignAnalystModal (estado de formulario local).
const isAssigning = ref(false)
const isLoadingUsers = ref(false)

// Calculated values for counter-offer
// counterOfferCalculation (amortización en vivo) se movió a CounterOfferModal.

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
// switchTab eliminado: el nav se movió a TabsBar y activeTab se controla con
// v-model:active-tab (Vue actualiza el ref directamente).

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

onMounted(async () => {
  await fetchApplication()
  await loadSelfie()
})

// getEmploymentType/getHousingType (dependen de tenantStore) y formatAddressTenure/
// formatTenureFromMonths (puras) se movieron a EmploymentSection/AddressSection,
// sus únicos consumidores.

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
    showStatusModal.value = true
  }
}

const updateStatus = async (payload: { status: string; notes?: string }) => {
  if (!application.value || !payload.status) return

  isUpdatingStatus.value = true

  try {
    // Make actual API call to update status
    await staff.application.changeStatus(application.value.id, {
      status: payload.status as import('@/types/v2').V2ApplicationStatus,
      notes: payload.notes
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

const assignApplication = async (userId: string) => {
  if (!application.value || !userId) return

  isAssigning.value = true

  try {
    await staff.application.assign(application.value.id, {
      user_id: userId
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

// La aprobación/rechazo/visualización de documentos la maneja por completo
// AdminDocumentGallery (visor, aprobar/rechazar/desaprobar + su propio estado);
// el padre solo escucha @refresh. Se eliminó el flujo de docs duplicado/muerto.

// Reference verification
const openVerifyRefModal = (ref: Reference) => {
  selectedReference.value = ref
  showVerifyRefModal.value = true
}

const confirmVerifyReference = async (payload: { result: 'VERIFIED' | 'NOT_VERIFIED' | 'NO_ANSWER'; notes: string }) => {
  if (!selectedReference.value || !application.value) return

  isVerifyingRef.value = true

  try {
    await staff.application.verifyReference(application.value.id, selectedReference.value.id, {
      result: payload.result,
      notes: payload.notes || undefined
    })

    selectedReference.value.verified = payload.result === 'VERIFIED'
    selectedReference.value.verification_result = payload.result
    selectedReference.value.verification_notes = payload.notes

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

// Verify data (field-level verification). El tipo VerifiableFieldKey se importa de
// applicationDetail.types (compartido con ApplicantDataSection).
const isVerifyingData = ref(false)

// Reject data modal state
const showRejectDataModal = ref(false)
const rejectDataField = ref<VerifiableFieldKey | null>(null)

// Unverify modal state (for removing verification/rejection)
const showUnverifyModal = ref(false)
const unverifyField = ref<VerifiableFieldKey | null>(null)

// Helpers de LECTURA de verificación por campo, del composable compartido
// useFieldVerification (los mismos que consumen ApplicantDataSection/Address/Employment).
// getFieldLabel se sigue usando aquí en los subtítulos de los modales reject/unverify.
const {
  isFieldVerified,
  isFieldRejected,
  isFieldPending,
  getFieldVerification,
  getFieldLabel,
} = useFieldVerification(() => application.value)

const verifyData = async (field: VerifiableFieldKey, action: 'verify' | 'reject' | 'unverify', reason?: string) => {
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
const openRejectDataModal = (field: VerifiableFieldKey) => {
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
const openUnverifyModal = (field: VerifiableFieldKey) => {
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
    // El pre-fill desde el crédito vive ahora en CounterOfferModal (watch sobre show).
    showCounterOfferModal.value = true
  }
}

const submitCounterOffer = async (payload: { amount: number; term_months: number; interest_rate: number; payment_frequency: string; reason: string }) => {
  if (!application.value) return

  isSubmittingCounterOffer.value = true

  try {
    await staff.application.createCounterOffer(application.value.id, {
      amount: payload.amount,
      term_months: payload.term_months,
      interest_rate: payload.interest_rate,
      reason: payload.reason
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
      <ApplicationDetailHeader
        :application="application"
        :primary-color="tenantStore.branding?.primary_color || '#7c3aed'"
        :can-approve-reject="canApproveReject"
        :can-assign="canAssign"
        :can-change-status="canChangeStatus"
        :can-review-docs="canReviewDocs"
        :selfie-url="selfieUrl"
        :selfie-status="selfieStatus"
        :is-loading-selfie="isLoadingSelfie"
        :selfie-is-kyc-verified="selfieIsKycVerified"
        :selfie-face-match-score="selfieFaceMatchScore"
        @back="goBack"
        @open-counter-offer="openCounterOfferModal"
        @open-assign="openAssignModal"
        @open-status="openStatusModal"
        @view-selfie="showSelfieViewer = true"
        @approve-selfie="showSelfieApproveModal = true"
        @reject-selfie="showSelfieRejectModal = true"
        @unapprove-selfie="showSelfieUnapproveModal = true"
        @unreject-selfie="showSelfieUnrejectModal = true"
      />

      <!-- Avance del Expediente -->
      <CompletenessCard
        :percent="completenessPercent"
        :color="completenessColor"
        :items="completenessItems"
      />

      <!-- Tabs -->
      <div class="bg-white rounded-xl shadow-sm mb-6">
        <TabsBar :tabs="tabs" v-model:active-tab="activeTab" />

        <!-- Tab Content -->
        <div class="p-6">
          <!-- General Tab -->
          <div v-if="activeTab === 'general'" class="space-y-4">
            <!-- Modal: Verificación de INE (confirmado cliente vs OCR vs RENAPO) -->
            <IneVerificationModal v-model:show="showIneVerification" :comparison="ineComparison" />

            <!-- Summary Cards -->
            <LoanSummaryCards :loan="application.loan" />

            <!-- Datos del Solicitante -->
            <ApplicantDataSection
              :application="application"
              :is-foreigner="isForeigner"
              :birth-state-display="birthStateDisplay"
              :ine-comparison="ineComparison"
              :is-verifying-data="isVerifyingData"
              :reverifying-ine="reverifyingIne"
              :is-editing-phone="isEditingPhone"
              :can-review-docs="canReviewDocs"
              :is-super-admin="authStore.isSuperAdmin"
              @verify="(field, action) => verifyData(field, action)"
              @reject="(field) => openRejectDataModal(field)"
              @unreject="(field) => openUnverifyModal(field)"
              @edit-phone="openEditPhoneModal"
              @reverify-ine="reverifyIne"
              @show-ine-detail="showIneVerification = true"
            />

            <!-- Address & Employment Row -->
            <div class="grid grid-cols-2 gap-4">
              <!-- Address -->
              <AddressSection
                :address="application.address"
                :is-verified="isFieldVerified('address')"
                :is-rejected="isFieldRejected('address')"
                :is-pending="isFieldPending('address')"
                :is-verifying="isVerifyingData"
                :rejection-reason="getFieldVerification('address')?.rejection_reason"
                @verify="(action) => verifyData('address', action)"
                @reject="openRejectDataModal('address')"
                @unreject="openUnverifyModal('address')"
              />

              <!-- Employment -->
              <EmploymentSection
                :employment="application.employment"
                :online-loans-count="application.online_loans_count ?? null"
                :is-verified="isFieldVerified('employment')"
                :is-rejected="isFieldRejected('employment')"
                :is-pending="isFieldPending('employment')"
                :is-verifying="isVerifyingData"
                :rejection-reason="getFieldVerification('employment')?.rejection_reason"
                @verify="(action) => verifyData('employment', action)"
                @reject="openRejectDataModal('employment')"
                @unreject="openUnverifyModal('employment')"
              />

              <!-- Arrendamiento (solo solicitudes de productos ARRENDAMIENTO) -->
              <LeaseInfoSection :lease-info="application.lease_info" />
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
    <ApplicationNotFoundState
      v-else
      :primary-color="tenantStore.branding?.primary_color || '#7c3aed'"
      @back="goBack"
    />

    <!-- API Log Detail Modal -->
    <!-- API Log Detail Modal removido: la nueva ActivityTimeline expande
         cada item inline con su metadata JSON; no necesita modal aparte. -->

    <!-- Status Change Modal -->
    <StatusChangeModal
      v-model:show="showStatusModal"
      :status-options="statusOptions"
      :current-status="application?.status ?? ''"
      :loading="isUpdatingStatus"
      @confirm="updateStatus"
    />

    <!-- Assignment Modal -->
    <AssignAnalystModal
      v-model:show="showAssignModal"
      :staff-users="staffUsers"
      :is-loading-users="isLoadingUsers"
      :is-assigning="isAssigning"
      @confirm="assignApplication"
    />

    <!-- Counter-Offer Modal -->
    <CounterOfferModal
      v-if="application"
      v-model:show="showCounterOfferModal"
      :loan="application.loan"
      :is-submitting="isSubmittingCounterOffer"
      @submit="submitCounterOffer"
    />

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
    <ReferenceVerifyModal
      v-model:show="showVerifyRefModal"
      :reference="selectedReference"
      :is-verifying="isVerifyingRef"
      @confirm="confirmVerifyReference"
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

    <!-- Selfie Viewer Modal -->
    <SelfieViewerModal
      v-model:show="showSelfieViewer"
      :selfie-url="selfieUrl"
      :selfie-status="selfieStatus"
      :selfie-is-kyc-verified="selfieIsKycVerified"
      :selfie-face-match-score="selfieFaceMatchScore"
      @approve="showSelfieViewer = false; showSelfieApproveModal = true"
      @reject="showSelfieViewer = false; showSelfieRejectModal = true"
      @unapprove="showSelfieViewer = false; showSelfieUnapproveModal = true"
      @unreject="showSelfieViewer = false; showSelfieUnrejectModal = true"
    />

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
