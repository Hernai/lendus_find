<script setup lang="ts">
import { reactive, ref, computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useOnboardingStore, useApplicationStore, useTenantStore, useKycStore, useApplicantStore } from '@/stores'
import { AppButton, AppInput, AppRadioGroup, AppSelect, AppDatePicker } from '@/components/common'
import LockedField from '@/components/common/LockedField.vue'
import type { PaymentFrequency } from '@/types'

const router = useRouter()
const onboardingStore = useOnboardingStore()
const applicationStore = useApplicationStore()
const tenantStore = useTenantStore()
const kycStore = useKycStore()
const applicantStore = useApplicantStore()

// Check if KYC is verified
const isKycVerified = computed(() => kycStore.verified && !!kycStore.lockedData.curp)

// Get verification info for fields (for showing method badges)
const getVerification = (field: string) => kycStore.getFieldVerification(field)

// Local form state (reactive copy from store)
const form = reactive({
  first_name: '',
  last_name: '',
  second_last_name: '',
  birth_date: '',
  gender: '' as 'M' | 'F' | '',
  is_mexican: '' as 'SI' | 'NO' | '',
  birth_state: '',
  nationality: ''
})

const errors = reactive({
  first_name: '',
  last_name: '',
  birth_date: '',
  gender: '',
  is_mexican: '',
  birth_state: '',
  nationality: ''
})

const submitError = ref('')

// Sync form from store on mount
onMounted(async () => {
  await onboardingStore.init()

  // Load KYC verifications if applicant exists (to restore KYC state)
  const applicantId = applicantStore.applicant?.id
  console.log('[Step1] Applicant ID:', applicantId)

  if (applicantId) {
    await kycStore.loadVerifications(applicantId)
    console.log('[Step1] After loadVerifications:')
    console.log('[Step1] - verified:', kycStore.verified)
    console.log('[Step1] - lockedData.curp:', kycStore.lockedData.curp)
    console.log('[Step1] - isKycVerified computed:', isKycVerified.value)
  }

  const step1 = onboardingStore.data.step1

  // If KYC is verified, use locked data from KYC store
  console.log('[Step1] Evaluating isKycVerified:', isKycVerified.value)
  console.log('[Step1] kycStore.verified:', kycStore.verified)
  console.log('[Step1] kycStore.lockedData.curp:', kycStore.lockedData.curp)

  if (isKycVerified.value) {
    console.log('[Step1] Using KYC locked data for form')
    form.first_name = kycStore.lockedData.nombres || step1.first_name
    form.last_name = kycStore.lockedData.apellido_paterno || step1.last_name
    form.second_last_name = kycStore.lockedData.apellido_materno || step1.second_last_name
    form.birth_date = kycStore.lockedData.fecha_nacimiento || step1.birth_date
    // Convert KYC gender format (H/M) to form format (M/F)
    if (kycStore.lockedData.sexo === 'H') {
      form.gender = 'M'
    } else if (kycStore.lockedData.sexo === 'M') {
      form.gender = 'F'
    } else {
      form.gender = step1.gender
    }
    // KYC verified = Mexican by definition (INE)
    form.is_mexican = 'SI'
    // Use entidad de nacimiento from CURP if available
    form.birth_state = kycStore.lockedData.entidad_nacimiento || step1.birth_state
    form.nationality = 'MX'
  } else {
    console.log('[Step1] No KYC data, using step1 data')
    form.first_name = step1.first_name
    form.last_name = step1.last_name
    form.second_last_name = step1.second_last_name
    form.birth_date = step1.birth_date
    form.gender = step1.gender
    form.birth_state = step1.birth_state
    form.nationality = step1.nationality

    // Determine if mexican based on nationality
    if (step1.nationality === 'MX' || step1.nationality === '') {
      form.is_mexican = step1.first_name ? 'SI' : ''
    } else {
      form.is_mexican = 'NO'
    }
  }
})

