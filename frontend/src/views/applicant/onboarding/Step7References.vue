<script setup lang="ts">
import { reactive, ref, onMounted, watch, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useOnboardingStore, useApplicationStore, useTenantStore } from '@/stores'
import { AppButton, AppInput, AppSelect } from '@/components/common'
import { api } from '@/services/api'
import { getErrorMessage } from '@/types/api'
import { logger } from '@/utils/logger'

const log = logger.child('Step7References')

const router = useRouter()
const onboardingStore = useOnboardingStore()
const applicationStore = useApplicationStore()
const tenantStore = useTenantStore()
const isSaving = ref(false)

// Get relationship options from backend
const familyRelationshipOptions = computed(() => tenantStore.options.relationship_family)
const nonFamilyRelationshipOptions = computed(() => tenantStore.options.relationship_non_family)

interface Reference {
  first_name: string
  last_name_1: string
  last_name_2: string
  relationship: string
  phone: string
}

const references = reactive<Reference[]>([
  { first_name: '', last_name_1: '', last_name_2: '', relationship: '', phone: '' },
  { first_name: '', last_name_1: '', last_name_2: '', relationship: '', phone: '' }
])

const errors = reactive<{ [key: string]: string }>({})

// Sync from store on mount
onMounted(async () => {
  await onboardingStore.init()

  const step7 = onboardingStore.data.step7
  if (step7.references && step7.references.length > 0) {
    step7.references.forEach((ref, index) => {
      if (references[index]) {
        references[index].first_name = ref.first_name || ''
        references[index].last_name_1 = ref.last_name_1 || ''
        references[index].last_name_2 = ref.last_name_2 || ''
        references[index].relationship = ref.relationship || ''
        references[index].phone = ref.phone || ''
      }
    })
  }
})

// Auto-save to store when references change
watch(
  () => references.map(r => ({ ...r })),
  () => {
    onboardingStore.updateStepData('step7', {
      references: references.map(ref => ({
        first_name: ref.first_name,
        last_name_1: ref.last_name_1,
        last_name_2: ref.last_name_2,
        phone: ref.phone,
        relationship: ref.relationship
      }))
    })
  },
  { deep: true }
)

// Derive reference type from relationship
const getTypeFromRelationship = (relationship: string): 'PERSONAL' | 'WORK' => {
  return relationship === 'COWORKER' || relationship === 'BOSS' ? 'WORK' : 'PERSONAL'
}

const isPhoneValid = (phone: string): boolean => {
  const digits = phone.replace(/\D/g, '')
  return digits.length === 10
}

const formatPhone = (phone: string): string => {
  const digits = phone.replace(/\D/g, '').slice(0, 10)
  if (digits.length >= 6) {
    return `${digits.slice(0, 2)} ${digits.slice(2, 6)} ${digits.slice(6)}`
  } else if (digits.length >= 2) {
    return `${digits.slice(0, 2)} ${digits.slice(2)}`
  }
  return digits
}

const handlePhoneInput = (index: number, event: Event) => {
  const target = event.target as HTMLInputElement
  const ref = references[index]
  if (ref) {
    ref.phone = formatPhone(target.value)
  }
}

const validate = () => {
  let isValid = true
  const newErrors: { [key: string]: string } = {}

  references.forEach((ref, index) => {
    if (!ref.first_name.trim()) {
      newErrors[`first_name_${index}`] = 'El nombre es requerido'
      isValid = false
    }

    if (!ref.last_name_1.trim()) {
      newErrors[`last_name_1_${index}`] = 'El apellido paterno es requerido'
      isValid = false
    }

    if (!ref.relationship) {
      newErrors[`relationship_${index}`] = 'Selecciona la relación'
      isValid = false
    }

    if (!ref.phone || !isPhoneValid(ref.phone)) {
      newErrors[`phone_${index}`] = 'Ingresa un teléfono válido (10 dígitos)'
      isValid = false
    }
  })

  // Check for duplicate phone numbers
  const phones = references.map(r => r.phone.replace(/\D/g, ''))
  if (phones[0] && phones[0] === phones[1]) {
    newErrors['phone_1'] = 'Los teléfonos deben ser diferentes'
    isValid = false
  }

  // Validate at least 1 family and 1 non-family reference
  const hasFamily = references.some(r =>
    familyRelationshipOptions.value.some(opt => opt.value === r.relationship)
  )
  const hasNonFamily = references.some(r =>
    nonFamilyRelationshipOptions.value.some(opt => opt.value === r.relationship)
  )

  if (!hasFamily) {
    newErrors['general'] = 'Debe incluir al menos una referencia familiar'
    isValid = false
  }
  if (!hasNonFamily) {
    newErrors['general'] = 'Debe incluir al menos una referencia no familiar'
    isValid = false
  }

  Object.assign(errors, newErrors)
  return isValid
}

