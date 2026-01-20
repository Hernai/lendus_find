import { ref, type Ref } from 'vue'
import { v2 } from '@/services/v2'
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
   * Upload INE documents (front and back) using V2 API.
   * Note: applicationId is kept for metadata but documents are attached to Person automatically.
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
          kycLogger.debug('Uploading INE front via V2 API')
          // Convert base64 to File
          const frontBlob = await fetch(`data:image/jpeg;base64,${documents.value.front}`).then(r => r.blob())
          const frontFile = new File([frontBlob], 'ine_front.jpg', { type: 'image/jpeg' })

          const response = await v2.applicant.document.upload(frontFile, 'INE_FRONT', {
            metadata: { source: 'kyc_capture', application_id: applicationId }
          })
          results.front = {
            success: response.success,
            documentId: response.data?.document?.id,
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
          kycLogger.debug('Uploading INE back via V2 API')
          // Convert base64 to File
          const backBlob = await fetch(`data:image/jpeg;base64,${documents.value.back}`).then(r => r.blob())
          const backFile = new File([backBlob], 'ine_back.jpg', { type: 'image/jpeg' })

          const response = await v2.applicant.document.upload(backFile, 'INE_BACK', {
            metadata: { source: 'kyc_capture', application_id: applicationId }
          })
          results.back = {
            success: response.success,
            documentId: response.data?.document?.id,
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
   * Upload selfie document using V2 API.
   * Note: applicationId is kept for metadata but documents are attached to Person automatically.
   */
  const uploadSelfie = async (applicationId: string): Promise<DocumentUploadResult> => {
    if (!documents.value.selfie) {
      return { success: false, error: 'No selfie captured' }
    }

    isUploading.value = true
    uploadError.value = null

    try {
      kycLogger.debug('Uploading selfie via V2 API')
      // Convert base64 to File
      const selfieBlob = await fetch(`data:image/jpeg;base64,${documents.value.selfie}`).then(r => r.blob())
      const selfieFile = new File([selfieBlob], 'selfie.jpg', { type: 'image/jpeg' })

      const response = await v2.applicant.document.upload(selfieFile, 'SELFIE', {
        metadata: { source: 'kyc_capture', application_id: applicationId }
      })

      return {
        success: response.success,
        documentId: response.data?.document?.id,
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
