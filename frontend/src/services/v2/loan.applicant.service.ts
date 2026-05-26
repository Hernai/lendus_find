/**
 * V2 Applicant Loan Service — módulo opt-in (tenant.features.loan_portfolio).
 * Endpoints bajo /api/v2/applicant/loans
 */

import { api } from '../api'
import type { V2ApiResponse } from '@/types/v2'
import type { V2Loan, V2LoanExtension, V2LoanExtensionQuote, V2LoanPayment } from '@/types/v2/loan'

const BASE_PATH = '/v2/applicant/loans'

export async function list(params?: { status?: string }): Promise<V2ApiResponse<{ loans: V2Loan[] }>> {
  const response = await api.get<V2ApiResponse<{ loans: V2Loan[] }>>(BASE_PATH, { params })
  return response.data
}

export async function get(id: string): Promise<V2ApiResponse<V2Loan>> {
  const response = await api.get<V2ApiResponse<V2Loan>>(`${BASE_PATH}/${id}`)
  return response.data
}

export async function quoteExtension(
  id: string,
  days: number,
): Promise<V2ApiResponse<V2LoanExtensionQuote>> {
  const response = await api.post<V2ApiResponse<V2LoanExtensionQuote>>(
    `${BASE_PATH}/${id}/extension/quote`,
    { days },
  )
  return response.data
}

export async function requestExtension(
  id: string,
  days: number,
): Promise<V2ApiResponse<V2LoanExtension>> {
  const response = await api.post<V2ApiResponse<V2LoanExtension>>(
    `${BASE_PATH}/${id}/extension`,
    { days },
  )
  return response.data
}

export async function pay(
  id: string,
  payload: { amount: number; channel?: string },
): Promise<V2ApiResponse<{ payment_url?: string; reference?: string; payment?: V2LoanPayment }>> {
  const response = await api.post<V2ApiResponse<{ payment_url?: string; reference?: string; payment?: V2LoanPayment }>>(
    `${BASE_PATH}/${id}/pay`,
    payload,
  )
  return response.data
}

export default {
  list,
  get,
  quoteExtension,
  requestExtension,
  pay,
}
