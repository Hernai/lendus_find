/**
 * Composable for loading document types from backend.
 *
 * This ensures document types are always in sync with the backend enum
 * and not hardcoded in the frontend.
 */
import { ref, readonly } from 'vue'
import { v2 } from '@/services/v2'
import { logger } from '@/utils/logger'

const log = logger.child('useDocumentTypes')

// Global state - shared across all components
const documentTypes = ref<Record<string, string>>({})
const documentCategories = ref<Record<string, string>>({})
const isLoaded = ref(false)
const isLoading = ref(false)

/**
 * Load document types from backend API.
 * Uses V2 applicant endpoint which is accessible to all authenticated users.
 */
async function loadDocumentTypes(): Promise<void> {
  // Don't reload if already loaded or currently loading
  if (isLoaded.value || isLoading.value) return

  isLoading.value = true

  try {
    const response = await v2.applicant.document.getTypes()
    if (response.success && response.data) {
      // Backend returns: { types: Record<string, string>, categories: Record<string, string> }
      documentTypes.value = response.data.types as unknown as Record<string, string>
      documentCategories.value = response.data.categories as unknown as Record<string, string>
      isLoaded.value = true
      log.debug('Document types loaded from backend', { count: Object.keys(documentTypes.value).length })
    }
  } catch (error) {
    log.error('Failed to load document types from backend', { error })
    // Don't set isLoaded so we can retry later
  } finally {
    isLoading.value = false
  }
}

/**
 * Get the label for a document type.
 * Falls back to the type itself if not found.
 */
function getDocumentTypeLabel(type: string): string {
  return documentTypes.value[type] || type.replace(/_/g, ' ')
}

/**
 * Composable for document types.
 */
export function useDocumentTypes() {
  return {
    // State (readonly to prevent external mutation)
    documentTypes: readonly(documentTypes),
    documentCategories: readonly(documentCategories),
    isLoaded: readonly(isLoaded),
    isLoading: readonly(isLoading),

    // Methods
    loadDocumentTypes,
    getDocumentTypeLabel,
  }
}
