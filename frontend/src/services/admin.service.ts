import { api } from './api'

// Dashboard types
export interface DashboardData {
  summary: {
    total_applications: number
    today_applications: number
    month_applications: number
    pending_review: number
    approved: number
    disbursed: number
    rejected: number
  }
  amounts: {
    pending: number
    approved: number
    disbursed: number
  }
  by_status: Record<string, number>
  recent_applications: {
    id: string
    folio: string
    applicant_name: string
    product: string
    amount: number
    status: string
    created_at: string
  }[]
}

export interface DashboardStats {
  applications_over_time: {
    date: string
    count: number
    amount: number
  }[]
  conversion: {
    total_submitted: number
    total_approved: number
    total_disbursed: number
    approval_rate: number
    disbursement_rate: number
  }
  avg_processing_days: number
  top_products: {
    name: string
    applications: number
    total_amount: number
  }[]
  rejection_reasons: {
    rejection_reason: string
    count: number
  }[]
}

// Product types
export interface AdminProduct {
  id: string
  name: string
  code: string
  type: 'PERSONAL' | 'AUTO' | 'HIPOTECARIO' | 'PYME' | 'NOMINA' | 'ARRENDAMIENTO'
  description: string | null
  min_amount: number
  max_amount: number
  min_term_months: number
  max_term_months: number
  interest_rate: number
  opening_commission: number
  late_fee_rate: number
  payment_frequencies: ('WEEKLY' | 'BIWEEKLY' | 'MONTHLY')[]
  term_config: Record<string, { available_terms: number[] }> | null
  required_documents: string[]
  eligibility_rules: Record<string, unknown>
  is_active: boolean
  applications_count: number
  created_at: string
  updated_at: string
}

export interface CreateProductPayload {
  name: string
  code: string
  type: AdminProduct['type']
  description?: string
  min_amount: number
  max_amount: number
  min_term_months?: number
  max_term_months?: number
  interest_rate: number
  opening_commission: number
  late_fee_rate?: number
  payment_frequencies: string[]
  term_config?: Record<string, { available_terms: number[] }>
  required_documents?: string[]
  eligibility_rules?: Record<string, unknown>
  is_active?: boolean
}

// User types
export interface AdminUser {
  id: string
  name: string
  email: string
  phone: string | null
  role: 'ADMIN' | 'SUPERVISOR' | 'ANALYST'
  is_active: boolean
  last_login_at: string | null
  created_at: string
  updated_at: string
}

export interface AdminUserDetail extends AdminUser {
  stats: {
    total_assigned: number
    pending_review: number
  }
}

export interface CreateUserPayload {
  name: string
  email: string
  phone?: string
  role: AdminUser['role']
  password?: string
}

export interface UpdateUserPayload {
  name?: string
  email?: string
  phone?: string
  role?: AdminUser['role']
  password?: string
  is_active?: boolean
}

// Note types
export interface ApplicationNote {
  id: string
  text: string
  author: string
  is_internal: boolean
  created_at: string
}

// Document types for admin review
export interface AdminDocument {
  id: string
  type: string
  name: string
  status: 'PENDING' | 'APPROVED' | 'REJECTED'
  rejection_reason: string | null
  rejection_comment: string | null
  uploaded_at: string
  reviewed_at: string | null
}

// Reference types for admin verification
export interface AdminReference {
  id: string
  full_name: string
  relationship: string
  phone: string
  verified: boolean
  verification_result: 'VERIFIED' | 'NOT_VERIFIED' | 'NO_ANSWER' | null
  verification_notes: string | null
  verified_at: string | null
}

// Report types
export interface ApplicationReportItem {
  folio: string
  created_at: string
  applicant: string | null
  curp: string | null
  product: string | null
  requested_amount: number
  approved_amount: number
  term_months: number
  status: string
  approved_at: string | null
  disbursed_at: string | null
}

export interface ApplicationReportSummary {
  total: number
  total_requested: number
  total_approved: number
  by_status: Record<string, number>
}

