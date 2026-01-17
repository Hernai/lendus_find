<script setup lang="ts">
import { onMounted, computed } from 'vue'
import { useOnboardingStore, useTenantStore } from '@/stores'
import { useStepForm, rules } from '@/composables'
import { AppButton, AppInput, AppSelect } from '@/components/common'

const onboardingStore = useOnboardingStore()
const tenantStore = useTenantStore()

// Get employment type options from backend
const employmentTypeOptions = computed(() => tenantStore.options.employment_type)

// Define form using composable - eliminates 80+ lines of boilerplate
const { form, errors, submitError, handleSubmit, prevStep, isSaving, init } = useStepForm({
  step: 4,
  fields: {
    employment_type: {
      default: '' as string,
      rules: [rules.required('Selecciona tu ocupación')],
    },
    company_name: {
      default: '',
      rules: [
        rules.when(
          (f) => f.employment_type === 'EMPLOYEE',
          rules.required('El nombre de la empresa es requerido')
        ),
      ],
      transform: (v: string) => v.toUpperCase(),
    },
    job_title: {
      default: '',
      rules: [
        rules.when(
          (f) => f.employment_type === 'EMPLOYEE',
          rules.required('El puesto es requerido')
        ),
      ],
      transform: (v: string) => v.toUpperCase(),
    },
    company_phone: { default: '' },
    company_address: { default: '' },
    monthly_income: {
      default: 0,
      rules: [rules.min(1000, 'Ingresa un ingreso válido (mínimo $1,000)')],
    },
    seniority_years: { default: 0 },
    seniority_months: { default: 0 },
  },
  nextRoute: '/solicitud/paso-5',
  prevRoute: '/solicitud/paso-3',
})

// Initialize form on mount
onMounted(() => init())

// Computed for formatted income display
const formattedIncome = computed(() => {
  return form.monthly_income ? form.monthly_income.toLocaleString('es-MX') : ''
})

// Handle income input (parse formatted number)
const handleIncomeInput = (event: Event) => {
  const target = event.target as HTMLInputElement
  const num = target.value.replace(/\D/g, '')
  form.monthly_income = num ? parseInt(num) : 0
}

// Check if employment type requires company details
const showCompanyDetails = computed(() => form.employment_type === 'EMPLOYEE')
const showBusinessDetails = computed(() =>
  ['SELF_EMPLOYED', 'BUSINESS_OWNER'].includes(form.employment_type)
)
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

        <template v-if="form.employment_type === 'EMPLOYEE'">
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
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Años en empleo</label>
              <div class="relative">
                <input
                  v-model.number="form.seniority_years"
                  type="number"
                  min="0"
                  max="99"
                  placeholder="0"
                  class="w-full px-4 py-3 pr-16 border-2 border-gray-200 rounded-xl focus:border-primary-500 focus:ring-2 focus:ring-primary-100 focus:outline-none"
                  inputmode="numeric"
                />
                <span class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-500 text-sm">años</span>
              </div>
              <p v-if="errors.seniority_years" class="mt-1 text-sm text-red-600">{{ errors.seniority_years }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Meses adicionales</label>
              <div class="relative">
                <input
                  v-model.number="form.seniority_months"
                  type="number"
                  min="0"
                  max="11"
                  placeholder="0"
                  class="w-full px-4 py-3 pr-20 border-2 border-gray-200 rounded-xl focus:border-primary-500 focus:ring-2 focus:ring-primary-100 focus:outline-none"
                  inputmode="numeric"
                />
                <span class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-500 text-sm">meses</span>
              </div>
            </div>
          </div>
        </template>

        <template v-else-if="form.employment_type === 'SELF_EMPLOYED' || form.employment_type === 'BUSINESS_OWNER'">
          <AppInput
            v-model="form.company_name"
            :label="form.employment_type === 'BUSINESS_OWNER' ? 'Nombre del negocio' : 'Descripción de actividad'"
            :placeholder="form.employment_type === 'BUSINESS_OWNER' ? 'MI NEGOCIO S.A.' : 'SERVICIOS PROFESIONALES'"
            uppercase
          />

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">
                {{ form.employment_type === 'BUSINESS_OWNER' ? 'Años con negocio' : 'Años de experiencia' }}
              </label>
              <div class="relative">
                <input
                  v-model.number="form.seniority_years"
                  type="number"
                  min="0"
                  max="99"
                  placeholder="0"
                  class="w-full px-4 py-3 pr-16 border-2 border-gray-200 rounded-xl focus:border-primary-500 focus:ring-2 focus:ring-primary-100 focus:outline-none"
                  inputmode="numeric"
                />
                <span class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-500 text-sm">años</span>
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Meses adicionales</label>
              <div class="relative">
                <input
                  v-model.number="form.seniority_months"
                  type="number"
                  min="0"
                  max="11"
                  placeholder="0"
                  class="w-full px-4 py-3 pr-20 border-2 border-gray-200 rounded-xl focus:border-primary-500 focus:ring-2 focus:ring-primary-100 focus:outline-none"
                  inputmode="numeric"
                />
                <span class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-500 text-sm">meses</span>
              </div>
            </div>
          </div>
        </template>

        <div class="relative">
          <label class="block text-sm font-medium text-gray-700 mb-1">
            ¿Cuánto ganas al mes? <span class="text-red-500">*</span>
          </label>
          <div class="relative">
            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500">$</span>
            <input
              :value="formattedIncome"
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
