/**
 * V2 Simulator Service
 *
 * Handles loan simulation calculations.
 * All endpoints are under /api/v2/simulator
 */

import { api } from '../api'
import type { V2ApiResponse } from '@/types/v2'

// =====================================================
// Types
// =====================================================

export interface V2SimulatorProduct {
  id: string
  name: string
  type: string
  description: string | null
  icon: string | null
  min_amount: number
  max_amount: number
  min_term_months: number
  max_term_months: number
  annual_rate: number
  opening_commission: number
  payment_frequencies: string[]
}

export interface V2SimulationPayload {
  product_id: string
  amount: number
  term_months: number
  payment_frequency: 'WEEKLY' | 'BIWEEKLY' | 'MONTHLY'
}

export interface V2SimulationResult {
  product_id: string
  product_name: string
  amount: number
  term_months: number
  payment_frequency: string
  annual_rate: number
  monthly_rate: number
  opening_commission: number
  opening_commission_amount: number
  net_amount: number
  payment_amount: number
  total_interest: number
  total_amount: number
  cat: number
}

export interface V2AmortizationPayload {
  amount: number
  annual_rate: number
  term_months: number
  payment_frequency: 'WEEKLY' | 'BIWEEKLY' | 'MONTHLY'
}

export interface V2AmortizationRow {
  period: number
  date: string
  payment: number
  principal: number
  interest: number
  balance: number
}

// =====================================================
// API Functions
// =====================================================

/**
 * Get available products for simulation.
 */
export async function getProducts(): Promise<V2ApiResponse<{ products: V2SimulatorProduct[] }>> {
  const response = await api.get<V2ApiResponse<{ products: V2SimulatorProduct[] }>>('/v2/simulator/products')
  return response.data
}

/**
 * Calculate loan simulation.
 */
export async function calculate(payload: V2SimulationPayload): Promise<V2ApiResponse<{ simulation: V2SimulationResult }>> {
  const response = await api.post<V2ApiResponse<{ simulation: V2SimulationResult }>>('/v2/simulator/calculate', payload)
  return response.data
}

/**
 * Get amortization table.
 */
export async function getAmortization(payload: V2AmortizationPayload): Promise<V2ApiResponse<{ amortization: V2AmortizationRow[] }>> {
  const response = await api.post<V2ApiResponse<{ amortization: V2AmortizationRow[] }>>('/v2/simulator/amortization', payload)
  return response.data
}

export default {
  getProducts,
  calculate,
  getAmortization,
}