export interface DisbursementReportItem {
  folio: string
  disbursed_at: string
  applicant: string | null
  product: string | null
  amount: number
  term_months: number
  monthly_payment: number
  reference: string | null
  bank: string | null
  clabe: string | null
}

export interface DisbursementReportSummary {
  total_disbursements: number
  total_amount: number
  by_product: Record<string, { count: number; amount: number }>
}

export interface ReportPeriod {
  start: string
  end: string
}

// Application types for admin
export interface AdminApplication {
  id: string
  folio: string
  status: string
  applicant: {
    id: string
    name: string
    phone: string
    email: string | null
  } | null
  product: {
    id: string
    name: string
    type: string
  } | null
  requested_amount: number
  approved_amount: number | null
  term_months: number
  payment_frequency: string
  monthly_payment: number
  assigned_to: string | null
  risk_level: string | null
  created_at: string
  updated_at: string
}

export interface AdminApplicationDetail extends Omit<AdminApplication, 'applicant'> {
  applicant: {
    id: string
    full_name: string
    first_name: string
    last_name_1: string
    last_name_2: string | null
    email: string | null
    phone: string
    curp: string
    rfc: string | null
    birth_date: string
    nationality: string
    gender: string
  } | null
  address: {
    street: string
    ext_number: string
    int_number: string | null
    neighborhood: string
    postal_code: string
    municipality: string
    state: string
    housing_type: string
    years_living: number
    months_living: number
  } | null
  employment: {
    type: string
    company_name: string | null
    position: string | null
    monthly_income: number
    seniority_months: number
  } | null
  loan: {
    product_name: string
    product_id: string
    requested_amount: number
    approved_amount: number | null
    term_months: number
    payment_frequency: string
    interest_rate: number
    opening_commission: number
    monthly_payment: number
    total_to_pay: number
    cat: number | null
    purpose: string
    purpose_description: string | null
  }
  risk: {
    score: number | null
    level: string | null
    data: Record<string, unknown> | null
  }
  documents: {
    id: string
    type: string
    name: string
    status: string
    rejection_reason: string | null
    rejection_comment: string | null
    uploaded_at: string
    reviewed_at: string | null
  }[]
  references: {
    id: string
    full_name: string
    relationship: string
    phone: string
    verified: boolean
    verification_result: string | null
    verification_notes: string | null
    verified_at: string | null
  }[]
  notes: {
    id: string
    text: string
    author: string
    is_internal: boolean
    created_at: string
  }[]
  timeline: {
    id: string
    action: string
    description: string
    author: string
    created_at: string
  }[]
  rejection_reason: string | null
  internal_notes: string | null
  disbursement_reference: string | null
  approved_at: string | null
  disbursed_at: string | null
}

export interface ApplicationFilters {
  status?: string
  search?: string
  date_from?: string
  date_to?: string
  assigned_to?: string
  sort_by?: string
  sort_order?: 'asc' | 'desc'
  per_page?: number
  page?: number
}

export interface CounterOfferPayload {
  amount: number
  term_months: number
  interest_rate: number
  payment_frequency: string
  reason?: string
}

