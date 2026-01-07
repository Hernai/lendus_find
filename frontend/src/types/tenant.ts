// Tenant Types - White-Label Configuration

export interface Branding {
  primary_color: string
  secondary_color: string
  accent_color: string
  logo_url: string
  favicon_url: string
  font_family: string
  border_radius: string
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
  type: ProductType
  description?: string
  icon?: string
  rules: ProductRules
  required_docs: RequiredDocument[]
  extra_fields: DynamicField[]
  is_active: boolean
}

export type ProductType = 'SIMPLE' | 'PERSONAL' | 'PAYROLL' | 'SME' | 'LEASING' | 'FACTORING' | 'NOMINA' | 'ARRENDAMIENTO' | 'HIPOTECARIO' | 'PYME'

export interface ProductRules {
  min_amount: number
  max_amount: number
  min_term_months: number
  max_term_months: number
  annual_rate: number
  opening_commission: number
  amortization_type: 'FRENCH' | 'GERMAN' | 'AMERICAN'
  payment_frequencies: PaymentFrequency[]
  min_age: number
  max_age: number
  min_income: number
}

export type PaymentFrequency = 'WEEKLY' | 'BIWEEKLY' | 'MONTHLY'

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
  | 'PROOF_ADDRESS'
  | 'PROOF_INCOME'
  | 'SIGNATURE'

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