// Auto-save to store when form changes (debounced via watch)
watch(form, () => {
  onboardingStore.updateStepData('step1', {
    first_name: form.first_name,
    last_name: form.last_name,
    second_last_name: form.second_last_name,
    birth_date: form.birth_date,
    birth_state: form.birth_state,
    gender: form.gender,
    nationality: form.is_mexican === 'SI' ? 'MX' : form.nationality,
    marital_status: onboardingStore.data.step1.marital_status || 'SOLTERO'
  })
}, { deep: true })

const genderOptions = [
  { value: 'M', label: 'Masculino' },
  { value: 'F', label: 'Femenino' }
]

const mexicanOptions = [
  { value: 'SI', label: 'Sí' },
  { value: 'NO', label: 'No' }
]

// Entidades federativas de México (códigos CURP)
const mexicanStates = [
  { value: 'AS', label: 'Aguascalientes' },
  { value: 'BC', label: 'Baja California' },
  { value: 'BS', label: 'Baja California Sur' },
  { value: 'CC', label: 'Campeche' },
  { value: 'CS', label: 'Chiapas' },
  { value: 'CH', label: 'Chihuahua' },
  { value: 'DF', label: 'Ciudad de México' },
  { value: 'CL', label: 'Coahuila' },
  { value: 'CM', label: 'Colima' },
  { value: 'DG', label: 'Durango' },
  { value: 'GT', label: 'Guanajuato' },
  { value: 'GR', label: 'Guerrero' },
  { value: 'HG', label: 'Hidalgo' },
  { value: 'JC', label: 'Jalisco' },
  { value: 'MC', label: 'Estado de México' },
  { value: 'MN', label: 'Michoacán' },
  { value: 'MS', label: 'Morelos' },
  { value: 'NT', label: 'Nayarit' },
  { value: 'NL', label: 'Nuevo León' },
  { value: 'OC', label: 'Oaxaca' },
  { value: 'PL', label: 'Puebla' },
  { value: 'QT', label: 'Querétaro' },
  { value: 'QR', label: 'Quintana Roo' },
  { value: 'SP', label: 'San Luis Potosí' },
  { value: 'SL', label: 'Sinaloa' },
  { value: 'SR', label: 'Sonora' },
  { value: 'TC', label: 'Tabasco' },
  { value: 'TS', label: 'Tamaulipas' },
  { value: 'TL', label: 'Tlaxcala' },
  { value: 'VZ', label: 'Veracruz' },
  { value: 'YN', label: 'Yucatán' },
  { value: 'ZS', label: 'Zacatecas' },
  { value: 'NE', label: 'Nacido en el Extranjero' }
]

// Países más comunes para extranjeros en México
const countries = [
  { value: 'US', label: 'Estados Unidos' },
  { value: 'GT', label: 'Guatemala' },
  { value: 'HN', label: 'Honduras' },
  { value: 'SV', label: 'El Salvador' },
  { value: 'VE', label: 'Venezuela' },
  { value: 'CO', label: 'Colombia' },
  { value: 'AR', label: 'Argentina' },
  { value: 'CU', label: 'Cuba' },
  { value: 'NI', label: 'Nicaragua' },
  { value: 'ES', label: 'España' },
  { value: 'PE', label: 'Perú' },
  { value: 'EC', label: 'Ecuador' },
  { value: 'BR', label: 'Brasil' },
  { value: 'CL', label: 'Chile' },
  { value: 'CA', label: 'Canadá' },
  { value: 'CN', label: 'China' },
  { value: 'IN', label: 'India' },
  { value: 'FR', label: 'Francia' },
  { value: 'DE', label: 'Alemania' },
  { value: 'IT', label: 'Italia' },
  { value: 'JP', label: 'Japón' },
  { value: 'KR', label: 'Corea del Sur' },
  { value: 'OTHER', label: 'Otro país' }
]

