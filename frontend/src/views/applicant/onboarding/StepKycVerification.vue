<script setup lang="ts">
import { ref, computed, onMounted, watchEffect } from 'vue'
import { useRouter } from 'vue-router'
import { useKycStore, useOnboardingStore, useApplicationStore, useTenantStore, useApplicantStore } from '@/stores'
import { useKycValidation } from '@/composables/useKycValidation'
import { AppButton } from '@/components/common'
import LockedField from '@/components/common/LockedField.vue'
import IneCapture from '@/components/kyc/IneCapture.vue'
import SelfieCapture from '@/components/kyc/SelfieCapture.vue'

const router = useRouter()
const kycStore = useKycStore()
const onboardingStore = useOnboardingStore()
const applicationStore = useApplicationStore()
const tenantStore = useTenantStore()
const applicantStore = useApplicantStore()

// Use the validation composable
const {
  currentStep,
  validationSteps,
  isComplete,
  allPassed,
  error,
  nextStep,
  goToStep,
  runValidations,
  resetKyc,
  requiresSelfie
} = useKycValidation()

// Debug: watch allPassed and requiresSelfie in dev mode
if (import.meta.env.DEV) {
  watchEffect(() => {
    console.log('[StepKYC Watch] allPassed:', allPassed.value)
    console.log('[StepKYC Watch] isComplete:', isComplete.value)
    console.log('[StepKYC Watch] requiresSelfie:', requiresSelfie.value)
    console.log('[StepKYC Watch] selectedProduct:', applicationStore.selectedProduct?.name)
    console.log('[StepKYC Watch] required_documents:', applicationStore.selectedProduct?.required_documents)
    console.log('[StepKYC Watch] validationSteps:', validationSteps.value.map(s => `${s.key}:${s.status}`).join(', '))
  })
}

// Local state
const isChecking = ref(true)
const showConfirmation = ref(false)

// Check if KYC is configured for this tenant
onMounted(async () => {
  isChecking.value = true

  try {
    // Load applicant if exists (for returning users starting a new application)
    // This is needed so that KYC verifications can be properly associated with the applicant
    await applicantStore.loadApplicant()
    console.log('[StepKYC] Applicant loaded:', applicantStore.applicant?.id || 'none')

    // Load tenant config to get products
    await tenantStore.loadConfig()

    // Load product from pending_application if available
    // This is needed because KYC runs before the application is created
    const pendingApp = localStorage.getItem('pending_application')
    if (pendingApp && !applicationStore.selectedProduct) {
      try {
        const params = JSON.parse(pendingApp) as { product_id: string }
        const product = tenantStore.products.find(p => p.id === params.product_id)
        if (product) {
          console.log('[StepKYC] Setting product from pending_application:', product.name)
          console.log('[StepKYC] Product required_documents:', product.required_documents)
          applicationStore.setSelectedProduct(product)
        }
      } catch (e) {
        console.error('[StepKYC] Failed to parse pending_application:', e)
      }
    }

    // Check if Nubarium is configured
    const hasNubarium = await kycStore.checkServices()

    if (!hasNubarium) {
      // Nubarium not configured - skip to regular flow
      console.log('ℹ️ Nubarium not configured, skipping KYC step')
      router.replace('/solicitud/paso-1')
      return
    }

    // Check if already verified in this session
    if (kycStore.verified && kycStore.lockedData.curp) {
      console.log('✅ KYC already verified, skipping to step 1')
      router.replace('/solicitud/paso-1')
      return
    }
  } catch (err) {
    console.error('Failed to check KYC services:', err)
    // On error, continue to regular flow
    router.replace('/solicitud/paso-1')
  } finally {
    isChecking.value = false
  }
})

// Handle INE front capture
const handleIneFrontCapture = (image: string) => {
  kycStore.setIneFrontImage(image)
}

// Handle INE back capture
const handleIneBackCapture = (image: string) => {
  kycStore.setIneBackImage(image)
}

// Handle INE front retake
const handleIneFrontRetake = () => {
  kycStore.setIneFrontImage('')
}

// Handle INE back retake
const handleIneBackRetake = () => {
  kycStore.setIneBackImage('')
}

// Handle selfie capture
const handleSelfieCapture = (image: string) => {
  kycStore.setSelfieImage(image)
}

// Handle selfie retake
const handleSelfieRetake = () => {
  kycStore.setSelfieImage('')
}

// Proceed from INE back to selfie or validation
const proceedAfterIneBack = () => {
  if (requiresSelfie.value) {
    goToStep('selfie')
  } else {
    startValidation()
  }
}

