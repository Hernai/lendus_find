<script setup lang="ts">
import { computed } from 'vue'
import type { OnboardingStep } from '@/types/v2/onboardingStep'
import { useTenantStore } from '@/stores/tenant'
import { resolveStepComponent } from '@/components/onboarding/stepRegistry'

const props = defineProps<{
  step: OnboardingStep
  modelValue: unknown
  /** Datos acumulados del onboarding (para steps tipo review). */
  formData?: Record<string, unknown>
}>()

defineEmits<{
  (e: 'update:modelValue', v: unknown): void
  // Validez intrínseca del paso (contrato de renderer). Ver onboardingStep.ts.
  (e: 'update:valid', valid: boolean): void
}>()

const tenantStore = useTenantStore()

/**
 * Resuelve el renderer del paso vía el registry (type[/variant], extensible por
 * tenant). El registry maneja el fallback a PendingStepRenderer.
 */
const resolved = computed(() =>
  resolveStepComponent(props.step.type, props.step.variant, tenantStore.slug),
)
</script>

<template>
  <!-- modelValue se declara como `unknown` aquí porque cada renderer interpreta su shape específico.
       El cast a `any` en el template es intencional: el contrato de cada renderer valida su payload. -->
  <component
    :is="resolved"
    :key="step.id"
    :step="(step as any)"
    :model-value="(modelValue as any)"
    :form-data="formData"
    @update:model-value="$emit('update:modelValue', $event)"
    @update:valid="$emit('update:valid', $event)"
  />
</template>
