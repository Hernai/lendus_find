/**
 * V2 API Types - Type definitions for V2 API endpoints
 *
 * These types mirror the V2 backend resources and responses.
 */

// =====================================================
// Common Types
// =====================================================

export interface V2ApiResponse<T = unknown> {
  success: boolean
  data?: T
  message?: string
  error?: string
  errors?: Record<string, string[]>
}

export interface V2PaginatedResponse<T> {
  data: T[]
  meta: {
    current_page: number
    from: number | null
    last_page: number
    per_page: number
    to: number | null
    total: number
  }
  links?: {
    first: string | null
    last: string | null
    prev: string | null
    next: string | null
  }
}

// =====================================================
// Authentication Types
// =====================================================

export interface V2OtpRequestPayload {
  type: 'phone' | 'email' | 'whatsapp' | 'PHONE' | 'EMAIL' | 'WHATSAPP'
  identifier: string
  channel?: 'sms' | 'email' | 'whatsapp' | 'SMS' | 'EMAIL' | 'WHATSAPP'
}

export interface V2OtpVerifyPayload {
  type: 'phone' | 'email' | 'whatsapp' | 'PHONE' | 'EMAIL' | 'WHATSAPP'
  identifier: string
  code: string
}

export interface V2CheckUserPayload {
  type: 'phone' | 'email' | 'PHONE' | 'EMAIL'
  identifier: string
}

export interface V2CheckUserResponse {
  exists: boolean
  has_pin: boolean
  auth_methods?: string[]
}

export interface V2PinLoginPayload {
  type: 'phone' | 'email' | 'PHONE' | 'EMAIL'
  identifier: string
  pin: string
}

export interface V2PinSetupPayload {
  pin: string
  pin_confirmation: string
}

export interface V2PinChangePayload {
  current_pin: string
  new_pin: string
  new_pin_confirmation: string
}

export interface V2AuthResponse {
  token: string
  token_type: 'Bearer'
  expires_at: string
  user: V2ApplicantUser | V2StaffUser
}

export interface V2ApplicantUser {
  id: string
  phone: string
  email: string | null
  person_id: string | null
  tenant_id: string
  is_active: boolean
  created_at: string
  person?: V2Person
}

export interface V2StaffUser {
  id: string
  name: string
  email: string
  role: 'ANALYST' | 'SUPERVISOR' | 'ADMIN' | 'SUPER_ADMIN'
  tenant_id: string
  is_active: boolean
  permissions: string[]
  created_at: string
}

export interface V2StaffLoginPayload {
  email: string
  password: string
}

// =====================================================
// Person Types
// =====================================================

export interface V2Person {
  id: string
  tenant_id: string
  first_name: string
  last_name_1: string
  last_name_2: string | null
  full_name: string
  gender: 'M' | 'F' | null
  birth_date: string | null
  birth_state: string | null
  birth_country: string | null
  nationality: string | null
  marital_status: string | null
  education_level: string | null
  dependents_count: number | null
  phone: string | null
  email: string | null
  curp: string | null
  rfc: string | null
  kyc_status: 'PENDING' | 'VERIFIED' | 'REJECTED' | 'EXPIRED'
  kyc_verified_at: string | null
  data_completeness: number
  created_at: string
  updated_at: string
  // Nested relations (when loaded)
  current_identification?: V2Identification
  current_home_address?: V2Address
  current_employment?: V2Employment
  primary_bank_account?: V2BankAccount
  identifications?: V2Identification[]
  addresses?: V2Address[]
  employments?: V2Employment[]
  references?: V2Reference[]
  bank_accounts?: V2BankAccount[]
}

export interface V2PersonCreatePayload {
  first_name: string
  last_name_1: string
  last_name_2?: string
  gender?: 'M' | 'F'
  birth_date?: string
  birth_state?: string
  birth_country?: string
  nationality?: string
  marital_status?: string
  education_level?: string
  dependents_count?: number
  phone?: string
  email?: string
}

export interface V2PersonUpdatePayload extends Partial<V2PersonCreatePayload> {}

// =====================================================
// Identification Types
// =====================================================

export type V2IdentificationType = 'CURP' | 'RFC' | 'INE' | 'PASSPORT' | 'DRIVER_LICENSE'