// Start validation after all images captured
const startValidation = async () => {
  goToStep('validating')

  try {
    const success = await runValidations()
    console.log('[StepKYC] runValidations returned:', success)
    console.log('[StepKYC] allPassed:', allPassed.value)
    console.log('[StepKYC] validationSteps:', validationSteps.value)
  } catch (err) {
    console.error('[StepKYC] Validation error:', err)
  }

  // Always go to result step to show what happened
  console.log('[StepKYC] Going to result step')
  goToStep('result')
}

// Continue to step 1 after confirmation
const handleContinue = () => {
  // Save KYC data to onboarding store
  if (kycStore.lockedData.nombres) {
    // Save personal data to step1
    onboardingStore.updateStepData('step1', {
      first_name: kycStore.lockedData.nombres,
      last_name: kycStore.lockedData.apellido_paterno || '',
      second_last_name: kycStore.lockedData.apellido_materno || '',
      birth_date: kycStore.lockedData.fecha_nacimiento || '',
      gender: kycStore.lockedData.sexo === 'H' ? 'M' : kycStore.lockedData.sexo === 'M' ? 'F' : ''
    })

    // Save CURP to step2 (identification)
    if (kycStore.lockedData.curp) {
      onboardingStore.updateStepData('step2', {
        curp: kycStore.lockedData.curp
      })
    }
  }

  // Navigate to step 1
  router.push('/solicitud/paso-1')
}

// Retry validation
const handleRetry = () => {
  goToStep('ine-front')
  resetKyc()
}

// Computed properties
const canProceedFromFront = computed(() => !!kycStore.ineFrontImage)
const canProceedFromBack = computed(() => !!kycStore.ineBackImage)
const canProceedFromSelfie = computed(() => !!kycStore.selfieImage)

// Step display info (dynamic based on whether selfie is required)
const stepInfo = computed(() => {
  const totalSteps = requiresSelfie.value ? 4 : 3

  switch (currentStep.value) {
    case 'ine-front':
      return { number: 1, total: totalSteps, title: 'Captura tu INE' }
    case 'ine-back':
      return { number: 2, total: totalSteps, title: 'Captura tu INE' }
    case 'selfie':
      return { number: 3, total: totalSteps, title: 'Verificación facial' }
    case 'validating':
      return { number: requiresSelfie.value ? 4 : 3, total: totalSteps, title: 'Verificando identidad' }
    case 'result':
      return { number: requiresSelfie.value ? 4 : 3, total: totalSteps, title: 'Verificación completa' }
    default:
      return { number: 1, total: totalSteps, title: 'Verificación de identidad' }
  }
})
</script>

