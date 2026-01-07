<script setup lang="ts">
import { computed } from 'vue'
import AppButton from './AppButton.vue'

interface Props {
  show: boolean
  title?: string
  message?: string
  confirmText?: string
  cancelText?: string
  variant?: 'primary' | 'danger' | 'warning' | 'success'
  loading?: boolean
  confirmDisabled?: boolean
  icon?: 'warning' | 'danger' | 'success' | 'info' | 'question'
  size?: 'sm' | 'md' | 'lg' | 'xl'
}

const props = withDefaults(defineProps<Props>(), {
  title: '¿Estás seguro?',
  message: '',
  confirmText: 'Confirmar',
  cancelText: 'Cancelar',
  variant: 'primary',
  loading: false,
  confirmDisabled: false,
  icon: 'question',
  size: 'sm'
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
  confirm: []
  cancel: []
  'update:show': [value: boolean]
}>()

const close = () => {
  emit('update:show', false)
  emit('cancel')
}

const confirm = () => {
  emit('confirm')
}

const iconConfig = computed(() => {
  const configs = {
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
    info: {
      bg: 'bg-blue-100',
      color: 'text-blue-600',
      path: 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z'
    },
    question: {
      bg: 'bg-primary-100',
      color: 'text-primary-600',
      path: 'M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z'
    }
  }
  return configs[props.icon]
})

const buttonVariantClass = computed(() => {
  const classes = {
    primary: '',
    danger: '!bg-red-600 hover:!bg-red-700',
    warning: '!bg-yellow-600 hover:!bg-yellow-700',
    success: '!bg-green-600 hover:!bg-green-700'
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
        class="fixed inset-0 bg-black/50 flex items-center justify-center z-50"
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
            :class="['bg-white rounded-xl p-6 w-full mx-4 shadow-xl', sizeClass]"
          >
            <!-- Icon -->
            <div class="flex justify-center mb-4">
              <div
                :class="[
                  'w-12 h-12 rounded-full flex items-center justify-center',
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
            </div>

            <!-- Title -->
            <h3 class="text-lg font-semibold text-gray-900 text-center mb-2">
              {{ title }}
            </h3>

            <!-- Message -->
            <p v-if="message" class="text-sm text-gray-500 text-center mb-6">
              {{ message }}
            </p>

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
                variant="primary"
                :class="['flex-1', buttonVariantClass]"
                :loading="loading"
                :disabled="confirmDisabled || loading"
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
