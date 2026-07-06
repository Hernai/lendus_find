<script setup lang="ts">
/**
 * Card "Firma Digital": muestra la firma base64 + metadatos (fecha/IP) cuando el
 * solicitante ya firmó, o el estado "Pendiente de firma".
 *
 * Extraído de AdminApplicationDetail.vue. 100% presentación: recibe `signature`
 * por prop, cero emits. La condición de visibilidad externa
 * (requiresSignature || signature?.has_signed) se queda en el padre, que envuelve
 * este componente con v-if.
 */
import { formatDateTime } from '@/utils/formatters'
import type { Application } from '@/modules/admin/views/panel/applicationDetail.types'

defineProps<{
  signature: Application['signature']
}>()
</script>

<template>
  <div class="border border-gray-200 rounded-lg">
    <div class="bg-gray-50 px-3 py-2 border-b border-gray-200">
      <h3 class="text-sm font-semibold text-gray-900">Firma Digital</h3>
    </div>
    <div class="p-3">
      <div v-if="signature?.has_signed" class="flex items-start gap-4">
        <div class="flex-shrink-0">
          <img
            v-if="signature.signature_base64"
            :src="signature.signature_base64.startsWith('data:') ? signature.signature_base64 : `data:image/png;base64,${signature.signature_base64}`"
            alt="Firma del solicitante"
            class="w-48 h-24 object-contain border border-gray-200 rounded bg-white"
          >
          <div v-else class="w-48 h-24 flex items-center justify-center border border-gray-200 rounded bg-gray-50 text-gray-400 text-sm">
            Firma no disponible
          </div>
        </div>
        <div class="text-sm">
          <div class="flex items-center gap-2 text-green-600 mb-1">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span class="font-medium">Firmado digitalmente</span>
          </div>
          <p v-if="signature.signature_date" class="text-xs text-gray-500">
            Fecha: {{ formatDateTime(signature.signature_date) }}
          </p>
          <p v-if="signature.signature_ip" class="text-xs text-gray-500">
            IP: {{ signature.signature_ip }}
          </p>
        </div>
      </div>
      <div v-else class="flex items-center gap-2 text-amber-600">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
        </svg>
        <span class="text-sm">Pendiente de firma</span>
      </div>
    </div>
  </div>
</template>
