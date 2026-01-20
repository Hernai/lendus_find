<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import AppButton from './AppButton.vue'

export interface SelectOption {
  value: string
  label: string
}

interface Props {
  show: boolean
  title?: string
  subtitle?: string
  message?: string
  confirmText?: string
  cancelText?: string
  variant?: 'primary' | 'danger' | 'warning' | 'success'
  loading?: boolean
  confirmDisabled?: boolean
  icon?: 'warning' | 'danger' | 'success' | 'info' | 'question' | 'check' | 'x' | 'trash' | 'undo'
  size?: 'sm' | 'md' | 'lg' | 'xl'
  // Form fields
  selectLabel?: string
  selectOptions?: SelectOption[]
  selectRequired?: boolean
  selectPlaceholder?: string
  commentLabel?: string
  commentPlaceholder?: string
  commentRequired?: boolean
  commentRows?: number
}

const props = withDefaults(defineProps<Props>(), {
  title: '¿Estás seguro?',
  subtitle: '',
  message: '',
  confirmText: 'Confirmar',
  cancelText: 'Cancelar',
  variant: 'primary',
  loading: false,
  confirmDisabled: false,
  icon: 'question',
  size: 'sm',
  selectLabel: '',
  selectOptions: () => [],
  selectRequired: false,
  selectPlaceholder: 'Selecciona una opción',
  commentLabel: '',
  commentPlaceholder: '',
  commentRequired: false,
  commentRows: 3
})

const sizeClass = computed(() => {
  const sizes = {
    sm: 'max-w-sm',
    md: 'max-w-md',
    lg: 'max-w-lg',
    xl: 'max-w-xl'
  }
  return sizes[props.size]
})

const emit = defineEmits<{
  confirm: [data?: { selectValue?: string; comment?: string }]
  cancel: []
  'update:show': [value: boolean]
}>()

// Form state
const selectValue = ref('')
const comment = ref('')

// Reset form when modal opens
watch(() => props.show, (newVal) => {
  if (newVal) {
    selectValue.value = ''
    comment.value = ''
  }
})

// Check if form is valid
const hasForm = computed(() => {
  return props.selectOptions.length > 0 || !!props.commentLabel
})

const isFormValid = computed(() => {
  if (props.selectOptions.length > 0 && props.selectRequired && !selectValue.value) {
    return false
  }
  if (props.commentLabel && props.commentRequired && !comment.value.trim()) {
    return false
  }
  return true
})

const close = () => {
  emit('update:show', false)
  emit('cancel')
}

const confirm = () => {
  if (!isFormValid.value) return

  if (hasForm.value) {
    emit('confirm', {
      selectValue: selectValue.value || undefined,
      comment: comment.value?.trim() || undefined
    })
  } else {
    emit('confirm')
  }
}

const iconConfigs: Record<string, { bg: string; color: string; path: string }> = {
  warning: {
    bg: 'bg-yellow-100',
    color: 'text-yellow-600',
    path: 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z'
  },
  danger: {
    bg: 'bg-red-100',
    color: 'text-red-600',
    path: 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z'
  },
  success: {
    bg: 'bg-green-100',
    color: 'text-green-600',
    path: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'
  },
  check: {
    bg: 'bg-green-100',
    color: 'text-green-600',
    path: 'M5 13l4 4L19 7'
  },
  info: {
    bg: 'bg-blue-100',
    color: 'text-blue-600',
    path: 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z'
  },
  question: {
    bg: 'bg-primary-100',
    color: 'text-primary-600',
    path: 'M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z'
  },
  x: {
    bg: 'bg-red-100',
    color: 'text-red-600',
    path: 'M6 18L18 6M6 6l12 12'
  },
  trash: {
    bg: 'bg-red-100',
    color: 'text-red-600',
    path: 'M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16'
  },
  undo: {
    bg: 'bg-blue-100',
    color: 'text-blue-600',
    path: 'M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6'
  }
}

const iconConfig = computed(() => iconConfigs[props.icon] || iconConfigs.question)

const buttonVariant = computed(() => {
  const map: Record<string, 'primary' | 'danger'> = {
    primary: 'primary',
    danger: 'danger',
    warning: 'primary',
    success: 'primary'
  }
  return map[props.variant] || 'primary'
})