export interface V2Identification {
  id: string
  person_id: string
  type: V2IdentificationType
  value: string
  issue_date: string | null
  expiry_date: string | null
  issuing_authority: string | null
  verification_status: 'PENDING' | 'VERIFIED' | 'REJECTED' | 'EXPIRED'
  verified_at: string | null
  verified_by: string | null
  rejection_reason: string | null
  metadata: Record<string, unknown> | null
  is_current: boolean
  created_at: string
  updated_at: string
}

export interface V2IdentificationPayload {
  type: V2IdentificationType
  value: string
  issue_date?: string
  expiry_date?: string
  issuing_authority?: string
  metadata?: Record<string, unknown>
}

// =====================================================
// Address Types
// =====================================================

export type V2AddressType = 'HOME' | 'WORK' | 'BILLING' | 'MAILING' | 'OTHER'

export interface V2Address {
  id: string
  person_id?: string
  company_id?: string
  type: V2AddressType
  street: string
  exterior_number: string
  interior_number: string | null
  neighborhood: string
  postal_code: string
  municipality: string
  city: string
  state: string
  country: string
  housing_type: string | null
  housing_status: string | null
  years_at_address: number | null
  months_at_address: number | null
  latitude: number | null
  longitude: number | null
  verification_status: 'PENDING' | 'VERIFIED' | 'REJECTED'
  verified_at: string | null
  verified_by: string | null
  is_current: boolean
  created_at: string
  updated_at: string
}

export interface V2AddressPayload {
  type: V2AddressType
  street: string
  exterior_number: string
  interior_number?: string
  neighborhood: string
  postal_code: string
  municipality: string
  city: string
  state: string
  country?: string
  housing_type?: string
  housing_status?: string
  years_at_address?: number
  months_at_address?: number
}

// =====================================================
// Employment Types
// =====================================================

export type V2EmploymentType = 'EMPLOYED' | 'SELF_EMPLOYED' | 'BUSINESS_OWNER' | 'RETIRED' | 'UNEMPLOYED' | 'STUDENT' | 'OTHER'
export type V2ContractType = 'PERMANENT' | 'TEMPORARY' | 'FREELANCE' | 'CONTRACT' | 'OTHER'
export type V2PaymentFrequency = 'WEEKLY' | 'BIWEEKLY' | 'MONTHLY' | 'OTHER'

export interface V2Employment {
  id: string
  person_id: string
  employment_type: V2EmploymentType
  company_name: string
  position: string | null
  contract_type: V2ContractType | null
  start_date: string
  end_date: string | null
  monthly_income: number
  payment_frequency: V2PaymentFrequency | null
  employer_phone: string | null
  employer_address: string | null
  verification_status: 'PENDING' | 'VERIFIED' | 'REJECTED'
  income_verified: boolean
  verified_at: string | null
  verified_by: string | null
  is_current: boolean
  created_at: string
  updated_at: string
}

export interface V2EmploymentPayload {
  employment_type: V2EmploymentType
  company_name: string
  position?: string
  contract_type?: V2ContractType
  start_date: string
  end_date?: string
  monthly_income: number
  payment_frequency?: V2PaymentFrequency
  employer_phone?: string
  employer_address?: string
}

// =====================================================
// Reference Types
// =====================================================

export type V2ReferenceType = 'PERSONAL' | 'WORK' | 'FAMILY'
export type V2Relationship = 'FRIEND' | 'NEIGHBOR' | 'COWORKER' | 'SUPERVISOR' | 'FAMILY' | 'OTHER'

export interface V2Reference {
  id: string
  person_id: string
  type: V2ReferenceType
  full_name: string
  relationship: V2Relationship
  phone: string
  email: string | null
  years_known: number | null
  verification_status: 'PENDING' | 'VERIFIED' | 'REJECTED' | 'UNREACHABLE'
  verified_at: string | null
  verified_by: string | null
  contact_attempts: number
  last_contact_attempt: string | null
  notes: string | null
  created_at: string
  updated_at: string
}

export interface V2ReferencePayload {
  type: V2ReferenceType
  full_name: string
  relationship: V2Relationship
  phone: string
  email?: string
  years_known?: number
}

// =====================================================
// Bank Account Types
// =====================================================

export type V2BankAccountType = 'CHECKING' | 'SAVINGS' | 'PAYROLL'

export interface V2BankAccount {
  id: string
  owner_type: string
  owner_id: string
  bank_code: string
  bank_name: string
  account_type: V2BankAccountType
  clabe: string
  account_number: string | null
  card_number_last_four: string | null
  account_holder_name: string
  is_primary: boolean
  is_for_disbursement: boolean
  is_verified: boolean
  verified_at: string | null
  verified_by: string | null
  is_active: boolean
  created_at: string
  updated_at: string
}

