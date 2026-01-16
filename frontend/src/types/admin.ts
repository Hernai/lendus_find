/**
 * Admin panel types.
 *
 * Centralized type definitions for admin views,
 * extracted from AdminApplicationDetail.vue.
 */

/**
 * Document in admin view.
 */
export interface AdminDocument {
  id: string
  type: string
  name: string
  status: 'PENDING' | 'APPROVED' | 'REJECTED'
  rejection_reason?: string
  rejection_comment?: string
  uploaded_at?: string
  reviewed_at?: string
  mime_type?: string
  metadata?: {
    kyc_validated?: boolean
    face_match_passed?: boolean
    face_match_score?: number
    validation_method?: string
    source?: string
    [key: string]: unknown
  }
  is_kyc_locked?: boolean
}

/**
 * Reference in admin view.
 */
export interface AdminReference {
  id: string
  full_name: string
  relationship: string
  phone: string
  verified: boolean
  verification_result?: 'VERIFIED' | 'NOT_VERIFIED' | 'NO_ANSWER'
  verification_notes?: string
  verified_at?: string
}

/**
 * Bank account in admin view.
 */
export interface AdminBankAccount {
  id: string
  type: string
  bank_name: string
  bank_code: string
  clabe: string
  account_type: string
  account_type_label?: string
  holder_name: string
  holder_rfc?: string
  is_primary: boolean
  is_own_account: boolean
  is_verified: boolean
  created_at?: string
}

/**
 * Application completeness metrics.
 */
export interface ApplicationCompleteness {
  personal_data: boolean
  address: boolean
  employment: boolean
  documents: {
    uploaded: number
    required: number
    approved: number
  }
  references: {
    count: number
    verified: number
  }
  signature: boolean
}

/**
 * Application details for admin view.
 */
export interface AdminApplication {
  id: string
  folio: string
  status: string
  created_at: string
  updated_at: string
  assigned_to?: string
  completeness: ApplicationCompleteness
  required_documents: string[]
  applicant: AdminApplicant
  product: AdminProduct
  simulation?: AdminSimulation
  financial?: AdminFinancial
  notes?: AdminNote[]
  documents?: AdminDocument[]
  references?: AdminReference[]
  bank_accounts?: AdminBankAccount[]
}

/**
 * Applicant data in admin application.
 */
export interface AdminApplicant {
  id: string
  full_name: string
  first_name: string
  last_name_1: string
  last_name_2: string
  email: string
  phone: string
  curp: string
  rfc: string
  birth_date: string
  nationality: string
  gender?: string
  marital_status?: string
  education_level?: string
  dependents_count?: number
  address?: AdminAddress
  employment?: AdminEmployment
}

/**
 * Address data.
 */
export interface AdminAddress {
  street: string
  ext_number: string
  int_number?: string
  neighborhood: string
  postal_code: string
  city: string
  state: string
  municipality?: string
  country?: string
  housing_type?: string
  years_at_address?: number
  months_at_address?: number
}

/**
 * Employment data.
 */
export interface AdminEmployment {
  employment_type: string
  company_name?: string
  position?: string
  seniority_months: number
  monthly_income: number
  other_income?: number
}

/**
 * Product data.
 */
export interface AdminProduct {
  id: string
  name: string
  type: string
}

/**
 * Simulation data.
 */
export interface AdminSimulation {
  amount: number
  term: number
  payment_frequency: string
  interest_rate: number
  monthly_payment: number
  total_payment: number
  cat: number
}

/**
 * Financial data.
 */
export interface AdminFinancial {
  monthly_income: number
  other_income: number
  total_income: number
  monthly_expenses: number
  payment_capacity: number
  debt_ratio: number
}

/**
 * Note data.
 */
export interface AdminNote {
  id: string
  content: string
  type: 'GENERAL' | 'INTERNAL' | 'SYSTEM'
  created_at: string
  created_by: {
    id: string
    name: string
  }
}

/**
 * API log entry for debugging.
 */
export interface ApiLogEntry {
  id: string
  provider: string
  service: string
  endpoint: string
  method: string
  response_status: number
  success: boolean
  error_message?: string
  duration_ms: number
  created_at: string
  request_payload?: Record<string, unknown>
  response_body?: Record<string, unknown>
}

/**
 * Staff user for assignment.
 */
export interface StaffUser {
  id: string
  name: string
  email: string
  role: string
}

/**
 * Document rejection reasons.
 */
export const DOC_REJECT_REASONS = [
  { value: 'ILLEGIBLE', label: 'Documento ilegible' },
  { value: 'EXPIRED', label: 'Documento vencido' },
  { value: 'INCOMPLETE', label: 'Documento incompleto' },
  { value: 'WRONG_DOCUMENT', label: 'Documento incorrecto' },
  { value: 'LOW_QUALITY', label: 'Baja calidad de imagen' },
  { value: 'DATA_MISMATCH', label: 'Datos no coinciden' },
  { value: 'OTHER', label: 'Otro' },
] as const

/**
 * Role labels for display.
 */
export const ROLE_LABELS: Record<string, string> = {
  SUPER_ADMIN: 'Super Admin',
  ADMIN: 'Administrador',
  ANALYST: 'Analista',
  SUPERVISOR: 'Supervisor',
}

/**
 * Get role label for display.
 */
export function getRoleLabel(role: string): string {
  return ROLE_LABELS[role] || role
}
