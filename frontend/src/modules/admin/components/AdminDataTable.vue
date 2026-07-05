<script setup lang="ts" generic="T extends { id: string }">
import { computed } from 'vue'
import type { TableColumn } from './types'

/**
 * Props for AdminDataTable component.
 */
interface Props {
  /** Data items to display */
  items: T[]
  /** Column definitions */
  columns: TableColumn<T>[]
  /** Loading state */
  loading?: boolean
  /** Error message to display */
  error?: string
  /** Message when no items */
  emptyMessage?: string
  /** Icon for empty state (slot name or 'users' | 'documents' | 'default') */
  emptyIcon?: 'users' | 'documents' | 'default'
  /** Loading message */
  loadingMessage?: string
  /** Current page (1-indexed) */
  currentPage?: number
  /** Total pages */
  totalPages?: number
  /** Total items count */
  totalItems?: number
  /** Items per page */
  itemsPerPage?: number
  /** Row click handler */
  clickable?: boolean
  /** Enable row selection */
  selectable?: boolean
  /** Selected item IDs */
  selectedIds?: Set<string>
  /** Hover effect on rows */
  hoverable?: boolean
  /** Sticky header */
  stickyHeader?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  loading: false,
  error: '',
  emptyMessage: 'No hay datos para mostrar',
  emptyIcon: 'default',
  loadingMessage: 'Cargando...',
  currentPage: 1,
  totalPages: 1,
  totalItems: 0,
  itemsPerPage: 20,
  clickable: false,
  selectable: false,
  selectedIds: () => new Set<string>(),
  hoverable: true,
  stickyHeader: false
})

const emit = defineEmits<{
  /** Row clicked */
  (e: 'row-click', item: T): void
  /** Page changed */
  (e: 'page-change', page: number): void
  /** Retry after error */
  (e: 'retry'): void
  /** Selection changed */
  (e: 'selection-change', ids: Set<string>): void
  /** Select all toggled */
  (e: 'select-all', selected: boolean): void
}>()

// Computed pagination info
const showPagination = computed(() => props.totalPages > 1)

const paginationRange = computed(() => {
  const range: number[] = []
  const maxVisible = 5

  if (props.totalPages <= maxVisible) {
    for (let i = 1; i <= props.totalPages; i++) {
      range.push(i)
    }
  } else {
    const start = Math.max(1, props.currentPage - 2)
    const end = Math.min(props.totalPages, start + maxVisible - 1)

    for (let i = start; i <= end; i++) {
      range.push(i)
    }
  }

  return range
})

const startItem = computed(() => (props.currentPage - 1) * props.itemsPerPage + 1)
const endItem = computed(() => Math.min(props.currentPage * props.itemsPerPage, props.totalItems))

// Selection helpers
const isAllSelected = computed(() => {
  if (props.items.length === 0) return false
  return props.items.every(item => props.selectedIds.has(item.id))
})

const isSomeSelected = computed(() => {
  if (props.items.length === 0) return false
  const selectedCount = props.items.filter(item => props.selectedIds.has(item.id)).length
  return selectedCount > 0 && selectedCount < props.items.length
})

const isSelected = (id: string) => props.selectedIds.has(id)

// Handlers
const handleRowClick = (item: T) => {
  if (props.clickable) {
    emit('row-click', item)
  }
}

const handlePageChange = (page: number) => {
  if (page >= 1 && page <= props.totalPages) {
    emit('page-change', page)
  }
}

const handleSelectAll = () => {
  emit('select-all', !isAllSelected.value)
}

const handleToggleSelection = (id: string) => {
  const newSelection = new Set(props.selectedIds)
  if (newSelection.has(id)) {
    newSelection.delete(id)
  } else {
    newSelection.add(id)
  }
  emit('selection-change', newSelection)
}

// Column alignment class
const getAlignClass = (align?: 'left' | 'center' | 'right') => {
  switch (align) {
    case 'center': return 'text-center'
    case 'right': return 'text-right'
    default: return 'text-left'
  }
}
</script>

