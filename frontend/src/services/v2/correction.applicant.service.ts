/**
 * V2 Applicant Correction Service
 *
 * Handles data corrections for rejected fields and documents.
 * All endpoints are under /api/v2/applicant/corrections
 */

import { api } from '../api'
import type { V2ApiResponse } from '@/types/v2'

// =====================================================
// Types
// =====================================================

export interface RejectedField {
  id: string
  field_name: string
  field_label: string
  current_value: string
  rejection_reason: string | null
  rejected_at: string | null
}

export interface RejectedDocument {
  id: string
  application_id: string | null
  type: string
  type_label: string
  name: string
  rejection_reason: string | null
  rejected_at: string | null
}

export interface CorrectionHistoryEntry {
  field_name: string | null
  field_label: string
  old_value: string | null
  new_value: string | null
  rejection_reason: string | null
  corrected_by: {
    id: string
    name: string
  } | null
  corrected_at: string | null
}

export interface PendingApplication {
  id: string
  folio: string
  status: string
  updated_at: string | null
}

export interface ApplicantData {
  first_name: string
  last_name_1: string
  last_name_2: string | null
  curp: string | null
  rfc: string | null
  ine_clave: string | null
  birth_date: string | null
  phone: string | null
  email: string | null
  address: {
    street: string
    ext_number: string
    int_number: string | null
    neighborhood: string
    postal_code: string
    municipality: string
    state: string
    housing_type: string | null
    years_at_address: number
    months_at_address: number
  } | null
  employment: {
    type: string
    company_name: string
    position: string
    monthly_income: number
    seniority_years: number
    seniority_months: number
  }
}

export interface CorrectionsIndexResponse {
  rejected_fields: RejectedField[]
  rejected_documents: RejectedDocument[]
  correction_history: CorrectionHistoryEntry[]
  applicant_data: ApplicantData
  pending_applications: PendingApplication[]
  has_corrections_pending: boolean
}

export interface CorrectionDetailResponse {
  field_name: string
  field_label: string
  current_value: unknown
  status: string
  rejection_reason: string | null
  rejected_at: string | null
  rejected_by: string | null
}

export interface Geolocation {
  latitude: number
  longitude: number
  accuracy?: number
  timestamp?: number
}

export interface SubmitCorrectionPayload {
  field_name: string
  new_value: unknown
  geolocation?: Geolocation
}

export interface SubmitCorrectionResponse {
  field_name: string
  status: string
}

const BASE_PATH = '/v2/applicant/corrections'

// =====================================================
// API Functions
// =====================================================

/**
 * Get list of pending corrections (rejected fields and documents).
 */
export async function index(): Promise<V2ApiResponse<CorrectionsIndexResponse>> {
  const response = await api.get<V2ApiResponse<CorrectionsIndexResponse>>(BASE_PATH)
  return response.data
}

/**
 * Get correction details for a specific field.
 */
export async function show(fieldName: string): Promise<V2ApiResponse<CorrectionDetailResponse>> {
  const response = await api.get<V2ApiResponse<CorrectionDetailResponse>>(`${BASE_PATH}/${fieldName}`)
  return response.data
}

/**
 * Submit a correction for a rejected field.
 */
export async function submit(payload: SubmitCorrectionPayload): Promise<V2ApiResponse<SubmitCorrectionResponse>> {
  const response = await api.post<V2ApiResponse<SubmitCorrectionResponse>>(BASE_PATH, payload)
  return response.data
}

// =====================================================
// Default Export
// =====================================================

export default {
  index,
  show,
  submit,
}
