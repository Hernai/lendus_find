<script setup lang="ts">
/**
 * Modal "Rechazar Documento": motivo + comentario. Extraído de AdminApplicationDetail.vue.
 *
 * El estado de formulario (docRejectReason/docRejectComment) es local; se resetea al
 * abrir (watch sobre show). El rechazo real (confirmRejectDocument, con mutación
 * optimista) queda en el padre, que recibe { reason, comment } por @confirm. El
 * catálogo de motivos llega por prop (se reutiliza en el modal de rechazo de selfie).
 */
import { ref, watch } from 'vue'
import { AppButton } from '@/components/common'
import type { Document } from '@/modules/admin/views/panel/applicationDetail.types'

const props = defineProps<{
  show: boolean
  document: Document | null
  reasons: Array<{ value: string; label: string }>
  isRejecting: boolean
}>()

const emit = defineEmits<{
  (e: 'update:show', value: boolean): void
  (e: 'confirm', payload: { reason: string; comment: string }): void
}>()

const docRejectReason = ref('')
const docRejectComment = ref('')

watch(() => props.show, (v) => {
  if (v) {
    docRejectReason.value = ''
    docRejectComment.value = ''
  }
})
</script>

<template>
<div
  v-if="show && document"
  class="fixed inset-0 bg-black/50 flex items-center justify-center z-50"
  @click.self="emit('update:show', false)"
>
  <div class="bg-white rounded-xl p-6 w-full max-w-md mx-4">
    <h3 class="text-lg font-semibold text-gray-900 mb-2">Rechazar Documento</h3>
    <p class="text-sm text-gray-500 mb-4">{{ document.name }}</p>

    <div class="space-y-4">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">
          Motivo de rechazo <span class="text-red-500">*</span>
        </label>
        <select
          v-model="docRejectReason"
          class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent"
        >
          <option value="">Seleccionar motivo...</option>
          <option v-for="reason in reasons" :key="reason.value" :value="reason.value">
            {{ reason.label }}
          </option>
        </select>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">
          Comentario adicional
        </label>
        <textarea
          v-model="docRejectComment"
          rows="3"
          class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent"
          placeholder="Detalle adicional para el solicitante..."
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
        class="flex-1 !bg-red-600 hover:!bg-red-700"
        :loading="isRejecting"
        :disabled="!docRejectReason"
        @click="emit('confirm', { reason: docRejectReason, comment: docRejectComment })"
      >
        Rechazar
      </AppButton>
    </div>
  </div>
</div>
</template>
