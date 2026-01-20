<script setup lang="ts">
import { ref, computed, watch } from 'vue'

export interface SelectOption {
  value: string
  label: string
}

export interface ConfirmModalProps {
  show: boolean
  title: string
  subtitle?: string
  message?: string
  // Icon
  icon?: 'check' | 'x' | 'warning' | 'info' | 'question' | 'trash' | 'undo' | null
  iconColor?: 'green' | 'red' | 'yellow' | 'blue' | 'gray'
  // Select/Combo
  selectLabel?: string
  selectOptions?: SelectOption[]
  selectRequired?: boolean
  selectPlaceholder?: string
  // Comment/Textarea
  commentLabel?: string
  commentPlaceholder?: string
  commentRequired?: boolean
  commentRows?: number
  // Buttons
  confirmText?: string
  cancelText?: string
  confirmColor?: 'green' | 'red' | 'yellow' | 'blue' | 'primary'
  // State
  loading?: boolean
}

const props = withDefaults(defineProps<ConfirmModalProps>(), {
  subtitle: '',
  message: '',
  icon: null,
  iconColor: 'blue',
  selectLabel: '',
  selectOptions: () => [],
  selectRequired: false,
  selectPlaceholder: 'Selecciona una opción',
  commentLabel: '',
  commentPlaceholder: '',
  commentRequired: false,
  commentRows: 3,
  confirmText: 'Confirmar',
  cancelText: 'Cancelar',
  confirmColor: 'primary',
  loading: false
})

const emit = defineEmits<{
  'update:show': [value: boolean]
  'confirm': [data: { selectValue?: string; comment?: string }]
  'cancel': []
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
const isValid = computed(() => {
  if (props.selectOptions.length > 0 && props.selectRequired && !selectValue.value) {
    return false
  }
  if (props.commentLabel && props.commentRequired && !comment.value.trim()) {
    return false
  }
  return true
})

// Has any form fields
const hasForm = computed(() => {
  return props.selectOptions.length > 0 || !!props.commentLabel
})

// Icon config
const iconConfig = computed(() => {
  const icons = {
    check: {
      path: 'M5 13l4 4L19 7',
      fill: false
    },
    x: {
      path: 'M6 18L18 6M6 6l12 12',
      fill: false
    },
    warning: {
      path: 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z',
      fill: false
    },
    info: {
      path: 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
      fill: false
    },
    question: {
      path: 'M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
      fill: false
    },
    trash: {
      path: 'M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16',
      fill: false
    },
    undo: {
      path: 'M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6',
      fill: false
    }
  }
  return props.icon ? icons[props.icon] : null
})

const iconBgClass = computed(() => {
  const colors = {
    green: 'bg-green-100',
    red: 'bg-red-100',
    yellow: 'bg-yellow-100',
    blue: 'bg-blue-100',
    gray: 'bg-gray-100'
  }
  return colors[props.iconColor]
})

const iconTextClass = computed(() => {
  const colors = {
    green: 'text-green-600',
    red: 'text-red-600',
    yellow: 'text-yellow-600',
    blue: 'text-blue-600',
    gray: 'text-gray-600'
  }
  return colors[props.iconColor]
})

const confirmBtnClass = computed(() => {
  const colors = {
    green: 'bg-green-600 hover:bg-green-700',
    red: 'bg-red-600 hover:bg-red-700',
    yellow: 'bg-yellow-600 hover:bg-yellow-700',
    blue: 'bg-blue-600 hover:bg-blue-700',
    primary: 'bg-primary-600 hover:bg-primary-700'
  }
  return colors[props.confirmColor]
})

const focusRingClass = computed(() => {
  const colors = {
    green: 'focus:ring-green-500',
    red: 'focus:ring-red-500',
    yellow: 'focus:ring-yellow-500',
    blue: 'focus:ring-blue-500',
    primary: 'focus:ring-primary-500'
  }
  return colors[props.confirmColor]
})

// Actions
const close = () => {
  emit('update:show', false)
  emit('cancel')
}

const confirm = () => {
  if (!isValid.value) return

  emit('confirm', {
    selectValue: selectValue.value || undefined,
    comment: comment.value?.trim() || undefined
  })
}
</script>

<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition-opacity duration-200"
      leave-active-class="transition-opacity duration-200"
      enter-from-class="opacity-0"
      leave-to-class="opacity-0"
    >
      <div
        v-if="show"
        class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4"
        @click.self="close"
      >
        <div class="bg-white rounded-xl p-6 w-full max-w-md shadow-xl">
          <!-- Header with optional icon -->
          <div class="flex items-start gap-4 mb-4">
            <!-- Icon -->
            <div
              v-if="iconConfig"
              class="w-12 h-12 rounded-full flex items-center justify-center flex-shrink-0"
              :class="iconBgClass"
            >
              <svg
                class="w-6 h-6"
                :class="iconTextClass"
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
            <div class="flex-1 min-w-0">
              <h3 class="text-lg font-semibold text-gray-900">{{ title }}</h3>
              <p v-if="subtitle" class="text-sm text-gray-500">{{ subtitle }}</p>
            </div>
          </div>

          <!-- Message (simple confirmation) -->
          <p v-if="message && !hasForm" class="text-gray-600 mb-6">
            {{ message }}
          </p>

          <!-- Form fields -->
          <div v-if="hasForm" class="space-y-4 mb-6">
            <!-- Select/Combo -->
            <div v-if="selectOptions.length > 0">
              <label class="block text-sm font-medium text-gray-700 mb-2">
                {{ selectLabel }}
                <span v-if="selectRequired" class="text-red-500">*</span>
              </label>
              <select
                v-model="selectValue"
                class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:border-primary-500 transition-colors"
                :class="focusRingClass"
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
                class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:border-primary-500 transition-colors resize-none"
                :class="focusRingClass"
                :placeholder="commentPlaceholder"
              />
            </div>
          </div>

          <!-- Actions -->
          <div class="flex gap-3">
            <button
              class="flex-1 px-4 py-2.5 border border-gray-200 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors font-medium"
              :disabled="loading"
              @click="close"
            >
              {{ cancelText }}
            </button>
            <button
              class="flex-1 px-4 py-2.5 text-white rounded-lg transition-colors font-medium flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed"
              :class="confirmBtnClass"
              :disabled="loading || !isValid"
              @click="confirm"
            >
              <div
                v-if="loading"
                class="animate-spin w-4 h-4 border-2 border-white border-t-transparent rounded-full"
              />
              <span>{{ loading ? 'Procesando...' : confirmText }}</span>
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
