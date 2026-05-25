<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import {
  notificationTemplatesApi,
  type NotificationTemplate,
  type TemplateConfig,
} from '@/services/notificationTemplates'
import SendTestModal from '@/components/admin/notification-templates/SendTestModal.vue'
import ConfirmModal from '@/components/admin/ConfirmModal.vue'
import { useToast } from '@/composables/useToast'
import { emailHtml, detailRows } from '@/utils/emailHtmlHelper'
import { SearchableSelect } from '@/components/common'

const toast = useToast()

const router = useRouter()

const templates = ref<NotificationTemplate[]>([])
const config = ref<TemplateConfig | null>(null)
const loading = ref(false)
const error = ref<string | null>(null)

// Send test modal
const showSendTestModal = ref(false)
const sendTestTemplate = ref<NotificationTemplate | null>(null)

const openSendTest = (template: NotificationTemplate) => {
  sendTestTemplate.value = template
  showSendTestModal.value = true
}

// Filters
const filterEvent = ref<string | null>(null)
const filterChannel = ref<string | null>(null)
const filterStatus = ref<string | null>(null)
const searchQuery = ref('')

const eventOptions = computed(() =>
  (config.value?.events ?? []).map((e: { value: string; label: string }) => ({ value: e.value, label: e.label })),
)
const channelOptions = computed(() =>
  (config.value?.channels ?? []).map((c: { value: string; label: string }) => ({ value: c.value, label: c.label })),
)
const statusOptions = [
  { value: 'active', label: 'Activas' },
  { value: 'inactive', label: 'Inactivas' },
] as const

watch([filterEvent, filterChannel, filterStatus], () => loadTemplates())

// Load data
const loadTemplates = async () => {
  loading.value = true
  error.value = null
  try {
    const params: any = {}
    if (filterEvent.value) params.event = filterEvent.value
    if (filterChannel.value) params.channel = filterChannel.value
    if (filterStatus.value) params.is_active = filterStatus.value === 'active'

    templates.value = await notificationTemplatesApi.getAll(params)
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Error al cargar plantillas'
    console.error('Error loading templates:', err)
  } finally {
    loading.value = false
  }
}

const loadConfig = async () => {
  try {
    config.value = await notificationTemplatesApi.getConfig()
  } catch (err) {
    console.error('Error loading config:', err)
  }
}

// Filtered templates
const filteredTemplates = computed(() => {
  if (!templates.value) return []

  let result = templates.value

  if (searchQuery.value) {
    const query = searchQuery.value.toLowerCase()
    result = result.filter(
      (t) =>
        t.name.toLowerCase().includes(query) ||
        t.event_label.toLowerCase().includes(query) ||
        t.channel_label.toLowerCase().includes(query)
    )
  }

  return result
})

// Group by event
const templatesByEvent = computed(() => {
  const groups: Record<string, NotificationTemplate[]> = {}
  const filtered = filteredTemplates.value || []

  filtered.forEach((template) => {
    if (!groups[template.event]) {
      groups[template.event] = []
    }
    groups[template.event].push(template)
  })
  return groups
})

// Stats
const stats = computed(() => {
  const templateList = templates.value || []

  return {
    total: templateList.length,
    active: templateList.filter((t) => t.is_active).length,
    inactive: templateList.filter((t) => !t.is_active).length,
    byChannel: templateList.reduce((acc, t) => {
      acc[t.channel] = (acc[t.channel] || 0) + 1
      return acc
    }, {} as Record<string, number>),
  }
})

// Create suggested templates
const creatingSuggested = ref(false)
const showSuggestedConfirm = ref(false)

const suggestedModeOptions = [
  { value: 'replace', label: 'Reemplazar todas las existentes' },
  { value: 'delete', label: 'Eliminar existentes (sin crear nuevas)' },
  { value: 'keep', label: 'Mantener existentes y agregar nuevas' },
]

const onSuggestedConfirm = async (data: { selectValue?: string }) => {
  const mode = data.selectValue as 'replace' | 'delete' | 'keep'
  if (!mode) return

  if (mode === 'delete') {
    creatingSuggested.value = true
    try {
      const existing = templates.value || []
      for (const t of existing) {
        await notificationTemplatesApi.delete(t.id)
      }
      await loadTemplates()
      showSuggestedConfirm.value = false
      toast.success(`Se eliminaron ${existing.length} plantillas exitosamente`)
    } catch (err: any) {
      toast.error(err.response?.data?.message || 'Error al eliminar plantillas')
    } finally {
      creatingSuggested.value = false
    }
    return
  }

  await createSuggestedTemplates(mode)
}

