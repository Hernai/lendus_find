import { onUnmounted } from 'vue'
import { getEcho } from '@/plugins/echo'
import type {
  ApplicationStatusChangedEvent,
  DocumentStatusChangedEvent,
  ReferenceVerifiedEvent,
  ApplicationAssignedEvent,
} from '@/types/realtime'

interface UseWebSocketOptions {
  tenantId: string
  applicationId?: string
  applicantId?: string
  userId?: string
  onApplicationStatusChanged?: (event: ApplicationStatusChangedEvent) => void
  onDocumentStatusChanged?: (event: DocumentStatusChangedEvent) => void
  onReferenceVerified?: (event: ReferenceVerifiedEvent) => void
  onApplicationAssigned?: (event: ApplicationAssignedEvent) => void
}

export function useWebSocket(options: UseWebSocketOptions) {
  const echo = getEcho()

  if (!echo) {
    console.warn('Echo not initialized. WebSocket features will not work.')
    return {
      connect: () => {},
      disconnect: () => {},
    }
  }

  const channels: any[] = []

  const connect = () => {
    // Suscribirse al canal de aplicación específica
    if (options.applicationId) {
      const appChannel = echo.private(`tenant.${options.tenantId}.application.${options.applicationId}`)

      if (options.onApplicationStatusChanged) {
        appChannel.listen('.application.status.changed', options.onApplicationStatusChanged)
      }

      if (options.onDocumentStatusChanged) {
        appChannel.listen('.document.status.changed', options.onDocumentStatusChanged)
      }

      if (options.onReferenceVerified) {
        appChannel.listen('.reference.verified', options.onReferenceVerified)
      }

      channels.push(appChannel)
    }

    // Suscribirse al canal personal del aplicante
    if (options.applicantId) {
      const applicantChannel = echo.private(`tenant.${options.tenantId}.applicant.${options.applicantId}`)

      if (options.onApplicationStatusChanged) {
        applicantChannel.listen('.application.status.changed', options.onApplicationStatusChanged)
      }

      if (options.onDocumentStatusChanged) {
        applicantChannel.listen('.document.status.changed', options.onDocumentStatusChanged)
      }

      channels.push(applicantChannel)
    }

    // Suscribirse al canal admin
    if (!options.applicationId && !options.applicantId) {
      const adminChannel = echo.private(`tenant.${options.tenantId}.admin`)

      if (options.onApplicationStatusChanged) {
        adminChannel.listen('.application.status.changed', options.onApplicationStatusChanged)
      }

      if (options.onApplicationAssigned) {
        adminChannel.listen('.application.assigned', options.onApplicationAssigned)
      }

      channels.push(adminChannel)
    }

    // Suscribirse al canal de usuario específico
    if (options.userId) {
      const userChannel = echo.private(`tenant.${options.tenantId}.user.${options.userId}`)

      if (options.onApplicationAssigned) {
        userChannel.listen('.application.assigned', options.onApplicationAssigned)
      }

      channels.push(userChannel)
    }
  }

  const disconnect = () => {
    channels.forEach((channel) => {
      echo.leave(channel.name)
    })
    channels.length = 0
  }

  // Auto-conectar
  connect()

  // Cleanup en unmount
  onUnmounted(() => {
    disconnect()
  })

  return {
    connect,
    disconnect,
  }
}
