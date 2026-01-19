/**
 * V2 Applicant Profile Service
 *
 * Handles profile management for authenticated applicants.
 * All endpoints are under /api/v2/applicant/profile
 */

import { api } from '../api'
import type {
  V2ApiResponse,
  V2Profile,
  V2ProfileSummary,
  V2PersonalData,
  V2Identifications,
  V2ProfileAddress,
  V2ProfileEmployment,
  V2ProfileBankAccount,
  V2ProfileReference,
  V2ClabeValidation,
} from '@/types/v2'

const BASE_PATH = '/v2/applicant/profile'

// =====================================================
// Profile Overview
// =====================================================

/**
 * Get complete profile with all relations.
 */
export async function getProfile(): Promise<V2ApiResponse<V2Profile>> {
  const response = await api.get<V2ApiResponse<V2Profile>>(BASE_PATH)
  return response.data
}

/**
 * Get profile summary (minimal data for dashboards).
 */
export async function getProfileSummary(): Promise<V2ApiResponse<V2ProfileSummary>> {
  const response = await api.get<V2ApiResponse<V2ProfileSummary>>(`${BASE_PATH}/summary`)
  return response.data
}

// =====================================================
// Personal Data
// =====================================================

export interface UpdatePersonalDataPayload {
  first_name?: string
  last_name_1?: string
  last_name_2?: string
  birth_date?: string
  birth_state?: string
  birth_country?: string
  gender?: 'M' | 'F'
  nationality?: string
  marital_status?: string
  education_level?: string
  dependents_count?: number
}

/**
 * Update personal data.
 */
export async function updatePersonalData(
  payload: UpdatePersonalDataPayload
): Promise<V2ApiResponse<{ personal_data: V2PersonalData; profile_completeness: number }>> {
  const response = await api.patch<V2ApiResponse<{ personal_data: V2PersonalData; profile_completeness: number }>>(
    `${BASE_PATH}/personal-data`,
    payload
  )
  return response.data
}

// =====================================================
// Identifications
// =====================================================

export interface UpdateIdentificationsPayload {
  curp?: string
  rfc?: string
  ine_clave?: string
  ine_ocr?: string
  ine_folio?: string
  ine_expiration?: string
  passport_number?: string
  passport_issue_date?: string
  passport_expiry_date?: string
}

/**
 * Update identifications (CURP, RFC, INE, Passport).
 */
export async function updateIdentifications(
  payload: UpdateIdentificationsPayload
): Promise<V2ApiResponse<{ identifications: V2Identifications; profile_completeness: number }>> {
  const response = await api.patch<V2ApiResponse<{ identifications: V2Identifications; profile_completeness: number }>>(
    `${BASE_PATH}/identifications`,
    payload
  )
  return response.data
}

// =====================================================
// Address
// =====================================================

export interface UpdateAddressPayload {
  street: string
  ext_number?: string
  int_number?: string
  neighborhood: string
  municipality?: string
  city?: string
  state: string
  postal_code: string
  country?: string
  housing_type: 'OWNED' | 'RENTED' | 'FAMILY' | 'MORTGAGED' | 'EMPLOYER'
  years_at_address?: number
  months_at_address?: number
  monthly_rent?: number
  between_streets?: string
  references?: string
}

/**
 * Get current home address.
 */
export async function getAddress(): Promise<V2ApiResponse<V2ProfileAddress | null>> {
  const response = await api.get<V2ApiResponse<V2ProfileAddress | null>>(`${BASE_PATH}/address`)
  return response.data
}

/**
 * Update home address.
 */
export async function updateAddress(
  payload: UpdateAddressPayload
): Promise<V2ApiResponse<{ address: V2ProfileAddress; profile_completeness: number }>> {
  const response = await api.put<V2ApiResponse<{ address: V2ProfileAddress; profile_completeness: number }>>(
    `${BASE_PATH}/address`,
    payload
  )
  return response.data
}

// =====================================================
// Employment
// =====================================================

export interface UpdateEmploymentPayload {
  employment_type: string
  company_name?: string
  position?: string
  department?: string
  work_phone?: string
  company_address?: string
  employer_rfc?: string
  monthly_income: number
  payment_frequency?: 'WEEKLY' | 'BIWEEKLY' | 'MONTHLY'
  contract_type?: string
  start_date?: string
  seniority_years?: number
  seniority_months?: number
}

/**
 * Get current employment.
 */
export async function getEmployment(): Promise<V2ApiResponse<V2ProfileEmployment | null>> {
  const response = await api.get<V2ApiResponse<V2ProfileEmployment | null>>(`${BASE_PATH}/employment`)
  return response.data
}

