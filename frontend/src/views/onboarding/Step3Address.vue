<script setup lang="ts">
import { reactive, ref, watch, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useApplicantStore, useApplicationStore } from '@/stores'
import { AppButton, AppInput, AppSelect } from '@/components/common'

const router = useRouter()
const applicantStore = useApplicantStore()
const applicationStore = useApplicationStore()

const form = reactive({
  postal_code: '',
  state: '',
  municipality: '',
  neighborhood: '',
  street: '',
  exterior_number: '',
  interior_number: '',
  years_at_address: ''
})

const errors = reactive({
  postal_code: '',
  state: '',
  municipality: '',
  neighborhood: '',
  street: '',
  exterior_number: '',
  years_at_address: ''
})

const neighborhoods = ref<{ value: string; label: string }[]>([])
const isLoadingPostalCode = ref(false)
const postalCodeLookedUp = ref(false)
const postalCodeFound = ref(false)

// Check if we should show address fields
const showAddressFields = computed(() => {
  return form.postal_code.length === 5 && postalCodeLookedUp.value
})

// Mock postal code lookup - in production this would call SEPOMEX API
const lookupPostalCode = async (postalCode: string) => {
  if (postalCode.length !== 5) return

  isLoadingPostalCode.value = true
  postalCodeLookedUp.value = false
  postalCodeFound.value = false

  try {
    // Simulate API call
    await new Promise(resolve => setTimeout(resolve, 500))

    // Mock data for common postal codes
    const mockData: Record<string, { state: string; municipality: string; neighborhoods: string[] }> = {
      '06600': {
        state: 'CIUDAD DE MEXICO',
        municipality: 'CUAUHTEMOC',
        neighborhoods: ['ROMA NORTE', 'ROMA SUR', 'JUAREZ', 'CONDESA']
      },
      '11000': {
        state: 'CIUDAD DE MEXICO',
        municipality: 'MIGUEL HIDALGO',
        neighborhoods: ['LOMAS DE CHAPULTEPEC', 'POLANCO']
      },
      '44100': {
        state: 'JALISCO',
        municipality: 'GUADALAJARA',
        neighborhoods: ['CENTRO', 'AMERICANA', 'LADRÓN DE GUEVARA']
      },
      '64000': {
        state: 'NUEVO LEON',
        municipality: 'MONTERREY',
        neighborhoods: ['CENTRO', 'OBISPADO', 'MITRAS CENTRO']
      }
    }

    const data = mockData[postalCode]
    if (data) {
      form.state = data.state
      form.municipality = data.municipality
      neighborhoods.value = data.neighborhoods.map(n => ({ value: n, label: n }))
      if (data.neighborhoods.length === 1 && data.neighborhoods[0]) {
        form.neighborhood = data.neighborhoods[0]
      }
      postalCodeFound.value = true
    } else {
      // Allow manual entry for unknown postal codes
      form.state = ''
      form.municipality = ''
      form.neighborhood = ''
      neighborhoods.value = []
      postalCodeFound.value = false
    }
  } finally {
    isLoadingPostalCode.value = false
    postalCodeLookedUp.value = true
  }
}

watch(() => form.postal_code, (newValue) => {
  if (newValue.length === 5) {
    lookupPostalCode(newValue)
  } else {
    form.state = ''
    form.municipality = ''
    form.neighborhood = ''
    neighborhoods.value = []
    postalCodeLookedUp.value = false
    postalCodeFound.value = false
  }
})

const stateOptions = [
  { value: 'AGUASCALIENTES', label: 'Aguascalientes' },
  { value: 'BAJA CALIFORNIA', label: 'Baja California' },
  { value: 'BAJA CALIFORNIA SUR', label: 'Baja California Sur' },
  { value: 'CAMPECHE', label: 'Campeche' },
  { value: 'CHIAPAS', label: 'Chiapas' },
  { value: 'CHIHUAHUA', label: 'Chihuahua' },
  { value: 'CIUDAD DE MEXICO', label: 'Ciudad de México' },
  { value: 'COAHUILA', label: 'Coahuila' },
  { value: 'COLIMA', label: 'Colima' },
  { value: 'DURANGO', label: 'Durango' },
  { value: 'GUANAJUATO', label: 'Guanajuato' },
  { value: 'GUERRERO', label: 'Guerrero' },
  { value: 'HIDALGO', label: 'Hidalgo' },
  { value: 'JALISCO', label: 'Jalisco' },
  { value: 'MEXICO', label: 'Estado de México' },
  { value: 'MICHOACAN', label: 'Michoacán' },
  { value: 'MORELOS', label: 'Morelos' },
  { value: 'NAYARIT', label: 'Nayarit' },
  { value: 'NUEVO LEON', label: 'Nuevo León' },
  { value: 'OAXACA', label: 'Oaxaca' },
  { value: 'PUEBLA', label: 'Puebla' },
  { value: 'QUERETARO', label: 'Querétaro' },
  { value: 'QUINTANA ROO', label: 'Quintana Roo' },
  { value: 'SAN LUIS POTOSI', label: 'San Luis Potosí' },
  { value: 'SINALOA', label: 'Sinaloa' },
  { value: 'SONORA', label: 'Sonora' },
  { value: 'TABASCO', label: 'Tabasco' },
  { value: 'TAMAULIPAS', label: 'Tamaulipas' },
  { value: 'TLAXCALA', label: 'Tlaxcala' },
  { value: 'VERACRUZ', label: 'Veracruz' },
  { value: 'YUCATAN', label: 'Yucatán' },
  { value: 'ZACATECAS', label: 'Zacatecas' }
]

