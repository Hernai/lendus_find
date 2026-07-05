<script setup lang="ts">
import { computed } from 'vue'
import { formatPhone } from '@/utils/formatters'
import { useTenantStore } from '@/stores'

const tenantStore = useTenantStore()

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

// Get relationship label from tenant options
const getRelationshipLabel = (relationship: string): string => {
  const option = tenantStore.options.relationship.find(opt => opt.value === relationship)
  return option?.label || relationship
}

// Check if relationship is family-based
const isFamilyRelationship = (relationship: string): boolean => {
  return tenantStore.options.relationshipFamily.some(opt => opt.value === relationship)
}
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

    <div v-else class="space-y-3">
      <div
        v-for="ref in references"
        :key="ref.id"
        class="bg-white border border-gray-200 rounded-lg px-4 py-3 hover:border-gray-300 transition-colors"
      >
        <div class="flex items-start justify-between gap-4">
          <!-- Left: Avatar + Info -->
          <div class="flex items-start gap-3 flex-1 min-w-0">
            <!-- Avatar with initial -->
            <div
              class="w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0 text-sm font-semibold"
              :class="isFamilyRelationship(ref.relationship)
                ? 'bg-blue-100 text-blue-700'
                : 'bg-purple-100 text-purple-700'"
            >
              {{ ref.full_name.charAt(0).toUpperCase() }}
            </div>

            <!-- Name and details -->
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2 mb-1">
                <span class="font-medium text-gray-900">{{ ref.full_name }}</span>
                <span
                  class="px-2 py-0.5 text-xs font-medium rounded"
                  :class="isFamilyRelationship(ref.relationship)
                    ? 'bg-blue-100 text-blue-800'
                    : 'bg-purple-100 text-purple-800'"
                >
                  {{ getRelationshipLabel(ref.relationship) }}
                </span>
              </div>

              <div class="flex items-center gap-2 text-sm text-gray-600">
                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                </svg>
                <span>{{ formatPhone(ref.phone) }}</span>
              </div>
            </div>
          </div>

          <!-- Right: Status + Actions -->
          <div class="flex items-center gap-2">
            <!-- Status badge -->
            <span
              class="px-2 py-1 text-xs font-medium rounded whitespace-nowrap"
              :class="ref.verified
                ? 'bg-green-100 text-green-800'
                : 'bg-gray-100 text-gray-600'"
            >
              {{ ref.verified ? 'Verificada' : 'Pendiente' }}
            </span>

            <!-- Actions -->
            <div class="flex items-center gap-1">
              <a
                :href="'tel:' + ref.phone"
                class="p-2 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded transition-colors"
                title="Llamar"
              >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                </svg>
              </a>
              <button
                v-if="!ref.verified && canVerify"
                class="p-2 text-gray-400 hover:text-green-600 hover:bg-green-50 rounded transition-colors"
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
  </div>
</template>
