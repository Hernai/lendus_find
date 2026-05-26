<script setup lang="ts">
import { defineAsyncComponent } from 'vue'
import type { OnboardingStep } from '@/types/v2/onboardingStep'

const props = defineProps<{
  step: OnboardingStep
  modelValue: unknown
  /** Datos acumulados del onboarding (para steps tipo review). */
  formData?: Record<string, unknown>
}>()

defineEmits<{
  (e: 'update:modelValue', v: unknown): void
}>()

/**
 * Despacha cada step al renderer correspondiente. Los renderers se
 * cargan async para code-splitting (el bundle de un onboarding pesado
 * con KYC no se baja si el paso es trivial).
 */
const PendingStepRenderer = defineAsyncComponent(
  () => import('@/components/onboarding/steps/PendingStepRenderer.vue'),
)

const renderers = {
  select: defineAsyncComponent(() => import('@/components/onboarding/steps/SelectStepRenderer.vue')),
  state_city: defineAsyncComponent(() => import('@/components/onboarding/steps/StateCityStepRenderer.vue')),
  number_select: defineAsyncComponent(() => import('@/components/onboarding/steps/NumberSelectStepRenderer.vue')),
  review: defineAsyncComponent(() => import('@/components/onboarding/steps/ReviewStepRenderer.vue')),
  // Los siguientes se implementan en sus fases respectivas (4, 5, 8).
  references: PendingStepRenderer,
  bank_account: PendingStepRenderer,
  kyc_ine: PendingStepRenderer,
  kyc_selfie: PendingStepRenderer,
  review_full: PendingStepRenderer,
} as const

function rendererFor(type: OnboardingStep['type']) {
  return renderers[type] ?? PendingStepRenderer
}
</script>

<template>
  <component
    :is="rendererFor(step.type)"
    :step="step"
    :model-value="modelValue"
    :form-data="formData"
    @update:model-value="$emit('update:modelValue', $event)"
  />
</template>
