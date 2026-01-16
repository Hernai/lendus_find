import { ref, type Ref } from 'vue'
import { api } from '@/services/api'
import { logger } from '@/utils/logger'

const kycLogger = logger.child('KYC:Documents')

/**
 * Document capture state.
 */
export interface DocumentCapture {
  front: string | null
  back: string | null
  selfie: string | null
}

/**
 * Document upload result.
 */
export interface DocumentUploadResult {
  success: boolean
  documentId?: string
  error?: string
}

/**
 * Composable for KYC document capture and upload.
 *
 * Handles INE front/back and selfie capture.
 */
export function useKycDocuments() {
  const isCapturing: Ref<boolean> = ref(false)
  const isUploading: Ref<boolean> = ref(false)
  const captureError: Ref<string | null> = ref(null)
  const uploadError: Ref<string | null> = ref(null)

  const documents: Ref<DocumentCapture> = ref({
    front: null,
    back: null,
    selfie: null,
  })

  /**
   * Set captured document image.
   */
  const setDocument = (type: keyof DocumentCapture, base64: string) => {
    documents.value[type] = base64
    kycLogger.debug(`Document ${type} captured`)
  }

  /**
   * Clear a specific document.
   */
  const clearDocument = (type: keyof DocumentCapture) => {
    documents.value[type] = null
  }

  /**
   * Upload INE documents (front and back).
   */
  const uploadIneDocuments = async (
    applicationId: string
  ): Promise<{ front: DocumentUploadResult; back: DocumentUploadResult }> => {
    isUploading.value = true
    uploadError.value = null

    const results = {
      front: { success: false } as DocumentUploadResult,
      back: { success: false } as DocumentUploadResult,
    }

    try {
      // Upload front
      if (documents.value.front) {
        try {
          kycLogger.debug('Uploading INE front')
          const response = await api.post<{ data?: { id?: string } }>(`/applications/${applicationId}/documents`, {
            type: 'INE_FRONT',
            file: documents.value.front,
            source: 'kyc_capture',
          })
          results.front = {
            success: true,
            documentId: response.data.data?.id,
          }
        } catch (error) {
          results.front = {
            success: false,
            error: error instanceof Error ? error.message : 'Error uploading INE front',
          }
          kycLogger.error('Failed to upload INE front', { error: results.front.error })
        }
      }

      // Upload back
      if (documents.value.back) {
        try {
          kycLogger.debug('Uploading INE back')
          const response = await api.post<{ data?: { id?: string } }>(`/applications/${applicationId}/documents`, {
            type: 'INE_BACK',
            file: documents.value.back,
            source: 'kyc_capture',
          })
          results.back = {
            success: true,
            documentId: response.data.data?.id,
          }
        } catch (error) {
          results.back = {
            success: false,
            error: error instanceof Error ? error.message : 'Error uploading INE back',
          }
          kycLogger.error('Failed to upload INE back', { error: results.back.error })
        }
      }

      return results
    } finally {
      isUploading.value = false
    }
  }

  /**
   * Upload selfie document.
   */
  const uploadSelfie = async (applicationId: string): Promise<DocumentUploadResult> => {
    if (!documents.value.selfie) {
      return { success: false, error: 'No selfie captured' }
    }

    isUploading.value = true
    uploadError.value = null

    try {
      kycLogger.debug('Uploading selfie')
      const response = await api.post<{ data?: { id?: string } }>(`/applications/${applicationId}/documents`, {
        type: 'SELFIE',
        file: documents.value.selfie,
        source: 'kyc_capture',
      })

      return {
        success: true,
        documentId: response.data.data?.id,
      }
    } catch (error) {
      const message = error instanceof Error ? error.message : 'Error uploading selfie'
      uploadError.value = message
      kycLogger.error('Failed to upload selfie', { error: message })
      return { success: false, error: message }
    } finally {
      isUploading.value = false
    }
  }

  /**
   * Convert file to base64.
   */
  const fileToBase64 = (file: File): Promise<string> => {
    return new Promise((resolve, reject) => {
      const reader = new FileReader()
      reader.onload = () => {
        const result = reader.result as string
        // Remove data URL prefix if present
        const base64 = result.includes(',') ? result.split(',')[1] ?? result : result
        resolve(base64)
      }
      reader.onerror = () => reject(new Error('Failed to read file'))
      reader.readAsDataURL(file)
    })
  }

  /**
   * Compress image before upload.
   */
  const compressImage = async (
    base64: string,
    maxWidth = 1200,
    quality = 0.8
  ): Promise<string> => {
    return new Promise((resolve, reject) => {
      const img = new Image()
      img.onload = () => {
        const canvas = document.createElement('canvas')
        let { width, height } = img

        if (width > maxWidth) {
          height = (height * maxWidth) / width
          width = maxWidth
        }

        canvas.width = width
        canvas.height = height

        const ctx = canvas.getContext('2d')
        if (!ctx) {
          reject(new Error('Could not get canvas context'))
          return
        }

        ctx.drawImage(img, 0, 0, width, height)
        const compressed = canvas.toDataURL('image/jpeg', quality)
        resolve(compressed.split(',')[1] ?? compressed)
      }
      img.onerror = () => reject(new Error('Failed to load image'))
      img.src = `data:image/jpeg;base64,${base64}`
    })
  }

  /**
   * Reset all documents.
   */
  const resetDocuments = () => {
    documents.value = {
      front: null,
      back: null,
      selfie: null,
    }
    captureError.value = null
    uploadError.value = null
  }

  return {
    isCapturing,
    isUploading,
    captureError,
    uploadError,
    documents,
    setDocument,
    clearDocument,
    uploadIneDocuments,
    uploadSelfie,
    fileToBase64,
    compressImage,
    resetDocuments,
  }
}
