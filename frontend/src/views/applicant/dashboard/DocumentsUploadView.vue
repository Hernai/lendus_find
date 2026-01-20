<script setup lang="ts">
import { ref, onMounted, onBeforeMount, computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { AppButton } from '@/components/common'
import { v2, type V2Application, type V2Document } from '@/services/v2'
import { useDocumentTypes } from '@/composables'
import { logger } from '@/utils/logger'

const log = logger.child('DocumentsUpload')

const { loadDocumentTypes, getDocumentTypeLabel } = useDocumentTypes()

interface PendingDocument {
  type: string
  label: string
  description: string
  required: boolean
}

interface Application {
  id: string
  folio: string
  status: string
  pending_documents?: PendingDocument[]
  documents?: V2Document[]
}

const router = useRouter()
const route = useRoute()

const applicationId = route.params.id as string

interface DocumentItem {
  type: string
  label: string
  description: string
  required: boolean
  uploaded: boolean
  file?: File
  existingDoc?: V2Document
  isUploading?: boolean
  uploadError?: string
}

const isLoading = ref(true)
const isSaving = ref(false)
const isDeleting = ref(false)
const error = ref<string | null>(null)
const application = ref<Application | null>(null)
const documents = ref<DocumentItem[]>([])
const documentToDelete = ref<DocumentItem | null>(null)
const showDeleteModal = ref(false)

// Helper to get human-readable document label (uses backend types)
const getDocumentLabel = (type: string): string => {
  return getDocumentTypeLabel(type)
}

onBeforeMount(async () => {
  await loadDocumentTypes()
})

onMounted(async () => {
  await loadApplication()
})

const loadApplication = async () => {
  isLoading.value = true
  error.value = null

  try {
    const response = await v2.applicant.application.get(applicationId)
    const data = response.data
    if (!data) {
      throw new Error('No se encontró la solicitud')
    }

    application.value = {
      id: data.id,
      folio: data.folio || '',
      status: data.status,
      pending_documents: data.pending_documents,
      documents: data.documents,
    }

    // Build document list from pending documents and existing uploads
    const items: DocumentItem[] = []

    // Add pending documents (required but not uploaded)
    if (data.pending_documents) {
      for (const pending of data.pending_documents) {
        items.push({
          type: pending.type,
          label: pending.label,
          description: pending.description,
          required: pending.required,
          uploaded: false,
        })
      }
    }

    // Add already uploaded documents
    if (data.documents) {
      for (const doc of data.documents) {
        items.push({
          type: doc.type,
          label: getDocumentLabel(doc.type),
          description: doc.status === 'REJECTED'
            ? `Rechazado: ${doc.rejection_reason || 'Sin motivo especificado'}`
            : 'Documento cargado',
          required: true,
          uploaded: doc.status !== 'REJECTED',
          existingDoc: doc,
        })
      }
    }

    documents.value = items
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string } } }
    error.value = err.response?.data?.message || 'Error al cargar la solicitud'
    log.error('Failed to load application', { error: e })
  } finally {
    isLoading.value = false
  }
}

const handleFileSelect = async (doc: DocumentItem, event: Event) => {
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

  doc.file = file
  doc.uploadError = undefined

  // Upload immediately
  await uploadDocument(doc)
}

const uploadDocument = async (doc: DocumentItem) => {
  if (!doc.file) return

  doc.isUploading = true
  doc.uploadError = undefined

  try {
    // Note: Document is automatically associated with Person
    // application_id is passed in metadata for reference
    const response = await v2.applicant.document.upload(doc.file, doc.type, {
      metadata: { application_id: applicationId }
    })
    doc.uploaded = true
    doc.existingDoc = response.data?.document
    // Mantener el label original (tipo de documento), no sobrescribir con el nombre del archivo
    doc.description = 'Documento cargado'
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string } } }
    doc.uploadError = err.response?.data?.message || 'Error al subir el documento'
    doc.file = undefined
    log.error('Failed to upload document:', e)
  } finally {
    doc.isUploading = false
  }
}

const removeDocument = (doc: DocumentItem) => {
  // Don't allow deleting approved documents
  if (doc.existingDoc?.status === 'APPROVED') {
    error.value = 'No puedes eliminar un documento aprobado'
    return
  }

  // If no existing doc (just local file), remove immediately
  if (!doc.existingDoc) {
    doc.file = undefined
    doc.uploaded = false
    return
  }

  // Show confirmation modal for server-side documents
  documentToDelete.value = doc
  showDeleteModal.value = true
}

