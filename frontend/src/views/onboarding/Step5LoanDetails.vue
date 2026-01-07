<script setup lang="ts">
import { reactive, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useApplicationStore } from '@/stores'
import { AppButton, AppSelect, AppRadioGroup } from '@/components/common'

const router = useRouter()
const applicationStore = useApplicationStore()

const form = reactive({
  purpose: '' as string,
  confirm_amount: true
})

const errors = reactive({
  purpose: ''
})

const simulation = computed(() => applicationStore.simulation)

const purposeOptions = [
  { value: 'PERSONAL', label: 'Gastos personales' },
  { value: 'CONSOLIDACION', label: 'Consolidar deudas' },
  { value: 'NEGOCIO', label: 'Capital de trabajo / negocio' },
  { value: 'MEDICO', label: 'Gastos médicos' },
  { value: 'EDUCACION', label: 'Educación' },
  { value: 'VIAJE', label: 'Viaje' },
  { value: 'HOGAR', label: 'Mejoras del hogar' },
  { value: 'OTRO', label: 'Otro' }
]

const frequencyLabels: Record<string, string> = {
  WEEKLY: 'semanal',
  BIWEEKLY: 'quincenal',
  MONTHLY: 'mensual'
}

const formatMoney = (amount: number) => {
  return new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0
  }).format(amount)
}

const validate = () => {
  let isValid = true

  if (!form.purpose) {
    errors.purpose = 'Selecciona el propósito del crédito'
    isValid = false
  } else {
    errors.purpose = ''
  }

  return isValid
}

const handleSubmit = async () => {
  if (!validate()) return

  await applicationStore.saveStepData({
    step5: {
      purpose: form.purpose,
      confirmed_simulation: true
    }
  })

  router.push('/solicitud/paso-6')
}

const prevStep = () => router.push('/solicitud/paso-4')

onMounted(() => {
  // If no simulation exists, redirect to simulator
  if (!simulation.value) {
    router.push('/simulador')
  }
})
</script>

<template>
  <div class="px-4 py-6">
    <div class="max-w-md mx-auto">
      <h1 class="text-2xl font-bold text-gray-900 mb-2">Confirma tu crédito</h1>
      <p class="text-gray-500 mb-6">Revisa los detalles de tu solicitud.</p>

      <form class="space-y-6" @submit.prevent="handleSubmit">
        <!-- Loan Summary Card -->
        <div v-if="simulation" class="bg-gradient-to-br from-primary-600 to-primary-700 rounded-2xl p-6 text-white">
          <div class="text-center mb-4">
            <p class="text-primary-100 text-sm">Monto solicitado</p>
            <p class="text-3xl font-bold">{{ formatMoney(simulation.requested_amount) }}</p>
          </div>

          <div class="grid grid-cols-2 gap-4 pt-4 border-t border-white/20">
            <div>
              <p class="text-primary-200 text-xs">Plazo</p>
              <p class="font-semibold">{{ simulation.term_months }} meses</p>
            </div>
            <div>
              <p class="text-primary-200 text-xs">Pagos</p>
              <p class="font-semibold">{{ simulation.total_periods }} pagos</p>
            </div>
            <div>
              <p class="text-primary-200 text-xs">Pago {{ frequencyLabels[simulation.payment_frequency] }}</p>
              <p class="font-semibold">{{ formatMoney(simulation.periodic_payment) }}</p>
            </div>
            <div>
              <p class="text-primary-200 text-xs">CAT</p>
              <p class="font-semibold">{{ simulation.cat.toFixed(1) }}%</p>
            </div>
          </div>
        </div>

        <!-- Cost breakdown -->
        <div v-if="simulation" class="bg-gray-50 rounded-xl p-4">
          <h3 class="font-medium text-gray-900 mb-3">Desglose de costos</h3>
          <div class="space-y-2 text-sm">
            <div class="flex justify-between">
              <span class="text-gray-600">Monto del préstamo</span>
              <span class="font-medium">{{ formatMoney(simulation.requested_amount) }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-600">Comisión por apertura</span>
              <span class="font-medium">{{ formatMoney(simulation.opening_commission) }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-600">Intereses totales</span>
              <span class="font-medium">{{ formatMoney(simulation.total_interest) }}</span>
            </div>
            <div class="flex justify-between pt-2 border-t border-gray-200">
              <span class="text-gray-900 font-medium">Monto total a pagar</span>
              <span class="font-bold text-primary-600">{{ formatMoney(simulation.total_amount) }}</span>
            </div>
          </div>
        </div>

        <AppSelect
          v-model="form.purpose"
          :options="purposeOptions"
          label="¿Para qué usarás el crédito?"
          placeholder="Selecciona el propósito"
          :error="errors.purpose"
          required
        />

        <div class="bg-blue-50 rounded-xl p-4 flex gap-3">
          <svg class="w-6 h-6 text-blue-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <p class="text-sm text-blue-800">
            Podrás modificar el monto y plazo antes de enviar tu solicitud si cambias de opinión.
          </p>
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
              :loading="applicationStore.isSaving"
            >
              Continuar →
            </AppButton>
          </div>
        </div>
      </form>
    </div>
  </div>
</template>
