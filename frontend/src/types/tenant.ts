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
  amortization_type: AmortizationType
  payment_frequencies: PaymentFrequency[]
  min_age: number
  max_age: number
  min_income: number
}

export type AmortizationType = 'FRENCH' | 'GERMAN' | 'AMERICAN' | 'BULLET'

export type PaymentFrequency = 'WEEKLY' | 'BIWEEKLY' | 'QUINCENAL' | 'MONTHLY' | 'MENSUAL'

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
  | 'PAYROLL_STUBS'
  | 'BANK_STATEMENTS'
  | 'ACTA_CONSTITUTIVA'
  | 'COTIZACION'
  | 'FACTURAS'
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
