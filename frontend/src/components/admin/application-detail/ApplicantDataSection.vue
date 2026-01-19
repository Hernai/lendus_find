<script setup lang="ts">
import { computed } from 'vue'
import VerifiableField from './VerifiableField.vue'
import { formatPhone, formatDate } from '@/utils/formatters'

interface FieldVerification {
  verified: boolean
  method: string
  method_label?: string
  verified_at?: string
  verified_by?: string
  notes?: string
  rejection_reason?: string
  status?: string
  is_locked?: boolean
}

interface Applicant {
  id: string
  full_name: string
  first_name: string
  last_name_1: string
  last_name_2: string
  email: string
  phone: string
  curp: string
  rfc: string
  birth_date: string
  nationality: string
  gender: string
}

interface Address {
  street: string
  ext_number: string
  int_number?: string
  neighborhood: string
  postal_code: string
  municipality: string
  state: string
  housing_type: string
  housing_type_label?: string
  years_at_address?: number
  months_at_address?: number
}

interface Employment {
  employment_type?: string
  company_name?: string
  position?: string
  monthly_income: number
  seniority_months?: number
}

const props = defineProps<{
  applicant: Applicant
  address: Address
  employment: Employment
  fieldVerifications?: Record<string, FieldVerification>
  canVerify?: boolean
  isVerifying?: boolean
}>()

const emit = defineEmits<{
  (e: 'verify', field: string, action: 'verify' | 'unverify'): void
  (e: 'reject', field: string): void
  (e: 'unreject', field: string): void
}>()

// Helper to convert field verification to component format
const getVerification = (field: string) => {
  const v = props.fieldVerifications?.[field]
  if (!v) return null

  let status: 'pending' | 'verified' | 'rejected' = 'pending'
  if (v.status === 'REJECTED' || v.rejection_reason) {
    status = 'rejected'
  } else if (v.verified || v.status === 'VERIFIED') {
    status = 'verified'
  }

  return {
    status,
    method: v.method,
    method_label: v.method_label,
    rejection_reason: v.rejection_reason
  }
}

const isLocked = (field: string) => props.fieldVerifications?.[field]?.is_locked ?? false

// Personal data fields
const personalFields = computed(() => [
  { key: 'first_name', label: 'Nombre', value: props.applicant.full_name },
  { key: 'email', label: 'Email', value: props.applicant.email },
  { key: 'phone', label: 'Teléfono', value: formatPhone(props.applicant.phone) },
  { key: 'curp', label: 'CURP', value: props.applicant.curp },
  { key: 'rfc', label: 'RFC', value: props.applicant.rfc },
  { key: 'birth_date', label: 'Nacimiento', value: formatDate(props.applicant.birth_date) },
])

// Address display
const fullAddress = computed(() => {
  const a = props.address
  if (!a.street) return '—'
  const parts = [a.street, a.ext_number]
  if (a.int_number) parts.push(`Int. ${a.int_number}`)
  return parts.join(' ')
})

const addressLocation = computed(() => {
  const a = props.address
  return `${a.neighborhood}, ${a.municipality}, ${a.state} CP ${a.postal_code}`
})

// Employment display
const employmentTypeLabel = computed(() => {
  const types: Record<string, string> = {
    EMPLOYEE: 'Empleado',
    SELF_EMPLOYED: 'Independiente',
    BUSINESS_OWNER: 'Empresario',
    RETIRED: 'Jubilado',
    OTHER: 'Otro'
  }
  return types[props.employment.employment_type || ''] || props.employment.employment_type || '—'
})

const seniorityDisplay = computed(() => {
  const months = props.employment.seniority_months
  if (!months) return '—'
  const years = Math.floor(months / 12)
  const remainingMonths = months % 12
  if (years > 0 && remainingMonths > 0) {
    return `${years} años, ${remainingMonths} meses`
  } else if (years > 0) {
    return `${years} años`
  }
  return `${remainingMonths} meses`
})
</script>

