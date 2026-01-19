/**
 * Admin Components Index
 *
 * Re-export all admin-specific components for convenient imports.
 *
 * @example
 * ```typescript
 * import { AdminDataTable, AdminDocumentGallery, type TableColumn } from '@/components/admin'
 * ```
 */

// Components
export { default as AdminDataTable } from './AdminDataTable.vue'
export { default as AdminDocumentGallery } from './AdminDocumentGallery.vue'
export { default as ConfirmModal } from './ConfirmModal.vue'
export { default as TenantBrandingEditor } from './TenantBrandingEditor.vue'
export { default as TenantSwitcher } from './TenantSwitcher.vue'

// Types
export type { TableColumn } from './types'
