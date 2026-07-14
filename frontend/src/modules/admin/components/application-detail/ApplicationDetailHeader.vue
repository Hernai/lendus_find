<script setup lang="ts">
/**
 * Encabezado del detalle: folio + badge de estado + solicitante, botones de acción
 * (Contraoferta/Asignar/Cambiar Estado/Generar Contrato, gated por permisos) y la
 * caja de selfie con su estado. Extraído de AdminApplicationDetail.vue.
 *
 * 100% presentación: recibe application + permisos + estado de selfie por props y
 * emite las acciones; el padre conserva los handlers (open*Modal), el ciclo de vida
 * de la selfie (loadSelfie) y los flags show* de los modales de selfie.
 */
import { getStatusBadge } from '@/utils/admin-styles'
import { formatDateTime } from '@/utils/formatters'
import type { Application } from '@/modules/admin/views/panel/applicationDetail.types'

defineProps<{
  application: Application
  primaryColor: string
  canApproveReject: boolean
  canAssign: boolean
  canChangeStatus: boolean
  canReviewDocs: boolean
  selfieUrl: string | null
  selfieStatus: 'PENDING' | 'APPROVED' | 'REJECTED'
  isLoadingSelfie: boolean
  selfieIsKycVerified: boolean
  selfieFaceMatchScore: number | null
}>()

defineEmits<{
  (e: 'back'): void
  (e: 'open-counter-offer'): void
  (e: 'open-assign'): void
  (e: 'open-status'): void
  (e: 'view-selfie'): void
  (e: 'approve-selfie'): void
  (e: 'reject-selfie'): void
  (e: 'unapprove-selfie'): void
  (e: 'unreject-selfie'): void
}>()
</script>

