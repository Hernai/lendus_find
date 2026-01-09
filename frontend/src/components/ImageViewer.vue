<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{
  src: string
  alt?: string
  title?: string
  isVerified?: boolean
  verifiedLabel?: string
  canChange?: boolean
  changeLabel?: string
}>()

const emit = defineEmits<{
  close: []
  change: []
}>()

const displayTitle = computed(() => props.title || props.alt || 'Imagen')
const displayVerifiedLabel = computed(() => props.verifiedLabel || 'Verificado')
const displayChangeLabel = computed(() => props.changeLabel || 'Cambiar')

const handleChange = () => {
  emit('close')
  emit('change')
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
        class="fixed inset-0 z-50 bg-black/90 flex flex-col"
        @click="emit('close')"
      >
        <!-- Header -->
        <div class="flex items-center justify-between px-4 py-3 text-white">
          <h3 class="font-medium truncate">{{ displayTitle }}</h3>
          <button
            class="w-10 h-10 bg-white/10 hover:bg-white/20 rounded-full flex items-center justify-center transition-colors flex-shrink-0"
            @click="emit('close')"
          >
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <!-- Image container -->
        <div class="flex-1 flex items-center justify-center p-4 overflow-auto" @click.stop>
          <img
            :src="src"
            :alt="alt || 'Imagen'"
            class="max-w-full max-h-full object-contain rounded-lg"
          />
        </div>

        <!-- Footer with actions -->
        <div class="px-4 py-4 pb-safe flex justify-center gap-4">
          <!-- Verified badge -->
          <div
            v-if="isVerified"
            class="flex items-center gap-2 bg-green-500 text-white px-4 py-2 rounded-full shadow-lg"
          >
            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
              <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
            </svg>
            <span class="font-medium">{{ displayVerifiedLabel }}</span>
          </div>

          <!-- Change button (only if allowed) -->
          <button
            v-if="canChange && !isVerified"
            class="flex items-center gap-2 bg-white text-gray-900 px-4 py-2 rounded-full shadow-lg hover:bg-gray-100 active:bg-gray-200 transition-colors"
            @click="handleChange"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
            </svg>
            <span class="font-medium">{{ displayChangeLabel }}</span>
          </button>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
