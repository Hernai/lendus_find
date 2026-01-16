<script setup lang="ts">
import { AppButton } from '@/components/common'

interface Reference {
  id: string
  full_name: string
  relationship: string
  phone: string
  verified: boolean
  verification_result?: 'VERIFIED' | 'NOT_VERIFIED' | 'NO_ANSWER'
  verification_notes?: string
  verified_at?: string
}

defineProps<{
  references: Reference[]
  canVerify: boolean
}>()

const emit = defineEmits<{
  (e: 'verify', reference: Reference): void
}>()

const formatPhone = (phone: string | null | undefined): string => {
  if (!phone) return '-'
  const cleaned = phone.replace(/\D/g, '')
  if (cleaned.length === 10) {
    return `(${cleaned.slice(0, 3)}) ${cleaned.slice(3, 6)}-${cleaned.slice(6)}`
  }
  return phone
}

const getVerificationBadge = (ref: Reference) => {
  if (!ref.verified) {
    return { bg: 'bg-gray-100', text: 'text-gray-600', label: 'Pendiente' }
  }
  const badges: Record<string, { bg: string; text: string; label: string }> = {
    VERIFIED: { bg: 'bg-green-100', text: 'text-green-700', label: 'Verificada' },
    NOT_VERIFIED: { bg: 'bg-red-100', text: 'text-red-700', label: 'No Verificada' },
    NO_ANSWER: { bg: 'bg-yellow-100', text: 'text-yellow-700', label: 'Sin Respuesta' },
  }
  return badges[ref.verification_result || 'VERIFIED'] || badges.VERIFIED
}

const formatDateTime = (dateStr: string) => {
  return new Date(dateStr).toLocaleString('es-MX', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}
</script>

<template>
  <div class="border border-gray-200 rounded-lg">
    <div class="bg-gray-50 px-3 py-2 border-b border-gray-200">
      <h3 class="text-sm font-semibold text-gray-900">Referencias</h3>
    </div>
    <div class="divide-y divide-gray-100">
      <div v-if="references.length === 0" class="p-4 text-center text-gray-500 text-sm">
        No hay referencias registradas
      </div>
      <div
        v-for="ref in references"
        :key="ref.id"
        class="p-3 flex items-center justify-between"
      >
        <div class="flex-1">
          <div class="flex items-center gap-2 mb-1">
            <span class="font-medium text-gray-900">{{ ref.full_name }}</span>
            <span
              :class="[
                'px-2 py-0.5 rounded-full text-xs font-medium',
                getVerificationBadge(ref).bg,
                getVerificationBadge(ref).text,
              ]"
            >
              {{ getVerificationBadge(ref).label }}
            </span>
          </div>
          <div class="text-sm text-gray-500 flex items-center gap-4">
            <span>{{ ref.relationship }}</span>
            <span>{{ formatPhone(ref.phone) }}</span>
          </div>
          <div v-if="ref.verified && ref.verified_at" class="text-xs text-gray-400 mt-1">
            Verificada el {{ formatDateTime(ref.verified_at) }}
            <span v-if="ref.verification_notes" class="ml-2">
              · {{ ref.verification_notes }}
            </span>
          </div>
        </div>
        <AppButton
          v-if="canVerify && !ref.verified"
          variant="outline"
          size="sm"
          @click="emit('verify', ref)"
        >
          Verificar
        </AppButton>
      </div>
    </div>
  </div>
</template>
