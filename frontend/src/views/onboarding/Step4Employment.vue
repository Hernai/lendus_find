<script setup lang="ts">
import { reactive } from 'vue'
import { useRouter } from 'vue-router'
import { useApplicantStore, useApplicationStore } from '@/stores'
import { AppButton, AppInput, AppSelect, AppRadioGroup } from '@/components/common'

const router = useRouter()
const applicantStore = useApplicantStore()
const applicationStore = useApplicationStore()

const form = reactive({
  occupation_type: '' as 'EMPLEADO' | 'INDEPENDIENTE' | 'EMPRESARIO' | 'JUBILADO' | 'OTRO' | '',
  company_name: '',
  job_title: '',
  work_phone: '',
  monthly_income: '',
  income_frequency: '' as 'SEMANAL' | 'QUINCENAL' | 'MENSUAL' | '',
  years_employed: ''
})

const errors = reactive({
  occupation_type: '',
  company_name: '',
  job_title: '',
  monthly_income: '',
  income_frequency: '',
  years_employed: ''
})

const occupationOptions = [
  { value: 'EMPLEADO', label: 'Empleado' },
  { value: 'INDEPENDIENTE', label: 'Trabajador independiente' },
  { value: 'EMPRESARIO', label: 'Dueño de negocio' },
  { value: 'JUBILADO', label: 'Jubilado/Pensionado' },
  { value: 'OTRO', label: 'Otro' }
]

const incomeFrequencyOptions = [
  { value: 'SEMANAL', label: 'Semanal' },
  { value: 'QUINCENAL', label: 'Quincenal' },
  { value: 'MENSUAL', label: 'Mensual' }
]

const yearsOptions = [
  { value: '1', label: 'Menos de 1 año' },
  { value: '2', label: '1-2 años' },
  { value: '5', label: '2-5 años' },
  { value: '10', label: '5-10 años' },
  { value: '11', label: 'Más de 10 años' }
]

const formatCurrency = (value: string): string => {
  const num = value.replace(/\D/g, '')
  return num ? parseInt(num).toLocaleString('es-MX') : ''
}

const handleIncomeInput = (event: Event) => {
  const target = event.target as HTMLInputElement
  form.monthly_income = formatCurrency(target.value)
}

const validate = () => {
  let isValid = true

  if (!form.occupation_type) {
    errors.occupation_type = 'Selecciona tu ocupación'
    isValid = false
  } else {
    errors.occupation_type = ''
  }

  if (form.occupation_type === 'EMPLEADO' && !form.company_name.trim()) {
    errors.company_name = 'El nombre de la empresa es requerido'
    isValid = false
  } else {
    errors.company_name = ''
  }

  if (form.occupation_type === 'EMPLEADO' && !form.job_title.trim()) {
    errors.job_title = 'El puesto es requerido'
    isValid = false
  } else {
    errors.job_title = ''
  }

  const income = parseInt(form.monthly_income.replace(/\D/g, ''))
  if (!income || income < 1000) {
    errors.monthly_income = 'Ingresa un ingreso válido (mínimo $1,000)'
    isValid = false
  } else {
    errors.monthly_income = ''
  }

  if (!form.income_frequency) {
    errors.income_frequency = 'Selecciona la frecuencia de tus ingresos'
    isValid = false
  } else {
    errors.income_frequency = ''
  }

  if (form.occupation_type === 'EMPLEADO' && !form.years_employed) {
    errors.years_employed = 'Selecciona el tiempo en tu empleo actual'
    isValid = false
  } else {
    errors.years_employed = ''
  }

  return isValid
}

