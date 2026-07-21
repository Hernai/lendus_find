<script setup lang="ts">
/**
 * Modal (teleportado a body) con la comparación de INE: datos confirmados vs OCR
 * vs RENAPO. Display-only. Extraído de AdminApplicationDetail.vue.
 *
 * El computed ineComparison y el ref showIneVerification se quedan en el padre
 * (también alimentan ApplicantDataSection y reverifyIne); aquí solo se muestra la
 * tabla y se cierra vía v-model:show.
 */
import type { IneComparison } from '@/modules/admin/views/panel/applicationDetail.types'

defineProps<{
  show: boolean
  comparison: IneComparison | null
}>()

const emit = defineEmits<{
  (e: 'update:show', value: boolean): void
}>()
</script>

<template>
<Teleport to="body">
  <div
    v-if="comparison && show"
    class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40"
  >
    <div
      class="bg-white rounded-lg shadow-xl w-full max-w-3xl max-h-[85vh] overflow-hidden flex flex-col"
      :class="comparison.hasDiffs ? 'ring-1 ring-amber-300' : ''"
    >
      <div
        class="px-5 py-3 flex items-center justify-between gap-3 border-b border-gray-100"
        :class="comparison.hasDiffs ? 'bg-amber-50' : 'bg-gray-50'"
      >
        <span class="font-semibold text-gray-800 inline-flex items-center gap-2">
          Verificación de INE
          <span v-if="comparison.hasDiffs" class="text-xs font-semibold text-amber-700 bg-amber-100 px-1.5 py-0.5 rounded">
            Revisar diferencias
          </span>
        </span>
        <div class="flex items-center gap-3 text-xs">
          <span :class="comparison.ineValid ? 'text-green-700' : 'text-gray-500'">
            INE {{ comparison.ineValid === true ? '✓' : comparison.ineValid === false ? '✕' : '—' }}
          </span>
          <span :class="comparison.curpValid ? 'text-green-700' : 'text-gray-500'">
            RENAPO {{ comparison.curpValid === true ? '✓' : comparison.curpValid === false ? '✕' : '—' }}
          </span>
          <button
            type="button"
            class="text-gray-400 hover:text-gray-600 text-lg leading-none"
            aria-label="Cerrar"
            @click="emit('update:show', false)"
          >
            ✕
          </button>
        </div>
      </div>
      <div class="overflow-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="text-xs text-gray-500 border-b border-gray-100">
              <th class="text-left font-medium px-5 py-2">Campo</th>
              <th class="text-left font-medium px-3 py-2">Confirmado</th>
              <th class="text-left font-medium px-3 py-2">OCR (INE)</th>
              <th class="text-left font-medium px-3 py-2">RENAPO</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="row in comparison.rows"
              :key="row.label"
              class="border-b border-gray-50 last:border-0"
              :class="row.diff ? 'bg-amber-50/50' : ''"
            >
              <td class="px-5 py-2 text-gray-500">{{ row.label }}</td>
              <td class="px-3 py-2 font-medium" :class="row.diff ? 'text-amber-800' : 'text-gray-900'">{{ row.confirmed || '—' }}</td>
              <td class="px-3 py-2 text-gray-700">{{ row.ocr || '—' }}</td>
              <td class="px-3 py-2 text-gray-700">{{ row.renapo || '—' }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</Teleport>
</template>
