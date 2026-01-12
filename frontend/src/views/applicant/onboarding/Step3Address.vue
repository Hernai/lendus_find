<script setup lang="ts">
import { reactive, ref, watch, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useOnboardingStore, useKycStore } from '@/stores'
import { AppButton, AppInput, AppSelect } from '@/components/common'
import LockedField from '@/components/common/LockedField.vue'

const router = useRouter()
const onboardingStore = useOnboardingStore()
const kycStore = useKycStore()

// Check if KYC is verified and has address data
const isKycVerified = computed(() => kycStore.verified && !!kycStore.lockedData.curp)
const hasIneAddress = computed(() =>
  isKycVerified.value && !!kycStore.lockedData.direccion_ine.calle
)

// Mexican state abbreviations mapping
const stateAbbreviations: Record<string, string> = {
  'AGS.': 'AGUASCALIENTES', 'AGS': 'AGUASCALIENTES',
  'B.C.': 'BAJA CALIFORNIA', 'BC': 'BAJA CALIFORNIA', 'BCN': 'BAJA CALIFORNIA',
  'B.C.S.': 'BAJA CALIFORNIA SUR', 'BCS': 'BAJA CALIFORNIA SUR',
  'CAMP.': 'CAMPECHE', 'CAMP': 'CAMPECHE', 'CAM': 'CAMPECHE',
  'CHIS.': 'CHIAPAS', 'CHIS': 'CHIAPAS', 'CHS': 'CHIAPAS',
  'CHIH.': 'CHIHUAHUA', 'CHIH': 'CHIHUAHUA', 'CHH': 'CHIHUAHUA',
  'CDMX': 'CIUDAD DE MEXICO', 'D.F.': 'CIUDAD DE MEXICO', 'DF': 'CIUDAD DE MEXICO',
  'COAH.': 'COAHUILA', 'COAH': 'COAHUILA', 'COA': 'COAHUILA',
  'COL.': 'COLIMA', 'COL': 'COLIMA',
  'DGO.': 'DURANGO', 'DGO': 'DURANGO', 'DUR': 'DURANGO',
  'GTO.': 'GUANAJUATO', 'GTO': 'GUANAJUATO', 'GUA': 'GUANAJUATO',
  'GRO.': 'GUERRERO', 'GRO': 'GUERRERO',
  'HGO.': 'HIDALGO', 'HGO': 'HIDALGO', 'HID': 'HIDALGO',
  'JAL.': 'JALISCO', 'JAL': 'JALISCO',
  'MEX.': 'ESTADO DE MEXICO', 'MEX': 'ESTADO DE MEXICO', 'EDO. MEX.': 'ESTADO DE MEXICO',
  'MICH.': 'MICHOACAN', 'MICH': 'MICHOACAN', 'MIC': 'MICHOACAN',
  'MOR.': 'MORELOS', 'MOR': 'MORELOS',
  'NAY.': 'NAYARIT', 'NAY': 'NAYARIT',
  'N.L.': 'NUEVO LEON', 'NL': 'NUEVO LEON', 'NLE': 'NUEVO LEON',
  'OAX.': 'OAXACA', 'OAX': 'OAXACA',
  'PUE.': 'PUEBLA', 'PUE': 'PUEBLA',
  'QRO.': 'QUERETARO', 'QRO': 'QUERETARO', 'QUE': 'QUERETARO',
  'Q.R.': 'QUINTANA ROO', 'QR': 'QUINTANA ROO', 'Q. ROO': 'QUINTANA ROO', 'ROO': 'QUINTANA ROO',
  'S.L.P.': 'SAN LUIS POTOSI', 'SLP': 'SAN LUIS POTOSI',
  'SIN.': 'SINALOA', 'SIN': 'SINALOA',
  'SON.': 'SONORA', 'SON': 'SONORA',
  'TAB.': 'TABASCO', 'TAB': 'TABASCO',
  'TAMPS.': 'TAMAULIPAS', 'TAMPS': 'TAMAULIPAS', 'TAM': 'TAMAULIPAS',
  'TLAX.': 'TLAXCALA', 'TLAX': 'TLAXCALA', 'TLA': 'TLAXCALA',
  'VER.': 'VERACRUZ', 'VER': 'VERACRUZ',
  'YUC.': 'YUCATAN', 'YUC': 'YUCATAN',
  'ZAC.': 'ZACATECAS', 'ZAC': 'ZACATECAS'
}