<template>
  <div class="bg-white rounded-xl shadow-sm overflow-hidden">
    <!-- Loading State -->
    <div v-if="loading && items.length === 0" class="p-8 text-center">
      <div class="animate-spin w-8 h-8 border-4 border-primary-600 border-t-transparent rounded-full mx-auto mb-4" />
      <p class="text-gray-500">{{ loadingMessage }}</p>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="p-8 text-center">
      <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
        <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
      </div>
      <p class="text-red-800 mb-4">{{ error }}</p>
      <button
        class="text-sm text-primary-600 hover:text-primary-700 underline"
        @click="emit('retry')"
      >
        Reintentar
      </button>
    </div>

    <!-- Empty State -->
    <div v-else-if="items.length === 0" class="p-8 text-center">
      <!-- Users Icon -->
      <svg v-if="emptyIcon === 'users'" class="w-12 h-12 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
      </svg>
      <!-- Documents Icon -->
      <svg v-else-if="emptyIcon === 'documents'" class="w-12 h-12 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
      </svg>
      <!-- Default Icon -->
      <svg v-else class="w-12 h-12 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
      </svg>
      <p class="text-gray-500">{{ emptyMessage }}</p>
      <slot name="empty-action" />
    </div>

    <!-- Table -->
    <template v-else>
      <!-- Bulk Selection Bar -->
      <slot name="bulk-actions" :selected-count="selectedIds.size" />

      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
          <thead :class="['bg-gray-50', { 'sticky top-0 z-10': stickyHeader }]">
            <tr>
              <!-- Selection Checkbox Header -->
              <th v-if="selectable" class="px-3 py-2 text-left w-10">
                <input
                  type="checkbox"
                  :checked="isAllSelected"
                  :indeterminate="isSomeSelected"
                  class="w-4 h-4 text-primary-600 border-gray-300 rounded focus:ring-primary-500 cursor-pointer"
                  @change="handleSelectAll"
                />
              </th>
              <!-- Column Headers -->
              <th
                v-for="col in columns"
                :key="col.key"
                :class="[
                  'px-3 py-2 text-[11px] font-medium text-gray-500 uppercase tracking-wider',
                  getAlignClass(col.align),
                  col.width,
                  { 'hidden md:table-cell': col.hideOnMobile }
                ]"
              >
                {{ col.label }}
              </th>
              <!-- Actions Column (if slot provided) -->
              <th v-if="$slots.actions" class="px-3 py-2 text-right text-[11px] font-medium text-gray-500 uppercase tracking-wider">
                Acciones
              </th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <tr
              v-for="item in items"
              :key="item.id"
              :class="[
                'transition-colors',
                { 'hover:bg-gray-50': hoverable },
                { 'cursor-pointer': clickable },
                { 'bg-primary-50': isSelected(item.id) }
              ]"
              @click="handleRowClick(item)"
            >
              <!-- Selection Checkbox -->
              <td v-if="selectable" class="px-3 py-2 whitespace-nowrap" @click.stop>
                <input
                  type="checkbox"
                  :checked="isSelected(item.id)"
                  class="w-4 h-4 text-primary-600 border-gray-300 rounded focus:ring-primary-500 cursor-pointer"
                  @change="handleToggleSelection(item.id)"
                />
              </td>
              <!-- Data Cells -->
              <td
                v-for="col in columns"
                :key="col.key"
                :class="[
                  'px-3 py-2 whitespace-nowrap',
                  getAlignClass(col.align),
                  { 'hidden md:table-cell': col.hideOnMobile }
                ]"
              >
                <!-- Custom slot for this column -->
                <slot :name="`cell-${col.key}`" :item="item" :value="(item as Record<string, unknown>)[col.key]">
                  <!-- Default: format function or raw value -->
                  <span class="text-sm text-gray-900">
                    {{ col.format ? col.format(item) : (item as Record<string, unknown>)[col.key] }}
                  </span>
                </slot>
              </td>
              <!-- Actions Cell -->
              <td v-if="$slots.actions" class="px-3 py-2 whitespace-nowrap text-right" @click.stop>
                <slot name="actions" :item="item" />
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="showPagination" class="bg-gray-50 px-4 py-3 flex items-center justify-between border-t border-gray-200">
        <!-- Mobile Pagination -->
        <div class="flex-1 flex justify-between sm:hidden">
          <button
            :disabled="currentPage === 1"
            class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
            @click="handlePageChange(currentPage - 1)"
          >
            Anterior
          </button>
          <button
            :disabled="currentPage === totalPages"
            class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
            @click="handlePageChange(currentPage + 1)"
          >
            Siguiente
          </button>
        </div>

        <!-- Desktop Pagination -->
        <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
          <div>
            <p class="text-sm text-gray-700">
              Mostrando
              <span class="font-medium">{{ startItem }}</span>
              a
              <span class="font-medium">{{ endItem }}</span>
              de
              <span class="font-medium">{{ totalItems }}</span>
              resultados
            </p>
          </div>
          <div>
            <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Paginación">
              <!-- Previous Button -->
              <button
                :disabled="currentPage === 1"
                class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                @click="handlePageChange(currentPage - 1)"
              >
                <span class="sr-only">Anterior</span>
                <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                </svg>
              </button>

              <!-- Page Numbers -->
              <button
                v-for="page in paginationRange"
                :key="page"
                :class="[
                  'relative inline-flex items-center px-4 py-2 border text-sm font-medium',
                  currentPage === page
                    ? 'z-10 bg-primary-50 border-primary-500 text-primary-600'
                    : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50'
                ]"
                @click="handlePageChange(page)"
              >
                {{ page }}
              </button>

              <!-- Next Button -->
              <button
                :disabled="currentPage === totalPages"
                class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                @click="handlePageChange(currentPage + 1)"
              >
                <span class="sr-only">Siguiente</span>
                <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                </svg>
              </button>
            </nav>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>
