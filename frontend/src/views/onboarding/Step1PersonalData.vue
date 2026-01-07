<script setup lang="ts">
import { ref, reactive } from 'vue'
import { useRouter } from 'vue-router'
import { useApplicantStore } from '@/stores'
import { AppButton, AppInput, AppRadioGroup } from '@/components/common'

const router = useRouter()
const applicantStore = useApplicantStore()

const form = reactive({
  first_name: '',
  middle_name: '',
  last_name: '',
  second_last_name: '',
  birth_date: '',
  gender: '' as 'M' | 'F' | ''
})

const errors = reactive({
  first_name: '',
  last_name: '',
  birth_date: '',
  gender: ''
})

const genderOptions = [
  { value: 'M', label: 'Masculino' },
  { value: 'F', label: 'Femenino' }
]

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
    birth_state: '',
    gender: form.gender as 'M' | 'F',
    nationality: 'MX',
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