const createSuggestedTemplates = async (mode: 'replace' | 'keep') => {
  creatingSuggested.value = true
  try {
    const suggestedTemplates = [
      // ═══════════════ OTP ═══════════════
      {
        name: 'Código de Verificación - Email',
        event: 'otp.sent',
        channel: 'EMAIL',
        is_active: true,
        priority: 1,
        subject: 'Tu código de verificación: {{otp.code}}',
        body: '{{tenant.name}}: Tu código de verificación es {{otp.code}}. Válido por {{otp.expires_in}} minutos.',
        html_body: `<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"></head>
<body style="margin:0;padding:0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background-color:#f3f4f6">
<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f3f4f6;padding:40px 20px"><tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" border="0" style="background-color:#fff;border-radius:16px;overflow:hidden;box-shadow:0 4px 6px rgba(0,0,0,0.1)">
<tr><td style="padding:40px 30px;text-align:center;background:linear-gradient(135deg,#3b82f6 0%,#2563eb 100%)">
<h1 style="margin:0;color:#fff;font-size:24px;font-weight:700">{{tenant.name}}</h1>
</td></tr>
<tr><td style="padding:40px 30px;text-align:center">
<h2 style="margin:0 0 16px 0;color:#1f2937;font-size:24px;font-weight:700">Código de Verificación</h2>
<p style="margin:0 0 32px 0;color:#6b7280;font-size:16px">Usa el siguiente código para verificar tu identidad:</p>
<div style="background:linear-gradient(135deg,#3b82f6 0%,#2563eb 100%);padding:24px 48px;border-radius:12px;margin:0 auto 24px;display:inline-block">
<span style="color:#fff;font-size:36px;font-weight:700;letter-spacing:8px;font-family:'Courier New',monospace">{{otp.code}}</span>
</div>
<p style="margin:0;color:#6b7280;font-size:14px">Este código expirará en <strong>{{otp.expires_in}} minutos</strong></p>
</td></tr>
<tr><td style="background-color:#f9fafb;padding:24px 30px;text-align:center;border-top:1px solid #e5e7eb">
<p style="margin:0;color:#9ca3af;font-size:12px">{{tenant.name}} · Mensaje automático</p>
</td></tr>
</table>
</td></tr></table>
</body>
</html>`,
      },
      {
        name: 'Código OTP - SMS',
        event: 'otp.sent',
        channel: 'SMS',
        is_active: true,
        priority: 1,
        subject: null,
        body: '{{tenant.name}}: Tu código es {{otp.code}}. Expira en {{otp.expires_in}} min. No lo compartas.',
        html_body: null,
      },
      {
        name: 'Código OTP - WhatsApp',
        event: 'otp.sent',
        channel: 'WHATSAPP',
        is_active: true,
        priority: 1,
        subject: null,
        body: '*{{tenant.name}}*\n\nTu código de verificación es:\n\n*{{otp.code}}*\n\nExpira en {{otp.expires_in}} minutos. No lo compartas con nadie.',
        html_body: null,
      },

      // ═══════════════ REGISTRO Y PERFIL ═══════════════
      {
        name: 'Bienvenida - Email',
        event: 'user.registered',
        channel: 'EMAIL',
        is_active: true,
        priority: 5,
        subject: '¡Bienvenido/a a {{tenant.name}}!',
        body: 'Hola {{applicant.first_name}}, tu cuenta ha sido creada exitosamente en {{tenant.name}}.',
        html_body: emailHtml({
          gradient: '#667eea 0%,#764ba2 100%',
          heading: '¡Bienvenido/a!',
          icon: 'wave',
          greeting: 'Hola <strong>{{applicant.first_name}}</strong>,',
          body: '<p style="margin:0 0 16px 0;color:#374151;font-size:16px;line-height:1.6">Tu cuenta ha sido creada exitosamente. Ya puedes iniciar tu solicitud de crédito.</p><p style="margin:0;color:#6b7280;font-size:14px">Si tienes alguna pregunta, no dudes en contactarnos.</p>',
          ctaText: 'Acceder',
          ctaUrl: '{{dashboard_url}}',
        }),
      },
      {
        name: 'Bienvenida - SMS',
        event: 'user.registered',
        channel: 'SMS',
        is_active: true,
        priority: 5,
        subject: null,
        body: '{{tenant.name}}: Bienvenido/a {{applicant.first_name}}. Tu cuenta ha sido creada. Inicia sesión para continuar.',
        html_body: null,
      },
      {
        name: 'Bienvenida - In App',
        event: 'user.registered',
        channel: 'IN_APP',
        is_active: true,
        priority: 5,
        subject: 'Bienvenido/a',
        body: 'Tu cuenta ha sido creada. Ya puedes iniciar tu solicitud de crédito.',
        html_body: null,
      },
      {
        name: 'Perfil Completado - Email',
        event: 'profile.completed',
        channel: 'EMAIL',
        is_active: true,
        priority: 5,
        subject: 'Perfil completado - {{tenant.name}}',
        body: 'Hola {{applicant.first_name}}, tu perfil ha sido completado. Ya puedes enviar tu solicitud.',
        html_body: emailHtml({
          gradient: '#10b981 0%,#059669 100%',
          heading: 'Perfil Completado',
          icon: 'check',
          greeting: 'Hola <strong>{{applicant.first_name}}</strong>,',
          body: '<p style="margin:0;color:#374151;font-size:16px;line-height:1.6">Tu perfil ha sido completado exitosamente. Ya puedes continuar con tu solicitud de crédito.</p>',
          ctaText: 'Continuar Solicitud',
          ctaUrl: '{{dashboard_url}}',
        }),
      },
      {
        name: 'Perfil Completado - In App',
        event: 'profile.completed',
        channel: 'IN_APP',
        is_active: true,
        priority: 5,
        subject: 'Perfil completado',
        body: 'Tu perfil ha sido completado exitosamente. Ya puedes enviar tu solicitud.',
        html_body: null,
      },

      // ═══════════════ SOLICITUD - CREACIÓN Y ENVÍO ═══════════════
      {
        name: 'Solicitud Creada - In App',
        event: 'application.created',
        channel: 'IN_APP',
        is_active: true,
        priority: 5,
        subject: 'Solicitud creada',
        body: 'Tu solicitud {{application.folio}} ha sido creada. Completa tu información y envíala para revisión.',
        html_body: null,
      },
      {
        name: 'Solicitud Recibida - Email',
        event: 'application.submitted',
        channel: 'EMAIL',
        is_active: true,
        priority: 5,
        subject: '¡Solicitud Recibida! - {{application.folio}}',
        body: 'Hola {{applicant.first_name}}, hemos recibido tu solicitud {{application.folio}}.',
        html_body: emailHtml({
          gradient: '#667eea 0%,#764ba2 100%',
          heading: '¡Solicitud Recibida!',
          icon: 'mail-sent',
          greeting: 'Hola <strong>{{applicant.first_name}}</strong>,',
          body: '<p style="margin:0;color:#374151;font-size:16px;line-height:1.6">Tu solicitud ha sido recibida exitosamente. Nuestro equipo la revisará pronto.</p>',
          details: detailRows(['Folio', '{{application.folio}}'], ['Monto', '{{application.amount}}'], ['Producto', '{{application.product_name}}']),
          detailsTitle: 'Detalles de la Solicitud',
          detailsTint: 'purple',
        }),
      },
      {
        name: 'Solicitud Recibida - SMS',
        event: 'application.submitted',
        channel: 'SMS',
        is_active: true,
        priority: 5,
        subject: null,
        body: '{{tenant.name}}: Recibimos tu solicitud {{application.folio}}. Te notificaremos cuando haya novedades.',
        html_body: null,
      },
      {
        name: 'Solicitud Recibida - In App',
        event: 'application.submitted',
        channel: 'IN_APP',
        is_active: true,
        priority: 5,
        subject: 'Solicitud enviada',
        body: 'Tu solicitud {{application.folio}} ha sido enviada y está pendiente de revisión.',
        html_body: null,
      },

      // ═══════════════ SOLICITUD - EN REVISIÓN ═══════════════
      {
        name: 'Solicitud en Revisión - Email',
        event: 'application.in_review',
        channel: 'EMAIL',
        is_active: true,
        priority: 5,
        subject: 'Tu solicitud está en revisión - {{application.folio}}',
        body: 'Hola {{applicant.first_name}}, tu solicitud {{application.folio}} está siendo revisada por nuestro equipo.',
        html_body: emailHtml({
          gradient: '#f59e0b 0%,#d97706 100%',
          heading: 'Solicitud en Revisión',
          icon: 'search',
          greeting: 'Hola <strong>{{applicant.first_name}}</strong>,',
          body: '<p style="margin:0;color:#374151;font-size:16px;line-height:1.6">Tu solicitud está siendo revisada por nuestro equipo. Te notificaremos cuando tengamos novedades.</p>',
          details: detailRows(['Folio', '{{application.folio}}'], ['Producto', '{{application.product_name}}']),
          detailsTitle: 'Tu Solicitud',
          detailsTint: 'amber',
        }),
      },
      {
        name: 'Solicitud en Revisión - In App',
        event: 'application.in_review',
        channel: 'IN_APP',
        is_active: true,
        priority: 5,
        subject: 'Solicitud en revisión',
        body: 'Tu solicitud {{application.folio}} está siendo revisada. Te notificaremos cuando tengamos novedades.',
        html_body: null,
      },

      // ═══════════════ SOLICITUD - APROBADA ═══════════════
      {
        name: 'Solicitud Aprobada - Email',
        event: 'application.approved',
        channel: 'EMAIL',
        is_active: true,
        priority: 3,
        subject: '¡Felicidades! Tu solicitud ha sido aprobada',
        body: 'Hola {{applicant.first_name}}, tu solicitud {{application.folio}} ha sido aprobada.',
        html_body: emailHtml({
          gradient: '#10b981 0%,#059669 100%',
          heading: '¡Solicitud Aprobada!',
          icon: 'celebrate',
          greeting: 'Hola <strong>{{applicant.first_name}}</strong>,',
          body: '<p style="margin:0;color:#374151;font-size:16px;line-height:1.6">Nos complace informarte que tu solicitud ha sido aprobada exitosamente.</p>',
          details: detailRows(['Folio', '{{application.folio}}'], ['Monto Aprobado', '{{application.amount}}'], ['Plazo', '{{application.term_months}} meses']),
          detailsTitle: 'Detalles del Crédito',
          detailsTint: 'green',
          ctaText: 'Ver Mi Solicitud',
          ctaUrl: '{{dashboard_url}}',
        }),
      },
      {
        name: 'Solicitud Aprobada - SMS',
        event: 'application.approved',
        channel: 'SMS',
        is_active: true,
        priority: 3,
        subject: null,
        body: '{{tenant.name}}: ¡Felicidades {{applicant.first_name}}! Tu solicitud {{application.folio}} ha sido aprobada.',
        html_body: null,
      },
      {
        name: 'Solicitud Aprobada - WhatsApp',
        event: 'application.approved',
        channel: 'WHATSAPP',
        is_active: true,
        priority: 3,
        subject: null,
        body: '*{{tenant.name}}*\n\n¡Felicidades *{{applicant.first_name}}*! Tu solicitud *{{application.folio}}* ha sido *aprobada*.\n\nMonto: {{application.amount}}\nPlazo: {{application.term_months}} meses',
        html_body: null,
      },
      {
        name: 'Solicitud Aprobada - In App',
        event: 'application.approved',
        channel: 'IN_APP',
        is_active: true,
        priority: 3,
        subject: '¡Solicitud aprobada!',
        body: '¡Felicidades! Tu solicitud {{application.folio}} por {{application.amount}} ha sido aprobada.',
        html_body: null,
      },

      // ═══════════════ SOLICITUD - RECHAZADA ═══════════════
      {
        name: 'Solicitud Rechazada - Email',
        event: 'application.rejected',
        channel: 'EMAIL',
        is_active: true,
        priority: 3,
        subject: 'Actualización de tu solicitud - {{application.folio}}',
        body: 'Hola {{applicant.first_name}}, lamentamos informarte que tu solicitud {{application.folio}} no pudo ser aprobada.',
        html_body: emailHtml({
          gradient: '#6b7280 0%,#4b5563 100%',
          heading: 'Actualización de tu Solicitud',
          icon: 'document',
          greeting: 'Hola <strong>{{applicant.first_name}}</strong>,',
          body: '<p style="margin:0 0 16px 0;color:#374151;font-size:16px;line-height:1.6">Lamentamos informarte que tu solicitud <strong>{{application.folio}}</strong> no pudo ser aprobada en este momento.</p><p style="margin:0;color:#374151;font-size:16px;line-height:1.6">Esto no significa el fin del camino. Puedes volver a aplicar en el futuro o contactarnos para más información.</p>',
          details: detailRows(['Folio', '{{application.folio}}']),
          detailsTint: 'neutral',
        }),
      },
      {
        name: 'Solicitud Rechazada - SMS',
        event: 'application.rejected',
        channel: 'SMS',
        is_active: true,
        priority: 3,
        subject: null,
        body: '{{tenant.name}}: Tu solicitud {{application.folio}} no fue aprobada. Contactanos para más información.',
        html_body: null,
      },
      {
        name: 'Solicitud Rechazada - In App',
        event: 'application.rejected',
        channel: 'IN_APP',
        is_active: true,
        priority: 3,
        subject: 'Solicitud no aprobada',
        body: 'Tu solicitud {{application.folio}} no pudo ser aprobada en este momento. Contacta a soporte para más información.',
        html_body: null,
      },

      // ═══════════════ DOCUMENTOS PENDIENTES ═══════════════
      {
        name: 'Documentos Pendientes - Email',
        event: 'application.docs_pending',
        channel: 'EMAIL',
        is_active: true,
        priority: 4,
        subject: 'Documentos pendientes - {{application.folio}}',
        body: 'Hola {{applicant.first_name}}, necesitamos documentación adicional para tu solicitud {{application.folio}}.',
        html_body: emailHtml({
          gradient: '#f59e0b 0%,#d97706 100%',
          heading: 'Documentos Pendientes',
          icon: 'document',
          greeting: 'Hola <strong>{{applicant.first_name}}</strong>,',
          body: '<p style="margin:0;color:#374151;font-size:16px;line-height:1.6">Necesitamos documentación adicional para continuar con tu solicitud.</p>',
          details: detailRows(['Folio', '{{application.folio}}']),
          detailsTint: 'amber',
          ctaText: 'Subir Documentos',
          ctaUrl: '{{dashboard_url}}',
        }),
      },
      {
        name: 'Documentos Pendientes - SMS',
        event: 'application.docs_pending',
        channel: 'SMS',
        is_active: true,
        priority: 4,
        subject: null,
        body: '{{tenant.name}}: Necesitamos documentos adicionales para tu solicitud {{application.folio}}. Ingresa a tu cuenta.',
        html_body: null,
      },
      {
        name: 'Documentos Pendientes - WhatsApp',
        event: 'application.docs_pending',
        channel: 'WHATSAPP',
        is_active: true,
        priority: 4,
        subject: null,
        body: '*{{tenant.name}}*\n\nHola {{applicant.first_name}}, necesitamos documentos adicionales para tu solicitud *{{application.folio}}*.\n\nIngresa a tu cuenta para subirlos.',
        html_body: null,
      },
      {
        name: 'Documentos Pendientes - In App',
        event: 'application.docs_pending',
        channel: 'IN_APP',
        is_active: true,
        priority: 4,
        subject: 'Documentos pendientes',
        body: 'Tu solicitud {{application.folio}} requiere documentación adicional.',
        html_body: null,
      },

      // ═══════════════ CORRECCIONES SOLICITADAS ═══════════════
      {
        name: 'Correcciones Solicitadas - Email',
        event: 'application.corrections_requested',
        channel: 'EMAIL',
        is_active: true,
        priority: 4,
        subject: 'Se requieren correcciones - {{application.folio}}',
        body: 'Hola {{applicant.first_name}}, se han solicitado correcciones en tu solicitud {{application.folio}}.',
        html_body: emailHtml({
          gradient: '#ef4444 0%,#dc2626 100%',
          heading: 'Correcciones Requeridas',
          icon: 'edit',
          greeting: 'Hola <strong>{{applicant.first_name}}</strong>,',
          body: '<p style="margin:0;color:#374151;font-size:16px;line-height:1.6">Hemos detectado información que necesita ser corregida en tu solicitud <strong>{{application.folio}}</strong>. Ingresa a tu cuenta para revisar y corregir los datos indicados.</p>',
          details: detailRows(['Folio', '{{application.folio}}']),
          detailsTint: 'red',
          ctaText: 'Revisar Solicitud',
          ctaUrl: '{{dashboard_url}}',
        }),
      },
      {
        name: 'Correcciones Solicitadas - SMS',
        event: 'application.corrections_requested',
        channel: 'SMS',
        is_active: true,
        priority: 4,
        subject: null,
        body: '{{tenant.name}}: Se requieren correcciones en tu solicitud {{application.folio}}. Ingresa a tu cuenta.',
        html_body: null,
      },
      {
        name: 'Correcciones Solicitadas - In App',
        event: 'application.corrections_requested',
        channel: 'IN_APP',
        is_active: true,
        priority: 4,
        subject: 'Correcciones requeridas',
        body: 'Se han solicitado correcciones en tu solicitud {{application.folio}}.',
        html_body: null,
      },

      // ═══════════════ DOCUMENTOS ═══════════════
      {
        name: 'Documento Subido - In App',
        event: 'document.uploaded',
        channel: 'IN_APP',
        is_active: true,
        priority: 5,
        subject: 'Documento recibido',
        body: 'Tu documento {{document.type_label}} ha sido recibido y está pendiente de revisión.',
        html_body: null,
      },
      {
        name: 'Documento Aprobado - In App',
        event: 'document.approved',
        channel: 'IN_APP',
        is_active: true,
        priority: 5,
        subject: 'Documento aprobado',
        body: 'Tu documento {{document.type_label}} ha sido aprobado.',
        html_body: null,
      },
      {
        name: 'Documento Rechazado - Email',
        event: 'document.rejected',
        channel: 'EMAIL',
        is_active: true,
        priority: 4,
        subject: 'Documento rechazado - Se requiere acción',
        body: 'Hola {{applicant.first_name}}, tu documento {{document.type_label}} fue rechazado. Por favor sube uno nuevo.',
        html_body: emailHtml({
          gradient: '#ef4444 0%,#dc2626 100%',
          heading: 'Documento Rechazado',
          icon: 'warning',
          greeting: 'Hola <strong>{{applicant.first_name}}</strong>,',
          body: '<p style="margin:0;color:#374151;font-size:16px;line-height:1.6">Tu documento <strong>{{document.type_label}}</strong> no pudo ser validado. Ingresa a tu cuenta para subir un nuevo documento.</p>',
          ctaText: 'Subir Documento',
          ctaUrl: '{{dashboard_url}}',
        }),
      },
      {
        name: 'Documento Rechazado - In App',
        event: 'document.rejected',
        channel: 'IN_APP',
        is_active: true,
        priority: 4,
        subject: 'Documento rechazado',
        body: 'Tu documento {{document.type_label}} fue rechazado. Por favor sube uno nuevo.',
        html_body: null,
      },
      {
        name: 'Documentos Completos - In App',
        event: 'documents.complete',
        channel: 'IN_APP',
        is_active: true,
        priority: 5,
        subject: 'Documentación completa',
        body: 'Todos tus documentos han sido recibidos. Tu solicitud avanzará al siguiente paso.',
        html_body: null,
      },

      // ═══════════════ REFERENCIAS ═══════════════
      {
        name: 'Referencia Verificada - In App',
        event: 'reference.verified',
        channel: 'IN_APP',
        is_active: true,
        priority: 5,
        subject: 'Referencia verificada',
        body: 'Una de tus referencias ha sido verificada exitosamente.',
        html_body: null,
      },

      // ═══════════════ KYC ═══════════════
      {
        name: 'Validación KYC Completada - In App',
        event: 'kyc.completed',
        channel: 'IN_APP',
        is_active: true,
        priority: 5,
        subject: 'Identidad verificada',
        body: 'Tu identidad ha sido verificada exitosamente. Tu solicitud continuará con el proceso.',
        html_body: null,
      },
      {
        name: 'Validación KYC Fallida - Email',
        event: 'kyc.failed',
        channel: 'EMAIL',
        is_active: true,
        priority: 3,
        subject: 'Problema con la verificación de identidad',
        body: 'Hola {{applicant.first_name}}, hubo un problema al verificar tu identidad. Intenta de nuevo.',
        html_body: emailHtml({
          gradient: '#ef4444 0%,#dc2626 100%',
          heading: 'Verificación de Identidad',
          icon: 'lock',
          greeting: 'Hola <strong>{{applicant.first_name}}</strong>,',
          body: '<p style="margin:0 0 16px 0;color:#374151;font-size:16px;line-height:1.6">Hubo un problema al verificar tu identidad. Por favor intenta nuevamente.</p><p style="margin:0;color:#6b7280;font-size:14px">Asegúrate de que tu identificación sea legible y esté vigente.</p>',
          ctaText: 'Intentar de Nuevo',
          ctaUrl: '{{dashboard_url}}',
        }),
      },
      {
        name: 'Validación KYC Fallida - In App',
        event: 'kyc.failed',
        channel: 'IN_APP',
        is_active: true,
        priority: 3,
        subject: 'Error en verificación',
        body: 'Hubo un problema al verificar tu identidad. Por favor intenta nuevamente.',
        html_body: null,
      },

      // ═══════════════ STAFF ═══════════════
      {
        name: 'Analista Asignado - Email',
        event: 'analyst.assigned',
        channel: 'EMAIL',
        is_active: true,
        priority: 5,
        subject: 'Nueva solicitud asignada - {{application.folio}}',
        body: 'Se te ha asignado la solicitud {{application.folio}} de {{applicant.first_name}} {{applicant.last_name}}.',
        html_body: emailHtml({
          gradient: '#3b82f6 0%,#2563eb 100%',
          heading: 'Nueva Solicitud Asignada',
          icon: 'clipboard',
          body: '<p style="margin:0;color:#374151;font-size:16px;line-height:1.6">Se te ha asignado una nueva solicitud para revisión.</p>',
          details: detailRows(['Folio', '{{application.folio}}'], ['Solicitante', '{{applicant.first_name}} {{applicant.last_name}}'], ['Monto', '{{application.amount}}'], ['Producto', '{{application.product_name}}']),
          detailsTitle: 'Datos de la Solicitud',
          detailsTint: 'blue',
          ctaText: 'Ver Solicitud',
          ctaUrl: '{{dashboard_url}}',
        }),
      },
      {
        name: 'Analista Asignado - In App',
        event: 'analyst.assigned',
        channel: 'IN_APP',
        is_active: true,
        priority: 5,
        subject: 'Solicitud asignada',
        body: 'Se te asignó la solicitud {{application.folio}} de {{applicant.first_name}} {{applicant.last_name}}.',
        html_body: null,
      },
      {
        name: 'Comentario Agregado - In App',
        event: 'comment.added',
        channel: 'IN_APP',
        is_active: true,
        priority: 5,
        subject: 'Nuevo comentario',
        body: 'Se agregó un nuevo comentario en la solicitud {{application.folio}}.',
        html_body: null,
      },

      // ═══════════════ RECORDATORIOS ═══════════════
      {
        name: 'Recordatorio Documentos - Email',
        event: 'reminder.pending_docs',
        channel: 'EMAIL',
        is_active: true,
        priority: 5,
        subject: 'Recordatorio: Documentos pendientes - {{application.folio}}',
        body: 'Hola {{applicant.first_name}}, aún tienes documentos pendientes en tu solicitud {{application.folio}}.',
        html_body: emailHtml({
          gradient: '#f59e0b 0%,#d97706 100%',
          heading: 'Recordatorio',
          icon: 'clock',
          greeting: 'Hola <strong>{{applicant.first_name}}</strong>,',
          body: '<p style="margin:0;color:#374151;font-size:16px;line-height:1.6">Te recordamos que aún tienes documentos pendientes en tu solicitud. Sube tus documentos lo antes posible para continuar con el proceso.</p>',
          details: detailRows(['Folio', '{{application.folio}}']),
          detailsTint: 'amber',
          ctaText: 'Subir Documentos',
          ctaUrl: '{{dashboard_url}}',
        }),
      },
      {
        name: 'Recordatorio Documentos - SMS',
        event: 'reminder.pending_docs',
        channel: 'SMS',
        is_active: true,
        priority: 5,
        subject: null,
        body: '{{tenant.name}}: Recordatorio - Tienes documentos pendientes en tu solicitud {{application.folio}}. Ingresa a tu cuenta.',
        html_body: null,
      },
      {
        name: 'Recordatorio Documentos - In App',
        event: 'reminder.pending_docs',
        channel: 'IN_APP',
        is_active: true,
        priority: 5,
        subject: 'Recordatorio',
        body: 'Aún tienes documentos pendientes en tu solicitud {{application.folio}}.',
        html_body: null,
      },
      {
        name: 'Recordatorio Perfil - Email',
        event: 'reminder.incomplete_profile',
        channel: 'EMAIL',
        is_active: true,
        priority: 5,
        subject: 'Completa tu perfil - {{tenant.name}}',
        body: 'Hola {{applicant.first_name}}, tu perfil aún está incompleto. Complétalo para iniciar tu solicitud.',
        html_body: emailHtml({
          gradient: '#8b5cf6 0%,#7c3aed 100%',
          heading: 'Completa tu Perfil',
          icon: 'user-edit',
          greeting: 'Hola <strong>{{applicant.first_name}}</strong>,',
          body: '<p style="margin:0;color:#374151;font-size:16px;line-height:1.6">Notamos que tu perfil aún está incompleto. Completa tu información para poder iniciar tu solicitud de crédito.</p>',
          ctaText: 'Completar Perfil',
          ctaUrl: '{{dashboard_url}}',
        }),
      },
      {
        name: 'Recordatorio Perfil - SMS',
        event: 'reminder.incomplete_profile',
        channel: 'SMS',
        is_active: true,
        priority: 5,
        subject: null,
        body: '{{tenant.name}}: Tu perfil está incompleto. Ingresa para completarlo y solicitar tu crédito.',
        html_body: null,
      },
      {
        name: 'Recordatorio Perfil - In App',
        event: 'reminder.incomplete_profile',
        channel: 'IN_APP',
        is_active: true,
        priority: 5,
        subject: 'Perfil incompleto',
        body: 'Tu perfil está incompleto. Complétalo para solicitar tu crédito.',
        html_body: null,
      },

      // ═══════════════ SOLICITUD CANCELADA ═══════════════
      {
        name: 'Solicitud Cancelada - Email',
        event: 'application.cancelled',
        channel: 'EMAIL',
        is_active: true,
        priority: 3,
        subject: 'Tu solicitud {{application.folio}} ha sido cancelada',
        body: 'Hola {{applicant.first_name}}, tu solicitud ha sido cancelada.',
        html_body: emailHtml({
          gradient: '#6b7280 0%,#4b5563 100%',
          heading: 'Solicitud Cancelada',
          icon: 'document',
          greeting: 'Hola <strong>{{applicant.first_name}}</strong>,',
          body: '<p style="margin:0 0 16px 0;color:#374151;font-size:16px;line-height:1.6">Tu solicitud <strong>{{application.folio}}</strong> ha sido cancelada.</p><p style="margin:0;color:#6b7280;font-size:14px">Si tienes alguna duda, no dudes en contactarnos.</p>',
          details: detailRows(['Folio', '{{application.folio}}'], ['Producto', '{{application.product_name}}']),
          detailsTint: 'neutral',
        }),
      },
      {
        name: 'Solicitud Cancelada - SMS',
        event: 'application.cancelled',
        channel: 'SMS',
        is_active: true,
        priority: 3,
        subject: null,
        body: '{{tenant.name}}: Tu solicitud {{application.folio}} ha sido cancelada. Contáctanos para más información.',
        html_body: null,
      },
      {
        name: 'Solicitud Cancelada - In App',
        event: 'application.cancelled',
        channel: 'IN_APP',
        is_active: true,
        priority: 3,
        subject: 'Solicitud cancelada',
        body: 'Tu solicitud {{application.folio}} ha sido cancelada.',
        html_body: null,
      },

      // ═══════════════ CONTRAOFERTA ENVIADA ═══════════════
      {
        name: 'Contraoferta - Email',
        event: 'application.counter_offered',
        channel: 'EMAIL',
        is_active: true,
        priority: 2,
        subject: 'Tienes una contraoferta - {{application.folio}}',
        body: 'Hola {{applicant.first_name}}, hemos preparado una contraoferta para tu solicitud.',
        html_body: emailHtml({
          gradient: '#8b5cf6 0%,#7c3aed 100%',
          heading: 'Contraoferta Disponible',
          icon: 'star',
          greeting: 'Hola <strong>{{applicant.first_name}}</strong>,',
          body: '<p style="margin:0;color:#374151;font-size:16px;line-height:1.6">Hemos preparado una contraoferta para tu solicitud <strong>{{application.folio}}</strong>. Revisa los nuevos términos y decide si deseas aceptarla.</p>',
          details: detailRows(['Folio', '{{application.folio}}'], ['Monto Original', '{{application.amount}}'], ['Monto Contraoferta', '{{counter_offer.amount}}'], ['Nuevo Plazo', '{{counter_offer.term_months}} meses']),
          detailsTitle: 'Detalles de la Contraoferta',
          detailsTint: 'purple',
          ctaText: 'Ver Contraoferta',
          ctaUrl: '{{dashboard_url}}',
        }),
      },
      {
        name: 'Contraoferta - SMS',
        event: 'application.counter_offered',
        channel: 'SMS',
        is_active: true,
        priority: 2,
        subject: null,
        body: '{{tenant.name}}: Tienes una contraoferta para tu solicitud {{application.folio}} por {{counter_offer.amount}}. Ingresa a tu cuenta.',
        html_body: null,
      },
      {
        name: 'Contraoferta - WhatsApp',
        event: 'application.counter_offered',
        channel: 'WHATSAPP',
        is_active: true,
        priority: 2,
        subject: null,
        body: '*{{tenant.name}}*\n\nHola {{applicant.first_name}}, tenemos una contraoferta para tu solicitud *{{application.folio}}*.\n\nMonto: {{counter_offer.amount}}\nPlazo: {{counter_offer.term_months}} meses\nPago mensual: {{counter_offer.monthly_payment}}',
        html_body: null,
      },
      {
        name: 'Contraoferta - In App',
        event: 'application.counter_offered',
        channel: 'IN_APP',
        is_active: true,
        priority: 2,
        subject: 'Contraoferta disponible',
        body: 'Tienes una contraoferta para tu solicitud {{application.folio}} por {{counter_offer.amount}}.',
        html_body: null,
      },

      // ═══════════════ CONTRAOFERTA ACEPTADA ═══════════════
      {
        name: 'Contraoferta Aceptada - Email',
        event: 'counter_offer.accepted',
        channel: 'EMAIL',
        is_active: true,
        priority: 2,
        subject: 'Contraoferta aceptada - {{application.folio}}',
        body: 'Hola {{applicant.first_name}}, has aceptado la contraoferta.',
        html_body: emailHtml({
          gradient: '#10b981 0%,#059669 100%',
          heading: 'Contraoferta Aceptada',
          icon: 'celebrate',
          greeting: 'Hola <strong>{{applicant.first_name}}</strong>,',
          body: '<p style="margin:0;color:#374151;font-size:16px;line-height:1.6">Has aceptado la contraoferta para tu solicitud <strong>{{application.folio}}</strong>. Tu solicitud continuará con el proceso.</p>',
          details: detailRows(['Folio', '{{application.folio}}'], ['Monto Aprobado', '{{counter_offer.amount}}'], ['Plazo', '{{counter_offer.term_months}} meses'], ['Pago Mensual', '{{counter_offer.monthly_payment}}']),
          detailsTitle: 'Nuevos Términos',
          detailsTint: 'green',
          ctaText: 'Ver Mi Solicitud',
          ctaUrl: '{{dashboard_url}}',
        }),
      },
      {
        name: 'Contraoferta Aceptada - SMS',
        event: 'counter_offer.accepted',
        channel: 'SMS',
        is_active: true,
        priority: 2,
        subject: null,
        body: '{{tenant.name}}: Has aceptado la contraoferta de {{application.folio}} por {{counter_offer.amount}}.',
        html_body: null,
      },
      {
        name: 'Contraoferta Aceptada - WhatsApp',
        event: 'counter_offer.accepted',
        channel: 'WHATSAPP',
        is_active: true,
        priority: 2,
        subject: null,
        body: '*{{tenant.name}}*\n\nHola {{applicant.first_name}}, has aceptado la contraoferta de tu solicitud *{{application.folio}}*.\n\nMonto: {{counter_offer.amount}}\nPlazo: {{counter_offer.term_months}} meses\nPago mensual: {{counter_offer.monthly_payment}}',
        html_body: null,
      },
      {
        name: 'Contraoferta Aceptada - In App',
        event: 'counter_offer.accepted',
        channel: 'IN_APP',
        is_active: true,
        priority: 2,
        subject: 'Contraoferta aceptada',
        body: 'Has aceptado la contraoferta de tu solicitud {{application.folio}} por {{counter_offer.amount}}.',
        html_body: null,
      },

      // ═══════════════ CONTRAOFERTA RECHAZADA ═══════════════
      {
        name: 'Contraoferta Rechazada - Email',
        event: 'counter_offer.rejected',
        channel: 'EMAIL',
        is_active: true,
        priority: 4,
        subject: 'Actualización sobre tu solicitud - {{application.folio}}',
        body: 'Hola {{applicant.first_name}}, la contraoferta no fue aceptada.',
        html_body: emailHtml({
          gradient: '#6b7280 0%,#4b5563 100%',
          heading: 'Contraoferta No Aceptada',
          icon: 'document',
          greeting: 'Hola <strong>{{applicant.first_name}}</strong>,',
          body: '<p style="margin:0 0 16px 0;color:#374151;font-size:16px;line-height:1.6">La contraoferta para tu solicitud <strong>{{application.folio}}</strong> no fue aceptada.</p><p style="margin:0;color:#6b7280;font-size:14px">Si deseas explorar otras opciones, no dudes en contactarnos.</p>',
          details: detailRows(['Folio', '{{application.folio}}']),
          detailsTint: 'neutral',
        }),
      },
      {
        name: 'Contraoferta Rechazada - In App',
        event: 'counter_offer.rejected',
        channel: 'IN_APP',
        is_active: true,
        priority: 4,
        subject: 'Contraoferta no aceptada',
        body: 'La contraoferta de tu solicitud {{application.folio}} no fue aceptada.',
        html_body: null,
      },

      // ═══════════════ SOLICITUD SINCRONIZADA ═══════════════
      {
        name: 'Solicitud Sincronizada - In App',
        event: 'application.synced',
        channel: 'IN_APP',
        is_active: true,
        priority: 5,
        subject: 'Solicitud en proceso',
        body: 'Tu solicitud {{application.folio}} ha avanzado al siguiente paso del proceso.',
        html_body: null,
      },

      // ═══════════════ CUENTA BANCARIA VERIFICADA ═══════════════
      {
        name: 'Cuenta Verificada - Email',
        event: 'bank_account.verified',
        channel: 'EMAIL',
        is_active: true,
        priority: 5,
        subject: 'Cuenta bancaria verificada - {{tenant.name}}',
        body: 'Hola {{applicant.first_name}}, tu cuenta bancaria ha sido verificada.',
        html_body: emailHtml({
          gradient: '#10b981 0%,#059669 100%',
          heading: 'Cuenta Verificada',
          icon: 'check',
          greeting: 'Hola <strong>{{applicant.first_name}}</strong>,',
          body: '<p style="margin:0;color:#374151;font-size:16px;line-height:1.6">Tu cuenta bancaria ha sido verificada exitosamente.</p>',
          details: detailRows(['Banco', '{{bank_account.bank_name}}'], ['CLABE', '{{bank_account.masked_clabe}}'], ['Titular', '{{bank_account.holder_name}}']),
          detailsTitle: 'Datos de la Cuenta',
          detailsTint: 'green',
        }),
      },
      {
        name: 'Cuenta Verificada - In App',
        event: 'bank_account.verified',
        channel: 'IN_APP',
        is_active: true,
        priority: 5,
        subject: 'Cuenta bancaria verificada',
        body: 'Tu cuenta en {{bank_account.bank_name}} ({{bank_account.masked_clabe}}) ha sido verificada.',
        html_body: null,
      },

      // ═══════════════ PIN DE SEGURIDAD CAMBIADO ═══════════════
      {
        name: 'PIN Cambiado - Email',
        event: 'security.pin_changed',
        channel: 'EMAIL',
        is_active: true,
        priority: 2,
        subject: 'Alerta de seguridad - PIN modificado',
        body: 'Hola {{applicant.first_name}}, tu PIN de seguridad ha sido cambiado. Si no fuiste tú, contáctanos.',
        html_body: emailHtml({
          gradient: '#ef4444 0%,#dc2626 100%',
          heading: 'PIN Modificado',
          icon: 'lock',
          greeting: 'Hola <strong>{{applicant.first_name}}</strong>,',
          body: '<p style="margin:0 0 16px 0;color:#374151;font-size:16px;line-height:1.6">Tu PIN de seguridad ha sido modificado exitosamente.</p><p style="margin:0;color:#374151;font-size:16px;line-height:1.6"><strong>Si no realizaste este cambio, contacta a soporte de inmediato.</strong></p>',
        }),
      },
      {
        name: 'PIN Cambiado - SMS',
        event: 'security.pin_changed',
        channel: 'SMS',
        is_active: true,
        priority: 2,
        subject: null,
        body: '{{tenant.name}}: Tu PIN fue cambiado. Si no fuiste tú, contáctanos de inmediato.',
        html_body: null,
      },
      {
        name: 'PIN Cambiado - In App',
        event: 'security.pin_changed',
        channel: 'IN_APP',
        is_active: true,
        priority: 2,
        subject: 'PIN de seguridad cambiado',
        body: 'Tu PIN ha sido modificado. Si no realizaste este cambio, contacta a soporte.',
        html_body: null,
      },

      // ═══════════════ CRÉDITO DESEMBOLSADO ═══════════════
      {
        name: 'Desembolso - Email',
        event: 'loan.disbursed',
        channel: 'EMAIL',
        is_active: true,
        priority: 1,
        subject: '¡Tu crédito ha sido desembolsado! - {{application.folio}}',
        body: 'Hola {{applicant.first_name}}, tu crédito ha sido desembolsado.',
        html_body: emailHtml({
          gradient: '#10b981 0%,#059669 100%',
          heading: '¡Crédito Desembolsado!',
          icon: 'celebrate',
          greeting: 'Hola <strong>{{applicant.first_name}}</strong>,',
          body: '<p style="margin:0;color:#374151;font-size:16px;line-height:1.6">Nos complace informarte que tu crédito ha sido desembolsado exitosamente.</p>',
          details: detailRows(['Folio', '{{application.folio}}'], ['Monto', '{{loan.disbursed_amount}}'], ['Fecha', '{{loan.disbursement_date}}'], ['Cuenta', '{{loan.bank_account}}'], ['Referencia', '{{loan.reference}}']),
          detailsTitle: 'Detalles del Desembolso',
          detailsTint: 'green',
          ctaText: 'Ver Mi Crédito',
          ctaUrl: '{{dashboard_url}}',
        }),
      },
      {
        name: 'Desembolso - SMS',
        event: 'loan.disbursed',
        channel: 'SMS',
        is_active: true,
        priority: 1,
        subject: null,
        body: '{{tenant.name}}: Tu crédito {{application.folio}} por {{loan.disbursed_amount}} ha sido depositado. Ref: {{loan.reference}}',
        html_body: null,
      },
      {
        name: 'Desembolso - WhatsApp',
        event: 'loan.disbursed',
        channel: 'WHATSAPP',
        is_active: true,
        priority: 1,
        subject: null,
        body: '*{{tenant.name}}*\n\n¡Hola {{applicant.first_name}}! Tu crédito ha sido desembolsado.\n\nFolio: *{{application.folio}}*\nMonto: {{loan.disbursed_amount}}\nFecha: {{loan.disbursement_date}}\nReferencia: {{loan.reference}}',
        html_body: null,
      },
      {
        name: 'Desembolso - In App',
        event: 'loan.disbursed',
        channel: 'IN_APP',
        is_active: true,
        priority: 1,
        subject: 'Crédito desembolsado',
        body: 'Tu crédito {{application.folio}} por {{loan.disbursed_amount}} ha sido depositado en tu cuenta.',
        html_body: null,
      },

      // ═══════════════ PAGO RECIBIDO ═══════════════
      {
        name: 'Pago Recibido - Email',
        event: 'payment.received',
        channel: 'EMAIL',
        is_active: true,
        priority: 3,
        subject: 'Pago recibido - {{application.folio}}',
        body: 'Hola {{applicant.first_name}}, hemos recibido tu pago.',
        html_body: emailHtml({
          gradient: '#10b981 0%,#059669 100%',
          heading: 'Pago Recibido',
          icon: 'check',
          greeting: 'Hola <strong>{{applicant.first_name}}</strong>,',
          body: '<p style="margin:0;color:#374151;font-size:16px;line-height:1.6">Hemos recibido tu pago exitosamente. Gracias por mantenerte al corriente.</p>',
          details: detailRows(['Folio', '{{application.folio}}'], ['Monto', '{{payment.amount}}'], ['Fecha', '{{payment.date}}'], ['Método', '{{payment.method}}'], ['Saldo Restante', '{{payment.remaining_balance}}']),
          detailsTitle: 'Detalles del Pago',
          detailsTint: 'green',
        }),
      },
      {
        name: 'Pago Recibido - SMS',
        event: 'payment.received',
        channel: 'SMS',
        is_active: true,
        priority: 3,
        subject: null,
        body: '{{tenant.name}}: Pago de {{payment.amount}} recibido para {{application.folio}}. Saldo: {{payment.remaining_balance}}',
        html_body: null,
      },
      {
        name: 'Pago Recibido - WhatsApp',
        event: 'payment.received',
        channel: 'WHATSAPP',
        is_active: true,
        priority: 3,
        subject: null,
        body: '*{{tenant.name}}*\n\nHola {{applicant.first_name}}, hemos recibido tu pago.\n\nMonto: {{payment.amount}}\nFecha: {{payment.date}}\nSaldo restante: {{payment.remaining_balance}}',
        html_body: null,
      },
      {
        name: 'Pago Recibido - In App',
        event: 'payment.received',
        channel: 'IN_APP',
        is_active: true,
        priority: 3,
        subject: 'Pago recibido',
        body: 'Tu pago de {{payment.amount}} fue recibido. Saldo restante: {{payment.remaining_balance}}',
        html_body: null,
      },

      // ═══════════════ PAGO PRÓXIMO A VENCER ═══════════════
      {
        name: 'Recordatorio Pago - Email',
        event: 'payment.upcoming',
        channel: 'EMAIL',
        is_active: true,
        priority: 4,
        subject: 'Recordatorio de pago - {{application.folio}}',
        body: 'Hola {{applicant.first_name}}, tu próximo pago está por vencer.',
        html_body: emailHtml({
          gradient: '#f59e0b 0%,#d97706 100%',
          heading: 'Recordatorio de Pago',
          icon: 'clock',
          greeting: 'Hola <strong>{{applicant.first_name}}</strong>,',
          body: '<p style="margin:0;color:#374151;font-size:16px;line-height:1.6">Te recordamos que tu próximo pago está por vencer. Realízalo a tiempo para evitar recargos.</p>',
          details: detailRows(['Folio', '{{application.folio}}'], ['Pago #', '{{payment.payment_number}} de {{payment.total_payments}}'], ['Monto', '{{payment.amount}}'], ['Vence', '{{payment.due_date}}']),
          detailsTitle: 'Próximo Pago',
          detailsTint: 'amber',
          ctaText: 'Realizar Pago',
          ctaUrl: '{{dashboard_url}}',
        }),
      },
      {
        name: 'Recordatorio Pago - SMS',
        event: 'payment.upcoming',
        channel: 'SMS',
        is_active: true,
        priority: 4,
        subject: null,
        body: '{{tenant.name}}: Tu pago de {{payment.amount}} vence el {{payment.due_date}}. Folio: {{application.folio}}',
        html_body: null,
      },
      {
        name: 'Recordatorio Pago - In App',
        event: 'payment.upcoming',
        channel: 'IN_APP',
        is_active: true,
        priority: 4,
        subject: 'Pago próximo a vencer',
        body: 'Tu pago #{{payment.payment_number}} de {{payment.amount}} vence el {{payment.due_date}}.',
        html_body: null,
      },

      // ═══════════════ PAGO VENCIDO ═══════════════
      {
        name: 'Pago Vencido - Email',
        event: 'payment.overdue',
        channel: 'EMAIL',
        is_active: true,
        priority: 2,
        subject: 'Pago vencido - Atención requerida - {{application.folio}}',
        body: 'Hola {{applicant.first_name}}, tienes un pago vencido.',
        html_body: emailHtml({
          gradient: '#ef4444 0%,#dc2626 100%',
          heading: 'Pago Vencido',
          icon: 'warning',
          greeting: 'Hola <strong>{{applicant.first_name}}</strong>,',
          body: '<p style="margin:0;color:#374151;font-size:16px;line-height:1.6">Tienes un pago vencido. Te recomendamos regularizarte lo antes posible para evitar recargos adicionales.</p>',
          details: detailRows(['Folio', '{{application.folio}}'], ['Monto', '{{payment.amount}}'], ['Venció', '{{payment.due_date}}'], ['Días de atraso', '{{payment.days_overdue}}'], ['Recargo', '{{payment.late_fee}}']),
          detailsTitle: 'Pago Vencido',
          detailsTint: 'red',
          ctaText: 'Realizar Pago',
          ctaUrl: '{{dashboard_url}}',
        }),
      },
      {
        name: 'Pago Vencido - SMS',
        event: 'payment.overdue',
        channel: 'SMS',
        is_active: true,
        priority: 2,
        subject: null,
        body: '{{tenant.name}}: Tu pago de {{payment.amount}} está vencido ({{payment.days_overdue}} días). Folio: {{application.folio}}',
        html_body: null,
      },
      {
        name: 'Pago Vencido - In App',
        event: 'payment.overdue',
        channel: 'IN_APP',
        is_active: true,
        priority: 2,
        subject: 'Pago vencido',
        body: 'Tu pago de {{payment.amount}} está vencido por {{payment.days_overdue}} días. Recargo: {{payment.late_fee}}',
        html_body: null,
      },

      // ═══════════════ CRÉDITO LIQUIDADO ═══════════════
      {
        name: 'Crédito Liquidado - Email',
        event: 'loan.completed',
        channel: 'EMAIL',
        is_active: true,
        priority: 3,
        subject: '¡Felicidades! Tu crédito ha sido liquidado',
        body: 'Hola {{applicant.first_name}}, tu crédito ha sido liquidado completamente.',
        html_body: emailHtml({
          gradient: '#10b981 0%,#059669 100%',
          heading: '¡Crédito Liquidado!',
          icon: 'celebrate',
          greeting: 'Hola <strong>{{applicant.first_name}}</strong>,',
          body: '<p style="margin:0;color:#374151;font-size:16px;line-height:1.6">¡Felicidades! Tu crédito ha sido liquidado completamente. Gracias por tu confianza.</p>',
          details: detailRows(['Folio', '{{application.folio}}'], ['Total Pagado', '{{loan.total_paid}}'], ['Fecha de Liquidación', '{{loan.completion_date}}']),
          detailsTitle: 'Resumen del Crédito',
          detailsTint: 'green',
        }),
      },
      {
        name: 'Crédito Liquidado - SMS',
        event: 'loan.completed',
        channel: 'SMS',
        is_active: true,
        priority: 3,
        subject: null,
        body: '{{tenant.name}}: ¡Felicidades {{applicant.first_name}}! Tu crédito {{application.folio}} ha sido liquidado.',
        html_body: null,
      },
      {
        name: 'Crédito Liquidado - WhatsApp',
        event: 'loan.completed',
        channel: 'WHATSAPP',
        is_active: true,
        priority: 3,
        subject: null,
        body: '*{{tenant.name}}*\n\n¡Felicidades *{{applicant.first_name}}*! Tu crédito *{{application.folio}}* ha sido liquidado completamente.\n\nTotal pagado: {{loan.total_paid}}\nFecha: {{loan.completion_date}}\n\n¡Gracias por tu confianza!',
        html_body: null,
      },
      {
        name: 'Crédito Liquidado - In App',
        event: 'loan.completed',
        channel: 'IN_APP',
        is_active: true,
        priority: 3,
        subject: 'Crédito liquidado',
        body: '¡Felicidades! Tu crédito {{application.folio}} ha sido liquidado completamente.',
        html_body: null,
      },

      // ═══════════════ CRÉDITO EN MORA ═══════════════
      {
        name: 'Mora - Email',
        event: 'loan.default',
        channel: 'EMAIL',
        is_active: true,
        priority: 1,
        subject: 'Aviso importante sobre tu crédito - {{application.folio}}',
        body: 'Hola {{applicant.first_name}}, tu crédito presenta un atraso importante.',
        html_body: emailHtml({
          gradient: '#dc2626 0%,#991b1b 100%',
          heading: 'Aviso Importante',
          icon: 'warning',
          greeting: 'Hola <strong>{{applicant.first_name}}</strong>,',
          body: '<p style="margin:0;color:#374151;font-size:16px;line-height:1.6">Tu crédito presenta un atraso importante. Te exhortamos a regularizar tu situación lo antes posible para evitar consecuencias adicionales.</p>',
          details: detailRows(['Folio', '{{application.folio}}'], ['Monto Vencido', '{{loan.overdue_amount}}'], ['Días de Atraso', '{{loan.days_overdue}}'], ['Recargos', '{{loan.late_fees}}']),
          detailsTitle: 'Situación del Crédito',
          detailsTint: 'red',
        }),
      },
      {
        name: 'Mora - SMS',
        event: 'loan.default',
        channel: 'SMS',
        is_active: true,
        priority: 1,
        subject: null,
        body: '{{tenant.name}}: Tu crédito {{application.folio}} tiene atraso de {{loan.days_overdue}} días. Contáctanos para regularizarte.',
        html_body: null,
      },
      {
        name: 'Mora - In App',
        event: 'loan.default',
        channel: 'IN_APP',
        is_active: true,
        priority: 1,
        subject: 'Crédito en mora',
        body: 'Tu crédito {{application.folio}} tiene atraso de {{loan.days_overdue}} días. Monto vencido: {{loan.overdue_amount}}.',
        html_body: null,
      },
    ]

    // Si es reemplazo, eliminar existentes primero
    if (mode === 'replace') {
      const existing = templates.value || []
      for (const t of existing) {
        await notificationTemplatesApi.delete(t.id)
      }
    }

    // Crear todas las plantillas sugeridas
    for (const template of suggestedTemplates) {
      await notificationTemplatesApi.create(template)
    }

    await loadTemplates()
    showSuggestedConfirm.value = false
    const msg = mode === 'replace'
      ? `Se reemplazaron las plantillas anteriores y se crearon ${suggestedTemplates.length} nuevas`
      : `Se crearon ${suggestedTemplates.length} plantillas profesionales exitosamente`
    toast.success(msg)
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Error al crear plantillas sugeridas')
    console.error('Error creating suggested templates:', err)
  } finally {
    creatingSuggested.value = false
  }
}