const isMexican = computed(() => form.is_mexican === 'SI')
const isForeigner = computed(() => form.is_mexican === 'NO')

const validate = () => {
  let isValid = true

  // If KYC is verified, locked fields are already validated
  if (!isKycVerified.value) {
    if (!form.first_name.trim()) {
      errors.first_name = 'El nombre es requerido'
      isValid = false
    } else {
      errors.first_name = ''
    }

    if (!form.last_name.trim()) {
      errors.last_name = 'El primer apellido es requerido'
      isValid = false
    } else {
      errors.last_name = ''
    }

    if (!form.birth_date) {
      errors.birth_date = 'La fecha de nacimiento es requerida'
      isValid = false
    } else {
      // Validate age (must be at least 18 years old)
      const birthDate = new Date(form.birth_date)
      const today = new Date()
      let age = today.getFullYear() - birthDate.getFullYear()
      const monthDiff = today.getMonth() - birthDate.getMonth()
      if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
        age--
      }
      if (age < 18) {
        errors.birth_date = 'Debes tener al menos 18 años para solicitar un crédito'
        isValid = false
      } else {
        errors.birth_date = ''
      }
    }

    if (!form.gender) {
      errors.gender = 'Selecciona tu género'
      isValid = false
    } else {
      errors.gender = ''
    }

    if (!form.is_mexican) {
      errors.is_mexican = 'Indica si eres mexicano'
      isValid = false
    } else {
      errors.is_mexican = ''
    }

    // Validar nacionalidad para extranjeros
    if (isForeigner.value && !form.nationality) {
      errors.nationality = 'Selecciona tu nacionalidad'
      isValid = false
    } else {
      errors.nationality = ''
    }
  }

  // Validar entidad de nacimiento para mexicanos (siempre requerido, incluso con KYC)
  if (isMexican.value && !form.birth_state) {
    errors.birth_state = 'Selecciona tu entidad de nacimiento'
    isValid = false
  } else {
    errors.birth_state = ''
  }

  return isValid
}

// Helper to extract error message from API response
const getErrorMessage = (e: unknown): string => {
  const error = e as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }
  const validationErrors = error.response?.data?.errors
  if (validationErrors) {
    // Get first validation error
    const keys = Object.keys(validationErrors)
    const firstKey = keys[0]
    if (firstKey) {
      const messages = validationErrors[firstKey]
      // Translate common backend messages
      const msg = messages?.[0] || ''
      if (msg.includes('before') && msg.includes('18 years')) {
        return 'Debes tener al menos 18 años para solicitar un crédito'
      }
      if (msg.includes('required')) {
        return 'Faltan campos requeridos'
      }
      return msg || 'Error de validación'
    }
  }
  return error.response?.data?.message || 'Error al guardar. Intenta de nuevo.'
}

