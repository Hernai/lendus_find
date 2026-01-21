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

/**
 * Auth response from OTP verify.
 * Backend returns: { token, is_new_user, user }
 */
export interface V2AuthResponse {
  token: string
  is_new_user?: boolean
  user: V2ApplicantUser | V2StaffUser
  // Legacy fields for backwards compatibility
  token_type?: 'Bearer'
  expires_at?: string
}

/**
 * Applicant user as returned by login endpoints.
 * Backend formatUserResponse() returns these fields.
 */
export interface V2ApplicantUser {
  id: string
  tenant_id: string
  phone: string
  email: string | null
  has_pin: boolean
  is_active: boolean
  onboarding_step: number
  onboarding_completed: boolean
  preferences: Record<string, unknown> | null
  created_at: string
  // Optional fields (not always included)
  person_id?: string | null
  person?: V2Person
  last_login_at?: string | null
}

/**
 * Staff user as returned by auth endpoints.
 * Backend formatStaffResponse() returns profile as nested object.
 */
export interface V2StaffUser {
  id: string
  email: string
  role: 'ANALYST' | 'SUPERVISOR' | 'ADMIN' | 'SUPER_ADMIN'
  is_staff?: boolean
  is_active: boolean
  // Permissions can be array (legacy) or object (V2)
  permissions?: string[] | Record<string, boolean>
  last_login_at?: string | null
  // Profile data - nested object from backend auth
  profile?: {
    first_name: string
    last_name: string
    last_name_2?: string | null
    full_name: string
    initials: string
    phone?: string | null
    avatar_url?: string | null
    title?: string | null
  }
  // Legacy/convenience fields (may be flattened by some endpoints like user.staff.service)
  name?: string
  first_name?: string | null
  last_name?: string | null
  last_name_2?: string | null
  phone?: string | null
  title?: string | null
  initials?: string
  tenant_id?: string
  created_at?: string
  updated_at?: string
  stats?: {
    total_assigned: number
    pending_review: number
  }
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

// Account type values come from backend BankAccountType enum via options.bank_account_type
export type V2BankAccountType = string

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

export interface V2FieldVerification {
  status: string
  verified: boolean
  method: string | null
  method_label?: string | null
  rejection_reason: string | null
  notes: string | null
  verified_at: string | null
  verified_by: string | null
  is_locked?: boolean
  metadata?: Record<string, unknown>
}

// =====================================================
// Application Detail Response Types (V2 Staff API)
// =====================================================

/**
 * Loan data as returned by the V2 API (flat structure).
 */
export interface V2ApplicationLoan {
  product_id: string
  product_name: string | null
  product_type: string | null
  requested_amount: number
  requested_term_months: number
  purpose: string | null
  purpose_label: string | null
  purpose_description: string | null
  interest_rate: number | null
  monthly_payment: number | null
  total_interest: number | null
  total_amount: number | null
  cat: number | null
  approved_amount: number | null
  approved_term_months: number | null
  approved_interest_rate: number | null
  has_counter_offer: boolean
  counter_offer: V2CounterOffer | null
  counter_offer_accepted: boolean | null
  risk_level: string | null
  risk_data: Record<string, unknown> | null
}

/**
 * Person data nested inside applicant.
 */
export interface V2ApplicationPerson {
  id: string
  personal_data: {
    first_name: string
    last_name_1: string
    last_name_2: string | null
    full_name: string
    birth_date: string | null
    birth_state: string | null
    gender: string | null
    nationality: string | null
    marital_status: string | null
    education_level: string | null
    dependents_count: number
  }
  identifications: {
    curp: string | null
    rfc: string | null
  }
  contact: {
    email: string | null
    phone: string | null
  }
  address: V2ApplicationAddress | null
  employment: V2ApplicationEmployment | null
  references: V2ApplicationReference[]
  bank_accounts: V2ApplicationBankAccount[]
  kyc_status: string
  kyc_verified_at: string | null
  profile_completeness: number
}

/**
 * Company data nested inside applicant.
 */
export interface V2ApplicationCompany {
  id: string
  legal_name: string
  trade_name: string | null
  rfc: string
  contact: {
    email: string | null
    phone: string | null
  }
}

/**
 * Applicant wrapper with type discriminator.
 */
export interface V2ApplicationApplicant {
  type: 'INDIVIDUAL' | 'COMPANY'
  person?: V2ApplicationPerson
  company?: V2ApplicationCompany
}

/**
 * Address as returned in application detail.
 */
export interface V2ApplicationAddress {
  id: string
  type: string
  street: string
  exterior_number: string
  interior_number: string | null
  neighborhood: string
  municipality: string
  state: string
  postal_code: string
  country: string
  housing_type: string | null
  years_at_address: number | null
  months_at_address: number | null
  is_current: boolean
  verification_status: string | null
}

/**
 * Employment as returned in application detail.
 */
export interface V2ApplicationEmployment {
  id: string
  employment_type: string
  employer_name: string | null
  employer_rfc: string | null
  employer_phone: string | null
  job_title: string | null
  department: string | null
  monthly_income: number | null
  additional_income: number | null
  payment_frequency: string | null
  start_date: string | null
  years_employed: number | null
  months_employed: number | null
  is_current: boolean
  verification_status: string | null
}

/**
 * Reference as returned in application detail.
 */
export interface V2ApplicationReference {
  id: string
  full_name: string
  first_name: string | null
  last_name_1: string | null
  last_name_2: string | null
  phone: string
  email: string | null
  relationship: string
  type: string
  years_known: number | null
  verification_status: string | null
  verified_at: string | null
  verification_notes: string | null
}

/**
 * Bank account as returned in application detail.
 */
export interface V2ApplicationBankAccount {
  id: string
  bank_name: string
  bank_code: string | null
  clabe: string
  account_number: string | null
  account_type: string | null
  holder_name: string | null
  is_primary: boolean
  is_verified: boolean
  verified_at: string | null
  created_at: string | null
}

/**
 * Verification section in application detail.
 */
export interface V2ApplicationVerification {
  kyc_status: string
  kyc_verified_at: string | null
  fields: Record<string, V2FieldVerification>
  signature: V2Signature
  checklist: Record<string, unknown>
}

/**
 * Document as returned in application detail.
 */
export interface V2ApplicationDocument {
  id: string
  type: string
  category: string | null
  file_name: string
  mime_type: string
  file_size: number
  status: 'PENDING' | 'APPROVED' | 'REJECTED'
  rejection_reason: string | null
  reviewed_at: string | null
  ocr_data: Record<string, unknown> | null
  created_at: string | null
  is_kyc_locked: boolean
}

/**
 * Workflow section in application detail.
 */
export interface V2ApplicationWorkflow {
  assigned_to: {
    id: string
    name: string
    email: string
  } | null
  status_history: V2StatusHistoryEntry[]
  notes: V2ApplicationNote[]
}

/**
 * Integration section in application detail.
 */
export interface V2ApplicationIntegration {
  external_id: string | null
  external_system: string | null
  synced_at: string | null
  snapshot_data: Record<string, unknown> | null
}

/**
 * Full application detail response from V2 Staff API.
 */
export interface V2ApplicationDetail {
  id: string
  folio: string
  status: V2ApplicationStatus
  status_label: string
  applicant_type: 'individual' | 'company'
  created_at: string
  updated_at: string
  submitted_at: string | null
  loan: V2ApplicationLoan
  required_documents: string[]
  applicant: V2ApplicationApplicant | null
  verification: V2ApplicationVerification
  documents: V2ApplicationDocument[]
  workflow: V2ApplicationWorkflow
  integration: V2ApplicationIntegration
}

export interface V2Application {
  id: string
  tenant_id?: string
  folio?: string
  applicant_id?: string
  product_id: string
  requested_amount: number
  requested_term_months: number
  term_months?: number // Alias for requested_term_months (backward compatibility)
  approved_amount: number | null
  approved_term_months: number | null
  interest_rate: number | null
  payment_frequency: V2PaymentFrequency
  monthly_payment: number | null
  opening_commission: number
  total_interest: number
  total_amount: number
  cat: number
  purpose: string | null
  has_counter_offer?: boolean
  status: V2ApplicationStatus
  status_label?: string
  status_reason: string | null
  status_changed_at: string | null
  status_changed_by: string | null
  assigned_to_id: string | null
  assigned_at: string | null
  submitted_at: string | null
  approved_at: string | null
  rejected_at: string | null
  decision_at: string | null
  disbursed_at: string | null
  disbursement_reference: string | null
  risk_score: number | null
  risk_assessment: Record<string, unknown> | null
  counter_offer: V2CounterOffer | null
  status_history?: V2StatusHistoryEntry[]
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
  // Field-level verifications
  field_verifications?: Record<string, V2FieldVerification>
  // Signature data
  signature?: V2Signature
  // Rejection info for corrections UI
  has_rejected_items?: boolean
  rejected_fields_count?: number
  rejected_documents_count?: number
}

export interface V2Signature {
  has_signed: boolean
  signature_base64?: string | null
  signature_date?: string | null
  signature_ip?: string | null
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
  // Event type info (for lifecycle events)
  event_type?: string
  event_label?: string
  is_lifecycle_event?: boolean

