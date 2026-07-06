<script setup lang="ts">
/**
 * Card "Avance del Expediente": barra de progreso + checklist de completitud.
 *
 * Extraído de AdminApplicationDetail.vue. 100% presentación: recibe el porcentaje,
 * el color y los ítems YA calculados por los computeds del padre (que dependen de
 * application.value.completeness y requiresSignature). No calcula ni muta nada.
 */
defineProps<{
  percent: number
  color: { bg: string; text: string; light?: string }
  items: Array<{ label: string; complete: boolean; partial?: boolean }>
}>()
</script>

<template>
  <div class="bg-white rounded-xl shadow-sm p-4 mb-5">
    <div class="flex items-center justify-between mb-3">
      <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Avance del Expediente</h3>
      <div class="flex items-center gap-2">
        <span :class="['text-lg font-bold', color.text]">{{ percent }}%</span>
        <span
          v-if="percent >= 100"
          class="px-1.5 py-0.5 text-xs font-medium bg-green-100 text-green-700 rounded"
        >
          Completo
        </span>
      </div>
    </div>

    <!-- Progress Bar (thinner for cleaner look) -->
    <div class="w-full h-1.5 bg-gray-200 rounded-full overflow-hidden mb-3">
      <div
        :class="['h-full rounded-full transition-all duration-500', color.bg]"
        :style="{ width: percent + '%' }"
      />
    </div>

    <!-- Checklist - Compact design -->
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-2">
      <div
        v-for="item in items"
        :key="item.label"
        :class="[
          'flex items-center gap-1.5 px-2 py-1.5 rounded text-xs',
          item.complete ? 'bg-green-50 border border-green-200' : item.partial ? 'bg-yellow-50 border border-yellow-200' : 'bg-gray-50 border border-gray-200'
        ]"
      >
        <div
          :class="[
            'w-4 h-4 rounded-full flex items-center justify-center flex-shrink-0',
            item.complete ? 'bg-green-500' : item.partial ? 'bg-yellow-500' : 'bg-gray-300'
          ]"
        >
          <svg v-if="item.complete" class="w-2.5 h-2.5 text-white" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
          </svg>
          <svg v-else-if="item.partial" class="w-2.5 h-2.5 text-white" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd" />
          </svg>
        </div>
        <span :class="[
          'font-medium truncate',
          item.complete ? 'text-green-700' : item.partial ? 'text-yellow-700' : 'text-gray-500'
        ]">
          {{ item.label }}
        </span>
      </div>
    </div>
  </div>
</template>
