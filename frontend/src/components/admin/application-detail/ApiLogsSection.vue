<script setup lang="ts">
import { formatDateTime } from '@/utils/formatters'
import { getProviderColor } from '@/utils/admin-styles'

interface ApiLog {
  id: string
  provider: string
  service: string
  success: boolean
  response_status: number
  duration_ms: number
  created_at: string
  endpoint: string
  method: string
  request_method: string
  request_url: string
  error_message?: string
  request_payload?: Record<string, unknown>
  response_payload?: Record<string, unknown>
  response_body?: Record<string, unknown>
}

defineProps<{
  logs: ApiLog[]
  isLoading: boolean
}>()

const emit = defineEmits<{
  (e: 'view-detail', log: ApiLog): void
}>()
</script>

<template>
  <div>
    <!-- Loading state -->
    <div v-if="isLoading" class="flex justify-center py-8">
      <div class="animate-spin w-8 h-8 border-4 border-primary-600 border-t-transparent rounded-full" />
    </div>

    <!-- Empty state -->
    <div v-else-if="logs.length === 0" class="text-center py-8">
      <svg class="w-12 h-12 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
      </svg>
      <p class="text-gray-500">No hay logs de API para este solicitante</p>
    </div>

    <!-- Table -->
    <div v-else class="overflow-x-auto">
      <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Proveedor</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Servicio</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Estado</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">HTTP</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Duración</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fecha</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Acciones</th>
          </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
          <tr v-for="log in logs" :key="log.id" class="hover:bg-gray-50">
            <td class="px-4 py-3 whitespace-nowrap">
              <span :class="['px-2 py-1 text-xs font-medium rounded-full', getProviderColor(log.provider)]">
                {{ log.provider }}
              </span>
            </td>
            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
              {{ log.service }}
            </td>
            <td class="px-4 py-3 whitespace-nowrap">
              <span
                :class="[
                  'px-2 py-1 text-xs font-medium rounded-full',
                  log.success ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
                ]"
              >
                {{ log.success ? 'OK' : 'Error' }}
              </span>
            </td>
            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
              {{ log.response_status }}
            </td>
            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
              {{ log.duration_ms }}ms
            </td>
            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
              {{ formatDateTime(log.created_at) }}
            </td>
            <td class="px-4 py-3 whitespace-nowrap">
              <button
                class="text-primary-600 hover:text-primary-800 text-sm font-medium"
                @click="emit('view-detail', log)"
              >
                Ver detalle
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
