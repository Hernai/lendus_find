// Application Types - Credit Application

import type { PaymentFrequency, DocumentType } from './tenant'

export interface Application {
  id: string
  tenant_id: string
  applicant_id: string
  product_id: string
  folio: string
  status: ApplicationStatus
  requested_amount: number
  approved_amount: number | null
  term_months: number
  payment_frequency: PaymentFrequency
  interest_rate: number
  opening_commission: number
  monthly_payment: number
  total_to_pay: number
  purpose: string | null
  purpose_description: string | null
  dynamic_data: Record<string, any>
  simulation_data: SimulationResult | null
  submitted_at: string | null
  approved_at: string | null
  webhook_sent_at: string | null
  created_at: string
  updated_at: string
  product?: {
    id: string
    name: string
    type: string
  }
}

export type ApplicationStatus =
  | 'DRAFT'
  | 'SUBMITTED'
  | 'IN_REVIEW'
  | 'DOCS_PENDING'
  | 'APPROVED'
  | 'REJECTED'
  | 'SYNCED'

export interface SimulationResult {
  requested_amount: number
  term_months: number
  payment_frequency: PaymentFrequency
  total_periods: number
  annual_rate: number
  periodic_rate: number
  opening_commission: number
  periodic_payment: number
  total_interest: number
  total_amount: number
  cat: number
  amortization_table: AmortizationRow[]
}

export interface AmortizationRow {
  number: number
  date: string
  opening_balance: number
  principal: number
  interest: number
  iva: number
  payment: number
  closing_balance: number
}

export interface SimulationParams {
  product_id: string
  amount: number
  term_months: number
  payment_frequency: PaymentFrequency
}

export interface Document {
  id: string
  tenant_id: string
  applicant_id: string
  application_id: string | null
  type: DocumentType
  file_path: string
  mime_type: string
  file_size: number
  ocr_data: Record<string, any> | null
  status: DocumentStatus
  rejection_reason: string | null
  created_at: string
  updated_at: string
}

export type DocumentStatus = 'PENDING' | 'PROCESSING' | 'VERIFIED' | 'REJECTED'

export interface CreateApplicationParams {
  product_id: string
  requested_amount: number
  term_months: number
  payment_frequency: PaymentFrequency
}

export interface UpdateApplicationParams {
  dynamic_data?: Record<string, any>
  requested_amount?: number
  term_months?: number
  payment_frequency?: PaymentFrequency
  purpose?: string
  purpose_description?: string
}
