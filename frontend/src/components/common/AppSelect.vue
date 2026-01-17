<script setup lang="ts">
import { computed, ref, onMounted } from 'vue'
import AppBottomSheet from './AppBottomSheet.vue'

interface Option {
  readonly value: string | number
  readonly label: string
  readonly disabled?: boolean
}

interface Props {
  modelValue: string | number | null
  options: readonly Option[]
  label?: string
  placeholder?: string
  error?: string
  hint?: string
  disabled?: boolean
  required?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  disabled: false,
  required: false
})

const emit = defineEmits<{
  'update:modelValue': [value: string | number]
}>()

const isMobile = ref(false)
const isOpen = ref(false)

// Detect mobile on mount
onMounted(() => {
  isMobile.value = window.matchMedia('(max-width: 640px)').matches
  const mediaQuery = window.matchMedia('(max-width: 640px)')
  mediaQuery.addEventListener('change', (e) => {
    isMobile.value = e.matches
  })
})

const selectedLabel = computed(() => {
  const selected = props.options.find(opt => opt.value === props.modelValue)
  return selected?.label || props.placeholder || 'Seleccionar'
})

const selectClasses = computed(() => {
  const base = 'w-full px-4 py-3 border-2 rounded-xl transition-colors duration-200 focus:outline-none appearance-none bg-white cursor-pointer text-left'
  const state = props.error
    ? 'border-red-300 focus:border-red-500 focus:ring-2 focus:ring-red-100'
    : 'border-gray-200 focus:border-primary-500 focus:ring-2 focus:ring-primary-100'
  const disabled = props.disabled ? 'bg-gray-50 text-gray-500 cursor-not-allowed' : ''
  const hasValue = props.modelValue ? 'text-gray-900' : 'text-gray-400'

  return [base, state, disabled, hasValue].join(' ')
})

const handleChange = (event: Event) => {
  const target = event.target as HTMLSelectElement
  emit('update:modelValue', target.value)
}

const selectOption = (value: string | number) => {
  emit('update:modelValue', value)
  isOpen.value = false
}

const openSheet = () => {
  if (!props.disabled) {
    isOpen.value = true
  }
}
</script>

<template>
  <div class="w-full">
    <!-- Label -->
    <label v-if="label" class="block text-sm font-medium text-gray-700 mb-1">
      {{ label }}
      <span v-if="required" class="text-red-500">*</span>
    </label>

    <!-- Mobile: Button that opens BottomSheet -->
    <template v-if="isMobile">
      <div class="relative">
        <button
          type="button"
          :disabled="disabled"
          :class="selectClasses"
          @click="openSheet"
        >
          {{ selectedLabel }}
        </button>

        <!-- Dropdown arrow -->
        <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
          <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
          </svg>
        </div>
      </div>

      <!-- BottomSheet for mobile -->
      <AppBottomSheet v-model="isOpen" :title="label || 'Seleccionar'">
        <div class="px-2 py-2">
          <button
            v-for="option in options"
            :key="option.value"
            type="button"
            :disabled="option.disabled"
            class="w-full px-4 py-4 text-left rounded-xl transition-colors flex items-center justify-between"
            :class="[
              option.value === modelValue
                ? 'bg-primary-50 text-primary-700'
                : 'hover:bg-gray-50 text-gray-900',
              option.disabled ? 'opacity-50 cursor-not-allowed' : ''
            ]"
            @click="!option.disabled && selectOption(option.value)"
          >
            <span class="text-base">{{ option.label }}</span>
            <svg
              v-if="option.value === modelValue"
              class="w-5 h-5 text-primary-600"
              fill="currentColor"
              viewBox="0 0 20 20"
            >
              <path
                fill-rule="evenodd"
                d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                clip-rule="evenodd"
              />
            </svg>
          </button>
        </div>
        <div class="h-6" />
      </AppBottomSheet>
    </template>

    <!-- Desktop: Native select -->
    <template v-else>
      <div class="relative">
        <select
          :value="modelValue"
          :disabled="disabled"
          :class="selectClasses"
          @change="handleChange"
        >
          <option v-if="placeholder" value="" disabled>
            {{ placeholder }}
          </option>
          <option
            v-for="option in options"
            :key="option.value"
            :value="option.value"
            :disabled="option.disabled"
          >
            {{ option.label }}
          </option>
        </select>

        <!-- Dropdown arrow -->
        <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
          <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
          </svg>
        </div>
      </div>
    </template>

    <!-- Error message -->
    <p v-if="error" class="mt-1 text-sm text-red-600">
      {{ error }}
    </p>

    <!-- Hint -->
    <p v-else-if="hint" class="mt-1 text-sm text-gray-500">
      {{ hint }}
    </p>
  </div>
</template>