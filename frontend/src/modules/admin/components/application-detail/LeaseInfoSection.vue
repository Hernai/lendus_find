<script setup lang="ts">
import { computed, ref } from 'vue'
import { useTenantStore } from '@/stores/tenant'
import type { LeaseInfo } from '@/modules/admin/views/panel/applicationDetail.types'

/**
 * Tarjeta read-only con los datos de ARRENDAMIENTO capturados en el onboarding
 * (tipo de activo, modalidad, y datos de empresa si es persona moral). Solo se
 * renderiza cuando la solicitud trae `lease_info` con contenido.
 */
const props = defineProps<{ leaseInfo?: LeaseInfo | null }>()

const tenantStore = useTenantStore()

const sim = computed(() => props.leaseInfo?.lease?.simulation ?? null)
const hasSimulation = computed(() => sim.value?.monthly_rental_with_iva != null)

const hasContent = computed(
  () => !!props.leaseInfo?.lease?.asset_type || !!props.leaseInfo?.company || hasSimulation.value,
)

function money(v?: number | null): string {
  if (v == null) return '—'
  return new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN', maximumFractionDigits: 2 }).format(v)
}

// Calendario de rentas: N rentas iguales (renta con IVA) + opción de compra al
// final (financiero). Se genera del snapshot; el analista lo expande para ver los
// montos mensuales. Colapsado por defecto para no saturar el panel.
const showSchedule = ref(false)
const scheduleRows = computed(() => {
  const s = sim.value
  const n = props.leaseInfo?.lease?.term_months ?? 0
  const renta = s?.monthly_rental_with_iva
  if (!renta || !n) return [] as { key: string; label: string; amount: number }[]
  const rows = Array.from({ length: n }, (_, i) => ({ key: `r${i + 1}`, label: `Renta ${i + 1}`, amount: renta }))
  if (s?.purchase_option && s?.purchase_option_amount) {
    rows.push({ key: 'opt', label: 'Opción de compra (fin del plazo)', amount: s.purchase_option_amount })
  }
  return rows
})
const scheduleTotal = computed(() => scheduleRows.value.reduce((a, r) => a + r.amount, 0))

function labelOf(list: { value: string; label: string }[] | undefined, value?: string | null): string {
  if (!value) return '—'
  return (list ?? []).find((o) => o.value === value)?.label ?? value
}

