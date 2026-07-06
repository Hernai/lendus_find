<script setup lang="ts">
/**
 * Estado vacío del detalle: "Solicitud no encontrada" con botón para volver al
 * listado. Se renderiza en la rama v-else (sin loading, sin error, sin application),
 * que permanece en el padre. Emite `back` (mapeado a goBack, que usa el router).
 */
defineProps<{
  primaryColor: string
}>()

const emit = defineEmits<{
  (e: 'back'): void
}>()
</script>

<template>
  <div class="flex flex-col items-center justify-center py-12 text-center">
    <svg class="w-16 h-16 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
    </svg>
    <h2 class="text-xl font-semibold text-gray-900 mb-2">Solicitud no encontrada</h2>
    <p class="text-gray-600 mb-4">La solicitud que buscas no existe o no tienes acceso.</p>
    <button
      class="px-4 py-2 text-sm font-medium text-white rounded-lg transition-colors"
      :style="{ backgroundColor: primaryColor }"
      @click="emit('back')"
    >
      Volver al listado
    </button>
  </div>
</template>
