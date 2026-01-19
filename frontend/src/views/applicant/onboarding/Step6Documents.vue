<script setup lang="ts">
import { reactive, ref, computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useOnboardingStore, useApplicationStore, useTenantStore, useKycStore } from '@/stores'
import { AppButton } from '@/components/common'
import { api } from '@/services/api'
import { logger } from '@/utils/logger'
import type { Product } from '@/types'

const log = logger.child('Step6Documents')

const router = useRouter()
const onboardingStore = useOnboardingStore()
const applicationStore = useApplicationStore()
const tenantStore = useTenantStore()
const kycStore = useKycStore()

interface DocumentUpload {
  id: string
  name: string
  description: string
  required: boolean
  file: File | null
  preview: string | null
  status: 'pending' | 'uploading' | 'uploaded' | 'error'
  fromKyc?: boolean // Flag to indicate document was captured during KYC verification
}

// Document labels for display (maps type codes to human-readable names)
const documentLabels: Record<string, { name: string; description: string }> = {
  'INE_FRONT': { name: 'INE Frente', description: 'Foto clara del frente de tu INE/IFE' },
  'INE_BACK': { name: 'INE Reverso', description: 'Foto clara del reverso de tu INE/IFE' },
  'PROOF_ADDRESS': { name: 'Comprobante de domicilio', description: 'Recibo de luz, agua, teléfono (máximo 3 meses)' },
  'PROOF_INCOME': { name: 'Comprobante de ingresos', description: 'Recibo de nómina, estado de cuenta o declaración fiscal' },
  'PAYSLIP_1': { name: 'Recibo de nómina 1', description: 'Recibo de nómina reciente (mes actual)' },
  'PAYSLIP_2': { name: 'Recibo de nómina 2', description: 'Recibo de nómina (mes anterior)' },
  'PAYSLIP_3': { name: 'Recibo de nómina 3', description: 'Recibo de nómina (hace 2 meses)' },
  'BANK_STATEMENT': { name: 'Estado de cuenta', description: 'Estado de cuenta bancario (máximo 3 meses)' },
  'VEHICLE_INVOICE': { name: 'Factura del vehículo', description: 'Factura original del vehículo' },
  'RFC_CONSTANCIA': { name: 'Constancia RFC', description: 'Constancia de situación fiscal' },
  'CURP': { name: 'CURP', description: 'Clave Única de Registro de Población' },
  'SELFIE': { name: 'Selfie con INE', description: 'Foto tuya sosteniendo tu INE' },
}

// Get document list from product, fallback to defaults
const getRequiredDocuments = (): DocumentUpload[] => {
  const product = applicationStore.selectedProduct
  const productFromConfig = tenantStore.products.find(
    (p: Product) => p.id === product?.id
  )

  // Get required_documents from product (either selected or from config)
  const requiredDocs = productFromConfig?.required_documents ?? product?.required_documents ??
                       productFromConfig?.required_docs ?? product?.required_docs ?? []

  if (requiredDocs.length > 0) {
    return requiredDocs.map((doc: { type: string; required: boolean; description?: string } | string) => {
      const docType = typeof doc === 'string' ? doc : doc.type
      const isRequired = typeof doc === 'string' ? true : (doc.required ?? true)
      const docInfo = documentLabels[docType] || { name: docType, description: '' }

      return {
        id: docType.toLowerCase(),
        name: docInfo.name,
        description: typeof doc === 'object' && doc.description ? doc.description : docInfo.description,
        required: isRequired,
        file: null,
        preview: null,
        status: 'pending' as const
      }
    })
  }

  // Fallback to basic documents if no product info available
  return [
    { id: 'ine_front', name: 'INE Frente', description: 'Foto clara del frente de tu INE/IFE', required: true, file: null, preview: null, status: 'pending' as const },
    { id: 'ine_back', name: 'INE Reverso', description: 'Foto clara del reverso de tu INE/IFE', required: true, file: null, preview: null, status: 'pending' as const },
    { id: 'proof_address', name: 'Comprobante de domicilio', description: 'Recibo de luz, agua, teléfono (máximo 3 meses)', required: true, file: null, preview: null, status: 'pending' as const },
  ]
}

const documents = reactive<DocumentUpload[]>([])

const error = ref('')

