<script setup lang="ts">
import { ref, watch, nextTick, type ComponentPublicInstance } from 'vue'

interface Props {
  modelValue: string
  length?: number
  error?: string
  disabled?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  length: 6,
  disabled: false
})

const emit = defineEmits<{
  'update:modelValue': [value: string]
  complete: [value: string]
}>()

const inputs = ref<HTMLInputElement[]>([])
const values = ref<string[]>(Array(props.length).fill(''))

// Sync with modelValue
watch(() => props.modelValue, (newValue) => {
  values.value = newValue.split('').concat(Array(props.length).fill('')).slice(0, props.length)
}, { immediate: true })

const focusInput = (index: number) => {
  nextTick(() => {
    if (inputs.value[index]) {
      inputs.value[index].focus()
      inputs.value[index].select()
    }
  })
}

const handleInput = (index: number, event: Event) => {
  const target = event.target as HTMLInputElement
  const value = target.value.replace(/[^0-9]/g, '')

  if (value.length > 1) {
    // Handle paste of multiple digits
    const digits = value.split('')
    digits.forEach((digit, i) => {
      if (index + i < props.length) {
        values.value[index + i] = digit
      }
    })
    focusInput(Math.min(index + digits.length, props.length - 1))
  } else {
    values.value[index] = value
    if (value && index < props.length - 1) {
      focusInput(index + 1)
    }
  }

  const code = values.value.join('')
  emit('update:modelValue', code)

  if (code.length === props.length) {
    emit('complete', code)
  }
}

const handleKeydown = (index: number, event: KeyboardEvent) => {
  if (event.key === 'Backspace') {
    if (!values.value[index] && index > 0) {
      focusInput(index - 1)
    } else {
      values.value[index] = ''
      emit('update:modelValue', values.value.join(''))
    }
  } else if (event.key === 'ArrowLeft' && index > 0) {
    focusInput(index - 1)
  } else if (event.key === 'ArrowRight' && index < props.length - 1) {
    focusInput(index + 1)
  }
}

const handlePaste = (event: ClipboardEvent) => {
  event.preventDefault()
  const pastedData = event.clipboardData?.getData('text') || ''
  const digits = pastedData.replace(/[^0-9]/g, '').split('')

  digits.forEach((digit, i) => {
    if (i < props.length) {
      values.value[i] = digit
    }
  })

  const code = values.value.join('')
  emit('update:modelValue', code)

  if (code.length === props.length) {
    emit('complete', code)
  }

  focusInput(Math.min(digits.length, props.length - 1))
}

const setInputRef = (el: Element | ComponentPublicInstance | null, index: number) => {
  if (el instanceof HTMLInputElement) {
    inputs.value[index] = el
  }
}

// Focus first input on mount
const focusFirst = () => {
  focusInput(0)
}

defineExpose({ focusFirst })
</script>

<template>
  <div class="flex justify-center gap-2 sm:gap-3">
    <input
      v-for="(_, index) in length"
      :key="index"
      :ref="(el) => setInputRef(el, index)"
      type="text"
      inputmode="numeric"
      maxlength="6"
      :value="values[index]"
      :disabled="disabled"
      :class="[
        'w-12 h-14 sm:w-14 sm:h-16 border-2 rounded-xl text-2xl text-center font-semibold transition-colors duration-200 focus:outline-none',
        error
          ? 'border-red-300 focus:border-red-500 focus:ring-2 focus:ring-red-100'
          : 'border-gray-200 focus:border-primary-500 focus:ring-2 focus:ring-primary-100',
        disabled ? 'bg-gray-50 text-gray-400 cursor-not-allowed' : 'bg-white'
      ]"
      @input="handleInput(index, $event)"
      @keydown="handleKeydown(index, $event)"
      @paste="handlePaste"
    />
  </div>

  <!-- Error message -->
  <p v-if="error" class="mt-3 text-sm text-red-600 text-center">
    {{ error }}
  </p>
</template>