const cancelDelete = () => {
  documentToDelete.value = null
  showDeleteModal.value = false
}

const confirmDelete = async () => {
  if (!documentToDelete.value?.existingDoc) {
    cancelDelete()
    return
  }

  isDeleting.value = true

  try {
    await v2.applicant.document.remove(documentToDelete.value.existingDoc.id)
    documentToDelete.value.uploaded = false
    documentToDelete.value.file = undefined
    documentToDelete.value.existingDoc = undefined
    // Reload to get fresh pending documents list
    await loadApplication()
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string } } }
    error.value = err.response?.data?.message || 'Error al eliminar el documento'
    log.error('Failed to delete document', { error: e })
  } finally {
    isDeleting.value = false
    cancelDelete()
  }
}

const allRequiredUploaded = computed(() => {
  return documents.value
    .filter(d => d.required && !d.existingDoc?.status?.includes('REJECTED'))
    .every(d => d.uploaded)
})

const canEditDocument = (doc: DocumentItem): boolean => {
  // Can't edit approved documents
  if (doc.existingDoc?.status === 'APPROVED') return false
  return true
}

const getStatusBadge = (doc: DocumentItem) => {
  if (!doc.existingDoc) return null

  const statusMap: Record<string, { label: string; color: string; bg: string }> = {
    PENDING: { label: 'Pendiente', color: 'text-yellow-700', bg: 'bg-yellow-100' },
    APPROVED: { label: 'Aprobado', color: 'text-green-700', bg: 'bg-green-100' },
    REJECTED: { label: 'Rechazado', color: 'text-red-700', bg: 'bg-red-100' }
  }

  return statusMap[doc.existingDoc.status] || statusMap.PENDING
}

const pendingCount = computed(() => {
  return documents.value.filter(d => !d.uploaded && d.required).length
})

const handleSubmit = async () => {
  if (!allRequiredUploaded.value) return

  isSaving.value = true

  try {
    // If all documents are uploaded and we're in DOCS_PENDING status, submit
    if (application.value?.status === 'DOCS_PENDING') {
      await v2.applicant.application.submit(applicationId)
    }
    router.push('/dashboard')
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string } } }
    error.value = err.response?.data?.message || 'Error al enviar los documentos'
    log.error('Failed to submit:', e)
  } finally {
    isSaving.value = false
  }
}

const goBack = () => {
  router.push('/dashboard')
}
</script>

