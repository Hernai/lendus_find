/**
 * Store de Notificaciones In-App del Aplicante
 *
 * Maneja el estado de notificaciones in-app: listado, contador,
 * marcar como leída, y recepción en tiempo real via WebSocket.
 */

import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import notificationService from '@/services/v2/notification.applicant.service'
import type { InAppNotification } from '@/services/v2/notification.applicant.service'

export const useNotificationStore = defineStore('notification', () => {
  // =====================================================
  // State
  // =====================================================

  const notifications = ref<InAppNotification[]>([])
  const unreadCount = ref(0)
  const isLoading = ref(false)
  const currentPage = ref(1)
  const totalPages = ref(1)

  // =====================================================
  // Getters
  // =====================================================

  const hasUnread = computed(() => unreadCount.value > 0)
  const hasMore = computed(() => currentPage.value < totalPages.value)

  // =====================================================
  // Actions
  // =====================================================

  async function loadNotifications(page = 1): Promise<void> {
    isLoading.value = true
    try {
      const response = await notificationService.list(page)
      if (response.success && response.data) {
        notifications.value = response.data.notifications
        currentPage.value = response.data.pagination.current_page
        totalPages.value = response.data.pagination.last_page
      }
    } catch {
      // Silenciar error — el componente puede manejar el estado vacío
    } finally {
      isLoading.value = false
    }
  }

  async function loadMore(): Promise<void> {
    if (!hasMore.value || isLoading.value) return

    isLoading.value = true
    try {
      const nextPage = currentPage.value + 1
      const response = await notificationService.list(nextPage)
      if (response.success && response.data) {
        notifications.value.push(...response.data.notifications)
        currentPage.value = response.data.pagination.current_page
        totalPages.value = response.data.pagination.last_page
      }
    } catch {
      // Silenciar error
    } finally {
      isLoading.value = false
    }
  }

  async function fetchUnreadCount(): Promise<void> {
    try {
      const response = await notificationService.unreadCount()
      if (response.success && response.data) {
        unreadCount.value = response.data.unread_count
      }
    } catch {
      // Silenciar error
    }
  }

  async function markAsRead(id: string): Promise<void> {
    try {
      const response = await notificationService.markAsRead(id)
      if (response.success && response.data) {
        const index = notifications.value.findIndex(n => n.id === id)
        if (index !== -1) {
          notifications.value[index] = response.data.notification
        }
        if (unreadCount.value > 0) {
          unreadCount.value--
        }
      }
    } catch {
      // Silenciar error
    }
  }

  async function markAllAsRead(): Promise<void> {
    try {
      const response = await notificationService.markAllAsRead()
      if (response.success) {
        notifications.value = notifications.value.map(n => ({
          ...n,
          is_read: true,
          read_at: n.read_at || new Date().toISOString(),
        }))
        unreadCount.value = 0
      }
    } catch {
      // Silenciar error
    }
  }

  function addNotification(notification: InAppNotification): void {
    notifications.value.unshift(notification)
    unreadCount.value++
  }

  function reset(): void {
    notifications.value = []
    unreadCount.value = 0
    isLoading.value = false
    currentPage.value = 1
    totalPages.value = 1
  }

  // =====================================================
  // Return Store
  // =====================================================

  return {
    // State
    notifications,
    unreadCount,
    isLoading,
    currentPage,
    totalPages,

    // Getters
    hasUnread,
    hasMore,

    // Actions
    loadNotifications,
    loadMore,
    fetchUnreadCount,
    markAsRead,
    markAllAsRead,
    addNotification,
    reset,
  }
})