// Check which documents were already captured during KYC
const getKycDocuments = (): Set<string> => {
  const kycDocs = new Set<string>()

  // If KYC verification was completed, INE images are already captured
  if (kycStore.verified && kycStore.lockedData.curp) {
    if (kycStore.ineFrontImage) {
      kycDocs.add('ine_front')
    }
    if (kycStore.ineBackImage) {
      kycDocs.add('ine_back')
    }
    if (kycStore.selfieImage) {
      kycDocs.add('selfie')
    }
  }

  return kycDocs
}

// Initialize documents from product on mount
const initDocuments = () => {
  const requiredDocs = getRequiredDocuments()
  const kycDocs = getKycDocuments()

  documents.length = 0 // Clear array

  // Helper to ensure base64 image has data URL prefix
  const ensureDataUrl = (base64: string): string => {
    if (base64.startsWith('data:')) {
      return base64
    }
    return `data:image/jpeg;base64,${base64}`
  }

  // Add documents, marking KYC ones with preview but NOT as uploaded yet
  // (they will be uploaded to backend by uploadKycDocuments)
  requiredDocs.forEach(doc => {
    const docIdLower = doc.id.toLowerCase()
    if (kycDocs.has(docIdLower)) {
      // Mark as pending but with KYC flag - will be uploaded automatically
      doc.fromKyc = true
      // Set preview from KYC images (ensure proper data URL format)
      if (docIdLower === 'ine_front' && kycStore.ineFrontImage) {
        doc.preview = ensureDataUrl(kycStore.ineFrontImage)
      } else if (docIdLower === 'ine_back' && kycStore.ineBackImage) {
        doc.preview = ensureDataUrl(kycStore.ineBackImage)
      } else if (docIdLower === 'selfie' && kycStore.selfieImage) {
        doc.preview = ensureDataUrl(kycStore.selfieImage)
      }
    }
    documents.push(doc)
  })

  // Restore uploaded status from store (for non-KYC docs)
  const step6 = onboardingStore.data.step6
  if (step6.documents_uploaded && step6.documents_uploaded.length > 0) {
    documents.forEach(doc => {
      if (!doc.fromKyc && step6.documents_uploaded.includes(doc.id)) {
        doc.status = 'uploaded'
      }
    })
  }
}

// Upload KYC images to backend as documents (if not already uploaded)
const uploadKycDocuments = async () => {
  const applicationId = applicationStore.currentApplication?.id
  if (!applicationId) {
    log.warn('No application ID for KYC document upload')
    return
  }

  // Check if KYC is verified and has images
  if (!kycStore.verified || !kycStore.lockedData.curp) {
    return
  }

  const kycImages: { type: string; image: string | null }[] = [
    { type: 'INE_FRONT', image: kycStore.ineFrontImage },
    { type: 'INE_BACK', image: kycStore.ineBackImage },
    { type: 'SELFIE', image: kycStore.selfieImage }
  ]

  for (const { type, image } of kycImages) {
    if (!image) continue

    // Find the document in our list (by type, case-insensitive)
    const doc = documents.find(d => d.id.toUpperCase() === type)
    if (!doc) continue

    // Only upload if it's a KYC document that hasn't been uploaded yet
    if (doc.status === 'uploaded') {
      log.debug('Skipping already uploaded document', { type })
      continue
    }

    try {
      doc.status = 'uploading'

      // Convert base64 to Blob
      const base64Data = image.startsWith('data:') ? image.split(',')[1] || image : image
      const byteCharacters = atob(base64Data)
      const byteNumbers = new Array(byteCharacters.length)
      for (let i = 0; i < byteCharacters.length; i++) {
        byteNumbers[i] = byteCharacters.charCodeAt(i)
      }
      const byteArray = new Uint8Array(byteNumbers)
      const blob = new Blob([byteArray], { type: 'image/jpeg' })

      // Create File from Blob
      const file = new File([blob], `${type.toLowerCase()}.jpg`, { type: 'image/jpeg' })

      // Prepare KYC metadata for auto-approval
      // Use different metadata for selfie (face match) vs INE (OCR)
      const isSelfie = type === 'SELFIE'
      const kycMetadata = isSelfie
        ? {
            kyc_validated: true,
            source: 'kyc',
            nubarium_validated: true,
            validation_method: 'KYC_FACE_MATCH',
            face_match: true,
            validated_at: new Date().toISOString(),
            face_match_score: kycStore.validations.face_match?.score || null,
            face_match_passed: kycStore.validations.face_match?.match || false,
            liveness_passed: kycStore.validations.liveness?.passed || null,
            liveness_score: kycStore.validations.liveness?.score || null
          }
        : {
            kyc_validated: true,
            source: 'kyc',
            nubarium_validated: true,
            validation_method: 'KYC_INE_OCR',
            ine_ocr: true,
            validated_at: new Date().toISOString(),
            ine_valid: true, // Document was validated during KYC
            ocr_curp: kycStore.lockedData.curp || null,
            ocr_data: kycStore.lockedData
          }

      // Upload to backend with KYC metadata
      const formData = new FormData()
      formData.append('type', type)
      formData.append('file', file)
      formData.append('metadata', JSON.stringify(kycMetadata))

      await api.post(`/applications/${applicationId}/documents`, formData)

      doc.status = 'uploaded'
      doc.fromKyc = true
      log.debug('KYC document uploaded with metadata - will be auto-approved', { type })
    } catch (e: unknown) {
      log.error('Error uploading KYC document', { type, error: e })
      // Don't mark as error - user can still upload manually
      doc.status = 'pending'
      doc.fromKyc = false
    }
  }
}

