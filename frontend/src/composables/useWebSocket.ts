import { onUnmounted, watch, type Ref, isRef, toValue, type ComputedRef } from 'vue'
import { getEcho } from '@/plugins/echo'
import { logger } from '@/utils/logger'

const log = logger.child('WebSocket')
import type {
  ApplicationStatusChangedEvent,
  DocumentStatusChangedEvent,
  DocumentDeletedEvent,
  DocumentUploadedEvent,
  ReferenceVerifiedEvent,
  ApplicationAssignedEvent,
  BankAccountVerifiedEvent,
} from '@/types/realtime'

type MaybeRef<T> = T | Ref<T> | ComputedRef<T>

interface UseWebSocketOptions {
  tenantId: MaybeRef<string | undefined>
  applicationId?: MaybeRef<string | undefined>
  applicantId?: MaybeRef<string | undefined>
  userId?: MaybeRef<string | undefined>
  onApplicationStatusChanged?: (event: ApplicationStatusChangedEvent) => void
  onDocumentStatusChanged?: (event: DocumentStatusChangedEvent) => void
  onDocumentDeleted?: (event: DocumentDeletedEvent) => void
  onDocumentUploaded?: (event: DocumentUploadedEvent) => void
  onReferenceVerified?: (event: ReferenceVerifiedEvent) => void
  onApplicationAssigned?: (event: ApplicationAssignedEvent) => void
  onBankAccountVerified?: (event: BankAccountVerifiedEvent) => void
}

// Helper para verificar si un valor es reactivo (Ref o ComputedRef)
function isReactive<T>(value: MaybeRef<T>): value is Ref<T> | ComputedRef<T> {
  return isRef(value) || (typeof value === 'object' && value !== null && 'value' in value)
}

interface PrivateChannel {
  name: string
  listen(event: string, callback: (...args: unknown[]) => void): PrivateChannel
}

