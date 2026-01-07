<script setup lang="ts">
import { computed } from 'vue'

interface Props {
  current: number
  total: number
  showLabel?: boolean
  height?: 'sm' | 'md' | 'lg'
}

const props = withDefaults(defineProps<Props>(), {
  showLabel: true,
  height: 'sm'
})

const percentage = computed(() => {
  return Math.min(Math.round((props.current / props.total) * 100), 100)
})

const heightClasses = {
  sm: 'h-1.5',
  md: 'h-2',
  lg: 'h-3'
}
</script>

<template>
  <div class="w-full">
    <!-- Label -->
    <div v-if="showLabel" class="flex justify-between items-center mb-2">
      <span class="text-sm text-gray-500">Paso {{ current }} de {{ total }}</span>
      <span class="text-sm font-medium text-primary-600">{{ percentage }}%</span>
    </div>

    <!-- Progress bar -->
    <div :class="['w-full bg-gray-200 rounded-full overflow-hidden', heightClasses[height]]">
      <div
        class="bg-primary-600 rounded-full transition-all duration-300 ease-out"
        :class="heightClasses[height]"
        :style="{ width: `${percentage}%` }"
      />
    </div>
  </div>
</template>