// Sync from store on mount
onMounted(async () => {
  await onboardingStore.init()
  initDocuments()
  // Auto-upload KYC documents to backend
  await uploadKycDocuments()
})

// Auto-save to store when document status changes
watch(
  () => documents.map(d => ({ id: d.id, status: d.status })),
  () => {
    const uploadedIds = documents
      .filter(d => d.status === 'uploaded')
      .map(d => d.id)
    onboardingStore.updateStepData('step6', {
      documents_uploaded: uploadedIds
    })
  },
  { deep: true }
)

const allRequiredUploaded = computed(() => {
  return documents
    .filter(doc => doc.required)
    .every(doc => doc.status === 'uploaded')
})

const handleFileSelect = async (doc: DocumentUpload, event: Event) => {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]

  if (!file) return

  // Validate file type
  const allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf']
  if (!allowedTypes.includes(file.type)) {
    error.value = 'Solo se permiten imágenes (JPG, PNG, WebP) o PDF'
    return
  }

  // Validate file size (max 10MB)
  if (file.size > 10 * 1024 * 1024) {
    error.value = 'El archivo no debe superar 10MB'
    return
  }

  error.value = ''
  doc.file = file
  doc.status = 'uploading'

  // Create preview for images
  if (file.type.startsWith('image/')) {
    const reader = new FileReader()
    reader.onload = (e) => {
      doc.preview = e.target?.result as string
    }
    reader.readAsDataURL(file)
  } else {
    doc.preview = null
  }

  // Upload to backend
  try {
    const applicationId = applicationStore.currentApplication?.id
    if (!applicationId) {
      throw new Error('No hay solicitud activa')
    }

    // Create FormData for file upload
    const formData = new FormData()
    formData.append('type', doc.id.toUpperCase())
    formData.append('file', file)

    await api.post(`/applications/${applicationId}/documents`, formData)
    doc.status = 'uploaded'
  } catch (e: unknown) {
    log.error('Error uploading document', { error: e })
    doc.status = 'error'
    const errorObj = e as { response?: { data?: { message?: string } } }
    error.value = errorObj.response?.data?.message || 'Error al subir el archivo. Intenta de nuevo.'
  }
}

const removeFile = (doc: DocumentUpload) => {
  doc.file = null
  doc.preview = null
  doc.status = 'pending'
}

const handleSubmit = async () => {
  if (!allRequiredUploaded.value) {
    error.value = 'Por favor sube todos los documentos requeridos'
    return
  }

  try {
    // Update store with final document list
    const uploadedIds = documents
      .filter(d => d.status === 'uploaded')
      .map(d => d.id)
    onboardingStore.updateStepData('step6', {
      documents_uploaded: uploadedIds
    })

    // Save step 6 explicitly
    await onboardingStore.completeStep(6)
    router.push('/solicitud/paso-7')
  } catch (e) {
    log.error('Failed to save step 6', { error: e })
    error.value = 'Error al guardar. Intenta de nuevo.'
  }
}

const prevStep = () => router.push('/solicitud/paso-5')
</script>

