// Plantillas de galería para el editor HTML de notificaciones.
// Usan el mismo diseño de 6 secciones que emailHtmlHelper.

import { emailHtml, detailRows } from './emailHtmlHelper'

export interface EmailTemplate {
  id: string
  name: string
  description: string
  thumbnail: string
  html: string
}

export const emailTemplates: EmailTemplate[] = [
  {
    id: 'general-info',
    name: 'Informativa General',
    description: 'Icono azul, saludo, contenido y botón de acción',
    thumbnail: 'i',
    html: emailHtml({
      gradient: '#3b82f6 0%,#2563eb 100%',
      heading: 'Título de tu Notificación',
      icon: 'inbox',
      greeting: 'Hola <strong>{{applicant.first_name}}</strong>,',
      body: '<p style="margin:0;color:#374151;font-size:16px;line-height:1.6">Escribe aquí el contenido principal de tu notificación. Puedes usar variables como <strong>{{application.folio}}</strong> para personalizar el mensaje.</p>',
      details: detailRows(['Folio', '{{application.folio}}'], ['Monto', '{{application.amount}}']),
      detailsTitle: 'Información',
      detailsTint: 'blue',
      ctaText: 'Ver Mi Solicitud',
      ctaUrl: '{{dashboard_url}}',
    }),
  },

  {
    id: 'success-approval',
    name: 'Aprobación / Éxito',
    description: 'Icono verde de éxito con detalles y CTA',
    thumbnail: '✓',
    html: emailHtml({
      gradient: '#10b981 0%,#059669 100%',
      heading: '¡Solicitud Aprobada!',
      icon: 'celebrate',
      greeting: 'Hola <strong>{{applicant.first_name}}</strong>,',
      body: '<p style="margin:0;color:#374151;font-size:16px;line-height:1.6">Nos complace informarte que tu solicitud ha sido aprobada exitosamente.</p>',
      details: detailRows(['Folio', '{{application.folio}}'], ['Monto Aprobado', '{{application.amount}}'], ['Producto', '{{application.product_name}}']),
      detailsTitle: 'Detalles del Crédito',
      detailsTint: 'green',
      ctaText: 'Ver Mi Solicitud',
      ctaUrl: '{{dashboard_url}}',
    }),
  },

  {
    id: 'warning-action',
    name: 'Acción Requerida',
    description: 'Icono ámbar de advertencia con llamada a la acción',
    thumbnail: '!',
    html: emailHtml({
      gradient: '#f59e0b 0%,#d97706 100%',
      heading: 'Acción Requerida',
      icon: 'warning',
      greeting: 'Hola <strong>{{applicant.first_name}}</strong>,',
      body: '<p style="margin:0;color:#374151;font-size:16px;line-height:1.6">Necesitamos que realices una acción para continuar con el proceso de tu solicitud.</p>',
      details: detailRows(['Folio', '{{application.folio}}']),
      detailsTint: 'amber',
      ctaText: 'Completar Acción',
      ctaUrl: '{{dashboard_url}}',
    }),
  },

  {
    id: 'simple-notice',
    name: 'Aviso Simple',
    description: 'Diseño limpio sin detalles, solo contenido y despedida',
    thumbnail: '✦',
    html: emailHtml({
      gradient: '#667eea 0%,#764ba2 100%',
      heading: 'Aviso Importante',
      icon: 'edit',
      greeting: 'Hola <strong>{{applicant.first_name}}</strong>,',
      body: '<p style="margin:0;color:#374151;font-size:16px;line-height:1.6">Escribe aquí el contenido de tu aviso. Este diseño es ideal para mensajes simples que no requieren tarjeta de detalles.</p>',
    }),
  },
]
