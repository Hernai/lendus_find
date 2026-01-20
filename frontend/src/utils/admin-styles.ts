/**
 * Standardized CSS class strings for admin panel components.
 * Use these constants to ensure consistent styling across all admin forms.
 */

// Input base styles (text inputs, email, number, etc.)
export const inputClass = 'w-full px-4 py-2.5 border border-gray-200 rounded-lg text-gray-900 placeholder-gray-400 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors'

// Input with error state
export const inputErrorClass = 'w-full px-4 py-2.5 border border-red-300 rounded-lg text-gray-900 placeholder-gray-400 focus:ring-2 focus:ring-red-500 focus:border-red-500 transition-colors'

// Select base styles
export const selectClass = 'w-full px-4 py-2.5 border border-gray-200 rounded-lg text-gray-900 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors'

// Select with error state
export const selectErrorClass = 'w-full px-4 py-2.5 border border-red-300 rounded-lg text-gray-900 focus:ring-2 focus:ring-red-500 focus:border-red-500 transition-colors'

// Textarea base styles
export const textareaClass = 'w-full px-4 py-2.5 border border-gray-200 rounded-lg text-gray-900 placeholder-gray-400 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors resize-none'

// Textarea with error state
export const textareaErrorClass = 'w-full px-4 py-2.5 border border-red-300 rounded-lg text-gray-900 placeholder-gray-400 focus:ring-2 focus:ring-red-500 focus:border-red-500 transition-colors resize-none'

// Form label
export const labelClass = 'block text-sm font-medium text-gray-700 mb-2'

// Required asterisk
export const requiredClass = 'text-red-500 ml-0.5'

// Error message
export const errorClass = 'mt-1 text-sm text-red-500'

// Helper text
export const helperClass = 'mt-1 text-sm text-gray-500'

// Checkbox/Radio label
export const checkboxLabelClass = 'flex items-center gap-2 text-sm text-gray-700 cursor-pointer'

// Dynamic input class generator
export function getInputClass(hasError: boolean): string {
  return hasError ? inputErrorClass : inputClass
}

export function getSelectClass(hasError: boolean): string {
  return hasError ? selectErrorClass : selectClass
}

export function getTextareaClass(hasError: boolean): string {
  return hasError ? textareaErrorClass : textareaClass
}

// Status badge styles (for admin tables)
export const statusBadgeBase = 'px-2 py-0.5 text-xs font-medium rounded-full inline-block'

export interface StatusBadgeConfig {
  bg: string
  text: string
  label?: string
}

export const statusBadgeColors: Record<string, StatusBadgeConfig> = {
  // Application statuses
  DRAFT: { bg: 'bg-gray-100', text: 'text-gray-800', label: 'Borrador' },
  SUBMITTED: { bg: 'bg-blue-100', text: 'text-blue-800', label: 'Nueva' },
  IN_REVIEW: { bg: 'bg-yellow-100', text: 'text-yellow-800', label: 'En Revisión' },
  DOCS_PENDING: { bg: 'bg-orange-100', text: 'text-orange-800', label: 'Docs Pendientes' },
  CORRECTIONS_PENDING: { bg: 'bg-orange-100', text: 'text-orange-800', label: 'Correcciones' },
  COUNTER_OFFERED: { bg: 'bg-purple-100', text: 'text-purple-800', label: 'Contraoferta' },
  APPROVED: { bg: 'bg-green-100', text: 'text-green-800', label: 'Aprobada' },
  REJECTED: { bg: 'bg-red-100', text: 'text-red-800', label: 'Rechazada' },
  DISBURSED: { bg: 'bg-purple-100', text: 'text-purple-800', label: 'Desembolsada' },
  SYNCED: { bg: 'bg-teal-100', text: 'text-teal-800', label: 'Sincronizada' },
  CANCELLED: { bg: 'bg-gray-200', text: 'text-gray-800', label: 'Cancelada' },
  // User roles
  SUPER_ADMIN: { bg: 'bg-purple-100', text: 'text-purple-800', label: 'Super Admin' },
  ADMIN: { bg: 'bg-red-100', text: 'text-red-800', label: 'Administrador' },
  ANALYST: { bg: 'bg-blue-100', text: 'text-blue-800', label: 'Analista' },
  SUPERVISOR: { bg: 'bg-green-100', text: 'text-green-800', label: 'Supervisor' },
  // Generic
  ACTIVE: { bg: 'bg-green-100', text: 'text-green-800', label: 'Activo' },
  INACTIVE: { bg: 'bg-gray-100', text: 'text-gray-800', label: 'Inactivo' },
  PENDING: { bg: 'bg-yellow-100', text: 'text-yellow-800', label: 'Pendiente' },
  VERIFIED: { bg: 'bg-green-100', text: 'text-green-800', label: 'Verificado' },
  ERROR: { bg: 'bg-red-100', text: 'text-red-800', label: 'Error' },
}

export function getStatusBadgeClass(status: string): string {
  const colors = statusBadgeColors[status] || { bg: 'bg-gray-100', text: 'text-gray-800' }
  return `${statusBadgeBase} ${colors.bg} ${colors.text}`
}

/**
 * Get status badge configuration including colors and label
 */
export function getStatusBadge(status: string): StatusBadgeConfig {
  return statusBadgeColors[status] || { bg: 'bg-gray-100', text: 'text-gray-800', label: status }
}

/**
 * Get role badge configuration
 */
export function getRoleBadge(role: string): StatusBadgeConfig {
  return statusBadgeColors[role] || { bg: 'bg-gray-100', text: 'text-gray-800', label: role }
}

// API Provider colors (for API logs)
export const providerColors: Record<string, string> = {
  NUBARIUM: 'bg-purple-100 text-purple-800',
  TWILIO: 'bg-blue-100 text-blue-800',
  SAT: 'bg-green-100 text-green-800',
  RENAPO: 'bg-yellow-100 text-yellow-800',
  INE: 'bg-orange-100 text-orange-800',
  BUREAU: 'bg-red-100 text-red-800',
}

export function getProviderColor(provider: string): string {
  return providerColors[provider] || 'bg-gray-100 text-gray-800'
}

// Document status badges
export function getDocStatusBadge(status: string): StatusBadgeConfig {
  const docStatuses: Record<string, StatusBadgeConfig> = {
    PENDING: { bg: 'bg-yellow-100', text: 'text-yellow-800', label: 'Pendiente' },
    APPROVED: { bg: 'bg-green-100', text: 'text-green-800', label: 'Aprobado' },
    REJECTED: { bg: 'bg-red-100', text: 'text-red-800', label: 'Rechazado' },
  }
  return docStatuses[status] || { bg: 'bg-gray-100', text: 'text-gray-800', label: status }
}

// Table styles
export const tableHeaderClass = 'px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider'
export const tableCellClass = 'px-4 py-3 whitespace-nowrap text-sm text-gray-900'
export const tableRowHoverClass = 'hover:bg-gray-50 transition-colors'

// Modal styles
export const modalBackdropClass = 'fixed inset-0 bg-black/50 flex items-center justify-center z-50'
export const modalContentClass = 'bg-white rounded-xl shadow-xl p-6 w-full mx-4 max-h-[90vh] overflow-y-auto'

// Empty state styles
export const emptyStateClass = 'p-12 text-center'
export const emptyStateIconClass = 'w-16 h-16 text-gray-300 mx-auto mb-4'
export const emptyStateTitleClass = 'text-lg font-medium text-gray-900 mb-2'
export const emptyStateTextClass = 'text-gray-500 mb-4'
