<script setup lang="ts">
import { reactive, computed, watch, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useOnboardingStore, useKycStore, useAuthStore, useProfileStore } from '@/stores'
import { AppButton, AppInput, AppRadioGroup } from '@/components/common'
import LockedField from '@/components/common/LockedField.vue'
import { generarRFCDesdeKyc } from '@/services/rfc.service'
import { logger } from '@/utils/logger'

const log = logger.child('Step2Identification')

const router = useRouter()
const onboardingStore = useOnboardingStore()
const kycStore = useKycStore()
const authStore = useAuthStore()
const profileStore = useProfileStore()

// Check if KYC data is available (from INE OCR)
const hasKycData = computed(() => kycStore.verified && !!kycStore.lockedData.curp)

// Get verification info for fields (for showing method badges)
const getVerification = (field: string) => kycStore.getFieldVerification(field)

// RFC validation state (declared first so they can be used in computed)
const isValidatingRfc = ref(false)
const rfcValidated = ref(false)
const rfcIsValid = ref(false)
const rfcRazonSocial = ref<string | null>(null)
const rfcError = ref<string | null>(null)

// Check if RFC is already validated and locked
const rfcVerification = computed(() => kycStore.getFieldVerification('rfc'))
const isRfcLocked = computed(() => {
  // RFC is locked if:
  // 1. It has a KYC verification from backend (SAT validation)
  // 2. OR it was validated successfully in the current session
  const verification = rfcVerification.value
  const hasBackendVerification = verification?.is_locked === true || verification?.method === 'KYC_RFC_SAT'
  const validatedInSession = rfcValidated.value && rfcIsValid.value
  return hasBackendVerification || validatedInSession
})

// RFC suggestion state
const rfcSugerido = ref<string | null>(null)

// Submit error state
const submitError = ref('')

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
const validateRfcFormat = (rfc: string): boolean => {
  const rfcRegex = /^[A-ZÑ&]{3,4}\d{6}[A-Z\d]{3}$/
  return rfcRegex.test(rfc.toUpperCase())
}

// Validate RFC with SAT via Nubarium (called automatically)
const validateRfcWithSat = async () => {
  if (!validateRfcFormat(form.rfc)) {
    return
  }

  isValidatingRfc.value = true
  rfcError.value = null

  try {
    // Pass person_id if available for auto-recording
    const personId = profileStore.profile?.id
    const result = await kycStore.validateRfc(form.rfc, personId)
    rfcValidated.value = true
    rfcIsValid.value = result.valid
    rfcRazonSocial.value = result.razon_social || null

    if (!result.valid) {
      rfcError.value = result.error || 'RFC no registrado en SAT'
    }
  } catch (e) {
    rfcError.value = 'Error al validar RFC'
    rfcIsValid.value = false
    rfcValidated.value = true
  } finally {
    isValidatingRfc.value = false
  }
}