const yearsOptions = [
  { value: '1', label: 'Menos de 1 año' },
  { value: '2', label: '1-2 años' },
  { value: '5', label: '2-5 años' },
  { value: '10', label: '5-10 años' },
  { value: '11', label: 'Más de 10 años' }
]

const validate = () => {
  let isValid = true

  if (!form.postal_code || form.postal_code.length !== 5) {
    errors.postal_code = 'Código postal inválido (5 dígitos)'
    isValid = false
  } else {
    errors.postal_code = ''
  }

  if (!form.state) {
    errors.state = 'El estado es requerido'
    isValid = false
  } else {
    errors.state = ''
  }

  if (!form.municipality.trim()) {
    errors.municipality = 'El municipio es requerido'
    isValid = false
  } else {
    errors.municipality = ''
  }

  if (!form.neighborhood.trim()) {
    errors.neighborhood = 'La colonia es requerida'
    isValid = false
  } else {
    errors.neighborhood = ''
  }

  if (!form.street.trim()) {
    errors.street = 'La calle es requerida'
    isValid = false
  } else {
    errors.street = ''
  }

  if (!form.exterior_number.trim()) {
    errors.exterior_number = 'El número exterior es requerido'
    isValid = false
  } else {
    errors.exterior_number = ''
  }

  if (!form.years_at_address) {
    errors.years_at_address = 'Selecciona el tiempo en tu domicilio'
    isValid = false
  } else {
    errors.years_at_address = ''
  }

  return isValid
}

const handleSubmit = async () => {
  if (!validate()) return

  await applicantStore.updateAddress({
    street: form.street.toUpperCase(),
    ext_number: form.exterior_number,
    int_number: form.interior_number || undefined,
    neighborhood: form.neighborhood.toUpperCase(),
    postal_code: form.postal_code,
    municipality: form.municipality.toUpperCase(),
    city: form.municipality.toUpperCase(),
    state: form.state,
    country: 'MEX',
    housing_type: 'RENTADA',
    years_living: parseInt(form.years_at_address) || 0
  })

  await applicationStore.saveStepData({
    step3: {
      ...form,
      years_at_address: parseInt(form.years_at_address)
    }
  })

  router.push('/solicitud/paso-4')
}

const prevStep = () => router.push('/solicitud/paso-2')
</script>

<template>
  <div class="px-4 py-6 pb-28">
    <div class="max-w-md mx-auto">
      <h1 class="text-2xl font-bold text-gray-900 mb-2">¿Dónde vives?</h1>
      <p class="text-gray-500 mb-6">Ingresa tu código postal para autocompletar.</p>

      <form class="space-y-4" @submit.prevent="handleSubmit">
        <AppInput
          v-model="form.postal_code"
          type="tel"
          label="Código Postal"
          placeholder="00000"
          :error="errors.postal_code"
          :maxlength="5"
          inputmode="numeric"
          required
        />

        <div v-if="isLoadingPostalCode" class="flex items-center gap-2 text-sm text-gray-500">
          <div class="animate-spin w-4 h-4 border-2 border-primary-600 border-t-transparent rounded-full" />
          Buscando...
        </div>

        <!-- Address fields - shown after postal code lookup -->
        <template v-if="showAddressFields">
          <!-- Found postal code: show readonly state/municipality -->
          <template v-if="postalCodeFound">
            <div class="grid grid-cols-2 gap-3">
              <AppInput
                v-model="form.state"
                label="Estado"
                :error="errors.state"
                readonly
                required
              />
              <AppInput
                v-model="form.municipality"
                label="Municipio"
                :error="errors.municipality"
                readonly
                required
              />
            </div>

            <AppSelect
              v-if="neighborhoods.length > 0"
              v-model="form.neighborhood"
              :options="neighborhoods"
              label="Colonia"
              placeholder="Selecciona tu colonia"
              :error="errors.neighborhood"
              required
            />
          </template>

          <!-- Unknown postal code: allow manual entry -->
          <template v-else>
            <div class="bg-yellow-50 rounded-lg p-3 mb-2">
              <p class="text-sm text-yellow-700">
                No encontramos este código postal. Por favor ingresa los datos manualmente.
              </p>
            </div>

            <AppSelect
              v-model="form.state"
              :options="stateOptions"
              label="Estado"
              placeholder="Selecciona tu estado"
              :error="errors.state"
              required
            />

            <AppInput
              v-model="form.municipality"
              label="Municipio / Alcaldía"
              placeholder="BENITO JUAREZ"
              :error="errors.municipality"
              uppercase
              required
            />

            <AppInput
              v-model="form.neighborhood"
              label="Colonia"
              placeholder="DEL VALLE"
              :error="errors.neighborhood"
              uppercase
              required
            />
          </template>

          <AppInput
            v-model="form.street"
            label="Calle"
            placeholder="AV. REFORMA"
            :error="errors.street"
            uppercase
            required
          />

          <div class="grid grid-cols-2 gap-3">
            <AppInput
              v-model="form.exterior_number"
              label="Núm. Exterior"
              placeholder="123"
              :error="errors.exterior_number"
              required
            />
            <AppInput
              v-model="form.interior_number"
              label="Núm. Interior"
              placeholder="A (opcional)"
            />
          </div>

          <AppSelect
            v-model="form.years_at_address"
            :options="yearsOptions"
            label="Tiempo en este domicilio"
            placeholder="Selecciona"
            :error="errors.years_at_address"
            required
          />
        </template>

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
