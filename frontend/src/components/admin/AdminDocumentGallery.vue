<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { api } from '@/services/api'
import ConfirmModal from './ConfirmModal.vue'

interface Document {
  id: string
  type: string
  name: string
  status: 'PENDING' | 'APPROVED' | 'REJECTED'
  rejection_reason?: string
  rejection_comment?: string
  uploaded_at?: string
  reviewed_at?: string
  mime_type?: string
}

const props = defineProps<{
  applicationId: string
  documents: Document[]
  requiredDocuments: string[]
}>()

const emit = defineEmits<{
  'document-approved': [doc: Document]
  'document-rejected': [doc: Document]
  'refresh': []
}>()

// Document type labels
const docTypeLabels: Record<string, string> = {
  'INE_FRONT': 'INE (Frente)',
  'INE_BACK': 'INE (Reverso)',
  'PROOF_ADDRESS': 'Comprobante de Domicilio',
  'PROOF_INCOME': 'Comprobante de Ingresos',
  'BANK_STATEMENT': 'Estado de Cuenta',
  'RFC_CONSTANCIA': 'Constancia RFC',
  'SIGNATURE': 'Firma',
  'SELFIE': 'Foto de Perfil',
  'PAYSLIP_1': 'Recibo de Nómina 1',
  'PAYSLIP_2': 'Recibo de Nómina 2',
  'PAYSLIP_3': 'Recibo de Nómina 3',
  'VEHICLE_INVOICE': 'Factura del Vehículo'
}

// State
const documentThumbnails = ref<Record<string, string>>({})
const loadingThumbnails = ref<Record<string, boolean>>({})
const selectedDocument = ref<Document | null>(null)
const selectedDocumentUrl = ref<string | null>(null)
const showViewer = ref(false)
const isLoadingViewer = ref(false)

// Modals
const showApproveModal = ref(false)
const showRejectModal = ref(false)
const showUnapproveModal = ref(false)
const showUnrejectModal = ref(false)
const docToAction = ref<Document | null>(null)
const isApproving = ref(false)
const isRejecting = ref(false)
const isUnapproving = ref(false)
const isUnrejecting = ref(false)

// Rejection reasons (values must match backend RejectionReason enum)
const rejectionReasons = [
  { value: 'ILLEGIBLE', label: 'Documento ilegible' },
  { value: 'EXPIRED', label: 'Documento vencido' },
  { value: 'WRONG_DOC', label: 'Documento incorrecto' },
  { value: 'INCOMPLETE', label: 'Documento incompleto' },
  { value: 'TAMPERED', label: 'Documento alterado' },
  { value: 'MISMATCH', label: 'No coincide con datos' },
  { value: 'OTHER', label: 'Otro' }
]

// Computed
const missingDocuments = computed(() => {
  const uploadedTypes = new Set(props.documents.map(d => d.type))
  return props.requiredDocuments
    .filter(type => !uploadedTypes.has(type))
    .map(type => ({
      type,
      name: docTypeLabels[type] || type,
      missing: true
    }))
})

const getDocTypeName = (type: string) => docTypeLabels[type] || type

// Get rejection reason label from value
const getRejectionReasonLabel = (value?: string): string => {
  if (!value) return ''
  const reason = rejectionReasons.find(r => r.value === value)
  return reason?.label || value
}

const isImage = (mimeType?: string) => mimeType?.startsWith('image/')
const isPdf = (mimeType?: string) => mimeType === 'application/pdf'
// SELFIE, SIGNATURE are always images even if mime_type is not set
const isImageType = (doc: Document) => isImage(doc.mime_type) || ['SELFIE', 'SIGNATURE'].includes(doc.type)

