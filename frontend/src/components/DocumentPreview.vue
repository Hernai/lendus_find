<script setup lang="ts">
import { ref, computed, onUnmounted, onBeforeMount } from 'vue'
import { api } from '@/services/api'
import { useDocumentTypes } from '@/composables'
import ImageViewer from './ImageViewer.vue'

const { loadDocumentTypes, getDocumentTypeLabel } = useDocumentTypes()

interface Document {
  id: string
  type: string
  name?: string
  original_name?: string
  status: string
  mime_type?: string
}

const props = defineProps<{
  document: Document
  showStatus?: boolean
  canReplace?: boolean
}>()

const emit = defineEmits<{
  replace: [documentId: string]
}>()

const isLoading = ref(false)
const imageUrl = ref<string | null>(null)
const showViewer = ref(false)
const error = ref<string | null>(null)

const isImage = computed(() => {
  const mimeType = props.document.mime_type || ''
  return mimeType.startsWith('image/')
})

const isPdf = computed(() => {
  const mimeType = props.document.mime_type || ''
  return mimeType === 'application/pdf'
})

const isVerified = computed(() => props.document.status === 'APPROVED')
const isRejected = computed(() => props.document.status === 'REJECTED')
const isPending = computed(() => props.document.status === 'PENDING')

const statusLabel = computed(() => {
  switch (props.document.status) {
    case 'APPROVED': return 'Verificado'
    case 'REJECTED': return 'Rechazado'
    case 'PENDING': return 'Pendiente'
    default: return props.document.status
  }
})

const statusColor = computed(() => {
  switch (props.document.status) {
    case 'APPROVED': return 'text-green-600 bg-green-50'
    case 'REJECTED': return 'text-red-600 bg-red-50'
    case 'PENDING': return 'text-yellow-600 bg-yellow-50'
    default: return 'text-gray-600 bg-gray-50'
  }
})

const documentName = computed(() => {
  return props.document.name || props.document.original_name || props.document.type
})

// Load document types on mount
onBeforeMount(async () => {
  await loadDocumentTypes()
})

const typeLabel = computed(() => {
  return getDocumentTypeLabel(props.document.type)
})

const loadImage = async () => {
  if (!isImage.value || imageUrl.value) return

  isLoading.value = true
  error.value = null

  try {
    const response = await api.get(`/documents/${props.document.id}/download`, {
      responseType: 'blob'
    })
    const blob = new Blob([response.data as BlobPart], { type: response.headers['content-type'] || 'image/jpeg' })
    imageUrl.value = URL.createObjectURL(blob)
  } catch (e) {
    console.error('Failed to load document image:', e)
    error.value = 'Error al cargar'
  } finally {
    isLoading.value = false
  }
}

const openPreview = async () => {
  if (isPdf.value) {
    // For PDFs, open in new tab using download URL
    window.open(`/api/documents/${props.document.id}/download`, '_blank')
    return
  }

  if (isImage.value) {
    if (!imageUrl.value) {
      await loadImage()
    }
    if (imageUrl.value) {
      showViewer.value = true
    }
  }
}

const handleReplace = () => {
  emit('replace', props.document.id)
}

// Cleanup: revoke object URL to prevent memory leak
onUnmounted(() => {
  if (imageUrl.value) {
    URL.revokeObjectURL(imageUrl.value)
  }
})
</script>

<template>
  <div class="border border-gray-200 rounded-xl overflow-hidden">
    <!-- Preview Area -->
    <button
      class="w-full aspect-[4/3] bg-gray-100 flex items-center justify-center relative group"
      @click="openPreview"
    >
      <!-- Loading -->
      <div v-if="isLoading" class="animate-spin w-6 h-6 border-2 border-primary-600 border-t-transparent rounded-full" />

      <!-- Error -->
      <div v-else-if="error" class="text-center p-4">
        <svg class="w-8 h-8 text-gray-400 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
        </svg>
        <p class="text-xs text-gray-500">{{ error }}</p>
      </div>

      <!-- Image preview -->
      <img
        v-else-if="imageUrl"
        :src="imageUrl"
        :alt="documentName"
        class="w-full h-full object-cover"
      />

      <!-- PDF icon -->
      <div v-else-if="isPdf" class="text-center">
        <svg class="w-12 h-12 text-red-500 mx-auto" fill="currentColor" viewBox="0 0 24 24">
          <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8l-6-6zm-1 2l5 5h-5V4zM8.5 13a.5.5 0 01.5.5v3a.5.5 0 01-1 0v-3a.5.5 0 01.5-.5zm3 0c.828 0 1.5.672 1.5 1.5v1c0 .828-.672 1.5-1.5 1.5H11v1h1.5a.5.5 0 010 1H11a1 1 0 01-1-1v-4h1.5zm0 3a.5.5 0 00.5-.5v-1a.5.5 0 00-.5-.5H11v2h.5zm3.5-3h2a.5.5 0 010 1h-1.5v1h1a.5.5 0 010 1h-1v1.5a.5.5 0 01-1 0V13.5a.5.5 0 01.5-.5z"/>
        </svg>
        <p class="text-xs text-gray-500 mt-1">PDF</p>
      </div>

      <!-- Generic document icon (for images not yet loaded) -->
      <div v-else-if="isImage" class="text-center" @click.prevent="loadImage">
        <svg class="w-12 h-12 text-gray-400 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
        </svg>
        <p class="text-xs text-gray-500 mt-1">Cargar vista previa</p>
      </div>

      <!-- Hover overlay -->
      <div class="absolute inset-0 bg-black/0 group-hover:bg-black/20 transition-colors flex items-center justify-center">
        <div class="opacity-0 group-hover:opacity-100 transition-opacity">
          <div class="bg-white/90 rounded-full p-2">
            <svg class="w-5 h-5 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
            </svg>
          </div>
        </div>
      </div>

      <!-- Status badge (corner) -->
      <div
        v-if="showStatus"
        class="absolute top-2 right-2 px-2 py-0.5 rounded-full text-xs font-medium"
        :class="statusColor"
      >
        {{ statusLabel }}
      </div>
    </button>

    <!-- Info Area -->
    <div class="p-3">
      <p class="font-medium text-gray-900 text-sm truncate">{{ typeLabel }}</p>
      <p class="text-xs text-gray-500 truncate">{{ documentName }}</p>

      <!-- Replace button -->
      <button
        v-if="canReplace && !isVerified"
        class="mt-2 w-full py-2 text-sm text-primary-600 bg-primary-50 rounded-lg hover:bg-primary-100 transition-colors flex items-center justify-center gap-1"
        @click="handleReplace"
      >
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
        </svg>
        Reemplazar
      </button>
    </div>
  </div>

  <!-- Image Viewer -->
  <ImageViewer
    v-if="showViewer && imageUrl"
    :src="imageUrl"
    :alt="documentName"
    :title="typeLabel"
    :is-verified="isVerified"
    verified-label="Documento verificado"
    :can-change="canReplace"
    change-label="Reemplazar documento"
    @close="showViewer = false"
    @change="handleReplace"
  />
</template>