<template>
  <div class="px-4 py-6">
    <div class="max-w-md mx-auto">
      <h1 class="text-2xl font-bold text-gray-900 mb-2">Sube tus documentos</h1>
      <p class="text-gray-500 mb-6">Necesitamos verificar tu identidad e información.</p>

      <!-- Loading state -->
      <div v-if="onboardingStore.isLoading" class="flex justify-center py-8">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600"></div>
      </div>

      <div v-else>
        <div class="space-y-4">
          <div
            v-for="doc in documents"
            :key="doc.id"
            class="bg-white rounded-xl border p-4"
            :class="{
              'border-gray-200': doc.status === 'pending',
              'border-primary-500 bg-primary-50/30': doc.status === 'uploading',
              'border-green-500 bg-green-50/30': doc.status === 'uploaded' && !doc.fromKyc,
              'border-green-600 bg-green-100/50': doc.status === 'uploaded' && doc.fromKyc,
              'border-red-500 bg-red-50/30': doc.status === 'error'
            }"
          >
          <div class="flex items-start gap-4">
            <!-- Preview / Icon -->
            <div class="w-16 h-16 bg-gray-100 rounded-lg flex items-center justify-center overflow-hidden flex-shrink-0">
              <img
                v-if="doc.preview"
                :src="doc.preview"
                :alt="doc.name"
                class="w-full h-full object-cover"
              >
              <svg v-else-if="doc.status === 'uploaded'" class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              <svg v-else class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
              </svg>
            </div>

            <!-- Info -->
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2 mb-1">
                <h3 class="font-medium text-gray-900">{{ doc.name }}</h3>
                <span v-if="doc.required" class="text-xs text-red-500">*</span>
              </div>
              <p class="text-sm text-gray-500 mb-2">{{ doc.description }}</p>

              <!-- Actions -->
              <div v-if="doc.status === 'pending' || doc.status === 'error'">
                <label class="inline-flex items-center gap-1 text-sm text-primary-600 font-medium cursor-pointer hover:text-primary-700">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                  </svg>
                  Subir archivo
                  <input
                    type="file"
                    accept="image/jpeg,image/png,image/webp,application/pdf,.jpg,.jpeg,.png,.webp,.pdf"
                    capture="environment"
                    class="sr-only"
                    @change="handleFileSelect(doc, $event)"
                  >
                </label>
              </div>

              <div v-else-if="doc.status === 'uploading'" class="flex items-center gap-2 text-sm text-primary-600">
                <div class="animate-spin w-4 h-4 border-2 border-primary-600 border-t-transparent rounded-full" />
                Subiendo...
              </div>

              <div v-else-if="doc.status === 'uploaded'" class="flex items-center gap-3">
                <span v-if="doc.fromKyc" class="text-sm text-green-600 flex items-center gap-1">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                  </svg>
                  Verificado con KYC
                </span>
                <template v-else>
                  <span class="text-sm text-green-600 flex items-center gap-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    Subido
                  </span>
                  <button
                    type="button"
                    class="text-sm text-gray-500 hover:text-red-600"
                    @click="removeFile(doc)"
                  >
                    Eliminar
                  </button>
                </template>
              </div>
            </div>
          </div>
        </div>
        </div>

        <p v-if="error" class="mt-4 text-sm text-red-500 text-center">
          {{ error }}
        </p>

        <div class="mt-6 bg-yellow-50 rounded-xl p-4 flex gap-3">
          <svg class="w-6 h-6 text-yellow-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
          </svg>
          <div class="text-sm text-yellow-800">
            <p class="font-medium">Consejos para mejores resultados:</p>
            <ul class="mt-1 list-disc list-inside text-yellow-700">
              <li>Usa buena iluminación</li>
              <li>Asegúrate de que el texto sea legible</li>
              <li>Evita reflejos y sombras</li>
            </ul>
          </div>
        </div>

        <!-- Auto-save indicator -->
        <div v-if="onboardingStore.lastSavedAt" class="text-xs text-gray-400 text-right mt-4">
          Guardado automáticamente
        </div>

        <!-- Sticky Footer -->
        <div class="fixed bottom-0 left-0 right-0 p-4 bg-white border-t">
          <div class="max-w-md mx-auto flex gap-3">
            <AppButton
              type="button"
              variant="outline"
              size="lg"
              class="flex-1"
              @click="prevStep"
            >
              ← Anterior
            </AppButton>
            <AppButton
              type="button"
              variant="primary"
              size="lg"
              class="flex-1"
              :disabled="!allRequiredUploaded"
              :loading="onboardingStore.isSaving"
              @click="handleSubmit"
            >
              Continuar →
            </AppButton>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
