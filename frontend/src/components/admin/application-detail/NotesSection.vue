<script setup lang="ts">
import { ref } from 'vue'
import { AppButton } from '@/components/common'

interface Note {
  id: string
  text: string
  author: string
  created_at: string
}

defineProps<{
  notes: Note[]
  isAdding: boolean
}>()

const emit = defineEmits<{
  (e: 'add', text: string): void
}>()

const newNoteText = ref('')

const handleSubmit = () => {
  if (newNoteText.value.trim()) {
    emit('add', newNoteText.value.trim())
    newNoteText.value = ''
  }
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
      <h3 class="text-sm font-semibold text-gray-900">Notas</h3>
    </div>

    <!-- Add note form -->
    <div class="p-3 border-b border-gray-100">
      <div class="flex gap-2">
        <textarea
          v-model="newNoteText"
          rows="2"
          placeholder="Agregar una nota..."
          class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500 resize-none"
          :disabled="isAdding"
        ></textarea>
        <AppButton
          variant="primary"
          size="sm"
          :disabled="!newNoteText.trim() || isAdding"
          :loading="isAdding"
          @click="handleSubmit"
        >
          Agregar
        </AppButton>
      </div>
    </div>

    <!-- Notes list -->
    <div class="divide-y divide-gray-100 max-h-80 overflow-y-auto">
      <div v-if="notes.length === 0" class="p-4 text-center text-gray-500 text-sm">
        No hay notas
      </div>
      <div
        v-for="note in notes"
        :key="note.id"
        class="p-3"
      >
        <div class="flex items-start gap-2">
          <div class="w-8 h-8 rounded-full bg-gray-200 flex items-center justify-center flex-shrink-0">
            <svg class="w-4 h-4 text-gray-500" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd" />
            </svg>
          </div>
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 mb-1">
              <span class="font-medium text-gray-900 text-sm">{{ note.author }}</span>
              <span class="text-xs text-gray-400">{{ formatDateTime(note.created_at) }}</span>
            </div>
            <p class="text-sm text-gray-600 whitespace-pre-wrap">{{ note.text }}</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
