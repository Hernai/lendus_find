/**
 * V2 Staff Loan Service — módulo opt-in (tenant.features.loan_portfolio).
 * Endpoints bajo /api/v2/staff/loans
 */

import { api } from '../api'
import type { V2ApiResponse } from '@/types/v2'
import type { V2Loan, V2LoanExtension, V2LoanPayment } from '@/types/v2/loan'

const BASE_PATH = '/v2/staff/loans'

export async function list(params?: {
  status?: string
  applicant_id?: string
  from?: string
  to?: string
  page?: number
  per_page?: number
}): Promise<V2ApiResponse<{ loans: V2Loan[]; total?: number }>> {
  const response = await api.get<V2ApiResponse<{ loans: V2Loan[]; total?: number }>>(BASE_PATH, { params })
  return response.data
}

export async function get(id: string): Promise<V2ApiResponse<V2Loan>> {
  const response = await api.get<V2ApiResponse<V2Loan>>(`${BASE_PATH}/${id}`)
  return response.data
}

export async function recordPayment(
  id: string,
  payload: { amount: number; channel: string; provider_reference?: string; paid_at?: string },
): Promise<V2ApiResponse<V2LoanPayment>> {
  const response = await api.post<V2ApiResponse<V2LoanPayment>>(`${BASE_PATH}/${id}/payments`, payload)
  return response.data
}

export async function approveExtension(
  loanId: string,
  extensionId: string,
): Promise<V2ApiResponse<V2LoanExtension>> {
  const response = await api.post<V2ApiResponse<V2LoanExtension>>(
    `${BASE_PATH}/${loanId}/extensions/${extensionId}/approve`,
  )
  return response.data
}

export default {
  list,
  get,
  recordPayment,
  approveExtension,
}
