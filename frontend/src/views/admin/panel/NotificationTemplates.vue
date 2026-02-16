<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import {
  notificationTemplatesApi,
  type NotificationTemplate,
  type TemplateConfig,
} from '@/services/notificationTemplates'

const router = useRouter()

const templates = ref<NotificationTemplate[]>([])
const config = ref<TemplateConfig | null>(null)
const loading = ref(false)
const error = ref<string | null>(null)

// Filters
const filterEvent = ref<string>('')
const filterChannel = ref<string>('')
const filterStatus = ref<string>('')
const searchQuery = ref('')

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

const createSuggestedTemplates = async () => {
  if (!confirm('¿Crear plantillas sugeridas profesionales? Se crearán 8 plantillas de ejemplo que podrás editar después.')) {
    return
  }

  creatingSuggested.value = true
  try {
    const suggestedTemplates = [
      // Solicitud Recibida
      {
        name: 'Solicitud Recibida - Email Elegante',
        event: 'application.submitted',
        channel: 'EMAIL',
        is_active: true,
        priority: 5,
        subject: '¡Solicitud Recibida! - {{application.folio}}',
        body: 'Hola {{applicant.first_name}}, hemos recibido tu solicitud.',
        html_body: `<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>{{tenant.name}}</title></head>
<body style="margin:0;padding:0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background-color:#f5f5f5">
<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f5f5f5;padding:40px 0"><tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" border="0" style="background-color:#fff;border-radius:16px;overflow:hidden;box-shadow:0 4px 6px rgba(0,0,0,0.1)">
<tr><td style="background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);padding:40px 30px;text-align:center">
<h1 style="margin:0;color:#fff;font-size:28px;font-weight:700">{{tenant.name}}</h1>
</td></tr>
<tr><td style="padding:40px 30px">
<h2 style="margin:0 0 20px 0;color:#1a202c;font-size:24px;font-weight:600">¡Hola {{applicant.first_name}}!</h2>
<p style="margin:0 0 20px 0;color:#4a5568;font-size:16px;line-height:1.6">Tu solicitud ha sido recibida exitosamente. Nuestro equipo está revisando tu información.</p>
<div style="background:linear-gradient(135deg,#667eea15 0%,#764ba215 100%);border-radius:12px;padding:24px;margin:30px 0">
<table width="100%" cellpadding="0" cellspacing="0" border="0">
<tr><td style="padding:8px 0;color:#4a5568;font-size:14px;font-weight:600">Folio:</td>
<td align="right" style="padding:8px 0;color:#1a202c;font-size:14px;font-weight:700">{{application.folio}}</td></tr>
<tr><td style="padding:8px 0;color:#4a5568;font-size:14px;font-weight:600">Monto:</td>
<td align="right" style="padding:8px 0;color:#1a202c;font-size:14px;font-weight:700">{{application.amount}}</td></tr>
</table>
</div>
<p style="margin:30px 0 0 0;color:#718096;font-size:14px;line-height:1.6">Si tienes alguna pregunta, no dudes en contactarnos.</p>
</td></tr>
<tr><td style="background-color:#f7fafc;padding:30px;text-align:center;border-top:1px solid #e2e8f0">
<p style="margin:0 0 10px 0;color:#718096;font-size:14px">{{tenant.name}}</p>
<p style="margin:0;color:#a0aec0;font-size:12px">Este correo fue enviado automáticamente</p>
</td></tr>
</table>
</td></tr></table>
</body>
</html>`,
      },

      // Código OTP
      {
        name: 'Código de Verificación - Email',
        event: 'otp.sent',
        channel: 'EMAIL',
        is_active: true,
        priority: 1,
        subject: 'Tu código de verificación: {{otp.code}}',
        body: 'Tu código: {{otp.code}}',
        html_body: `<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"></head>
<body style="margin:0;padding:0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background-color:#f3f4f6">
<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f3f4f6;padding:40px 20px"><tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" border="0" style="background-color:#fff;border-radius:16px;overflow:hidden;box-shadow:0 4px 6px rgba(0,0,0,0.1)">
<tr><td style="padding:40px 30px;text-align:center;background-color:#3b82f6">
<h1 style="margin:0;color:#fff;font-size:24px;font-weight:700">{{tenant.name}}</h1>
</td></tr>
<tr><td style="padding:40px 30px;text-align:center">
<div style="width:80px;height:80px;background-color:#dbeafe;border-radius:50%;margin:0 auto 24px;line-height:80px;font-size:40px">🔐</div>
<h2 style="margin:0 0 16px 0;color:#1f2937;font-size:28px;font-weight:700">Código de Verificación</h2>
<p style="margin:0 0 32px 0;color:#6b7280;font-size:16px">Usa el siguiente código para verificar tu identidad:</p>
<div style="background:linear-gradient(135deg,#3b82f6 0%,#2563eb 100%);padding:24px 48px;border-radius:12px;margin:0 auto 24px;display:inline-block">
<span style="color:#fff;font-size:36px;font-weight:700;letter-spacing:8px;font-family:'Courier New',monospace">{{otp.code}}</span>
</div>
<p style="margin:0;color:#6b7280;font-size:14px">Este código expirará en <strong>{{otp.expires_in}}</strong></p>
</td></tr>
<tr><td style="background-color:#f9fafb;padding:24px 30px;text-align:center;border-top:1px solid #e5e7eb">
<p style="margin:0;color:#9ca3af;font-size:12px">{{tenant.name}} • Mensaje automático</p>
</td></tr>
</table>
</td></tr></table>
</body>
</html>`,
      },

      // Solicitud Aprobada
      {
        name: 'Solicitud Aprobada - Email',
        event: 'application.approved',
        channel: 'EMAIL',
        is_active: true,
        priority: 3,
        subject: '¡Felicidades! Tu solicitud ha sido aprobada',
        body: 'Tu solicitud {{application.folio}} ha sido aprobada',
        html_body: `<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"></head>
<body style="margin:0;padding:0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:linear-gradient(135deg,#f5f7fa 0%,#c3cfe2 100%)">
<table width="100%" cellpadding="0" cellspacing="0" border="0" style="padding:60px 20px"><tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" border="0" style="background-color:#fff;border-radius:20px;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,0.15)">
<tr><td align="center" style="padding:50px 30px 30px 30px">
<div style="width:80px;height:80px;background:linear-gradient(135deg,#10b981 0%,#059669 100%);border-radius:50%;display:inline-flex;align-items:center;justify-content:center">
<span style="color:#fff;font-size:40px">✓</span>
</div>
</td></tr>
<tr><td align="center" style="padding:0 30px 20px 30px">
<h1 style="margin:0;color:#1e293b;font-size:28px;font-weight:700">¡Solicitud Aprobada!</h1>
</td></tr>
<tr><td style="padding:0 30px 30px 30px">
<p style="margin:0 0 30px 0;color:#64748b;font-size:16px;line-height:1.6;text-align:center">
Hola <strong style="color:#1e293b">{{applicant.first_name}}</strong>, tu solicitud ha sido aprobada exitosamente.
</p>
<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f8fafc;border-radius:12px">
<tr><td style="padding:24px">
<p style="margin:0;color:#64748b;font-size:13px;text-transform:uppercase;letter-spacing:0.5px;font-weight:600">Folio</p>
<p style="margin:4px 0 0 0;color:#1e293b;font-size:20px;font-weight:700">{{application.folio}}</p>
<p style="margin:16px 0 0 0;padding-top:12px;border-top:1px solid #e2e8f0;color:#64748b;font-size:13px;text-transform:uppercase;letter-spacing:0.5px;font-weight:600">Monto Aprobado</p>
<p style="margin:4px 0 0 0;color:#10b981;font-size:24px;font-weight:700">{{application.amount}}</p>
</td></tr>
</table>
</td></tr>
<tr><td style="background-color:#f8fafc;padding:30px;text-align:center">
<p style="margin:0 0 8px 0;color:#1e293b;font-size:14px;font-weight:600">{{tenant.name}}</p>
<p style="margin:0;color:#94a3b8;font-size:12px">Mensaje automático</p>
</td></tr>
</table>
</td></tr></table>
</body>
</html>`,
      },

      // WhatsApp OTP
      {
        name: 'Código OTP - WhatsApp',
        event: 'otp.sent',
        channel: 'WHATSAPP',
        is_active: true,
        priority: 1,
        subject: null,
        body: '*{{tenant.name}}*\n\nTu código de verificación es:\n\n*{{otp.code}}*\n\nExpira en {{otp.expires_in}}',
        html_body: null,
      },

      // SMS OTP
      {
        name: 'Código OTP - SMS',
        event: 'otp.sent',
        channel: 'SMS',
        is_active: true,
        priority: 1,
        subject: null,
        body: '{{tenant.name}}: Tu código es {{otp.code}}. Expira en {{otp.expires_in}}',
        html_body: null,
      },

      // Notificación In-App
      {
        name: 'Solicitud en Revisión - In App',
        event: 'application.in_review',
        channel: 'IN_APP',
        is_active: true,
        priority: 5,
        subject: 'Tu solicitud está en revisión',
        body: 'Estamos revisando tu solicitud {{application.folio}}. Te notificaremos cuando tengamos novedades.',
        html_body: null,
      },

      // Documentos Pendientes
      {
        name: 'Documentos Pendientes - Email',
        event: 'documents.pending',
        channel: 'EMAIL',
        is_active: true,
        priority: 4,
        subject: 'Documentos pendientes - {{application.folio}}',
        body: 'Necesitamos documentación adicional',
        html_body: `<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"></head>
<body style="margin:0;padding:0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background-color:#ffffff">
<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#fff;padding:40px 20px"><tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" border="0">
<tr><td style="padding:0 0 40px 0;border-bottom:2px solid #000">
<h1 style="margin:0;color:#000;font-size:24px;font-weight:700">{{tenant.name}}</h1>
</td></tr>
<tr><td style="padding:40px 0">
<h2 style="margin:0 0 24px 0;color:#000;font-size:32px;font-weight:700">Hola {{applicant.first_name}}</h2>
<p style="margin:0 0 20px 0;color:#333;font-size:16px;line-height:1.7">
Necesitamos documentación adicional para continuar con tu solicitud <strong>{{application.folio}}</strong>.
</p>
<table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:40px 0;border:2px solid #000;border-radius:4px">
<tr><td style="padding:30px">
<p style="margin:0 0 16px 0;color:#000;font-size:18px;font-weight:700">Documentos Requeridos:</p>
<ul style="margin:0;padding-left:20px;color:#333;font-size:15px;line-height:1.8">
<li>Identificación oficial vigente</li>
<li>Comprobante de domicilio reciente</li>
<li>Comprobante de ingresos</li>
</ul>
</td></tr>
</table>
<table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:40px 0">
<tr><td>
<a href="#" style="display:inline-block;background-color:#000;color:#fff;text-decoration:none;padding:16px 32px;font-weight:600;font-size:16px;text-transform:uppercase;letter-spacing:0.5px">Subir Documentos</a>
</td></tr>
</table>
</td></tr>
<tr><td style="padding:40px 0 0 0;border-top:1px solid #e5e5e5">
<p style="margin:0;color:#999;font-size:12px">{{tenant.name}}<br>Correo automático</p>
</td></tr>
</table>
</td></tr></table>
</body>
</html>`,
      },

      // Solicitud Rechazada
      {
        name: 'Solicitud Rechazada - Email',
        event: 'application.rejected',
        channel: 'EMAIL',
        is_active: true,
        priority: 3,
        subject: 'Actualización de tu solicitud',
        body: 'Tu solicitud {{application.folio}} no pudo ser aprobada en este momento',
        html_body: `<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"></head>
<body style="margin:0;padding:0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background-color:#f5f5f5">
<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f5f5f5;padding:40px 0"><tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" border="0" style="background-color:#fff;border-radius:16px;overflow:hidden;box-shadow:0 4px 6px rgba(0,0,0,0.1)">
<tr><td style="background:linear-gradient(135deg,#6b7280 0%,#4b5563 100%);padding:40px 30px;text-align:center">
<h1 style="margin:0;color:#fff;font-size:28px;font-weight:700">{{tenant.name}}</h1>
</td></tr>
<tr><td style="padding:40px 30px">
<h2 style="margin:0 0 20px 0;color:#1a202c;font-size:24px;font-weight:600">Hola {{applicant.first_name}}</h2>
<p style="margin:0 0 20px 0;color:#4a5568;font-size:16px;line-height:1.6">
Lamentamos informarte que tu solicitud <strong>{{application.folio}}</strong> no pudo ser aprobada en este momento.
</p>
<p style="margin:20px 0;color:#4a5568;font-size:16px;line-height:1.6">
Esto no significa el fin del camino. Puedes volver a aplicar en el futuro o contactarnos para más información.
</p>
<p style="margin:30px 0 0 0;color:#718096;font-size:14px;line-height:1.6">
Si tienes preguntas, estamos aquí para ayudarte.
</p>
</td></tr>
<tr><td style="background-color:#f7fafc;padding:30px;text-align:center;border-top:1px solid #e2e8f0">
<p style="margin:0 0 10px 0;color:#718096;font-size:14px">{{tenant.name}}</p>
<p style="margin:0;color:#a0aec0;font-size:12px">Este correo fue enviado automáticamente</p>
</td></tr>
</table>
</td></tr></table>
</body>
</html>`,
      },
    ]

    // Create all templates
    for (const template of suggestedTemplates) {
      await notificationTemplatesApi.create(template)
    }

    await loadTemplates()
    alert(`✅ Se crearon ${suggestedTemplates.length} plantillas profesionales exitosamente`)
  } catch (err: any) {
    alert(err.response?.data?.message || 'Error al crear plantillas sugeridas')
    console.error('Error creating suggested templates:', err)
  } finally {
    creatingSuggested.value = false
  }
}

