<script setup lang="ts">
import { reactive, ref, computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useOnboardingStore, useApplicationStore, useTenantStore, useKycStore } from '@/stores'
import { AppButton } from '@/components/common'
import { v2 } from '@/services/v2'
import { logger } from '@/utils/logger'
import type { Product } from '@/types'

const log = logger.child('Step6Documents')

const router = useRouter()
const onboardingStore = useOnboardingStore()
const applicationStore = useApplicationStore()
const tenantStore = useTenantStore()
const kycStore = useKycStore()

// Check if user is foreigner (non-Mexican)
const isForeigner = computed(() => {
  const nationality = onboardingStore.data.step1.nationality
  return nationality && nationality !== 'MX'
})

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

// Fallback labels in Spanish (used if API call fails)
const DOCUMENT_TYPE_LABELS_FALLBACK: Record<string, string> = {
  'INE_FRONT': 'INE (Frente)',
  'INE_BACK': 'INE (Reverso)',
  'PASSPORT': 'Pasaporte',
  'FM2': 'FM2 (Tarjeta de No Inmigrante)',
  'FM3': 'FM3 (Tarjeta de Visitante)',
  'RESIDENCE_CARD': 'Tarjeta de Residente',
  'VISA': 'Visa vigente',
  'CURP': 'CURP',
  'CURP_DOC': 'CURP',
  'DRIVER_LICENSE_FRONT': 'Licencia de Conducir (Frente)',
  'DRIVER_LICENSE_BACK': 'Licencia de Conducir (Reverso)',
  'SELFIE': 'Foto de perfil (Selfie)',
  'SIGNATURE': 'Firma',
  'PROOF_OF_ADDRESS': 'Comprobante de domicilio',
  'UTILITY_BILL': 'Recibo de servicios',
  'BANK_STATEMENT_ADDRESS': 'Estado de cuenta (Domicilio)',
  'LEASE_AGREEMENT': 'Contrato de arrendamiento',
  'PROPERTY_DEED': 'Escrituras',
  'PAYSLIP': 'Recibo de nómina',
  'PAYSLIP_1': 'Recibo de nómina 1',
  'PAYSLIP_2': 'Recibo de nómina 2',
  'PAYSLIP_3': 'Recibo de nómina 3',
  'BANK_STATEMENT': 'Estado de cuenta bancario',
  'IMSS_STATEMENT': 'Estado de cuenta IMSS',
  'EMPLOYMENT_LETTER': 'Carta laboral',
  'INCOME_AFFIDAVIT': 'Declaración de ingresos',
  'RFC_CONSTANCIA': 'Constancia de situación fiscal',
  'RFC': 'Constancia de situación fiscal',
  'TAX_RETURN': 'Declaración de impuestos',
  'VEHICLE_INVOICE': 'Factura del vehículo',
  'BIRTH_CERTIFICATE': 'Acta de nacimiento',
  'MARRIAGE_CERTIFICATE': 'Acta de matrimonio',
  'BUSINESS_LICENSE': 'Licencia comercial',
  'CONSTITUTIVE_ACT': 'Acta constitutiva',
  'POWER_OF_ATTORNEY': 'Poder notarial',
  'TAX_ID_COMPANY': 'RFC de empresa',
  'FISCAL_SITUATION': 'Situación fiscal',
  'LEGAL_REP_ID': 'Identificación del representante legal',
  'SHAREHOLDER_STRUCTURE': 'Estructura accionaria',
  'OTHER': 'Otro documento',
}

// Document types loaded from backend
const documentTypeLabels = ref<Record<string, string>>(DOCUMENT_TYPE_LABELS_FALLBACK)

// Load document types from backend (updates fallback with fresh data if available)
const loadDocumentTypes = async () => {
  try {
    const response = await v2.applicant.document.getTypes()
    if (response.success && response.data?.types) {
      documentTypeLabels.value = response.data.types
      log.debug('Loaded document type labels from API')
    }
  } catch (err) {
    log.warn('Failed to load document types from API, using fallback', err)
    // Keep using fallback labels (already set as default)
  }
}

// Get label for a document type
const getDocumentLabel = (type: string): string => {
  return documentTypeLabels.value[type] || DOCUMENT_TYPE_LABELS_FALLBACK[type] || type.replace(/_/g, ' ')
}

// Document types that should NOT be shown in Step 6 (handled elsewhere)
const EXCLUDED_DOCUMENT_TYPES = ['SIGNATURE'] // Signature is drawn in Step 8, not uploaded