// Load thumbnail for a document (fetch as blob with auth headers)
const loadThumbnail = async (doc: Document) => {
  if (documentThumbnails.value[doc.id] || loadingThumbnails.value[doc.id]) return
  if (!isImageType(doc)) return

  loadingThumbnails.value[doc.id] = true

  try {
    // Fetch the image as blob with auth headers
    const response = await api.get(
      `/admin/applications/${props.applicationId}/documents/${doc.id}/download`,
      { responseType: 'blob' }
    )
    const blob = new Blob([response.data], { type: doc.mime_type || 'image/jpeg' })
    documentThumbnails.value[doc.id] = URL.createObjectURL(blob)
  } catch (e) {
    console.error('Failed to load thumbnail:', e)
  } finally {
    loadingThumbnails.value[doc.id] = false
  }
}

// Load thumbnails for all image documents - staggered loading
const loadAllThumbnails = async () => {
  const imageDocs = props.documents.filter(doc => isImageType(doc))

  for (let i = 0; i < imageDocs.length; i++) {
    // Small delay between each load to stagger the rendering
    if (i > 0) {
      await new Promise(resolve => setTimeout(resolve, 150))
    }
    loadThumbnail(imageDocs[i])
  }
}

// Load on mount and when documents change
onMounted(() => {
  if (props.documents.length > 0) {
    loadAllThumbnails()
  }
})

watch(() => props.documents, (newDocs) => {
  if (newDocs.length > 0) {
    loadAllThumbnails()
  }
}, { deep: true })

// Only image documents for carousel navigation
const imageDocuments = computed(() =>
  props.documents.filter(d => !isPdf(d.mime_type))
)

// Current document index in carousel
const currentDocIndex = computed(() => {
  if (!selectedDocument.value) return -1
  return imageDocuments.value.findIndex(d => d.id === selectedDocument.value?.id)
})

// Check if can navigate
const canGoPrev = computed(() => currentDocIndex.value > 0)
const canGoNext = computed(() => currentDocIndex.value < imageDocuments.value.length - 1)

// View document in full size
const viewDocument = async (doc: Document) => {
  selectedDocument.value = doc
  isLoadingViewer.value = true

  try {
    if (isPdf(doc.mime_type)) {
      // For PDFs, get the URL and open in new tab
      const response = await api.get<{ url: string }>(
        `/admin/applications/${props.applicationId}/documents/${doc.id}/url`
      )
      window.open(response.data.url, '_blank')
    } else {
      // For images, use the cached thumbnail or fetch as blob
      if (documentThumbnails.value[doc.id]) {
        selectedDocumentUrl.value = documentThumbnails.value[doc.id]
      } else {
        const response = await api.get(
          `/admin/applications/${props.applicationId}/documents/${doc.id}/download`,
          { responseType: 'blob' }
        )
        const blob = new Blob([response.data], { type: doc.mime_type || 'image/jpeg' })
        selectedDocumentUrl.value = URL.createObjectURL(blob)
      }
      showViewer.value = true
    }
  } catch (e) {
    console.error('Failed to load document:', e)
    alert('Error al cargar el documento')
  } finally {
    isLoadingViewer.value = false
  }
}

// Navigate to specific document in carousel
const goToDocument = async (doc: Document) => {
  if (isPdf(doc.mime_type)) return // Skip PDFs

  selectedDocument.value = doc

  // Use cached thumbnail or fetch
  if (documentThumbnails.value[doc.id]) {
    selectedDocumentUrl.value = documentThumbnails.value[doc.id]
  } else {
    isLoadingViewer.value = true
    try {
      const response = await api.get(
        `/admin/applications/${props.applicationId}/documents/${doc.id}/download`,
        { responseType: 'blob' }
      )
      const blob = new Blob([response.data], { type: doc.mime_type || 'image/jpeg' })
      selectedDocumentUrl.value = URL.createObjectURL(blob)
    } catch (e) {
      console.error('Failed to load document:', e)
    } finally {
      isLoadingViewer.value = false
    }
  }
}

// Navigate to previous document
const prevDocument = () => {
  if (!canGoPrev.value) return
  goToDocument(imageDocuments.value[currentDocIndex.value - 1])
}

// Navigate to next document
const nextDocument = () => {
  if (!canGoNext.value) return
  goToDocument(imageDocuments.value[currentDocIndex.value + 1])
}

// Open approve modal
const openApproveModal = (doc: Document) => {
  docToAction.value = doc
  showApproveModal.value = true
}