// Delete template
const deleteTemplate = async (template: NotificationTemplate) => {
  if (
    !confirm(
      `¿Estás seguro de eliminar la plantilla "${template.name}"? Esta acción no se puede deshacer.`
    )
  ) {
    return
  }

  try {
    await notificationTemplatesApi.delete(template.id)
    await loadTemplates()
  } catch (err: any) {
    alert(err.response?.data?.message || 'Error al eliminar plantilla')
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
    alert(err.response?.data?.message || 'Error al actualizar plantilla')
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
            @click="createSuggestedTemplates"
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
            <select
              v-model="filterEvent"
              class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent appearance-none bg-white"
              @change="loadTemplates"
            >
              <option value="">Todos los eventos</option>
              <option v-for="event in config?.events" :key="event.value" :value="event.value">
                {{ event.label }}
              </option>
            </select>
          </div>

          <!-- Channel filter -->
          <div>
            <select
              v-model="filterChannel"
              class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent appearance-none bg-white"
              @change="loadTemplates"
            >
              <option value="">Todos los canales</option>
              <option
                v-for="channel in config?.channels"
                :key="channel.value"
                :value="channel.value"
              >
                {{ channel.label }}
              </option>
            </select>
          </div>

          <!-- Status filter -->
          <div>
            <select
              v-model="filterStatus"
              class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent appearance-none bg-white"
              @change="loadTemplates"
            >
              <option value="">Todos los estados</option>
              <option value="active">Activas</option>
              <option value="inactive">Inactivas</option>
            </select>
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
                    @click="deleteTemplate(template)"
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
  </div>
</template>
