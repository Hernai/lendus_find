/**
 * Modelo de vista del detalle de solicitud que consume AdminApplicationDetail.vue.
 *
 * Extraído del componente (antes definido inline) para reducir el tamaño del
 * "god-component" y compartir el tipo con el mapeo puro (ver mapApplicationDetail.ts).
 * Es el shape que la plantilla y los computeds del detalle esperan — NO es la
 * respuesta cruda del API (esa es V2ApplicationDetail); el mapeo traduce de una a otra.
 */

import type { ClabeValidationSummary } from '@/modules/admin/components/application-detail/BankAccountsSection.vue'

/**
 * Claves de campo verificables manualmente (verify/reject/unverify) en el detalle.
 * Compartido entre el padre (verifyData/openRejectDataModal/openUnverifyModal) y
 * ApplicantDataSection (tipa el payload de sus emits). Antes era un tipo local en
 * AdminApplicationDetail.vue llamado `VerifiableField`, que colisionaba con el
 * COMPONENTE homónimo; se renombró a VerifiableFieldKey al compartirlo.
 */
export type VerifiableFieldKey =
  | 'first_name' | 'last_name_1' | 'last_name_2' | 'curp' | 'rfc' | 'ine_clave'
  | 'birth_date' | 'phone' | 'email' | 'address' | 'employment'
  | 'passport_number' | 'passport_issue_date' | 'passport_expiry_date'

/**
 * Comparación de la verificación de INE (datos confirmados vs OCR vs RENAPO).
 * Es el shape que retorna el computed ineComparison del padre; lo consumen
 * ApplicantDataSection (botón/indicador) e IneVerificationModal (tabla de detalle).
 */
/** Analista/usuario staff (lista de asignación). Compartido padre ↔ AssignAnalystModal. */
export interface StaffUser {
  id: string
  name: string
  email: string
  role: string
}

export interface IneComparison {
  rows: Array<{ label: string; confirmed: string; ocr: string; renapo: string; diff: boolean }>
  ineValid: boolean | null
  curpValid: boolean | null
  verifiedAt: string | null
  hasDiffs: boolean
}

export interface Document {
  id: string
  type: string
  name: string
  status: 'PENDING' | 'APPROVED' | 'REJECTED'
  rejection_reason?: string
  rejection_comment?: string
  uploaded_at?: string
  reviewed_at?: string
  mime_type?: string
  metadata?: {
    kyc_validated?: boolean
    face_match_passed?: boolean
    face_match_score?: number
    validation_method?: string
    source?: string
    [key: string]: unknown
  }
  is_kyc_locked?: boolean
}

export interface Reference {
  id: string
  full_name: string
  relationship: string
  phone: string
  verified: boolean
  verification_result?: 'VERIFIED' | 'NOT_VERIFIED' | 'NO_ANSWER'
  verification_notes?: string
  verified_at?: string
}

export interface BankAccount {
  id: string
  type: string
  bank_name: string
  bank_code: string
  clabe: string
  account_type: string
  account_type_label?: string
  holder_name: string
  holder_rfc?: string
  is_primary: boolean
  is_own_account: boolean
  is_verified: boolean
  verified_at?: string | null
  verification_method?: string | null
  verified_by_nubarium?: boolean
  clabe_validation?: ClabeValidationSummary | null
  created_at?: string
}

export interface ApplicationCompleteness {
  personal_data: boolean
  address: boolean
  employment: boolean
  documents: { uploaded: number; required: number; approved: number }
  references: { count: number; verified: number }
  signature: boolean
}

export interface Application {
  id: string
  folio: string
  status: string
  created_at: string
  updated_at: string
  assigned_to?: string
  online_loans_count?: number | null
  completeness: ApplicationCompleteness
  required_documents: string[] | { nationals: string[]; foreigners: string[] }
  applicant: {
    id: string
    full_name: string
    first_name: string
    last_name_1: string
    last_name_2: string
    email: string
    phone: string
    curp: string
    rfc: string
    ine_clave?: string
    ine_ocr?: string
    ine_folio?: string
    ine_verification?: {
      ocr?: { nombres?: string; apellido_paterno?: string; apellido_materno?: string; curp?: string } | null
      renapo?: { nombres?: string; apellido_paterno?: string; apellido_materno?: string } | null
      ine_valid?: boolean | null
      curp_valid?: boolean | null
      verified_at?: string | null
    } | null
    birth_date: string
    birth_state?: string
    nationality: string
    // nationality_info: subcampos opcionales — el backend puede no enviar bandera/código todavía.
    nationality_info?: {
      code?: string
      name?: string
      flag?: string
    } | null
    gender: string
    passport_number?: string
    passport_issue_date?: string
    passport_expiry_date?: string
    marital_status?: string
    marital_status_label?: string
    education_level?: string
    education_level_label?: string
  }
  address: {
    street: string
    ext_number: string
    int_number?: string
    neighborhood: string
    city?: string
    postal_code: string
    municipality: string
    state: string
    housing_type: string
    housing_type_label?: string
    years_at_address?: number
    months_at_address?: number
  }
  employment: {
    employment_type?: string
    company_name?: string
    position?: string
    monthly_income: number
    income_range_label?: string
    seniority_months?: number
  }
  loan: {
    product_name: string
    requested_amount: number
    approved_amount?: number
    term_months: number
    payment_frequency: string
    interest_rate: number
    monthly_payment: number
    total_to_pay: number
    purpose: string
    purpose_label?: string
    // term_in_days: bandera que indica si el préstamo usa plazo en días (productos short-term).
    term_in_days?: boolean
    // requested_term_days / requested_term_months: el backend manda ambos según el formato del producto.
    requested_term_days?: number
    requested_term_months?: number
  }
  documents: Document[]
  references: Reference[]
  bank_accounts: BankAccount[]
  notes: { id: string; text: string; author: string; created_at: string }[]
  timeline: {
    id: string
    action: string
    description: string
    author: string
    created_at: string
    // metadata: el backend puede mandar campos como null o ausentes; aceptamos string | null en los opcionales.
    metadata?: {
      ip_address?: string | null
      user_agent?: string | null
      location?: string | null
      old_value?: string | null
      new_value?: string | null
      changes?: Record<string, string> | null
      reason?: string | null
      field_name?: string | null
      field_label?: string | null
      event_type?: string | null
      action?: string | null
      document_type?: string | null
      document_type_label?: string | null
      step_number?: number | null
      step_label?: string | null
      changed_fields?: string[] | null
      bank_name?: string | null
      reference_type?: string | null
      employment_type?: string | null
      postal_code?: string | null
      score?: number | null
      is_valid?: boolean | null
      matched?: boolean | null
      geolocation?: {
        latitude?: number
        longitude?: number
        accuracy?: number
        timestamp?: number
      }
      [key: string]: unknown
    }
  }[]
  signature?: {
    has_signed: boolean
    signature_base64?: string
    signature_date?: string
    signature_ip?: string
  }
  verification?: {
    phone_verified: boolean
    phone_verified_at?: string
    email_verified: boolean
    email_verified_at?: string
    identity_verified: boolean
    identity_verified_at?: string
    address_verified: boolean
    employment_verified: boolean
  }
  field_verifications?: Record<string, {
    verified: boolean
    method: string | null
    method_label?: string | null
    verified_at?: string | null
    verified_by?: string | null
    notes?: string | null
    rejection_reason?: string | null
    status?: string
    is_locked?: boolean
    metadata?: Record<string, unknown>
  }>
}
