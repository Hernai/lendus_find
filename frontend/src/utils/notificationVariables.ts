/**
 * Diccionario centralizado de variables disponibles para plantillas de notificación
 * Todas las plantillas tienen acceso a estas variables
 */

export interface VariableDefinition {
  key: string
  label: string
  description: string
  example: string
  category: 'tenant' | 'applicant' | 'application' | 'document' | 'reference' | 'otp' | 'user' | 'other'
}

export const notificationVariables: VariableDefinition[] = [
  // TENANT VARIABLES
  {
    key: 'tenant.name',
    label: 'Nombre del Tenant',
    description: 'Nombre de la empresa/organización',
    example: 'Lendus Demo',
    category: 'tenant',
  },
  {
    key: 'tenant.slug',
    label: 'Slug del Tenant',
    description: 'Identificador único del tenant',
    example: 'demo',
    category: 'tenant',
  },

  // APPLICANT VARIABLES
  {
    key: 'applicant.name',
    label: 'Nombre Completo del Solicitante',
    description: 'Nombre completo del solicitante',
    example: 'Juan Pérez García',
    category: 'applicant',
  },
  {
    key: 'applicant.first_name',
    label: 'Nombre del Solicitante',
    description: 'Primer nombre del solicitante',
    example: 'Juan',
    category: 'applicant',
  },
  {
    key: 'applicant.last_name',
    label: 'Apellido del Solicitante',
    description: 'Apellido paterno del solicitante',
    example: 'Pérez',
    category: 'applicant',
  },
  {
    key: 'applicant.email',
    label: 'Email del Solicitante',
    description: 'Correo electrónico del solicitante',
    example: 'juan.perez@example.com',
    category: 'applicant',
  },
  {
    key: 'applicant.phone',
    label: 'Teléfono del Solicitante',
    description: 'Número de teléfono del solicitante',
    example: '5512345678',
    category: 'applicant',
  },
  {
    key: 'applicant.rfc',
    label: 'RFC del Solicitante',
    description: 'RFC del solicitante',
    example: 'PEGJ850101ABC',
    category: 'applicant',
  },
  {
    key: 'applicant.curp',
    label: 'CURP del Solicitante',
    description: 'CURP del solicitante',
    example: 'PEGJ850101HDFRNN09',
    category: 'applicant',
  },

  // APPLICATION VARIABLES
  {
    key: 'application.folio',
    label: 'Folio de Solicitud',
    description: 'Número de folio único de la solicitud',
    example: 'APP-2024-001',
    category: 'application',
  },
  {
    key: 'application.amount',
    label: 'Monto Solicitado',
    description: 'Monto solicitado formateado',
    example: '$50,000.00',
    category: 'application',
  },
  {
    key: 'application.term_months',
    label: 'Plazo en Meses',
    description: 'Plazo solicitado en meses',
    example: '12',
    category: 'application',
  },
  {
    key: 'application.product_name',
    label: 'Producto',
    description: 'Nombre del producto de crédito',
    example: 'Crédito Simple',
    category: 'application',
  },
  {
    key: 'application.status',
    label: 'Estado de la Solicitud',
    description: 'Estado actual de la solicitud',
    example: 'EN_REVISION',
    category: 'application',
  },
  {
    key: 'application.status_label',
    label: 'Etiqueta del Estado',
    description: 'Etiqueta legible del estado',
    example: 'En Revisión',
    category: 'application',
  },
  {
    key: 'application.created_at',
    label: 'Fecha de Creación',
    description: 'Fecha de creación de la solicitud',
    example: '23/01/2024',
    category: 'application',
  },
  {
    key: 'application.updated_at',
    label: 'Fecha de Actualización',
    description: 'Última actualización de la solicitud',
    example: '23/01/2024',
    category: 'application',
  },

  // DOCUMENT VARIABLES
  {
    key: 'document.type',
    label: 'Tipo de Documento',
    description: 'Tipo de documento subido',
    example: 'INE_FRENTE',
    category: 'document',
  },
  {
    key: 'document.type_label',
    label: 'Etiqueta del Documento',
    description: 'Nombre legible del tipo de documento',
    example: 'INE (Frente)',
    category: 'document',
  },
  {
    key: 'document.status',
    label: 'Estado del Documento',
    description: 'Estado de validación del documento',
    example: 'APROBADO',
    category: 'document',
  },
  {
    key: 'document.uploaded_at',
    label: 'Fecha de Subida',
    description: 'Fecha en que se subió el documento',
    example: '23/01/2024',
    category: 'document',
  },

  // REFERENCE VARIABLES
  {
    key: 'reference.name',
    label: 'Nombre de la Referencia',
    description: 'Nombre completo de la referencia',
    example: 'María González',
    category: 'reference',
  },
  {
    key: 'reference.phone',
    label: 'Teléfono de la Referencia',
    description: 'Número de teléfono de la referencia',
    example: '5598765432',
    category: 'reference',
  },
  {
    key: 'reference.relationship',
    label: 'Parentesco/Relación',
    description: 'Relación con el solicitante',
    example: 'Hermana',
    category: 'reference',
  },

  // OTP VARIABLES
  {
    key: 'otp.code',
    label: 'Código OTP',
    description: 'Código de verificación temporal',
    example: '123456',
    category: 'otp',
  },
  {
    key: 'otp.expires_at',
    label: 'Expiración del OTP',
    description: 'Fecha/hora de expiración del código',
    example: '23/01/2024 14:30',
    category: 'otp',
  },

  // USER/STAFF VARIABLES
  {
    key: 'user.name',
    label: 'Nombre del Usuario',
    description: 'Nombre del usuario que realiza la acción',
    example: 'Ana Martínez',
    category: 'user',
  },
  {
    key: 'user.email',
    label: 'Email del Usuario',
    description: 'Correo del usuario que realiza la acción',
    example: 'ana.martinez@lendus.mx',
    category: 'user',
  },
  {
    key: 'user.role',
    label: 'Rol del Usuario',
    description: 'Rol del usuario en el sistema',
    example: 'ANALYST',
    category: 'user',
  },

  // OTHER VARIABLES
  {
    key: 'rejection_reason',
    label: 'Razón de Rechazo',
    description: 'Motivo del rechazo de la solicitud',
    example: 'No cumple con los requisitos de ingresos',
    category: 'other',
  },
  {
    key: 'notes',
    label: 'Notas',
    description: 'Notas adicionales',
    example: 'Se requiere documentación adicional',
    category: 'other',
  },
  {
    key: 'dashboard_url',
    label: 'URL del Dashboard',
    description: 'Link al dashboard del solicitante',
    example: 'https://app.lendus.mx/dashboard',
    category: 'other',
  },
  {
    key: 'login_url',
    label: 'URL de Login',
    description: 'Link a la página de inicio de sesión',
    example: 'https://app.lendus.mx/login',
    category: 'other',
  },
]

/**
 * Agrupa las variables por categoría
 */
export const variablesByCategory = notificationVariables.reduce(
  (acc, variable) => {
    if (!acc[variable.category]) {
      acc[variable.category] = []
    }
    acc[variable.category].push(variable)
    return acc
  },
  {} as Record<string, VariableDefinition[]>
)

/**
 * Obtiene una variable por su key
 */
export const getVariableByKey = (key: string): VariableDefinition | undefined => {
  return notificationVariables.find((v) => v.key === key)
}

/**
 * Formatea una variable para mostrar en el editor
 */
export const formatVariable = (key: string): string => {
  return `{{${key}}}`
}

/**
 * Etiquetas legibles para categorías
 */
export const categoryLabels: Record<string, string> = {
  tenant: 'Tenant',
  applicant: 'Solicitante',
  application: 'Solicitud',
  document: 'Documento',
  reference: 'Referencia',
  otp: 'Código OTP',
  user: 'Usuario/Staff',
  other: 'Otros',
}