// Get document list from product - types come from backend enum
const getRequiredDocuments = (): DocumentUpload[] => {
  const product = applicationStore.selectedProduct
  // Always prefer fresh data from tenant config (loaded from API) over cached application data
  const productFromConfig = tenantStore.products.find(
    (p: Product) => p.id === product?.id
  )

  // Priority: fresh config data > cached application data
  const requiredDocs = productFromConfig?.required_documents ?? productFromConfig?.required_docs ??
                       product?.required_documents ?? product?.required_docs ?? []

  if (requiredDocs.length > 0) {
    return requiredDocs
      .filter((doc: { type: string; required: boolean; description?: string } | string) => {
        // Exclude document types that are handled elsewhere (e.g., signature in Step 8)
        const docType = typeof doc === 'string' ? doc : doc.type
        return !EXCLUDED_DOCUMENT_TYPES.includes(docType)
      })
      .map((doc: { type: string; required: boolean; description?: string } | string) => {
        let docType = typeof doc === 'string' ? doc : doc.type
        const isRequired = typeof doc === 'string' ? true : (doc.required ?? true)

        // Replace INE with PASSPORT and add residence document for foreigners
        if (isForeigner.value) {
          if (docType === 'INE_FRONT') {
            docType = 'PASSPORT'
          } else if (docType === 'INE_BACK') {
            docType = 'RESIDENCE_CARD' // FM2/FM3/Residence card
          }
        }

        const label = getDocumentLabel(docType)

        return {
          id: docType,
          name: label,
          description: typeof doc === 'object' && doc.description ? doc.description : '',
          required: isRequired,
          file: null,
          preview: null,
          status: 'pending' as const
        }
      })
      .filter((doc: any) => doc !== null) // Remove null entries (INE_BACK for foreigners)
  }

  // Fallback to basic documents if no product info available
  if (isForeigner.value) {
    // Foreigners: PASSPORT + RESIDENCE_CARD
    return [
      { id: 'PASSPORT', name: getDocumentLabel('PASSPORT'), description: '', required: true, file: null, preview: null, status: 'pending' as const },
      { id: 'RESIDENCE_CARD', name: getDocumentLabel('RESIDENCE_CARD'), description: 'FM2, FM3 o Tarjeta de Residente vigente', required: true, file: null, preview: null, status: 'pending' as const },
      { id: 'PROOF_OF_ADDRESS', name: getDocumentLabel('PROOF_OF_ADDRESS'), description: '', required: true, file: null, preview: null, status: 'pending' as const },
    ]
  }

  // Mexicans: INE front and back
  return [
    { id: 'INE_FRONT', name: getDocumentLabel('INE_FRONT'), description: '', required: true, file: null, preview: null, status: 'pending' as const },
    { id: 'INE_BACK', name: getDocumentLabel('INE_BACK'), description: '', required: true, file: null, preview: null, status: 'pending' as const },
    { id: 'PROOF_OF_ADDRESS', name: getDocumentLabel('PROOF_OF_ADDRESS'), description: '', required: true, file: null, preview: null, status: 'pending' as const },
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
const initDocuments = async () => {
  const requiredDocs = getRequiredDocuments()
  const kycDocs = getKycDocuments()

  documents.length = 0 // Clear array

  log.debug('KYC Store state:', {
    verified: kycStore.verified,
    hasCurp: !!kycStore.lockedData.curp,
    hasIneFront: !!kycStore.ineFrontImage,
    hasIneBack: !!kycStore.ineBackImage,
    hasSelfie: !!kycStore.selfieImage
  })

  // Helper to ensure base64 image has data URL prefix
  const ensureDataUrl = (base64: string): string => {
    if (base64.startsWith('data:')) {
      return base64
    }
    return `data:image/jpeg;base64,${base64}`
  }

  // Fetch existing documents from backend to check which are KYC-verified
  let uploadedKycDocs = new Map<string, { id: string; preview?: string }>()
  try {
    const response = await v2.applicant.document.list()
    if (response.success && response.data?.documents) {
      // Filter only KYC-verified documents and store their IDs
      for (const doc of response.data.documents) {
        if (doc.metadata?.kyc_validated === true) {
          uploadedKycDocs.set(doc.type, { id: doc.id })

          // For image documents, try to get preview URL
          if (doc.mime_type?.startsWith('image/')) {
            try {
              const urlResponse = await v2.applicant.document.download(doc.id)
              if (urlResponse.success && urlResponse.data?.url) {
                uploadedKycDocs.set(doc.type, { id: doc.id, preview: urlResponse.data.url })
              }
            } catch (err) {
              log.warn('Failed to get preview URL for document', { type: doc.type, error: err })
            }
          }
        }
      }

      log.debug('Found KYC-verified documents', {
        total: response.data.documents.length,
        kycVerified: Array.from(uploadedKycDocs.keys()),
        withPreviews: Array.from(uploadedKycDocs.entries()).filter(([, v]) => v.preview).map(([k]) => k)
      })
    }
  } catch (e) {
    log.warn('Failed to fetch existing documents', { error: e })
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

  // Restore uploaded status ONLY for KYC-verified documents
  documents.forEach(doc => {
    if (uploadedKycDocs.has(doc.id)) {
      doc.status = 'uploaded'
      doc.fromKyc = true

      // Set preview from backend download URL (signed URL)
      const docData = uploadedKycDocs.get(doc.id)
      if (docData?.preview) {
        doc.preview = docData.preview
      } else {
        // Fallback to KYC store images if backend preview not available
        const docIdLower = doc.id.toLowerCase()
        if (docIdLower === 'ine_front' && kycStore.ineFrontImage) {
          doc.preview = ensureDataUrl(kycStore.ineFrontImage)
        } else if (docIdLower === 'ine_back' && kycStore.ineBackImage) {
          doc.preview = ensureDataUrl(kycStore.ineBackImage)
        } else if (docIdLower === 'selfie' && kycStore.selfieImage) {
          doc.preview = ensureDataUrl(kycStore.selfieImage)
        }
      }

      log.debug('KYC document already uploaded and verified, skipping re-upload', { type: doc.id, hasPreview: !!doc.preview })
    }
  })
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

    // Find the document in our list (doc.id is already uppercase)
    const doc = documents.find(d => d.id === type)
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

      // Upload to backend with KYC metadata using V2 API
      await v2.applicant.document.upload(file, type, { metadata: kycMetadata })

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
  // Load document type labels first (must complete before initDocuments)
  await loadDocumentTypes()

  // Ensure tenant config is loaded (with fresh product data from API)
  if (!tenantStore.isLoaded) {
    await tenantStore.loadConfig()
  }
  await onboardingStore.init()
  await initDocuments()
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

    // Upload using V2 API (doc.id is already uppercase from backend)
    await v2.applicant.document.upload(file, doc.id)
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
