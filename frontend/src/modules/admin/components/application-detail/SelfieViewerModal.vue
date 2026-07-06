<script setup lang="ts">
/**
 * Visor de la foto (selfie) del solicitante: teleportado a body con transición,
 * imagen + estado y botones contextuales (aprobar/rechazar/desaprobar/quitar rechazo).
 * Extraído de AdminApplicationDetail.vue.
 *
 * Display-only: el estado de selfie y su ciclo de vida (loadSelfie) se quedan en el
 * padre; los botones emiten acciones que el padre mapea a cerrar el visor + abrir el
 * ConfirmModal de selfie correspondiente. Cierre vía v-model:show.
 */
defineProps<{
  show: boolean
  selfieUrl: string | null
  selfieStatus: 'PENDING' | 'APPROVED' | 'REJECTED'
  selfieIsKycVerified: boolean
  selfieFaceMatchScore: number | null
}>()

defineEmits<{
  (e: 'update:show', value: boolean): void
  (e: 'approve'): void
  (e: 'reject'): void
  (e: 'unapprove'): void
  (e: 'unreject'): void
}>()
</script>

<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition-opacity duration-200"
      leave-active-class="transition-opacity duration-200"
      enter-from-class="opacity-0"
      leave-to-class="opacity-0"
    >
      <div
        v-if="show && selfieUrl"
        class="fixed inset-0 z-50 bg-black/90 flex flex-col"
        @click="$emit('update:show', false)"
      >
        <!-- Header -->
        <div class="flex items-center justify-between px-4 py-3 text-white">
          <div class="flex items-center gap-3">
            <h3 class="font-medium">Foto del Solicitante</h3>
            <span
              class="px-2 py-0.5 rounded-full text-xs font-medium"
              :class="{
                'bg-green-100 text-green-800': selfieStatus === 'APPROVED',
                'bg-red-100 text-red-800': selfieStatus === 'REJECTED',
                'bg-yellow-100 text-yellow-800': selfieStatus === 'PENDING'
              }"
            >
              {{ selfieStatus === 'APPROVED' ? 'Aprobada' : selfieStatus === 'REJECTED' ? 'Rechazada' : 'Pendiente' }}
            </span>
          </div>
          <button
            class="p-2 bg-white/10 hover:bg-white/20 rounded-lg transition-colors"
            @click="$emit('update:show', false)"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <!-- Image -->
        <div class="flex-1 flex items-center justify-center p-4 overflow-auto" @click.stop>
          <img
            :src="selfieUrl"
            alt="Foto del solicitante"
            class="max-w-full max-h-full object-contain rounded-lg"
          />
        </div>

        <!-- Footer with actions -->
        <div v-if="selfieStatus === 'PENDING'" class="px-4 py-4 pb-safe flex justify-center gap-4">
          <button
            class="flex items-center gap-2 bg-green-500 text-white px-6 py-3 rounded-full shadow-lg hover:bg-green-600 active:bg-green-700 transition-colors"
            @click.stop="$emit('approve')"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            <span class="font-medium">Aprobar</span>
          </button>
          <button
            class="flex items-center gap-2 bg-red-500 text-white px-6 py-3 rounded-full shadow-lg hover:bg-red-600 active:bg-red-700 transition-colors"
            @click.stop="$emit('reject')"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
            <span class="font-medium">Rechazar</span>
          </button>
        </div>

        <!-- APPROVED: badge + unapprove button -->
        <div v-else-if="selfieStatus === 'APPROVED'" class="px-4 py-4 pb-safe flex justify-center gap-4">
          <div class="flex items-center gap-2 bg-green-500 text-white px-4 py-2 rounded-full shadow-lg">
            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
              <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
            </svg>
            <span class="font-medium">
              {{ selfieIsKycVerified ? 'Verificada por KYC' : 'Aprobada' }}
              <template v-if="selfieIsKycVerified && selfieFaceMatchScore !== null">
                ({{ selfieFaceMatchScore.toFixed(0) }}% match)
              </template>
            </span>
          </div>
          <!-- Only show unapprove button if NOT verified by KYC face match -->
          <button
            v-if="!selfieIsKycVerified"
            class="flex items-center gap-2 bg-yellow-500 text-white px-4 py-2 rounded-full shadow-lg hover:bg-yellow-600 active:bg-yellow-700 transition-colors"
            @click.stop="$emit('unapprove')"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" />
            </svg>
            <span class="font-medium">Desaprobar</span>
          </button>
        </div>

        <!-- REJECTED: badge + unreject button (no direct approve) -->
        <div v-else-if="selfieStatus === 'REJECTED'" class="px-4 py-4 pb-safe flex justify-center gap-4">
          <div class="flex items-center gap-2 bg-red-500 text-white px-4 py-2 rounded-full shadow-lg">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
            <span class="font-medium">Rechazada</span>
          </div>
          <button
            class="flex items-center gap-2 bg-yellow-500 text-white px-4 py-2 rounded-full shadow-lg hover:bg-yellow-600 active:bg-yellow-700 transition-colors"
            @click.stop="$emit('unreject')"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" />
            </svg>
            <span class="font-medium">Quitar Rechazo</span>
          </button>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
