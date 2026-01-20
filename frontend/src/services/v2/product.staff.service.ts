/**
 * V2 Staff Product Management Service
 *
 * Handles product CRUD operations for staff.
 * All endpoints are under /api/v2/staff/products
 */

import { api } from '../api'
import type { V2ApiResponse } from '@/types/v2'

// =====================================================
// Types
// =====================================================

export interface V2TermConfig {
  available_terms: number[]
}

export interface V2Product {
  id: string
  name: string
  code: string
  type: 'PERSONAL' | 'AUTO' | 'HIPOTECARIO' | 'PYME' | 'NOMINA' | 'ARRENDAMIENTO'
  description: string | null
  min_amount: number
  max_amount: number
  min_term_months: number
  max_term_months: number
  interest_rate: number
  opening_commission: number
  late_fee_rate: number
  payment_frequencies: string[]
  term_config: Record<string, V2TermConfig> | null
  required_documents: string[]
  eligibility_rules: Record<string, unknown>
  is_active: boolean
  display_order: number
  applications_count: number
  created_at: string
  updated_at: string
}

export interface V2ProductCreatePayload {
  name: string
  code: string
  type: V2Product['type']
  description?: string
  min_amount: number
  max_amount: number
  min_term_months: number
  max_term_months: number
  interest_rate: number
  opening_commission?: number
  late_fee_rate?: number
  payment_frequencies: string[]
  term_config?: Record<string, V2TermConfig>
  required_documents?: string[]
  eligibility_rules?: Record<string, unknown>
  is_active?: boolean
}

export interface V2ProductUpdatePayload {
  name?: string
  code?: string
  type?: V2Product['type']
  description?: string
  min_amount?: number
  max_amount?: number
  min_term_months?: number
  max_term_months?: number
  interest_rate?: number
  opening_commission?: number
  late_fee_rate?: number
  payment_frequencies?: string[]
  term_config?: Record<string, V2TermConfig>
  required_documents?: string[]
  eligibility_rules?: Record<string, unknown>
  is_active?: boolean
}

export interface V2ProductFilters {
  search?: string
  active?: boolean
  type?: string
  sort_by?: string
  sort_dir?: 'asc' | 'desc'
  per_page?: number
  page?: number
}

/**
 * Response structure for paginated product list.
 */
export interface V2ProductListResponse {
  products: V2Product[]
  meta: {
    current_page: number
    from: number | null
    last_page: number
    per_page: number
    to: number | null
    total: number
  }
}

const BASE_PATH = '/v2/staff/products'

// =====================================================
// CRUD Operations
// =====================================================

/**
 * List all products with optional filters.
 */
export async function list(filters?: V2ProductFilters): Promise<V2ApiResponse<V2ProductListResponse>> {
  const response = await api.get<V2ApiResponse<V2ProductListResponse>>(BASE_PATH, { params: filters })
  return response.data
}

/**
 * Create a new product.
 */
export async function create(payload: V2ProductCreatePayload): Promise<V2ApiResponse<{ product: V2Product }>> {
  const response = await api.post<V2ApiResponse<{ product: V2Product }>>(BASE_PATH, payload)
  return response.data
}

/**
 * Get a specific product by ID.
 */
export async function get(id: string): Promise<V2ApiResponse<{ product: V2Product }>> {
  const response = await api.get<V2ApiResponse<{ product: V2Product }>>(`${BASE_PATH}/${id}`)
  return response.data
}

/**
 * Update a product.
 */
export async function update(id: string, payload: V2ProductUpdatePayload): Promise<V2ApiResponse<{ product: V2Product }>> {
  const response = await api.put<V2ApiResponse<{ product: V2Product }>>(`${BASE_PATH}/${id}`, payload)
  return response.data
}

/**
 * Delete a product.
 */
export async function remove(id: string): Promise<V2ApiResponse<{ message: string }>> {
  const response = await api.delete<V2ApiResponse<{ message: string }>>(`${BASE_PATH}/${id}`)
  return response.data
}

// =====================================================
// Export
// =====================================================

export default {
  list,
  create,
  get,
  update,
  remove,
}
