<script setup lang="ts">
import { reactive, ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useApplicationStore } from '@/stores'
import { AppButton } from '@/components/common'

const router = useRouter()
const applicationStore = useApplicationStore()

interface DocumentUpload {
  id: string
  name: string
  description: string
  required: boolean
  file: File | null
  preview: string | null
  status: 'pending' | 'uploading' | 'uploaded' | 'error'
}

const documents = reactive<DocumentUpload[]>([
  {
    id: 'ine_front',
    name: 'INE Frente',
    description: 'Foto clara del frente de tu INE/IFE',
    required: true,
    file: null,
    preview: null,
    status: 'pending'
  },
  {
    id: 'ine_back',
    name: 'INE Reverso',
    description: 'Foto clara del reverso de tu INE/IFE',
    required: true,
    file: null,
    preview: null,
    status: 'pending'
  },
  {
    id: 'proof_address',
    name: 'Comprobante de domicilio',
    description: 'Recibo de luz, agua, teléfono (máximo 3 meses)',
    required: true,
    file: null,
    preview: null,
    status: 'pending'
  },
  {
    id: 'proof_income',
    name: 'Comprobante de ingresos',
    description: 'Recibo de nómina, estado de cuenta o declaración fiscal',
    required: false,
    file: null,
    preview: null,
    status: 'pending'
  }
])

const error = ref('')

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

  await applicationStore.saveStepData({
    step6: {
      documents: documents.map(doc => ({
        id: doc.id,
        name: doc.name,
        uploaded: doc.status === 'uploaded',
        file_name: doc.file?.name || null
      }))
    }
  })

  router.push('/solicitud/paso-7')
}

const prevStep = () => router.push('/solicitud/paso-5')
</script>

<template>
  <div class="px-4 py-6">
    <div class="max-w-md mx-auto">
      <h1 class="text-2xl font-bold text-gray-900 mb-2">Sube tus documentos</h1>
      <p class="text-gray-500 mb-6">Necesitamos verificar tu identidad e información.</p>

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
            :loading="applicationStore.isSaving"
            @click="handleSubmit"
          >
            Continuar →
          </AppButton>
        </div>
      </div>
    </div>
  </div>
</template>
