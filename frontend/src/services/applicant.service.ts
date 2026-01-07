import { api } from './api'
import type { Applicant, Address, EmploymentRecord, BankAccount } from '@/types'

// Response types
export interface ApplicantResponse {
  data: Applicant | null
}

export interface ApplicantUpdateResponse {
  message: string
  data: Applicant
}

// Payload types for updates
export interface PersonalDataPayload {
  first_name: string
  last_name_1: string
  last_name_2?: string
  birth_date: string
  gender: 'M' | 'F'
  marital_status?: string
  nationality?: string
  education_level?: string
  dependents_count?: number
  curp: string
  rfc?: string
  ine_clave?: string
  phone: string
  phone_secondary?: string
  email?: string
}

export interface AddressPayload {
  type?: 'HOME' | 'WORK' | 'FISCAL' | 'CORRESPONDENCE'
  is_primary?: boolean
  street: string
  ext_number: string
  int_number?: string
  neighborhood: string
  postal_code: string
  municipality?: string
  city: string
  state: string
  country?: string
  between_streets?: string
  references?: string
  housing_type?: string
  years_at_address?: number
  months_at_address?: number
  monthly_rent?: number
}

export interface EmploymentPayload {
  is_current?: boolean
  employment_type: string
  occupation?: string
  company_name?: string
  company_rfc?: string
  company_industry?: string
  company_phone?: string
  company_address?: string
  job_title?: string
  department?: string
  start_date?: string
  seniority_years?: number
  contract_type?: string
  monthly_income: number
  monthly_net_income?: number
  payment_frequency?: string
  other_income?: number
  other_income_source?: string
  supervisor_name?: string
  supervisor_phone?: string
}

export interface BankAccountPayload {
  type?: 'DISBURSEMENT' | 'PAYMENT' | 'BOTH'
  is_primary?: boolean
  bank_name: string
  bank_code?: string
  clabe: string
  account_number?: string
  account_type?: string
  holder_name: string
  holder_rfc?: string
  is_own_account?: boolean
}

const applicantService = {
  /**
   * Get current applicant profile with all related data
   */
  getProfile: async (): Promise<Applicant | null> => {
    const response = await api.get<ApplicantResponse>('/applicant')
    return response.data.data
  },

  /**
   * Create applicant profile
   */
  createProfile: async (data: Partial<PersonalDataPayload>): Promise<Applicant> => {
    const response = await api.post<ApplicantUpdateResponse>('/applicant', data)
    return response.data.data
  },

  /**
   * Update personal data (Step 1 & 2 - Personal info + Identification)
   */
  updatePersonalData: async (data: PersonalDataPayload): Promise<Applicant> => {
    const response = await api.put<ApplicantUpdateResponse>('/applicant/personal-data', data)
    return response.data.data
  },

  /**
   * Update/Add address (Step 3)
   */
  updateAddress: async (data: AddressPayload): Promise<Applicant> => {
    const response = await api.put<ApplicantUpdateResponse>('/applicant/address', data)
    return response.data.data
  },

  /**
   * Update/Add employment info (Step 4)
   */
  updateEmployment: async (data: EmploymentPayload): Promise<Applicant> => {
    const response = await api.put<ApplicantUpdateResponse>('/applicant/employment', data)
    return response.data.data
  },

  /**
   * Update/Add bank account info
   */
  updateBankAccount: async (data: BankAccountPayload): Promise<Applicant> => {
    const response = await api.put<ApplicantUpdateResponse>('/applicant/bank-account', data)
    return response.data.data
  },

  /**
   * Save signature (Step 8)
   */
  saveSignature: async (signatureBase64: string): Promise<{ signed_at: string }> => {
    const response = await api.post<{ message: string; data: { signed_at: string } }>(
      '/applicant/signature',
      { signature: signatureBase64 }
    )
    return response.data.data
  },

  // ============================================
  // Related data management (addresses, employment, bank accounts)
  // ============================================

  /**
   * Get all addresses for applicant
   */
  getAddresses: async (): Promise<Address[]> => {
    const response = await api.get<{ data: Address[] }>('/applicant/addresses')
    return response.data.data
  },

  /**
   * Add a new address
   */
  addAddress: async (data: AddressPayload): Promise<Address> => {
    const response = await api.post<{ message: string; data: Address }>('/applicant/addresses', data)
    return response.data.data
  },

  /**
   * Update an address
   */
  updateAddressById: async (addressId: string, data: Partial<AddressPayload>): Promise<Address> => {
    const response = await api.put<{ message: string; data: Address }>(
      `/applicant/addresses/${addressId}`,
      data
    )
    return response.data.data
  },

  /**
   * Delete an address
   */
  deleteAddress: async (addressId: string): Promise<void> => {
    await api.delete(`/applicant/addresses/${addressId}`)
  },

  /**
   * Get all employment records
   */
  getEmploymentRecords: async (): Promise<EmploymentRecord[]> => {
    const response = await api.get<{ data: EmploymentRecord[] }>('/applicant/employment-records')
    return response.data.data
  },

  /**
   * Add employment record
   */
  addEmploymentRecord: async (data: EmploymentPayload): Promise<EmploymentRecord> => {
    const response = await api.post<{ message: string; data: EmploymentRecord }>(
      '/applicant/employment-records',
      data
    )
    return response.data.data
  },

  /**
   * Get all bank accounts
   */
  getBankAccounts: async (): Promise<BankAccount[]> => {
    const response = await api.get<{ data: BankAccount[] }>('/applicant/bank-accounts')
    return response.data.data
  },

  /**
   * Add bank account
   */
  addBankAccount: async (data: BankAccountPayload): Promise<BankAccount> => {
    const response = await api.post<{ message: string; data: BankAccount }>(
      '/applicant/bank-accounts',
      data
    )
    return response.data.data
  },

  /**
   * Validate CLABE number
   */
  validateClabe: async (clabe: string): Promise<{ valid: boolean; bank_name?: string }> => {
    const response = await api.post<{ data: { valid: boolean; bank_name?: string } }>(
      '/applicant/validate-clabe',
      { clabe }
    )
    return response.data.data
  },
}

export default applicantService
