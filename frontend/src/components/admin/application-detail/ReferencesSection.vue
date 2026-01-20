<script setup lang="ts">
import { computed } from 'vue'
import { formatPhone } from '@/utils/formatters'

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

const props = defineProps<{
  references: Reference[]
  canVerify: boolean
}>()

const emit = defineEmits<{
  (e: 'verify', reference: Reference): void
}>()

const stats = computed(() => ({
  total: props.references.length,
  verified: props.references.filter(r => r.verified).length,
  pending: props.references.filter(r => !r.verified).length,
}))
</script>

<template>
  <div>
    <!-- Reference Stats -->
    <div class="flex items-center gap-4 mb-4 text-sm text-gray-500">
      <span>Total: <b class="text-gray-900">{{ stats.total }}</b></span>
      <span>Verificadas: <b class="text-gray-900">{{ stats.verified }}</b></span>
      <span>Pendientes: <b class="text-gray-900">{{ stats.pending }}</b></span>
    </div>

    <div v-if="references.length === 0" class="text-center py-6 text-gray-500 text-sm">
      No hay referencias
    </div>

    <div v-else class="space-y-2">
      <div
        v-for="ref in references"
        :key="ref.id"
        class="flex items-center justify-between border border-gray-200 rounded px-3 py-2"
      >
        <div class="flex items-center gap-2">
          <div class="w-8 h-8 bg-gray-100 rounded-full flex items-center justify-center flex-shrink-0 text-sm font-medium text-gray-600">
            {{ ref.full_name.charAt(0).toUpperCase() }}
          </div>
          <div>
            <span class="text-sm font-medium text-gray-900">{{ ref.full_name }}</span>
            <span class="text-xs text-gray-500 ml-2">{{ ref.relationship }} · {{ formatPhone(ref.phone) }}</span>
          </div>
        </div>

        <div class="flex items-center gap-2">
          <span class="text-xs text-gray-500">{{ ref.verified ? 'Verificada' : 'Pendiente' }}</span>
          <div class="flex items-center">
            <a
              :href="'tel:' + ref.phone"
              class="p-1.5 text-gray-400 hover:text-gray-600"
              title="Llamar"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
              </svg>
            </a>
            <button
              v-if="!ref.verified && canVerify"
              class="p-1.5 text-gray-400 hover:text-gray-600"
              title="Verificar"
              @click="emit('verify', ref)"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