// Auto-validate RFC when it has valid format (debounced) - only if Nubarium is configured
let rfcValidationTimeout: ReturnType<typeof setTimeout> | null = null
watch(() => form.rfc, (newRfc) => {
  // Skip auto-validation if RFC is already locked (verified)
  if (isRfcLocked.value) {
    return
  }

  // Reset validation state
  rfcValidated.value = false
  rfcIsValid.value = false
  rfcRazonSocial.value = null
  rfcError.value = null

  // Clear previous timeout
  if (rfcValidationTimeout) {
    clearTimeout(rfcValidationTimeout)
  }

  // Auto-validate if format is valid and Nubarium is configured (with debounce)
  const canValidate = kycStore.hasNubarium && newRfc.length >= 12 && validateRfcFormat(newRfc)
  log.debug('RFC changed', { rfc: newRfc, length: newRfc.length, hasNubarium: kycStore.hasNubarium, formatValid: validateRfcFormat(newRfc), canValidate })

  if (canValidate) {
    rfcValidationTimeout = setTimeout(() => {
      log.debug('Triggering SAT validation', { rfc: newRfc })
      validateRfcWithSat()
    }, 500) // 500ms debounce
  }
})

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

  // Ensure KYC services are checked (needed for RFC auto-validation)
  await kycStore.checkServices()

  // Load KYC verifications if profile exists
  const personId = profileStore.profile?.id
  if (personId) {
    await kycStore.loadVerifications(personId)
  }

  const step2 = onboardingStore.data.step2

  // If KYC data is available, use it (takes priority over stored data)
  if (hasKycData.value) {
    form.id_type = 'INE' // KYC is always INE
    form.curp = kycStore.lockedData.curp || step2.curp
    form.clave_elector = kycStore.lockedData.clave_elector || step2.clave_elector
    form.numero_ocr = kycStore.lockedData.ocr || step2.numero_ocr
    form.folio_ine = kycStore.lockedData.cic || kycStore.lockedData.identificador_ciudadano || step2.folio_ine

    // Check if RFC is already verified (locked)
    const rfcVerified = kycStore.getFieldVerification('rfc')
    if (rfcVerified?.value) {
      // Use the verified RFC value (complete with homoclave)
      form.rfc = rfcVerified.value
      rfcValidated.value = true
      rfcIsValid.value = true
      log.debug('Using verified RFC from KYC', { rfc: form.rfc })
    } else if (
      // Generate RFC from INE data only if not already verified
      kycStore.lockedData.nombres &&
      kycStore.lockedData.apellido_paterno &&
      kycStore.lockedData.fecha_nacimiento
    ) {
      try {
        log.debug('Generating RFC from KYC data')
        const resultado = generarRFCDesdeKyc(
          kycStore.lockedData.nombres,
          kycStore.lockedData.apellido_paterno,
          kycStore.lockedData.apellido_materno || null,
          kycStore.lockedData.fecha_nacimiento
        )
        // Store full RFC suggestion (13 chars with homoclave)
        rfcSugerido.value = resultado.rfcSugerido

        // If RFC is NOT validated, only load first 10 characters (without homoclave)
        // This allows the user to complete/correct the homoclave
        const rfcBase = resultado.rfcSugerido.substring(0, 10)
        form.rfc = rfcBase
        log.debug('RFC auto-filled', { rfcBase: form.rfc, rfcSuggested: rfcSugerido.value })
      } catch (error) {
        log.error('Error generating RFC', { error })
        // Si falla, usar el valor guardado
        form.rfc = step2.rfc
      }
    } else {
      // Si no hay datos KYC suficientes, usar el guardado
      form.rfc = step2.rfc
    }
  } else {
    form.id_type = step2.id_type || 'INE'
    form.curp = step2.curp
    form.rfc = step2.rfc
    form.clave_elector = step2.clave_elector
    form.numero_ocr = step2.numero_ocr
    form.folio_ine = step2.folio_ine
  }

  // Passport fields (only if not using KYC)
  form.passport_number = step2.passport_number
  form.passport_issue_date = step2.passport_issue_date
  form.passport_expiry_date = step2.passport_expiry_date
})

