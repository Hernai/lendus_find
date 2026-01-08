<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { AppButton } from '@/components/common'
import api from '@/services/api'

const router = useRouter()
const route = useRoute()

interface RejectedField {
  id: string
  field_name: string
  field_label: string
  current_value: string
  rejection_reason: string
  rejected_at: string
}

interface CorrectionsData {
  rejected_fields: RejectedField[]
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

// Currently editing field
const editingField = ref<string | null>(null)
const editValue = ref<string>('')

onMounted(async () => {
  await loadCorrections()
})

const loadCorrections = async () => {
  isLoading.value = true
  error.value = null

  try {
    const response = await api.get('/corrections')
    correctionsData.value = response.data.data
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string } } }
    error.value = err.response?.data?.message || 'Error al cargar las correcciones'
    console.error('Failed to load corrections:', e)
  } finally {
    isLoading.value = false
  }
}

const startEditing = (field: RejectedField) => {
  editingField.value = field.field_name
  editValue.value = field.current_value || ''
}

const cancelEditing = () => {
  editingField.value = null
  editValue.value = ''
}

const submitCorrection = async (field: RejectedField) => {
  if (!editValue.value.trim()) {
    error.value = 'Por favor ingresa un valor'
    return
  }

  isSaving.value = true
  error.value = null
  successMessage.value = null

  try {
    await api.post('/corrections', {
      field_name: field.field_name,
      new_value: editValue.value.trim()
    })

    successMessage.value = `${field.field_label} actualizado correctamente`
    editingField.value = null
    editValue.value = ''

    // Reload corrections
    await loadCorrections()
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string } } }
    error.value = err.response?.data?.message || 'Error al enviar la corrección'
    console.error('Failed to submit correction:', e)
  } finally {
    isSaving.value = false
  }
}

const rejectedFields = computed(() => correctionsData.value?.rejected_fields || [])
const hasCorrections = computed(() => rejectedFields.value.length > 0)

const formatDate = (dateString: string): string => {
  if (!dateString) return ''
  const date = new Date(dateString)
  return date.toLocaleDateString('es-MX', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}

const getFieldInputType = (fieldName: string): string => {
  if (fieldName === 'email') return 'email'
  if (fieldName === 'phone') return 'tel'
  if (fieldName === 'birth_date') return 'date'
  return 'text'
}

const getFieldPlaceholder = (fieldName: string): string => {
  const placeholders: Record<string, string> = {
    first_name: 'Nombre(s)',
    last_name_1: 'Apellido paterno',
    last_name_2: 'Apellido materno',
    curp: 'CURP (18 caracteres)',
    rfc: 'RFC (12-13 caracteres)',
    ine_clave: 'Clave de elector',
    phone: '10 dígitos',
    email: 'correo@ejemplo.com',
    birth_date: 'YYYY-MM-DD'
  }
  return placeholders[fieldName] || 'Nuevo valor'
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
          <h1 class="text-lg font-semibold text-gray-900">Correcciones Pendientes</h1>
          <p class="text-sm text-gray-500">Revisa y corrige los datos rechazados</p>
        </div>
      </div>
    </header>

    <!-- Main Content -->
    <main class="max-w-2xl mx-auto px-4 py-6">
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
        <div v-if="successMessage" class="bg-green-50 rounded-xl p-4 mb-6">
          <div class="flex gap-3">
            <svg class="w-5 h-5 text-green-500 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <p class="text-sm text-green-800">{{ successMessage }}</p>
          </div>
        </div>

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
        <div v-if="!hasCorrections" class="bg-white rounded-2xl shadow-sm p-8 text-center">
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

        <!-- Corrections List -->
        <div v-else class="space-y-4">
          <!-- Info Banner -->
          <div class="bg-amber-50 rounded-xl p-4 mb-6">
            <div class="flex gap-3">
              <svg class="w-5 h-5 text-amber-600 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
              </svg>
              <div class="text-sm text-amber-800">
                <p class="font-medium">Tienes {{ rejectedFields.length }} dato(s) que requieren corrección</p>
                <p class="text-amber-700 mt-1">Por favor revisa y corrige la información marcada para continuar con tu solicitud.</p>
              </div>
            </div>
          </div>

          <!-- Field Cards -->
          <div
            v-for="field in rejectedFields"
            :key="field.id"
            class="bg-white rounded-xl shadow-sm overflow-hidden"
          >
            <div class="p-4">
              <!-- Field Header -->
              <div class="flex items-start justify-between mb-3">
                <div>
                  <h3 class="font-medium text-gray-900">{{ field.field_label }}</h3>
                  <p class="text-xs text-gray-500 mt-0.5">Rechazado {{ formatDate(field.rejected_at) }}</p>
                </div>
                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-700">
                  <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                  </svg>
                  Rechazado
                </span>
              </div>

              <!-- Rejection Reason -->
              <div class="bg-red-50 rounded-lg p-3 mb-4">
                <p class="text-sm text-red-800">
                  <span class="font-medium">Motivo:</span> {{ field.rejection_reason }}
                </p>
              </div>

              <!-- Current Value -->
              <div class="mb-4">
                <p class="text-xs text-gray-500 mb-1">Valor actual:</p>
                <p class="text-sm text-gray-700 font-mono bg-gray-50 px-3 py-2 rounded-lg">
                  {{ field.current_value || '(vacío)' }}
                </p>
              </div>

              <!-- Edit Form -->
              <div v-if="editingField === field.field_name" class="border-t pt-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  Nuevo valor
                </label>
                <input
                  v-model="editValue"
                  :type="getFieldInputType(field.field_name)"
                  :placeholder="getFieldPlaceholder(field.field_name)"
                  class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                />
                <div class="flex gap-3 mt-4">
                  <AppButton
                    variant="outline"
                    size="sm"
                    class="flex-1"
                    :disabled="isSaving"
                    @click="cancelEditing"
                  >
                    Cancelar
                  </AppButton>
                  <AppButton
                    variant="primary"
                    size="sm"
                    class="flex-1"
                    :loading="isSaving"
                    @click="submitCorrection(field)"
                  >
                    Guardar
                  </AppButton>
                </div>
              </div>

              <!-- Edit Button -->
              <div v-else>
                <AppButton
                  variant="primary"
                  size="sm"
                  full-width
                  @click="startEditing(field)"
                >
                  <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                  </svg>
                  Corregir
                </AppButton>
              </div>
            </div>
          </div>
        </div>

        <!-- Back Button -->
        <div class="mt-6">
          <AppButton
            variant="outline"
            size="lg"
            full-width
            @click="goBack"
          >
            Volver al inicio
          </AppButton>
        </div>
      </template>
    </main>
  </div>
</template>
