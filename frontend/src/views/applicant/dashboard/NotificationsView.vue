<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useNotificationStore } from '@/stores'
import type { InAppNotification } from '@/services/v2/notification.applicant.service'

const router = useRouter()
const notificationStore = useNotificationStore()

const isMarkingAll = ref(false)

onMounted(async () => {
  await notificationStore.loadNotifications()
})

const goBack = () => {
  router.back()
}

const handleMarkAllAsRead = async () => {
  isMarkingAll.value = true
  await notificationStore.markAllAsRead()
  isMarkingAll.value = false
}

const handleNotificationClick = async (notification: InAppNotification) => {
  if (!notification.is_read) {
    await notificationStore.markAsRead(notification.id)
  }
}

const handleScroll = (e: Event) => {
  const target = e.target as HTMLElement
  if (target.scrollHeight - target.scrollTop - target.clientHeight < 100) {
    notificationStore.loadMore()
  }
}

// Mapeo de eventos a iconos SVG paths
const getEventIcon = (event: string): string => {
  const iconMap: Record<string, string> = {
    // Registro y perfil
    'user.registered': 'M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z',
    'profile.completed': 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z',
    // Solicitud
    'application.created': 'M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z',
    'application.submitted': 'M12 19l9 2-9-18-9 18 9-2zm0 0v-8',
    'application.in_review': 'M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z',
    'application.approved': 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z',
    'application.rejected': 'M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z',
    'application.docs_pending': 'M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12',
    'application.corrections_requested': 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z',
    'application.counter_offered': 'M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4',
    'application.cancelled': 'M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636',
    'application.synced': 'M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15',
    'counter_offer.accepted': 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z',
    'counter_offer.rejected': 'M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z',
    // Documentos
    'document.uploaded': 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
    'document.approved': 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
    'document.rejected': 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
    'documents.complete': 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4',
    // KYC
    'kyc.completed': 'M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2',
    'kyc.failed': 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z',
    // Referencias
    'reference.verified': 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z',
    // Cuenta bancaria y seguridad
    'bank_account.verified': 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z',
    'security.pin_changed': 'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z',
    // Préstamos y pagos
    'loan.disbursed': 'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z',
    'payment.received': 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
    'payment.upcoming': 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
    'payment.overdue': 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z',
    'loan.completed': 'M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z',
    'loan.default': 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z',
    // Recordatorios
    'reminder.pending_docs': 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
    'reminder.incomplete_profile': 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
  }
  return iconMap[event] || 'M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9'
}

const getEventColor = (event: string): string => {
  const colorMap: Record<string, string> = {
    // Verde — éxito
    'application.approved': 'text-green-600 bg-green-100',
    'document.approved': 'text-green-600 bg-green-100',
    'documents.complete': 'text-green-600 bg-green-100',
    'kyc.completed': 'text-green-600 bg-green-100',
    'profile.completed': 'text-green-600 bg-green-100',
    'reference.verified': 'text-green-600 bg-green-100',
    'counter_offer.accepted': 'text-green-600 bg-green-100',
    'bank_account.verified': 'text-green-600 bg-green-100',
    'loan.completed': 'text-green-600 bg-green-100',
    'payment.received': 'text-green-600 bg-green-100',
    // Azul/Teal — informativo
    'application.submitted': 'text-blue-600 bg-blue-100',
    'application.created': 'text-blue-600 bg-blue-100',
    'user.registered': 'text-blue-600 bg-blue-100',
    'document.uploaded': 'text-blue-600 bg-blue-100',
    'application.synced': 'text-blue-600 bg-blue-100',
    'loan.disbursed': 'text-teal-600 bg-teal-100',
    // Amarillo — atención requerida
    'application.in_review': 'text-amber-600 bg-amber-100',
    'application.docs_pending': 'text-amber-600 bg-amber-100',
    'reminder.pending_docs': 'text-amber-600 bg-amber-100',
    'reminder.incomplete_profile': 'text-amber-600 bg-amber-100',
    'payment.upcoming': 'text-amber-600 bg-amber-100',
    // Rojo — acción urgente
    'application.rejected': 'text-red-600 bg-red-100',
    'application.corrections_requested': 'text-red-600 bg-red-100',
    'document.rejected': 'text-red-600 bg-red-100',
    'kyc.failed': 'text-red-600 bg-red-100',
    'counter_offer.rejected': 'text-red-600 bg-red-100',
    'payment.overdue': 'text-red-600 bg-red-100',
    'loan.default': 'text-red-600 bg-red-100',
    // Gris — cancelación
    'application.cancelled': 'text-gray-600 bg-gray-100',
    // Púrpura — contraoferta
    'application.counter_offered': 'text-purple-600 bg-purple-100',
    // Naranja — seguridad
    'security.pin_changed': 'text-orange-600 bg-orange-100',
  }
  return colorMap[event] || 'text-primary-600 bg-primary-100'
}