// Parse INE address - extract CP from colonia, num_ext from calle if concatenated
const parsedIneAddress = computed(() => {
  const addr = kycStore.lockedData.direccion_ine
  let calle = addr.calle || ''
  let numExt = ''
  let colonia = addr.colonia || ''
  let cp = addr.cp || ''
  let municipio = addr.municipio || addr.ciudad || addr.localidad || ''
  let estado = addr.estado || ''

  // Try to extract exterior number from calle (e.g., "BLVD PASO LIMON 19" or "AV REFORMA NO. 123")
  // Patterns: "CALLE 123", "CALLE NO 123", "CALLE NO. 123", "CALLE NUM 123", "CALLE # 123"
  const numExtMatch = calle.match(/^(.+?)\s+(?:NO\.?\s*|NUM\.?\s*|#\s*)?(\d+[A-Z]?)$/i)
  if (numExtMatch) {
    calle = numExtMatch[1].trim()
    numExt = numExtMatch[2]
  }

  // Try to extract CP from colonia if it ends with 5 digits (with or without space)
  const cpMatch = colonia.match(/\s*(\d{5})$/)
  if (cpMatch && !cp) {
    cp = cpMatch[1]
    colonia = colonia.replace(/\s*\d{5}$/, '').trim()
  }

  // Try to extract state abbreviation from municipio (e.g., "TUXTLA GUTIERREZ, CHIS.")
  if (municipio && !estado) {
    // Check for comma-separated format: "MUNICIPIO, ESTADO"
    const commaMatch = municipio.match(/^(.+?),\s*(.+)$/)
    if (commaMatch) {
      const possibleMunicipio = commaMatch[1].trim()
      const possibleEstado = commaMatch[2].trim().toUpperCase()
      // Check if it's a known abbreviation
      const fullEstado = stateAbbreviations[possibleEstado]
      if (fullEstado) {
        municipio = possibleMunicipio
        estado = fullEstado
      } else if (possibleEstado.length > 3) {
        // Might be full state name
        municipio = possibleMunicipio
        estado = possibleEstado
      }
    }
  }

  // Expand state abbreviation if needed
  if (estado && stateAbbreviations[estado.toUpperCase()]) {
    estado = stateAbbreviations[estado.toUpperCase()]
  }

  return {
    calle,
    numExt,
    colonia,
    cp,
    municipio,
    estado
  }
})

// User indicates if current address is different from INE
const addressIsDifferent = ref<'same' | 'different' | ''>('')

// Show address form when: no KYC, address is different, or no INE address
const showAddressForm = computed(() => {
  if (!isKycVerified.value) return true
  if (!hasIneAddress.value) return true
  return addressIsDifferent.value === 'different'
})

const form = reactive({
  postal_code: '',
  state: '',
  municipality: '',
  city: '',
  neighborhood: '',
  street: '',
  ext_number: '',
  int_number: '',
  housing_type: '',
  years_at_address: 0,
  months_at_address: 0
})

const errors = reactive({
  postal_code: '',
  state: '',
  municipality: '',
  neighborhood: '',
  street: '',
  ext_number: '',
  housing_type: '',
  residence_time: ''
})

const housingTypeOptions = [
  { value: 'PROPIA_PAGADA', label: 'Propia pagada' },
  { value: 'PROPIA_HIPOTECA', label: 'Propia con hipoteca' },
  { value: 'RENTADA', label: 'Rentada' },
  { value: 'FAMILIAR', label: 'Familiar' },
  { value: 'PRESTADA', label: 'Prestada' },
  { value: 'OTRO', label: 'Otro' }
]

const neighborhoods = ref<{ value: string; label: string }[]>([])

// Sync form from store on mount
onMounted(async () => {
  await onboardingStore.init()

  const step3 = onboardingStore.data.step3
  form.postal_code = step3.postal_code || ''
  form.state = step3.state || ''
  form.municipality = step3.municipality || ''
  form.city = step3.city || ''
  form.neighborhood = step3.neighborhood || ''
  form.street = step3.street || ''
  form.ext_number = step3.ext_number || ''
  form.int_number = step3.int_number || ''
  form.housing_type = step3.housing_type || ''
  form.years_at_address = step3.years_at_address || 0
  form.months_at_address = step3.months_at_address || 0

  // Trigger postal code lookup if we have data
  if (form.postal_code.length === 5) {
    postalCodeLookedUp.value = true
    postalCodeFound.value = !!form.state
  }
})

// Auto-save to store when form changes (except postal_code which triggers lookup)
watch([
  () => form.state,
  () => form.municipality,
  () => form.city,
  () => form.neighborhood,
  () => form.street,
  () => form.ext_number,
  () => form.int_number,
  () => form.housing_type,
  () => form.years_at_address,
  () => form.months_at_address
], () => {
  onboardingStore.updateStepData('step3', {
    postal_code: form.postal_code,
    street: form.street,
    ext_number: form.ext_number,
    int_number: form.int_number,
    neighborhood: form.neighborhood,
    city: form.city || form.municipality,
    state: form.state,
    municipality: form.municipality,
    housing_type: form.housing_type,
    years_at_address: form.years_at_address,
    months_at_address: form.months_at_address
  })
})
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

const validate = () => {
  let isValid = true

  // If using INE address, only validate housing type and residence time
  if (hasIneAddress.value && addressIsDifferent.value === 'same') {
    // Clear address errors since we're using INE data
    errors.postal_code = ''
    errors.state = ''
    errors.municipality = ''
    errors.neighborhood = ''
    errors.street = ''
    errors.ext_number = ''
  } else if (hasIneAddress.value && addressIsDifferent.value === '') {
    // User hasn't selected if address is same or different
    return false
  } else {
    // Validate full address form
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

    if (!form.ext_number.trim()) {
      errors.ext_number = 'El número exterior es requerido'
      isValid = false
    } else {
      errors.ext_number = ''
    }
  }

  // Always validate housing type and residence time
  if (!form.housing_type) {
    errors.housing_type = 'Selecciona el tipo de vivienda'
    isValid = false
  } else {
    errors.housing_type = ''
  }

  // Validar tiempo de residencia
  const years = form.years_at_address
  const months = form.months_at_address

  if (years < 0) {
    errors.residence_time = 'Indica los años en tu domicilio'
    isValid = false
  } else if (months < 0 || months > 11) {
    errors.residence_time = 'Los meses deben ser entre 0 y 11'
    isValid = false
  } else {
    errors.residence_time = ''
  }

  return isValid
}

const handleSubmit = async () => {
  if (!validate()) return

  try {
    // Use INE address if same, otherwise use form data
    if (hasIneAddress.value && addressIsDifferent.value === 'same') {
      // Use parsed INE address data
      const addr = parsedIneAddress.value
      onboardingStore.updateStepData('step3', {
        street: addr.calle?.toUpperCase() || '',
        ext_number: addr.numExt || '', // Use extracted number from street
        int_number: '',
        neighborhood: addr.colonia?.toUpperCase() || '',
        postal_code: addr.cp || '',
        city: addr.municipio?.toUpperCase() || '',
        state: addr.estado?.toUpperCase() || '',
        municipality: addr.municipio?.toUpperCase() || '',
        housing_type: form.housing_type,
        years_at_address: form.years_at_address,
        months_at_address: form.months_at_address,
        is_ine_address: !addr.numExt // Only mark as INE address if we couldn't extract number
      })
    } else {
      // Use form data (normalize to uppercase)
      onboardingStore.updateStepData('step3', {
        street: form.street.toUpperCase(),
        ext_number: form.ext_number,
        int_number: form.int_number || '',
        neighborhood: form.neighborhood.toUpperCase(),
        postal_code: form.postal_code,
        city: form.city || form.municipality.toUpperCase(),
        state: form.state,
        municipality: form.municipality.toUpperCase(),
        housing_type: form.housing_type,
        years_at_address: form.years_at_address,
        months_at_address: form.months_at_address,
        is_ine_address: false
      })
    }

    // Save step 3 explicitly
    await onboardingStore.completeStep(3)
    router.push('/solicitud/paso-4')
  } catch (e) {
    console.error('Failed to save step 3:', e)
  }
}

const prevStep = () => router.push('/solicitud/paso-2')
</script>

<template>
  <div class="px-4 py-6 pb-28">
    <div class="max-w-md mx-auto">
      <h1 class="text-2xl font-bold text-gray-900 mb-2">¿Dónde vives?</h1>
      <p class="text-gray-500 mb-6">Ingresa tu código postal para autocompletar.</p>

      <!-- Loading state -->
      <div v-if="onboardingStore.isLoading" class="flex justify-center py-8">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600"></div>
      </div>

      <form v-else class="space-y-4" @submit.prevent="handleSubmit">
        <!-- KYC Verified: Show INE address and ask if different -->
        <template v-if="hasIneAddress">
          <!-- Locked INE address - cleaner card design -->
          <div class="bg-green-50 border border-green-200 rounded-xl p-4">
            <div class="flex items-center gap-2 mb-3">
              <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                <svg class="w-4 h-4 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd" />
                </svg>
              </div>
              <div>
                <p class="text-sm font-medium text-green-800">Dirección en tu INE</p>
                <p class="text-xs text-green-600">Datos extraídos automáticamente</p>
              </div>
            </div>

            <!-- Address as a formatted block -->
            <div class="bg-white rounded-lg p-4 border border-green-100 space-y-2">
              <p class="text-gray-900 font-medium">
                {{ parsedIneAddress.calle }}
                <span v-if="parsedIneAddress.numExt" class="text-gray-600 font-normal"> No. {{ parsedIneAddress.numExt }}</span>
              </p>
              <p class="text-gray-700">{{ parsedIneAddress.colonia }}</p>
              <div class="flex flex-wrap gap-x-4 gap-y-1 text-sm text-gray-600">
                <span v-if="parsedIneAddress.cp" class="flex items-center gap-1">
                  <span class="text-gray-400">C.P.</span> {{ parsedIneAddress.cp }}
                </span>
                <span v-if="parsedIneAddress.municipio">{{ parsedIneAddress.municipio }}</span>
                <span v-if="parsedIneAddress.estado">{{ parsedIneAddress.estado }}</span>
              </div>
            </div>
          </div>

          <!-- Ask if address is the same -->
          <div class="pt-4">
            <p class="text-sm font-medium text-gray-700 mb-3">¿Actualmente vives en esta dirección?</p>
            <div class="grid grid-cols-2 gap-3">
              <button
                type="button"
                class="flex items-center justify-center gap-2 px-4 py-4 rounded-xl border-2 transition-all duration-200 font-medium"
                :class="addressIsDifferent === 'same'
                  ? 'bg-green-50 border-green-500 text-green-700'
                  : 'bg-white border-gray-200 text-gray-600 hover:border-gray-300'"
                @click="addressIsDifferent = 'same'"
              >
                <svg class="w-5 h-5" :class="addressIsDifferent === 'same' ? 'text-green-500' : 'text-gray-400'" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                Sí
              </button>
              <button
                type="button"
                class="flex items-center justify-center gap-2 px-4 py-4 rounded-xl border-2 transition-all duration-200 font-medium"
                :class="addressIsDifferent === 'different'
                  ? 'bg-orange-50 border-orange-500 text-orange-700'
                  : 'bg-white border-gray-200 text-gray-600 hover:border-gray-300'"
                @click="addressIsDifferent = 'different'"
              >
                <svg class="w-5 h-5" :class="addressIsDifferent === 'different' ? 'text-orange-500' : 'text-gray-400'" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                </svg>
                No
              </button>
            </div>
          </div>

          <!-- If same address, show housing type and time -->
          <template v-if="addressIsDifferent === 'same'">
            <AppSelect
              v-model="form.housing_type"
              :options="housingTypeOptions"
              label="Tipo de vivienda"
              placeholder="Selecciona una opción"
              :error="errors.housing_type"
              required
            />

            <!-- Tiempo en el domicilio -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">
                Tiempo en este domicilio <span class="text-red-500">*</span>
              </label>
              <div class="grid grid-cols-2 gap-3">
                <div>
                  <div class="relative">
                    <input
                      v-model.number="form.years_at_address"
                      type="number"
                      min="0"
                      max="99"
                      placeholder="0"
                      class="w-full px-4 py-3 pr-16 border-2 border-gray-200 rounded-xl focus:border-primary-500 focus:ring-2 focus:ring-primary-100 focus:outline-none"
                      inputmode="numeric"
                    >
                    <span class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-500 text-sm">años</span>
                  </div>
                </div>
                <div>
                  <div class="relative">
                    <input
                      v-model.number="form.months_at_address"
                      type="number"
                      min="0"
                      max="11"
                      placeholder="0"
                      class="w-full px-4 py-3 pr-20 border-2 border-gray-200 rounded-xl focus:border-primary-500 focus:ring-2 focus:ring-primary-100 focus:outline-none"
                      inputmode="numeric"
                    >
                    <span class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-500 text-sm">meses</span>
                  </div>
                </div>
              </div>
              <p v-if="errors.residence_time" class="mt-1 text-sm text-red-600">
                {{ errors.residence_time }}
              </p>
              <p v-else class="mt-1 text-xs text-gray-500">
                Ej: 2 años y 6 meses
              </p>
            </div>
          </template>

          <!-- If different address, show divider -->
          <div v-if="addressIsDifferent === 'different'" class="border-t border-gray-200 pt-4">
            <p class="text-sm text-gray-500 mb-4">Ingresa tu domicilio actual:</p>
          </div>
        </template>

        <!-- Address form (shown when no KYC or address is different) -->
        <template v-if="showAddressForm">
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
              v-model="form.ext_number"
              label="Núm. Exterior"
              placeholder="123"
              :error="errors.ext_number"
              required
            />
            <AppInput
              v-model="form.int_number"
              label="Núm. Interior"
              placeholder="A (opcional)"
            />
          </div>

          <AppSelect
            v-model="form.housing_type"
            :options="housingTypeOptions"
            label="Tipo de vivienda"
            placeholder="Selecciona una opción"
            :error="errors.housing_type"
            required
          />

          <!-- Tiempo en el domicilio -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
              Tiempo en este domicilio <span class="text-red-500">*</span>
            </label>
            <div class="grid grid-cols-2 gap-3">
              <div>
                <div class="relative">
                  <input
                    v-model.number="form.years_at_address"
                    type="number"
                    min="0"
                    max="99"
                    placeholder="0"
                    class="w-full px-4 py-3 pr-16 border-2 border-gray-200 rounded-xl focus:border-primary-500 focus:ring-2 focus:ring-primary-100 focus:outline-none"
                    inputmode="numeric"
                  >
                  <span class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-500 text-sm">años</span>
                </div>
              </div>
              <div>
                <div class="relative">
                  <input
                    v-model.number="form.months_at_address"
                    type="number"
                    min="0"
                    max="11"
                    placeholder="0"
                    class="w-full px-4 py-3 pr-20 border-2 border-gray-200 rounded-xl focus:border-primary-500 focus:ring-2 focus:ring-primary-100 focus:outline-none"
                    inputmode="numeric"
                  >
                  <span class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-500 text-sm">meses</span>
                </div>
              </div>
            </div>
            <p v-if="errors.residence_time" class="mt-1 text-sm text-red-600">
              {{ errors.residence_time }}
            </p>
            <p v-else class="mt-1 text-xs text-gray-500">
              Ej: 2 años y 6 meses
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
        </template>
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
