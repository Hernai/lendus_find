<script setup lang="ts">
import { computed } from 'vue'
import { useTenantStore } from '@/stores/tenant'
import type { SelectStep } from '@/types/v2/onboardingStep'

const props = defineProps<{
  step: SelectStep
  modelValue: string | number | null
}>()

const emit = defineEmits<{
  'update:modelValue': [value: string]
}>()

const tenantStore = useTenantStore()

interface Option { value: string | number; label: string }

const options = computed<Option[]>(() => {
  // mapear "EducationLevel" → "educationLevel" en tenantStore.options
  const key = props.step.enum.charAt(0).toLowerCase() + props.step.enum.slice(1)
  const list = (tenantStore.options as Record<string, Option[]>)[key] ?? []
  return list
})

function pick(value: string | number) {
  emit('update:modelValue', String(value))
}
</script>

<template>
  <div class="step-select">
    <h2 class="step-title">{{ step.label }}</h2>
    <div class="options-list">
      <button
        v-for="opt in options"
        :key="opt.value"
        type="button"
        class="option-row"
        :class="{ 'option-row--active': modelValue === opt.value }"
        @click="pick(opt.value)"
      >
        <span class="option-label">{{ opt.label }}</span>
        <span class="option-radio" :class="{ 'option-radio--active': modelValue === opt.value }">
          <svg v-if="modelValue === opt.value" class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
          </svg>
        </span>
      </button>
    </div>
  </div>
</template>

<style scoped>
.step-select { display: flex; flex-direction: column; gap: 16px; }
.step-title { font-size: 20px; font-weight: 700; color: #0f172a; margin: 0; }
.options-list { display: flex; flex-direction: column; gap: 8px; }
.option-row {
  display: flex; align-items: center; justify-content: space-between;
  width: 100%; padding: 14px 16px;
  background: #fff; border: 1px solid #e2e8f0; border-radius: 12px;
  cursor: pointer; transition: all 120ms ease;
  -webkit-tap-highlight-color: transparent;
}
.option-row:active { transform: scale(0.99); }
.option-row--active { border-color: var(--tenant-primary, #5B21B6); background: color-mix(in srgb, var(--tenant-primary, #5B21B6) 6%, #fff); }
.option-label { font-size: 14px; color: #0f172a; font-weight: 500; }
.option-radio {
  width: 24px; height: 24px; border-radius: 999px;
  border: 2px solid #cbd5e1; display: grid; place-items: center;
}
.option-radio--active { background: var(--tenant-primary, #5B21B6); border-color: var(--tenant-primary, #5B21B6); }
</style>
