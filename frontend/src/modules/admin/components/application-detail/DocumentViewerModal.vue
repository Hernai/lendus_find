<script setup lang="ts">
/**
 * Modal visor de documento: renderiza imagen, iframe de PDF o un fallback de
 * descarga según el mime type. Puramente presentacional — NO toca lógica de
 * negocio ni muta application. La obtención de la URL firmada (viewDocument) vive
 * en el padre; aquí solo se muestra y se cierra (v-model:show).
 */
defineProps<{
  show: boolean
  url: string
  name: string
  mimeType: string
}>()

const emit = defineEmits<{
  (e: 'update:show', value: boolean): void
}>()
</script>

<template>
  <div
    v-if="show"
    class="fixed inset-0 bg-black/80 flex items-center justify-center z-50"
    @click.self="emit('update:show', false)"
  >
    <div class="relative w-full max-w-4xl mx-4 max-h-[90vh] bg-white rounded-xl overflow-hidden">
      <!-- Header -->
      <div class="flex items-center justify-between p-4 border-b border-gray-200 bg-gray-50">
        <h3 class="text-lg font-semibold text-gray-900 truncate">{{ name }}</h3>
        <div class="flex items-center gap-2">
          <a
            :href="url"
            target="_blank"
            class="p-2 text-gray-500 hover:text-gray-700 transition-colors"
            title="Abrir en nueva pestaña"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
            </svg>
          </a>
          <button
            class="p-2 text-gray-500 hover:text-gray-700 transition-colors"
            title="Cerrar"
            @click="emit('update:show', false)"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
      </div>

      <!-- Content -->
      <div class="p-4 overflow-auto" style="max-height: calc(90vh - 80px);">
        <!-- Image viewer -->
        <img
          v-if="mimeType.startsWith('image/')"
          :src="url"
          :alt="name"
          class="max-w-full h-auto mx-auto rounded-lg shadow-lg"
        />

        <!-- PDF viewer fallback (iframe) -->
        <iframe
          v-else-if="mimeType === 'application/pdf'"
          :src="url"
          class="w-full h-[70vh] rounded-lg"
          frameborder="0"
        />

        <!-- Unknown type -->
        <div v-else class="text-center py-12">
          <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
          </svg>
          <p class="text-gray-500 mb-4">Este tipo de archivo no se puede previsualizar</p>
          <a
            :href="url"
            target="_blank"
            class="inline-flex items-center gap-2 px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
            </svg>
            Descargar archivo
          </a>
        </div>
      </div>
    </div>
  </div>
</template>
