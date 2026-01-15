<script setup lang="ts">
import { reactive, watch, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useOnboardingStore } from '@/stores'
import { AppButton, AppInput, AppSelect, AppRadioGroup } from '@/components/common'

const router = useRouter()
const onboardingStore = useOnboardingStore()

const form = reactive({
  employment_type: '' as 'EMPLEADO' | 'INDEPENDIENTE' | 'EMPRESARIO' | 'PENSIONADO' | 'ESTUDIANTE' | 'HOGAR' | 'DESEMPLEADO' | 'OTRO' | '',
  company_name: '',
  job_title: '',
  company_phone: '',
  company_address: '',
  monthly_income: 0,
  seniority_years: 0,
  seniority_months: 0
})

const errors = reactive({
  employment_type: '',
  company_name: '',
  job_title: '',
  monthly_income: '',
  seniority_years: ''
})

const employmentTypeOptions = [
  { value: 'EMPLEADO', label: 'Empleado' },
  { value: 'INDEPENDIENTE', label: 'Trabajador independiente' },
  { value: 'EMPRESARIO', label: 'Dueño de negocio' },
  { value: 'PENSIONADO', label: 'Jubilado/Pensionado' },
  { value: 'ESTUDIANTE', label: 'Estudiante' },
  { value: 'HOGAR', label: 'Ama de casa' },
  { value: 'DESEMPLEADO', label: 'Desempleado' },
  { value: 'OTRO', label: 'Otro' }
]

// Opciones de antigüedad en años
const seniorityYearsOptions = [
  { value: 0, label: '0 años' },
  { value: 1, label: '1 año' },
  { value: 2, label: '2 años' },
  { value: 3, label: '3 años' },
  { value: 4, label: '4 años' },
  { value: 5, label: '5 años' },
  { value: 10, label: '10+ años' }
]

// Opciones de antigüedad en meses (máximo 11)
const seniorityMonthsOptions = [
  { value: 0, label: '0 meses' },
  { value: 1, label: '1 mes' },
  { value: 2, label: '2 meses' },
  { value: 3, label: '3 meses' },
  { value: 4, label: '4 meses' },
  { value: 5, label: '5 meses' },
  { value: 6, label: '6 meses' },
  { value: 7, label: '7 meses' },
  { value: 8, label: '8 meses' },
  { value: 9, label: '9 meses' },
  { value: 10, label: '10 meses' },
  { value: 11, label: '11 meses' }
]

// Sync form from store on mount
onMounted(async () => {
  await onboardingStore.init()

  const step4 = onboardingStore.data.step4
  form.employment_type = (step4.employment_type || '') as typeof form.employment_type
  form.company_name = step4.company_name || ''
  form.job_title = step4.job_title || ''
  form.company_phone = step4.company_phone || ''
  form.company_address = step4.company_address || ''
  form.monthly_income = step4.monthly_income || 0
  form.seniority_years = step4.seniority_years || 0
  form.seniority_months = step4.seniority_months || 0
})

// Auto-save to store when form changes
watch(form, () => {
  onboardingStore.updateStepData('step4', {
    employment_type: form.employment_type,
    company_name: form.company_name,
    job_title: form.job_title,
    company_phone: form.company_phone,
    company_address: form.company_address,
    monthly_income: form.monthly_income,
    seniority_years: form.seniority_years,
    seniority_months: form.seniority_months
  })
}, { deep: true })

const formattedIncome = () => {
  return form.monthly_income ? form.monthly_income.toLocaleString('es-MX') : ''
}

const handleIncomeInput = (event: Event) => {
  const target = event.target as HTMLInputElement
  const num = target.value.replace(/\D/g, '')
  form.monthly_income = num ? parseInt(num) : 0
}

const validate = () => {
  let isValid = true

  if (!form.employment_type) {
    errors.employment_type = 'Selecciona tu ocupación'
    isValid = false
  } else {
    errors.employment_type = ''
  }

  // Only require company details for employed workers
  if (form.employment_type === 'EMPLEADO') {
    if (!form.company_name.trim()) {
      errors.company_name = 'El nombre de la empresa es requerido'
      isValid = false
    } else {
      errors.company_name = ''
    }

    if (!form.job_title.trim()) {
      errors.job_title = 'El puesto es requerido'
      isValid = false
    } else {
      errors.job_title = ''
    }
  } else {
    errors.company_name = ''
    errors.job_title = ''
  }

  if (form.monthly_income < 1000) {
    errors.monthly_income = 'Ingresa un ingreso válido (mínimo $1,000)'
    isValid = false
  } else {
    errors.monthly_income = ''
  }

  return isValid
}