const handleSubmit = async () => {
  // Clear previous errors
  Object.keys(errors).forEach(key => delete errors[key])

  if (!validate()) return

  // Verify we have an application
  const appId = applicationStore.currentApplication?.id
  if (!appId || appId === 'null' || appId === 'undefined') {
    errors['general'] = 'No se encontró la solicitud. Por favor, regresa al inicio.'
    return
  }

  isSaving.value = true

  try {
    // Save each reference to backend via API
    for (const ref of references) {
      const fullName = [
        ref.first_name.toUpperCase(),
        ref.last_name_1.toUpperCase(),
        ref.last_name_2.toUpperCase()
      ].filter(Boolean).join(' ')

      await api.post(`/applications/${appId}/references`, {
        full_name: fullName,
        relationship: ref.relationship,
        phone: ref.phone.replace(/\D/g, '')
      })
    }

    // Update store with normalized data
    onboardingStore.updateStepData('step7', {
      references: references.map(ref => ({
        first_name: ref.first_name.toUpperCase(),
        last_name_1: ref.last_name_1.toUpperCase(),
        last_name_2: ref.last_name_2.toUpperCase(),
        phone: ref.phone.replace(/\D/g, ''),
        relationship: ref.relationship
      }))
    })

    // Mark step as completed in store
    if (!onboardingStore.completedSteps.includes(7)) {
      onboardingStore.completedSteps.push(7)
    }

    router.push('/solicitud/paso-8')
  } catch (e: unknown) {
    log.error('Failed to save step 7', { error: e })
    errors['general'] = getErrorMessage(e, 'Error al guardar las referencias')
  } finally {
    isSaving.value = false
  }
}

const prevStep = () => router.push('/solicitud/paso-6')
</script>

<template>
  <div class="px-4 py-6">
    <div class="max-w-md mx-auto">
      <h1 class="text-2xl font-bold text-gray-900 mb-2">Referencias personales</h1>
      <p class="text-gray-500 mb-6">Proporciona 2 referencias: 1 familiar y 1 no familiar que no vivan contigo.</p>

      <!-- Loading state -->
      <div v-if="onboardingStore.isLoading" class="flex justify-center py-8">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600"></div>
      </div>

      <template v-else>
        <!-- Error general -->
        <div v-if="errors['general']" class="mb-4 p-3 bg-red-50 border border-red-200 rounded-xl">
          <p class="text-sm text-red-600">{{ errors['general'] }}</p>
        </div>

        <form class="space-y-8" @submit.prevent="handleSubmit">
        <div
          v-for="(ref, index) in references"
          :key="index"
          class="bg-gray-50 rounded-xl p-4"
        >
          <h3 class="font-medium text-gray-900 mb-4 flex items-center gap-2">
            <span class="w-6 h-6 bg-primary-600 text-white rounded-full flex items-center justify-center text-sm">
              {{ index + 1 }}
            </span>
            Referencia {{ index + 1 }}
            <span class="text-xs text-gray-500 ml-auto">
              {{ index === 0 ? '(Familiar)' : '(No familiar)' }}
            </span>
          </h3>

          <div class="space-y-4">
            <!-- Nombres separados -->
            <AppInput
              v-model="ref.first_name"
              label="Nombre(s)"
              placeholder="JUAN CARLOS"
              :error="errors[`first_name_${index}`]"
              uppercase
              required
            />

            <div class="grid grid-cols-2 gap-3">
              <AppInput
                v-model="ref.last_name_1"
                label="Apellido paterno"
                placeholder="PÉREZ"
                :error="errors[`last_name_1_${index}`]"
                uppercase
                required
              />
              <AppInput
                v-model="ref.last_name_2"
                label="Apellido materno"
                placeholder="GARCÍA"
                uppercase
              />
            </div>

            <!-- Relación según si es la primera (familiar) o segunda (no familiar) -->
            <AppSelect
              v-model="ref.relationship"
              :options="index === 0 ? familyRelationshipOptions : nonFamilyRelationshipOptions"
              :label="index === 0 ? 'Parentesco' : 'Relación'"
              placeholder="Selecciona"
              :error="errors[`relationship_${index}`]"
              required
            />

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">
                Teléfono celular <span class="text-red-500">*</span>
              </label>
              <div class="relative">
                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500">+52</span>
                <input
                  :value="ref.phone"
                  type="tel"
                  inputmode="numeric"
                  placeholder="55 1234 5678"
                  maxlength="12"
                  class="w-full pl-12 pr-4 py-3 border rounded-xl focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                  :class="{
                    'border-gray-300': !errors[`phone_${index}`],
                    'border-red-500': errors[`phone_${index}`]
                  }"
                  @input="handlePhoneInput(index, $event)"
                >
              </div>
              <p v-if="errors[`phone_${index}`]" class="mt-1 text-sm text-red-500">
                {{ errors[`phone_${index}`] }}
              </p>
            </div>
          </div>
        </div>

        <div class="bg-blue-50 rounded-xl p-4 flex gap-3">
          <svg class="w-6 h-6 text-blue-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <p class="text-sm text-blue-800">
            Podríamos contactar a tus referencias para verificar tu información. Asegúrate de avisarles.
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
              :loading="isSaving"
            >
              Continuar →
            </AppButton>
          </div>
        </div>
      </form>
      </template>
    </div>
  </div>
</template>
