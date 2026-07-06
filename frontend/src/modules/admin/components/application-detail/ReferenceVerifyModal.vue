<script setup lang="ts">
/**
 * Modal "Verificar Referencia": resultado (radios) + notas de la llamada. Extraído
 * de AdminApplicationDetail.vue.
 *
 * El estado de formulario (refVerifyResult/refVerifyNotes) es local; se siembra al
 * abrir (watch sobre show: resultado 'VERIFIED', notas ''). La verificación real
 * (confirmVerifyReference, con mutación optimista) queda en el padre, que recibe
 * { result, notes } por @confirm. Cierre vía v-model:show.
 */
import { ref, watch } from 'vue'
import { AppButton } from '@/components/common'
import { formatPhone } from '@/utils/formatters'
import type { Reference } from '@/modules/admin/views/panel/applicationDetail.types'

const props = defineProps<{
  show: boolean
  reference: Reference | null
  isVerifying: boolean
}>()

const emit = defineEmits<{
  (e: 'update:show', value: boolean): void
  (e: 'confirm', payload: { result: 'VERIFIED' | 'NOT_VERIFIED' | 'NO_ANSWER'; notes: string }): void
}>()

const refVerifyResult = ref<'VERIFIED' | 'NOT_VERIFIED' | 'NO_ANSWER'>('VERIFIED')
const refVerifyNotes = ref('')

watch(() => props.show, (v) => {
  if (v) {
    refVerifyResult.value = 'VERIFIED'
    refVerifyNotes.value = ''
  }
})
</script>

<template>
<div
  v-if="show && reference"
  class="fixed inset-0 bg-black/50 flex items-center justify-center z-50"
  @click.self="emit('update:show', false)"
>
  <div class="bg-white rounded-xl p-6 w-full max-w-md mx-4">
    <h3 class="text-lg font-semibold text-gray-900 mb-2">Verificar Referencia</h3>
    <div class="bg-gray-50 rounded-lg p-3 mb-4">
      <p class="font-medium">{{ reference.full_name }}</p>
      <p class="text-sm text-gray-500">{{ reference.relationship }} · {{ formatPhone(reference.phone) }}</p>
    </div>

    <div class="space-y-4">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">
          Resultado de verificación
        </label>
        <div class="space-y-2">
          <label class="flex items-center gap-3 p-3 border rounded-lg cursor-pointer hover:bg-gray-50"
            :class="{ 'border-green-500 bg-green-50': refVerifyResult === 'VERIFIED' }"
          >
            <input
              v-model="refVerifyResult"
              type="radio"
              value="VERIFIED"
              class="text-green-600 focus:ring-green-500"
            />
            <div>
              <p class="font-medium text-gray-900">Verificada</p>
              <p class="text-sm text-gray-500">La referencia confirmó conocer al solicitante</p>
            </div>
          </label>

          <label class="flex items-center gap-3 p-3 border rounded-lg cursor-pointer hover:bg-gray-50"
            :class="{ 'border-red-500 bg-red-50': refVerifyResult === 'NOT_VERIFIED' }"
          >
            <input
              v-model="refVerifyResult"
              type="radio"
              value="NOT_VERIFIED"
              class="text-red-600 focus:ring-red-500"
            />
            <div>
              <p class="font-medium text-gray-900">No verificada</p>
              <p class="text-sm text-gray-500">Datos incorrectos o no conoce al solicitante</p>
            </div>
          </label>

          <label class="flex items-center gap-3 p-3 border rounded-lg cursor-pointer hover:bg-gray-50"
            :class="{ 'border-yellow-500 bg-yellow-50': refVerifyResult === 'NO_ANSWER' }"
          >
            <input
              v-model="refVerifyResult"
              type="radio"
              value="NO_ANSWER"
              class="text-yellow-600 focus:ring-yellow-500"
            />
            <div>
              <p class="font-medium text-gray-900">Sin respuesta</p>
              <p class="text-sm text-gray-500">No contestaron o número fuera de servicio</p>
            </div>
          </label>
        </div>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">
          Notas de la llamada
        </label>
        <textarea
          v-model="refVerifyNotes"
          rows="3"
          class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
          placeholder="Comentarios adicionales sobre la verificación..."
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
        :loading="isVerifying"
        @click="emit('confirm', { result: refVerifyResult, notes: refVerifyNotes })"
      >
        Guardar
      </AppButton>
    </div>
  </div>
</div>
</template>