const handleSubmit = async () => {
  if (!validate()) return

  try {
    // Normalize data to uppercase before saving
    onboardingStore.updateStepData('step4', {
      employment_type: form.employment_type,
      company_name: form.company_name.toUpperCase(),
      job_title: form.job_title.toUpperCase(),
      company_phone: form.company_phone,
      company_address: form.company_address,
      monthly_income: form.monthly_income,
      seniority_years: form.seniority_years,
      seniority_months: form.seniority_months
    })

    // Save step 4 explicitly
    await onboardingStore.completeStep(4)
    router.push('/solicitud/paso-5')
  } catch (e) {
    console.error('Failed to save step 4:', e)
  }
}

const prevStep = () => router.push('/solicitud/paso-3')
</script>

<template>
  <div class="px-4 py-6">
    <div class="max-w-md mx-auto">
      <h1 class="text-2xl font-bold text-gray-900 mb-2">¿A qué te dedicas?</h1>
      <p class="text-gray-500 mb-6">Cuéntanos sobre tu fuente de ingresos.</p>

      <!-- Loading state -->
      <div v-if="onboardingStore.isLoading" class="flex justify-center py-8">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600"></div>
      </div>

      <form v-else class="space-y-4" @submit.prevent="handleSubmit">
        <AppSelect
          v-model="form.employment_type"
          :options="employmentTypeOptions"
          label="Tipo de ocupación"
          placeholder="Selecciona"
          :error="errors.employment_type"
          required
        />

        <template v-if="form.employment_type === 'EMPLEADO'">
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
            v-model="form.company_phone"
            type="tel"
            label="Teléfono de trabajo (opcional)"
            placeholder="55 1234 5678"
            :maxlength="12"
          />

          <div class="grid grid-cols-2 gap-3">
            <AppSelect
              v-model.number="form.seniority_years"
              :options="seniorityYearsOptions"
              label="Años en empleo"
              placeholder="Años"
              :error="errors.seniority_years"
            />
            <AppSelect
              v-model.number="form.seniority_months"
              :options="seniorityMonthsOptions"
              label="Meses adicionales"
              placeholder="Meses"
            />
          </div>
        </template>

        <template v-else-if="form.employment_type === 'INDEPENDIENTE' || form.employment_type === 'EMPRESARIO'">
          <AppInput
            v-model="form.company_name"
            :label="form.employment_type === 'EMPRESARIO' ? 'Nombre del negocio' : 'Descripción de actividad'"
            :placeholder="form.employment_type === 'EMPRESARIO' ? 'MI NEGOCIO S.A.' : 'SERVICIOS PROFESIONALES'"
            uppercase
          />

          <div class="grid grid-cols-2 gap-3">
            <AppSelect
              v-model.number="form.seniority_years"
              :options="seniorityYearsOptions"
              :label="form.employment_type === 'EMPRESARIO' ? 'Años con negocio' : 'Años de experiencia'"
              placeholder="Años"
            />
            <AppSelect
              v-model.number="form.seniority_months"
              :options="seniorityMonthsOptions"
              label="Meses adicionales"
              placeholder="Meses"
            />
          </div>
        </template>

        <div class="relative">
          <label class="block text-sm font-medium text-gray-700 mb-1">
            ¿Cuánto ganas al mes? <span class="text-red-500">*</span>
          </label>
          <div class="relative">
            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500">$</span>
            <input
              :value="formattedIncome()"
              type="text"
              inputmode="numeric"
              placeholder="15,000"
              class="w-full pl-7 pr-16 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-transparent"
              :class="{ 'border-red-500': errors.monthly_income }"
              @input="handleIncomeInput"
            >
            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">MXN/mes</span>
          </div>
          <p v-if="errors.monthly_income" class="mt-1 text-sm text-red-500">
            {{ errors.monthly_income }}
          </p>
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