const handleSubmit = async () => {
  if (!validate()) return

  submitError.value = ''

  try {
    // Save step 1 explicitly - this creates the applicant record
    await onboardingStore.completeStep(1)
    console.log('✅ Step 1 completed - applicant record created')

    // Reload the applicant to get the ID (if it was just created)
    await applicantStore.loadApplicant()
    const newApplicantId = applicantStore.applicant?.id

    // If KYC was done but verifications weren't recorded (because applicant didn't exist),
    // record them now. This handles the case of new users doing KYC before Step 1.
    if (newApplicantId && kycStore.lockedData.curp && !kycStore.verifiedFields.curp) {
      console.log('📝 Recording pending KYC verifications for new applicant:', newApplicantId)
      await kycStore.recordVerifications(newApplicantId)
    }

    // ALWAYS create application if it doesn't exist (now that applicant exists)
    if (!applicationStore.currentApplication) {
      console.log('📝 No current application, creating one...')

      // Check if there's a pending application from the simulator
      const pendingApp = localStorage.getItem('pending_application')
      let params: {
        product_id: string
        requested_amount: number
        term_months: number
        payment_frequency: PaymentFrequency
      }

      if (pendingApp) {
        // Use pending application params
        console.log('📝 Using pending application params')
        params = JSON.parse(pendingApp)
      } else {
        // Use default params with first active product
        console.log('📝 Using default params')
        const product = applicationStore.selectedProduct || tenantStore.activeProducts[0]

        if (!product) {
          console.error('❌ No products available to create application')
          router.push('/solicitud/paso-2')
          return
        }

        params = {
          product_id: product.id,
          requested_amount: product.rules.min_amount || 10000,
          term_months: product.rules.min_term_months || 12,
          payment_frequency: 'MONTHLY'
        }

        // Set the selected product
        applicationStore.setSelectedProduct(product)
      }

      try {
        // Run simulation first to populate store
        await applicationStore.runSimulation({
          product_id: params.product_id,
          amount: params.requested_amount,
          term_months: params.term_months,
          payment_frequency: params.payment_frequency
        })

        // Create the application (now that applicant exists)
        const newApp = await applicationStore.createApplication({
          product_id: params.product_id,
          requested_amount: params.requested_amount,
          term_months: params.term_months,
          payment_frequency: params.payment_frequency
        })

        console.log('✅ Application created after step 1:', newApp?.id)

        // KYC verifications are now automatically recorded by the backend
        // when CURP/INE validation succeeds - no need to call recordVerifications

        // Clear pending application if it existed
        if (pendingApp) {
          localStorage.removeItem('pending_application')
        }
      } catch (createError) {
        console.error('❌ Failed to create application after step 1:', createError)
        submitError.value = 'No se pudo crear la solicitud. Por favor intenta de nuevo.'
        return
      }
    }

    router.push('/solicitud/paso-2')
  } catch (e) {
    console.error('Failed to save step 1:', e)
    submitError.value = getErrorMessage(e)
  }
}
</script>

