import { api } from './api'

export interface Product {
  id: string
  name: string
  code: string
  type: string
  description: string
  min_amount: number
  max_amount: number
  min_term_months: number
  max_term_months: number
  interest_rate: number
  opening_commission: number
  payment_frequencies: string[]
  required_documents: string[]
  is_active: boolean
}

export interface CalculatePayload {
  product_id?: string
  amount: number
  term_months: number
  payment_frequency: 'QUINCENAL' | 'MENSUAL'
  interest_rate?: number
}

export interface LoanCalculation {
  amount: number
  term_months: number
  payment_frequency: string
  interest_rate: number
  monthly_payment: number
  total_payments: number
  total_to_pay: number
  total_interest: number
  opening_commission: number
  cat?: number
}

export interface AmortizationRow {
  period: number
  payment: number
  principal: number
  interest: number
  balance: number
}

export interface AmortizationPayload {
  amount: number
  term_months: number
  payment_frequency: 'QUINCENAL' | 'MENSUAL'
  interest_rate: number
}

const simulatorService = {
  /**
   * Get available products for simulation
   */
  getProducts: async () => {
    const response = await api.get<{ data: Product[] }>('/simulator/products')
    return response.data.data
  },

  /**
   * Calculate loan terms
   */
  calculate: async (data: CalculatePayload) => {
    const response = await api.post<{ data: LoanCalculation }>('/simulator/calculate', data)
    return response.data.data
  },

  /**
   * Get amortization table
   */
  getAmortization: async (data: AmortizationPayload) => {
    const response = await api.get<{ data: { schedule: AmortizationRow[] } }>('/simulator/amortization', {
      params: data,
    })
    return response.data.data.schedule
  },
}

export default simulatorService