// Confirm approve
const confirmApprove = async () => {
  if (!docToAction.value) return

  isApproving.value = true

  try {
    await api.put(`/admin/applications/${props.applicationId}/documents/${docToAction.value.id}/approve`)
    docToAction.value.status = 'APPROVED'
    emit('document-approved', docToAction.value)
    emit('refresh')
    showApproveModal.value = false
  } catch (e) {
    console.error('Failed to approve document:', e)
    alert('Error al aprobar el documento')
  } finally {
    isApproving.value = false
  }
}

// Open reject modal
const openRejectModal = (doc: Document) => {
  docToAction.value = doc
  showRejectModal.value = true
}

// Confirm reject
const confirmReject = async (data: { selectValue?: string; comment?: string }) => {
  if (!docToAction.value || !data.selectValue) return

  isRejecting.value = true

  try {
    await api.put(`/admin/applications/${props.applicationId}/documents/${docToAction.value.id}/reject`, {
      reason: data.selectValue,
      comment: data.comment || null
    })
    docToAction.value.status = 'REJECTED'
    docToAction.value.rejection_reason = data.selectValue
    docToAction.value.rejection_comment = data.comment
    emit('document-rejected', docToAction.value)
    emit('refresh')
    showRejectModal.value = false
  } catch (e) {
    console.error('Failed to reject document:', e)
    alert('Error al rechazar el documento')
  } finally {
    isRejecting.value = false
  }
}

// Close viewer and optionally open approve/reject
const viewerApprove = () => {
  if (selectedDocument.value) {
    showViewer.value = false
    openApproveModal(selectedDocument.value)
  }
}

const viewerReject = () => {
  if (selectedDocument.value) {
    showViewer.value = false
    openRejectModal(selectedDocument.value)
  }
}

// Open unapprove modal (set approved doc back to pending)
const openUnapproveModal = (doc: Document) => {
  docToAction.value = doc
  showUnapproveModal.value = true
}

// Confirm unapprove
const confirmUnapprove = async () => {
  if (!docToAction.value) return

  isUnapproving.value = true

  try {
    await api.put(`/admin/applications/${props.applicationId}/documents/${docToAction.value.id}/unapprove`)
    docToAction.value.status = 'PENDING'
    emit('refresh')
    showUnapproveModal.value = false
  } catch (e) {
    console.error('Failed to unapprove document:', e)
    alert('Error al desaprobar el documento')
  } finally {
    isUnapproving.value = false
  }
}

// Open unreject modal (set rejected doc back to pending)
const openUnrejectModal = (doc: Document) => {
  docToAction.value = doc
  showUnrejectModal.value = true
}

// Confirm unreject
const confirmUnreject = async () => {
  if (!docToAction.value) return

  isUnrejecting.value = true

  try {
    await api.put(`/admin/applications/${props.applicationId}/documents/${docToAction.value.id}/unapprove`)
    docToAction.value.status = 'PENDING'
    docToAction.value.rejection_reason = undefined
    docToAction.value.rejection_comment = undefined
    emit('refresh')
    showUnrejectModal.value = false
  } catch (e) {
    console.error('Failed to unreject document:', e)
    alert('Error al desrechazar el documento')
  } finally {
    isUnrejecting.value = false
  }
}

// Viewer actions for unapprove/unreject
const viewerUnapprove = () => {
  if (selectedDocument.value) {
    showViewer.value = false
    openUnapproveModal(selectedDocument.value)
  }
}

const viewerUnreject = () => {
  if (selectedDocument.value) {
    showViewer.value = false
    openUnrejectModal(selectedDocument.value)
  }
}

// Status badge colors
const getStatusBadge = (status: string) => {
  switch (status) {
    case 'APPROVED':
      return { label: 'Aprobado', class: 'bg-green-100 text-green-800' }
    case 'REJECTED':
      return { label: 'Rechazado', class: 'bg-red-100 text-red-800' }
    default:
      return { label: 'Pendiente', class: 'bg-yellow-100 text-yellow-800' }
  }
}
</script>

