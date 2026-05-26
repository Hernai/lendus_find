/**
 * V2 Loan portfolio types (módulo opt-in MoneyCapital).
 * Espejo de Loan, LoanPayment, LoanExtension y LoanReward del backend.
 */

export type V2LoanStatus = 'DISBURSED' | 'ACTIVE' | 'COMPLETED' | 'DEFAULT' | 'RESTRUCTURED'

export interface V2LoanPayment {
  id: string
  loan_id: string
  amount: number
  paid_at: string | null
  status: string
  channel: string
  provider: string | null
  provider_reference: string | null
  metadata?: Record<string, unknown> | null
  created_at?: string
}

export interface V2LoanExtension {
  id: string
  loan_id: string
  days_added: number
  fee_amount: number
  previous_due_date: string
  new_due_date: string
  requested_at: string
  approved_at: string | null
  status: 'PENDING' | 'APPROVED' | 'REJECTED'
  approved_by: string | null
}

export interface V2LoanReward {
  id: string
  type: 'PUNCTUAL_PAYMENT' | 'REFERRAL' | 'MILESTONE'
  points: number
  description: string | null
  earned_at: string
  redeemed_at: string | null
}

export interface V2Loan {
  id: string
  tenant_id: string
  application_id: string | null
  applicant_account_id: string
  person_id: string | null
  bank_account_id: string | null
  principal_amount: number
  interest_rate: number
  term_days: number
  disbursed_at: string | null
  due_date: string
  outstanding_balance: number
  total_to_pay: number
  paid_amount: number
  late_fee_accrued: number
  status: V2LoanStatus
  disbursement_provider: string | null
  disbursement_reference: string | null
  payments?: V2LoanPayment[]
  extensions?: V2LoanExtension[]
  rewards?: V2LoanReward[]
  created_at: string
  updated_at: string
}

export interface V2LoanExtensionQuote {
  days: number
  fee: number
  new_due_date: string
}