// Delete template
const showDeleteConfirm = ref(false)
const deleteTarget = ref<NotificationTemplate | null>(null)
const deleting = ref(false)

const openDeleteConfirm = (template: NotificationTemplate) => {
  deleteTarget.value = template
  showDeleteConfirm.value = true
}

const confirmDelete = async () => {
  if (!deleteTarget.value) return
  deleting.value = true
  try {
    await notificationTemplatesApi.delete(deleteTarget.value.id)
    showDeleteConfirm.value = false
    await loadTemplates()
    toast.success('Plantilla eliminada exitosamente')
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Error al eliminar plantilla')
  } finally {
    deleting.value = false
  }
}

// Toggle active status
const toggleActive = async (template: NotificationTemplate) => {
  try {
    await notificationTemplatesApi.update(template.id, {
      is_active: !template.is_active,
    })
    await loadTemplates()
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Error al actualizar plantilla')
  }
}

// Get channel badge color
const getChannelColor = (channel: string) => {
  const colors: Record<string, string> = {
    SMS: 'bg-blue-100 text-blue-800',
    WHATSAPP: 'bg-green-100 text-green-800',
    EMAIL: 'bg-purple-100 text-purple-800',
    IN_APP: 'bg-gray-100 text-gray-800',
  }
  return colors[channel] || 'bg-gray-100 text-gray-800'
}

