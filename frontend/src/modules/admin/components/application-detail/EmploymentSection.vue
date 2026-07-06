<script setup lang="ts">
/**
 * Card "Información Laboral" con el patrón de verificación de campo (verificar /
 * rechazar / quitar). Extraída de AdminApplicationDetail.vue.
 *
 * Mismo contrato que AddressSection: el estado de verificación del campo
 * 'employment' se evalúa en el padre y llega como props; las acciones se emiten y
 * el padre las mapea 1:1 a sus handlers. El componente NO muta estado.
 */
import { formatMoney } from '@/utils/formatters'
import { useTenantStore } from '@/stores/tenant'
import type { Application } from '@/modules/admin/views/panel/applicationDetail.types'

defineProps<{
  employment: Application['employment']
  onlineLoansCount: number | null
  isVerified: boolean
  isRejected: boolean
  isPending: boolean
  isVerifying: boolean
  rejectionReason?: string | null
}>()

const emit = defineEmits<{
  (e: 'verify', action: 'verify' | 'unverify'): void
  (e: 'reject'): void
  (e: 'unreject'): void
}>()

const tenantStore = useTenantStore()

// Etiqueta del tipo de empleo desde las opciones del tenant (store singleton,
// mismo comportamiento que en el padre). Movido aquí por ser su único consumidor.
const getEmploymentType = (type: string) => {
  const option = tenantStore.options.employmentType.find(o => o.value === type)
  return option?.label || type
}

// Antigüedad laboral: convierte meses totales a "X años, Y meses". Función pura
// movida desde el padre.
const formatTenureFromMonths = (totalMonths?: number) => {
  if (totalMonths === undefined || totalMonths === null) return '—'

  const years = Math.floor(totalMonths / 12)
  const months = totalMonths % 12

  const parts = []
  if (years > 0) {
    parts.push(`${years} ${years === 1 ? 'año' : 'años'}`)
  }
  if (months > 0) {
    parts.push(`${months} ${months === 1 ? 'mes' : 'meses'}`)
  }

  return parts.length > 0 ? parts.join(', ') : 'Menos de 1 mes'
}
</script>

<template>
  <div class="border border-gray-200 rounded-lg">
    <div class="bg-gray-50 px-3 py-1.5 border-b border-gray-200 flex items-center justify-between">
      <div class="flex items-center gap-2">
        <span
          class="w-2 h-2 rounded-full flex-shrink-0"
          :class="isRejected ? 'bg-red-500' : isVerified ? 'bg-green-500' : isPending ? 'bg-yellow-500' : employment.employment_type ? 'bg-blue-500' : 'bg-gray-300'"
        ></span>
        <h3 class="text-sm font-semibold text-gray-900">Información Laboral</h3>
      </div>
      <div class="flex items-center gap-0.5">
        <button
          v-if="!isVerified && !isRejected"
          class="flex items-center gap-1 text-xs px-2 py-0.5 rounded transition-colors text-gray-500 hover:bg-green-100 hover:text-green-700"
          :disabled="isVerifying"
          title="Verificar empleo"
          @click="emit('verify', 'verify')"
        >
          <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <span>Verificar</span>
        </button>
        <button
          v-if="isVerified"
          class="flex items-center gap-1 text-xs px-2 py-0.5 rounded transition-colors bg-green-100 text-green-700 hover:bg-gray-100"
          :disabled="isVerifying"
          title="Quitar verificación"
          @click="emit('verify', 'unverify')"
        >
          <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
          </svg>
          <span>Verificado</span>
        </button>
        <button
          v-if="!isVerified && !isRejected"
          class="flex items-center gap-1 text-xs px-2 py-0.5 rounded transition-colors text-gray-500 hover:bg-red-100 hover:text-red-700"
          :disabled="isVerifying"
          title="Rechazar empleo"
          @click="emit('reject')"
        >
          <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <span>Rechazar</span>
        </button>
        <button
          v-if="isRejected"
          class="flex items-center gap-1 text-xs px-2 py-0.5 rounded transition-colors bg-red-100 text-red-700 hover:bg-gray-100"
          :disabled="isVerifying"
          title="Quitar rechazo"
          @click="emit('unreject')"
        >
          <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
          </svg>
          <span>Rechazado</span>
        </button>
      </div>
    </div>
    <div class="p-3">
      <div v-if="isRejected" class="mb-3 p-2 bg-red-50 border border-red-200 rounded text-xs text-red-700">
        <span class="font-semibold">⚠ Dato rechazado:</span> {{ rejectionReason }}
      </div>
      <div class="grid grid-cols-2 gap-2 text-sm">
        <div>
          <p class="text-xs text-gray-500">Tipo</p>
          <p class="font-medium text-gray-900">{{ getEmploymentType(employment.employment_type || '') || '—' }}</p>
        </div>
        <div>
          <p class="text-xs text-gray-500">Empresa</p>
          <p class="font-medium text-gray-900">{{ employment.company_name || '—' }}</p>
        </div>
        <div>
          <p class="text-xs text-gray-500">Puesto</p>
          <p class="font-medium text-gray-900">{{ employment.position || '—' }}</p>
        </div>
        <div>
          <p class="text-xs text-gray-500">Antigüedad</p>
          <p class="font-medium text-gray-900">{{ formatTenureFromMonths(employment.seniority_months) }}</p>
        </div>
        <div class="col-span-2">
          <p class="text-xs text-gray-500">Ingreso mensual</p>
          <p class="font-bold text-gray-900 text-lg">
            <template v-if="employment.monthly_income">
              {{ formatMoney(employment.monthly_income) }}
              <span v-if="employment.income_range_label" class="text-xs font-normal text-gray-500">
                ({{ employment.income_range_label }})
              </span>
            </template>
            <template v-else>{{ employment.income_range_label || '—' }}</template>
          </p>
        </div>
        <div class="col-span-2">
          <p class="text-xs text-gray-500">Préstamos en línea solicitados (onboarding)</p>
          <p class="font-medium text-gray-900">
            {{ onlineLoansCount != null
              ? (onlineLoansCount === 10 ? '10 o más' : onlineLoansCount)
              : '—' }}
          </p>
        </div>
      </div>
    </div>
  </div>
</template>
