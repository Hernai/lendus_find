<script setup lang="ts">
/**
 * Modal "Asignar para Revisión": lista de analistas + selección. Extraído de
 * AdminApplicationDetail.vue.
 *
 * La carga async de analistas (openAssignModal) y la asignación (assignApplication)
 * quedan en el padre; el hijo recibe la lista + loadings por props, mantiene la
 * selección local y emite confirm(userId). Cierre vía v-model:show.
 */
import { ref, watch } from 'vue'
import { AppButton } from '@/components/common'
import type { StaffUser } from '@/modules/admin/views/panel/applicationDetail.types'

const props = defineProps<{
  show: boolean
  staffUsers: StaffUser[]
  isLoadingUsers: boolean
  isAssigning: boolean
}>()

const emit = defineEmits<{
  (e: 'update:show', value: boolean): void
  (e: 'confirm', userId: string): void
}>()

const selectedUserId = ref('')

// Resetear la selección al abrir (equivalente a openAssignModal en el padre).
watch(() => props.show, (v) => {
  if (v) selectedUserId.value = ''
})
</script>

<template>
<div
  v-if="show"
  class="fixed inset-0 bg-black/50 flex items-center justify-center z-50"
  @click.self="emit('update:show', false)"
>
  <div class="bg-white rounded-xl p-6 w-full max-w-md mx-4">
    <h3 class="text-lg font-semibold text-gray-900 mb-2">Asignar para Revisión</h3>
    <p class="text-sm text-gray-500 mb-4">Selecciona un analista para revisar esta solicitud</p>

    <div class="space-y-4">
      <div v-if="isLoadingUsers" class="flex justify-center py-8">
        <div class="animate-spin w-8 h-8 border-4 border-primary-600 border-t-transparent rounded-full" />
      </div>

      <div v-else-if="staffUsers.length === 0" class="text-center py-8">
        <svg class="w-12 h-12 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
        </svg>
        <p class="text-gray-500">No hay analistas disponibles</p>
        <p class="text-sm text-gray-400 mt-1">Crea un usuario con rol Analista en la sección de Usuarios</p>
      </div>

      <template v-else>
        <!-- Analysts list -->
        <div class="space-y-2">
          <div
            v-for="user in staffUsers"
            :key="user.id"
            class="flex items-center justify-between p-3 rounded-lg cursor-pointer transition-colors"
            :class="selectedUserId === user.id ? 'bg-primary-50 border border-primary-200' : 'bg-gray-50 hover:bg-gray-100'"
            @click="selectedUserId = user.id"
          >
            <div class="flex items-center gap-3">
              <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center text-sm font-medium text-blue-700">
                {{ user.name.charAt(0).toUpperCase() }}
              </div>
              <div>
                <p class="font-medium text-gray-900 text-sm">{{ user.name }}</p>
                <p class="text-xs text-gray-500">{{ user.email }}</p>
              </div>
            </div>
            <svg v-if="selectedUserId === user.id" class="w-5 h-5 text-primary-600" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
            </svg>
          </div>
        </div>
      </template>
    </div>

    <div class="flex gap-3 mt-6">
      <AppButton
        variant="outline"
        class="flex-1"
        @click="emit('update:show', false)"
      >
        Cancelar
      </AppButton>
      <AppButton
        variant="primary"
        class="flex-1"
        :loading="isAssigning"
        :disabled="!selectedUserId"
        @click="emit('confirm', selectedUserId)"
      >
        Asignar
      </AppButton>
    </div>
  </div>
</div>
</template>