// Get priority color
const getPriorityColor = (priority: number) => {
  if (priority <= 3) return 'text-red-600'
  if (priority <= 7) return 'text-yellow-600'
  return 'text-gray-600'
}

// Sample data for preview rendering
const sampleData: Record<string, any> = {
  'tenant.name': 'Lendus Demo',
  'tenant.slug': 'demo',
  'tenant.support_email': 'soporte@lendus.mx',
  'tenant.support_phone': '555-1234-567',
  'user.first_name': 'Juan',
  'user.last_name': 'Pérez',
  'user.name': 'Juan Pérez García',
  'user.email': 'juan.perez@example.com',
  'applicant.first_name': 'Juan',
  'applicant.last_name': 'Pérez',
  'applicant.name': 'Juan Pérez García',
  'applicant.email': 'juan.perez@example.com',
  'applicant.phone': '5551234567',
  'application.folio': 'APP-2024-001',
  'application.id': 'APP-2024-001',
  'application.amount': '$50,000.00',
  'application.product_name': 'Crédito Simple',
  'application.status': 'En Revisión',
  'currency application.amount': '$50,000.00',
  'otp.code': '123456',
  'otp.expires_in': '10 minutos',
  'analyst.name': 'Ana Martínez',
  'analyst.email': 'ana.martinez@lendus.mx',
  'staff.first_name': 'Ana',
  'staff.last_name': 'Martínez',
  'document.type': 'INE',
  'document.type_label': 'Identificación Oficial (INE)',
  'document.status': 'Aprobado',
  'rejection.reason': 'No cumple con los requisitos mínimos',
  'corrections.list': '- Actualizar comprobante de ingresos\n- Subir INE actualizada',
  // Contraoferta
  'counter_offer.amount': '$35,000.00',
  'counter_offer.term_months': '18',
  'counter_offer.monthly_payment': '$2,150.00',
  'counter_offer.interest_rate': '24%',
  'counter_offer.reason': 'Monto ajustado por capacidad de pago',
  // Cancelación
  'cancellation.reason': 'Solicitud duplicada',
  // Sincronización
  'sync.system': 'SAP Business One',
  'sync.external_id': 'SAP-2024-00123',
  // Cuenta bancaria
  'bank_account.bank_name': 'BBVA México',
  'bank_account.masked_clabe': '0121**********5678',
  'bank_account.holder_name': 'Juan Pérez García',
  // Préstamo
  'loan.disbursed_amount': '$50,000.00',
  'loan.disbursement_date': '15 de febrero de 2026',
  'loan.bank_account': 'BBVA - ****5678',
  'loan.reference': 'DEP-2026-001234',
  'loan.total_paid': '$58,750.00',
  'loan.completion_date': '15 de febrero de 2028',
  'loan.overdue_amount': '$4,300.00',
  'loan.days_overdue': '45',
  'loan.late_fees': '$650.00',
  // Pagos
  'payment.amount': '$2,150.00',
  'payment.date': '15 de febrero de 2026',
  'payment.due_date': '1 de marzo de 2026',
  'payment.method': 'Transferencia SPEI',
  'payment.reference': 'PAG-2026-000456',
  'payment.remaining_balance': '$42,500.00',
  'payment.payment_number': '3',
  'payment.total_payments': '24',
  'payment.days_overdue': '15',
  'payment.late_fee': '$215.00',
}