export interface V2BankAccountPayload {
  bank_code: string
  account_type: V2BankAccountType
  clabe: string
  account_number?: string
  account_holder_name: string
  is_for_disbursement?: boolean
}

// =====================================================
// Application Types
// =====================================================

export type V2ApplicationStatus =
  | 'DRAFT'
  | 'SUBMITTED'
  | 'IN_REVIEW'
  | 'DOCS_PENDING'
  | 'ANALYST_REVIEW'
  | 'SUPERVISOR_REVIEW'
  | 'APPROVED'
  | 'REJECTED'
  | 'CANCELLED'
  | 'SYNCED'

export interface V2Application {
  id: string
  tenant_id: string
  folio: string
  applicant_id: string
  product_id: string
  requested_amount: number
  approved_amount: number | null
  term_months: number
  interest_rate: number | null
  payment_frequency: V2PaymentFrequency
  monthly_payment: number | null
  status: V2ApplicationStatus
  status_reason: string | null
  status_changed_at: string | null
  status_changed_by: string | null
  assigned_to_id: string | null
  assigned_at: string | null
  submitted_at: string | null
  approved_at: string | null
  rejected_at: string | null
  disbursed_at: string | null
  disbursement_reference: string | null
  risk_score: number | null
  risk_assessment: Record<string, unknown> | null
  counter_offer: V2CounterOffer | null
  status_history: V2StatusHistoryEntry[]
  created_at: string
  updated_at: string
  // Relations (when loaded)
  applicant?: V2Person
  product?: V2Product
  assigned_agent?: V2StaffUser
  documents?: V2Document[]
  notes?: V2ApplicationNote[]
  // Pending documents for applicant view
  pending_documents?: V2PendingDocument[]
}

export interface V2PendingDocument {
  type: string
  label: string
  description: string
  required: boolean
}

export interface V2CounterOffer {
  amount: number
  term_months: number
  interest_rate: number
  payment_frequency: V2PaymentFrequency
  monthly_payment: number
  reason: string
  offered_by: string
  offered_at: string
  responded_at: string | null
  accepted: boolean | null
}

export interface V2StatusHistoryEntry {
  status: V2ApplicationStatus
  reason: string | null
  changed_by: string | null
  timestamp: string
}

export interface V2ApplicationCreatePayload {
  product_id: string
  requested_amount: number
  term_months: number
  payment_frequency?: V2PaymentFrequency
  purpose?: string
}

export interface V2ApplicationUpdatePayload {
  requested_amount?: number
  term_months?: number
  payment_frequency?: V2PaymentFrequency
  purpose?: string
}

export interface V2CounterOfferResponsePayload {
  accept: boolean
  reason?: string
}

// =====================================================
// Product Types
// =====================================================

export type V2ProductType = 'SIMPLE' | 'NOMINA' | 'ARRENDAMIENTO' | 'HIPOTECARIO' | 'PYME'

export interface V2Product {
  id: string
  tenant_id: string
  name: string
  type: V2ProductType
  description: string | null
  min_amount: number
  max_amount: number
  min_term_months: number
  max_term_months: number
  interest_rate_annual: number
  allowed_payment_frequencies: V2PaymentFrequency[]
  required_documents: string[]
  eligibility_rules: Record<string, unknown> | null
  is_active: boolean
  created_at: string
  updated_at: string
}

// =====================================================
// Document Types
// =====================================================

export type V2DocumentStatus = 'PENDING' | 'APPROVED' | 'REJECTED'
export type V2DocumentCategory = 'IDENTITY' | 'ADDRESS' | 'INCOME' | 'EMPLOYMENT' | 'OTHER'

export interface V2Document {
  id: string
  tenant_id: string
  documentable_type: string
  documentable_id: string
  type: string
  category: V2DocumentCategory
  file_name: string
  file_path: string
  mime_type: string
  file_size: number
  status: V2DocumentStatus
  rejection_reason: string | null
  reviewed_at: string | null
  reviewed_by: string | null
  is_sensitive: boolean
  ocr_processed: boolean
  ocr_data: Record<string, unknown> | null
  ocr_confidence: number | null
  valid_until: string | null
  version_number: number
  created_at: string
  updated_at: string
  // Computed
  download_url?: string
}