// Función para usar la sugerencia de RFC
const usarRfcSugerido = () => {
  if (rfcSugerido.value) {
    form.rfc = rfcSugerido.value
    log.debug('Using suggested RFC', { rfc: rfcSugerido.value })
  }
}

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

  // If KYC verified, skip validation of locked fields (CURP, INE fields)
  if (hasKycData.value) {
    // Only validate RFC if it's not already locked/verified
    if (!isRfcLocked.value) {
      if (!form.rfc.trim()) {
        errors.rfc = 'El RFC es requerido'
        isValid = false
      } else if (!validateRfcFormat(form.rfc)) {
        errors.rfc = 'RFC inválido (12-13 caracteres)'
        isValid = false
      }
    }
    return isValid
  }

  // Normal validation when no KYC data
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
  } else if (!validateRfcFormat(form.rfc)) {
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

// Helper to extract error message from API response
const getErrorMessage = (e: unknown): string => {
  const error = e as { response?: { data?: { message?: string; errors?: Record<string, string[]> } }; message?: string }

  // Check for validation errors (422)
  const validationErrors = error.response?.data?.errors
  if (validationErrors) {
    const keys = Object.keys(validationErrors)
    const firstKey = keys[0]
    if (firstKey) {
      const messages = validationErrors[firstKey]
      return messages?.[0] || 'Error de validación'
    }
  }

  // Check for message in response
  if (error.response?.data?.message) {
    return error.response.data.message
  }

  // Fallback
  return error.message || 'Error al guardar. Intenta de nuevo.'
}

const handleSubmit = async () => {
  if (!validate()) return

  submitError.value = ''

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
    log.error('Failed to save step 2', { error: e })
    submitError.value = getErrorMessage(e)
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
        <!-- KYC Verified: Show locked fields -->
        <template v-if="hasKycData">
          <!-- Verified badge -->
          <div class="bg-green-50 border border-green-200 rounded-xl p-4 flex items-center gap-3 mb-2">
            <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center flex-shrink-0">
              <svg class="w-5 h-5 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 5.225-3.34 9.67-8 11.317C5.34 16.67 2 12.225 2 7c0-.682.057-1.35.166-2.001zm11.541 3.708a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
              </svg>
            </div>
            <div>
              <p class="text-sm font-medium text-green-800">Datos verificados de tu INE</p>
              <p class="text-xs text-green-600">Estos datos fueron extraídos automáticamente</p>
            </div>
          </div>

          <!-- ID Type locked to INE -->
          <LockedField
            label="Tipo de identificación"
            value="INE/IFE"
            :verified="true"
            :verification="getVerification('ine_document')"
          />

          <!-- CURP locked -->
          <LockedField
            label="CURP"
            :value="form.curp"
            format="curp"
            :verified="true"
            :verification="getVerification('curp')"
            hint="Validado con RENAPO"
          />

          <!-- RFC: Show as locked if already verified, otherwise editable -->
          <template v-if="isRfcLocked">
            <!-- RFC already verified and locked -->
            <LockedField
              label="RFC"
              :value="form.rfc"
              format="uppercase"
              :verified="true"
              :verification="rfcVerification"
              hint="Validado con SAT"
            />
          </template>
          <template v-else>
            <!-- RFC editable (not yet verified) -->
            <div class="border-t border-gray-200 my-4 pt-4">
              <p class="text-sm text-gray-500 mb-4">Completa la siguiente información:</p>
            </div>

            <div class="space-y-2">
              <!-- RFC Suggestion (if available from KYC) - only show if form.rfc differs from suggestion -->
              <div v-if="rfcSugerido && form.rfc !== rfcSugerido" class="bg-blue-50 border border-blue-200 rounded-lg p-3 mb-2">
                <div class="flex items-start justify-between">
                  <div class="flex-1">
                    <p class="text-xs font-medium text-blue-800 mb-1">RFC sugerido basado en tu INE:</p>
                    <p class="text-lg font-mono font-bold text-blue-900">{{ rfcSugerido }}</p>
                    <p class="text-xs text-blue-600 mt-1">
                      Calculado con el algoritmo oficial del SAT
                    </p>
                  </div>
                  <button
                    type="button"
                    @click="usarRfcSugerido"
                    class="ml-2 px-3 py-1.5 bg-blue-600 text-white text-xs font-medium rounded-md hover:bg-blue-700 transition-colors"
                  >
                    Usar
                  </button>
                </div>
              </div>

              <div class="relative">
                <AppInput
                  v-model="form.rfc"
                  label="RFC"
                  placeholder="XXXX000000XXX"
                  :error="errors.rfc"
                  :maxlength="13"
                  uppercase
                  required
                />
                <!-- Validation indicator inside input area (only if Nubarium configured) -->
                <div v-if="kycStore.hasNubarium" class="absolute right-3 top-9 flex items-center">
                  <svg v-if="isValidatingRfc" class="animate-spin h-5 w-5 text-primary-500" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                  </svg>
                  <svg v-else-if="rfcValidated && rfcIsValid" class="w-5 h-5 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                  </svg>
                  <svg v-else-if="rfcValidated && !rfcIsValid" class="w-5 h-5 text-red-500" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                  </svg>
                </div>
              </div>
              <p class="text-xs text-gray-400">
                Completa tu RFC con homoclave (3 caracteres adicionales). Si no la conoces, usa XXX.
              </p>
              <!-- SAT Validation result (only if Nubarium configured) -->
              <template v-if="kycStore.hasNubarium">
                <div v-if="rfcValidated && rfcIsValid && rfcRazonSocial" class="bg-green-50 border border-green-200 rounded-lg p-3">
                  <p class="text-xs text-green-700 font-medium">RFC validado con SAT</p>
                  <p class="text-sm text-green-800">{{ rfcRazonSocial }}</p>
                </div>
                <div v-else-if="rfcValidated && !rfcIsValid" class="bg-red-50 border border-red-200 rounded-lg p-3">
                  <p class="text-xs text-red-700">{{ rfcError || 'RFC no encontrado en SAT' }}</p>
                </div>
              </template>
            </div>
          </template>

          <!-- INE fields locked -->
          <div class="border-t pt-5 mt-4">
            <h3 class="font-medium text-gray-900 mb-4 flex items-center gap-2">
              <svg class="w-5 h-5 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
              </svg>
              Datos de tu INE/IFE
            </h3>

            <div class="space-y-3">
              <LockedField
                label="Clave de Elector"
                :value="form.clave_elector"
                format="uppercase"
                :verified="true"
                :verification="getVerification('ine_clave')"
              />
              <LockedField
                label="Número OCR"
                :value="form.numero_ocr"
                :verified="true"
                :verification="getVerification('ine_ocr')"
              />
              <LockedField
                label="Folio (CIC/ID Ciudadano)"
                :value="form.folio_ine"
                :verified="true"
                :verification="getVerification('ine_folio')"
              />
            </div>
          </div>
        </template>

        <!-- Normal flow: All fields editable -->
        <template v-else>
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

          <div class="space-y-2">
            <div class="relative">
              <AppInput
                v-model="form.rfc"
                label="RFC"
                placeholder="XXXX000000XXX"
                :error="errors.rfc"
                :maxlength="13"
                uppercase
                required
              />
              <!-- Validation indicator inside input area (only if Nubarium configured) -->
              <div v-if="kycStore.hasNubarium" class="absolute right-3 top-9 flex items-center">
                <svg v-if="isValidatingRfc" class="animate-spin h-5 w-5 text-primary-500" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <svg v-else-if="rfcValidated && rfcIsValid" class="w-5 h-5 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <svg v-else-if="rfcValidated && !rfcIsValid" class="w-5 h-5 text-red-500" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                </svg>
              </div>
            </div>
            <p class="text-xs text-gray-400">
              12-13 caracteres con homoclave
            </p>
            <!-- SAT Validation result (only if Nubarium configured) -->
            <template v-if="kycStore.hasNubarium">
              <div v-if="rfcValidated && rfcIsValid && rfcRazonSocial" class="bg-green-50 border border-green-200 rounded-lg p-3">
                <p class="text-xs text-green-700 font-medium">RFC validado con SAT</p>
                <p class="text-sm text-green-800">{{ rfcRazonSocial }}</p>
              </div>
              <div v-else-if="rfcValidated && !rfcIsValid" class="bg-red-50 border border-red-200 rounded-lg p-3">
                <p class="text-xs text-red-700">{{ rfcError || 'RFC no encontrado en SAT' }}</p>
              </div>
            </template>
          </div>

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
        </template>

        <!-- Campos de Pasaporte (only when not KYC verified) -->
        <template v-if="showPassportFields && !hasKycData">
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

        <!-- Error alert -->
        <div v-if="submitError" class="bg-red-50 border border-red-200 rounded-xl p-4 flex gap-3">
          <svg class="w-5 h-5 text-red-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <div class="flex-1">
            <p class="text-sm text-red-800 font-medium">{{ submitError }}</p>
            <button
              type="button"
              class="text-xs text-red-600 underline mt-1"
              @click="submitError = ''"
            >
              Cerrar
            </button>
          </div>
        </div>

        <!-- Auto-save indicator -->
        <div v-if="onboardingStore.isSaving || onboardingStore.lastSavedAt" class="text-xs text-right">
          <span v-if="onboardingStore.isSaving" class="text-primary-600 flex items-center justify-end gap-1">
            <svg class="animate-spin h-3 w-3" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            Guardando...
          </span>
          <span v-else class="text-gray-400">
            ✓ Guardado automáticamente
          </span>
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