// Render preview with sample data
const renderPreview = (text: string): string => {
  let rendered = text

  // Replace variables with sample data
  Object.entries(sampleData).forEach(([key, value]) => {
    const regex = new RegExp(`{{\\s*${key.replace('.', '\\.')}\\s*}}`, 'g')
    rendered = rendered.replace(regex, String(value))
  })

  // Remove any remaining unmatched variables for cleaner preview
  // rendered = rendered.replace(/\{\{[^}]+\}\}/g, '[variable]')

  return rendered
}

// Strip HTML tags for text preview while preserving paragraph structure
const stripHtml = (html: string): string => {
  if (!html) return ''

  // Replace block-level elements with newlines BEFORE parsing
  let processed = html
    // Add double newlines for block elements
    .replace(/<\/(p|div|h[1-6]|li|tr|br)>/gi, '\n\n')
    .replace(/<br\s*\/?>/gi, '\n')
    .replace(/<\/li>/gi, '\n')
    // Add newlines for table rows
    .replace(/<tr[^>]*>/gi, '\n')

  // Create a temporary div to parse HTML and extract text
  const tmp = document.createElement('div')
  tmp.innerHTML = processed

  // Get text content
  let text = tmp.textContent || tmp.innerText || ''

  // Clean up excessive whitespace while preserving paragraph breaks
  text = text
    .split('\n') // Split by lines
    .map(line => line.trim()) // Trim each line
    .filter(line => line.length > 0) // Remove empty lines
    .join('\n\n') // Join with double newlines for paragraph separation
    .trim()

  return text
}

