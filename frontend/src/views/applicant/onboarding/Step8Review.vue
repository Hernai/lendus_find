<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useOnboardingStore, useApplicationStore, useAuthStore, useTenantStore } from '@/stores'
import { AppButton, AppSignaturePad } from '@/components/common'
import { v2 } from '@/services/v2'
import { type AxiosErrorResponse } from '@/types/api'
import { logger } from '@/utils/logger'
import { formatMoney, formatFrequency } from '@/utils/formatters'

const log = logger.child('Step8Review')

const router = useRouter()
const onboardingStore = useOnboardingStore()
const applicationStore = useApplicationStore()
const authStore = useAuthStore()
const tenantStore = useTenantStore()

// Sync from store on mount
onMounted(async () => {
  await onboardingStore.init()
})

const isSubmitting = ref(false)
const acceptTerms = ref(false)
const acceptPrivacy = ref(false)
const acceptBuro = ref(false)
const signature = ref<string | null>(null)
const error = ref('')

const simulation = computed(() => applicationStore.simulation)

const hasApplication = computed(() => !!applicationStore.currentApplication)

// Check if the product requires signature
const requiresSignature = computed(() => {
  const product = applicationStore.selectedProduct
  // Check fresh config first, then fall back to selected product
  const productFromConfig = tenantStore.products.find(p => p.id === product?.id)
  const requiredDocs = productFromConfig?.required_documents ?? productFromConfig?.required_docs ??
                       product?.required_documents ?? product?.required_docs ?? []

  // Check if SIGNATURE is in the required documents list
  return requiredDocs.some((doc: { type: string } | string) => {
    const docType = typeof doc === 'string' ? doc : doc.type
    return docType === 'SIGNATURE'
  })
})

const canSubmit = computed(() =>
  hasApplication.value &&
  acceptTerms.value &&
  acceptPrivacy.value &&
  acceptBuro.value &&
  // Only require signature if product requires it
  (!requiresSignature.value || signature.value !== null)
)



const handleSubmit = async () => {
  if (!canSubmit.value) {
    if (requiresSignature.value && !signature.value) {
      error.value = 'Debes firmar la solicitud'
    } else {
      error.value = 'Debes aceptar todos los términos y condiciones'
    }
    return
  }

  // Ensure we have an application loaded
  if (!applicationStore.currentApplication) {
    error.value = 'No se encontró la solicitud. Por favor, regresa al inicio y vuelve a intentar.'
    return
  }

  isSubmitting.value = true
  error.value = ''

  try {
    // 1. Save signature to applicant if required by product
    if (requiresSignature.value && signature.value) {
      await v2.applicant.profile.saveSignature(signature.value)
      log.debug('Signature saved to applicant')
    }

    // KYC verifications are now automatically recorded by the backend
    // when CURP/INE validation succeeds - no need to call recordVerifications

    // 2. Update application with consent data
    await applicationStore.updateApplication({
      dynamic_data: {
        ...applicationStore.currentApplication.dynamic_data,
        step8: {
          accepted_terms: true,
          accepted_privacy: true,
          accepted_buro: true,
          accepted_at: new Date().toISOString()
        }
      }
    })

    // 4. Submit the application
    const result = await applicationStore.submitApplication()

    if (result) {
      // Clear all onboarding data after successful submission
      onboardingStore.reset()
      authStore.clearOnboardingCache()
      router.push(`/solicitud/${result.id}/estado`)
    } else {
      error.value = 'Error al enviar la solicitud. Intenta de nuevo.'
    }
  } catch (e: unknown) {
    log.error('Failed to submit application', { error: e })
    // Show detailed error if available
    const axiosErr = e as AxiosErrorResponse
    const errorMsg = axiosErr.response?.data?.message || axiosErr.response?.data?.errors
    if (errorMsg) {
      if (typeof errorMsg === 'object') {
        // Format validation errors
        const messages = Object.values(errorMsg).flat().join(', ')
        error.value = messages || 'Error al enviar la solicitud'
      } else {
        error.value = errorMsg
      }
    } else {
      error.value = 'Error al enviar la solicitud. Intenta de nuevo.'
    }
  } finally {
    isSubmitting.value = false
  }
}

const prevStep = () => router.push('/solicitud/paso-7')

const sections = computed(() => {
  const data = onboardingStore.data
  const step1 = data.step1
  const step2 = data.step2
  const step3 = data.step3
  const step4 = data.step4

  return [
    {
      title: 'Datos personales',
      items: [
        { label: 'Nombre', value: step1.first_name ? `${step1.first_name} ${step1.last_name} ${step1.second_last_name}`.trim() : '-' },
        { label: 'CURP', value: step2.curp || '-' },
        { label: 'RFC', value: step2.rfc || '-' }
      ]
    },
    {
      title: 'Domicilio',
      items: [
        { label: 'Dirección', value: step3.street ? `${step3.street} ${step3.ext_number}` : '-' },
        { label: 'Colonia', value: step3.neighborhood || '-' },
        { label: 'C.P.', value: step3.postal_code || '-' }
      ]
    },
    {
      title: 'Empleo',
      items: [
        { label: 'Ocupación', value: step4.employment_type || '-' },
        { label: 'Ingreso mensual', value: step4.monthly_income ? formatMoney(step4.monthly_income) : '-' }
      ]
    },
    {
      title: 'Crédito',
      items: [
        { label: 'Monto', value: simulation.value ? formatMoney(simulation.value.requested_amount) : '-' },
        { label: 'Plazo', value: simulation.value ? `${simulation.value.term_months} meses` : '-' },
        { label: 'Pago', value: simulation.value ? `${formatMoney(simulation.value.periodic_payment)} ${formatFrequency(simulation.value.payment_frequency)}` : '-' }
      ]
    }
  ]
})
</script>