  // Status change info (for status changes)
  from_status?: string | null
  from_status_label?: string | null
  to_status?: string | null
  to_status_label?: string | null

  // Common fields
  changed_by?: string | null
  changed_by_type?: string | null
  notes?: string | null
  created_at?: string

  // Context (IP address, user agent)
  ip_address?: string | null
  user_agent?: string | null

  // Event-specific metadata
  metadata?: {
    document_type?: string | null
    document_type_label?: string | null
    changed_fields?: string[] | null
    step_number?: number | null
    step_label?: string | null
    is_valid?: boolean | null
    matched?: boolean | null
    score?: number | null
    bank_name?: string | null
    reference_type?: string | null
    postal_code?: string | null
    employment_type?: string | null
    [key: string]: unknown
  }

  // Legacy format (kept for backwards compatibility)
  status?: V2ApplicationStatus
  reason?: string | null
  timestamp?: string
}

export interface V2ApplicationCreatePayload {
  product_id: string
  requested_amount: number
  term_months: number
  payment_frequency?: V2PaymentFrequency
  purpose?: string
  purpose_description?: string
  simulation_data?: Record<string, unknown>
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
  // KYC lock indicator (from backend)
  is_kyc_locked?: boolean
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

/**
 * Payload for changing application status.
 * Backend expects: status, notes
 */
export interface V2ChangeStatusPayload {
  status: V2ApplicationStatus
  notes?: string
}

/**
 * Payload for approving an application.
 * Backend expects: amount, term_months, interest_rate, notes
 */
export interface V2ApprovePayload {
  amount?: number
  term_months?: number
  interest_rate?: number
  notes?: string
}

/**
 * Payload for rejecting an application.
 * Backend expects: reason, notes
 */
export interface V2RejectPayload {
  reason: string
  notes?: string
}

/**
 * Payload for creating a counter-offer.
 * Backend expects: amount, term_months, interest_rate, reason
 */
export interface V2CounterOfferCreatePayload {
  amount: number
  term_months: number
  interest_rate?: number
  reason?: string
}

/**
 * Payload for setting risk assessment.
 * Backend expects: level (LOW, MEDIUM, HIGH, VERY_HIGH), data
 */
export interface V2RiskAssessmentPayload {
  level: 'LOW' | 'MEDIUM' | 'HIGH' | 'VERY_HIGH'
  data?: Record<string, unknown>
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

// =====================================================
// Profile Types (V2 Applicant Profile API)
// =====================================================

export interface V2PersonalData {
  first_name: string
  last_name_1: string
  last_name_2: string | null
  full_name: string
  birth_date: string | null
  birth_state: string | null
  birth_country: string | null
  age: number | null
  gender: 'M' | 'F' | null
  gender_label: string | null
  nationality: string | null
  marital_status: string | null
  marital_status_label: string | null
  education_level: string | null
  education_level_label: string | null
  dependents_count: number | null
}

export interface V2Identifications {
  curp: string | null
  curp_verified: boolean
  rfc: string | null
  rfc_verified: boolean
  ine: {
    clave_elector: string | null
    ocr: string | null
    folio: string | null
    expiration_date: string | null
    verified: boolean
  } | null
}

export interface V2Profile {
  id: string
  personal_data: V2PersonalData
  identifications: V2Identifications
  address: V2ProfileAddress | null
  employment: V2ProfileEmployment | null
  bank_account: V2ProfileBankAccount | null
  bank_accounts: V2ProfileBankAccount[]
  references: V2ProfileReference[]
  profile_completeness: number
  missing_data: string[]
  kyc_status: string
  is_kyc_verified: boolean
}

export interface V2ProfileSummary {
  id: string
  full_name: string
  profile_completeness: number
  kyc_status: string
  is_kyc_verified: boolean
  has_address: boolean
  has_employment: boolean
  has_bank_account: boolean
}

export interface V2ProfileAddress {
  id: string
  street: string
  ext_number: string | null
  int_number: string | null
  neighborhood: string
  municipality: string | null
  city: string | null
  state: string
  postal_code: string
  country: string
  housing_type: string | null
  years_at_address: number | null
  months_at_address: number | null
  full_address?: string
  is_verified: boolean
}

export interface V2ProfileEmployment {
  id: string
  employment_type: string
  company_name: string | null
  position: string | null
  department: string | null
  work_phone: string | null
  monthly_income: number | null
  payment_frequency: string | null
  years_employed: number | null
  months_employed: number | null
  seniority_months: number | null
  start_date: string | null
  is_verified: boolean
  income_verified: boolean
}

export interface V2ProfileBankAccount {
  id: string
  bank_name: string
  bank_code: string | null
  clabe: string
  clabe_masked: string | null
  account_number: string | null
  holder_name: string
  account_type: string | null
  is_primary: boolean
  is_verified: boolean
}

export interface V2ProfileReference {
  id: string
  type: 'PERSONAL' | 'WORK'
  // Individual name fields from V2 API
  first_name?: string
  last_name_1?: string
  last_name_2?: string
  full_name: string
  phone: string
  relationship: string
  years_known: number | null
  is_verified?: boolean
  verification_status?: string
}

export interface V2ClabeValidation {
  is_valid: boolean
  bank_code: string | null
  bank_name: string | null
}