const adminService = {
  // Dashboard
  getDashboard: async () => {
    const response = await api.get<{ data: DashboardData }>('/admin/dashboard')
    return response.data.data
  },

  getDashboardStats: async (period?: number) => {
    const response = await api.get<{ data: DashboardStats }>('/admin/dashboard/stats', {
      params: { period },
    })
    return response.data.data
  },

  // Applications
  getApplications: async (filters?: ApplicationFilters) => {
    const response = await api.get<{
      data: AdminApplication[]
      meta: { current_page: number; last_page: number; per_page: number; total: number }
    }>('/admin/applications', { params: filters })
    return response.data
  },

  getApplication: async (id: string) => {
    const response = await api.get<{ data: AdminApplicationDetail }>(`/admin/applications/${id}`)
    return response.data.data
  },

  updateApplicationStatus: async (id: string, status: string, reason?: string, extra?: Record<string, string>) => {
    const response = await api.put<{ message: string; data: AdminApplication }>(
      `/admin/applications/${id}/status`,
      { status, reason, ...extra }
    )
    return response.data.data
  },

  createCounterOffer: async (id: string, data: CounterOfferPayload) => {
    const response = await api.post<{ message: string; data: AdminApplication }>(
      `/admin/applications/${id}/counter-offer`,
      data
    )
    return response.data.data
  },

  assignApplication: async (id: string, userId: string) => {
    const response = await api.put<{ message: string; data: AdminApplication }>(
      `/admin/applications/${id}/assign`,
      { user_id: userId }
    )
    return response.data.data
  },

  addNote: async (id: string, content: string, isInternal = true) => {
    const response = await api.post<{ message: string; data: ApplicationNote }>(`/admin/applications/${id}/notes`, {
      content,
      is_internal: isInternal,
    })
    return response.data
  },

  // Document review
  approveDocument: async (applicationId: string, documentId: string) => {
    const response = await api.put<{ message: string; data: AdminDocument }>(
      `/admin/applications/${applicationId}/documents/${documentId}/approve`
    )
    return response.data
  },

  rejectDocument: async (applicationId: string, documentId: string, reason: string, comment?: string) => {
    const response = await api.put<{ message: string; data: AdminDocument }>(
      `/admin/applications/${applicationId}/documents/${documentId}/reject`,
      { reason, comment }
    )
    return response.data
  },

  // Reference verification
  verifyReference: async (
    applicationId: string,
    referenceId: string,
    result: 'VERIFIED' | 'NOT_VERIFIED' | 'NO_ANSWER',
    notes?: string
  ) => {
    const response = await api.put<{ message: string; data: AdminReference }>(
      `/admin/applications/${applicationId}/references/${referenceId}/verify`,
      { result, notes }
    )
    return response.data
  },

  // Products
  getProducts: async () => {
    const response = await api.get<{ data: AdminProduct[] }>('/admin/products')
    return response.data.data
  },

  createProduct: async (data: CreateProductPayload) => {
    const response = await api.post<{ message: string; data: AdminProduct }>('/admin/products', data)
    return response.data.data
  },

  updateProduct: async (id: string, data: Partial<CreateProductPayload>) => {
    const response = await api.put<{ message: string; data: AdminProduct }>(`/admin/products/${id}`, data)
    return response.data.data
  },

  deleteProduct: async (id: string) => {
    const response = await api.delete<{ message: string }>(`/admin/products/${id}`)
    return response.data
  },

  // Users
  getUsers: async (filters?: { role?: string; active?: boolean; search?: string }) => {
    const response = await api.get<{ data: AdminUser[] }>('/admin/users', { params: filters })
    return response.data.data
  },

  createUser: async (data: CreateUserPayload) => {
    const response = await api.post<{ message: string; data: AdminUser; temporary_password?: string }>(
      '/admin/users',
      data
    )
    return response.data
  },

  updateUser: async (id: string, data: UpdateUserPayload) => {
    const response = await api.put<{ message: string; data: AdminUser }>(`/admin/users/${id}`, data)
    return response.data.data
  },

  deleteUser: async (id: string) => {
    const response = await api.delete<{ message: string }>(`/admin/users/${id}`)
    return response.data
  },

  // Reports
  getApplicationsReport: async (startDate: string, endDate: string, status?: string) => {
    const response = await api.get<{ data: ApplicationReportItem[]; summary: ApplicationReportSummary; period: ReportPeriod }>(
      '/admin/reports/applications',
      { params: { start_date: startDate, end_date: endDate, status } }
    )
    return response.data
  },

  getDisbursementsReport: async (startDate: string, endDate: string) => {
    const response = await api.get<{ data: DisbursementReportItem[]; summary: DisbursementReportSummary; period: ReportPeriod }>(
      '/admin/reports/disbursements',
      { params: { start_date: startDate, end_date: endDate } }
    )
    return response.data
  },
}

export default adminService