const handleSubmit = async () => {
  if (!validate()) return

  const income = parseInt(form.monthly_income.replace(/\D/g, ''))

  const employmentStatusMap: Record<string, 'EMPLEADO' | 'INDEPENDIENTE' | 'JUBILADO' | 'SIN_EMPLEO'> = {
    'EMPLEADO': 'EMPLEADO',
    'INDEPENDIENTE': 'INDEPENDIENTE',
    'EMPRESARIO': 'INDEPENDIENTE',
    'JUBILADO': 'JUBILADO',
    'OTRO': 'SIN_EMPLEO'
  }

  await applicantStore.updateEmploymentInfo({
    employment_status: employmentStatusMap[form.occupation_type] || 'EMPLEADO',
    company_name: form.company_name?.toUpperCase(),
    position: form.job_title?.toUpperCase(),
    company_phone: form.work_phone || undefined,
    monthly_income: income,
    seniority_months: (parseInt(form.years_employed) || 0) * 12
  })

  await applicationStore.saveStepData({
    step4: {
      occupation_type: form.occupation_type,
      company_name: form.company_name,
      job_title: form.job_title,
      monthly_income: income,
      income_frequency: form.income_frequency,
      years_employed: parseInt(form.years_employed) || null
    }
  })

  router.push('/solicitud/paso-5')
}

const prevStep = () => router.push('/solicitud/paso-3')
</script>

<template>
  <div class="px-4 py-6">
    <div class="max-w-md mx-auto">
      <h1 class="text-2xl font-bold text-gray-900 mb-2">¿A qué te dedicas?</h1>
      <p class="text-gray-500 mb-6">Cuéntanos sobre tu fuente de ingresos.</p>

      <form class="space-y-4" @submit.prevent="handleSubmit">
        <AppSelect
          v-model="form.occupation_type"
          :options="occupationOptions"
          label="Tipo de ocupación"
          placeholder="Selecciona"
          :error="errors.occupation_type"
          required
        />

        <template v-if="form.occupation_type === 'EMPLEADO'">
          <AppInput
            v-model="form.company_name"
            label="Nombre de la empresa"
            placeholder="EMPRESA S.A. DE C.V."
            :error="errors.company_name"
            uppercase
            required
          />

          <AppInput
            v-model="form.job_title"
            label="Puesto"
            placeholder="GERENTE DE VENTAS"
            :error="errors.job_title"
            uppercase
            required
          />

          <AppInput
            v-model="form.work_phone"
            type="tel"
            label="Teléfono de trabajo (opcional)"
            placeholder="55 1234 5678"
            :maxlength="12"
          />

          <AppSelect
            v-model="form.years_employed"
            :options="yearsOptions"
            label="Antigüedad en empleo actual"
            placeholder="Selecciona"
            :error="errors.years_employed"
            required
          />
        </template>

        <template v-else-if="form.occupation_type === 'INDEPENDIENTE' || form.occupation_type === 'EMPRESARIO'">
          <AppInput
            v-model="form.company_name"
            :label="form.occupation_type === 'EMPRESARIO' ? 'Nombre del negocio' : 'Descripción de actividad'"
            :placeholder="form.occupation_type === 'EMPRESARIO' ? 'MI NEGOCIO S.A.' : 'SERVICIOS PROFESIONALES'"
            uppercase
          />
        </template>

        <AppRadioGroup
          v-model="form.income_frequency"
          :options="incomeFrequencyOptions"
          label="¿Cada cuándo recibes tu ingreso?"
          :error="errors.income_frequency"
          required
        />

        <div class="relative">
          <label class="block text-sm font-medium text-gray-700 mb-1">
            ¿Cuánto recibes? <span class="text-red-500">*</span>
          </label>
          <div class="relative">
            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500">$</span>
            <input
              :value="form.monthly_income"
              type="text"
              inputmode="numeric"
              placeholder="15,000"
              class="w-full pl-7 pr-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-transparent"
              :class="{ 'border-red-500': errors.monthly_income }"
              @input="handleIncomeInput"
            >
            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">MXN</span>
          </div>
          <p v-if="form.income_frequency && !errors.monthly_income" class="mt-1 text-xs text-gray-500">
            Ingreso {{ form.income_frequency === 'SEMANAL' ? 'semanal' : form.income_frequency === 'QUINCENAL' ? 'quincenal' : 'mensual' }}
          </p>
          <p v-if="errors.monthly_income" class="mt-1 text-sm text-red-500">
            {{ errors.monthly_income }}
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
              :loading="applicantStore.isSaving"
            >
              Continuar →
            </AppButton>
          </div>
        </div>
      </form>
    </div>
  </div>
</template>