<template>
  <div>
    <!-- Document Gallery Grid -->
    <div class="bg-white border border-gray-200 rounded-xl p-4">
      <h3 class="text-sm font-semibold text-gray-900 mb-4 flex items-center gap-2">
        <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
        </svg>
        Documentos
        <span class="text-xs font-normal text-gray-500 ml-2">
          ({{ documents.filter(d => d.status === 'APPROVED').length }}/{{ requiredDocuments.length }} aprobados)
        </span>
      </h3>

      <!-- Stats -->
      <div class="flex flex-wrap gap-3 mb-4 text-xs">
        <span class="px-2 py-1 bg-gray-100 rounded-full text-gray-600">
          Subidos: {{ documents.length }}
        </span>
        <span class="px-2 py-1 bg-green-100 rounded-full text-green-700">
          Aprobados: {{ documents.filter(d => d.status === 'APPROVED').length }}
        </span>
        <span class="px-2 py-1 bg-red-100 rounded-full text-red-700">
          Rechazados: {{ documents.filter(d => d.status === 'REJECTED').length }}
        </span>
        <span class="px-2 py-1 bg-yellow-100 rounded-full text-yellow-700">
          Pendientes: {{ documents.filter(d => d.status === 'PENDING').length }}
        </span>
        <span v-if="missingDocuments.length > 0" class="px-2 py-1 bg-orange-100 rounded-full text-orange-700">
          Faltantes: {{ missingDocuments.length }}
        </span>
      </div>

      <!-- Grid -->
      <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
        <!-- Uploaded Documents -->
        <div
          v-for="doc in documents"
          :key="doc.id"
          class="group relative border rounded-xl overflow-hidden transition-all hover:shadow-lg flex flex-col"
          :class="{
            'border-green-300 bg-green-50/50': doc.status === 'APPROVED',
            'border-red-300 bg-red-50/50': doc.status === 'REJECTED',
            'border-yellow-300 bg-yellow-50/50': doc.status === 'PENDING'
          }"
        >
          <!-- Thumbnail -->
          <button
            class="w-full aspect-[4/3] bg-gray-100 flex items-center justify-center relative"
            @click="viewDocument(doc)"
          >
            <!-- Image thumbnail -->
            <img
              v-if="documentThumbnails[doc.id]"
              :src="documentThumbnails[doc.id]"
              :alt="doc.name"
              class="w-full h-full object-cover"
            />
            <!-- Loading -->
            <div v-else-if="loadingThumbnails[doc.id]" class="animate-spin w-6 h-6 border-2 border-primary-600 border-t-transparent rounded-full" />
            <!-- PDF Icon -->
            <svg v-else-if="isPdf(doc.mime_type)" class="w-12 h-12 text-red-500" fill="currentColor" viewBox="0 0 24 24">
              <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8l-6-6zm-1 2l5 5h-5V4zM8.5 13a.5.5 0 01.5.5v3a.5.5 0 01-1 0v-3a.5.5 0 01.5-.5zm3 0c.828 0 1.5.672 1.5 1.5v1c0 .828-.672 1.5-1.5 1.5H11v1h1.5a.5.5 0 010 1H11a1 1 0 01-1-1v-4h1.5zm0 3a.5.5 0 00.5-.5v-1a.5.5 0 00-.5-.5H11v2h.5zm3.5-3h2a.5.5 0 010 1h-1.5v1h1a.5.5 0 010 1h-1v1.5a.5.5 0 01-1 0V13.5a.5.5 0 01.5-.5z"/>
            </svg>
            <!-- Person icon for SELFIE -->
            <svg v-else-if="doc.type === 'SELFIE'" class="w-12 h-12 text-gray-400" fill="currentColor" viewBox="0 0 24 24">
              <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
            </svg>
            <!-- Image icon for images not yet loaded -->
            <svg v-else-if="isImage(doc.mime_type)" class="w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
            <!-- Generic document -->
            <svg v-else class="w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>

            <!-- Hover overlay -->
            <div class="absolute inset-0 bg-black/0 group-hover:bg-black/30 transition-colors flex items-center justify-center">
              <div class="opacity-0 group-hover:opacity-100 transition-opacity bg-white/90 rounded-full p-2">
                <svg class="w-5 h-5 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                </svg>
              </div>
            </div>

            <!-- Status badge -->
            <span
              class="absolute top-2 right-2 px-2 py-0.5 rounded-full text-xs font-medium"
              :class="getStatusBadge(doc.status).class"
            >
              {{ getStatusBadge(doc.status).label }}
            </span>
          </button>

          <!-- Footer: info + actions (flex-grow to push to bottom) -->
          <div class="p-2 flex flex-col flex-grow">
            <!-- Document info -->
            <div class="flex-grow">
              <p class="text-xs font-medium text-gray-900 truncate">{{ getDocTypeName(doc.type) }}</p>
              <!-- Rejection reason -->
              <p v-if="doc.status === 'REJECTED' && doc.rejection_reason" class="text-xs text-red-600 mt-1 truncate">
                {{ getRejectionReasonLabel(doc.rejection_reason) }}
              </p>
            </div>

            <!-- Actions (always at bottom) -->
            <div class="mt-2">
              <!-- PENDING: Aprobar / Rechazar -->
              <div v-if="doc.status === 'PENDING'" class="flex gap-1">
                <button
                  class="flex-1 px-2 py-1 text-xs text-green-600 hover:bg-green-50 rounded transition-colors"
                  @click="openApproveModal(doc)"
                >
                  Aprobar
                </button>
                <button
                  class="flex-1 px-2 py-1 text-xs text-red-600 hover:bg-red-50 rounded transition-colors"
                  @click="openRejectModal(doc)"
                >
                  Rechazar
                </button>
              </div>

              <!-- REJECTED: Quitar Rechazo -->
              <div v-else-if="doc.status === 'REJECTED'" class="flex gap-1">
                <button
                  class="flex-1 px-2 py-1 text-xs text-yellow-600 hover:bg-yellow-50 rounded transition-colors border border-yellow-200"
                  @click="openUnrejectModal(doc)"
                >
                  Quitar Rechazo
                </button>
              </div>

              <!-- APPROVED: Desaprobar -->
              <div v-else-if="doc.status === 'APPROVED'" class="flex gap-1">
                <button
                  class="flex-1 px-2 py-1 text-xs text-yellow-600 hover:bg-yellow-50 rounded transition-colors border border-yellow-200"
                  @click="openUnapproveModal(doc)"
                >
                  Desaprobar
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Missing Documents -->
        <div
          v-for="missing in missingDocuments"
          :key="missing.type"
          class="border-2 border-dashed border-orange-300 rounded-xl overflow-hidden bg-orange-50/50 flex flex-col"
        >
          <!-- Thumbnail placeholder -->
          <div class="w-full aspect-[4/3] flex items-center justify-center bg-orange-100/50">
            <div class="text-center">
              <svg class="w-10 h-10 text-orange-400 mx-auto mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
              </svg>
              <p class="text-xs text-orange-600 font-medium">No subido</p>
            </div>
          </div>

          <!-- Footer (to match other cards) -->
          <div class="p-2 flex flex-col flex-grow">
            <div class="flex-grow">
              <p class="text-xs font-medium text-orange-800 truncate">{{ missing.name }}</p>
            </div>
            <div class="mt-2 h-7" />
          </div>
        </div>
      </div>

      <!-- Empty state -->
      <div v-if="documents.length === 0 && missingDocuments.length === 0" class="text-center py-8 text-gray-500">
        <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
        </svg>
        <p class="text-sm">No hay documentos requeridos para esta solicitud</p>
      </div>
    </div>

    <!-- Document Viewer Modal -->
    <Teleport to="body">
      <Transition
        enter-active-class="transition-opacity duration-200"
        leave-active-class="transition-opacity duration-200"
        enter-from-class="opacity-0"
        leave-to-class="opacity-0"
      >
        <div
          v-if="showViewer && selectedDocumentUrl"
          class="fixed inset-0 z-50 bg-black/90 flex flex-col"
          @click="showViewer = false"
        >
          <!-- Header -->
          <div class="flex items-center justify-between px-4 py-3 text-white">
            <div class="flex items-center gap-3">
              <h3 class="font-medium truncate">{{ selectedDocument?.name || getDocTypeName(selectedDocument?.type || '') }}</h3>
              <span
                v-if="selectedDocument"
                class="px-2 py-0.5 rounded-full text-xs font-medium"
                :class="getStatusBadge(selectedDocument.status).class"
              >
                {{ getStatusBadge(selectedDocument.status).label }}
              </span>
            </div>
            <div class="flex items-center gap-2">
              <a
                :href="selectedDocumentUrl"
                target="_blank"
                class="p-2 bg-white/10 hover:bg-white/20 rounded-lg transition-colors"
                title="Abrir en nueva pestaña"
                @click.stop
              >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                </svg>
              </a>
              <button
                class="p-2 bg-white/10 hover:bg-white/20 rounded-lg transition-colors"
                @click="showViewer = false"
              >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>
          </div>

          <!-- Image with navigation arrows -->
          <div class="flex-1 flex items-center justify-center p-4 overflow-auto relative" @click.stop>
            <!-- Previous arrow -->
            <button
              v-if="imageDocuments.length > 1"
              class="absolute left-4 top-1/2 -translate-y-1/2 w-12 h-12 bg-black/40 hover:bg-black/60 rounded-full flex items-center justify-center transition-colors z-10"
              :class="{ 'opacity-30 cursor-not-allowed': !canGoPrev }"
              :disabled="!canGoPrev"
              @click="prevDocument"
            >
              <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
              </svg>
            </button>

            <!-- Main image -->
            <img
              :src="selectedDocumentUrl"
              :alt="selectedDocument?.name || 'Documento'"
              class="max-w-full max-h-full object-contain rounded-lg"
            />

            <!-- Next arrow -->
            <button
              v-if="imageDocuments.length > 1"
              class="absolute right-4 top-1/2 -translate-y-1/2 w-12 h-12 bg-black/40 hover:bg-black/60 rounded-full flex items-center justify-center transition-colors z-10"
              :class="{ 'opacity-30 cursor-not-allowed': !canGoNext }"
              :disabled="!canGoNext"
              @click="nextDocument"
            >
              <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
              </svg>
            </button>

            <!-- Loading overlay -->
            <div v-if="isLoadingViewer" class="absolute inset-0 flex items-center justify-center bg-black/50">
              <div class="animate-spin w-8 h-8 border-4 border-white border-t-transparent rounded-full" />
            </div>
          </div>

          <!-- Thumbnail strip -->
          <div v-if="imageDocuments.length > 1" class="px-4 py-2 bg-black/50" @click.stop>
            <div class="flex gap-2 justify-center overflow-x-auto py-1">
              <button
                v-for="doc in imageDocuments"
                :key="doc.id"
                class="flex-shrink-0 w-16 h-16 rounded-lg overflow-hidden border-2 transition-all"
                :class="[
                  selectedDocument?.id === doc.id
                    ? 'border-white ring-2 ring-white/50'
                    : 'border-transparent opacity-60 hover:opacity-100'
                ]"
                @click="goToDocument(doc)"
              >
                <img
                  v-if="documentThumbnails[doc.id]"
                  :src="documentThumbnails[doc.id]"
                  :alt="getDocTypeName(doc.type)"
                  class="w-full h-full object-cover"
                />
                <div v-else class="w-full h-full bg-gray-700 flex items-center justify-center">
                  <svg class="w-6 h-6 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                  </svg>
                </div>
              </button>
            </div>
            <!-- Document counter -->
            <p class="text-center text-white/60 text-xs mt-1">
              {{ currentDocIndex + 1 }} / {{ imageDocuments.length }}
            </p>
          </div>

          <!-- Footer with actions -->
          <div v-if="selectedDocument?.status === 'PENDING'" class="px-4 py-4 pb-safe flex justify-center gap-4">
            <button
              class="flex items-center gap-2 bg-green-500 text-white px-6 py-3 rounded-full shadow-lg hover:bg-green-600 active:bg-green-700 transition-colors"
              @click.stop="viewerApprove"
            >
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
              </svg>
              <span class="font-medium">Aprobar</span>
            </button>
            <button
              class="flex items-center gap-2 bg-red-500 text-white px-6 py-3 rounded-full shadow-lg hover:bg-red-600 active:bg-red-700 transition-colors"
              @click.stop="viewerReject"
            >
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
              <span class="font-medium">Rechazar</span>
            </button>
          </div>

          <!-- APPROVED: badge + unapprove button -->
          <div v-else-if="selectedDocument?.status === 'APPROVED'" class="px-4 py-4 pb-safe flex justify-center gap-4">
            <div class="flex items-center gap-2 bg-green-500 text-white px-4 py-2 rounded-full shadow-lg">
              <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
              </svg>
              <span class="font-medium">Aprobado</span>
            </div>
            <button
              class="flex items-center gap-2 bg-yellow-500 text-white px-4 py-2 rounded-full shadow-lg hover:bg-yellow-600 active:bg-yellow-700 transition-colors"
              @click.stop="viewerUnapprove"
            >
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" />
              </svg>
              <span class="font-medium">Desaprobar</span>
            </button>
          </div>

          <!-- REJECTED: badge + unreject button (no approve option) -->
          <div v-else-if="selectedDocument?.status === 'REJECTED'" class="px-4 py-4 pb-safe flex justify-center gap-4">
            <div class="flex items-center gap-2 bg-red-500 text-white px-4 py-2 rounded-full shadow-lg">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
              <span class="font-medium">Rechazado</span>
            </div>
            <button
              class="flex items-center gap-2 bg-yellow-500 text-white px-4 py-2 rounded-full shadow-lg hover:bg-yellow-600 active:bg-yellow-700 transition-colors"
              @click.stop="viewerUnreject"
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

    <!-- Approve Modal -->
    <ConfirmModal
      v-model:show="showApproveModal"
      title="Aprobar Documento"
      :subtitle="docToAction ? getDocTypeName(docToAction.type) : ''"
      message="¿Confirmas que el documento es válido y cumple con los requisitos?"
      icon="check"
      icon-color="green"
      confirm-text="Aprobar"
      confirm-color="green"
      :loading="isApproving"
      @confirm="confirmApprove"
    />

    <!-- Reject Modal -->
    <ConfirmModal
      v-model:show="showRejectModal"
      title="Rechazar Documento"
      :subtitle="docToAction ? getDocTypeName(docToAction.type) : ''"
      icon="x"
      icon-color="red"
      select-label="Motivo del rechazo"
      :select-options="rejectionReasons"
      select-required
      comment-label="Comentario adicional"
      comment-placeholder="Explica qué debe corregir el solicitante..."
      confirm-text="Rechazar"
      confirm-color="red"
      :loading="isRejecting"
      @confirm="confirmReject"
    />

    <!-- Unapprove Modal -->
    <ConfirmModal
      v-model:show="showUnapproveModal"
      title="Desaprobar Documento"
      :subtitle="docToAction ? getDocTypeName(docToAction.type) : ''"
      message="El documento volverá a estado pendiente y podrá ser revisado nuevamente."
      icon="undo"
      icon-color="yellow"
      confirm-text="Desaprobar"
      confirm-color="yellow"
      :loading="isUnapproving"
      @confirm="confirmUnapprove"
    />

    <!-- Unreject Modal -->
    <ConfirmModal
      v-model:show="showUnrejectModal"
      title="Quitar Rechazo"
      :subtitle="docToAction ? getDocTypeName(docToAction.type) : ''"
      message="El documento volverá a estado pendiente y podrá ser revisado nuevamente."
      icon="undo"
      icon-color="yellow"
      confirm-text="Quitar Rechazo"
      confirm-color="yellow"
      :loading="isUnrejecting"
      @confirm="confirmUnreject"
    />
  </div>
</template>