export interface V2DocumentUploadPayload {
  file: File
  type: string
  documentable_type?: string
  documentable_id?: string
  metadata?: Record<string, unknown>
}

export interface V2DocumentType {
  type: string
  label: string
  category: V2DocumentCategory
  description: string
  accepted_formats: string[]
  max_size_mb: number
  is_required: boolean
}

// =====================================================
// Application Note Types
// =====================================================

export interface V2ApplicationNote {
  id: string
  application_id: string
  user_id: string
  content: string
  is_internal: boolean
  created_at: string
  author?: {
    id: string
    name: string
  }
}

export interface V2ApplicationNotePayload {
  content: string
  is_internal?: boolean
}

// =====================================================
// Staff Application Management Types
// =====================================================

export interface V2ApplicationFilters {
  status?: V2ApplicationStatus | V2ApplicationStatus[]
  search?: string
  date_from?: string
  date_to?: string
  assigned_to?: string
  assignment?: 'assigned' | 'unassigned'
  product_id?: string
  stale?: boolean
  sort_by?: 'created_at' | 'updated_at' | 'status' | 'folio' | 'requested_amount'
  sort_order?: 'asc' | 'desc'
  per_page?: number
  page?: number
}

export interface V2AssignApplicationPayload {
  user_id: string
}

export interface V2ChangeStatusPayload {
  status: V2ApplicationStatus
  reason?: string
  internal_note?: string
}

export interface V2ApprovePayload {
  approved_amount: number
  interest_rate: number
  term_months: number
  notes?: string
}

export interface V2RejectPayload {
  reason: string
  internal_note?: string
}

export interface V2CounterOfferCreatePayload {
  amount: number
  term_months: number
  interest_rate: number
  payment_frequency: V2PaymentFrequency
  reason: string
}

export interface V2RiskAssessmentPayload {
  score: number
  factors: Record<string, unknown>
  notes?: string
}

// Lowercase status keys as returned by V2 API
export type V2ApplicationStatusKey =
  | 'draft'
  | 'submitted'
  | 'in_review'
  | 'docs_pending'
  | 'analyst_review'
  | 'supervisor_review'
  | 'approved'
  | 'rejected'
  | 'cancelled'
  | 'synced'

export interface V2ApplicationStatistics {
  total: number
  by_status: Partial<Record<V2ApplicationStatusKey, number>>
  pending_review: number
  pending_documents: number
  approved_today: number
  rejected_today: number
  average_processing_time_hours: number
}

// =====================================================
// CLABE Validation Types
// =====================================================

export interface V2ClabeValidationResult {
  valid: boolean
  bank_code: string
  bank_name: string
  control_digit_valid: boolean
}

// =====================================================
// Company Types (for Persona Moral)
// =====================================================

export type V2CompanyStatus = 'PENDING' | 'VERIFIED' | 'SUSPENDED' | 'CLOSED'
export type V2MemberRole = 'OWNER' | 'LEGAL_REP' | 'ADMIN' | 'MEMBER'
export type V2MemberStatus = 'PENDING' | 'ACTIVE' | 'SUSPENDED' | 'REMOVED'

export interface V2Company {
  id: string
  tenant_id: string
  legal_name: string
  trade_name: string | null
  rfc: string
  incorporation_date: string | null
  legal_structure: string | null
  industry: string | null
  company_size: string | null
  annual_revenue: number | null
  employee_count: number | null
  phone: string | null
  email: string | null
  website: string | null
  status: V2CompanyStatus
  kyb_status: 'PENDING' | 'VERIFIED' | 'REJECTED'
  kyb_verified_at: string | null
  created_at: string
  updated_at: string
  addresses?: V2Address[]
  members?: V2CompanyMember[]
}

export interface V2CompanyMember {
  id: string
  company_id: string
  person_id: string
  role: V2MemberRole
  title: string | null
  ownership_percentage: number | null
  is_authorized_signer: boolean
  status: V2MemberStatus
  joined_at: string
  left_at: string | null
  created_at: string
  person?: V2Person
}

export interface V2CompanyCreatePayload {
  legal_name: string
  trade_name?: string
  rfc: string
  incorporation_date?: string
  legal_structure?: string
  industry?: string
  company_size?: string
  annual_revenue?: number
  employee_count?: number
  phone?: string
  email?: string
  website?: string
}

export interface V2CompanyMemberPayload {
  person_id: string
  role: V2MemberRole
  title?: string
  ownership_percentage?: number
  is_authorized_signer?: boolean
}