export function useWebSocket(options: UseWebSocketOptions) {
  const channels: PrivateChannel[] = []

  // Determinar la intención del caller basado en qué opciones fueron provistas
  // (no en sus valores actuales, que pueden ser undefined inicialmente)
  const isApplicantMode = 'applicantId' in options
  const isApplicationMode = 'applicationId' in options
  const isAdminMode = !isApplicantMode && !isApplicationMode

  const connect = () => {
    const echo = getEcho()
    const tenantId = toValue(options.tenantId)
    const applicationId = toValue(options.applicationId)
    const applicantId = toValue(options.applicantId)
    const userId = toValue(options.userId)

    if (!echo) {
      log.warn(' Echo not initialized yet. WebSocket will connect after authentication.')
      return
    }

    if (!tenantId) {
      log.warn(' Tenant ID not available yet. WebSocket will connect when tenant loads.')
      return
    }

    // Si ya hay canales, no reconectar
    if (channels.length > 0) {
      return
    }

    // Para modo aplicante, esperar a que applicantId esté disponible
    if (isApplicantMode && !applicantId) {
      log.warn(' Applicant ID not available yet. WebSocket will connect when applicant loads.')
      return
    }

    // Para modo aplicación, esperar a que applicationId esté disponible
    if (isApplicationMode && !applicationId) {
      log.warn(' Application ID not available yet. WebSocket will connect when application loads.')
      return
    }

    log.debug(' Connecting to WebSocket channels...', {
      tenantId,
      applicationId,
      applicantId,
      mode: isAdminMode ? 'admin' : isApplicantMode ? 'applicant' : 'application',
    })

    // Canal de aplicación específica (staff viendo la aplicación)
    if (applicationId) {
      const appChannel = echo.private(`tenant.${tenantId}.application.${applicationId}`)

      if (options.onApplicationStatusChanged) {
        appChannel.listen('.application.status.changed', options.onApplicationStatusChanged)
      }

      if (options.onDocumentStatusChanged) {
        appChannel.listen('.document.status.changed', options.onDocumentStatusChanged)
      }

      if (options.onDocumentDeleted) {
        appChannel.listen('.document.deleted', options.onDocumentDeleted)
      }

      if (options.onDocumentUploaded) {
        appChannel.listen('.document.uploaded', options.onDocumentUploaded)
      }

      if (options.onReferenceVerified) {
        appChannel.listen('.reference.verified', options.onReferenceVerified)
      }

      if (options.onBankAccountVerified) {
        appChannel.listen('.bank_account.verified', options.onBankAccountVerified)
      }

      channels.push(appChannel)
    }

    // Canal personal del aplicante (para su dashboard)
    if (applicantId) {
      const applicantChannel = echo.private(`tenant.${tenantId}.applicant.${applicantId}`)

      if (options.onApplicationStatusChanged) {
        applicantChannel.listen('.application.status.changed', options.onApplicationStatusChanged)
      }

      if (options.onDocumentStatusChanged) {
        applicantChannel.listen('.document.status.changed', options.onDocumentStatusChanged)
      }

      if (options.onDocumentDeleted) {
        applicantChannel.listen('.document.deleted', options.onDocumentDeleted)
      }

      if (options.onDocumentUploaded) {
        applicantChannel.listen('.document.uploaded', options.onDocumentUploaded)
      }

      channels.push(applicantChannel)
    }

    // Canal admin (solo si no se especificó applicationId ni applicantId)
    if (isAdminMode) {
      const adminChannel = echo.private(`tenant.${tenantId}.admin`)

      if (options.onApplicationStatusChanged) {
        adminChannel.listen('.application.status.changed', options.onApplicationStatusChanged)
      }

      if (options.onDocumentDeleted) {
        adminChannel.listen('.document.deleted', options.onDocumentDeleted)
      }

      if (options.onDocumentUploaded) {
        adminChannel.listen('.document.uploaded', options.onDocumentUploaded)
      }

      if (options.onApplicationAssigned) {
        adminChannel.listen('.application.assigned', options.onApplicationAssigned)
      }

      channels.push(adminChannel)
    }

    // Canal de usuario específico (notificaciones personales)
    if (userId) {
      const userChannel = echo.private(`tenant.${tenantId}.user.${userId}`)

      if (options.onApplicationAssigned) {
        userChannel.listen('.application.assigned', options.onApplicationAssigned)
      }

      channels.push(userChannel)
    }

    log.debug(`Connected to ${channels.length} WebSocket channel(s)`)
  }

  const disconnect = () => {
    const echo = getEcho()

    if (!echo) {
      return
    }

    channels.forEach((channel) => {
      echo.leave(channel.name)
    })
    channels.length = 0
    log.debug(' Disconnected from WebSocket channels')
  }

  // Auto-conectar
  connect()

  // Watcher para reconectar cuando tenantId cambie (soporta Ref y ComputedRef)
  if (isReactive(options.tenantId)) {
    watch(
      () => toValue(options.tenantId),
      (newTenantId) => {
        if (newTenantId && channels.length === 0) {
          log.debug(' Tenant loaded, attempting to connect WebSocket...')
          connect()
        }
      },
      { immediate: true } // Ejecutar inmediatamente para capturar el valor actual si ya está disponible
    )
  }

  // Watcher para reconectar cuando applicantId cambie (modo aplicante)
  if (isApplicantMode && options.applicantId && isReactive(options.applicantId)) {
    watch(
      () => toValue(options.applicantId),
      (newApplicantId) => {
        if (newApplicantId && channels.length === 0) {
          log.debug(' Applicant loaded, attempting to connect WebSocket...')
          connect()
        }
      },
      { immediate: true }
    )
  }

  // Watcher para reconectar cuando applicationId cambie (modo aplicación)
  if (isApplicationMode && options.applicationId && isReactive(options.applicationId)) {
    watch(
      () => toValue(options.applicationId),
      (newApplicationId) => {
        if (newApplicationId && channels.length === 0) {
          log.debug(' Application loaded, attempting to connect WebSocket...')
          connect()
        }
      },
      { immediate: true }
    )
  }

  // Cleanup en unmount
  onUnmounted(() => {
    disconnect()
  })

  return {
    connect,
    disconnect,
  }
}