const formatRelativeDate = (dateStr: string): string => {
  const date = new Date(dateStr)
  const now = new Date()
  const diffMs = now.getTime() - date.getTime()
  const diffMin = Math.floor(diffMs / 60000)
  const diffHrs = Math.floor(diffMs / 3600000)
  const diffDays = Math.floor(diffMs / 86400000)

  if (diffMin < 1) return 'Ahora'
  if (diffMin < 60) return `Hace ${diffMin} min`
  if (diffHrs < 24) return `Hace ${diffHrs}h`
  if (diffDays === 1) return 'Ayer'
  if (diffDays < 7) return `Hace ${diffDays} días`

  return date.toLocaleDateString('es-MX', { day: 'numeric', month: 'short' })
}
</script>

<template>
  <div class="min-h-screen bg-gray-50" @scroll.passive="handleScroll">
    <!-- Header -->
    <header class="bg-gradient-to-br from-primary-600 to-primary-700 px-4 pt-3 pb-6">
      <div class="max-w-2xl mx-auto">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-3">
            <button
              class="w-8 h-8 bg-white/10 rounded-lg flex items-center justify-center text-white hover:bg-white/20 transition-colors"
              @click="goBack"
            >
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
              </svg>
            </button>
            <h1 class="text-lg font-bold text-white">Notificaciones</h1>
          </div>
          <button
            v-if="notificationStore.hasUnread"
            class="px-3 py-1.5 bg-white/10 rounded-lg text-white text-xs font-medium hover:bg-white/20 transition-colors disabled:opacity-50"
            :disabled="isMarkingAll"
            @click="handleMarkAllAsRead"
          >
            {{ isMarkingAll ? 'Marcando...' : 'Marcar todas como leídas' }}
          </button>
        </div>
      </div>
    </header>

    <!-- Content -->
    <main class="max-w-2xl mx-auto px-4 -mt-3">
      <!-- Loading -->
      <div v-if="notificationStore.isLoading && notificationStore.notifications.length === 0" class="bg-white rounded-2xl shadow-lg p-6">
        <div class="space-y-4">
          <div v-for="i in 4" :key="i" class="flex gap-3 animate-pulse">
            <div class="w-10 h-10 bg-gray-200 rounded-full flex-shrink-0" />
            <div class="flex-1 space-y-2">
              <div class="h-4 bg-gray-200 rounded w-3/4" />
              <div class="h-3 bg-gray-200 rounded w-1/2" />
            </div>
          </div>
        </div>
      </div>

      <!-- Empty State -->
      <div
        v-else-if="notificationStore.notifications.length === 0"
        class="bg-white rounded-2xl shadow-lg p-8 text-center"
      >
        <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
          <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
          </svg>
        </div>
        <h2 class="text-lg font-semibold text-gray-900 mb-1">No tienes notificaciones</h2>
        <p class="text-sm text-gray-500">Te avisaremos cuando haya novedades sobre tu solicitud.</p>
      </div>

      <!-- Notifications List -->
      <div v-else class="bg-white rounded-2xl shadow-lg overflow-hidden divide-y divide-gray-100">
        <button
          v-for="notification in notificationStore.notifications"
          :key="notification.id"
          class="w-full flex items-start gap-3 p-4 text-left hover:bg-gray-50 transition-colors"
          @click="handleNotificationClick(notification)"
        >
          <!-- Unread indicator + Icon -->
          <div class="relative flex-shrink-0">
            <div :class="['w-10 h-10 rounded-full flex items-center justify-center', getEventColor(notification.event)]">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="getEventIcon(notification.event)" />
              </svg>
            </div>
            <!-- Blue dot for unread -->
            <span
              v-if="!notification.is_read"
              class="absolute -top-0.5 -left-0.5 w-3 h-3 bg-blue-500 rounded-full border-2 border-white"
            />
          </div>

          <!-- Content -->
          <div class="flex-1 min-w-0">
            <p :class="['text-sm leading-snug', notification.is_read ? 'text-gray-700' : 'text-gray-900 font-medium']">
              {{ notification.subject || notification.body }}
            </p>
            <p v-if="notification.subject && notification.body" class="text-xs text-gray-500 mt-0.5 line-clamp-2">
              {{ notification.body }}
            </p>
            <p class="text-xs text-gray-400 mt-1">{{ formatRelativeDate(notification.created_at) }}</p>
          </div>
        </button>

        <!-- Load more indicator -->
        <div v-if="notificationStore.isLoading && notificationStore.notifications.length > 0" class="p-4 text-center">
          <div class="animate-spin w-5 h-5 border-2 border-primary-600 border-t-transparent rounded-full mx-auto" />
        </div>

        <!-- Load more button -->
        <button
          v-else-if="notificationStore.hasMore"
          class="w-full p-4 text-sm text-primary-600 font-medium hover:bg-gray-50 transition-colors"
          @click="notificationStore.loadMore()"
        >
          Cargar anteriores
        </button>
      </div>

      <!-- Bottom spacing -->
      <div class="h-8" />
    </main>
  </div>
</template>
