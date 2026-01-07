<script setup lang="ts">
import { reactive } from 'vue'
import { useRouter } from 'vue-router'
import { useApplicantStore, useApplicationStore } from '@/stores'
import { AppButton, AppInput, AppRadioGroup } from '@/components/common'

const router = useRouter()
const applicantStore = useApplicantStore()
const applicationStore = useApplicationStore()

const form = reactive({
  id_type: '' as 'INE' | 'PASSPORT' | '',
  curp: '',
  rfc: ''
})

const errors = reactive({
  id_type: '',
  curp: '',
  rfc: ''
})

const idTypeOptions = [
  { value: 'INE', label: 'INE/IFE' },
  { value: 'PASSPORT', label: 'Pasaporte' }
]

// CURP validation: 18 characters
const validateCurp = (curp: string): boolean => {
  const curpRegex = /^[A-Z]{4}\d{6}[HM][A-Z]{5}[A-Z\d]\d$/
  return curpRegex.test(curp.toUpperCase())
}

// RFC validation: 12 (moral) or 13 (physical) characters
const validateRfc = (rfc: string): boolean => {
  const rfcRegex = /^[A-ZÑ&]{3,4}\d{6}[A-Z\d]{3}$/
  return rfcRegex.test(rfc.toUpperCase())
}

const validate = () => {
  let isValid = true

  if (!form.id_type) {
    errors.id_type = 'Selecciona un tipo de identificación'
    isValid = false
  } else {
    errors.id_type = ''
  }

  if (!form.curp.trim()) {
    errors.curp = 'La CURP es requerida'
    isValid = false
  } else if (!validateCurp(form.curp)) {
    errors.curp = 'CURP inválida (18 caracteres)'
    isValid = false
  } else {
    errors.curp = ''
  }

  if (!form.rfc.trim()) {
    errors.rfc = 'El RFC es requerido'
    isValid = false
  } else if (!validateRfc(form.rfc)) {
    errors.rfc = 'RFC inválido (12-13 caracteres)'
    isValid = false
  } else {
    errors.rfc = ''
  }

  return isValid
}

const handleSubmit = async () => {
  if (!validate()) return

  await applicantStore.updateIdentification(
    form.rfc.toUpperCase(),
    form.curp.toUpperCase()
  )

  await applicationStore.saveStepData({
    step2: {
      id_type: form.id_type,
      curp: form.curp.toUpperCase(),
      rfc: form.rfc.toUpperCase()
    }
  })

  router.push('/solicitud/paso-3')
}

const prevStep = () => router.push('/solicitud/paso-1')
</script>

<template>
  <div class="px-4 py-6">
    <div class="max-w-md mx-auto">
      <h1 class="text-2xl font-bold text-gray-900 mb-2">Tu identificación</h1>
      <p class="text-gray-500 mb-6">Necesitamos estos datos para verificar tu identidad.</p>

      <form class="space-y-5" @submit.prevent="handleSubmit">
        <AppRadioGroup
          v-model="form.id_type"
          :options="idTypeOptions"
          label="Tipo de identificación"
          :error="errors.id_type"
          required
        />

        <AppInput
          v-model="form.curp"
          label="CURP"
          placeholder="XXXX000000XXXXXX00"
          :error="errors.curp"
          :maxlength="18"
          uppercase
          required
        />
        <p class="text-xs text-gray-400 -mt-3">
          18 caracteres alfanuméricos
        </p>

        <AppInput
          v-model="form.rfc"
          label="RFC"
          placeholder="XXXX000000XXX"
          :error="errors.rfc"
          :maxlength="13"
          uppercase
          required
        />
        <p class="text-xs text-gray-400 -mt-3">
          12-13 caracteres alfanuméricos
        </p>

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
