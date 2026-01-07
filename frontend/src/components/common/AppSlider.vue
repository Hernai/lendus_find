<script setup lang="ts">
import { computed } from 'vue'

interface Props {
  modelValue: number
  min: number
  max: number
  step?: number
  label?: string
  formatValue?: (value: number) => string
  showMinMax?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  step: 1,
  showMinMax: true
})

const emit = defineEmits<{
  'update:modelValue': [value: number]
}>()

const percentage = computed(() => {
  return ((props.modelValue - props.min) / (props.max - props.min)) * 100
})

const displayValue = computed(() => {
  if (props.formatValue) {
    return props.formatValue(props.modelValue)
  }
  return props.modelValue.toLocaleString('es-MX')
})

const formatMinMax = (value: number) => {
  if (props.formatValue) {
    return props.formatValue(value)
  }
  return value.toLocaleString('es-MX')
}

const handleInput = (event: Event) => {
  const target = event.target as HTMLInputElement
  emit('update:modelValue', Number(target.value))
}
</script>

<template>
  <div class="w-full">
    <!-- Label and value -->
    <div v-if="label" class="flex justify-between items-baseline mb-2">
      <label class="text-sm font-medium text-gray-700">{{ label }}</label>
      <span class="text-2xl font-bold text-primary-600">{{ displayValue }}</span>
    </div>

    <!-- Slider track -->
    <div class="relative">
      <input
        type="range"
        :min="min"
        :max="max"
        :step="step"
        :value="modelValue"
        class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer slider"
        :style="{
          background: `linear-gradient(to right, rgb(99 102 241) 0%, rgb(99 102 241) ${percentage}%, rgb(229 231 235) ${percentage}%, rgb(229 231 235) 100%)`
        }"
        @input="handleInput"
      />
    </div>

    <!-- Min/Max labels -->
    <div v-if="showMinMax" class="flex justify-between text-xs text-gray-400 mt-1">
      <span>{{ formatMinMax(min) }}</span>
      <span>{{ formatMinMax(max) }}</span>
    </div>
  </div>
</template>

<style scoped>
.slider::-webkit-slider-thumb {
  -webkit-appearance: none;
  appearance: none;
  width: 24px;
  height: 24px;
  border-radius: 50%;
  background: white;
  border: 4px solid rgb(99 102 241);
  cursor: pointer;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15);
  transition: transform 0.2s;
}

.slider::-webkit-slider-thumb:hover {
  transform: scale(1.1);
}

.slider::-moz-range-thumb {
  width: 24px;
  height: 24px;
  border-radius: 50%;
  background: white;
  border: 4px solid rgb(99 102 241);
  cursor: pointer;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15);
}
</style>
