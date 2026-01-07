<script setup lang="ts">
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useApplicationStore, useApplicantStore } from '@/stores'
import { AppButton, AppSignaturePad } from '@/components/common'

const router = useRouter()
const applicationStore = useApplicationStore()
const applicantStore = useApplicantStore()

const isSubmitting = ref(false)
const acceptTerms = ref(false)
const acceptPrivacy = ref(false)
const acceptBuro = ref(false)
const signature = ref<string | null>(null)
const error = ref('')

const simulation = computed(() => applicationStore.simulation)
const applicant = computed(() => applicantStore.applicant)
const application = computed(() => applicationStore.currentApplication)

const canSubmit = computed(() =>
  acceptTerms.value &&
  acceptPrivacy.value &&
  acceptBuro.value &&
  signature.value !== null
)

const formatMoney = (amount: number) => {
  return new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0
  }).format(amount)
}

const frequencyLabels: Record<string, string> = {
  WEEKLY: 'semanal',
  BIWEEKLY: 'quincenal',
  MONTHLY: 'mensual'
}

const handleSubmit = async () => {
  if (!canSubmit.value) {
    if (!signature.value) {
      error.value = 'Debes firmar la solicitud'
    } else {
      error.value = 'Debes aceptar todos los términos y condiciones'
    }
    return
  }

  isSubmitting.value = true
  error.value = ''

  try {
    await applicationStore.saveStepData({
      step8: {
        accepted_terms: true,
        accepted_privacy: true,
        accepted_buro: true,
        signature: signature.value,
        accepted_at: new Date().toISOString()
      }
    })

    const result = await applicationStore.submitApplication()

    if (result) {
      router.push(`/solicitud/${result.id}/estado`)
    } else {
      error.value = 'Error al enviar la solicitud. Intenta de nuevo.'
    }
  } catch (e) {
    error.value = 'Error al enviar la solicitud. Intenta de nuevo.'
  } finally {
    isSubmitting.value = false
  }
}

const prevStep = () => router.push('/solicitud/paso-7')

const sections = computed(() => [
  {
    title: 'Datos personales',
    items: [
      { label: 'Nombre', value: applicant.value?.personal_data?.first_name ? `${applicant.value.personal_data.first_name} ${applicant.value.personal_data.last_name}` : '-' },
      { label: 'CURP', value: applicant.value?.curp || '-' },
      { label: 'RFC', value: applicant.value?.rfc || '-' }
    ]
  },
  {
    title: 'Domicilio',
    items: [
      { label: 'Dirección', value: applicant.value?.address ? `${applicant.value.address.street} ${applicant.value.address.ext_number}` : '-' },
      { label: 'Colonia', value: applicant.value?.address?.neighborhood || '-' },
      { label: 'C.P.', value: applicant.value?.address?.postal_code || '-' }
    ]
  },
  {
    title: 'Empleo',
    items: [
      { label: 'Ocupación', value: applicant.value?.employment_info?.employment_status || '-' },
      { label: 'Ingreso mensual', value: applicant.value?.employment_info?.monthly_income ? formatMoney(applicant.value.employment_info.monthly_income) : '-' }
    ]
  },
  {
    title: 'Crédito',
    items: [
      { label: 'Monto', value: simulation.value ? formatMoney(simulation.value.requested_amount) : '-' },
      { label: 'Plazo', value: simulation.value ? `${simulation.value.term_months} meses` : '-' },
      { label: 'Pago', value: simulation.value ? `${formatMoney(simulation.value.periodic_payment)} ${frequencyLabels[simulation.value.payment_frequency]}` : '-' }
    ]
  }
])
</script>

<template>
  <div class="px-4 py-6">
    <div class="max-w-md mx-auto">
      <h1 class="text-2xl font-bold text-gray-900 mb-2">Revisa y envía</h1>
      <p class="text-gray-500 mb-6">Confirma que toda tu información es correcta.</p>

      <div class="space-y-4">
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

        <!-- Signature Pad -->
        <div class="mt-6">
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
