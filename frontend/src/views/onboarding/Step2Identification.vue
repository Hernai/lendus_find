<script setup lang="ts">
import { reactive, computed, watch, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useOnboardingStore } from '@/stores'
import { AppButton, AppInput, AppRadioGroup } from '@/components/common'

const router = useRouter()
const onboardingStore = useOnboardingStore()

const form = reactive({
  id_type: 'INE' as 'INE' | 'PASSPORT',
  curp: '',
  rfc: '',
  // Campos adicionales de INE
  clave_elector: '',
  numero_ocr: '',
  folio_ine: '',
  // Campos de Pasaporte
  passport_number: '',
  passport_issue_date: '',
  passport_expiry_date: ''
})

const errors = reactive({
  id_type: '',
  curp: '',
  rfc: '',
  clave_elector: '',
  numero_ocr: '',
  folio_ine: '',
  passport_number: '',
  passport_issue_date: '',
  passport_expiry_date: ''
})

// Mostrar campos según tipo de ID
const showIneFields = computed(() => form.id_type === 'INE')
const showPassportFields = computed(() => form.id_type === 'PASSPORT')

const idTypeOptions = [
  { value: 'INE', label: 'INE/IFE' },
  { value: 'PASSPORT', label: 'Pasaporte' }
]

// CURP validation: 18 characters
const validateCurp = (curp: string): boolean => {
  const curpRegex = /^[A-Z]{4}\d{6}[HM][A-Z]{5}[A-Z\d]\d$/
  return curpRegex.test(curp.toUpperCase())
}

// RFC validation: 12 (moral) or 13 (physical) characters
const validateRfc = (rfc: string): boolean => {
  const rfcRegex = /^[A-ZÑ&]{3,4}\d{6}[A-Z\d]{3}$/
  return rfcRegex.test(rfc.toUpperCase())
}

// Validar Clave de Elector: 18 caracteres alfanuméricos
const validateClaveElector = (clave: string): boolean => {
  return /^[A-Z0-9]{18}$/.test(clave.toUpperCase())
}

// Validar OCR: 13 dígitos
const validateOcr = (ocr: string): boolean => {
  return /^\d{13}$/.test(ocr)
}

// Validar Folio INE: Entre 9 y 20 dígitos
const validateFolioIne = (folio: string): boolean => {
  return /^\d{9,20}$/.test(folio)
}

// Validar número de pasaporte mexicano: G + 8 dígitos o formato antiguo
const validatePassportNumber = (passport: string): boolean => {
  // Formato nuevo: G seguido de 8 dígitos
  // Formato antiguo: 9-10 caracteres alfanuméricos
  return /^[A-Z]\d{8}$/.test(passport.toUpperCase()) || /^[A-Z0-9]{9,10}$/.test(passport.toUpperCase())
}

// Sync form from store on mount
onMounted(async () => {
  await onboardingStore.init()

  const step2 = onboardingStore.data.step2
  form.id_type = step2.id_type || 'INE'
  form.curp = step2.curp
  form.rfc = step2.rfc
  form.clave_elector = step2.clave_elector
  form.numero_ocr = step2.numero_ocr
  form.folio_ine = step2.folio_ine
  form.passport_number = step2.passport_number
  form.passport_issue_date = step2.passport_issue_date
  form.passport_expiry_date = step2.passport_expiry_date
})

// Auto-save to store when form changes
watch(form, () => {
  onboardingStore.updateStepData('step2', {
    id_type: form.id_type,
    curp: form.curp,
    rfc: form.rfc,
    clave_elector: form.clave_elector,
    numero_ocr: form.numero_ocr,
    folio_ine: form.folio_ine,
    passport_number: form.passport_number,
    passport_issue_date: form.passport_issue_date,
    passport_expiry_date: form.passport_expiry_date
  })
}, { deep: true })

// Validar que la fecha de expiración sea futura
const isDateFuture = (dateStr: string): boolean => {
  if (!dateStr) return false
  const date = new Date(dateStr)
  const today = new Date()
  today.setHours(0, 0, 0, 0)
  return date > today
}

// Validar que la fecha de emisión sea pasada
const isDatePast = (dateStr: string): boolean => {
  if (!dateStr) return false
  const date = new Date(dateStr)
  const today = new Date()
  today.setHours(0, 0, 0, 0)
  return date <= today
}

