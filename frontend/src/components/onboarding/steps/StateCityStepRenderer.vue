<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useTenantStore } from '@/stores/tenant'
import { AppSelect, AppInput } from '@/components/common'
import type { StateCityStep } from '@/types/v2/onboardingStep'

const props = defineProps<{
  step: StateCityStep
  modelValue: { state: string; city: string } | null
}>()

const emit = defineEmits<{
  'update:modelValue': [value: { state: string; city: string }]
}>()

const tenantStore = useTenantStore()

const state = ref<string>(props.modelValue?.state ?? '')
const city = ref<string>(props.modelValue?.city ?? '')

const stateOptions = computed(() => tenantStore.options.mexicanState ?? [])

watch([state, city], () => {
  if (state.value && city.value) {
    emit('update:modelValue', { state: state.value, city: city.value })
  }
})
</script>

<template>
  <div class="step-state-city">
    <h2 class="step-title">{{ step.label }}</h2>
    <p class="step-hint">Selecciona dónde vives actualmente. Tu ubicación nos ayuda a continuar con tu evaluación.</p>
    <div class="fields">
      <AppSelect
        v-model="state"
        :options="stateOptions"
        label="Estado"
        placeholder="Selecciona tu estado"
        required
      />
      <AppInput
        v-model="city"
        label="Ciudad / municipio"
        placeholder="Ej. Tuxtla Gutiérrez"
        required
      />
    </div>
  </div>
</template>

<style scoped>
.step-state-city { display: flex; flex-direction: column; gap: 16px; }
.step-title { font-size: 20px; font-weight: 700; color: #0f172a; margin: 0; }
.step-hint { font-size: 13px; color: #64748b; margin: 0; }
.fields { display: flex; flex-direction: column; gap: 12px; }
</style>
