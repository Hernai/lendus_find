// Applicant Types - Credit Applicant Data

export interface Applicant {
  id: string
  tenant_id: string
  user_id: string
  type: ApplicantType
  rfc: string
  curp: string | null
  personal_data: PersonalData
  contact_info: ContactInfo
  address: Address
  employment_info: EmploymentInfo
  kyc_status: KycStatus
  created_at: string
  updated_at: string
}

export type ApplicantType = 'PERSONA_FISICA' | 'PERSONA_MORAL'

export type KycStatus = 'PENDING' | 'IN_PROGRESS' | 'VERIFIED' | 'REJECTED'

export interface PersonalData {
  first_name: string
  middle_name?: string
  last_name: string
  second_last_name?: string
  birth_date: string
  birth_state: string
  gender: 'M' | 'F'
  nationality: string
  marital_status: MaritalStatus
  education_level?: string
}

export type MaritalStatus = 'SOLTERO' | 'CASADO' | 'UNION_LIBRE' | 'DIVORCIADO' | 'VIUDO'

export interface ContactInfo {
  phone: string
  email: string
  secondary_phone?: string
}

export interface Address {
  street: string
  ext_number: string
  int_number?: string
  neighborhood: string
  postal_code: string
  municipality: string
  city: string
  state: string
  country: string
  housing_type: HousingType
  years_living: number
}

export type HousingType = 'PROPIA' | 'RENTADA' | 'FAMILIAR' | 'HIPOTECADA'

export interface EmploymentInfo {
  employment_status: EmploymentStatus
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

export type EmploymentStatus = 'EMPLEADO' | 'INDEPENDIENTE' | 'JUBILADO' | 'SIN_EMPLEO'

export type ContractType = 'INDEFINIDO' | 'TEMPORAL' | 'OBRA_DETERMINADA'

export interface Reference {
  id: string
  applicant_id: string
  full_name: string
  phone: string
  relationship: ReferenceRelationship
  type: ReferenceType
}

export type ReferenceRelationship = 'FAMILY' | 'FRIEND' | 'COWORKER' | 'OTHER'

export type ReferenceType = 'PERSONAL' | 'WORK'