<template>
  <div class="min-h-screen bg-gray-50">
    <!-- Header -->
    <header class="bg-white border-b px-4 py-4">
      <div class="max-w-2xl mx-auto flex items-center gap-4">
        <button
          class="p-2 -ml-2 text-gray-500 hover:text-gray-700"
          @click="goBack"
        >
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
          </svg>
        </button>
        <div>
          <h1 class="text-lg font-semibold text-gray-900">Subir Documentos</h1>
          <p v-if="application" class="text-sm text-gray-500">{{ application.folio }}</p>
        </div>
      </div>
    </header>

    <!-- Main Content -->
    <main class="max-w-2xl mx-auto px-4 py-6">
      <!-- Loading State -->
      <div v-if="isLoading" class="bg-white rounded-2xl shadow-sm p-8 text-center">
        <div class="animate-spin w-8 h-8 border-4 border-primary-600 border-t-transparent rounded-full mx-auto" />
        <p class="text-gray-500 mt-4">Cargando documentos...</p>
      </div>

      <!-- Error State -->
      <div v-else-if="error" class="bg-red-50 rounded-xl p-4 mb-6">
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
        <!-- Info Banner -->
        <div class="bg-blue-50 rounded-xl p-4 mb-6">
          <div class="flex gap-3">
            <svg class="w-5 h-5 text-blue-500 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <div class="text-sm text-blue-800">
              <p class="font-medium">
                {{ pendingCount > 0 ? `${pendingCount} documento(s) pendiente(s)` : 'Todos los documentos subidos' }}
              </p>
              <p class="text-blue-600 mt-1">Formatos aceptados: PDF, JPG, PNG (máx. 10MB)</p>
            </div>
          </div>
        </div>

        <!-- Documents List -->
        <div class="space-y-4">
          <div
            v-for="doc in documents"
            :key="doc.type"
            class="bg-white rounded-xl shadow-sm overflow-hidden"
          >
            <div class="p-4">
              <div class="flex items-start justify-between mb-2">
                <div class="flex-1">
                  <h3 class="font-medium text-gray-900">
                    {{ doc.label }}
                    <span v-if="doc.required" class="text-red-500">*</span>
                  </h3>
                  <p class="text-sm text-gray-500 mt-1">{{ doc.description }}</p>
                </div>
                <div class="flex-shrink-0 ml-3">
                  <!-- Status Badge -->
                  <span
                    v-if="doc.existingDoc && getStatusBadge(doc)"
                    :class="[
                      'inline-flex items-center px-2 py-1 rounded-full text-xs font-medium',
                      getStatusBadge(doc)?.bg,
                      getStatusBadge(doc)?.color
                    ]"
                  >
                    <svg v-if="doc.existingDoc.status === 'APPROVED'" class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                      <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                    </svg>
                    <svg v-else-if="doc.existingDoc.status === 'REJECTED'" class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                      <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                    </svg>
                    {{ getStatusBadge(doc)?.label }}
                  </span>
                  <!-- Upload Status (for non-existing docs) -->
                  <span
                    v-else-if="doc.uploaded && !doc.existingDoc"
                    class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-700"
                  >
                    <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                      <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                    </svg>
                    Listo
                  </span>
                  <!-- Uploading -->
                  <span
                    v-else-if="doc.isUploading"
                    class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-700"
                  >
                    <div class="w-3 h-3 mr-1 border-2 border-blue-600 border-t-transparent rounded-full animate-spin" />
                    Subiendo...
                  </span>
                </div>
              </div>

              <!-- Upload Error -->
              <div v-if="doc.uploadError" class="mt-2 text-sm text-red-600">
                {{ doc.uploadError }}
              </div>

              <!-- Locked Message (Approved) -->
              <div v-if="doc.existingDoc?.status === 'APPROVED'" class="mt-3 bg-green-50 rounded-lg p-3 flex gap-3">
                <svg class="w-5 h-5 text-green-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                </svg>
                <p class="text-sm text-green-800">
                  Este documento ha sido aprobado y no puede ser modificado.
                </p>
              </div>

              <!-- Upload Area -->
              <div v-else-if="!doc.uploaded && !doc.isUploading" class="mt-3">
                <label
                  :for="`file-${doc.type}`"
                  class="flex flex-col items-center justify-center w-full h-32 border-2 border-dashed border-gray-300 rounded-xl cursor-pointer hover:border-primary-500 hover:bg-primary-50 transition-colors"
                >
                  <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                  </svg>
                  <p class="mt-2 text-sm text-gray-500">Toca para subir archivo</p>
                  <input
                    :id="`file-${doc.type}`"
                    type="file"
                    class="sr-only"
                    accept="image/jpeg,image/png,image/webp,application/pdf,.jpg,.jpeg,.png,.webp,.pdf"
                    capture="environment"
                    @change="handleFileSelect(doc, $event)"
                  >
                </label>
              </div>

              <!-- Uploaded File -->
              <div v-else-if="doc.uploaded && doc.existingDoc" class="mt-3 flex items-center justify-between bg-gray-50 rounded-lg p-3">
                <div class="flex items-center gap-3">
                  <div class="w-10 h-10 bg-primary-100 rounded-lg flex items-center justify-center">
                    <svg class="w-5 h-5 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                  </div>
                  <div>
                    <p class="text-sm font-medium text-gray-900">{{ doc.existingDoc.file_name }}</p>
                    <p v-if="doc.existingDoc.file_size" class="text-xs text-gray-500">
                      {{ (doc.existingDoc.file_size / 1024 / 1024).toFixed(2) }} MB
                    </p>
                  </div>
                </div>
                <button
                  v-if="canEditDocument(doc)"
                  class="p-2 text-gray-400 hover:text-red-500 transition-colors"
                  title="Eliminar documento"
                  @click="removeDocument(doc)"
                >
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                  </svg>
                </button>
                <div v-else class="p-2 text-gray-300" title="Documento aprobado y bloqueado">
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                  </svg>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Empty State -->
        <div v-if="documents.length === 0" class="bg-white rounded-2xl shadow-sm p-8 text-center">
          <svg class="w-12 h-12 text-gray-300 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
          </svg>
          <p class="text-gray-500 mt-4">No hay documentos requeridos para esta solicitud</p>
        </div>

        <!-- Action Buttons -->
        <div v-if="documents.length > 0" class="mt-6 space-y-3">
          <AppButton
            variant="primary"
            size="lg"
            full-width
            :loading="isSaving"
            :disabled="!allRequiredUploaded"
            @click="handleSubmit"
          >
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            {{ application?.status === 'DOCS_PENDING' ? 'Enviar Solicitud' : 'Continuar' }}
          </AppButton>
          <p v-if="!allRequiredUploaded" class="text-center text-sm text-gray-500">
            Sube todos los documentos requeridos para continuar
          </p>
          <AppButton
            variant="outline"
            size="lg"
            full-width
            @click="goBack"
          >
            Volver
          </AppButton>
        </div>
      </template>
    </main>

    <!-- Delete Confirmation Modal (Bottom Sheet Style) -->
    <Teleport to="body">
      <Transition name="modal">
        <div
          v-if="showDeleteModal"
          class="fixed inset-0 z-50 flex items-end sm:items-center justify-center"
        >
          <!-- Backdrop -->
          <div
            class="absolute inset-0 bg-black/50 transition-opacity"
            @click="cancelDelete"
          />

          <!-- Modal Content (Bottom Sheet) -->
          <div class="relative w-full sm:max-w-md mx-auto bg-white rounded-t-2xl sm:rounded-2xl shadow-xl transform transition-all">
            <!-- Handle bar for mobile -->
            <div class="flex justify-center pt-3 sm:hidden">
              <div class="w-10 h-1 bg-gray-300 rounded-full" />
            </div>

            <div class="p-6">
              <!-- Icon -->
              <div class="flex justify-center mb-4">
                <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center">
                  <svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                  </svg>
                </div>
              </div>

              <!-- Title -->
              <h3 class="text-lg font-semibold text-gray-900 text-center">
                ¿Eliminar documento?
              </h3>

              <!-- Description -->
              <p class="mt-2 text-sm text-gray-600 text-center">
                Estás a punto de eliminar
                <span class="font-medium text-gray-900">{{ documentToDelete?.label }}</span>.
                Esta acción no se puede deshacer.
              </p>

              <!-- Buttons -->
              <div class="mt-6 space-y-3">
                <button
                  class="w-full py-3 px-4 bg-red-600 hover:bg-red-700 text-white font-medium rounded-xl transition-colors flex items-center justify-center disabled:opacity-50 disabled:cursor-not-allowed"
                  :disabled="isDeleting"
                  @click="confirmDelete"
                >
                  <template v-if="isDeleting">
                    <div class="w-5 h-5 mr-2 border-2 border-white border-t-transparent rounded-full animate-spin" />
                    Eliminando...
                  </template>
                  <template v-else>
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                    Sí, eliminar
                  </template>
                </button>
                <button
                  class="w-full py-3 px-4 bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium rounded-xl transition-colors"
                  :disabled="isDeleting"
                  @click="cancelDelete"
                >
                  Cancelar
                </button>
              </div>
            </div>

            <!-- Safe area padding for iPhone -->
            <div class="h-safe-area-inset-bottom sm:hidden" />
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<style scoped>
/* Modal animations */
.modal-enter-active,
.modal-leave-active {
  transition: opacity 0.2s ease;
}

.modal-enter-active > div:last-child,
.modal-leave-active > div:last-child {
  transition: transform 0.3s ease;
}

.modal-enter-from,
.modal-leave-to {
  opacity: 0;
}

.modal-enter-from > div:last-child,
.modal-leave-to > div:last-child {
  transform: translateY(100%);
}

@media (min-width: 640px) {
  .modal-enter-from > div:last-child,
  .modal-leave-to > div:last-child {
    transform: translateY(20px) scale(0.95);
  }
}

/* Safe area for iPhone notch */
.h-safe-area-inset-bottom {
  height: env(safe-area-inset-bottom, 0);
}
</style>
