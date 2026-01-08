<script setup lang="ts">
import { reactive, ref, computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useOnboardingStore, useApplicationStore, useTenantStore } from '@/stores'
import { AppButton } from '@/components/common'
import type { Product } from '@/types'

const router = useRouter()
const onboardingStore = useOnboardingStore()
const applicationStore = useApplicationStore()
const tenantStore = useTenantStore()

interface DocumentUpload {
  id: string
  name: string
  description: string
  required: boolean
  file: File | null
  preview: string | null
  status: 'pending' | 'uploading' | 'uploaded' | 'error'
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

  // Get required_docs from product (either selected or from config)
  const requiredDocs = productFromConfig?.required_docs ?? product?.required_docs ?? []

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

// Initialize documents from product on mount
const initDocuments = () => {
  const requiredDocs = getRequiredDocuments()
  documents.length = 0 // Clear array
  documents.push(...requiredDocs)

  // Restore uploaded status from store
  const step6 = onboardingStore.data.step6
  if (step6.documents_uploaded && step6.documents_uploaded.length > 0) {
    documents.forEach(doc => {
      if (step6.documents_uploaded.includes(doc.id)) {
        doc.status = 'uploaded'
      }
    })
  }
}

// Sync from store on mount
onMounted(async () => {
  await onboardingStore.init()
  initDocuments()
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

  // Simulate upload
  try {
    await new Promise(resolve => setTimeout(resolve, 1500))
    doc.status = 'uploaded'
  } catch (e) {
    doc.status = 'error'
    error.value = 'Error al subir el archivo. Intenta de nuevo.'
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
    console.error('Failed to save step 6:', e)
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
              'border-green-500 bg-green-50/30': doc.status === 'uploaded',
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
                    accept="image/*,.pdf"
                    class="hidden"
                    @change="handleFileSelect(doc, $event)"
                  >
                </label>
              </div>

              <div v-else-if="doc.status === 'uploading'" class="flex items-center gap-2 text-sm text-primary-600">
                <div class="animate-spin w-4 h-4 border-2 border-primary-600 border-t-transparent rounded-full" />
                Subiendo...
              </div>

              <div v-else-if="doc.status === 'uploaded'" class="flex items-center gap-3">
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
