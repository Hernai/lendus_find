<script setup lang="ts">
import { reactive, ref, computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useOnboardingStore, useApplicationStore, useTenantStore, useKycStore, useProfileStore } from '@/stores'
import { AppButton, AppInput, AppRadioGroup, AppSelect, AppDatePicker } from '@/components/common'
import LockedField from '@/components/common/LockedField.vue'
import { logger } from '@/utils/logger'
import { MEXICAN_STATES, COUNTRIES, YES_NO_OPTIONS } from '@/constants'
import type { PaymentFrequency } from '@/types'

const log = logger.child('Step1PersonalData')

const router = useRouter()
const onboardingStore = useOnboardingStore()
const applicationStore = useApplicationStore()
const tenantStore = useTenantStore()
const kycStore = useKycStore()
const profileStore = useProfileStore()

// Check if KYC is verified
const isKycVerified = computed(() => kycStore.verified && !!kycStore.lockedData.curp)

// Get verification info for fields (for showing method badges)
const getVerification = (field: string) => kycStore.getFieldVerification(field)

// Check if birth_state is verified/locked
const isBirthStateLocked = computed(() => {
  const verification = kycStore.getFieldVerification('birth_state')
  return verification?.is_locked === true || !!kycStore.lockedData.entidad_nacimiento
})

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

// Fusión con la confirmación de identidad: si el INE ya verificó al usuario, no
// re-presentamos los datos personales (nombre, CURP, fecha, sexo y entidad ya vienen
// de INE+CURP). No queda nada por capturar, así que saltamos este paso de forma
// transparente y avanzamos al siguiente. Solo si el CURP no trae entidad de
// nacimiento pedimos ese único dato.
const autoSubmitting = ref(false)
const autoSubmitted = ref(false)
const needsBirthState = computed(() => isKycVerified.value && !form.birth_state)
const canAutoSkip = computed(() => isKycVerified.value && !needsBirthState.value)

// Sync form from store on mount
onMounted(async () => {
  // Si el INE ya verificó en esta sesión, encendemos el spinner de inmediato para
  // que el salto sea transparente (sin parpadear el resumen durante los awaits de
  // init/loadVerifications). Si al final no aplica auto-skip, se apaga abajo.
  if (isKycVerified.value) autoSubmitting.value = true

  await onboardingStore.init()

  // Load KYC verifications if profile exists (to restore KYC state)
  const personId = profileStore.profile?.id
  log.debug('Person ID from profile', { personId })

  if (personId) {
    await kycStore.loadVerifications(personId)
    log.debug('After loadVerifications', {
      verified: kycStore.verified,
      lockedDataCurp: kycStore.lockedData.curp,
      isKycVerified: isKycVerified.value
    })
  }

  const step1 = onboardingStore.data.step1

  // If KYC is verified, use locked data from KYC store
  log.debug('Evaluating KYC state', {
    isKycVerified: isKycVerified.value,
    kycStoreVerified: kycStore.verified,
    lockedDataCurp: kycStore.lockedData.curp
  })

  if (isKycVerified.value) {
    // Optimista: si el INE ya verificó, casi seguro saltamos este paso. Encendemos
    // el spinner desde ya (misma tick, sin await intermedio) para no parpadear el
    // resumen/formulario antes de decidir el auto-skip.
    autoSubmitting.value = true
    log.debug('Using KYC locked data for form')
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
    log.debug('No KYC data, using step1 data')
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

  // Si el INE ya verificó al usuario y no falta ningún dato, saltamos este paso de
  // forma transparente: creamos perfil + solicitud y avanzamos, sin mostrar el
  // formulario redundante ("¿Cómo te llamas?").
  if (canAutoSkip.value && !autoSubmitted.value) {
    autoSubmitted.value = true
    autoSubmitting.value = true
    // Persistimos step1 (incl. birth_state) antes de crear perfil/solicitud, sin
    // depender del watch asíncrono.
    onboardingStore.updateStepData('step1', {
      first_name: form.first_name,
      last_name: form.last_name,
      second_last_name: form.second_last_name,
      birth_date: form.birth_date,
      birth_state: form.birth_state,
      gender: form.gender,
      nationality: form.is_mexican === 'SI' ? 'MX' : form.nationality,
      marital_status: onboardingStore.data.step1.marital_status || ''
    })
    await handleSubmit()
    // En éxito, handleSubmit navega a /solicitud/paso-2 y este componente se
    // desmonta: NO apagamos el spinner (evita el parpadeo del resumen justo antes
    // de navegar). Solo lo apagamos si falló (submitError) para el fallback manual.
    if (submitError.value) autoSubmitting.value = false
  } else {
    // Sin auto-skip (no hay KYC, o falta la entidad de nacimiento): apagamos el
    // spinner optimista para mostrar el formulario/campo correspondiente.
    autoSubmitting.value = false
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
    marital_status: onboardingStore.data.step1.marital_status || ''
  })
}, { deep: true })

