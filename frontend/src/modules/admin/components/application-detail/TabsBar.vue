<script setup lang="ts">
/**
 * Barra de navegación de pestañas del detalle. Solo el <nav>: el contenido de
 * cada tab (v-if="activeTab === ...") permanece en el padre.
 *
 * La pestaña activa se controla con v-model:active-tab. Extraído de
 * AdminApplicationDetail.vue.
 */
defineProps<{
  tabs: Array<{ id: string; label: string }>
  activeTab: string
}>()

const emit = defineEmits<{
  (e: 'update:activeTab', id: string): void
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
            : 'border-transparent text-gray-500 hover:text-gray-700'
        ]"
        @click="emit('update:activeTab', tab.id)"
      >
        {{ tab.label }}
      </button>
    </nav>
  </div>
</template>
