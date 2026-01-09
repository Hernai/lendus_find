<script setup lang="ts">
import { ref, watch, onMounted, onBeforeUnmount } from 'vue'

interface Props {
  modelValue: boolean
  title?: string
  showHandle?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  showHandle: true
})

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
}>()

const sheetRef = ref<HTMLElement | null>(null)
const contentRef = ref<HTMLElement | null>(null)
const isDragging = ref(false)
const startY = ref(0)
const currentY = ref(0)
const sheetHeight = ref(0)

const close = () => {
  emit('update:modelValue', false)
}

// Handle touch events for swipe-to-close
const handleTouchStart = (e: TouchEvent) => {
  if (!e.touches[0]) return
  isDragging.value = true
  startY.value = e.touches[0].clientY
  currentY.value = 0
  if (sheetRef.value) {
    sheetHeight.value = sheetRef.value.offsetHeight
  }
}

const handleTouchMove = (e: TouchEvent) => {
  if (!isDragging.value || !e.touches[0]) return
  const deltaY = e.touches[0].clientY - startY.value
  // Only allow dragging down
  if (deltaY > 0) {
    currentY.value = deltaY
    if (sheetRef.value) {
      sheetRef.value.style.transform = `translateY(${deltaY}px)`
    }
  }
}

const handleTouchEnd = () => {
  if (!isDragging.value) return
  isDragging.value = false

  // If dragged more than 30% of height, close
  if (currentY.value > sheetHeight.value * 0.3) {
    close()
  } else {
    // Snap back
    if (sheetRef.value) {
      sheetRef.value.style.transform = ''
    }
  }
  currentY.value = 0
}

// Lock body scroll when open
watch(() => props.modelValue, (isOpen) => {
  if (isOpen) {
    document.body.style.overflow = 'hidden'
  } else {
    document.body.style.overflow = ''
    // Reset transform when closing
    if (sheetRef.value) {
      sheetRef.value.style.transform = ''
    }
  }
})

onBeforeUnmount(() => {
  document.body.style.overflow = ''
})
</script>

<template>
  <Teleport to="body">
    <Transition name="bottomsheet">
      <div
        v-if="modelValue"
        class="fixed inset-0 z-50"
        @click.self="close"
      >
        <!-- Backdrop -->
        <div
          class="absolute inset-0 bg-black/50 transition-opacity"
          @click="close"
        />

        <!-- Sheet -->
        <div
          ref="sheetRef"
          class="absolute bottom-0 left-0 right-0 bg-white rounded-t-3xl max-h-[85vh] flex flex-col transition-transform duration-200 ease-out"
          :class="{ 'transition-none': isDragging }"
          @touchstart="handleTouchStart"
          @touchmove="handleTouchMove"
          @touchend="handleTouchEnd"
        >
          <!-- Handle -->
          <div v-if="showHandle" class="flex justify-center pt-3 pb-2 cursor-grab active:cursor-grabbing">
            <div class="w-10 h-1 bg-gray-300 rounded-full" />
          </div>

          <!-- Header -->
          <div v-if="title" class="flex items-center justify-between px-4 py-3 border-b border-gray-100">
            <h3 class="text-lg font-semibold text-gray-900">{{ title }}</h3>
            <button
              type="button"
              class="p-2 -mr-2 text-gray-400 hover:text-gray-600 transition-colors"
              @click="close"
            >
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <!-- Content -->
          <div ref="contentRef" class="flex-1 overflow-y-auto overscroll-contain">
            <slot />
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.bottomsheet-enter-active,
.bottomsheet-leave-active {
  transition: opacity 0.2s ease;
}

.bottomsheet-enter-active .absolute:last-child,
.bottomsheet-leave-active .absolute:last-child {
  transition: transform 0.3s ease;
}

.bottomsheet-enter-from,
.bottomsheet-leave-to {
  opacity: 0;
}

.bottomsheet-enter-from .absolute:last-child,
.bottomsheet-leave-to .absolute:last-child {
  transform: translateY(100%);
}
</style>