/**
 * Update employment.
 */
export async function updateEmployment(
  payload: UpdateEmploymentPayload
): Promise<V2ApiResponse<{ employment: V2ProfileEmployment; profile_completeness: number }>> {
  const response = await api.put<V2ApiResponse<{ employment: V2ProfileEmployment; profile_completeness: number }>>(
    `${BASE_PATH}/employment`,
    payload
  )
  return response.data
}

// =====================================================
// Bank Account
// =====================================================

export interface UpdateBankAccountPayload {
  bank_name: string
  bank_code?: string
  clabe: string
  account_number?: string
  card_number?: string
  holder_name: string
  account_type?: 'DEBIT' | 'PAYROLL' | 'SAVINGS' | 'CHECKING'
}

/**
 * Get primary bank account.
 */
export async function getBankAccount(): Promise<V2ApiResponse<V2ProfileBankAccount | null>> {
  const response = await api.get<V2ApiResponse<V2ProfileBankAccount | null>>(`${BASE_PATH}/bank-account`)
  return response.data
}

/**
 * Update bank account.
 */
export async function updateBankAccount(
  payload: UpdateBankAccountPayload
): Promise<V2ApiResponse<{ bank_account: { id: string; bank_name: string; clabe_masked: string } }>> {
  const response = await api.put<V2ApiResponse<{ bank_account: { id: string; bank_name: string; clabe_masked: string } }>>(
    `${BASE_PATH}/bank-account`,
    payload
  )
  return response.data
}

/**
 * Validate a CLABE number.
 */
export async function validateClabe(clabe: string): Promise<V2ApiResponse<V2ClabeValidation>> {
  const response = await api.post<V2ApiResponse<V2ClabeValidation>>(`${BASE_PATH}/validate-clabe`, { clabe })
  return response.data
}

// =====================================================
// References
// =====================================================

export interface StoreReferencePayload {
  type: 'PERSONAL' | 'WORK'
  first_name: string
  last_name_1: string
  last_name_2?: string
  phone: string
  email?: string
  relationship: string
  years_known?: number
  employer_name?: string
  job_title?: string
}

export interface UpdateReferencePayload {
  first_name?: string
  last_name_1?: string
  last_name_2?: string
  phone?: string
  email?: string
  relationship?: string
  years_known?: number
}

export interface ReferencesListResponse {
  data: V2ProfileReference[]
  meta: {
    total: number
    personal_count: number
    work_count: number
  }
}

/**
 * List all references.
 */
export async function listReferences(): Promise<V2ApiResponse<V2ProfileReference[]> & { meta?: ReferencesListResponse['meta'] }> {
  const response = await api.get<ReferencesListResponse>(`${BASE_PATH}/references`)
  return {
    success: true,
    data: response.data.data,
    meta: response.data.meta,
  }
}

/**
 * Add a reference.
 */
export async function addReference(
  payload: StoreReferencePayload
): Promise<V2ApiResponse<V2ProfileReference>> {
  const response = await api.post<V2ApiResponse<V2ProfileReference>>(`${BASE_PATH}/references`, payload)
  return response.data
}

/**
 * Update a reference.
 */
export async function updateReference(
  referenceId: string,
  payload: UpdateReferencePayload
): Promise<V2ApiResponse<V2ProfileReference>> {
  const response = await api.put<V2ApiResponse<V2ProfileReference>>(`${BASE_PATH}/references/${referenceId}`, payload)
  return response.data
}

/**
 * Delete a reference.
 */
export async function deleteReference(referenceId: string): Promise<V2ApiResponse<void>> {
  const response = await api.delete<V2ApiResponse<void>>(`${BASE_PATH}/references/${referenceId}`)
  return response.data
}

// =====================================================
// Signature
// =====================================================

/**
 * Save digital signature.
 */
export async function saveSignature(signature: string): Promise<V2ApiResponse<{ signed_at: string }>> {
  const response = await api.post<V2ApiResponse<{ signed_at: string }>>(`${BASE_PATH}/signature`, { signature })
  return response.data
}

// =====================================================
// Default Export
// =====================================================

export default {
  // Profile
  getProfile,
  getProfileSummary,
  // Personal data
  updatePersonalData,
  // Identifications
  updateIdentifications,
  // Address
  getAddress,
  updateAddress,
  // Employment
  getEmployment,
  updateEmployment,
  // Bank account
  getBankAccount,
  updateBankAccount,
  validateClabe,
  // References
  listReferences,
  addReference,
  updateReference,
  deleteReference,
  // Signature
  saveSignature,
}
