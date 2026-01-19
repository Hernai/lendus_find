<script setup lang="ts">
interface TimelineEvent {
  id: string
  action: string
  description: string
  author: string
  created_at: string
  metadata?: {
    ip_address?: string
    user_agent?: string
    location?: string
    old_value?: string
    new_value?: string
    changes?: Record<string, string>
    reason?: string
    [key: string]: unknown
  }
}

defineProps<{
  events: TimelineEvent[]
}>()

const emit = defineEmits<{
  (e: 'view-details', event: TimelineEvent): void
}>()

const formatDateTime = (date: string) => {
  return new Date(date).toLocaleString('es-MX', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}
</script>

<template>
  <div class="flow-root">
    <ul class="-mb-8">
      <li v-for="(event, index) in events" :key="event.id">
        <div class="relative pb-8">
          <span
            v-if="index !== events.length - 1"
            class="absolute top-4 left-4 -ml-px h-full w-0.5 bg-gray-200"
          />
          <div class="relative flex space-x-3">
            <div>
              <span class="h-8 w-8 rounded-full bg-primary-100 flex items-center justify-center ring-8 ring-white">
                <svg class="h-4 w-4 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
              </span>
            </div>
            <div class="min-w-0 flex-1 pt-1.5 flex justify-between space-x-4">
              <div class="flex-1">
                <p class="text-sm text-gray-800">
                  {{ event.description }}
                </p>
                <p class="text-xs text-gray-500 mt-1">
                  Por {{ event.author }}
                </p>
              </div>
              <div class="text-right text-sm whitespace-nowrap text-gray-500 flex flex-col items-end gap-1">
                <span>{{ formatDateTime(event.created_at) }}</span>
                <button
                  v-if="event.metadata?.ip_address || event.metadata?.user_agent"
                  class="text-xs text-primary-600 hover:text-primary-800 flex items-center gap-1"
                  @click="emit('view-details', event)"
                >
                  <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                  Ver detalles
                </button>
              </div>
            </div>
          </div>
        </div>
      </li>
    </ul>

    <div v-if="events.length === 0" class="text-center py-8">
      <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
      </svg>
      <p class="mt-2 text-sm text-gray-500">No hay eventos en el historial</p>
    </div>
  </div>
</template>