<template>
  <div class="px-4 py-6">
    <div class="max-w-md mx-auto">
      <h1 class="text-2xl font-bold text-gray-900 mb-2">Revisa y envía</h1>
      <p class="text-gray-500 mb-6">Confirma que toda tu información es correcta.</p>

      <!-- Loading state -->
      <div v-if="onboardingStore.isLoading" class="flex justify-center py-8">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600"></div>
      </div>

      <div v-else class="space-y-4">
        <!-- Warning if no application loaded -->
        <div v-if="!hasApplication" class="bg-yellow-50 rounded-xl p-4 flex gap-3 mb-4">
          <svg class="w-6 h-6 text-yellow-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
          </svg>
          <div class="text-sm text-yellow-800">
            <p class="font-medium">No se encontró la solicitud</p>
            <p class="text-yellow-700 mt-1">
              Por favor, regresa al <router-link to="/" class="underline font-medium">inicio</router-link> y vuelve a simular tu crédito.
            </p>
          </div>
        </div>

        <!-- Summary sections -->
        <div
          v-for="section in sections"
          :key="section.title"
          class="bg-white rounded-xl border border-gray-200 overflow-hidden"
        >
          <div class="bg-gray-50 px-4 py-2 border-b border-gray-200">
            <h3 class="font-medium text-gray-900 text-sm">{{ section.title }}</h3>
          </div>
          <div class="p-4 space-y-2">
            <div
              v-for="item in section.items"
              :key="item.label"
              class="flex justify-between text-sm"
            >
              <span class="text-gray-500">{{ item.label }}</span>
              <span class="font-medium text-gray-900">{{ item.value }}</span>
            </div>
          </div>
        </div>

        <!-- Legal agreements -->
        <div class="space-y-3 mt-6">
          <label class="flex items-start gap-3 cursor-pointer">
            <div class="relative flex-shrink-0 mt-0.5">
              <input
                v-model="acceptTerms"
                type="checkbox"
                class="sr-only peer"
              >
              <div class="w-5 h-5 border-2 border-gray-300 rounded peer-checked:border-primary-600 peer-checked:bg-primary-600 transition-colors">
                <svg v-if="acceptTerms" class="w-full h-full text-white p-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                </svg>
              </div>
            </div>
            <span class="text-sm text-gray-600">
              Acepto los
              <a href="#" class="text-primary-600 hover:underline">Términos y Condiciones</a>
              del servicio.
            </span>
          </label>

          <label class="flex items-start gap-3 cursor-pointer">
            <div class="relative flex-shrink-0 mt-0.5">
              <input
                v-model="acceptPrivacy"
                type="checkbox"
                class="sr-only peer"
              >
              <div class="w-5 h-5 border-2 border-gray-300 rounded peer-checked:border-primary-600 peer-checked:bg-primary-600 transition-colors">
                <svg v-if="acceptPrivacy" class="w-full h-full text-white p-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                </svg>
              </div>
            </div>
            <span class="text-sm text-gray-600">
              He leído y acepto el
              <a href="#" class="text-primary-600 hover:underline">Aviso de Privacidad</a>.
            </span>
          </label>

          <label class="flex items-start gap-3 cursor-pointer">
            <div class="relative flex-shrink-0 mt-0.5">
              <input
                v-model="acceptBuro"
                type="checkbox"
                class="sr-only peer"
              >
              <div class="w-5 h-5 border-2 border-gray-300 rounded peer-checked:border-primary-600 peer-checked:bg-primary-600 transition-colors">
                <svg v-if="acceptBuro" class="w-full h-full text-white p-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                </svg>
              </div>
            </div>
            <span class="text-sm text-gray-600">
              Autorizo la consulta de mi historial crediticio en
              <strong>Buró de Crédito</strong> y <strong>Círculo de Crédito</strong>.
            </span>
          </label>
        </div>

        <!-- Signature Pad - only if product requires it -->
        <div v-if="requiresSignature" class="mt-6">
          <AppSignaturePad
            v-model="signature"
            label="Firma digital"
            :height="120"
            required
          />
        </div>

        <p v-if="error" class="text-sm text-red-500 text-center mt-4">
          {{ error }}
        </p>

        <!-- Info box -->
        <div class="bg-green-50 rounded-xl p-4 flex gap-3 mt-4">
          <svg class="w-6 h-6 text-green-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
          </svg>
          <div class="text-sm text-green-800">
            <p class="font-medium">Tu información está segura</p>
            <p class="text-green-700 mt-1">
              Usamos encriptación de grado bancario para proteger tus datos.
            </p>
          </div>
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
            :disabled="!canSubmit"
            :loading="isSubmitting"
            @click="handleSubmit"
          >
            Enviar solicitud
          </AppButton>
        </div>
      </div>
    </div>
  </div>
</template>
