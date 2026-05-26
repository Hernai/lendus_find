<script setup lang="ts">
import type { OnboardingStep } from '@/types/v2/onboardingStep'

defineProps<{
  step: OnboardingStep
}>()

defineEmits<{
  (e: 'update:modelValue', v: true): void
}>()
</script>

<template>
  <div class="pending">
    <div class="pending-icon">
      <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
      </svg>
    </div>
    <h2 class="title">{{ step.label || step.id }}</h2>
    <p class="msg">
      Esta pantalla aún no está implementada (tipo <code>{{ step.type }}</code>).
      Se construye en la fase correspondiente del roadmap.
    </p>
  </div>
</template>

<style scoped>
.pending { padding: 32px 16px; text-align: center; display: flex; flex-direction: column; gap: 12px; align-items: center; }
.pending-icon { color: #94a3b8; }
.title { font-size: 18px; font-weight: 600; color: #0f172a; margin: 0; }
.msg { font-size: 14px; color: #64748b; max-width: 320px; margin: 0; }
code { background: #f1f5f9; padding: 2px 6px; border-radius: 6px; font-size: 13px; }
</style>