<template>
  <div class="relative bg-white rounded-lg border-t-4 shadow-sm mb-6" :style="{ borderTopColor: primaryColor }">
    <!-- Back button + Header content in single row -->
    <div class="flex items-center justify-between gap-4 px-6 py-3">
      <!-- Left: Back button + Info -->
      <div class="flex items-center gap-4 flex-1 min-w-0">
        <button
          class="flex items-center gap-2 text-gray-600 hover:text-primary-600 transition-colors flex-shrink-0"
          @click="$emit('back')"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
          </svg>
        </button>

        <div class="flex-1 min-w-0">
          <div class="flex items-center gap-3 mb-0.5">
            <h1 class="text-xl font-bold text-gray-900">{{ application.folio }}</h1>
            <span
              :class="[
                'px-2.5 py-0.5 text-xs font-medium rounded-full',
                getStatusBadge(application.status).bg,
                getStatusBadge(application.status).text
              ]"
            >
              {{ getStatusBadge(application.status).label }}
            </span>
          </div>
          <p class="text-base text-gray-800 font-medium truncate">{{ application.applicant.full_name }}</p>
          <p class="text-gray-500 text-xs">
            {{ formatDateTime(application.created_at) }}
            <span v-if="application.assigned_to" class="ml-2">
              · {{ application.assigned_to }}
            </span>
          </p>
        </div>
      </div>

      <!-- Center: Action Buttons -->
      <div class="flex flex-col gap-2 flex-shrink-0">
        <!-- Contraoferta: Solo supervisores/admins que pueden aprobar/rechazar -->
        <button
          v-if="canApproveReject && ['IN_REVIEW', 'DOCS_PENDING', 'COUNTER_OFFERED'].includes(application.status)"
          class="px-3 py-1.5 text-xs font-medium rounded-lg border transition-colors"
          :style="{
            backgroundColor: `${primaryColor}15`,
            color: primaryColor,
            borderColor: `${primaryColor}40`
          }"
          @click="$emit('open-counter-offer')"
        >
          {{ application.status === 'COUNTER_OFFERED' ? 'Reenviar Contraoferta' : 'Contraoferta' }}
        </button>
        <!-- Asignar: Solo supervisores/admins -->
        <button
          v-if="canAssign"
          class="px-3 py-1.5 text-xs font-medium rounded-lg border transition-colors"
          :style="{
            backgroundColor: `${primaryColor}15`,
            color: primaryColor,
            borderColor: `${primaryColor}40`
          }"
          @click="$emit('open-assign')"
        >
          Asignar
        </button>
        <!-- Cambiar Estado: Analistas y superiores -->
        <button
          v-if="canChangeStatus"
          class="px-3 py-1.5 text-xs font-medium rounded-lg border transition-colors"
          :style="{
            backgroundColor: `${primaryColor}15`,
            color: primaryColor,
            borderColor: `${primaryColor}40`
          }"
          @click="$emit('open-status')"
        >
          Cambiar Estado
        </button>
        <!-- Generar Contrato: Solo supervisores/admins con solicitud aprobada -->
        <button
          v-if="canApproveReject && application.status === 'APPROVED'"
          class="px-3 py-1.5 text-xs font-medium text-white rounded-lg transition-colors"
          :style="{
            backgroundColor: primaryColor
          }"
        >
          Generar Contrato
        </button>
      </div>

      <!-- Right: Selfie Photo -->
      <div class="flex-shrink-0">
        <div class="relative w-28 h-28">
          <!-- Photo container -->
          <button
            class="w-full h-full rounded-lg overflow-hidden bg-gray-100 flex items-center justify-center border-2 transition-all hover:ring-2 hover:ring-primary-200"
            :class="{
              'border-green-400': selfieStatus === 'APPROVED',
              'border-red-400': selfieStatus === 'REJECTED',
              'border-yellow-400': selfieStatus === 'PENDING' && selfieUrl,
              'border-gray-300': !selfieUrl
            }"
            @click="selfieUrl ? $emit('view-selfie') : null"
            :disabled="!selfieUrl"
          >
            <img
              v-if="selfieUrl"
              :src="selfieUrl"
              alt="Foto del solicitante"
              class="w-full h-full object-cover"
            />
            <div v-else-if="isLoadingSelfie" class="animate-spin w-6 h-6 border-2 border-primary-600 border-t-transparent rounded-full" />
            <svg v-else class="w-10 h-10 text-gray-400" fill="currentColor" viewBox="0 0 24 24">
              <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
            </svg>
          </button>

          <!-- Status badge -->
          <span
            v-if="selfieUrl"
            class="absolute -top-1 -right-1 px-1.5 py-0.5 rounded text-xs font-medium z-10"
            :class="{
              'bg-green-100 text-green-800': selfieStatus === 'APPROVED',
              'bg-red-100 text-red-800': selfieStatus === 'REJECTED',
              'bg-yellow-100 text-yellow-800': selfieStatus === 'PENDING'
            }"
            :title="selfieIsKycVerified && selfieFaceMatchScore !== null ? `Face match: ${selfieFaceMatchScore.toFixed(0)}%` : ''"
          >
            <template v-if="selfieStatus === 'APPROVED' && selfieIsKycVerified && selfieFaceMatchScore !== null">
              {{ selfieFaceMatchScore.toFixed(0) }}%
            </template>
            <template v-else>
              {{ selfieStatus === 'APPROVED' ? 'OK' : selfieStatus === 'REJECTED' ? 'X' : '?' }}
            </template>
          </span>

          <!-- Approve/Reject/Unapprove buttons inside photo box (subtle icons) - Solo con permiso de revisar documentos -->
          <div
            v-if="selfieUrl && canReviewDocs"
            class="absolute bottom-1 left-1/2 -translate-x-1/2 flex gap-1"
          >
            <!-- PENDING: show approve and reject -->
            <template v-if="selfieStatus === 'PENDING'">
              <button
                class="w-6 h-6 flex items-center justify-center rounded-full bg-black/40 hover:bg-green-600 text-white/80 hover:text-white transition-colors"
                @click.stop="$emit('approve-selfie')"
                title="Aprobar"
              >
                <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24">
                  <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
                </svg>
              </button>
              <button
                class="w-6 h-6 flex items-center justify-center rounded-full bg-black/40 hover:bg-red-600 text-white/80 hover:text-white transition-colors"
                @click.stop="$emit('reject-selfie')"
                title="Rechazar"
              >
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                </svg>
              </button>
            </template>
            <!-- APPROVED: show unapprove (back to pending) - NOT if KYC verified -->
            <template v-else-if="selfieStatus === 'APPROVED' && !selfieIsKycVerified">
              <button
                class="w-6 h-6 flex items-center justify-center rounded-full bg-black/40 hover:bg-yellow-600 text-white/80 hover:text-white transition-colors"
                @click.stop="$emit('unapprove-selfie')"
                title="Desaprobar (volver a pendiente)"
              >
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/>
                </svg>
              </button>
            </template>
            <!-- APPROVED + KYC verified: show lock indicator only -->
            <template v-else-if="selfieStatus === 'APPROVED' && selfieIsKycVerified">
              <div
                class="w-6 h-6 flex items-center justify-center rounded-full bg-green-600/80 text-white"
                title="Validado por KYC - No modificable"
              >
                <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
                </svg>
              </div>
            </template>
            <!-- REJECTED: show only unreject (back to pending), no direct approve -->
            <template v-else-if="selfieStatus === 'REJECTED'">
              <button
                class="w-6 h-6 flex items-center justify-center rounded-full bg-black/40 hover:bg-yellow-600 text-white/80 hover:text-white transition-colors"
                @click.stop="$emit('unreject-selfie')"
                title="Quitar Rechazo (volver a pendiente)"
              >
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/>
                </svg>
              </button>
            </template>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