<template>
  <div class="px-4 py-6">
    <div class="max-w-md mx-auto">
      <h1 class="text-2xl font-bold text-gray-900 mb-6">¿Cómo te llamas?</h1>

      <!-- Loading state -->
      <div v-if="onboardingStore.isLoading" class="flex justify-center py-8">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600"></div>
      </div>

      <form v-else class="space-y-4" @submit.prevent="handleSubmit">
        <!-- KYC Verified: Show locked fields -->
        <template v-if="isKycVerified">
          <!-- Verified badge -->
          <div class="bg-green-50 border border-green-200 rounded-xl p-4 flex items-center gap-3 mb-2">
            <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center flex-shrink-0">
              <svg class="w-5 h-5 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 5.225-3.34 9.67-8 11.317C5.34 16.67 2 12.225 2 7c0-.682.057-1.35.166-2.001zm11.541 3.708a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
              </svg>
            </div>
            <div>
              <p class="text-sm font-medium text-green-800">Identidad verificada</p>
              <p class="text-xs text-green-600">Tus datos fueron extraídos de tu INE</p>
            </div>
          </div>

          <!-- Locked personal data fields -->
          <div class="space-y-3">
            <LockedField
              label="Nombre(s)"
              :value="form.first_name"
              format="uppercase"
              :verification="getVerification('first_name')"
            />
            <div class="grid grid-cols-2 gap-3">
              <LockedField
                label="Primer Apellido"
                :value="form.last_name"
                format="uppercase"
                :verification="getVerification('last_name_1')"
                :show-method="false"
              />
              <LockedField
                label="Segundo Apellido"
                :value="form.second_last_name"
                format="uppercase"
                :verification="getVerification('last_name_2')"
                :show-method="false"
              />
            </div>
            <LockedField
              label="Fecha de nacimiento"
              :value="form.birth_date"
              format="date"
              :verification="getVerification('birth_date')"
            />
            <LockedField
              label="Género"
              :value="form.gender === 'M' ? 'Masculino' : form.gender === 'F' ? 'Femenino' : '-'"
              :verification="getVerification('gender')"
            />
            <LockedField
              label="CURP"
              :value="kycStore.lockedData.curp"
              format="curp"
              :verification="getVerification('curp')"
            />
          </div>

          <!-- Entidad de nacimiento: locked if from CURP, editable otherwise -->
          <LockedField
            v-if="kycStore.lockedData.entidad_nacimiento"
            label="Entidad de nacimiento"
            :value="mexicanStates.find(s => s.value === form.birth_state)?.label || form.birth_state"
            format="uppercase"
            :verified="true"
            :verification="getVerification('birth_state')"
            hint="Extraído de tu CURP"
          />
          <template v-else>
            <!-- Divider -->
            <div class="border-t border-gray-200 my-6 pt-4">
              <p class="text-sm text-gray-500 mb-4">Completa la siguiente información:</p>
            </div>

            <!-- Editable: Birth state (not in INE OCR usually) -->
            <AppSelect
              v-model="form.birth_state"
              :options="mexicanStates"
              label="Entidad de nacimiento"
              placeholder="Selecciona tu estado"
              :error="errors.birth_state"
              required
            />
          </template>
        </template>

        <!-- Normal flow: All fields editable -->
        <template v-else>
          <AppInput
            v-model="form.first_name"
            label="Nombre(s)"
            placeholder="JUAN CARLOS"
            :error="errors.first_name"
            uppercase
            required
          />

          <div class="grid grid-cols-2 gap-3">
            <AppInput
              v-model="form.last_name"
              label="Primer Apellido"
              placeholder="PÉREZ"
              :error="errors.last_name"
              uppercase
              required
            />
            <AppInput
              v-model="form.second_last_name"
              label="Segundo Apellido"
              placeholder="GARCÍA"
              uppercase
            />
          </div>

          <AppDatePicker
            v-model="form.birth_date"
            label="Fecha de nacimiento"
            placeholder="Selecciona tu fecha"
            :error="errors.birth_date"
            hint="Debes tener al menos 18 años"
            required
          />

          <AppRadioGroup
            v-model="form.gender"
            :options="genderOptions"
            label="Género"
            :error="errors.gender"
            required
          />

          <!-- Nacionalidad -->
          <AppRadioGroup
            v-model="form.is_mexican"
            :options="mexicanOptions"
            label="¿Eres mexicano por nacimiento?"
            :error="errors.is_mexican"
            required
          />
        </template>

        <!-- Entidad de nacimiento (solo mexicanos y sin KYC verificado) -->
        <AppSelect
          v-if="isMexican && !isKycVerified"
          v-model="form.birth_state"
          :options="mexicanStates"
          label="Entidad de nacimiento"
          placeholder="Selecciona tu estado"
          :error="errors.birth_state"
          required
        />

        <!-- Nacionalidad (solo extranjeros) -->
        <AppSelect
          v-if="isForeigner && !isKycVerified"
          v-model="form.nationality"
          :options="countries"
          label="Nacionalidad"
          placeholder="Selecciona tu país de origen"
          :error="errors.nationality"
          required
        />

        <!-- Nota informativa para extranjeros -->
        <div v-if="isForeigner && !isKycVerified" class="bg-blue-50 rounded-xl p-4 flex gap-3">
          <svg class="w-5 h-5 text-blue-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <div class="text-sm text-blue-800">
            <p class="font-medium">Documentación adicional</p>
            <p class="text-blue-700 mt-1">
              Como extranjero necesitarás presentar tu FM2/FM3 o tarjeta de residente vigente.
            </p>
          </div>
        </div>

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
            Guardado
          </span>
        </div>

        <!-- Sticky Footer -->
        <div class="fixed bottom-0 left-0 right-0 p-4 bg-white border-t">
          <div class="max-w-md mx-auto">
            <AppButton
              type="submit"
              variant="primary"
              size="lg"
              full-width
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
