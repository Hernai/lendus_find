<script setup lang="ts">
import { computed } from 'vue'

interface CompletenessData {
  personal_data: boolean
  address: boolean
  employment: boolean
  documents: { uploaded: number; required: number; approved: number }
  references: { count: number; verified: number }
  signature: boolean
}

const props = defineProps<{
  completeness: CompletenessData
}>()

const completenessItems = computed(() => [
  {
    label: 'Datos Personales',
    complete: props.completeness.personal_data,
    partial: false,
  },
  {
    label: 'Domicilio',
    complete: props.completeness.address,
    partial: false,
  },
  {
    label: 'Empleo',
    complete: props.completeness.employment,
    partial: false,
  },
  {
    label: `Documentos (${props.completeness.documents.approved}/${props.completeness.documents.required})`,
    complete: props.completeness.documents.approved === props.completeness.documents.required,
    partial: props.completeness.documents.uploaded > 0 && props.completeness.documents.approved < props.completeness.documents.required,
  },
  {
    label: `Referencias (${props.completeness.references.verified}/${props.completeness.references.count})`,
    complete: props.completeness.references.count > 0 && props.completeness.references.verified === props.completeness.references.count,
    partial: props.completeness.references.verified > 0 && props.completeness.references.verified < props.completeness.references.count,
  },
  {
    label: 'Firma',
    complete: props.completeness.signature,
    partial: false,
  },
])

const completenessPercent = computed(() => {
  const items = completenessItems.value
  const completed = items.filter((i) => i.complete).length
  const partial = items.filter((i) => i.partial).length
  return Math.round(((completed + partial * 0.5) / items.length) * 100)
})

const completenessColor = computed(() => {
  const percent = completenessPercent.value
  if (percent >= 80) return 'bg-green-500'
  if (percent >= 50) return 'bg-yellow-500'
  return 'bg-red-500'
})
</script>

<template>
  <div class="bg-white rounded-xl shadow-sm p-3 mb-4">
    <div class="flex items-center justify-between mb-2">
      <span class="text-xs font-medium text-gray-500">Completitud</span>
      <span class="text-xs font-bold" :class="completenessColor.replace('bg-', 'text-')">
        {{ completenessPercent }}%
      </span>
    </div>
    <div class="h-1.5 bg-gray-200 rounded-full overflow-hidden mb-3">
      <div
        class="h-full rounded-full transition-all duration-300"
        :class="completenessColor"
        :style="{ width: `${completenessPercent}%` }"
      ></div>
    </div>
    <div class="grid grid-cols-3 gap-2">
      <div
        v-for="item in completenessItems"
        :key="item.label"
        class="flex items-center gap-1.5 text-xs"
      >
        <span
          class="w-2 h-2 rounded-full flex-shrink-0"
          :class="item.complete ? 'bg-green-500' : item.partial ? 'bg-yellow-500' : 'bg-gray-300'"
        ></span>
        <span
          :class="[
            'font-medium truncate',
            item.complete ? 'text-green-700' : item.partial ? 'text-yellow-700' : 'text-gray-500',
          ]"
        >
          {{ item.label }}
        </span>
      </div>
    </div>
  </div>
</template>
