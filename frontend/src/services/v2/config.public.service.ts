/**
 * V2 Public Config Service
 *
 * Handles public tenant configuration retrieval.
 * Endpoint: /api/v2/config
 */

import { api } from '../api'
import type { V2ApiResponse } from '@/types/v2'

// =====================================================
// Types
// =====================================================

export interface V2TenantBranding {
  primary_color: string
  secondary_color: string
  accent_color: string
  background_color: string
  text_color: string
  logo_url: string | null
  logo_dark_url: string | null
  favicon_url: string | null
  login_background_url: string | null
  font_family: string
  heading_font_family: string | null
  border_radius: string
  button_style: string
  custom_css: string | null
}

export interface V2TenantSettings {
  otp_provider: string
  kyc_provider: string | null
  max_loan_amount: number
  min_loan_amount: number
  currency: string
  timezone: string
}

export interface V2TenantConfig {
  id: string
  name: string
  slug: string
  branding: V2TenantBranding
  webhook_config: Record<string, unknown> | null
  settings: V2TenantSettings
  is_active: boolean
  created_at: string | null
  updated_at: string | null
}

export interface V2ProductRules {
  min_amount: number
  max_amount: number
  min_term_months: number
  max_term_months: number
  annual_rate: number
  opening_commission: number
  amortization_type: string
  payment_frequencies: string[]
  term_config: Record<string, unknown> | null
  min_age: number
  max_age: number
  min_income: number
}

export interface V2RequiredDoc {
  type: string
  required: boolean
  description: string
}

export interface V2ProductConfig {
  id: string
  tenant_id: string
  name: string
  code: string
  type: string
  description: string | null
  icon: string
  rules: V2ProductRules
  required_docs: V2RequiredDoc[]
  extra_fields: Record<string, unknown>[]
  eligibility_rules: Record<string, unknown>[]
  late_fee_rate: number | null
  display_order: number
  is_active: boolean
}

export interface V2EnumOption {
  value: string
  label: string
}

export interface V2ConfigOptions {
  // Profile enums
  gender: V2EnumOption[]
  maritalStatus: V2EnumOption[]
  educationLevel: V2EnumOption[]
  housingType: V2EnumOption[]
  employmentType: V2EnumOption[]
  bankAccountType: V2EnumOption[]
  // Reference enums
  referenceType: V2EnumOption[]
  relationship: V2EnumOption[]
  relationshipFamily: V2EnumOption[]
  relationshipNonFamily: V2EnumOption[]
  // Document and ID enums
  documentType: V2EnumOption[]
  idType: V2EnumOption[]
  // Application enums
  loanPurpose: V2EnumOption[]
  paymentFrequency: V2EnumOption[]
  applicationStatus: V2EnumOption[]
  // Product enums
  productType: V2EnumOption[]
  // Admin enums
  userType: V2EnumOption[]
  rejectionReason: V2EnumOption[]
  documentRejectionReason: V2EnumOption[]
}

export interface V2ConfigResponse {
  tenant: V2TenantConfig
  products: V2ProductConfig[]
  options: V2ConfigOptions
}

// =====================================================
// API Functions
// =====================================================

/**
 * Get tenant configuration.
 */
export async function getConfig(): Promise<V2ApiResponse<V2ConfigResponse>> {
  const response = await api.get<V2ApiResponse<V2ConfigResponse>>('/v2/config')
  return response.data
}

export default {
  getConfig,
}
