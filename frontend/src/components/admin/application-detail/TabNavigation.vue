<script setup lang="ts">
interface Tab {
  id: string
  label: string
}

defineProps<{
  tabs: Tab[]
  activeTab: string
}>()

const emit = defineEmits<{
  (e: 'update:activeTab', value: string): void
}>()
</script>

<template>
  <div class="border-b border-gray-100">
    <nav class="flex -mb-px">
      <button
        v-for="tab in tabs"
        :key="tab.id"
        :class="[
          'px-6 py-3 text-sm font-medium border-b transition-colors',
          activeTab === tab.id
            ? 'border-primary-500 text-primary-600'
            : 'border-transparent text-gray-500 hover:text-gray-700',
        ]"
        @click="emit('update:activeTab', tab.id)"
      >
        {{ tab.label }}
      </button>
    </nav>
  </div>
</template>
