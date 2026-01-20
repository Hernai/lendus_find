<script setup lang="ts">
import { computed } from 'vue'
import { AppButton } from '@/components/common'
import { formatDateShort } from '@/utils/formatters'

interface Props {
  folio: string
  status: string
  createdAt: string
  assignedTo?: string
  canAssign: boolean
  canChangeStatus: boolean
  allowedStatuses: { value: string; label: string }[]
}

const props = defineProps<Props>()

const emit = defineEmits<{
  (e: 'back'): void
  (e: 'openStatusModal'): void
  (e: 'openAssignModal'): void
}>()

const statusBadge = computed(() => {
  const badges: Record<string, { bg: string; text: string; label: string }> = {
    DRAFT: { bg: 'bg-gray-100', text: 'text-gray-600', label: 'Borrador' },
    SUBMITTED: { bg: 'bg-blue-100', text: 'text-blue-700', label: 'Enviada' },
    IN_REVIEW: { bg: 'bg-yellow-100', text: 'text-yellow-700', label: 'En Revisión' },
    DOCS_PENDING: { bg: 'bg-orange-100', text: 'text-orange-700', label: 'Docs Pendientes' },
    CORRECTIONS_PENDING: { bg: 'bg-purple-100', text: 'text-purple-700', label: 'Correcciones' },
    APPROVED: { bg: 'bg-green-100', text: 'text-green-700', label: 'Aprobada' },
    REJECTED: { bg: 'bg-red-100', text: 'text-red-700', label: 'Rechazada' },
    SYNCED: { bg: 'bg-teal-100', text: 'text-teal-700', label: 'Sincronizada' },
    CANCELLED: { bg: 'bg-gray-200', text: 'text-gray-600', label: 'Cancelada' },
  }
  return badges[props.status] || { bg: 'bg-gray-100', text: 'text-gray-600', label: props.status }
})

</script>

<template>
  <div class="bg-white rounded-xl shadow-sm p-4 mb-4">
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-4">
        <button
          @click="emit('back')"
          class="p-2 hover:bg-gray-100 rounded-lg transition-colors"
        >
          <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
          </svg>
        </button>
        <div>
          <div class="flex items-center gap-3">
            <h1 class="text-xl font-bold text-gray-900">{{ folio }}</h1>
            <span :class="['px-2.5 py-0.5 rounded-full text-xs font-medium', statusBadge.bg, statusBadge.text]">
              {{ statusBadge.label }}
            </span>
          </div>
          <p class="text-sm text-gray-500">
            Creada el {{ formatDateShort(createdAt) }}
            <span v-if="assignedTo" class="ml-2">
              · Asignada a <span class="font-medium">{{ assignedTo }}</span>
            </span>
          </p>
        </div>
      </div>

      <div class="flex items-center gap-2">
        <AppButton
          v-if="canAssign"
          variant="outline"
          size="sm"
          @click="emit('openAssignModal')"
        >
          <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
          </svg>
          Asignar
        </AppButton>

        <AppButton
          v-if="canChangeStatus && allowedStatuses.length > 0"
          variant="primary"
          size="sm"
          @click="emit('openStatusModal')"
        >
          <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
          </svg>
          Cambiar Estado
        </AppButton>
      </div>
    </div>
  </div>
</template>
