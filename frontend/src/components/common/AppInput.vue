<script setup lang="ts">
import { computed, ref } from 'vue'

interface Props {
  modelValue: string | number
  type?: 'text' | 'email' | 'tel' | 'number' | 'password' | 'date'
  label?: string
  placeholder?: string
  error?: string
  hint?: string
  disabled?: boolean
  readonly?: boolean
  required?: boolean
  maxlength?: number
  uppercase?: boolean
  prefix?: string
  suffix?: string
}

const props = withDefaults(defineProps<Props>(), {
  type: 'text',
  disabled: false,
  readonly: false,
  required: false,
  uppercase: false
})

const emit = defineEmits<{
  'update:modelValue': [value: string | number]
  blur: [event: FocusEvent]
  focus: [event: FocusEvent]
}>()

const inputRef = ref<HTMLInputElement | null>(null)
const isFocused = ref(false)

const inputClasses = computed(() => {
  const base = 'w-full px-4 py-3 border-2 rounded-xl transition-colors duration-200 focus:outline-none'
  const state = props.error
    ? 'border-red-300 focus:border-red-500 focus:ring-2 focus:ring-red-100'
    : 'border-gray-200 focus:border-primary-500 focus:ring-2 focus:ring-primary-100'
  const disabled = props.disabled ? 'bg-gray-50 text-gray-500 cursor-not-allowed' : 'bg-white'
  const uppercase = props.uppercase ? 'uppercase' : ''

  return [base, state, disabled, uppercase].join(' ')
})

const handleInput = (event: Event) => {
  const target = event.target as HTMLInputElement
  let value: string | number = target.value

  if (props.uppercase) {
    value = value.toUpperCase()
  }

  if (props.type === 'number') {
    value = parseFloat(value) || 0
  }

  emit('update:modelValue', value)
}

const handleFocus = (event: FocusEvent) => {
  isFocused.value = true
  emit('focus', event)
}

const handleBlur = (event: FocusEvent) => {
  isFocused.value = false
  emit('blur', event)
}

const focus = () => {
  inputRef.value?.focus()
}

defineExpose({ focus })
</script>

<template>
  <div class="w-full">
    <!-- Label -->
    <label v-if="label" class="block text-sm font-medium text-gray-700 mb-1">
      {{ label }}
      <span v-if="required" class="text-red-500">*</span>
    </label>

    <!-- Input wrapper -->
    <div class="relative">
      <!-- Prefix -->
      <div
        v-if="prefix"
        class="absolute inset-y-0 left-0 flex items-center pl-4 pointer-events-none"
      >
        <span class="text-gray-500">{{ prefix }}</span>
      </div>

      <!-- Input -->
      <input
        ref="inputRef"
        :type="type"
        :value="modelValue"
        :placeholder="placeholder"
        :disabled="disabled"
        :readonly="readonly"
        :maxlength="maxlength"
        :class="[inputClasses, prefix ? 'pl-12' : '', suffix ? 'pr-12' : '']"
        @input="handleInput"
        @focus="handleFocus"
        @blur="handleBlur"
      />

      <!-- Suffix -->
      <div
        v-if="suffix"
        class="absolute inset-y-0 right-0 flex items-center pr-4 pointer-events-none"
      >
        <span class="text-gray-500">{{ suffix }}</span>
      </div>

      <!-- Success icon when valid -->
      <div
        v-if="!error && modelValue && !isFocused"
        class="absolute inset-y-0 right-0 flex items-center pr-3"
      >
        <svg class="w-5 h-5 text-green-500" fill="currentColor" viewBox="0 0 20 20">
          <path
            fill-rule="evenodd"
            d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
            clip-rule="evenodd"
          />
        </svg>
      </div>
    </div>

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
