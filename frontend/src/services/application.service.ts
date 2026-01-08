import { api } from './api'

export interface Application {
  id: string
  folio: string
  status: string
  product: {
    id: string
    name: string
    type: string
  } | null
  requested_amount: number
  approved_amount: number | null
  term_months: number
  payment_frequency: string
  interest_rate: number
  monthly_payment: number
  total_to_pay: number
  purpose: string
  purpose_description?: string
  opening_commission?: number
  rejection_reason?: string
  assigned_to?: string
  documents?: ApplicationDocument[]
  pending_documents?: PendingDocument[]
  references?: ApplicationReference[]
  status_history?: { status: string; timestamp: string }[]
  created_at: string
  updated_at: string
}

export interface ApplicationDocument {
  id: string
  type: string
  name: string
  status: 'PENDING' | 'APPROVED' | 'REJECTED'
  rejection_reason?: string
  mime_type?: string
  size?: number
  uploaded_at: string
}

export interface PendingDocument {
  type: string
  label: string
  description: string
  required: boolean
}

export interface ApplicationReference {
  id: string
  full_name: string
  relationship: string
  phone: string
  verified: boolean
}

export interface CreateApplicationPayload {
  product_id: string
  requested_amount: number
  term_months: number
  payment_frequency: 'WEEKLY' | 'BIWEEKLY' | 'QUINCENAL' | 'MONTHLY' | 'MENSUAL'
  purpose: string
  purpose_description?: string
}

export interface CreateReferencePayload {
  full_name: string
  relationship: string
  phone: string
  email?: string
  address?: string
  years_known?: number
}

const applicationService = {
  /**
   * List all applications for current user
   */
  list: async () => {
    const response = await api.get<{ data: Application[]; meta: { total: number } }>('/applications')
    return response.data
  },

  /**
   * Create a new application
   */
  create: async (data: CreateApplicationPayload) => {
    const response = await api.post<{ message: string; data: Application }>('/applications', data)
    return response.data.data
  },

  /**
   * Get application details
   */
  get: async (id: string) => {
    const response = await api.get<{ data: Application }>(`/applications/${id}`)
    return response.data.data
  },

  /**
   * Update application
   */
  update: async (id: string, data: Partial<CreateApplicationPayload>) => {
    const response = await api.put<{ message: string; data: Application }>(`/applications/${id}`, data)
    return response.data.data
  },

  /**
   * Submit application for review
   */
  submit: async (id: string) => {
    const response = await api.post<{ message: string; data: Application }>(`/applications/${id}/submit`)
    return response.data.data
  },

  /**
   * Cancel application
   */
  cancel: async (id: string, reason?: string) => {
    const response = await api.post<{ message: string; data: Application }>(`/applications/${id}/cancel`, {
      reason,
    })
    return response.data.data
  },

  /**
   * Accept counter offer
   */
  acceptCounterOffer: async (id: string) => {
    const response = await api.post<{ message: string; data: Application }>(
      `/applications/${id}/counter-offer/accept`
    )
    return response.data.data
  },

  /**
   * Reject counter offer
   */
  rejectCounterOffer: async (id: string, reason?: string) => {
    const response = await api.post<{ message: string; data: Application }>(
      `/applications/${id}/counter-offer/reject`,
      { reason }
    )
    return response.data.data
  },

  /**
   * Get documents for application
   */
  getDocuments: async (applicationId: string) => {
    const response = await api.get<{ data: ApplicationDocument[] }>(
      `/applications/${applicationId}/documents`
    )
    return response.data.data
  },

  /**
   * Upload a document
   */
  uploadDocument: async (applicationId: string, type: string, file: File) => {
    const formData = new FormData()
    formData.append('type', type)
    formData.append('file', file)

    const response = await api.post<{ message: string; data: ApplicationDocument }>(
      `/applications/${applicationId}/documents`,
      formData,
      {
        headers: {
          'Content-Type': 'multipart/form-data',
        },
      }
    )
    return response.data.data
  },

  /**
   * Delete a document
   */
  deleteDocument: async (applicationId: string, documentId: string) => {
    const response = await api.delete<{ message: string }>(
      `/applications/${applicationId}/documents/${documentId}`
    )
    return response.data
  },

  /**
   * Get references for application
   */
  getReferences: async (applicationId: string) => {
    const response = await api.get<{ data: ApplicationReference[] }>(
      `/applications/${applicationId}/references`
    )
    return response.data.data
  },

  /**
   * Add a reference
   */
  addReference: async (applicationId: string, data: CreateReferencePayload) => {
    const response = await api.post<{ message: string; data: ApplicationReference }>(
      `/applications/${applicationId}/references`,
      data
    )
    return response.data.data
  },
}

export default applicationService