// Get gender options from backend enum
const genderOptions = computed(() => tenantStore.options.gender)

// Options from shared constants
const mexicanOptions = YES_NO_OPTIONS
const mexicanStates = MEXICAN_STATES
const countries = COUNTRIES

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

    // Validate nationality for foreigners
    if (isForeigner.value && !form.nationality) {
      errors.nationality = 'Selecciona tu nacionalidad'
      isValid = false
    } else {
      errors.nationality = ''
    }
  }

  // Validate birth state for Mexicans (always required, even with KYC)
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
    // Save step 1 explicitly - this creates/updates the profile record via V2 API
    await onboardingStore.completeStep(1)
    log.debug('Step 1 completed - profile record created/updated')

    // Reload the profile to get the ID (if it was just created)
    await profileStore.loadProfile()
    const personId = profileStore.profile?.id

    // If KYC was done but verifications weren't recorded (because profile didn't exist),
    // record them now. This handles the case of new users doing KYC before Step 1.
    if (personId && kycStore.lockedData.curp && !kycStore.verifiedFields.curp) {
      log.debug('Recording pending KYC verifications for new profile', { personId })
      await kycStore.recordVerifications(personId)
    }

    // ALWAYS create application if it doesn't exist (now that applicant exists)
    if (!applicationStore.currentApplication) {
      log.debug('No current application, creating one...')

      // Check if there's a pending application from the simulator
      const pendingApp = localStorage.getItem('pending_application')
      let params: {
        product_id: string
        requested_amount: number
        term_months: number
        requested_term_days?: number
        payment_frequency: PaymentFrequency
      }

      if (pendingApp) {
        // Use pending application params
        log.debug('Using pending application params')
        params = JSON.parse(pendingApp)
      } else {
        // Use default params with first active product
        log.debug('Using default params')
        const product = applicationStore.selectedProduct || tenantStore.activeProducts[0]

        if (!product) {
          log.error('No products available to create application')
          router.push('/solicitud/paso-2')
          return
        }

        params = {
          product_id: product.id,
          requested_amount: product.min_amount || product.rules?.min_amount || 10000,
          term_months: product.min_term_months || product.rules?.min_term_months || 12,
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
          term_days: params.requested_term_days,
          payment_frequency: params.payment_frequency
        })

        // Create the application (now that applicant exists)
        const newApp = await applicationStore.createApplication({
          product_id: params.product_id,
          requested_amount: params.requested_amount,
          term_months: params.term_months,
          requested_term_days: params.requested_term_days,
          payment_frequency: params.payment_frequency
        })

        log.debug('Application created after step 1', { appId: newApp?.id })

        // Arrendamiento: volcar el activo elegido en el simulador (buffer) + la
        // modalidad del producto a applications.metadata.lease. El analista lo lee
        // de ahí (LeaseInfoSection). Solo para productos ARRENDAMIENTO; no toca crédito.
        const leaseProduct = applicationStore.selectedProduct
        const assetType = applicationStore.selectedAssetType
        if (leaseProduct?.type === 'ARRENDAMIENTO' && assetType) {
          try {
            await applicationStore.updateApplication({
              metadata: { lease: { asset_type: assetType, modality: leaseProduct.rules?.lease?.modality } },
            } as never)
          } catch (e) {
            log.warn('No se pudo guardar metadata.lease', { error: e })
          }
        }

        // KYC verifications are now automatically recorded by the backend
        // when CURP/INE validation succeeds - no need to call recordVerifications

        // Clear pending application if it existed
        if (pendingApp) {
          localStorage.removeItem('pending_application')
        }
      } catch (createError) {
        log.error('Failed to create application after step 1', { error: createError })
        submitError.value = 'No se pudo crear la solicitud. Por favor intenta de nuevo.'
        return
      }
    }

    router.push('/solicitud/paso-2')
  } catch (e) {
    log.error('Failed to save step 1', { error: e })
    submitError.value = getErrorMessage(e)
  }
}
</script>