const buttonClass = computed(() => {
  const classes: Record<string, string> = {
    primary: '',
    danger: '!bg-red-600 hover:!bg-red-700',
    warning: '!bg-yellow-600 hover:!bg-yellow-700',
    success: '!bg-green-600 hover:!bg-green-700'
  }
  return classes[props.variant]
})

const focusRingClass = computed(() => {
  const classes: Record<string, string> = {
    primary: 'focus:ring-primary-500',
    danger: 'focus:ring-red-500',
    warning: 'focus:ring-yellow-500',
    success: 'focus:ring-green-500'
  }
  return classes[props.variant]
})
</script>

<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition-opacity duration-200"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition-opacity duration-200"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="show"
        class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4"
        @click.self="close"
      >
        <Transition
          enter-active-class="transition-all duration-200"
          enter-from-class="opacity-0 scale-95"
          enter-to-class="opacity-100 scale-100"
          leave-active-class="transition-all duration-200"
          leave-from-class="opacity-100 scale-100"
          leave-to-class="opacity-0 scale-95"
        >
          <div
            v-if="show"
            :class="['bg-white rounded-xl p-6 w-full shadow-xl', sizeClass]"
          >
            <!-- Header with icon -->
            <div :class="['flex gap-4 mb-4', hasForm ? 'items-start' : 'flex-col items-center']">
              <!-- Icon -->
              <div
                v-if="iconConfig"
                :class="[
                  'w-12 h-12 rounded-full flex items-center justify-center flex-shrink-0',
                  iconConfig.bg
                ]"
              >
                <svg
                  :class="['w-6 h-6', iconConfig.color]"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    :d="iconConfig.path"
                  />
                </svg>
              </div>

              <!-- Title & Subtitle -->
              <div :class="['flex-1 min-w-0', hasForm ? '' : 'text-center']">
                <h3 class="text-lg font-semibold text-gray-900">
                  {{ title }}
                </h3>
                <p v-if="subtitle" class="text-sm text-gray-500">
                  {{ subtitle }}
                </p>
              </div>
            </div>

            <!-- Message -->
            <p v-if="message && !hasForm" class="text-sm text-gray-500 text-center mb-6">
              {{ message }}
            </p>

            <!-- Form fields -->
            <div v-if="hasForm" class="space-y-4 mb-6">
              <!-- Select -->
              <div v-if="selectOptions.length > 0">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  {{ selectLabel }}
                  <span v-if="selectRequired" class="text-red-500">*</span>
                </label>
                <select
                  v-model="selectValue"
                  :class="[
                    'w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:border-primary-500 transition-colors',
                    focusRingClass
                  ]"
                >
                  <option value="">{{ selectPlaceholder }}</option>
                  <option
                    v-for="option in selectOptions"
                    :key="option.value"
                    :value="option.value"
                  >
                    {{ option.label }}
                  </option>
                </select>
              </div>

              <!-- Comment/Textarea -->
              <div v-if="commentLabel">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  {{ commentLabel }}
                  <span v-if="commentRequired" class="text-red-500">*</span>
                </label>
                <textarea
                  v-model="comment"
                  :rows="commentRows"
                  :class="[
                    'w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:border-primary-500 transition-colors resize-none',
                    focusRingClass
                  ]"
                  :placeholder="commentPlaceholder"
                />
              </div>
            </div>

            <!-- Slot for custom content -->
            <div v-if="$slots.default" class="mb-6">
              <slot />
            </div>

            <!-- Actions -->
            <div class="flex gap-3">
              <AppButton
                variant="outline"
                class="flex-1"
                :disabled="loading"
                @click="close"
              >
                {{ cancelText }}
              </AppButton>
              <AppButton
                :variant="buttonVariant"
                :class="['flex-1', buttonClass]"
                :loading="loading"
                :disabled="confirmDisabled || loading || !isFormValid"
                @click="confirm"
              >
                {{ confirmText }}
              </AppButton>
            </div>
          </div>
        </Transition>
      </div>
    </Transition>
  </Teleport>
</template>