const validate = () => {
  let isValid = true

  // Reset errors
  Object.keys(errors).forEach(key => {
    errors[key as keyof typeof errors] = ''
  })

  if (!form.id_type) {
    errors.id_type = 'Selecciona un tipo de identificación'
    isValid = false
  }

  if (!form.curp.trim()) {
    errors.curp = 'La CURP es requerida'
    isValid = false
  } else if (!validateCurp(form.curp)) {
    errors.curp = 'CURP inválida (18 caracteres)'
    isValid = false
  }

  if (!form.rfc.trim()) {
    errors.rfc = 'El RFC es requerido'
    isValid = false
  } else if (!validateRfc(form.rfc)) {
    errors.rfc = 'RFC inválido (12-13 caracteres)'
    isValid = false
  }

  // Validar campos de INE solo si el tipo es INE
  if (form.id_type === 'INE') {
    if (!form.clave_elector.trim()) {
      errors.clave_elector = 'La clave de elector es requerida'
      isValid = false
    } else if (!validateClaveElector(form.clave_elector)) {
      errors.clave_elector = 'Clave inválida (18 caracteres alfanuméricos)'
      isValid = false
    }

    if (!form.numero_ocr.trim()) {
      errors.numero_ocr = 'El número OCR es requerido'
      isValid = false
    } else if (!validateOcr(form.numero_ocr)) {
      errors.numero_ocr = 'OCR inválido (13 dígitos)'
      isValid = false
    }

    if (!form.folio_ine.trim()) {
      errors.folio_ine = 'El folio es requerido'
      isValid = false
    } else if (!validateFolioIne(form.folio_ine)) {
      errors.folio_ine = 'Folio inválido (9-20 dígitos)'
      isValid = false
    }
  }

  // Validar campos de Pasaporte
  if (form.id_type === 'PASSPORT') {
    if (!form.passport_number.trim()) {
      errors.passport_number = 'El número de pasaporte es requerido'
      isValid = false
    } else if (!validatePassportNumber(form.passport_number)) {
      errors.passport_number = 'Número de pasaporte inválido'
      isValid = false
    }

    if (!form.passport_issue_date) {
      errors.passport_issue_date = 'La fecha de emisión es requerida'
      isValid = false
    } else if (!isDatePast(form.passport_issue_date)) {
      errors.passport_issue_date = 'La fecha debe ser anterior a hoy'
      isValid = false
    }

    if (!form.passport_expiry_date) {
      errors.passport_expiry_date = 'La fecha de vencimiento es requerida'
      isValid = false
    } else if (!isDateFuture(form.passport_expiry_date)) {
      errors.passport_expiry_date = 'El pasaporte debe estar vigente'
      isValid = false
    }
  }

  return isValid
}

const handleSubmit = async () => {
  if (!validate()) return

  try {
    // Normalize to uppercase before saving
    onboardingStore.updateStepData('step2', {
      id_type: form.id_type,
      curp: form.curp.toUpperCase(),
      rfc: form.rfc.toUpperCase(),
      clave_elector: form.clave_elector.toUpperCase(),
      numero_ocr: form.numero_ocr,
      folio_ine: form.folio_ine,
      passport_number: form.passport_number.toUpperCase(),
      passport_issue_date: form.passport_issue_date,
      passport_expiry_date: form.passport_expiry_date
    })

    // Save step 2 explicitly
    await onboardingStore.completeStep(2)
    router.push('/solicitud/paso-3')
  } catch (e) {
    console.error('Failed to save step 2:', e)
  }
}

const prevStep = () => router.push('/solicitud/paso-1')
</script>

