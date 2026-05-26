<script setup lang="ts">
interface ExtractedData {
  given_names?: string | null
  paternal_surname?: string | null
  maternal_surname?: string | null
  curp?: string | null
  birth_date?: string | null
  expiry_date?: string | null
}

defineProps<{
  open: boolean
  data: ExtractedData | null
  loading?: boolean
}>()

const emit = defineEmits<{
  confirm: []
  modify: []
}>()
</script>

<template>
  <Teleport to="body">
    <Transition name="fade">
      <div
        v-if="open"
        class="fixed inset-0 z-50 bg-black/60 flex items-end sm:items-center justify-center p-0 sm:p-4"
      >
        <div class="bg-white w-full sm:max-w-md rounded-t-2xl sm:rounded-2xl p-5 space-y-4 shadow-xl">
          <h3 class="text-lg font-semibold text-gray-900">Confirma los datos de tu INE</h3>
          <p class="text-sm text-gray-600">
            Por favor, confirma que eres titular de la INE y que la información extraída es auténtica y válida.
          </p>

          <div v-if="loading" class="flex items-center justify-center py-8">
            <div class="w-8 h-8 border-2 border-primary-600 border-t-transparent rounded-full animate-spin" />
            <span class="ml-3 text-sm text-gray-600">Procesando INE...</span>
          </div>

          <div v-else-if="data" class="rounded-xl border border-gray-200 divide-y divide-gray-100">
            <div class="flex justify-between px-3 py-2.5 text-sm">
              <span class="text-gray-500">Nombre(s)</span>
              <span class="font-medium text-gray-900 text-right uppercase">{{ data.given_names || '—' }}</span>
            </div>
            <div class="flex justify-between px-3 py-2.5 text-sm">
              <span class="text-gray-500">Apellido paterno</span>
              <span class="font-medium text-gray-900 text-right uppercase">{{ data.paternal_surname || '—' }}</span>
            </div>
            <div class="flex justify-between px-3 py-2.5 text-sm">
              <span class="text-gray-500">Apellido materno</span>
              <span class="font-medium text-gray-900 text-right uppercase">{{ data.maternal_surname || '—' }}</span>
            </div>
            <div class="flex justify-between px-3 py-2.5 text-sm">
              <span class="text-gray-500">CURP</span>
              <span class="font-mono text-gray-900 text-right">{{ data.curp || '—' }}</span>
            </div>
            <div v-if="data.birth_date" class="flex justify-between px-3 py-2.5 text-sm">
              <span class="text-gray-500">Fecha de nacimiento</span>
              <span class="font-medium text-gray-900">{{ data.birth_date }}</span>
            </div>
            <div v-if="data.expiry_date" class="flex justify-between px-3 py-2.5 text-sm">
              <span class="text-gray-500">Vigencia</span>
              <span class="font-medium text-gray-900">{{ data.expiry_date }}</span>
            </div>
          </div>

          <div class="flex flex-col gap-2 pt-2">
            <button
              type="button"
              class="w-full py-3 bg-primary-600 text-white rounded-xl font-semibold text-base hover:bg-primary-700 active:bg-primary-800 transition-colors disabled:opacity-50"
              :disabled="loading"
              @click="emit('confirm')"
            >
              Confirmar sin errores
            </button>
            <button
              type="button"
              class="w-full py-3 bg-gray-100 text-gray-700 rounded-xl font-semibold text-base hover:bg-gray-200 active:bg-gray-300 transition-colors"
              :disabled="loading"
              @click="emit('modify')"
            >
              Modificar
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