onMounted(() => {
  loadTemplates()
  loadConfig()
})
</script>

<template>
  <div class="min-h-screen bg-gradient-to-br from-gray-50 to-gray-100 p-6">
    <!-- Header -->
    <div class="mb-8">
      <div class="flex items-center justify-between mb-6">
        <div>
          <h1 class="text-3xl font-bold text-gray-900 mb-2">Plantillas de Notificación</h1>
          <p class="text-gray-600">
            Gestiona plantillas profesionales multi-canal para tus notificaciones
          </p>
        </div>
        <div class="flex gap-3">
          <button
            class="px-5 py-2.5 bg-gradient-to-r from-purple-600 to-purple-700 text-white rounded-xl hover:from-purple-700 hover:to-purple-800 transition-all flex items-center gap-2 shadow-lg shadow-purple-500/30"
            @click="showSuggestedConfirm = true"
            :disabled="creatingSuggested"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
            </svg>
            {{ creatingSuggested ? 'Creando...' : 'Crear Plantillas Sugeridas' }}
          </button>
          <button
            class="px-5 py-2.5 bg-gradient-to-r from-indigo-600 to-indigo-700 text-white rounded-xl hover:from-indigo-700 hover:to-indigo-800 transition-all flex items-center gap-2 shadow-lg shadow-indigo-500/30"
            @click="router.push('/admin/notificaciones/nueva')"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M12 4v16m8-8H4"
              />
            </svg>
            Nueva Plantilla
          </button>
        </div>
      </div>

      <!-- Compact Stats -->
      <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-3 mb-6">
        <div class="flex items-center gap-6">
          <!-- Total -->
          <div class="flex items-center gap-2">
            <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <span class="text-sm text-gray-600">Total:</span>
            <span class="text-lg font-bold text-gray-900">{{ stats.total }}</span>
          </div>

          <!-- Active -->
          <div class="flex items-center gap-2">
            <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span class="text-sm text-gray-600">Activas:</span>
            <span class="text-lg font-bold text-green-600">{{ stats.active }}</span>
          </div>

          <!-- Inactive -->
          <div class="flex items-center gap-2">
            <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
            </svg>
            <span class="text-sm text-gray-600">Inactivas:</span>
            <span class="text-lg font-bold text-gray-900">{{ stats.inactive }}</span>
          </div>

          <!-- Channels -->
          <div class="flex items-center gap-2 ml-auto">
            <svg class="w-4 h-4 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
            </svg>
            <span class="text-sm text-gray-600">Por Canal:</span>
            <div class="flex gap-1">
              <span
                v-for="(count, channel) in stats.byChannel"
                :key="channel"
                class="text-xs font-bold px-2 py-0.5 rounded"
                :class="getChannelColor(channel as string)"
              >
                {{ channel }}: {{ count }}
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- Filters - Compact Single Row -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-8">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
          <!-- Search -->
          <div class="md:col-span-1">
            <div class="relative">
              <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
              </div>
              <input
                v-model="searchQuery"
                type="text"
                placeholder="Buscar..."
                class="w-full pl-10 pr-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
              />
            </div>
          </div>

          <!-- Event filter -->
          <div>
            <SearchableSelect
              v-model="filterEvent"
              :options="eventOptions"
              placeholder="Todos los eventos"
            />
          </div>

          <!-- Channel filter -->
          <div>
            <SearchableSelect
              v-model="filterChannel"
              :options="channelOptions"
              placeholder="Todos los canales"
            />
          </div>

          <!-- Status filter -->
          <div>
            <SearchableSelect
              v-model="filterStatus"
              :options="statusOptions"
              placeholder="Todos los estados"
            />
          </div>
        </div>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="text-center py-12">
      <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600"></div>
      <p class="mt-2 text-sm text-gray-600">Cargando plantillas...</p>
    </div>

    <!-- Error -->
    <div
      v-else-if="error"
      class="bg-red-50 border border-red-200 rounded-lg p-4 text-red-800 text-sm"
    >
      {{ error }}
    </div>

    <!-- Templates grouped by event -->
    <div v-else-if="Object.keys(templatesByEvent).length > 0" class="space-y-6">
      <div v-for="(eventTemplates, event) in templatesByEvent" :key="event">
        <!-- Event Header - Collapsible -->
        <div class="mb-4">
          <button
            @click="eventTemplates._collapsed = !eventTemplates._collapsed"
            class="w-full flex items-center gap-3 hover:bg-gray-50 rounded-lg p-3 transition-colors group"
          >
            <!-- Collapse indicator -->
            <svg
              class="w-5 h-5 text-gray-400 group-hover:text-indigo-600 transition-transform"
              :class="eventTemplates._collapsed ? '' : 'rotate-90'"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
            </svg>

            <div class="w-1 h-6 bg-gradient-to-b from-indigo-500 to-purple-600 rounded-full"></div>
            <h2 class="text-xl font-bold text-gray-900 flex-1 text-left">
              {{ eventTemplates[0].event_label }}
            </h2>
            <span class="px-2 py-0.5 bg-gray-100 text-gray-700 text-xs font-medium rounded-full">
              {{ eventTemplates.length }}
            </span>
          </button>
        </div>

        <!-- Template Cards Grid - 3 columns (Collapsible) -->
        <div
          v-show="!eventTemplates._collapsed"
          class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6"
        >
          <div
            v-for="template in eventTemplates"
            :key="template.id"
            class="bg-white rounded-lg shadow-sm border border-gray-200 hover:shadow-md hover:border-indigo-300 transition-all overflow-hidden"
          >
            <!-- Card Header - Compact -->
            <div class="bg-gray-50 border-b border-gray-200 px-4 py-3">
              <div class="flex items-start justify-between mb-2">
                <h3 class="font-semibold text-gray-900 text-sm line-clamp-2 flex-1 pr-2">{{ template.name }}</h3>
                <div class="flex items-center gap-1 flex-shrink-0">
                  <button
                    class="p-1.5 text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition-all"
                    title="Enviar prueba"
                    @click="openSendTest(template)"
                  >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                    </svg>
                  </button>
                  <button
                    class="p-1.5 text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition-all"
                    title="Editar"
                    @click="router.push(`/admin/notificaciones/${template.id}/editar`)"
                  >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                  </button>
                  <button
                    class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-all"
                    title="Eliminar"
                    @click="openDeleteConfirm(template)"
                  >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                  </button>
                </div>
              </div>

              <div class="flex items-center gap-2 flex-wrap">
                <span class="px-2 py-0.5 text-xs font-bold rounded" :class="getChannelColor(template.channel)">
                  {{ template.channel_label }}
                </span>
                <span
                  class="px-2 py-0.5 text-xs font-bold rounded"
                  :class="template.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600'"
                  @click="toggleActive(template)"
                  role="button"
                  :title="template.is_active ? 'Click para desactivar' : 'Click para activar'"
                >
                  {{ template.is_active ? '✓' : '○' }}
                </span>
                <span class="text-xs" :class="getPriorityColor(template.priority)">
                  P{{ template.priority }}
                </span>
              </div>
            </div>

            <!-- Card Content - Collapsed by default -->
            <div class="p-4">
              <!-- Subject/Body Preview (text only, no formatting) -->
              <div v-if="template.subject" class="mb-3">
                <div class="text-xs text-gray-500 font-semibold mb-1.5 uppercase tracking-wide">Asunto</div>
                <div class="text-sm text-gray-900 font-medium line-clamp-2 leading-relaxed">
                  {{ stripHtml(renderPreview(template.subject)) }}
                </div>
              </div>

              <div class="mb-3">
                <div class="text-xs text-gray-500 font-semibold mb-1.5 uppercase tracking-wide">
                  Contenido
                </div>
                <div class="text-sm text-gray-700 leading-relaxed whitespace-pre-line line-clamp-6">
                  {{ stripHtml(renderPreview(template.body)) }}
                </div>
              </div>

              <!-- Expand HTML Preview Button (only for emails with HTML) -->
              <button
                v-if="template.channel === 'EMAIL' && template.html_body"
                @click="template._htmlExpanded = !template._htmlExpanded"
                class="w-full py-2 px-3 text-xs font-medium text-indigo-600 bg-indigo-50 hover:bg-indigo-100 rounded-lg transition-colors flex items-center justify-center gap-2"
              >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                </svg>
                {{ template._htmlExpanded ? 'Ocultar' : 'Ver' }} vista previa HTML
              </button>

              <!-- Expanded HTML Preview -->
              <div v-if="template._htmlExpanded && template.channel === 'EMAIL' && template.html_body" class="mt-3 bg-gray-50 rounded-lg p-3 border border-gray-200">
                <div class="bg-white rounded overflow-hidden border border-gray-300" style="height: 200px;">
                  <iframe
                    :srcdoc="renderPreview(template.html_body)"
                    class="w-full h-full border-0"
                    style="transform: scale(0.5); transform-origin: top left; width: 200%; height: 200%;"
                    sandbox="allow-same-origin"
                  />
                </div>
              </div>

              <!-- Footer Info -->
              <div class="mt-3 pt-3 border-t border-gray-200 flex items-center justify-between text-xs text-gray-500">
                <span v-if="template.created_by" class="truncate">{{ template.created_by.name }}</span>
                <span>{{ new Date(template.updated_at).toLocaleDateString('es-MX', { day: '2-digit', month: '2-digit' }) }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Empty state -->
    <div v-else class="text-center py-12">
      <svg
        class="mx-auto h-12 w-12 text-gray-400"
        fill="none"
        stroke="currentColor"
        viewBox="0 0 24 24"
      >
        <path
          stroke-linecap="round"
          stroke-linejoin="round"
          stroke-width="2"
          d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"
        />
      </svg>
      <h3 class="mt-2 text-sm font-medium text-gray-900">No hay plantillas</h3>
      <p class="mt-1 text-sm text-gray-500">
        Comienza creando tu primera plantilla de notificación.
      </p>
      <div class="mt-6">
        <button
          class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
          @click="router.push('/admin/notificaciones/nueva')"
        >
          <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M12 4v16m8-8H4"
            />
          </svg>
          Nueva Plantilla
        </button>
      </div>
    </div>

    <!-- Send Test Modal -->
    <SendTestModal
      v-model:show="showSendTestModal"
      :template="sendTestTemplate"
    />

    <ConfirmModal
      :show="showDeleteConfirm"
      title="Eliminar Plantilla"
      :subtitle="deleteTarget?.name"
      message="Esta acción no se puede deshacer."
      icon="trash"
      icon-color="red"
      confirm-text="Eliminar"
      confirm-color="red"
      :loading="deleting"
      @update:show="showDeleteConfirm = $event"
      @confirm="confirmDelete"
      @cancel="showDeleteConfirm = false"
    />

    <ConfirmModal
      :show="showSuggestedConfirm"
      title="Crear Plantillas Sugeridas"
      icon="info"
      icon-color="blue"
      select-label="¿Qué hacer con las plantillas existentes?"
      :select-options="suggestedModeOptions"
      :select-required="true"
      select-placeholder="Selecciona una opción"
      confirm-text="Crear Plantillas"
      confirm-color="blue"
      :loading="creatingSuggested"
      @update:show="showSuggestedConfirm = $event"
      @confirm="onSuggestedConfirm"
      @cancel="showSuggestedConfirm = false"
    />
  </div>
</template>
