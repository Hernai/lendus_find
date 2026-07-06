<script setup lang="ts">
/**
 * Modal "Cambiar Estado" de la solicitud. Extraído de AdminApplicationDetail.vue.
 *
 * El estado de formulario (newStatus/statusNote) es local al modal; se siembra el
 * estado actual al abrir (watch sobre show, equivalente a openStatusModal). La
 * validación de transición + API (updateStatus) queda en el padre, que recibe el
 * payload por @confirm. Cierre vía v-model:show.
 */
import { ref, watch } from 'vue'
import { AppButton } from '@/components/common'

const props = defineProps<{
  show: boolean
  statusOptions: Array<{ value: string; label: string }>
  currentStatus: string
  loading: boolean
}>()

const emit = defineEmits<{
  (e: 'update:show', value: boolean): void
  (e: 'confirm', payload: { status: string; notes?: string }): void
}>()

const newStatus = ref('')
const statusNote = ref('')

// Sin immediate: siembra solo en la transición a show=true (preserva el timing de
// openStatusModal, que sembraba justo antes de mostrar el modal).
watch(() => props.show, (v) => {
  if (v) {
    newStatus.value = props.currentStatus
    statusNote.value = ''
  }
})
</script>

<template>
<div
  v-if="show"
  class="fixed inset-0 bg-black/50 flex items-center justify-center z-50"
  @click.self="emit('update:show', false)"
>
  <div class="bg-white rounded-xl p-6 w-full max-w-md mx-4">
    <h3 class="text-lg font-semibold text-gray-900 mb-4">Cambiar Estado</h3>

    <div class="space-y-4">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">Nuevo Estado</label>
        <select
          v-model="newStatus"
          class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
        >
          <option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">
            {{ opt.label }}
          </option>
        </select>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">Nota (opcional)</label>
        <textarea
          v-model="statusNote"
          rows="3"
          class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
          placeholder="Agregar una nota sobre el cambio de estado..."
        />
      </div>
    </div>

    <div class="flex gap-3 mt-6">
      <AppButton
        variant="outline"
        class="flex-1"
        @click="emit('update:show', false)"
      >
        Cancelar
      </AppButton>
      <AppButton
        variant="primary"
        class="flex-1"
        :loading="loading"
        @click="emit('confirm', { status: newStatus, notes: statusNote || undefined })"
      >
        Guardar
      </AppButton>
    </div>
  </div>
</div>
</template>
