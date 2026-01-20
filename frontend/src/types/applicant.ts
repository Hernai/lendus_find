// Applicant Types - Credit Applicant Data (Normalized Structure)

import type { PaymentFrequency } from './tenant'

export interface Applicant {
  id: string
  tenant_id: string
  user_id: string
  type: ApplicantType

  // Personal Data (normalized fields)
  first_name: string
  last_name_1: string
  last_name_2?: string
  full_name: string
  birth_date: string | null
  gender: 'M' | 'F' | null
  marital_status: MaritalStatus | null
  nationality: string
  education_level?: string
  dependents_count?: number

  // Identification
  curp: string | null
  rfc: string | null
  ine_clave: string | null
  ine_ocr?: string | null
  ine_folio?: string | null
  passport_number?: string | null
  passport_issue_date?: string | null
  passport_expiry_date?: string | null

  // Contact
  phone: string | null
  phone_secondary?: string
  email: string | null

  // KYC
  kyc_status: KycStatus
  kyc_completed_at?: string

  // Signature
  has_signed: boolean
  signed_at?: string

  // Related data (loaded via relationships)
  primary_address?: Address
  addresses?: Address[]
  current_employment?: EmploymentRecord
  employment_records?: EmploymentRecord[]
  primary_bank_account?: BankAccount
  bank_accounts?: BankAccount[]

  created_at: string
  updated_at: string
}

export type ApplicantType = 'PERSONA_FISICA' | 'PERSONA_MORAL'

export type KycStatus = 'PENDING' | 'IN_PROGRESS' | 'VERIFIED' | 'REJECTED'

export type MaritalStatus = 'SINGLE' | 'MARRIED' | 'COMMON_LAW' | 'DIVORCED' | 'WIDOWED' | 'SEPARATED'

// Address (normalized table)
export interface Address {
  id: string
  type: AddressType
  is_primary: boolean
  street: string
  ext_number: string
  int_number?: string
  neighborhood: string
  postal_code: string
  municipality?: string
  city: string
  state: string
  country: string
  between_streets?: string
  references?: string
  housing_type: HousingType | null
  housing_type_label?: string
  years_at_address?: number
  months_at_address?: number
  total_months_at_address?: number
  monthly_rent?: number
  is_verified: boolean
  verified_at?: string
  full_address?: string
  coordinates?: {
    latitude: number
    longitude: number
  }
  created_at: string
  updated_at: string
}

export type AddressType = 'HOME' | 'WORK' | 'FISCAL' | 'CORRESPONDENCE'

export type HousingType = 'OWNED_PAID' | 'OWNED_MORTGAGE' | 'RENTED' | 'FAMILY' | 'BORROWED' | 'OTHER'

// Employment Record (normalized table)
export interface EmploymentRecord {
  id: string
  is_current: boolean
  employment_type: EmploymentType
  occupation?: string
  company_name?: string
  company_rfc?: string
  company_industry?: string
  company_size?: string
  company_phone?: string
  company_address?: string
  job_title?: string
  position?: string // Backend field name (same as job_title)
  work_phone?: string // Backend field name (same as company_phone)
  department?: string
  start_date?: string
  end_date?: string
  seniority_years?: number
  seniority_months?: number
  contract_type?: ContractType
  monthly_income?: number
  monthly_net_income?: number
  payment_frequency?: PaymentFrequency
  payment_day?: number
  other_income?: number
  other_income_source?: string
  supervisor_name?: string
  supervisor_phone?: string
  is_verified: boolean
  verified_at?: string
  verification_method?: string
  created_at: string
  updated_at: string
}

export type EmploymentType = 'EMPLOYEE' | 'SELF_EMPLOYED' | 'BUSINESS_OWNER' | 'RETIRED' | 'STUDENT' | 'HOMEMAKER' | 'UNEMPLOYED' | 'OTHER'

// Legacy V1 API contract types (Spanish values from backend)
// For new code, use V2ContractType from types/v2
export type ContractType = 'INDEFINIDO' | 'TEMPORAL' | 'OBRA_DETERMINADA' | 'HONORARIOS' | 'COMISION'

// PaymentFrequency is defined in tenant.ts

// Bank Account (normalized table)
export interface BankAccount {
  id: string
  type: BankAccountType
  is_primary: boolean
  bank_name: string
  bank_code?: string
  clabe: string
  clabe_last4?: string
  account_number?: string
  card_number_last4?: string
  account_type: AccountType
  holder_name: string
  holder_rfc?: string
  is_own_account: boolean
  is_verified: boolean
  verified_at?: string
  verification_method?: string
  is_active: boolean
  created_at: string
  updated_at: string
}

export type BankAccountType = 'DISBURSEMENT' | 'PAYMENT' | 'BOTH'

// Account type values come from backend BankAccountType enum via options.bank_account_type
export type AccountType = string

// Reference (existing structure)
export interface Reference {
  id: string
  applicant_id?: string
  application_id?: string
  // V2 API uses separate name fields
  first_name?: string
  last_name_1?: string
  last_name_2?: string
  full_name: string
  phone: string
  email?: string
  relationship: ReferenceRelationship | string
  type: ReferenceType
  address?: string
  years_known?: number
  is_verified?: boolean
  verification_result?: string
  verification_notes?: string
  verified_at?: string
}

export type ReferenceRelationship =
  | 'PARENT'
  | 'SIBLING'
  | 'SPOUSE'
  | 'CHILD'
  | 'UNCLE_AUNT'
  | 'COUSIN'
  | 'GRANDPARENT'
  | 'OTHER_FAMILY'
  | 'FRIEND'
  | 'NEIGHBOR'
  | 'COWORKER'
  | 'BOSS'
  | 'ACQUAINTANCE'
  | 'OTHER'

export type ReferenceType = 'PERSONAL' | 'WORK'

// Legacy interfaces for backwards compatibility during migration
export interface PersonalData {
  first_name: string
  middle_name?: string
  last_name: string
  second_last_name?: string
  birth_date: string
  birth_state?: string
  gender: 'M' | 'F'
  nationality: string
  marital_status: MaritalStatus
  education_level?: string
}

export interface ContactInfo {
  phone: string
  email: string
  secondary_phone?: string
}

export interface EmploymentInfo {
  employment_status: EmploymentType
  company_name?: string
  company_sector?: string
  position?: string
  seniority_months?: number
  monthly_income: number
  other_income?: number
  company_phone?: string
  company_address?: Partial<Address>
  company_rfc?: string
  contract_type?: ContractType
  has_imss?: boolean
  nss?: string
}
