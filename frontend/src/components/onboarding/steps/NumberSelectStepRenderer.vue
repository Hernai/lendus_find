<script setup lang="ts">
import type { NumberSelectStep } from '@/types/v2/onboardingStep'

const props = defineProps<{
  step: NumberSelectStep
  modelValue: number | null
}>()

const emit = defineEmits<{
  'update:modelValue': [value: number]
}>()

function pick(n: number) {
  emit('update:modelValue', n)
}
</script>

<template>
  <div class="step-number">
    <h2 class="step-title">{{ step.label }}</h2>
    <p v-if="step.label?.includes('?')" class="step-hint">
      Si tienes experiencia previa, selecciona la opción que más se acerque a tu historial.
    </p>
    <ul class="num-list">
      <li
        v-for="n in step.options"
        :key="n"
        class="num-row"
        :class="{ 'num-row--active': modelValue === n }"
        @click="pick(n)"
      >
        <span class="num-label">{{ n === step.options[step.options.length - 1] && step.options.length > 1 ? `${n} o más` : n }}</span>
        <span class="num-radio" :class="{ 'num-radio--active': modelValue === n }">
          <svg v-if="modelValue === n" class="w-3 h-3 text-white" fill="currentColor" viewBox="0 0 20 20">
            <circle cx="10" cy="10" r="6" />
          </svg>
        </span>
      </li>
    </ul>
  </div>
</template>

<style scoped>
.step-number { display: flex; flex-direction: column; gap: 14px; }
.step-title { font-size: 20px; font-weight: 700; color: #0f172a; margin: 0; }
.step-hint { font-size: 13px; color: #64748b; margin: 0; }
.num-list { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; }
.num-row {
  display: flex; justify-content: space-between; align-items: center;
  padding: 14px 4px; border-bottom: 1px solid #e2e8f0; cursor: pointer;
  -webkit-tap-highlight-color: transparent;
}
.num-row:last-child { border-bottom: none; }
.num-row--active .num-label { color: var(--tenant-primary, #5B21B6); font-weight: 600; }
.num-label { font-size: 15px; color: #0f172a; }
.num-radio { width: 20px; height: 20px; border-radius: 999px; border: 2px solid #cbd5e1; display: grid; place-items: center; }
.num-radio--active { background: var(--tenant-primary, #5B21B6); border-color: var(--tenant-primary, #5B21B6); }
</style>
