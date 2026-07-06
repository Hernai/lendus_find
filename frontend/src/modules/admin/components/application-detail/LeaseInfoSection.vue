<script setup lang="ts">
import { computed } from 'vue'
import { useTenantStore } from '@/stores/tenant'
import type { LeaseInfo } from '@/modules/admin/views/panel/applicationDetail.types'

/**
 * Tarjeta read-only con los datos de ARRENDAMIENTO capturados en el onboarding
 * (tipo de activo, modalidad, y datos de empresa si es persona moral). Solo se
 * renderiza cuando la solicitud trae `lease_info` con contenido.
 */
const props = defineProps<{ leaseInfo?: LeaseInfo | null }>()

const tenantStore = useTenantStore()

const hasContent = computed(
  () => !!props.leaseInfo?.lease?.asset_type || !!props.leaseInfo?.company,
)

function labelOf(list: { value: string; label: string }[] | undefined, value?: string | null): string {
  if (!value) return '—'
  return (list ?? []).find((o) => o.value === value)?.label ?? value
}

const assetLabel = computed(() => labelOf(tenantStore.options.assetType, props.leaseInfo?.lease?.asset_type))
const modalityLabel = computed(() => labelOf(tenantStore.options.leaseModality, props.leaseInfo?.lease?.modality))
const isCompany = computed(() => String(props.leaseInfo?.applicant_kind ?? '').toUpperCase() === 'COMPANY')

const estimatedValue = computed(() => {
  const v = props.leaseInfo?.lease?.asset_estimated_value
  if (v == null) return '—'
  return new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN', maximumFractionDigits: 0 }).format(v)
})
</script>

<template>
  <div v-if="hasContent" class="rounded-xl border border-gray-200 bg-white">
    <div class="flex items-center gap-2 px-5 py-3 border-b border-gray-100">
      <span class="h-2 w-2 rounded-full bg-primary-400" />
      <h3 class="text-sm font-semibold text-gray-900">Arrendamiento</h3>
      <span class="ml-auto text-xs font-medium text-gray-500">
        {{ isCompany ? 'Persona Moral' : 'Persona Física' }}
      </span>
    </div>

    <div class="px-5 py-4 grid grid-cols-2 gap-x-6 gap-y-4">
      <div>
        <p class="text-xs text-gray-500">Bien a arrendar</p>
        <p class="text-sm font-medium text-gray-900">{{ assetLabel }}</p>
      </div>
      <div>
        <p class="text-xs text-gray-500">Modalidad</p>
        <p class="text-sm font-medium text-gray-900">{{ modalityLabel }}</p>
      </div>
      <div v-if="leaseInfo?.lease?.asset_description">
        <p class="text-xs text-gray-500">Descripción</p>
        <p class="text-sm font-medium text-gray-900">{{ leaseInfo.lease.asset_description }}</p>
      </div>
      <div>
        <p class="text-xs text-gray-500">Valor estimado</p>
        <p class="text-sm font-medium text-gray-900">{{ estimatedValue }}</p>
      </div>
    </div>

    <!-- Datos de empresa (persona moral) -->
    <div v-if="leaseInfo?.company" class="px-5 pb-4">
      <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Empresa</p>
      <div class="grid grid-cols-2 gap-x-6 gap-y-4">
        <div>
          <p class="text-xs text-gray-500">Razón social</p>
          <p class="text-sm font-medium text-gray-900">{{ leaseInfo.company.legal_name || '—' }}</p>
        </div>
        <div>
          <p class="text-xs text-gray-500">RFC</p>
          <p class="text-sm font-medium text-gray-900 uppercase">{{ leaseInfo.company.rfc || '—' }}</p>
        </div>
        <div>
          <p class="text-xs text-gray-500">Tipo de sociedad</p>
          <p class="text-sm font-medium text-gray-900">{{ leaseInfo.company.legal_entity_type || '—' }}</p>
        </div>
        <div>
          <p class="text-xs text-gray-500">Fecha de constitución</p>
          <p class="text-sm font-medium text-gray-900">{{ leaseInfo.company.incorporation_date || '—' }}</p>
        </div>
      </div>
    </div>
  </div>
</template>
