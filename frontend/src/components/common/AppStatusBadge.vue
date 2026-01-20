<script setup lang="ts">
import { computed } from 'vue'

type StatusType =
  | 'draft' | 'submitted' | 'in_review' | 'docs_pending' | 'corrections_pending'
  | 'counter_offered' | 'approved' | 'rejected' | 'synced' | 'cancelled'
  | 'disbursed' | 'active' | 'completed' | 'default'
  | 'pending' | 'verified' | 'success' | 'warning' | 'error' | 'info'

interface Props {
  status: string
  /** Size variant */
  size?: 'xs' | 'sm' | 'md'
  /** Use uppercase status value */
  uppercase?: boolean
  /** Custom label override */
  label?: string
}

const props = withDefaults(defineProps<Props>(), {
  size: 'sm',
  uppercase: true
})

const normalizedStatus = computed(() => {
  const s = props.uppercase ? props.status.toUpperCase() : props.status.toLowerCase()
  return s.replace(/_/g, '_').toLowerCase() as StatusType
})

const statusConfig = computed(() => {
  const configs: Record<string, { bg: string; text: string; label: string }> = {
    // Application statuses
    draft: { bg: 'bg-gray-100', text: 'text-gray-600', label: 'Borrador' },
    submitted: { bg: 'bg-blue-100', text: 'text-blue-700', label: 'Enviada' },
    in_review: { bg: 'bg-yellow-100', text: 'text-yellow-700', label: 'En Revisión' },
    docs_pending: { bg: 'bg-orange-100', text: 'text-orange-700', label: 'Docs Pendientes' },
    corrections_pending: { bg: 'bg-purple-100', text: 'text-purple-700', label: 'Correcciones' },
    counter_offered: { bg: 'bg-purple-100', text: 'text-purple-700', label: 'Contraoferta' },
    approved: { bg: 'bg-green-100', text: 'text-green-700', label: 'Aprobada' },
    rejected: { bg: 'bg-red-100', text: 'text-red-700', label: 'Rechazada' },
    synced: { bg: 'bg-teal-100', text: 'text-teal-700', label: 'Sincronizada' },
    cancelled: { bg: 'bg-gray-200', text: 'text-gray-600', label: 'Cancelada' },
    disbursed: { bg: 'bg-purple-100', text: 'text-purple-700', label: 'Desembolsada' },
    active: { bg: 'bg-blue-100', text: 'text-blue-700', label: 'Activa' },
    completed: { bg: 'bg-green-50', text: 'text-green-700', label: 'Completada' },
    default: { bg: 'bg-red-100', text: 'text-red-700', label: 'En Mora' },
    // Generic statuses
    pending: { bg: 'bg-yellow-100', text: 'text-yellow-700', label: 'Pendiente' },
    verified: { bg: 'bg-green-100', text: 'text-green-700', label: 'Verificado' },
    success: { bg: 'bg-green-100', text: 'text-green-700', label: 'Exitoso' },
    warning: { bg: 'bg-yellow-100', text: 'text-yellow-700', label: 'Advertencia' },
    error: { bg: 'bg-red-100', text: 'text-red-700', label: 'Error' },
    info: { bg: 'bg-blue-100', text: 'text-blue-700', label: 'Info' },
  }
  return configs[normalizedStatus.value] || { bg: 'bg-gray-100', text: 'text-gray-600', label: props.status }
})

const displayLabel = computed(() => props.label || statusConfig.value.label)

const sizeClass = computed(() => {
  const sizes = {
    xs: 'px-1.5 py-0.5 text-[10px]',
    sm: 'px-2 py-0.5 text-xs',
    md: 'px-2.5 py-1 text-sm'
  }
  return sizes[props.size]
})
</script>

<template>
  <span
    :class="[
      'inline-flex items-center font-medium rounded-full whitespace-nowrap',
      sizeClass,
      statusConfig.bg,
      statusConfig.text
    ]"
  >
    {{ displayLabel }}
  </span>
</template>