<template>
  <div class="px-4 py-6">
    <div class="max-w-md mx-auto">
      <h1 v-if="!autoSubmitting" class="text-2xl font-bold text-gray-900 mb-6">¿Cómo te llamas?</h1>

      <!-- Loading state -->
      <div v-if="onboardingStore.isLoading" class="flex justify-center py-8">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600"></div>
      </div>

      <form v-else class="space-y-4" @submit.prevent="handleSubmit">
        <!-- KYC verificado: fusión con la confirmación de identidad. No re-mostramos
             los datos ya confirmados; si no falta nada, saltamos de forma transparente. -->
        <template v-if="isKycVerified">
          <!-- Preparando: creando perfil + solicitud antes de avanzar -->
          <div v-if="autoSubmitting" class="flex flex-col items-center justify-center py-16">
            <div class="animate-spin rounded-full h-10 w-10 border-b-2 border-primary-600 mb-4"></div>
            <p class="text-gray-600 text-sm">Preparando tu solicitud...</p>
          </div>

          <template v-else>
            <!-- Identidad verificada: resumen mínimo, sin re-captura -->
            <div class="bg-green-50 border border-green-200 rounded-xl p-4 flex items-center gap-3">
              <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 5.225-3.34 9.67-8 11.317C5.34 16.67 2 12.225 2 7c0-.682.057-1.35.166-2.001zm11.541 3.708a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
              </div>
              <div>
                <p class="text-sm font-medium text-green-800">Identidad verificada</p>
                <p class="text-xs text-green-600">{{ form.first_name }} {{ form.last_name }} {{ form.second_last_name }}</p>
              </div>
            </div>

            <!-- Solo pedimos lo que realmente falta (entidad si el CURP no la trae) -->
            <div v-if="needsBirthState" class="space-y-4 pt-2">
              <p class="text-sm text-gray-500">Solo falta un dato para continuar:</p>
              <AppSelect
                v-model="form.birth_state"
                :options="mexicanStates"
                label="Entidad de nacimiento"
                placeholder="Selecciona tu estado"
                :error="errors.birth_state"
                required
              />
            </div>
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
        <LockedField
          v-if="isMexican && !isKycVerified && isBirthStateLocked && form.birth_state"
          label="Entidad de nacimiento"
          :value="mexicanStates.find(s => s.value === form.birth_state)?.label || form.birth_state"
          format="uppercase"
          :verified="true"
          :verification="getVerification('birth_state')"
        />
        <AppSelect
          v-else-if="isMexican && !isKycVerified"
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
        <div v-if="!autoSubmitting && (onboardingStore.isSaving || onboardingStore.lastSavedAt)" class="text-xs text-right">
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
        <div v-if="!autoSubmitting" class="fixed bottom-0 left-0 right-0 p-3 bg-white border-t">
          <div class="max-w-md mx-auto">
            <AppButton
              type="submit"
              variant="primary"
              size="lg"
              full-width
              :loading="onboardingStore.isSaving"
            >
              Continuar
            </AppButton>
          </div>
        </div>
      </form>
    </div>
  </div>
</template>
