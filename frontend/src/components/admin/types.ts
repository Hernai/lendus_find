/**
 * Type definitions for Admin components.
 */

/**
 * Column definition for AdminDataTable.
 */
export interface TableColumn<T = unknown> {
  /** Unique key for the column */
  key: string
  /** Display label in header */
  label: string
  /** Width class (e.g., 'w-32', 'min-w-[200px]') */
  width?: string
  /** Text alignment */
  align?: 'left' | 'center' | 'right'
  /** Hide on mobile */
  hideOnMobile?: boolean
  /** Custom cell renderer - use slot instead for complex rendering */
  format?: (row: T) => string
  /** Whether this column is sortable */
  sortable?: boolean
}