const assetLabel = computed(() => labelOf(tenantStore.options.assetType, props.leaseInfo?.lease?.asset_type))
const modalityLabel = computed(() => labelOf(tenantStore.options.leaseModality, props.leaseInfo?.lease?.modality))
const isCompany = computed(() => String(props.leaseInfo?.applicant_kind ?? '').toUpperCase() === 'COMPANY')
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
      <div v-if="leaseInfo?.lease?.asset_brand">
        <p class="text-xs text-gray-500">Marca</p>
        <p class="text-sm font-medium text-gray-900">{{ leaseInfo.lease.asset_brand }}</p>
      </div>
      <div v-if="leaseInfo?.lease?.asset_model">
        <p class="text-xs text-gray-500">Modelo</p>
        <p class="text-sm font-medium text-gray-900">{{ leaseInfo.lease.asset_model }}</p>
      </div>
      <div v-if="leaseInfo?.lease?.asset_year">
        <p class="text-xs text-gray-500">Año</p>
        <p class="text-sm font-medium text-gray-900">{{ leaseInfo.lease.asset_year }}</p>
      </div>
      <div v-if="leaseInfo?.lease?.asset_capacity">
        <p class="text-xs text-gray-500">Capacidad</p>
        <p class="text-sm font-medium text-gray-900">{{ leaseInfo.lease.asset_capacity }}</p>
      </div>
      <div v-if="leaseInfo?.lease?.asset_description">
        <p class="text-xs text-gray-500">Descripción</p>
        <p class="text-sm font-medium text-gray-900">{{ leaseInfo.lease.asset_description }}</p>
      </div>
      <!-- "Valor estimado" se muestra arriba (Summary Cards: "Valor del bien"); aquí
           se omite para no repetir el dato. -->
    </div>

    <!-- Condiciones financieras: snapshot de la simulación (renta, desembolso
         inicial desglosado, valor residual / opción de compra). -->
    <div v-if="hasSimulation" class="px-5 pb-4">
      <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Condiciones</p>
      <div class="grid grid-cols-2 gap-x-6 gap-y-4">
        <div>
          <p class="text-xs text-gray-500">Plazo</p>
          <p class="text-sm font-medium text-gray-900">{{ leaseInfo?.lease?.term_months ? leaseInfo.lease.term_months + ' meses' : '—' }}</p>
        </div>
        <div>
          <p class="text-xs text-gray-500">Anticipo</p>
          <p class="text-sm font-medium text-gray-900">{{ sim?.down_payment_pct != null ? sim.down_payment_pct + '%' : '—' }}</p>
        </div>
        <div>
          <p class="text-xs text-gray-500">Renta mensual (IVA incl.)</p>
          <p class="text-sm font-semibold text-primary-600">{{ money(sim?.monthly_rental_with_iva) }}</p>
        </div>
        <div>
          <p class="text-xs text-gray-500">Monto financiado</p>
          <p class="text-sm font-medium text-gray-900">{{ money(sim?.financed_amount) }}</p>
        </div>
      </div>

      <!-- Desembolso inicial desglosado -->
      <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mt-4 mb-2">Desembolso inicial (al firmar)</p>
      <div class="rounded-lg border border-gray-100 divide-y divide-gray-100 text-sm">
        <div v-if="sim?.first_installment?.anticipo" class="flex justify-between px-3 py-2">
          <span class="text-gray-600">Anticipo</span><span class="font-medium">{{ money(sim.first_installment.anticipo) }}</span>
        </div>
        <div v-if="sim?.first_installment?.comision" class="flex justify-between px-3 py-2">
          <span class="text-gray-600">Comisión de apertura (+IVA)</span>
          <span class="font-medium">{{ money((sim.first_installment.comision ?? 0) + (sim.first_installment.comision_iva ?? 0)) }}</span>
        </div>
        <div v-if="sim?.first_installment?.deposito_reembolsable" class="flex justify-between px-3 py-2">
          <span class="text-gray-600">Depósito en garantía (reembolsable)</span>
          <span class="font-medium">{{ money(sim.first_installment.deposito_reembolsable) }}</span>
        </div>
        <div v-if="sim?.first_installment?.rentas_anticipadas" class="flex justify-between px-3 py-2">
          <span class="text-gray-600">Renta anticipada (IVA incl.)</span>
          <span class="font-medium">{{ money(sim.first_installment.rentas_anticipadas) }}</span>
        </div>
        <div class="flex justify-between px-3 py-2 bg-gray-50">
          <span class="font-semibold text-gray-700">Total a pagar hoy</span>
          <span class="font-bold text-primary-600">{{ money(sim?.first_installment?.total) }}</span>
        </div>
      </div>

      <!-- Opción de compra / valor residual (arrendamiento financiero) -->
      <div v-if="sim?.purchase_option && sim?.purchase_option_amount" class="mt-3 rounded-lg bg-blue-50 px-3 py-2 text-sm text-blue-800">
        Opción de compra al final del plazo:
        <strong>{{ money(sim.purchase_option_amount) }}</strong>
        (valor residual{{ sim.residual_value_pct != null ? ' ' + sim.residual_value_pct + '%' : '' }}).
      </div>

      <!-- Próximo pago -->
      <p v-if="sim?.next_payment_amount" class="mt-2 text-xs text-gray-500">
        Próximo pago: {{ money(sim.next_payment_amount) }} en ~{{ sim.next_payment_offset_days ?? 30 }} días.
      </p>

      <!-- Calendario de rentas (colapsable) -->
      <template v-if="scheduleRows.length">
        <button
          type="button"
          class="mt-4 flex items-center gap-1 text-xs font-semibold text-primary-600 uppercase tracking-wide"
          @click="showSchedule = !showSchedule"
        >
          Calendario de rentas ({{ leaseInfo?.lease?.term_months }} rentas)
          <svg class="w-3.5 h-3.5 transition-transform" :class="{ 'rotate-180': showSchedule }" viewBox="0 0 24 24" fill="none">
            <path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
          </svg>
        </button>
        <div v-if="showSchedule" class="mt-2 rounded-lg border border-gray-100 divide-y divide-gray-100 text-sm max-h-64 overflow-y-auto">
          <div v-for="row in scheduleRows" :key="row.key" class="flex justify-between px-3 py-1.5">
            <span class="text-gray-600">{{ row.label }}</span>
            <span class="font-medium">{{ money(row.amount) }}</span>
          </div>
          <div class="flex justify-between px-3 py-2 bg-gray-50 sticky bottom-0">
            <span class="font-semibold text-gray-700">Total de rentas + opción</span>
            <span class="font-bold text-primary-600">{{ money(scheduleTotal) }}</span>
          </div>
        </div>
      </template>
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
        <div v-if="leaseInfo.company.legal_rep_name">
          <p class="text-xs text-gray-500">Representante legal</p>
          <p class="text-sm font-medium text-gray-900">{{ leaseInfo.company.legal_rep_name }}</p>
        </div>
        <div v-if="leaseInfo.company.legal_rep_rfc">
          <p class="text-xs text-gray-500">RFC del representante</p>
          <p class="text-sm font-medium text-gray-900 uppercase">{{ leaseInfo.company.legal_rep_rfc }}</p>
        </div>
      </div>
    </div>
  </div>
</template>
