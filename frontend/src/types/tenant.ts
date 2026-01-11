// Tenant Types - White-Label Configuration

export interface Branding {
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
  button_style: 'rounded' | 'pill' | 'square'
  custom_css: string | null
}

export interface WebhookConfig {
  url: string
  secret_key: string
  retry_count: number
  timeout_seconds: number
  events: string[]
}

export interface TenantSettings {
  otp_provider: 'twilio' | 'messagebird'
  kyc_provider: string
  max_loan_amount: number
  min_loan_amount: number
  currency: string
  timezone: string
}

export interface Tenant {
  id: string
  name: string
  slug: string
  branding: Branding
  webhook_config: WebhookConfig
  settings: TenantSettings
  is_active: boolean
  created_at: string
  updated_at: string
}

export interface TenantConfig {
  tenant: Tenant
  products: Product[]
}

export interface Product {
  id: string
  tenant_id: string
  name: string
  code?: string
  type: ProductType
  description?: string
  icon?: string
  // New flat structure
  min_amount?: number
  max_amount?: number
  min_term_months?: number
  max_term_months?: number
  interest_rate?: number
  opening_commission?: number
  late_fee_rate?: number
  payment_frequencies?: PaymentFrequency[]
  required_documents?: (RequiredDocument | string)[]
  eligibility_rules?: Record<string, unknown>
  term_config?: Record<string, TermConfig>
  // Legacy nested structure (backwards compatibility)
  rules?: ProductRules
  required_docs?: (RequiredDocument | string)[]
  extra_fields?: DynamicField[]
  is_active: boolean
  applications_count?: number
  created_at?: string
  updated_at?: string
}

export type ProductType = 'PERSONAL' | 'AUTO' | 'HIPOTECARIO' | 'PYME' | 'NOMINA' | 'ARRENDAMIENTO'

export interface ProductRules {
  min_amount: number
  max_amount: number
  min_term?: number
  max_term?: number
  min_term_months?: number
  max_term_months?: number
  annual_rate?: number
  interest_rate?: number
  opening_commission?: number
  amortization_type?: AmortizationType
  payment_frequencies?: PaymentFrequency[]
  term_config?: Record<string, TermConfig>
  min_age?: number
  max_age?: number
  min_income?: number
}

export type AmortizationType = 'FRENCH' | 'GERMAN' | 'AMERICAN' | 'BULLET'

export type PaymentFrequency = 'SEMANAL' | 'WEEKLY' | 'BIWEEKLY' | 'QUINCENAL' | 'MONTHLY' | 'MENSUAL'

export interface TermConfig {
  available_terms: number[]
}

export interface RequiredDocument {
  type: DocumentType
  required: boolean
  description: string
}

export type DocumentType =
  | 'INE_FRONT'
  | 'INE_BACK'
  | 'CURP'
  | 'RFC_CSF'
  | 'RFC_CONSTANCIA'
  | 'PROOF_ADDRESS'
  | 'PROOF_INCOME'
  | 'PAYSLIP_1'
  | 'PAYSLIP_2'
  | 'PAYSLIP_3'
  | 'PAYROLL_STUBS'
  | 'BANK_STATEMENT'
  | 'BANK_STATEMENTS'
  | 'VEHICLE_INVOICE'
  | 'ACTA_CONSTITUTIVA'
  | 'COTIZACION'
  | 'FACTURAS'
  | 'SIGNATURE'
  | 'SELFIE'

export interface DynamicField {
  name: string
  label: string
  type: 'text' | 'number' | 'select' | 'currency' | 'boolean' | 'date'
  options?: { value: string; label: string }[]
  required: boolean
  min?: number
  max?: number
  maxLength?: number
  pattern?: string
  default?: any
}
