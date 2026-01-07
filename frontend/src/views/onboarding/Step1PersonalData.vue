<script setup lang="ts">
import { ref, reactive, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useApplicantStore } from '@/stores'
import { AppButton, AppInput, AppRadioGroup, AppSelect } from '@/components/common'

const router = useRouter()
const applicantStore = useApplicantStore()

const form = reactive({
  first_name: '',
  middle_name: '',
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

const genderOptions = [
  { value: 'M', label: 'Masculino' },
  { value: 'F', label: 'Femenino' }
]

const mexicanOptions = [
  { value: 'SI', label: 'Sí' },
  { value: 'NO', label: 'No' }
]

// Entidades federativas de México
const mexicanStates = [
  { value: 'AGS', label: 'Aguascalientes' },
  { value: 'BC', label: 'Baja California' },
  { value: 'BCS', label: 'Baja California Sur' },
  { value: 'CAM', label: 'Campeche' },
  { value: 'CHIS', label: 'Chiapas' },
  { value: 'CHIH', label: 'Chihuahua' },
  { value: 'CDMX', label: 'Ciudad de México' },
  { value: 'COAH', label: 'Coahuila' },
  { value: 'COL', label: 'Colima' },
  { value: 'DGO', label: 'Durango' },
  { value: 'GTO', label: 'Guanajuato' },
  { value: 'GRO', label: 'Guerrero' },
  { value: 'HGO', label: 'Hidalgo' },
  { value: 'JAL', label: 'Jalisco' },
  { value: 'MEX', label: 'Estado de México' },
  { value: 'MICH', label: 'Michoacán' },
  { value: 'MOR', label: 'Morelos' },
  { value: 'NAY', label: 'Nayarit' },
  { value: 'NL', label: 'Nuevo León' },
  { value: 'OAX', label: 'Oaxaca' },
  { value: 'PUE', label: 'Puebla' },
  { value: 'QRO', label: 'Querétaro' },
  { value: 'QROO', label: 'Quintana Roo' },
  { value: 'SLP', label: 'San Luis Potosí' },
  { value: 'SIN', label: 'Sinaloa' },
  { value: 'SON', label: 'Sonora' },
  { value: 'TAB', label: 'Tabasco' },
  { value: 'TAM', label: 'Tamaulipas' },
  { value: 'TLAX', label: 'Tlaxcala' },
  { value: 'VER', label: 'Veracruz' },
  { value: 'YUC', label: 'Yucatán' },
  { value: 'ZAC', label: 'Zacatecas' },
  { value: 'NE', label: 'Nacido en el Extranjero' }
]

// Países más comunes para extranjeros en México
const countries = [
  { value: 'US', label: 'Estados Unidos' },
  { value: 'GT', label: 'Guatemala' },
  { value: 'HN', label: 'Honduras' },
  { value: 'SV', label: 'El Salvador' },
  { value: 'VE', label: 'Venezuela' },
  { value: 'CO', label: 'Colombia' },
  { value: 'AR', label: 'Argentina' },
  { value: 'CU', label: 'Cuba' },
  { value: 'NI', label: 'Nicaragua' },
  { value: 'ES', label: 'España' },
  { value: 'PE', label: 'Perú' },
  { value: 'EC', label: 'Ecuador' },
  { value: 'BR', label: 'Brasil' },
  { value: 'CL', label: 'Chile' },
  { value: 'CA', label: 'Canadá' },
  { value: 'CN', label: 'China' },
  { value: 'IN', label: 'India' },
  { value: 'FR', label: 'Francia' },
  { value: 'DE', label: 'Alemania' },
  { value: 'IT', label: 'Italia' },
  { value: 'JP', label: 'Japón' },
  { value: 'KR', label: 'Corea del Sur' },
  { value: 'OTHER', label: 'Otro país' }
]

const isMexican = computed(() => form.is_mexican === 'SI')
const isForeigner = computed(() => form.is_mexican === 'NO')

const validate = () => {
  let isValid = true

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
    errors.birth_date = ''
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

  // Validar entidad de nacimiento para mexicanos
  if (isMexican.value && !form.birth_state) {
    errors.birth_state = 'Selecciona tu entidad de nacimiento'
    isValid = false
  } else {
    errors.birth_state = ''
  }

  // Validar nacionalidad para extranjeros
  if (isForeigner.value && !form.nationality) {
    errors.nationality = 'Selecciona tu nacionalidad'
    isValid = false
  } else {
    errors.nationality = ''
  }

  return isValid
}

const handleSubmit = async () => {
  if (!validate()) return

  await applicantStore.updatePersonalData({
    first_name: form.first_name.toUpperCase(),
    middle_name: form.middle_name?.toUpperCase(),
    last_name: form.last_name.toUpperCase(),
    second_last_name: form.second_last_name?.toUpperCase(),
    birth_date: form.birth_date,
    birth_state: isMexican.value ? form.birth_state : 'EXTRANJERO',
    gender: form.gender as 'M' | 'F',
    nationality: isMexican.value ? 'MX' : form.nationality,
    marital_status: 'SOLTERO'
  })

  router.push('/solicitud/paso-2')
}
</script>

<template>
  <div class="px-4 py-6">
    <div class="max-w-md mx-auto">
      <h1 class="text-2xl font-bold text-gray-900 mb-6">¿Cómo te llamas?</h1>

      <form class="space-y-4" @submit.prevent="handleSubmit">
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

        <AppInput
          v-model="form.birth_date"
          type="date"
          label="Fecha de nacimiento"
          :error="errors.birth_date"
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

        <!-- Entidad de nacimiento (solo mexicanos) -->
        <AppSelect
          v-if="isMexican"
          v-model="form.birth_state"
          :options="mexicanStates"
          label="Entidad de nacimiento"
          placeholder="Selecciona tu estado"
          :error="errors.birth_state"
          required
        />

        <!-- Nacionalidad (solo extranjeros) -->
        <AppSelect
          v-if="isForeigner"
          v-model="form.nationality"
          :options="countries"
          label="Nacionalidad"
          placeholder="Selecciona tu país de origen"
          :error="errors.nationality"
          required
        />

        <!-- Nota informativa para extranjeros -->
        <div v-if="isForeigner" class="bg-blue-50 rounded-xl p-4 flex gap-3">
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

        <!-- Sticky Footer -->
        <div class="fixed bottom-0 left-0 right-0 p-4 bg-white border-t">
          <div class="max-w-md mx-auto">
            <AppButton
              type="submit"
              variant="primary"
              size="lg"
              full-width
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
