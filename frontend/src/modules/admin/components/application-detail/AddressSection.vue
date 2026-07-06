<script setup lang="ts">
/**
 * Card "Domicilio" con el patrón de verificación de campo (verificar / rechazar /
 * quitar). Extraída de AdminApplicationDetail.vue.
 *
 * El estado de verificación se evalúa en el padre (isFieldVerified/Rejected/Pending
 * sobre el campo 'address') y llega como props booleanas; las acciones se emiten y
 * el padre las mapea 1:1 a verifyData/openRejectDataModal/openUnverifyModal,
 * preservando el flujo optimista + fetchApplication(). El componente NO muta estado.
 */
import { useTenantStore } from '@/stores/tenant'
import type { Application } from '@/modules/admin/views/panel/applicationDetail.types'

defineProps<{
  address: Application['address']
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

// Etiqueta del tipo de vivienda desde las opciones del tenant (store singleton,
// mismo comportamiento que en el padre). Movido aquí por ser su único consumidor.
const getHousingType = (type: string) => {
  const option = tenantStore.options.housingType.find(o => o.value === type)
  return option?.label || type
}

// Antigüedad en domicilio ("X años, Y meses"). Función pura movida desde el padre.
const formatAddressTenure = (years?: number, months?: number) => {
  if (years === undefined && months === undefined) return '—'
  if (years === null && months === null) return '—'

  const parts = []
  if (years && years > 0) {
    parts.push(`${years} ${years === 1 ? 'año' : 'años'}`)
  }
  if (months && months > 0) {
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
          :class="isRejected ? 'bg-red-500' : isVerified ? 'bg-green-500' : isPending ? 'bg-yellow-500' : address.street ? 'bg-blue-500' : 'bg-gray-300'"
        ></span>
        <h3 class="text-sm font-semibold text-gray-900">Domicilio</h3>
      </div>
      <div class="flex items-center gap-0.5">
        <button
          v-if="!isVerified && !isRejected"
          class="flex items-center gap-1 text-xs px-2 py-0.5 rounded transition-colors text-gray-500 hover:bg-green-100 hover:text-green-700"
          :disabled="isVerifying"
          title="Verificar domicilio"
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
          title="Rechazar domicilio"
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
        <div class="col-span-2">
          <p class="text-xs text-gray-500">Dirección</p>
          <p class="font-medium text-gray-900">
            {{ address.street || '—' }} {{ address.ext_number }}
            <span v-if="address.int_number">, Int. {{ address.int_number }}</span>
          </p>
        </div>
        <div>
          <p class="text-xs text-gray-500">Colonia</p>
          <p class="font-medium text-gray-900">{{ address.neighborhood || '—' }}</p>
        </div>
        <div>
          <p class="text-xs text-gray-500">C.P.</p>
          <p class="font-medium text-gray-900">{{ address.postal_code || '—' }}</p>
        </div>
        <div v-if="address.city && address.city !== address.municipality">
          <p class="text-xs text-gray-500">Ciudad</p>
          <p class="font-medium text-gray-900">{{ address.city }}</p>
        </div>
        <div>
          <p class="text-xs text-gray-500">Municipio/Estado</p>
          <p class="font-medium text-gray-900">{{ address.municipality || '—' }}, {{ address.state || '—' }}</p>
        </div>
        <div>
          <p class="text-xs text-gray-500">Vivienda</p>
          <p class="font-medium text-gray-900">{{ address.housing_type_label || getHousingType(address.housing_type) || '—' }}</p>
        </div>
        <div>
          <p class="text-xs text-gray-500">Antigüedad en domicilio</p>
          <p class="font-medium text-gray-900">{{ formatAddressTenure(address.years_at_address, address.months_at_address) }}</p>
        </div>
      </div>
    </div>
  </div>
</template>