<template>
  <div class="px-4 py-6">
    <div class="max-w-md mx-auto">
      <!-- Loading state -->
      <div v-if="isChecking" class="flex flex-col items-center justify-center py-20">
        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-primary-600 mb-4" />
        <p class="text-gray-500">Verificando configuración...</p>
      </div>

      <template v-else>
        <!-- Step indicator -->
        <div class="mb-6">
          <div class="flex items-center justify-between text-sm text-gray-500 mb-2">
            <span>Verificación {{ stepInfo.number }}/{{ stepInfo.total }}</span>
            <span class="flex items-center gap-1">
              <svg class="w-4 h-4 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 5.225-3.34 9.67-8 11.317C5.34 16.67 2 12.225 2 7c0-.682.057-1.35.166-2.001zm11.541 3.708a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
              </svg>
              Verificación segura
            </span>
          </div>
          <div class="w-full bg-gray-200 rounded-full h-1">
            <div
              class="bg-primary-600 h-1 rounded-full transition-all duration-300"
              :style="{ width: `${(stepInfo.number / stepInfo.total) * 100}%` }"
            />
          </div>
        </div>

        <!-- INE Front capture -->
        <div v-if="currentStep === 'ine-front'">
          <IneCapture
            side="front"
            :captured-image="kycStore.ineFrontImage"
            @captured="handleIneFrontCapture"
            @retake="handleIneFrontRetake"
          />

          <div class="fixed bottom-0 left-0 right-0 p-4 bg-white border-t">
            <div class="max-w-md mx-auto">
              <AppButton
                variant="primary"
                size="lg"
                full-width
                :disabled="!canProceedFromFront"
                @click="nextStep"
              >
                Continuar
              </AppButton>
            </div>
          </div>
        </div>

        <!-- INE Back capture -->
        <div v-else-if="currentStep === 'ine-back'">
          <IneCapture
            side="back"
            :captured-image="kycStore.ineBackImage"
            @captured="handleIneBackCapture"
            @retake="handleIneBackRetake"
          />

          <div class="fixed bottom-0 left-0 right-0 p-4 bg-white border-t">
            <div class="max-w-md mx-auto space-y-2">
              <AppButton
                variant="primary"
                size="lg"
                full-width
                :disabled="!canProceedFromBack"
                @click="proceedAfterIneBack"
              >
                {{ requiresSelfie ? 'Continuar' : 'Verificar mi identidad' }}
              </AppButton>
              <button
                type="button"
                class="w-full text-center text-gray-500 text-sm"
                @click="goToStep('ine-front')"
              >
                Volver a capturar frente
              </button>
            </div>
          </div>
        </div>

        <!-- Selfie capture (only if required by product) -->
        <div v-else-if="currentStep === 'selfie'">
          <SelfieCapture
            :captured-image="kycStore.selfieImage"
            :is-validated="kycStore.isFaceMatched"
            @captured="handleSelfieCapture"
            @retake="handleSelfieRetake"
          />

          <div class="fixed bottom-0 left-0 right-0 p-4 bg-white border-t">
            <div class="max-w-md mx-auto space-y-2">
              <AppButton
                variant="primary"
                size="lg"
                full-width
                :disabled="!canProceedFromSelfie"
                @click="startValidation"
              >
                Verificar mi identidad
              </AppButton>
              <button
                type="button"
                class="w-full text-center text-gray-500 text-sm"
                @click="goToStep('ine-back')"
              >
                Volver a capturar INE
              </button>
            </div>
          </div>
        </div>

        <!-- Validating step -->
        <div v-else-if="currentStep === 'validating'" class="space-y-6">
          <div class="text-center">
            <div class="w-16 h-16 bg-primary-100 rounded-full flex items-center justify-center mx-auto mb-4">
              <svg class="w-8 h-8 text-primary-600 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
              </svg>
            </div>
            <h2 class="text-xl font-semibold text-gray-900">Verificando tu identidad</h2>
            <p class="text-gray-600 mt-1">Esto puede tomar unos segundos...</p>
          </div>

          <!-- Validation progress -->
          <div class="space-y-3">
            <div
              v-for="step in validationSteps"
              :key="step.key"
              class="flex items-center gap-3 p-3 rounded-lg"
              :class="{
                'bg-gray-50': step.status === 'pending',
                'bg-blue-50': step.status === 'in_progress',
                'bg-green-50': step.status === 'success',
                'bg-amber-50': step.status === 'warning',
                'bg-red-50': step.status === 'error'
              }"
            >
              <!-- Status icon -->
              <div class="flex-shrink-0">
                <div v-if="step.status === 'pending'" class="w-6 h-6 rounded-full border-2 border-gray-300" />
                <svg v-else-if="step.status === 'in_progress'" class="w-6 h-6 text-blue-600 animate-spin" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                </svg>
                <svg v-else-if="step.status === 'success'" class="w-6 h-6 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <!-- Warning icon (exclamation triangle) -->
                <svg v-else-if="step.status === 'warning'" class="w-6 h-6 text-amber-500" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                </svg>
                <svg v-else class="w-6 h-6 text-red-600" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                </svg>
              </div>

              <!-- Step info -->
              <div class="flex-1">
                <p
                  class="font-medium"
                  :class="{
                    'text-gray-500': step.status === 'pending',
                    'text-blue-700': step.status === 'in_progress',
                    'text-green-700': step.status === 'success',
                    'text-amber-700': step.status === 'warning',
                    'text-red-700': step.status === 'error'
                  }"
                >
                  {{ step.label }}
                </p>
                <p v-if="step.message" class="text-sm" :class="{
                  'text-red-600': step.status === 'error',
                  'text-amber-600': step.status === 'warning',
                  'text-gray-500': step.status !== 'error' && step.status !== 'warning'
                }">
                  {{ step.message }}
                </p>
              </div>
            </div>
          </div>
        </div>

        <!-- Result step -->
        <div v-else-if="currentStep === 'result'" class="space-y-4">
          <!-- Success header -->
          <div v-if="allPassed" class="text-center">
            <div class="w-14 h-14 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-2">
              <svg class="w-7 h-7 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
              </svg>
            </div>
            <h2 class="text-lg font-bold text-gray-900">Identidad Verificada</h2>
            <p class="text-gray-600 text-sm">Tus datos han sido validados correctamente</p>
          </div>

          <!-- Error header -->
          <div v-else class="text-center">
            <div class="w-14 h-14 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-2">
              <svg class="w-7 h-7 text-red-600" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
              </svg>
            </div>
            <h2 class="text-lg font-bold text-gray-900">Verificación Incompleta</h2>
            <p class="text-gray-600 text-sm">{{ error || 'Algunas validaciones no pasaron' }}</p>
          </div>

          <!-- Verified data preview -->
          <div v-if="allPassed && kycStore.lockedData.nombres" class="space-y-2">
            <LockedField
              label="Nombre completo"
              :value="kycStore.fullNameFromIne"
              format="uppercase"
            />
            <LockedField
              label="CURP"
              :value="kycStore.lockedData.curp"
              format="curp"
            />
            <div class="grid grid-cols-2 gap-2">
              <LockedField
                label="Fecha de nacimiento"
                :value="kycStore.lockedData.fecha_nacimiento"
                format="date"
              />
              <LockedField
                label="Sexo"
                :value="kycStore.lockedData.sexo === 'H' ? 'Masculino' : kycStore.lockedData.sexo === 'M' ? 'Femenino' : '-'"
              />
            </div>
            <LockedField
              v-if="kycStore.lockedData.entidad_nacimiento"
              label="Entidad de nacimiento"
              :value="kycStore.birthStates[kycStore.lockedData.entidad_nacimiento] || kycStore.lockedData.entidad_nacimiento"
              format="uppercase"
              :verified="true"
            />
            <LockedField
              v-if="kycStore.addressFromIne"
              label="Dirección INE"
              :value="kycStore.addressFromIne"
            />
          </div>

          <!-- Validation summary (for errors or when there are warnings) -->
          <div v-if="!allPassed || validationSteps.some(s => s.status === 'warning')" class="space-y-2">
            <div
              v-for="step in validationSteps"
              :key="step.key"
              class="flex items-center gap-2 text-sm"
            >
              <!-- Success icon -->
              <svg
                v-if="step.status === 'success'"
                class="w-4 h-4 text-green-500"
                fill="currentColor"
                viewBox="0 0 20 20"
              >
                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
              </svg>
              <!-- Warning icon -->
              <svg
                v-else-if="step.status === 'warning'"
                class="w-4 h-4 text-amber-500"
                fill="currentColor"
                viewBox="0 0 20 20"
              >
                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
              </svg>
              <!-- Error icon -->
              <svg
                v-else
                class="w-4 h-4 text-red-500"
                fill="currentColor"
                viewBox="0 0 20 20"
              >
                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
              </svg>
              <span :class="{
                'text-gray-600': step.status === 'success',
                'text-amber-600': step.status === 'warning',
                'text-red-600': step.status === 'error'
              }">
                {{ step.label }}
                <span v-if="step.status === 'warning'" class="text-xs">(requiere revisión)</span>
              </span>
            </div>
          </div>

          <!-- Confirmation checkbox -->
          <div
            v-if="allPassed"
            :class="[
              'mt-4 p-4 rounded-xl border-2 cursor-pointer select-none transition-colors',
              showConfirmation
                ? 'bg-primary-50 border-primary-500'
                : 'bg-gray-50 border-gray-200'
            ]"
            @click="showConfirmation = !showConfirmation"
          >
            <label class="flex items-start gap-3 cursor-pointer">
              <div
                :class="[
                  'w-6 h-6 rounded-lg border-2 flex items-center justify-center flex-shrink-0 transition-colors',
                  showConfirmation
                    ? 'bg-primary-600 border-primary-600'
                    : 'bg-white border-gray-300'
                ]"
              >
                <svg
                  v-if="showConfirmation"
                  class="w-4 h-4 text-white"
                  fill="currentColor"
                  viewBox="0 0 20 20"
                >
                  <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                </svg>
              </div>
              <span class="text-sm text-gray-700">
                Confirmo que los datos mostrados son correctos y corresponden a mi identidad
              </span>
            </label>
          </div>

          <!-- Actions -->
          <div class="fixed bottom-0 left-0 right-0 p-4 bg-white border-t">
            <div class="max-w-md mx-auto space-y-2">
              <AppButton
                v-if="allPassed"
                variant="primary"
                size="lg"
                full-width
                :disabled="!showConfirmation"
                @click="handleContinue"
              >
                Continuar con mi solicitud
              </AppButton>
              <AppButton
                v-else
                variant="primary"
                size="lg"
                full-width
                @click="handleRetry"
              >
                Intentar de nuevo
              </AppButton>
            </div>
          </div>
        </div>
      </template>
    </div>
  </div>
</template>