<template>
  <div class="px-4 py-6">
    <div class="max-w-md mx-auto">
      <h1 class="text-2xl font-bold text-gray-900 mb-2">Tu identificación</h1>
      <p class="text-gray-500 mb-6">Necesitamos estos datos para verificar tu identidad.</p>

      <!-- Loading state -->
      <div v-if="onboardingStore.isLoading" class="flex justify-center py-8">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600"></div>
      </div>

      <form v-else class="space-y-5" @submit.prevent="handleSubmit">
        <AppRadioGroup
          v-model="form.id_type"
          :options="idTypeOptions"
          label="Tipo de identificación"
          :error="errors.id_type"
          required
        />

        <AppInput
          v-model="form.curp"
          label="CURP"
          placeholder="XXXX000000XXXXXX00"
          :error="errors.curp"
          :maxlength="18"
          uppercase
          required
        />
        <p class="text-xs text-gray-400 -mt-3">
          <a href="https://www.gob.mx/curp/" target="_blank" class="text-primary-600 hover:underline">Consultar CURP</a>
        </p>

        <AppInput
          v-model="form.rfc"
          label="RFC"
          placeholder="XXXX000000XXX"
          :error="errors.rfc"
          :maxlength="13"
          uppercase
          required
        />
        <p class="text-xs text-gray-400 -mt-3">
          12-13 caracteres con homoclave
        </p>

        <!-- Campos adicionales de INE -->
        <template v-if="showIneFields">
          <div class="border-t pt-5 mt-6">
            <h3 class="font-medium text-gray-900 mb-4 flex items-center gap-2">
              <svg class="w-5 h-5 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2" />
              </svg>
              Datos de tu INE/IFE
            </h3>

            <!-- Ayuda visual INE -->
            <div class="bg-blue-50 rounded-xl p-3 mb-4 flex gap-3">
              <svg class="w-5 h-5 text-blue-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              <div class="text-xs text-blue-800">
                <p class="font-medium mb-1">Estos datos los encuentras al reverso de tu INE</p>
                <p>Asegurate de que tu INE esté vigente y los datos sean legibles.</p>
              </div>
            </div>

            <div class="space-y-5">
              <div>
                <AppInput
                  v-model="form.clave_elector"
                  label="Clave de Elector"
                  placeholder="ABCDEF12345678ABCD"
                  :error="errors.clave_elector"
                  :maxlength="18"
                  uppercase
                  required
                />
                <div class="mt-1 space-y-1">
                  <p class="text-xs text-gray-500">
                    18 caracteres alfanuméricos (letras y números)
                  </p>
                  <p class="text-xs text-gray-400">
                    Ejemplo: GRPRLR80010509H100
                  </p>
                </div>
              </div>

              <div>
                <AppInput
                  v-model="form.numero_ocr"
                  label="Número OCR"
                  placeholder="0000000000000"
                  :error="errors.numero_ocr"
                  :maxlength="13"
                  inputmode="numeric"
                  required
                />
                <div class="mt-1 space-y-1">
                  <p class="text-xs text-gray-500">
                    13 dígitos debajo del código de barras
                  </p>
                  <p class="text-xs text-gray-400">
                    Ejemplo: 0123456789012
                  </p>
                </div>
              </div>

              <div>
                <AppInput
                  v-model="form.folio_ine"
                  label="Folio (CIC/ID Ciudadano)"
                  placeholder="000000000000"
                  :error="errors.folio_ine"
                  :maxlength="20"
                  inputmode="numeric"
                  required
                />
                <div class="mt-1 space-y-1">
                  <p class="text-xs text-gray-500">
                    9 a 20 dígitos. En INE nuevas busca "IDMEX" seguido del número
                  </p>
                  <p class="text-xs text-gray-400">
                    Ejemplo: 123456789012
                  </p>
                </div>
              </div>
            </div>
          </div>
        </template>

        <!-- Campos de Pasaporte -->
        <template v-if="showPassportFields">
          <div class="border-t pt-5 mt-6">
            <h3 class="font-medium text-gray-900 mb-4 flex items-center gap-2">
              <svg class="w-5 h-5 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
              </svg>
              Datos de tu Pasaporte
            </h3>

            <!-- Ayuda visual Pasaporte -->
            <div class="bg-blue-50 rounded-xl p-3 mb-4 flex gap-3">
              <svg class="w-5 h-5 text-blue-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              <div class="text-xs text-blue-800">
                <p class="font-medium mb-1">Estos datos los encuentras en la página de datos de tu pasaporte</p>
                <p>Asegurate de que tu pasaporte esté vigente.</p>
              </div>
            </div>

            <div class="space-y-5">
              <div>
                <AppInput
                  v-model="form.passport_number"
                  label="Número de Pasaporte"
                  placeholder="G12345678"
                  :error="errors.passport_number"
                  :maxlength="10"
                  uppercase
                  required
                />
                <div class="mt-1 space-y-1">
                  <p class="text-xs text-gray-500">
                    Formato: letra seguida de 8 dígitos (ej: G12345678)
                  </p>
                </div>
              </div>

              <div class="grid grid-cols-2 gap-4">
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">
                    Fecha de emisión <span class="text-red-500">*</span>
                  </label>
                  <input
                    v-model="form.passport_issue_date"
                    type="date"
                    class="w-full px-4 py-3 border rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                    :class="{
                      'border-gray-300': !errors.passport_issue_date,
                      'border-red-500': errors.passport_issue_date
                    }"
                  >
                  <p v-if="errors.passport_issue_date" class="mt-1 text-sm text-red-500">
                    {{ errors.passport_issue_date }}
                  </p>
                </div>

                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">
                    Fecha de vencimiento <span class="text-red-500">*</span>
                  </label>
                  <input
                    v-model="form.passport_expiry_date"
                    type="date"
                    class="w-full px-4 py-3 border rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                    :class="{
                      'border-gray-300': !errors.passport_expiry_date,
                      'border-red-500': errors.passport_expiry_date
                    }"
                  >
                  <p v-if="errors.passport_expiry_date" class="mt-1 text-sm text-red-500">
                    {{ errors.passport_expiry_date }}
                  </p>
                </div>
              </div>
            </div>
          </div>
        </template>

        <!-- Auto-save indicator -->
        <div v-if="onboardingStore.lastSavedAt" class="text-xs text-gray-400 text-right">
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
              type="submit"
              variant="primary"
              size="lg"
              class="flex-1"
              :loading="onboardingStore.isSaving"
            >
              Continuar →
            </AppButton>
          </div>
        </div>
      </form>
    </div>
  </div>
</template>