<template>
  <div class="space-y-4">
    <!-- Personal Data -->
    <div class="border border-gray-200 rounded-lg">
      <div class="bg-gray-50 px-3 py-2 border-b border-gray-200 flex items-center justify-between">
        <h3 class="text-sm font-semibold text-gray-900">Datos del Solicitante</h3>
        <div class="flex items-center gap-3 text-xs text-gray-500">
          <span class="flex items-center gap-1">
            <span class="w-2 h-2 rounded-full bg-gray-300"></span>
            Vacío
          </span>
          <span class="flex items-center gap-1">
            <span class="w-2 h-2 rounded-full bg-blue-500"></span>
            Completado
          </span>
          <span class="flex items-center gap-1">
            <span class="w-2 h-2 rounded-full bg-green-500"></span>
            Verificado
          </span>
          <span class="flex items-center gap-1">
            <span class="w-2 h-2 rounded-full bg-yellow-500"></span>
            Pendiente
          </span>
          <span class="flex items-center gap-1">
            <span class="w-2 h-2 rounded-full bg-red-500"></span>
            Rechazado
          </span>
        </div>
      </div>
      <div class="p-3">
        <div class="grid grid-cols-3 gap-x-4 gap-y-3 text-sm">
          <VerifiableField
            v-for="field in personalFields"
            :key="field.key"
            :label="field.label"
            :value="field.value"
            :field-key="field.key"
            :verification="getVerification(field.key)"
            :is-locked="isLocked(field.key)"
            :is-verifying="isVerifying"
            :can-verify="canVerify"
            @verify="(action) => emit('verify', field.key, action)"
            @reject="emit('reject', field.key)"
            @unreject="emit('unreject', field.key)"
          />
        </div>
      </div>
    </div>

    <!-- Address -->
    <div class="border border-gray-200 rounded-lg">
      <div class="bg-gray-50 px-3 py-2 border-b border-gray-200 flex items-center justify-between">
        <h3 class="text-sm font-semibold text-gray-900">Domicilio</h3>
      </div>
      <div class="p-3">
        <div class="grid grid-cols-2 gap-4 text-sm">
          <VerifiableField
            label="Dirección"
            :value="fullAddress"
            field-key="address"
            :verification="getVerification('address')"
            :is-locked="isLocked('address')"
            :is-verifying="isVerifying"
            :can-verify="canVerify"
            @verify="(action) => emit('verify', 'address', action)"
            @reject="emit('reject', 'address')"
            @unreject="emit('unreject', 'address')"
          />
          <div>
            <span class="text-xs text-gray-500">Ubicación</span>
            <p class="font-medium text-gray-900">{{ addressLocation }}</p>
          </div>
          <div>
            <span class="text-xs text-gray-500">Tipo de vivienda</span>
            <p class="font-medium text-gray-900">{{ address.housing_type_label || address.housing_type || '—' }}</p>
          </div>
          <div>
            <span class="text-xs text-gray-500">Tiempo en domicilio</span>
            <p class="font-medium text-gray-900">
              {{ address.years_at_address || 0 }} años, {{ address.months_at_address || 0 }} meses
            </p>
          </div>
        </div>
      </div>
    </div>

    <!-- Employment -->
    <div class="border border-gray-200 rounded-lg">
      <div class="bg-gray-50 px-3 py-2 border-b border-gray-200 flex items-center justify-between">
        <h3 class="text-sm font-semibold text-gray-900">Información Laboral</h3>
      </div>
      <div class="p-3">
        <div class="grid grid-cols-2 gap-4 text-sm">
          <VerifiableField
            label="Tipo de empleo"
            :value="employmentTypeLabel"
            field-key="employment"
            :verification="getVerification('employment')"
            :is-locked="isLocked('employment')"
            :is-verifying="isVerifying"
            :can-verify="canVerify"
            @verify="(action) => emit('verify', 'employment', action)"
            @reject="emit('reject', 'employment')"
            @unreject="emit('unreject', 'employment')"
          />
          <div>
            <span class="text-xs text-gray-500">Empresa</span>
            <p class="font-medium text-gray-900">{{ employment.company_name || '—' }}</p>
          </div>
          <div>
            <span class="text-xs text-gray-500">Puesto</span>
            <p class="font-medium text-gray-900">{{ employment.position || '—' }}</p>
          </div>
          <div>
            <span class="text-xs text-gray-500">Antigüedad</span>
            <p class="font-medium text-gray-900">{{ seniorityDisplay }}</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
